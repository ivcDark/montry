package laravel

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"
	"time"

	"montry/apps/poller/internal/checks"
)

func TestClientFetchDueChecks(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/internal/monitors/due" {
			t.Fatalf("unexpected path %s", r.URL.Path)
		}
		if r.URL.Query().Get("limit") != "2" {
			t.Fatalf("expected limit=2, got %q", r.URL.Query().Get("limit"))
		}

		_ = json.NewEncoder(w).Encode(map[string]any{
			"data": []map[string]any{
				{
					"event_id":     "event-1",
					"monitor_id":   "monitor-1",
					"check_type":   "http",
					"target":       "https://example.com",
					"settings":     map[string]any{"method": "GET"},
					"expected":     map[string]any{"status_codes": []int{200}},
					"timeout_ms":   5000,
					"requested_at": "2026-05-13T10:00:00Z",
				},
			},
		})
	}))
	defer server.Close()

	client := NewHTTPClient(HTTPClientConfig{
		BaseURL: server.URL,
		Timeout: time.Second,
	})

	checkJobs, err := client.FetchDueChecks(context.Background(), 2)
	if err != nil {
		t.Fatalf("fetch due checks: %v", err)
	}

	if len(checkJobs) != 1 {
		t.Fatalf("expected 1 job, got %d", len(checkJobs))
	}

	job := checkJobs[0]
	if job.EventID != "event-1" {
		t.Fatalf("expected event-1, got %q", job.EventID)
	}
	if job.MonitorID != "monitor-1" {
		t.Fatalf("expected monitor-1, got %q", job.MonitorID)
	}
	if job.Type != "http" {
		t.Fatalf("expected http, got %q", job.Type)
	}
	if job.Timeout != 5*time.Second {
		t.Fatalf("expected timeout 5s, got %s", job.Timeout)
	}
}

func TestClientSubmitCheckResult(t *testing.T) {
	var received map[string]any
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/internal/check-results" {
			t.Fatalf("unexpected path %s", r.URL.Path)
		}

		if err := json.NewDecoder(r.Body).Decode(&received); err != nil {
			t.Fatalf("decode request: %v", err)
		}

		w.WriteHeader(http.StatusCreated)
		_ = json.NewEncoder(w).Encode(map[string]any{"id": 1, "status": "success"})
	}))
	defer server.Close()

	client := NewHTTPClient(HTTPClientConfig{
		BaseURL: server.URL,
		Timeout: time.Second,
	})

	err := client.SubmitCheckResult(context.Background(), checks.CheckResult{
		EventID:   "event-1",
		MonitorID: "monitor-1",
		Type:      "http",
		Status:    checks.ResultStatusSuccess,
		CheckedAt: time.Date(2026, 5, 13, 10, 0, 0, 0, time.UTC),
		Duration:  150 * time.Millisecond,
		Raw:       map[string]any{"status_code": 200},
	})
	if err != nil {
		t.Fatalf("submit check result: %v", err)
	}

	if received["event_id"] != "event-1" {
		t.Fatalf("expected event-1, got %v", received["event_id"])
	}
	if received["duration_ms"] != float64(150) {
		t.Fatalf("expected duration_ms 150, got %v", received["duration_ms"])
	}
	if received["check_type"] != "http" {
		t.Fatalf("expected check_type http, got %v", received["check_type"])
	}
}

func TestClientReturnsServerError(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		http.Error(w, "boom", http.StatusInternalServerError)
	}))
	defer server.Close()

	client := NewHTTPClient(HTTPClientConfig{
		BaseURL:    server.URL,
		Timeout:    time.Second,
		MaxRetries: 1,
	})

	_, err := client.FetchDueChecks(context.Background(), 10)
	if err == nil {
		t.Fatal("expected error")
	}

	var apiErr *APIError
	if !AsAPIError(err, &apiErr) {
		t.Fatalf("expected APIError, got %T", err)
	}
	if apiErr.StatusCode != http.StatusInternalServerError {
		t.Fatalf("expected 500, got %d", apiErr.StatusCode)
	}
}

func TestClientReturnsInvalidJSONError(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		_, _ = w.Write([]byte("{"))
	}))
	defer server.Close()

	client := NewHTTPClient(HTTPClientConfig{
		BaseURL: server.URL,
		Timeout: time.Second,
	})

	_, err := client.FetchDueChecks(context.Background(), 10)
	if err == nil {
		t.Fatal("expected invalid JSON error")
	}
}

func TestClientSendsBearerToken(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Header.Get("Authorization") != "Bearer secret-token" {
			t.Fatalf("unexpected auth header %q", r.Header.Get("Authorization"))
		}

		_ = json.NewEncoder(w).Encode(map[string]any{"data": []any{}})
	}))
	defer server.Close()

	client := NewHTTPClient(HTTPClientConfig{
		BaseURL: server.URL,
		Token:   "secret-token",
		Timeout: time.Second,
	})

	if _, err := client.FetchDueChecks(context.Background(), 10); err != nil {
		t.Fatalf("fetch due checks: %v", err)
	}
}
