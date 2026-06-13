package apiendpointcheck

import (
	"context"
	"net/http"
	"net/http/httptest"
	"testing"

	"montry/apps/poller/internal/checks"
	"montry/apps/poller/internal/jobs"
)

func TestCheckerReturnsLaravelAPIEndpointContract(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodPost {
			t.Fatalf("expected POST, got %s", r.Method)
		}
		if r.Header.Get("X-Test") != "ok" {
			t.Fatalf("expected X-Test header")
		}
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusCreated)
		_, _ = w.Write([]byte(`{"status":"ok"}`))
	}))
	defer server.Close()

	result, err := New().Check(context.Background(), jobs.CheckJob{
		EventID:   "event-1",
		MonitorID: "monitor-1",
		Type:      Type,
		Target:    server.URL,
		Settings: map[string]any{
			"method":  "POST",
			"headers": map[string]any{"X-Test": "ok"},
			"body":    `{"ping":true}`,
		},
		Expected: map[string]any{
			"status_codes":      []any{float64(201)},
			"response_contains": "ok",
		},
	})
	if err != nil {
		t.Fatalf("check: %v", err)
	}
	if result.Status != checks.ResultStatusSuccess {
		t.Fatalf("expected success, got %q", result.Status)
	}
	if result.Raw["status_code"] != http.StatusCreated {
		t.Fatalf("expected status 201, got %#v", result.Raw["status_code"])
	}
	if result.Raw["response_contains_matched"] != true {
		t.Fatalf("expected response_contains_matched=true, got %#v", result.Raw["response_contains_matched"])
	}
}

func TestCheckerFailsWhenResponseDoesNotContainText(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte(`no match`))
	}))
	defer server.Close()

	result, err := New().Check(context.Background(), jobs.CheckJob{
		Type:   Type,
		Target: server.URL,
		Expected: map[string]any{
			"status_codes":      []any{float64(200)},
			"response_contains": "expected text",
		},
	})
	if err != nil {
		t.Fatalf("check: %v", err)
	}
	if result.Status != checks.ResultStatusFailed {
		t.Fatalf("expected failed, got %q", result.Status)
	}
	if result.Error == nil || result.Error.Code != "api_endpoint_response_missing_text" {
		t.Fatalf("expected api_endpoint_response_missing_text, got %#v", result.Error)
	}
}
