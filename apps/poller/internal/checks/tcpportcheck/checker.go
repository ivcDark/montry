package tcpportcheck

import (
	"context"
	"errors"
	"fmt"
	"net"
	"net/url"
	"strconv"
	"strings"
	"time"

	"montry/apps/poller/internal/checks"
	"montry/apps/poller/internal/checks/checkutil"
	"montry/apps/poller/internal/jobs"
)

const Type = "tcp_port"

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
		return failedResult(job, startedAt, "tcp_port_invalid_config", err.Error(), false), err
	}

	address := net.JoinHostPort(cfg.host, strconv.Itoa(cfg.port))
	dialer := net.Dialer{}
	responseStartedAt := time.Now()
	conn, err := dialer.DialContext(ctx, "tcp", address)
	responseTime := time.Since(responseStartedAt)
	if conn != nil {
		_ = conn.Close()
	}

	open := err == nil
	raw := map[string]any{
		"open":             open,
		"host":             cfg.host,
		"port":             cfg.port,
		"response_time_ms": responseTime.Milliseconds(),
	}

	result := checks.CheckResult{
		EventID:   job.EventID,
		MonitorID: job.MonitorID,
		Type:      Type,
		Status:    checks.ResultStatusSuccess,
		CheckedAt: startedAt,
		Duration:  time.Since(startedAt),
		Raw:       raw,
	}

	if open != cfg.expectedOpen {
		result.Status = checks.ResultStatusFailed
		if open {
			result.Error = &checks.CheckError{Code: "tcp_port_unexpectedly_open", Message: fmt.Sprintf("TCP port %s is open", address), Temporary: true}
		} else {
			result.Error = &checks.CheckError{Code: "tcp_port_closed", Message: err.Error(), Temporary: isTemporaryNetError(err)}
		}

		if cfg.expectedOpen {
			return result, err
		}

		return result, nil
	}

	if cfg.maxResponseTime > 0 && responseTime > cfg.maxResponseTime {
		result.Status = checks.ResultStatusFailed
		result.Error = &checks.CheckError{Code: "tcp_port_response_too_slow", Message: "TCP connection time exceeded the limit", Temporary: true}
	}

	return result, nil
}

type config struct {
	host            string
	port            int
	expectedOpen    bool
	maxResponseTime time.Duration
}

func parseConfig(job jobs.CheckJob) (config, error) {
	settings := job.Settings
	expected := job.Expected

	host := normalizeHost(checkutil.String(settings, "host", ""))
	if host == "" {
		host = normalizeHost(job.Target)
	}
	if host == "" {
		return config{}, fmt.Errorf("host is required")
	}

	port := checkutil.Int(settings, "port", 443)
	if port < 1 || port > 65535 {
		return config{}, fmt.Errorf("port must be between 1 and 65535")
	}

	return config{
		host:            host,
		port:            port,
		expectedOpen:    checkutil.Bool(expected, "open", true),
		maxResponseTime: checkutil.DurationMillis(expected, "max_response_time_ms"),
	}, nil
}

func failedResult(job jobs.CheckJob, checkedAt time.Time, code string, message string, temporary bool) checks.CheckResult {
	return checks.CheckResult{
		EventID:   job.EventID,
		MonitorID: job.MonitorID,
		Type:      Type,
		Status:    checks.ResultStatusFailed,
		CheckedAt: checkedAt,
		Duration:  time.Since(checkedAt),
		Raw:       map[string]any{},
		Error: &checks.CheckError{
			Code:      code,
			Message:   message,
			Temporary: temporary,
		},
	}
}

func normalizeHost(value string) string {
	value = strings.TrimSpace(strings.ToLower(value))
	if value == "" {
		return ""
	}

	if strings.Contains(value, "://") {
		parsed, err := url.Parse(value)
		if err == nil {
			value = parsed.Hostname()
		}
	}

	if host, _, err := net.SplitHostPort(value); err == nil {
		value = host
	}

	return strings.TrimSuffix(strings.Trim(value, "/"), ".")
}

func isTemporaryNetError(err error) bool {
	if err == nil {
		return false
	}

	var netErr net.Error
	return strings.Contains(strings.ToLower(err.Error()), "timeout") || errors.As(err, &netErr) && netErr.Timeout()
}
