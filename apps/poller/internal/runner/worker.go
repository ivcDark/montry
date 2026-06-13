package runner

import (
	"context"
	"errors"
	"fmt"
	"time"

	"montry/apps/poller/internal/checks"
	"montry/apps/poller/internal/jobs"
	"montry/apps/poller/internal/observability"
)

type Worker struct {
	id           int
	registry     checks.Registry
	publisher    ResultPublisher
	checkTimeout time.Duration
	metrics      *observability.Metrics
	tracer       *observability.Tracer
	sentry       *observability.SentryReporter
}

func NewWorker(id int, registry checks.Registry, publisher ResultPublisher, checkTimeout time.Duration, metrics *observability.Metrics, tracer *observability.Tracer, sentry *observability.SentryReporter) Worker {
	return Worker{
		id:           id,
		registry:     registry,
		publisher:    publisher,
		checkTimeout: checkTimeout,
		metrics:      metrics,
		tracer:       tracer,
		sentry:       sentry,
	}
}

func (w Worker) Run(ctx context.Context, jobCh <-chan jobs.CheckJob) {
	for {
		select {
		case <-ctx.Done():
			return
		case job, ok := <-jobCh:
			if !ok {
				return
			}

			w.handleJob(ctx, job)
		}
	}
}

func (w Worker) handleJob(ctx context.Context, job jobs.CheckJob) {
	span := w.startSpan(job)
	if span != nil {
		job.TraceParent = span.TraceParent()
	}

	result := w.runCheck(ctx, job)
	if w.metrics != nil {
		w.metrics.IncJobs(job.Type, string(job.Source), string(result.Status))
		w.metrics.ObserveCheckDuration(job.Type, string(result.Status), result.Duration.Seconds())
	}
	if span != nil {
		span.End(ctx, statusCodeFromResult(result.Status))
	}
	_ = w.publisher.Publish(ctx, result)
}

func (w Worker) runCheck(ctx context.Context, job jobs.CheckJob) (result checks.CheckResult) {
	startedAt := time.Now().UTC()

	result = baseResult(job, startedAt)
	defer func() {
		if recovered := recover(); recovered != nil {
			result.Status = checks.ResultStatusFailed
			result.Error = &checks.CheckError{
				Code:      "checker_panic",
				Message:   fmt.Sprintf("checker panic: %v", recovered),
				Temporary: false,
			}
			w.captureMessage("checker panic", job, map[string]any{
				"panic": fmt.Sprintf("%v", recovered),
			})
		}

		result.Duration = time.Since(startedAt)
	}()

	checker, err := w.registry.Get(job.Type)
	if err != nil {
		result.Status = checks.ResultStatusFailed
		result.Error = &checks.CheckError{
			Code:      "unsupported_check_type",
			Message:   err.Error(),
			Temporary: false,
		}

		return result
	}

	timeout := w.checkTimeout
	if job.Timeout > 0 {
		timeout = job.Timeout
	}

	checkCtx, cancel := context.WithTimeout(ctx, timeout)
	defer cancel()

	checkResult, err := checker.Check(checkCtx, job)
	result = normalizeResult(job, startedAt, checkResult)
	if err != nil {
		result.Status = checks.ResultStatusFailed
		if result.Error == nil {
			result.Error = checkErrorFromError(err)
		}
		w.captureError(err, job, "checker_error")
	}

	if errors.Is(checkCtx.Err(), context.DeadlineExceeded) {
		result.Status = checks.ResultStatusFailed
		result.Error = &checks.CheckError{
			Code:      "timeout",
			Message:   "check timed out",
			Temporary: true,
		}
	}

	return result
}

func (w Worker) captureError(err error, job jobs.CheckJob, event string) {
	if w.sentry == nil {
		return
	}

	w.sentry.CaptureException(err, map[string]string{
		"event":          event,
		"check_type":     job.Type,
		"job_source":     string(job.Source),
		"correlation_id": job.CorrelationID,
	}, map[string]any{
		"event_id":   job.EventID,
		"monitor_id": job.MonitorID,
		"worker_id":  w.id,
	})
}

func (w Worker) captureMessage(message string, job jobs.CheckJob, extra map[string]any) {
	if w.sentry == nil {
		return
	}

	baseExtra := map[string]any{
		"event_id":   job.EventID,
		"monitor_id": job.MonitorID,
		"worker_id":  w.id,
	}
	for key, value := range extra {
		baseExtra[key] = value
	}

	w.sentry.CaptureMessage(message, map[string]string{
		"event":          "checker_panic",
		"check_type":     job.Type,
		"job_source":     string(job.Source),
		"correlation_id": job.CorrelationID,
	}, baseExtra)
}

func baseResult(job jobs.CheckJob, checkedAt time.Time) checks.CheckResult {
	return checks.CheckResult{
		EventID:       job.EventID,
		MonitorID:     job.MonitorID,
		Type:          job.Type,
		Status:        checks.ResultStatusFailed,
		CheckedAt:     checkedAt,
		Raw:           map[string]any{},
		CorrelationID: job.CorrelationID,
		TraceParent:   job.TraceParent,
	}
}

func normalizeResult(job jobs.CheckJob, checkedAt time.Time, result checks.CheckResult) checks.CheckResult {
	if result.EventID == "" {
		result.EventID = job.EventID
	}

	if result.MonitorID == "" {
		result.MonitorID = job.MonitorID
	}

	if result.Type == "" {
		result.Type = job.Type
	}

	if result.CorrelationID == "" {
		result.CorrelationID = job.CorrelationID
	}

	if result.TraceParent == "" {
		result.TraceParent = job.TraceParent
	}

	if result.CheckedAt.IsZero() {
		result.CheckedAt = checkedAt
	}

	if result.Status == "" {
		result.Status = checks.ResultStatusSuccess
	}

	if result.Raw == nil {
		result.Raw = map[string]any{}
	}

	return result
}

func checkErrorFromError(err error) *checks.CheckError {
	if errors.Is(err, context.DeadlineExceeded) {
		return &checks.CheckError{Code: "timeout", Message: "check timed out", Temporary: true}
	}

	if errors.Is(err, context.Canceled) {
		return &checks.CheckError{Code: "canceled", Message: "check canceled", Temporary: true}
	}

	return &checks.CheckError{Code: "check_error", Message: err.Error(), Temporary: true}
}

func (w Worker) startSpan(job jobs.CheckJob) *observability.Span {
	if w.tracer == nil {
		return nil
	}

	return w.tracer.StartSpan("poller.check", job.TraceParent, observability.SpanKindConsumer, map[string]any{
		"check.type":  job.Type,
		"job.source":  string(job.Source),
		"worker.id":   w.id,
		"event.id":    job.EventID,
		"monitor.id":  job.MonitorID,
		"target.host": "",
	})
}

func statusCodeFromResult(status checks.ResultStatus) string {
	if status == checks.ResultStatusSuccess {
		return "STATUS_CODE_OK"
	}

	return "STATUS_CODE_ERROR"
}
