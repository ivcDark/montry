package runner

import (
	"context"
	"errors"
	"testing"
	"time"

	"montry/apps/poller/internal/checks"
	"montry/apps/poller/internal/jobs"
)

type testChecker struct {
	checkType string
	check     func(ctx context.Context, job jobs.CheckJob) (checks.CheckResult, error)
}

func (c testChecker) Type() string {
	return c.checkType
}

func (c testChecker) Check(ctx context.Context, job jobs.CheckJob) (checks.CheckResult, error) {
	return c.check(ctx, job)
}

func TestWorkerPoolRunsJob(t *testing.T) {
	registry := checks.NewRegistry()
	if err := registry.Register(testChecker{
		checkType: "http",
		check: func(_ context.Context, job jobs.CheckJob) (checks.CheckResult, error) {
			return checks.CheckResult{
				EventID:   job.EventID,
				MonitorID: job.MonitorID,
				Type:      job.Type,
				Status:    checks.ResultStatusSuccess,
			}, nil
		},
	}); err != nil {
		t.Fatalf("register checker: %v", err)
	}

	results := make(chan checks.CheckResult, 1)
	pool, err := NewWorkerPool(registry, NewChannelResultPublisher(results), WorkerPoolConfig{
		Workers:      1,
		CheckTimeout: time.Second,
	})
	if err != nil {
		t.Fatalf("new worker pool: %v", err)
	}

	jobCh := make(chan jobs.CheckJob, 1)
	jobCh <- jobs.CheckJob{EventID: "event-1", MonitorID: "monitor-1", Type: "http"}
	close(jobCh)

	if err := pool.Run(context.Background(), jobCh); err != nil {
		t.Fatalf("run worker pool: %v", err)
	}

	result := <-results
	if result.Status != checks.ResultStatusSuccess {
		t.Fatalf("expected success status, got %q", result.Status)
	}
	if result.EventID != "event-1" {
		t.Fatalf("expected event-1, got %q", result.EventID)
	}
}

func TestWorkerPoolPublishesUnknownCheckTypeResult(t *testing.T) {
	results := make(chan checks.CheckResult, 1)
	pool, err := NewWorkerPool(checks.NewRegistry(), NewChannelResultPublisher(results), WorkerPoolConfig{
		Workers:      1,
		CheckTimeout: time.Second,
	})
	if err != nil {
		t.Fatalf("new worker pool: %v", err)
	}

	jobCh := make(chan jobs.CheckJob, 1)
	jobCh <- jobs.CheckJob{EventID: "event-2", MonitorID: "monitor-2", Type: "ssl"}
	close(jobCh)

	if err := pool.Run(context.Background(), jobCh); err != nil {
		t.Fatalf("run worker pool: %v", err)
	}

	result := <-results
	if result.Status != checks.ResultStatusFailed {
		t.Fatalf("expected failed status, got %q", result.Status)
	}
	if result.Error == nil {
		t.Fatal("expected check error")
	}
	if result.Error.Code != "unsupported_check_type" {
		t.Fatalf("expected unsupported_check_type error, got %q", result.Error.Code)
	}
}

func TestWorkerPoolStopsWhenContextIsCancelled(t *testing.T) {
	pool, err := NewWorkerPool(checks.NewRegistry(), NewChannelResultPublisher(make(chan checks.CheckResult)), WorkerPoolConfig{
		Workers:      2,
		CheckTimeout: time.Second,
	})
	if err != nil {
		t.Fatalf("new worker pool: %v", err)
	}

	ctx, cancel := context.WithCancel(context.Background())
	jobCh := make(chan jobs.CheckJob)
	done := make(chan error, 1)

	go func() {
		done <- pool.Run(ctx, jobCh)
	}()

	cancel()

	select {
	case err := <-done:
		if err != nil && !errors.Is(err, context.Canceled) {
			t.Fatalf("expected nil or context canceled, got %v", err)
		}
	case <-time.After(time.Second):
		t.Fatal("worker pool did not stop after context cancellation")
	}
}

func TestWorkerPoolAppliesCheckTimeout(t *testing.T) {
	registry := checks.NewRegistry()
	if err := registry.Register(testChecker{
		checkType: "http",
		check: func(ctx context.Context, job jobs.CheckJob) (checks.CheckResult, error) {
			<-ctx.Done()
			return checks.CheckResult{
				EventID:   job.EventID,
				MonitorID: job.MonitorID,
				Type:      job.Type,
				Status:    checks.ResultStatusFailed,
			}, ctx.Err()
		},
	}); err != nil {
		t.Fatalf("register checker: %v", err)
	}

	results := make(chan checks.CheckResult, 1)
	pool, err := NewWorkerPool(registry, NewChannelResultPublisher(results), WorkerPoolConfig{
		Workers:      1,
		CheckTimeout: 10 * time.Millisecond,
	})
	if err != nil {
		t.Fatalf("new worker pool: %v", err)
	}

	jobCh := make(chan jobs.CheckJob, 1)
	jobCh <- jobs.CheckJob{EventID: "event-3", MonitorID: "monitor-3", Type: "http"}
	close(jobCh)

	if err := pool.Run(context.Background(), jobCh); err != nil {
		t.Fatalf("run worker pool: %v", err)
	}

	result := <-results
	if result.Status != checks.ResultStatusFailed {
		t.Fatalf("expected failed status, got %q", result.Status)
	}
	if result.Error == nil {
		t.Fatal("expected timeout error")
	}
	if result.Error.Code != "timeout" {
		t.Fatalf("expected timeout error, got %q", result.Error.Code)
	}
}

func TestWorkerPoolRecoversCheckerPanic(t *testing.T) {
	registry := checks.NewRegistry()
	if err := registry.Register(testChecker{
		checkType: "http",
		check: func(context.Context, jobs.CheckJob) (checks.CheckResult, error) {
			panic("boom")
		},
	}); err != nil {
		t.Fatalf("register checker: %v", err)
	}

	results := make(chan checks.CheckResult, 1)
	pool, err := NewWorkerPool(registry, NewChannelResultPublisher(results), WorkerPoolConfig{
		Workers:      1,
		CheckTimeout: time.Second,
	})
	if err != nil {
		t.Fatalf("new worker pool: %v", err)
	}

	jobCh := make(chan jobs.CheckJob, 1)
	jobCh <- jobs.CheckJob{EventID: "event-4", MonitorID: "monitor-4", Type: "http"}
	close(jobCh)

	if err := pool.Run(context.Background(), jobCh); err != nil {
		t.Fatalf("run worker pool: %v", err)
	}

	result := <-results
	if result.Status != checks.ResultStatusFailed {
		t.Fatalf("expected failed status, got %q", result.Status)
	}
	if result.Error == nil {
		t.Fatal("expected panic error")
	}
	if result.Error.Code != "checker_panic" {
		t.Fatalf("expected checker_panic error, got %q", result.Error.Code)
	}
}
