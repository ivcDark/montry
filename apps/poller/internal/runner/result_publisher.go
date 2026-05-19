package runner

import (
	"context"
	"errors"
	"fmt"
	"net"
	"time"

	"montry/apps/poller/internal/checks"
	"montry/apps/poller/internal/laravel"
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
}

type LaravelResultPublisher struct {
	client        laravel.LaravelClient
	retryAttempts int
	retryDelay    time.Duration
	logger        PublisherLogger
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
	}, nil
}

func (p *LaravelResultPublisher) Publish(ctx context.Context, result checks.CheckResult) error {
	var lastErr error

	for attempt := 1; attempt <= p.retryAttempts; attempt++ {
		if err := ctx.Err(); err != nil {
			return err
		}

		err := p.client.SubmitCheckResult(ctx, result)
		if err == nil {
			return nil
		}

		lastErr = err
		if attempt == p.retryAttempts || !isTemporaryPublishError(err) {
			p.logFailure(result, attempt, err)
			return err
		}

		delay := p.retryDelay * time.Duration(attempt)
		timer := time.NewTimer(delay)
		select {
		case <-ctx.Done():
			timer.Stop()
			return ctx.Err()
		case <-timer.C:
		}
	}

	if lastErr != nil {
		p.logFailure(result, p.retryAttempts, lastErr)
	}

	return lastErr
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
