package sitemapxmlcheck

import (
	"bytes"
	"context"
	"crypto/tls"
	"encoding/xml"
	"errors"
	"fmt"
	"io"
	"net"
	"net/http"
	"net/url"
	"time"

	"montry/apps/poller/internal/checks"
	"montry/apps/poller/internal/checks/checkutil"
	"montry/apps/poller/internal/jobs"
)

const Type = "sitemap_xml"

const maxBodyBytes = 4 * 1024 * 1024

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
		return failedResult(job, startedAt, "sitemap_xml_invalid_url", err.Error(), false), err
	}

	req, err := http.NewRequestWithContext(ctx, http.MethodGet, cfg.url, nil)
	if err != nil {
		return failedResult(job, startedAt, "sitemap_xml_invalid_url", err.Error(), false), err
	}
	req.Header.Set("User-Agent", "Montry-Poller/1.0")

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

	body, readErr := io.ReadAll(io.LimitReader(resp.Body, maxBodyBytes))
	if readErr != nil {
		return failedResult(job, startedAt, "sitemap_xml_read_failed", readErr.Error(), true), readErr
	}

	validXML, urlCount, sitemapCount, parseErr := parseSitemapXML(body)

	raw := map[string]any{
		"exists":           resp.StatusCode == http.StatusOK,
		"valid_xml":        validXML,
		"status_code":      resp.StatusCode,
		"response_time_ms": responseTime.Milliseconds(),
		"url_count":        urlCount,
		"sitemap_count":    sitemapCount,
		"headers":          checkutil.BasicHeaders(resp.Header),
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

	if !checkutil.StatusAllowed(resp.StatusCode, cfg.expectedStatusCodes) {
		result.Status = checks.ResultStatusFailed
		result.Error = &checks.CheckError{
			Code:      "sitemap_xml_invalid_status",
			Message:   fmt.Sprintf("unexpected sitemap.xml HTTP status code: %d", resp.StatusCode),
			Temporary: true,
		}

		return result, nil
	}

	if cfg.expectedExists && resp.StatusCode != http.StatusOK {
		result.Status = checks.ResultStatusFailed
		result.Error = &checks.CheckError{Code: "sitemap_xml_not_found", Message: "sitemap.xml was not found", Temporary: true}
	}

	if cfg.expectedValidXML && !validXML {
		result.Status = checks.ResultStatusFailed
		message := "sitemap.xml is not a valid urlset or sitemapindex document"
		if parseErr != nil {
			message = parseErr.Error()
		}
		result.Error = &checks.CheckError{Code: "sitemap_xml_invalid_xml", Message: message, Temporary: true}
	}

	if cfg.maxResponseTime > 0 && responseTime > cfg.maxResponseTime {
		result.Status = checks.ResultStatusFailed
		result.Error = &checks.CheckError{Code: "sitemap_xml_response_too_slow", Message: "sitemap.xml response time exceeded the limit", Temporary: true}
	}

	return result, nil
}

type config struct {
	url                 string
	followRedirects     bool
	verifySSL           bool
	expectedExists      bool
	expectedValidXML    bool
	expectedStatusCodes []int
	maxResponseTime     time.Duration
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

	return config{
		url:                 target,
		followRedirects:     checkutil.Bool(settings, "follow_redirects", true),
		verifySSL:           checkutil.Bool(settings, "verify_ssl", true),
		expectedExists:      checkutil.Bool(expected, "exists", true),
		expectedValidXML:    checkutil.Bool(expected, "valid_xml", true),
		expectedStatusCodes: checkutil.IntSlice(expected, "status_codes", []int{http.StatusOK}),
		maxResponseTime:     checkutil.DurationMillis(expected, "max_response_time_ms"),
	}, nil
}

func parseSitemapXML(body []byte) (bool, int, int, error) {
	decoder := xml.NewDecoder(bytes.NewReader(body))
	urlCount := 0
	sitemapCount := 0
	root := ""

	for {
		token, err := decoder.Token()
		if errors.Is(err, io.EOF) {
			break
		}
		if err != nil {
			return false, urlCount, sitemapCount, err
		}

		start, ok := token.(xml.StartElement)
		if !ok {
			continue
		}

		if root == "" {
			root = start.Name.Local
		}

		switch start.Name.Local {
		case "url":
			urlCount++
		case "sitemap":
			sitemapCount++
		}
	}

	if root != "urlset" && root != "sitemapindex" {
		return false, urlCount, sitemapCount, fmt.Errorf("unexpected sitemap root element: %s", root)
	}

	return true, urlCount, sitemapCount, nil
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
		return failedResult(job, checkedAt, "sitemap_xml_timeout", "sitemap.xml check timed out", true), err
	}

	var urlErr *url.Error
	if errors.As(err, &urlErr) {
		var netErr net.Error
		if errors.As(urlErr.Err, &netErr) && netErr.Timeout() {
			return failedResult(job, checkedAt, "sitemap_xml_timeout", "sitemap.xml check timed out", true), err
		}
	}

	return failedResult(job, checkedAt, "sitemap_xml_network_error", err.Error(), true), err
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
