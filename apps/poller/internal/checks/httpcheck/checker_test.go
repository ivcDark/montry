package httpcheck

import (
	"context"
	"net/http"
	"net/http/httptest"
	"testing"
	"time"

	"montri/apps/poller/internal/checks"
	"montri/apps/poller/internal/jobs"
)

func TestCheckerReturnsSuccessForExpectedStatus(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		w.Header().Set("X-Test", "ok")
		w.WriteHeader(http.StatusOK)
	}))
	defer server.Close()

	result, err := New().Check(context.Background(), jobs.CheckJob{
		EventID:   "event-1",
		MonitorID: "monitor-1",
		Type:      "http",
		Target:    server.URL,
		Settings: map[string]any{
			"method": "GET",
		},
		Expected: map[string]any{
			"status_codes":         []any{float64(200)},
			"max_response_time_ms": float64(1000),
		},
	})
	if err != nil {
		t.Fatalf("check: %v", err)
	}

	if result.Status != checks.ResultStatusSuccess {
		t.Fatalf("expected success, got %q", result.Status)
	}
	if result.Raw["status_code"] != http.StatusOK {
		t.Fatalf("expected status code 200, got %v", result.Raw["status_code"])
	}
}

func TestCheckerReturnsFailedForUnexpectedStatus(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusInternalServerError)
	}))
	defer server.Close()

	result, err := New().Check(context.Background(), jobs.CheckJob{
		Type:   "http",
		Target: server.URL,
		Expected: map[string]any{
			"status_codes": []any{float64(200)},
		},
	})
	if err != nil {
		t.Fatalf("check: %v", err)
	}

	if result.Status != checks.ResultStatusFailed {
		t.Fatalf("expected failed, got %q", result.Status)
	}
	if result.Error == nil || result.Error.Code != "http_invalid_status" {
		t.Fatalf("expected http_invalid_status, got %#v", result.Error)
	}
}

func TestCheckerReturnsFailedForTimeout(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		time.Sleep(50 * time.Millisecond)
		w.WriteHeader(http.StatusOK)
	}))
	defer server.Close()

	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Millisecond)
	defer cancel()

	result, err := New().Check(ctx, jobs.CheckJob{
		Type:   "http",
		Target: server.URL,
		Expected: map[string]any{
			"status_codes": []any{float64(200)},
		},
	})
	if err == nil {
		t.Fatal("expected timeout error")
	}

	if result.Status != checks.ResultStatusFailed {
		t.Fatalf("expected failed, got %q", result.Status)
	}
	if result.Error == nil || result.Error.Code != "http_timeout" {
		t.Fatalf("expected http_timeout, got %#v", result.Error)
	}
}

func TestCheckerReturnsWarningForSlowResponse(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		time.Sleep(20 * time.Millisecond)
		w.WriteHeader(http.StatusOK)
	}))
	defer server.Close()

	result, err := New().Check(context.Background(), jobs.CheckJob{
		Type:   "http",
		Target: server.URL,
		Expected: map[string]any{
			"status_codes":         []any{float64(200)},
			"max_response_time_ms": float64(1),
		},
	})
	if err != nil {
		t.Fatalf("check: %v", err)
	}

	if result.Status != checks.ResultStatusWarning {
		t.Fatalf("expected warning, got %q", result.Status)
	}
}

func TestCheckerFollowsRedirectsWhenEnabled(t *testing.T) {
	finalServer := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusOK)
	}))
	defer finalServer.Close()

	redirectServer := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		http.Redirect(w, r, finalServer.URL, http.StatusFound)
	}))
	defer redirectServer.Close()

	result, err := New().Check(context.Background(), jobs.CheckJob{
		Type:   "http",
		Target: redirectServer.URL,
		Settings: map[string]any{
			"follow_redirects": true,
		},
		Expected: map[string]any{
			"status_codes": []any{float64(200)},
		},
	})
	if err != nil {
		t.Fatalf("check: %v", err)
	}

	if result.Status != checks.ResultStatusSuccess {
		t.Fatalf("expected success, got %q", result.Status)
	}
}

func TestCheckerDoesNotFollowRedirectsWhenDisabled(t *testing.T) {
	finalServer := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusOK)
	}))
	defer finalServer.Close()

	redirectServer := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		http.Redirect(w, r, finalServer.URL, http.StatusFound)
	}))
	defer redirectServer.Close()

	result, err := New().Check(context.Background(), jobs.CheckJob{
		Type:   "http",
		Target: redirectServer.URL,
		Settings: map[string]any{
			"follow_redirects": false,
		},
		Expected: map[string]any{
			"status_codes": []any{float64(200)},
		},
	})
	if err != nil {
		t.Fatalf("check: %v", err)
	}

	if result.Status != checks.ResultStatusFailed {
		t.Fatalf("expected failed, got %q", result.Status)
	}
	if result.Raw["status_code"] != http.StatusFound {
		t.Fatalf("expected status code 302, got %v", result.Raw["status_code"])
	}
}

func TestCheckerReturnsFailedForInvalidURL(t *testing.T) {
	result, err := New().Check(context.Background(), jobs.CheckJob{
		Type:   "http",
		Target: "://bad-url",
	})
	if err == nil {
		t.Fatal("expected invalid URL error")
	}

	if result.Status != checks.ResultStatusFailed {
		t.Fatalf("expected failed, got %q", result.Status)
	}
	if result.Error == nil || result.Error.Code != "http_invalid_url" {
		t.Fatalf("expected http_invalid_url, got %#v", result.Error)
	}
}
