package runner

import (
	"context"
	"errors"
	"fmt"
	"net"
	"time"

	"montry/apps/poller/internal/checks"
	"montry/apps/poller/internal/laravel"
	"montry/apps/poller/internal/observability"
)

type ResultPublisher interface {
	Publish(ctx context.Context, result checks.CheckResult) error
}

type ChannelResultPublisher struct {
	results chan<- checks.CheckResult
}

func NewChannelResultPublisher(results chan<- checks.CheckResult) *ChannelResultPublisher {
	return &ChannelResultPublisher{results: results}
}

func (p *ChannelResultPublisher) Publish(ctx context.Context, result checks.CheckResult) error {
	select {
	case <-ctx.Done():
		return ctx.Err()
	case p.results <- result:
		return nil
	}
}

type PublisherLogger interface {
	Error(message string, fields ...any)
}

type ResultPublisherConfig struct {
	RetryAttempts int
	RetryDelay    time.Duration
	Logger        PublisherLogger
	Metrics       *observability.Metrics
	Tracer        *observability.Tracer
}

type LaravelResultPublisher struct {
	client        laravel.LaravelClient
	retryAttempts int
	retryDelay    time.Duration
	logger        PublisherLogger
	metrics       *observability.Metrics
	tracer        *observability.Tracer
}

func NewLaravelResultPublisher(client laravel.LaravelClient, cfg ResultPublisherConfig) (*LaravelResultPublisher, error) {
	if client == nil {
		return nil, fmt.Errorf("laravel client is required")
	}

	if cfg.RetryAttempts < 1 {
		return nil, fmt.Errorf("retry attempts must be greater than 0")
	}

	if cfg.RetryDelay <= 0 {
		return nil, fmt.Errorf("retry delay must be greater than 0")
	}

	return &LaravelResultPublisher{
		client:        client,
		retryAttempts: cfg.RetryAttempts,
		retryDelay:    cfg.RetryDelay,
		logger:        cfg.Logger,
		metrics:       cfg.Metrics,
		tracer:        cfg.Tracer,
	}, nil
}

func (p *LaravelResultPublisher) Publish(ctx context.Context, result checks.CheckResult) error {
	span := p.startSpan(result)
	if span != nil {
		result.TraceParent = span.TraceParent()
	}

	var lastErr error

	for attempt := 1; attempt <= p.retryAttempts; attempt++ {
		if err := ctx.Err(); err != nil {
			p.endSpan(ctx, span, "STATUS_CODE_ERROR")
			return err
		}

		err := p.client.SubmitCheckResult(ctx, result)
		if err == nil {
			p.recordDelivery(result.Type, "success")
			p.endSpan(ctx, span, "STATUS_CODE_OK")
			return nil
		}

		lastErr = err
		if attempt == p.retryAttempts || !isTemporaryPublishError(err) {
			p.recordDelivery(result.Type, "failed")
			p.logFailure(result, attempt, err)
			p.endSpan(ctx, span, "STATUS_CODE_ERROR")
			return err
		}

		p.recordDelivery(result.Type, "retry")
		delay := p.retryDelay * time.Duration(attempt)
		timer := time.NewTimer(delay)
		select {
		case <-ctx.Done():
			timer.Stop()
			p.endSpan(ctx, span, "STATUS_CODE_ERROR")
			return ctx.Err()
		case <-timer.C:
		}
	}

	if lastErr != nil {
		p.recordDelivery(result.Type, "failed")
		p.logFailure(result, p.retryAttempts, lastErr)
		p.endSpan(ctx, span, "STATUS_CODE_ERROR")
	}

	return lastErr
}

func (p *LaravelResultPublisher) recordDelivery(checkType string, status string) {
	if p.metrics == nil {
		return
	}

	p.metrics.IncResultDelivery(checkType, status)
}

func (p *LaravelResultPublisher) logFailure(result checks.CheckResult, attempt int, err error) {
	if p.logger == nil {
		return
	}

	p.logger.Error(
		"failed to publish check result",
		"event_id", result.EventID,
		"monitor_id", result.MonitorID,
		"check_type", result.Type,
		"correlation_id", result.CorrelationID,
		"attempt", attempt,
		"error", err,
	)
}

func isTemporaryPublishError(err error) bool {
	if err == nil {
		return false
	}

	var apiErr *laravel.APIError
	if laravel.AsAPIError(err, &apiErr) {
		return apiErr.Temporary()
	}

	var netErr net.Error
	return errors.As(err, &netErr) && netErr.Timeout()
}

func (p *LaravelResultPublisher) startSpan(result checks.CheckResult) *observability.Span {
	if p.tracer == nil {
		return nil
	}

	return p.tracer.StartSpan("laravel.submit_check_result", result.TraceParent, observability.SpanKindClient, map[string]any{
		"http.request.method": "POST",
		"url.path":            "/internal/check-results",
		"check.type":          result.Type,
		"check.status":        string(result.Status),
		"event.id":            result.EventID,
		"monitor.id":          result.MonitorID,
	})
}

func (p *LaravelResultPublisher) endSpan(ctx context.Context, span *observability.Span, status string) {
	if span == nil {
		return
	}

	span.End(ctx, status)
}
