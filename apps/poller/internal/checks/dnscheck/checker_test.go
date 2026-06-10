package dnscheck

import (
	"context"
	"testing"

	"montry/apps/poller/internal/checks"
	"montry/apps/poller/internal/jobs"
)

func TestParseConfigUsesLaravelDNSFields(t *testing.T) {
	cfg, err := parseConfig(jobs.CheckJob{
		Type:   Type,
		Target: "https://Example.COM/path",
		Settings: map[string]any{
			"record_types": []any{"mx", "A", "A"},
		},
		Expected: map[string]any{
			"resolves":    true,
			"min_records": float64(2),
		},
	})
	if err != nil {
		t.Fatalf("parse config: %v", err)
	}
	if cfg.domain != "example.com" {
		t.Fatalf("expected normalized target fallback, got %q", cfg.domain)
	}
	if len(cfg.recordTypes) != 2 || cfg.recordTypes[0] != "A" || cfg.recordTypes[1] != "MX" {
		t.Fatalf("unexpected record types: %#v", cfg.recordTypes)
	}
	if cfg.minRecords != 2 {
		t.Fatalf("expected min_records=2, got %d", cfg.minRecords)
	}
}

func TestCheckerFailsForInvalidDNSConfig(t *testing.T) {
	result, err := New().Check(context.Background(), jobs.CheckJob{
		Type: Type,
	})
	if err == nil {
		t.Fatal("expected config error")
	}
	if result.Status != checks.ResultStatusFailed {
		t.Fatalf("expected failed, got %q", result.Status)
	}
	if result.Error == nil || result.Error.Code != "dns_invalid_config" {
		t.Fatalf("expected dns_invalid_config, got %#v", result.Error)
	}
}
