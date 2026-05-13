package httpcheck

import (
	"context"
	"crypto/tls"
	"errors"
	"fmt"
	"net"
	"net/http"
	"net/url"
	"strings"
	"time"

	"montri/apps/poller/internal/checks"
	"montri/apps/poller/internal/jobs"
)

const Type = "http"

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
		return failedResult(job, startedAt, "http_invalid_url", err.Error(), false), err
	}

	req, err := http.NewRequestWithContext(ctx, cfg.method, cfg.url, nil)
	if err != nil {
		return failedResult(job, startedAt, "http_invalid_url", err.Error(), false), err
	}

	for key, value := range cfg.headers {
		req.Header.Set(key, value)
	}

	client := http.Client{
		CheckRedirect: redirectPolicy(cfg.followRedirects),
		Transport: &http.Transport{
			TLSClientConfig: &tls.Config{
				InsecureSkipVerify: !cfg.verifySSL, //nolint:gosec
			},
		},
	}

	responseStartedAt := time.Now()
	resp, err := client.Do(req)
	responseTime := time.Since(responseStartedAt)
	if err != nil {
		return classifyRequestError(ctx, job, startedAt, err)
	}
	defer resp.Body.Close()

	raw := map[string]any{
		"status_code":      resp.StatusCode,
		"response_time_ms": responseTime.Milliseconds(),
		"headers":          basicHeaders(resp.Header),
		"final_url":        resp.Request.URL.String(),
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

	if !statusAllowed(resp.StatusCode, cfg.expectedStatusCodes) {
		result.Status = checks.ResultStatusFailed
		result.Error = &checks.CheckError{
			Code:      "http_invalid_status",
			Message:   fmt.Sprintf("unexpected HTTP status code: %d", resp.StatusCode),
			Temporary: true,
		}

		return result, nil
	}

	if cfg.maxResponseTime > 0 && responseTime > cfg.maxResponseTime {
		result.Status = checks.ResultStatusWarning
	}

	return result, nil
}

type config struct {
	method              string
	url                 string
	followRedirects     bool
	verifySSL           bool
	headers             map[string]string
	expectedStatusCodes []int
	maxResponseTime     time.Duration
}

func parseConfig(job jobs.CheckJob) (config, error) {
	settings := job.Settings
	expected := job.Expected

	target := stringSetting(settings, "url", "")
	if target == "" {
		target = job.Target
	}

	parsedURL, err := url.ParseRequestURI(target)
	if err != nil || parsedURL.Scheme == "" || parsedURL.Host == "" {
		return config{}, fmt.Errorf("invalid URL: %s", target)
	}

	method := strings.ToUpper(stringSetting(settings, "method", http.MethodGet))
	if method != http.MethodGet && method != http.MethodHead {
		method = http.MethodGet
	}

	return config{
		method:              method,
		url:                 target,
		followRedirects:     boolSetting(settings, "follow_redirects", true),
		verifySSL:           boolSetting(settings, "verify_ssl", true),
		headers:             headersSetting(settings, "headers"),
		expectedStatusCodes: intSliceSetting(expected, "status_codes", []int{http.StatusOK}),
		maxResponseTime:     durationMillisSetting(expected, "max_response_time_ms"),
	}, nil
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
		return failedResult(job, checkedAt, "http_timeout", "HTTP check timed out", true), err
	}

	var urlErr *url.Error
	if errors.As(err, &urlErr) {
		var netErr net.Error
		if errors.As(urlErr.Err, &netErr) && netErr.Timeout() {
			return failedResult(job, checkedAt, "http_timeout", "HTTP check timed out", true), err
		}

		if strings.Contains(strings.ToLower(urlErr.Err.Error()), "certificate") ||
			strings.Contains(strings.ToLower(urlErr.Err.Error()), "tls") {
			return failedResult(job, checkedAt, "http_tls_error", urlErr.Err.Error(), true), err
		}
	}

	return failedResult(job, checkedAt, "http_network_error", err.Error(), true), err
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

func statusAllowed(statusCode int, expected []int) bool {
	for _, allowed := range expected {
		if statusCode == allowed {
			return true
		}
	}

	return false
}

func basicHeaders(headers http.Header) map[string]any {
	result := make(map[string]any)
	for _, key := range []string{"content-type", "server", "location"} {
		if value := headers.Get(key); value != "" {
			result[key] = value
		}
	}

	return result
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

func headersSetting(settings map[string]any, key string) map[string]string {
	values, ok := settings[key].(map[string]any)
	if !ok {
		return map[string]string{}
	}

	headers := make(map[string]string, len(values))
	for header, value := range values {
		if stringValue, ok := value.(string); ok {
			headers[header] = stringValue
		}
	}

	return headers
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

func durationMillisSetting(settings map[string]any, key string) time.Duration {
	value, ok := settings[key]
	if !ok {
		return 0
	}

	switch typed := value.(type) {
	case int:
		return time.Duration(typed) * time.Millisecond
	case int64:
		return time.Duration(typed) * time.Millisecond
	case float64:
		return time.Duration(typed) * time.Millisecond
	default:
		return 0
	}
}
