package domaincheck

import (
	"context"
	"testing"
	"time"

	"montri/apps/poller/internal/checks"
	"montri/apps/poller/internal/jobs"
)

const ruWhoisFixture = `
domain:        EXAMPLE.RU
nserver:       ns1.example.ru.
state:         REGISTERED, DELEGATED, VERIFIED
paid-till:     2026-06-20T21:00:00Z
free-date:     2026-07-22
source:        TCI
`

const comWhoisFixture = `
Domain Name: EXAMPLE.COM
Registry Expiry Date: 2026-08-13T04:00:00Z
Registrar: Example Registrar, Inc.
`

func TestParseRUWhoisExpiration(t *testing.T) {
	expiresAt, err := parseWHOISExpiration("example.ru", ruWhoisFixture)
	if err != nil {
		t.Fatalf("parse RU whois: %v", err)
	}

	if expiresAt.Format(time.RFC3339) != "2026-06-20T21:00:00Z" {
		t.Fatalf("unexpected expiration date: %s", expiresAt.Format(time.RFC3339))
	}
}

func TestParseCOMWhoisExpiration(t *testing.T) {
	expiresAt, err := parseWHOISExpiration("example.com", comWhoisFixture)
	if err != nil {
		t.Fatalf("parse COM whois: %v", err)
	}

	if expiresAt.Format(time.RFC3339) != "2026-08-13T04:00:00Z" {
		t.Fatalf("unexpected expiration date: %s", expiresAt.Format(time.RFC3339))
	}
}

func TestResolveExpirationReturnsWarningSoon(t *testing.T) {
	result := resolveExpiration(time.Now().Add(7*24*time.Hour), []int{30, 14, 7})

	if result.status != checks.ResultStatusWarning {
		t.Fatalf("expected warning, got %q", result.status)
	}
}

func TestResolveExpirationReturnsExpired(t *testing.T) {
	result := resolveExpiration(time.Now().Add(-24*time.Hour), []int{30})

	if result.status != checks.ResultStatusFailed {
		t.Fatalf("expected failed, got %q", result.status)
	}
	if result.err == nil || result.err.Code != "domain_expired" {
		t.Fatalf("expected domain_expired, got %#v", result.err)
	}
}

func TestParseWHOISExpirationReturnsNotFound(t *testing.T) {
	_, err := parseWHOISExpiration("example.com", "Domain Name: EXAMPLE.COM")
	if err == nil {
		t.Fatal("expected expiration not found")
	}

	if code := checkErrorCode(err); code != "domain_expiration_not_found" {
		t.Fatalf("expected domain_expiration_not_found, got %q", code)
	}
}

func TestCheckerReturnsInvalidDomain(t *testing.T) {
	result, err := New().Check(context.Background(), jobs.CheckJob{
		Type: "domain",
		Settings: map[string]any{
			"domain": "-bad-domain",
		},
	})
	if err == nil {
		t.Fatal("expected invalid domain error")
	}

	if result.Status != checks.ResultStatusFailed {
		t.Fatalf("expected failed, got %q", result.Status)
	}
	if result.Error == nil || result.Error.Code != "domain_invalid_name" {
		t.Fatalf("expected domain_invalid_name, got %#v", result.Error)
	}
}

func TestParseConfigUsesSettings(t *testing.T) {
	cfg, err := parseConfig(jobs.CheckJob{
		Target: "fallback.com",
		Settings: map[string]any{
			"domain":       "example.net",
			"warning_days": []any{float64(30), float64(3)},
		},
	})
	if err != nil {
		t.Fatalf("parse config: %v", err)
	}

	if cfg.domain != "example.net" {
		t.Fatalf("expected example.net, got %q", cfg.domain)
	}
	if len(cfg.warningDays) != 2 || cfg.warningDays[0] != 30 || cfg.warningDays[1] != 3 {
		t.Fatalf("unexpected warning days: %#v", cfg.warningDays)
	}
}
