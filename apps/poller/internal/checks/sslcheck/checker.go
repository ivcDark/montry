package sslcheck

import (
	"context"
	"crypto/tls"
	"crypto/x509"
	"errors"
	"fmt"
	"net"
	"net/url"
	"strconv"
	"strings"
	"time"

	"montri/apps/poller/internal/checks"
	"montri/apps/poller/internal/jobs"
)

const Type = "ssl"

type Checker struct{}

func New() Checker {
	return Checker{}
}

func (c Checker) Type() string {
	return Type
}

func (c Checker) Check(ctx context.Context, job jobs.CheckJob) (checks.CheckResult, error) {
	startedAt := time.Now().UTC()
	cfg, err := parseConfig(job)
	if err != nil {
		result := baseResult(job, startedAt)
		result.Error = &checks.CheckError{Code: "ssl_connection_error", Message: err.Error(), Temporary: false}
		return result, err
	}

	dialer := net.Dialer{}
	rawConn, err := dialer.DialContext(ctx, "tcp", net.JoinHostPort(cfg.domain, strconv.Itoa(cfg.port)))
	if err != nil {
		result := baseResult(job, startedAt)
		result.Error = &checks.CheckError{Code: "ssl_connection_error", Message: err.Error(), Temporary: true}
		result.Duration = time.Since(startedAt)
		return result, err
	}
	defer rawConn.Close()

	tlsConn := tls.Client(rawConn, &tls.Config{
		ServerName:         cfg.serverName,
		InsecureSkipVerify: !cfg.verifySSL, //nolint:gosec
	})
	defer tlsConn.Close()

	if err := tlsConn.HandshakeContext(ctx); err != nil {
		return classifyHandshakeError(ctx, job, startedAt, err)
	}

	state := tlsConn.ConnectionState()
	if len(state.PeerCertificates) == 0 {
		result := baseResult(job, startedAt)
		result.Error = &checks.CheckError{Code: "ssl_no_certificate", Message: "TLS peer did not return a certificate", Temporary: true}
		result.Duration = time.Since(startedAt)
		return result, nil
	}

	leaf := state.PeerCertificates[0]
	status := resolveCertificateStatus(leaf, cfg.serverName, cfg.warningDays)

	result := checks.CheckResult{
		EventID:   job.EventID,
		MonitorID: job.MonitorID,
		Type:      Type,
		Status:    status.status,
		CheckedAt: startedAt,
		Duration:  time.Since(startedAt),
		Raw:       rawResult(leaf, state.PeerCertificates),
		Error:     status.err,
	}

	return result, errorForStatus(status.err)
}

type config struct {
	domain      string
	port        int
	warningDays []int
	serverName  string
	verifySSL   bool
}

func parseConfig(job jobs.CheckJob) (config, error) {
	settings := job.Settings

	domain := stringSetting(settings, "domain", "")
	if domain == "" {
		domain = job.Target
	}
	domain = normalizeDomain(domain)

	if domain == "" {
		return config{}, fmt.Errorf("domain is required")
	}

	port := intSetting(settings, "port", 443)
	if port < 1 || port > 65535 {
		return config{}, fmt.Errorf("invalid port: %d", port)
	}

	serverName := stringSetting(settings, "server_name", domain)

	return config{
		domain:      domain,
		port:        port,
		warningDays: intSliceSetting(settings, "warning_days", []int{30, 14, 7, 3, 1}),
		serverName:  serverName,
		verifySSL:   boolSetting(settings, "verify_ssl", true),
	}, nil
}

type certificateStatus struct {
	status checks.ResultStatus
	err    *checks.CheckError
}

func resolveCertificateStatus(cert *x509.Certificate, serverName string, warningDays []int) certificateStatus {
	now := time.Now()

	if now.Before(cert.NotBefore) || now.After(cert.NotAfter) {
		return certificateStatus{
			status: checks.ResultStatusFailed,
			err: &checks.CheckError{
				Code:      "ssl_expired",
				Message:   "certificate is not valid at the current time",
				Temporary: false,
			},
		}
	}

	if err := cert.VerifyHostname(serverName); err != nil {
		return certificateStatus{
			status: checks.ResultStatusFailed,
			err: &checks.CheckError{
				Code:      "ssl_domain_mismatch",
				Message:   err.Error(),
				Temporary: false,
			},
		}
	}

	daysUntilExpiry := int(time.Until(cert.NotAfter).Hours() / 24)
	if shouldWarn(daysUntilExpiry, warningDays) {
		return certificateStatus{status: checks.ResultStatusWarning}
	}

	return certificateStatus{status: checks.ResultStatusSuccess}
}

func shouldWarn(daysUntilExpiry int, warningDays []int) bool {
	if daysUntilExpiry < 0 {
		return false
	}

	for _, threshold := range warningDays {
		if daysUntilExpiry <= threshold {
			return true
		}
	}

	return false
}

func classifyHandshakeError(ctx context.Context, job jobs.CheckJob, checkedAt time.Time, err error) (checks.CheckResult, error) {
	result := baseResult(job, checkedAt)
	result.Duration = time.Since(checkedAt)

	if errors.Is(ctx.Err(), context.DeadlineExceeded) {
		result.Error = &checks.CheckError{Code: "ssl_connection_error", Message: "TLS connection timed out", Temporary: true}
		return result, err
	}

	var hostnameErr x509.HostnameError
	if errors.As(err, &hostnameErr) {
		result.Error = &checks.CheckError{Code: "ssl_domain_mismatch", Message: err.Error(), Temporary: false}
		return result, err
	}

	result.Error = &checks.CheckError{Code: "ssl_handshake_error", Message: err.Error(), Temporary: true}
	return result, err
}

func baseResult(job jobs.CheckJob, checkedAt time.Time) checks.CheckResult {
	return checks.CheckResult{
		EventID:   job.EventID,
		MonitorID: job.MonitorID,
		Type:      Type,
		Status:    checks.ResultStatusFailed,
		CheckedAt: checkedAt,
		Raw:       map[string]any{},
	}
}

func rawResult(cert *x509.Certificate, chain []*x509.Certificate) map[string]any {
	return map[string]any{
		"not_before":        cert.NotBefore.Format(time.RFC3339),
		"not_after":         cert.NotAfter.Format(time.RFC3339),
		"days_until_expiry": int(time.Until(cert.NotAfter).Hours() / 24),
		"issuer":            cert.Issuer.String(),
		"subject":           cert.Subject.String(),
		"dns_names":         cert.DNSNames,
		"chain_length":      len(chain),
	}
}

func errorForStatus(err *checks.CheckError) error {
	if err == nil {
		return nil
	}

	return err
}

func normalizeDomain(value string) string {
	value = strings.TrimSpace(value)
	if value == "" {
		return ""
	}

	if strings.Contains(value, "://") {
		parsed, err := url.Parse(value)
		if err == nil {
			return parsed.Hostname()
		}
	}

	host, _, err := net.SplitHostPort(value)
	if err == nil {
		return host
	}

	return value
}

func stringSetting(settings map[string]any, key string, fallback string) string {
	if value, ok := settings[key].(string); ok && value != "" {
		return value
	}

	return fallback
}

func boolSetting(settings map[string]any, key string, fallback bool) bool {
	if value, ok := settings[key].(bool); ok {
		return value
	}

	return fallback
}

func intSetting(settings map[string]any, key string, fallback int) int {
	value, ok := settings[key]
	if !ok {
		return fallback
	}

	switch typed := value.(type) {
	case int:
		return typed
	case int64:
		return int(typed)
	case float64:
		return int(typed)
	default:
		return fallback
	}
}

func intSliceSetting(settings map[string]any, key string, fallback []int) []int {
	values, ok := settings[key]
	if !ok {
		return fallback
	}

	switch typed := values.(type) {
	case []int:
		return typed
	case []any:
		result := make([]int, 0, len(typed))
		for _, value := range typed {
			switch number := value.(type) {
			case int:
				result = append(result, number)
			case int64:
				result = append(result, int(number))
			case float64:
				result = append(result, int(number))
			}
		}
		if len(result) > 0 {
			return result
		}
	}

	return fallback
}
