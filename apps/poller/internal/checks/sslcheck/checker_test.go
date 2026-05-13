package sslcheck

import (
	"context"
	"crypto/x509"
	"crypto/x509/pkix"
	"math/big"
	"net"
	"net/http"
	"net/http/httptest"
	"strconv"
	"testing"
	"time"

	"montri/apps/poller/internal/checks"
	"montri/apps/poller/internal/jobs"
)

func TestParseConfigUsesSettings(t *testing.T) {
	cfg, err := parseConfig(jobs.CheckJob{
		Target: "fallback.example.com",
		Settings: map[string]any{
			"domain":       "example.com",
			"port":         float64(8443),
			"server_name":  "www.example.com",
			"warning_days": []any{float64(30), float64(7)},
		},
	})
	if err != nil {
		t.Fatalf("parse config: %v", err)
	}

	if cfg.domain != "example.com" {
		t.Fatalf("expected domain example.com, got %q", cfg.domain)
	}
	if cfg.port != 8443 {
		t.Fatalf("expected port 8443, got %d", cfg.port)
	}
	if cfg.serverName != "www.example.com" {
		t.Fatalf("expected server_name www.example.com, got %q", cfg.serverName)
	}
	if len(cfg.warningDays) != 2 || cfg.warningDays[0] != 30 || cfg.warningDays[1] != 7 {
		t.Fatalf("unexpected warning days: %#v", cfg.warningDays)
	}
}

func TestResolveCertificateStatusReturnsSuccess(t *testing.T) {
	cert := certificateFixture("example.com", time.Now().Add(-time.Hour), time.Now().Add(90*24*time.Hour))
	result := resolveCertificateStatus(cert, "example.com", []int{30, 14, 7})

	if result.status != checks.ResultStatusSuccess {
		t.Fatalf("expected success, got %q", result.status)
	}
	if result.err != nil {
		t.Fatalf("expected no error, got %#v", result.err)
	}
}

func TestResolveCertificateStatusReturnsWarningNearExpiry(t *testing.T) {
	cert := certificateFixture("example.com", time.Now().Add(-time.Hour), time.Now().Add(7*24*time.Hour))
	result := resolveCertificateStatus(cert, "example.com", []int{30, 14, 7})

	if result.status != checks.ResultStatusWarning {
		t.Fatalf("expected warning, got %q", result.status)
	}
}

func TestResolveCertificateStatusReturnsExpired(t *testing.T) {
	cert := certificateFixture("example.com", time.Now().Add(-48*time.Hour), time.Now().Add(-24*time.Hour))
	result := resolveCertificateStatus(cert, "example.com", []int{30})

	if result.status != checks.ResultStatusFailed {
		t.Fatalf("expected failed, got %q", result.status)
	}
	if result.err == nil || result.err.Code != "ssl_expired" {
		t.Fatalf("expected ssl_expired, got %#v", result.err)
	}
}

func TestResolveCertificateStatusReturnsDomainMismatch(t *testing.T) {
	cert := certificateFixture("other.example.com", time.Now().Add(-time.Hour), time.Now().Add(90*24*time.Hour))
	result := resolveCertificateStatus(cert, "example.com", []int{30})

	if result.status != checks.ResultStatusFailed {
		t.Fatalf("expected failed, got %q", result.status)
	}
	if result.err == nil || result.err.Code != "ssl_domain_mismatch" {
		t.Fatalf("expected ssl_domain_mismatch, got %#v", result.err)
	}
}

func TestCheckerConnectsToTLSServer(t *testing.T) {
	server := httptest.NewTLSServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusOK)
	}))
	defer server.Close()

	host, portString, err := net.SplitHostPort(server.Listener.Addr().String())
	if err != nil {
		t.Fatalf("split host port: %v", err)
	}

	port, err := strconv.Atoi(portString)
	if err != nil {
		t.Fatalf("parse port: %v", err)
	}

	result, err := New().Check(context.Background(), jobs.CheckJob{
		EventID:   "event-1",
		MonitorID: "monitor-1",
		Type:      "ssl",
		Settings: map[string]any{
			"domain":     host,
			"port":       port,
			"verify_ssl": false,
		},
	})
	if err != nil {
		t.Fatalf("check: %v", err)
	}

	if result.Status != checks.ResultStatusSuccess && result.Status != checks.ResultStatusWarning {
		t.Fatalf("expected success or warning, got %q with error %#v", result.Status, result.Error)
	}
	if result.Raw["valid"] != true {
		t.Fatalf("expected valid=true in raw result, got %#v", result.Raw["valid"])
	}
	if result.Raw["expires_at"] == nil {
		t.Fatalf("expected expires_at in raw result")
	}
	if result.Raw["issued_at"] == nil {
		t.Fatalf("expected issued_at in raw result")
	}
	if result.Raw["days_until_expiration"] == nil {
		t.Fatalf("expected days_until_expiration in raw result")
	}
}

func certificateFixture(dnsName string, notBefore time.Time, notAfter time.Time) *x509.Certificate {
	return &x509.Certificate{
		NotBefore:    notBefore,
		NotAfter:     notAfter,
		DNSNames:     []string{dnsName},
		Issuer:       pkix.Name{CommonName: "Test Issuer"},
		Subject:      pkix.Name{CommonName: dnsName},
		SerialNumber: big.NewInt(1),
	}
}
