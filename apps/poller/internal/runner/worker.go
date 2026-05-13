package runner

import (
	"context"
	"errors"
	"fmt"
	"time"

	"montri/apps/poller/internal/checks"
	"montri/apps/poller/internal/jobs"
)

type Worker struct {
	id           int
	registry     checks.Registry
	publisher    ResultPublisher
	checkTimeout time.Duration
}

func NewWorker(id int, registry checks.Registry, publisher ResultPublisher, checkTimeout time.Duration) Worker {
	return Worker{
		id:           id,
		registry:     registry,
		publisher:    publisher,
		checkTimeout: checkTimeout,
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
	result := w.runCheck(ctx, job)
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

func baseResult(job jobs.CheckJob, checkedAt time.Time) checks.CheckResult {
	return checks.CheckResult{
		EventID:   job.EventID,
		MonitorID: job.MonitorID,
		Type:      job.Type,
		Status:    checks.ResultStatusFailed,
		CheckedAt: checkedAt,
		Raw:       map[string]any{},
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
