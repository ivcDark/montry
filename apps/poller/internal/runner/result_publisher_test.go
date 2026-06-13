package runner

import (
	"context"
	"errors"
	"testing"
	"time"

	"montry/apps/poller/internal/checks"
	"montry/apps/poller/internal/jobs"
	"montry/apps/poller/internal/laravel"
)

type fakeLaravelClient struct {
	submitCalls int
	submitErrs  []error
}

func (c *fakeLaravelClient) FetchDueChecks(context.Context, int) ([]jobs.CheckJob, error) {
	return nil, nil
}

func (c *fakeLaravelClient) FetchManualChecks(context.Context, int) ([]jobs.CheckJob, error) {
	return nil, nil
}

func (c *fakeLaravelClient) SubmitCheckResult(context.Context, checks.CheckResult) error {
	c.submitCalls++

	if len(c.submitErrs) == 0 {
		return nil
	}

	err := c.submitErrs[0]
	c.submitErrs = c.submitErrs[1:]

	return err
}

func TestLaravelResultPublisherSubmitsResult(t *testing.T) {
	client := &fakeLaravelClient{}
	publisher, err := NewLaravelResultPublisher(client, ResultPublisherConfig{
		RetryAttempts: 1,
		RetryDelay:    time.Millisecond,
	})
	if err != nil {
		t.Fatalf("new publisher: %v", err)
	}

	if err := publisher.Publish(context.Background(), checks.CheckResult{EventID: "event-1"}); err != nil {
		t.Fatalf("publish result: %v", err)
	}

	if client.submitCalls != 1 {
		t.Fatalf("expected 1 submit call, got %d", client.submitCalls)
	}
}

func TestLaravelResultPublisherRetriesTemporaryError(t *testing.T) {
	client := &fakeLaravelClient{
		submitErrs: []error{
			&laravel.APIError{StatusCode: 500, Body: "server error"},
			nil,
		},
	}
	publisher, err := NewLaravelResultPublisher(client, ResultPublisherConfig{
		RetryAttempts: 2,
		RetryDelay:    time.Millisecond,
	})
	if err != nil {
		t.Fatalf("new publisher: %v", err)
	}

	if err := publisher.Publish(context.Background(), checks.CheckResult{EventID: "event-2"}); err != nil {
		t.Fatalf("publish result: %v", err)
	}

	if client.submitCalls != 2 {
		t.Fatalf("expected 2 submit calls, got %d", client.submitCalls)
	}
}

func TestLaravelResultPublisherDoesNotRetryPermanent4xx(t *testing.T) {
	client := &fakeLaravelClient{
		submitErrs: []error{
			&laravel.APIError{StatusCode: 422, Body: "invalid payload"},
		},
	}
	publisher, err := NewLaravelResultPublisher(client, ResultPublisherConfig{
		RetryAttempts: 3,
		RetryDelay:    time.Millisecond,
	})
	if err != nil {
		t.Fatalf("new publisher: %v", err)
	}

	if err := publisher.Publish(context.Background(), checks.CheckResult{EventID: "event-3"}); err == nil {
		t.Fatal("expected publish error")
	}

	if client.submitCalls != 1 {
		t.Fatalf("expected 1 submit call, got %d", client.submitCalls)
	}
}

func TestLaravelResultPublisherReturnsErrorAfterAttemptsExceeded(t *testing.T) {
	client := &fakeLaravelClient{
		submitErrs: []error{
			&laravel.APIError{StatusCode: 500, Body: "server error"},
			&laravel.APIError{StatusCode: 500, Body: "server error"},
			&laravel.APIError{StatusCode: 500, Body: "server error"},
		},
	}
	publisher, err := NewLaravelResultPublisher(client, ResultPublisherConfig{
		RetryAttempts: 3,
		RetryDelay:    time.Millisecond,
	})
	if err != nil {
		t.Fatalf("new publisher: %v", err)
	}

	if err := publisher.Publish(context.Background(), checks.CheckResult{EventID: "event-4"}); err == nil {
		t.Fatal("expected publish error")
	}

	if client.submitCalls != 3 {
		t.Fatalf("expected 3 submit calls, got %d", client.submitCalls)
	}
}

func TestNewLaravelResultPublisherRejectsInvalidConfig(t *testing.T) {
	if _, err := NewLaravelResultPublisher(&fakeLaravelClient{}, ResultPublisherConfig{}); err == nil {
		t.Fatal("expected invalid config error")
	}

	if _, err := NewLaravelResultPublisher(nil, ResultPublisherConfig{RetryAttempts: 1, RetryDelay: time.Millisecond}); err == nil {
		t.Fatal("expected missing client error")
	}
}

func TestLaravelResultPublisherStopsOnContextCancel(t *testing.T) {
	client := &fakeLaravelClient{
		submitErrs: []error{
			&laravel.APIError{StatusCode: 500, Body: "server error"},
			errors.New("should not be reached"),
		},
	}
	publisher, err := NewLaravelResultPublisher(client, ResultPublisherConfig{
		RetryAttempts: 2,
		RetryDelay:    time.Hour,
	})
	if err != nil {
		t.Fatalf("new publisher: %v", err)
	}

	ctx, cancel := context.WithCancel(context.Background())
	cancel()

	if err := publisher.Publish(ctx, checks.CheckResult{EventID: "event-5"}); err == nil {
		t.Fatal("expected context error")
	}
}
