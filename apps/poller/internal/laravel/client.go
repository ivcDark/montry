package laravel

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net"
	"net/http"
	"net/url"
	"strconv"
	"strings"
	"time"

	"montry/apps/poller/internal/checks"
	"montry/apps/poller/internal/jobs"
	"montry/apps/poller/internal/observability"
)

type LaravelClient interface {
	FetchDueChecks(ctx context.Context, limit int) ([]jobs.CheckJob, error)
	FetchManualChecks(ctx context.Context, limit int) ([]jobs.CheckJob, error)
	SubmitCheckResult(ctx context.Context, result checks.CheckResult) error
}

type HTTPClientConfig struct {
	BaseURL    string
	Token      string
	Timeout    time.Duration
	MaxRetries int
	RetryDelay time.Duration
	Tracer     *observability.Tracer
}

type HTTPClient struct {
	baseURL    string
	httpClient *http.Client
	signer     Signer
	maxRetries int
	retryDelay time.Duration
	tracer     *observability.Tracer
}

func NewHTTPClient(cfg HTTPClientConfig) *HTTPClient {
	timeout := cfg.Timeout
	if timeout <= 0 {
		timeout = 10 * time.Second
	}

	maxRetries := cfg.MaxRetries
	if maxRetries < 0 {
		maxRetries = 0
	}

	retryDelay := cfg.RetryDelay
	if retryDelay <= 0 {
		retryDelay = 100 * time.Millisecond
	}

	return &HTTPClient{
		baseURL: strings.TrimRight(cfg.BaseURL, "/"),
		httpClient: &http.Client{
			Timeout: timeout,
		},
		signer:     NewBearerTokenSigner(cfg.Token),
		maxRetries: maxRetries,
		retryDelay: retryDelay,
		tracer:     cfg.Tracer,
	}
}

func (c *HTTPClient) FetchDueChecks(ctx context.Context, limit int) ([]jobs.CheckJob, error) {
	return c.fetchChecks(ctx, "/internal/monitors/due", limit, jobs.SourceScheduled)
}

func (c *HTTPClient) FetchManualChecks(ctx context.Context, limit int) ([]jobs.CheckJob, error) {
	return c.fetchChecks(ctx, "/internal/manual-checks", limit, jobs.SourceManual)
}

func (c *HTTPClient) SubmitCheckResult(ctx context.Context, result checks.CheckResult) error {
	payload := newCheckResultPayload(result)

	var body bytes.Buffer
	if err := json.NewEncoder(&body).Encode(payload); err != nil {
		return err
	}

	resp, err := c.do(ctx, http.MethodPost, "/internal/check-results", nil, body.Bytes(), result.CorrelationID, result.TraceParent)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if err := errorFromResponse(resp); err != nil {
		return err
	}

	return nil
}

func (c *HTTPClient) fetchChecks(ctx context.Context, path string, limit int, source jobs.JobSource) ([]jobs.CheckJob, error) {
	query := url.Values{}
	if limit > 0 {
		query.Set("limit", strconv.Itoa(limit))
	}

	traceparent := ""
	span := c.startSpan("laravel.fetch_checks", "", observability.SpanKindClient, map[string]any{
		"http.request.method": "GET",
		"url.path":            path,
		"job.source":          string(source),
	})
	if span != nil {
		traceparent = span.TraceParent()
	}

	resp, err := c.do(ctx, http.MethodGet, path, query, nil, "", traceparent)
	if err != nil {
		c.endSpan(ctx, span, "STATUS_CODE_ERROR")
		return nil, err
	}
	defer resp.Body.Close()

	if err := errorFromResponse(resp); err != nil {
		c.endSpan(ctx, span, "STATUS_CODE_ERROR")
		return nil, err
	}

	var payload dueChecksResponse
	if err := json.NewDecoder(resp.Body).Decode(&payload); err != nil {
		c.endSpan(ctx, span, "STATUS_CODE_ERROR")
		return nil, fmt.Errorf("decode due checks response: %w", err)
	}

	checkJobs := make([]jobs.CheckJob, 0, len(payload.Data))
	for _, item := range payload.Data {
		checkJob, err := item.toJob(source)
		if err != nil {
			c.endSpan(ctx, span, "STATUS_CODE_ERROR")
			return nil, fmt.Errorf("decode due check job: %w", err)
		}

		checkJobs = append(checkJobs, checkJob)
	}

	c.endSpan(ctx, span, "STATUS_CODE_OK")
	return checkJobs, nil
}

func (c *HTTPClient) do(ctx context.Context, method string, path string, query url.Values, body []byte, correlationID string, traceparent string) (*http.Response, error) {
	var lastErr error

	for attempt := 0; attempt <= c.maxRetries; attempt++ {
		resp, err := c.doOnce(ctx, method, path, query, body, correlationID, traceparent)
		if err == nil && !isTemporaryStatus(resp.StatusCode) {
			return resp, nil
		}

		if err == nil {
			lastErr = errorFromResponse(resp)
			resp.Body.Close()
		} else {
			lastErr = err
		}

		if attempt == c.maxRetries || !isTemporaryError(lastErr) {
			return nil, lastErr
		}

		timer := time.NewTimer(c.retryDelay * time.Duration(attempt+1))
		select {
		case <-ctx.Done():
			timer.Stop()
			return nil, ctx.Err()
		case <-timer.C:
		}
	}

	return nil, lastErr
}

func (c *HTTPClient) doOnce(ctx context.Context, method string, path string, query url.Values, body []byte, correlationID string, traceparent string) (*http.Response, error) {
	endpoint := c.baseURL + path
	if len(query) > 0 {
		endpoint += "?" + query.Encode()
	}

	var reader io.Reader
	if body != nil {
		reader = bytes.NewReader(body)
	}

	req, err := http.NewRequestWithContext(ctx, method, endpoint, reader)
	if err != nil {
		return nil, err
	}

	req.Header.Set("Accept", "application/json")
	if body != nil {
		req.Header.Set("Content-Type", "application/json")
	}
	if correlationID != "" {
		req.Header.Set("X-Correlation-ID", correlationID)
	}
	if traceparent != "" {
		req.Header.Set("traceparent", traceparent)
	}
	c.signer.Sign(req)

	return c.httpClient.Do(req)
}

func (c *HTTPClient) startSpan(name string, traceparent string, kind int, attributes map[string]any) *observability.Span {
	if c.tracer == nil {
		return nil
	}

	return c.tracer.StartSpan(name, traceparent, kind, attributes)
}

func (c *HTTPClient) endSpan(ctx context.Context, span *observability.Span, status string) {
	if span == nil {
		return
	}

	span.End(ctx, status)
}

func errorFromResponse(resp *http.Response) error {
	if resp.StatusCode >= 200 && resp.StatusCode < 300 {
		return nil
	}

	body, _ := io.ReadAll(io.LimitReader(resp.Body, 4096))
	return &APIError{
		StatusCode: resp.StatusCode,
		Body:       strings.TrimSpace(string(body)),
	}
}

func isTemporaryStatus(statusCode int) bool {
	return statusCode == http.StatusTooManyRequests || statusCode >= 500
}

func isTemporaryError(err error) bool {
	if err == nil {
		return false
	}

	var apiErr *APIError
	if AsAPIError(err, &apiErr) {
		return apiErr.Temporary()
	}

	var netErr net.Error
	if errors.As(err, &netErr) && netErr.Timeout() {
		return true
	}

	return false
}
