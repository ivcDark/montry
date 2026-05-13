package scheduler

import (
	"context"
	"errors"
	"testing"
	"time"

	"montri/apps/poller/internal/checks"
	"montri/apps/poller/internal/jobs"
)

type fakeLaravelClient struct {
	fetchCalls int
	results    [][]jobs.CheckJob
	errors     []error
}

func (c *fakeLaravelClient) FetchDueChecks(context.Context, int) ([]jobs.CheckJob, error) {
	c.fetchCalls++

	if len(c.errors) > 0 {
		err := c.errors[0]
		c.errors = c.errors[1:]
		if err != nil {
			return nil, err
		}
	}

	if len(c.results) == 0 {
		return nil, nil
	}

	result := c.results[0]
	c.results = c.results[1:]

	return result, nil
}

func (c *fakeLaravelClient) FetchManualChecks(context.Context, int) ([]jobs.CheckJob, error) {
	return nil, nil
}

func (c *fakeLaravelClient) SubmitCheckResult(context.Context, checks.CheckResult) error {
	return nil
}

type testLogger struct {
	errors int
	warns  int
}

func (l *testLogger) Error(string, ...any) {
	l.errors++
}

func (l *testLogger) Warn(string, ...any) {
	l.warns++
}

func TestSchedulerFetchesDueChecksAndEnqueuesJobs(t *testing.T) {
	client := &fakeLaravelClient{
		results: [][]jobs.CheckJob{
			{
				{EventID: "event-1", Type: "http"},
			},
		},
	}
	jobCh := make(chan jobs.CheckJob, 1)
	s := New(client, jobCh, Config{
		Interval:   time.Hour,
		FetchLimit: 10,
	})

	if err := s.Tick(context.Background()); err != nil {
		t.Fatalf("tick: %v", err)
	}

	select {
	case job := <-jobCh:
		if job.EventID != "event-1" {
			t.Fatalf("expected event-1, got %q", job.EventID)
		}
	default:
		t.Fatal("expected job in channel")
	}
}

func TestSchedulerSurvivesLaravelAPIError(t *testing.T) {
	client := &fakeLaravelClient{
		errors: []error{errors.New("api down"), nil},
		results: [][]jobs.CheckJob{
			{
				{EventID: "event-2", Type: "ssl"},
			},
		},
	}
	jobCh := make(chan jobs.CheckJob, 1)
	logger := &testLogger{}
	s := New(client, jobCh, Config{
		Interval:   time.Hour,
		FetchLimit: 5,
		Logger:     logger,
	})

	if err := s.Tick(context.Background()); err != nil {
		t.Fatalf("first tick should not fail: %v", err)
	}
	if logger.errors != 1 {
		t.Fatalf("expected one logged error, got %d", logger.errors)
	}

	if err := s.Tick(context.Background()); err != nil {
		t.Fatalf("second tick: %v", err)
	}

	select {
	case job := <-jobCh:
		if job.EventID != "event-2" {
			t.Fatalf("expected event-2, got %q", job.EventID)
		}
	default:
		t.Fatal("expected job after second tick")
	}
}

func TestSchedulerStopsOnContextCancel(t *testing.T) {
	client := &fakeLaravelClient{}
	jobCh := make(chan jobs.CheckJob)
	s := New(client, jobCh, Config{
		Interval:   time.Millisecond,
		FetchLimit: 10,
	})

	ctx, cancel := context.WithCancel(context.Background())
	done := make(chan error, 1)
	go func() {
		done <- s.Run(ctx)
	}()

	cancel()

	select {
	case err := <-done:
		if err != nil && !errors.Is(err, context.Canceled) {
			t.Fatalf("expected nil or context canceled, got %v", err)
		}
	case <-time.After(time.Second):
		t.Fatal("scheduler did not stop")
	}
}

func TestSchedulerLogsWarningWhenQueueIsFull(t *testing.T) {
	client := &fakeLaravelClient{
		results: [][]jobs.CheckJob{
			{
				{EventID: "event-3", Type: "domain"},
			},
		},
	}
	jobCh := make(chan jobs.CheckJob)
	logger := &testLogger{}
	s := New(client, jobCh, Config{
		Interval:   time.Hour,
		FetchLimit: 10,
		Logger:     logger,
	})

	if err := s.Tick(context.Background()); err != nil {
		t.Fatalf("tick: %v", err)
	}

	if logger.warns != 1 {
		t.Fatalf("expected one warning, got %d", logger.warns)
	}
}
