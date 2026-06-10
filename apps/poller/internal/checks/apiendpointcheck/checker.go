package apiendpointcheck

import (
	"bytes"
	"context"
	"crypto/tls"
	"errors"
	"fmt"
	"io"
	"net"
	"net/http"
	"net/http/httptrace"
	"net/url"
	"strings"
	"time"

	"montry/apps/poller/internal/checks"
	"montry/apps/poller/internal/checks/checkutil"
	"montry/apps/poller/internal/jobs"
)

const Type = "api_endpoint"

const maxBodyBytes = 2 * 1024 * 1024

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
		return failedResult(job, startedAt, "api_endpoint_invalid_config", err.Error(), false), err
	}

	var body io.Reader
	if cfg.body != "" {
		body = bytes.NewBufferString(cfg.body)
	}

	req, err := http.NewRequestWithContext(ctx, cfg.method, cfg.url, body)
	if err != nil {
		return failedResult(job, startedAt, "api_endpoint_invalid_url", err.Error(), false), err
	}

	for key, value := range cfg.headers {
		req.Header.Set(key, value)
	}
	if cfg.body != "" && req.Header.Get("Content-Type") == "" {
		req.Header.Set("Content-Type", "application/json")
	}

	var remoteIP string
	req = req.WithContext(httptrace.WithClientTrace(req.Context(), &httptrace.ClientTrace{
		GotConn: func(info httptrace.GotConnInfo) {
			if tcpAddr, ok := info.Conn.RemoteAddr().(*net.TCPAddr); ok {
				remoteIP = tcpAddr.IP.String()
			}
		},
	}))

	client := http.Client{
		CheckRedirect: redirectPolicy(cfg.followRedirects),
		Transport: &http.Transport{
			TLSClientConfig: &tls.Config{InsecureSkipVerify: !cfg.verifySSL}, //nolint:gosec
		},
	}

	responseStartedAt := time.Now()
	resp, err := client.Do(req)
	responseTime := time.Since(responseStartedAt)
	if err != nil {
		return classifyRequestError(ctx, job, startedAt, err)
	}
	defer resp.Body.Close()

	responseBody, readErr := io.ReadAll(io.LimitReader(resp.Body, maxBodyBytes))
	if readErr != nil {
		return failedResult(job, startedAt, "api_endpoint_read_failed", readErr.Error(), true), readErr
	}

	containsMatched := any(nil)
	if cfg.responseContains != "" {
		containsMatched = strings.Contains(string(responseBody), cfg.responseContains)
	}

	raw := map[string]any{
		"status_code":               resp.StatusCode,
		"response_time_ms":          responseTime.Milliseconds(),
		"headers":                   checkutil.BasicHeaders(resp.Header),
		"response_contains_matched": containsMatched,
		"ip":                        remoteIP,
		"final_url":                 resp.Request.URL.String(),
		"body_size":                 len(responseBody),
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

	if !checkutil.StatusAllowed(resp.StatusCode, cfg.expectedStatusCodes) {
		result.Status = checks.ResultStatusFailed
		result.Error = &checks.CheckError{
			Code:      "api_endpoint_invalid_status",
			Message:   fmt.Sprintf("unexpected API endpoint HTTP status code: %d", resp.StatusCode),
			Temporary: true,
		}

		return result, nil
	}

	if cfg.maxResponseTime > 0 && responseTime > cfg.maxResponseTime {
		result.Status = checks.ResultStatusFailed
		result.Error = &checks.CheckError{Code: "api_endpoint_response_too_slow", Message: "API endpoint response time exceeded the limit", Temporary: true}

		return result, nil
	}

	if cfg.responseContains != "" && containsMatched == false {
		result.Status = checks.ResultStatusFailed
		result.Error = &checks.CheckError{Code: "api_endpoint_response_missing_text", Message: "API endpoint response does not contain expected text", Temporary: true}
	}

	return result, nil
}

type config struct {
	method              string
	url                 string
	headers             map[string]string
	body                string
	followRedirects     bool
	verifySSL           bool
	expectedStatusCodes []int
	maxResponseTime     time.Duration
	responseContains    string
}

func parseConfig(job jobs.CheckJob) (config, error) {
	settings := job.Settings
	expected := job.Expected

	target := checkutil.String(settings, "url", "")
	if target == "" {
		target = job.Target
	}

	parsedURL, err := url.ParseRequestURI(target)
	if err != nil || parsedURL.Scheme == "" || parsedURL.Host == "" {
		return config{}, fmt.Errorf("invalid URL: %s", target)
	}

	method := strings.ToUpper(checkutil.String(settings, "method", http.MethodGet))
	if !allowedMethod(method) {
		return config{}, fmt.Errorf("unsupported API method: %s", method)
	}

	return config{
		method:              method,
		url:                 target,
		headers:             checkutil.Headers(settings, "headers"),
		body:                checkutil.String(settings, "body", ""),
		followRedirects:     checkutil.Bool(settings, "follow_redirects", true),
		verifySSL:           checkutil.Bool(settings, "verify_ssl", true),
		expectedStatusCodes: checkutil.IntSlice(expected, "status_codes", []int{http.StatusOK}),
		maxResponseTime:     checkutil.DurationMillis(expected, "max_response_time_ms"),
		responseContains:    strings.TrimSpace(checkutil.String(expected, "response_contains", "")),
	}, nil
}

func allowedMethod(method string) bool {
	switch method {
	case http.MethodGet, http.MethodPost, http.MethodPut, http.MethodPatch, http.MethodDelete, http.MethodHead, http.MethodOptions:
		return true
	default:
		return false
	}
}

func redirectPolicy(follow bool) func(*http.Request, []*http.Request) error {
	if follow {
		return nil
	}

	return func(*http.Request, []*http.Request) error {
		return http.ErrUseLastResponse
	}
}

func classifyRequestError(ctx context.Context, job jobs.CheckJob, checkedAt time.Time, err error) (checks.CheckResult, error) {
	if errors.Is(ctx.Err(), context.DeadlineExceeded) {
		return failedResult(job, checkedAt, "api_endpoint_timeout", "API endpoint check timed out", true), err
	}

	var urlErr *url.Error
	if errors.As(err, &urlErr) {
		var netErr net.Error
		if errors.As(urlErr.Err, &netErr) && netErr.Timeout() {
			return failedResult(job, checkedAt, "api_endpoint_timeout", "API endpoint check timed out", true), err
		}

		if strings.Contains(strings.ToLower(urlErr.Err.Error()), "certificate") || strings.Contains(strings.ToLower(urlErr.Err.Error()), "tls") {
			return failedResult(job, checkedAt, "api_endpoint_tls_error", urlErr.Err.Error(), true), err
		}
	}

	return failedResult(job, checkedAt, "api_endpoint_network_error", err.Error(), true), err
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
