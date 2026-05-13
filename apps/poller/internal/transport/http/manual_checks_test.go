package http

import (
	"context"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"

	"montri/apps/poller/internal/checks"
	"montri/apps/poller/internal/jobs"
	"montri/apps/poller/internal/logger"
)

type testChecker struct {
	checkType string
}

func (c testChecker) Type() string {
	return c.checkType
}

func (c testChecker) Check(context.Context, jobs.CheckJob) (checks.CheckResult, error) {
	return checks.CheckResult{}, nil
}

func TestManualCheckEndpointAcceptsValidJob(t *testing.T) {
	registry := checks.NewRegistry()
	if err := registry.Register(testChecker{checkType: "http"}); err != nil {
		t.Fatalf("register checker: %v", err)
	}

	jobCh := make(chan jobs.CheckJob, 1)
	server := NewServer(":0", logger.New("test"), Options{
		ManualJobs:           jobCh,
		CheckRegistry:        registry,
		ManualAuthToken:      "secret",
		ManualRequestTimeout: time.Second,
	})

	request := httptest.NewRequest(http.MethodPost, "/internal/manual-checks", strings.NewReader(`{
		"event_id": "event-1",
		"event_type": "manual_check_requested",
		"monitor_id": 1,
		"check_type": "http",
		"target": "https://example.com",
		"settings": {"method": "GET"},
		"expected": {"status_codes": [200]},
		"requested_at": "2026-05-13T10:00:00Z"
	}`))
	request.Header.Set("Authorization", "Bearer secret")
	response := httptest.NewRecorder()

	server.server.Handler.ServeHTTP(response, request)

	if response.Code != http.StatusAccepted {
		t.Fatalf("expected 202, got %d with body %s", response.Code, response.Body.String())
	}

	select {
	case job := <-jobCh:
		if job.EventID != "event-1" {
			t.Fatalf("expected event-1, got %q", job.EventID)
		}
		if job.MonitorID != "1" {
			t.Fatalf("expected monitor id 1, got %q", job.MonitorID)
		}
		if job.Source != jobs.SourceManual {
			t.Fatalf("expected manual source, got %q", job.Source)
		}
	default:
		t.Fatal("expected job in channel")
	}
}

func TestManualCheckEndpointRejectsUnknownCheckType(t *testing.T) {
	server := NewServer(":0", logger.New("test"), Options{
		ManualJobs:           make(chan jobs.CheckJob, 1),
		CheckRegistry:        checks.NewRegistry(),
		ManualRequestTimeout: time.Second,
	})

	request := httptest.NewRequest(http.MethodPost, "/internal/manual-checks", strings.NewReader(`{
		"event_id": "event-2",
		"monitor_id": 2,
		"check_type": "missing",
		"target": "https://example.com",
		"requested_at": "2026-05-13T10:00:00Z"
	}`))
	response := httptest.NewRecorder()

	server.server.Handler.ServeHTTP(response, request)

	if response.Code != http.StatusUnprocessableEntity {
		t.Fatalf("expected 422, got %d with body %s", response.Code, response.Body.String())
	}
}

func TestManualCheckEndpointRejectsInvalidPayload(t *testing.T) {
	server := NewServer(":0", logger.New("test"), Options{
		ManualJobs:           make(chan jobs.CheckJob, 1),
		CheckRegistry:        checks.NewRegistry(),
		ManualRequestTimeout: time.Second,
	})

	request := httptest.NewRequest(http.MethodPost, "/internal/manual-checks", strings.NewReader(`{"event_id": ""}`))
	response := httptest.NewRecorder()

	server.server.Handler.ServeHTTP(response, request)

	if response.Code != http.StatusBadRequest {
		t.Fatalf("expected 400, got %d with body %s", response.Code, response.Body.String())
	}
}

func TestManualCheckEndpointRejectsUnauthorizedRequest(t *testing.T) {
	server := NewServer(":0", logger.New("test"), Options{
		ManualJobs:           make(chan jobs.CheckJob, 1),
		CheckRegistry:        checks.NewRegistry(),
		ManualAuthToken:      "secret",
		ManualRequestTimeout: time.Second,
	})

	request := httptest.NewRequest(http.MethodPost, "/internal/manual-checks", strings.NewReader(`{}`))
	response := httptest.NewRecorder()

	server.server.Handler.ServeHTTP(response, request)

	if response.Code != http.StatusUnauthorized {
		t.Fatalf("expected 401, got %d", response.Code)
	}
}
