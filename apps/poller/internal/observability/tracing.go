package observability

import (
	"bytes"
	"context"
	"crypto/rand"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"net/http"
	"regexp"
	"strings"
	"time"
)

const (
	SpanKindInternal = 1
	SpanKindServer   = 2
	SpanKindClient   = 3
	SpanKindProducer = 4
	SpanKindConsumer = 5
)

type TracerConfig struct {
	Enabled     bool
	Endpoint    string
	ServiceName string
	Environment string
	Timeout     time.Duration
}

type Tracer struct {
	enabled     bool
	endpoint    string
	serviceName string
	environment string
	httpClient  *http.Client
}

type Span struct {
	tracer         *Tracer
	name           string
	traceID        string
	spanID         string
	parentSpanID   string
	kind           int
	attributes     map[string]any
	startUnixNanos int64
	endUnixNanos   int64
}

var traceparentPattern = regexp.MustCompile(`^00-([a-f0-9]{32})-([a-f0-9]{16})-([a-f0-9]{2})$`)

func NewTracer(cfg TracerConfig) *Tracer {
	timeout := cfg.Timeout
	if timeout <= 0 {
		timeout = 2 * time.Second
	}

	endpoint := strings.TrimRight(cfg.Endpoint, "/")
	if endpoint == "" {
		endpoint = "http://otel-collector:4318"
	}

	serviceName := cfg.ServiceName
	if serviceName == "" {
		serviceName = "montry-poller"
	}

	environment := cfg.Environment
	if environment == "" {
		environment = "local"
	}

	return &Tracer{
		enabled:     cfg.Enabled,
		endpoint:    endpoint,
		serviceName: serviceName,
		environment: environment,
		httpClient:  &http.Client{Timeout: timeout},
	}
}

func (t *Tracer) StartSpan(name string, traceparent string, kind int, attributes map[string]any) *Span {
	traceID, parentSpanID := parseTraceParent(traceparent)
	if traceID == "" {
		traceID = randomHex(16)
	}

	spanID := randomHex(8)
	if attributes == nil {
		attributes = map[string]any{}
	}
	attributes["service.name"] = t.serviceName
	attributes["deployment.environment"] = t.environment

	return &Span{
		tracer:         t,
		name:           name,
		traceID:        traceID,
		spanID:         spanID,
		parentSpanID:   parentSpanID,
		kind:           kind,
		attributes:     attributes,
		startUnixNanos: time.Now().UnixNano(),
	}
}

func (s *Span) TraceParent() string {
	return fmt.Sprintf("00-%s-%s-01", s.traceID, s.spanID)
}

func (s *Span) End(ctx context.Context, statusCode string) {
	if s == nil || s.endUnixNanos != 0 {
		return
	}

	if statusCode == "" {
		statusCode = "STATUS_CODE_OK"
	}

	s.endUnixNanos = time.Now().UnixNano()
	s.tracer.export(ctx, s, statusCode)
}

func (t *Tracer) export(ctx context.Context, span *Span, statusCode string) {
	if t == nil || !t.enabled {
		return
	}

	payload, err := json.Marshal(t.payload(span, statusCode))
	if err != nil {
		return
	}

	req, err := http.NewRequestWithContext(ctx, http.MethodPost, t.endpoint+"/v1/traces", bytes.NewReader(payload))
	if err != nil {
		return
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Accept", "application/json")

	resp, err := t.httpClient.Do(req)
	if err != nil {
		return
	}
	defer resp.Body.Close()
}

func (t *Tracer) payload(span *Span, statusCode string) map[string]any {
	encoded := map[string]any{
		"traceId":           span.traceID,
		"spanId":            span.spanID,
		"name":              span.name,
		"kind":              span.kind,
		"startTimeUnixNano": fmt.Sprintf("%d", span.startUnixNanos),
		"endTimeUnixNano":   fmt.Sprintf("%d", span.endUnixNanos),
		"attributes":        otlpAttributes(span.attributes),
		"status": map[string]any{
			"code": statusCode,
		},
	}
	if span.parentSpanID != "" {
		encoded["parentSpanId"] = span.parentSpanID
	}

	return map[string]any{
		"resourceSpans": []any{
			map[string]any{
				"resource": map[string]any{
					"attributes": otlpAttributes(map[string]any{
						"service.name":           t.serviceName,
						"service.namespace":      "montry",
						"deployment.environment": t.environment,
					}),
				},
				"scopeSpans": []any{
					map[string]any{
						"scope": map[string]any{
							"name": "montry.poller",
						},
						"spans": []any{encoded},
					},
				},
			},
		},
	}
}

func parseTraceParent(traceparent string) (string, string) {
	matches := traceparentPattern.FindStringSubmatch(strings.ToLower(strings.TrimSpace(traceparent)))
	if len(matches) != 4 {
		return "", ""
	}

	return matches[1], matches[2]
}

func randomHex(bytesLen int) string {
	buf := make([]byte, bytesLen)
	if _, err := rand.Read(buf); err != nil {
		return strings.Repeat("0", bytesLen*2)
	}

	return hex.EncodeToString(buf)
}

func otlpAttributes(attributes map[string]any) []any {
	result := make([]any, 0, len(attributes))
	for key, value := range attributes {
		encoded := otlpAttributeValue(value)
		if encoded == nil {
			continue
		}
		result = append(result, map[string]any{
			"key":   key,
			"value": encoded,
		})
	}

	return result
}

func otlpAttributeValue(value any) map[string]any {
	switch typed := value.(type) {
	case string:
		return map[string]any{"stringValue": typed}
	case bool:
		return map[string]any{"boolValue": typed}
	case int:
		return map[string]any{"intValue": fmt.Sprintf("%d", typed)}
	case int64:
		return map[string]any{"intValue": fmt.Sprintf("%d", typed)}
	case float64:
		return map[string]any{"doubleValue": typed}
	default:
		return nil
	}
}
