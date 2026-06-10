package sitemapxmlcheck

import (
	"context"
	"net/http"
	"net/http/httptest"
	"testing"

	"montry/apps/poller/internal/checks"
	"montry/apps/poller/internal/jobs"
)

func TestCheckerReturnsLaravelSitemapXMLContract(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte(`<?xml version="1.0" encoding="UTF-8"?><urlset><url><loc>https://example.com/</loc></url></urlset>`))
	}))
	defer server.Close()

	result, err := New().Check(context.Background(), jobs.CheckJob{
		EventID:   "event-1",
		MonitorID: "monitor-1",
		Type:      Type,
		Target:    server.URL,
		Expected: map[string]any{
			"status_codes": []any{float64(200)},
			"valid_xml":    true,
		},
	})
	if err != nil {
		t.Fatalf("check: %v", err)
	}

	if result.Status != checks.ResultStatusSuccess {
		t.Fatalf("expected success, got %q", result.Status)
	}
	if result.Raw["exists"] != true || result.Raw["valid_xml"] != true {
		t.Fatalf("expected exists and valid_xml, got %#v", result.Raw)
	}
	if result.Raw["url_count"] != 1 {
		t.Fatalf("expected url_count=1, got %#v", result.Raw["url_count"])
	}
}

func TestCheckerFailsForInvalidXML(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte(`not xml`))
	}))
	defer server.Close()

	result, err := New().Check(context.Background(), jobs.CheckJob{
		Type:   Type,
		Target: server.URL,
		Expected: map[string]any{
			"status_codes": []any{float64(200)},
			"valid_xml":    true,
		},
	})
	if err != nil {
		t.Fatalf("check: %v", err)
	}
	if result.Status != checks.ResultStatusFailed {
		t.Fatalf("expected failed, got %q", result.Status)
	}
	if result.Error == nil || result.Error.Code != "sitemap_xml_invalid_xml" {
		t.Fatalf("expected sitemap_xml_invalid_xml, got %#v", result.Error)
	}
}
