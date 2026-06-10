package robotstxtcheck

import (
	"context"
	"net/http"
	"net/http/httptest"
	"testing"

	"montry/apps/poller/internal/checks"
	"montry/apps/poller/internal/jobs"
)

func TestCheckerReturnsLaravelRobotsTxtContract(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte("User-agent: *\nSitemap: https://example.com/sitemap.xml\n"))
	}))
	defer server.Close()

	result, err := New().Check(context.Background(), jobs.CheckJob{
		EventID:   "event-1",
		MonitorID: "monitor-1",
		Type:      Type,
		Target:    server.URL,
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
	if result.Raw["exists"] != true {
		t.Fatalf("expected exists=true, got %#v", result.Raw["exists"])
	}
	if result.Raw["status_code"] != http.StatusOK {
		t.Fatalf("expected status 200, got %#v", result.Raw["status_code"])
	}
	if result.Raw["response_time_ms"] == nil {
		t.Fatalf("expected response_time_ms")
	}
	sitemaps, ok := result.Raw["sitemaps"].([]string)
	if !ok || len(sitemaps) != 1 || sitemaps[0] != "https://example.com/sitemap.xml" {
		t.Fatalf("unexpected sitemaps: %#v", result.Raw["sitemaps"])
	}
}

func TestCheckerFailsForMissingRobotsTxt(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusNotFound)
	}))
	defer server.Close()

	result, err := New().Check(context.Background(), jobs.CheckJob{
		Type:   Type,
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
	if result.Error == nil || result.Error.Code != "robots_txt_invalid_status" {
		t.Fatalf("expected robots_txt_invalid_status, got %#v", result.Error)
	}
}
