package http

import (
	"context"
	"encoding/json"
	"net/http"
	"strconv"
	"strings"
	"time"

	"montry/apps/poller/internal/checks"
	"montry/apps/poller/internal/jobs"
	"montry/apps/poller/internal/logger"
	"montry/apps/poller/internal/observability"
)

type Options struct {
	ManualJobs           chan<- jobs.CheckJob
	CheckRegistry        checks.Registry
	ManualAuthToken      string
	ManualRequestTimeout time.Duration
	Metrics              *observability.Metrics
	Tracer               *observability.Tracer
	QueueStats           func() (used int, capacity int)
}

type Server struct {
	server        *http.Server
	log           *logger.Logger
	manualJobs    chan<- jobs.CheckJob
	registry      checks.Registry
	manualToken   string
	manualTimeout time.Duration
	metrics       *observability.Metrics
	tracer        *observability.Tracer
	queueStats    func() (used int, capacity int)
}

func NewServer(addr string, log *logger.Logger, options ...Options) *Server {
	mux := http.NewServeMux()
	opts := Options{}
	if len(options) > 0 {
		opts = options[0]
	}
	if opts.ManualRequestTimeout <= 0 {
		opts.ManualRequestTimeout = 5 * time.Second
	}

	server := &Server{
		log:           log,
		manualJobs:    opts.ManualJobs,
		registry:      opts.CheckRegistry,
		manualToken:   opts.ManualAuthToken,
		manualTimeout: opts.ManualRequestTimeout,
		metrics:       opts.Metrics,
		tracer:        opts.Tracer,
		queueStats:    opts.QueueStats,
		server: &http.Server{
			Addr:    addr,
			Handler: mux,
		},
	}

	mux.HandleFunc("/health", server.health)
	mux.HandleFunc("/metrics", server.metricsHandler)
	mux.HandleFunc("/internal/manual-checks", server.manualChecks)

	return server
}

func (s *Server) metricsHandler(w http.ResponseWriter, _ *http.Request) {
	if s.metrics == nil {
		http.NotFound(w, nil)
		return
	}

	if s.queueStats != nil {
		used, capacity := s.queueStats()
		s.metrics.SetQueueStats(used, capacity)
	}

	w.Header().Set("Content-Type", "text/plain; version=0.0.4; charset=utf-8")
	w.WriteHeader(http.StatusOK)
	_, _ = w.Write([]byte(s.metrics.Render()))
}

func (s *Server) Start() error {
	s.log.Info("starting HTTP server", "addr", s.server.Addr)
	return s.server.ListenAndServe()
}

func (s *Server) Shutdown(ctx context.Context) error {
	return s.server.Shutdown(ctx)
}

func (s *Server) health(w http.ResponseWriter, _ *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)

	_ = json.NewEncoder(w).Encode(map[string]any{
		"status": "ok",
	})
}

type manualCheckPayload struct {
	EventID       string         `json:"event_id"`
	EventType     string         `json:"event_type"`
	MonitorID     any            `json:"monitor_id"`
	CheckType     string         `json:"check_type"`
	Target        string         `json:"target"`
	Settings      map[string]any `json:"settings"`
	Expected      map[string]any `json:"expected"`
	TimeoutMS     int64          `json:"timeout_ms"`
	RequestedAt   string         `json:"requested_at"`
	CorrelationID string         `json:"correlation_id"`
	TraceParent   string         `json:"traceparent"`
}

func (s *Server) manualChecks(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		writeJSON(w, http.StatusMethodNotAllowed, map[string]any{"error": "method_not_allowed"})
		return
	}

	if !s.authorized(r) {
		writeJSON(w, http.StatusUnauthorized, map[string]any{"error": "unauthorized"})
		return
	}

	ctx, cancel := context.WithTimeout(r.Context(), s.manualTimeout)
	defer cancel()

	var payload manualCheckPayload
	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]any{"error": "invalid_json"})
		return
	}
	if payload.TraceParent == "" {
		payload.TraceParent = r.Header.Get("traceparent")
	}

	span := s.startManualSpan(payload)
	if span != nil {
		payload.TraceParent = span.TraceParent()
	}

	checkJob, err := payload.toJob()
	if err != nil {
		s.endSpan(r.Context(), span, "STATUS_CODE_ERROR")
		writeJSON(w, http.StatusBadRequest, map[string]any{"error": err.Error()})
		return
	}

	if s.registry == nil {
		s.endSpan(r.Context(), span, "STATUS_CODE_ERROR")
		writeJSON(w, http.StatusServiceUnavailable, map[string]any{"error": "check_registry_unavailable"})
		return
	}
	if _, err := s.registry.Get(checkJob.Type); err != nil {
		s.endSpan(r.Context(), span, "STATUS_CODE_ERROR")
		writeJSON(w, http.StatusUnprocessableEntity, map[string]any{"error": "unknown_check_type"})
		return
	}

	if s.manualJobs == nil {
		s.endSpan(r.Context(), span, "STATUS_CODE_ERROR")
		writeJSON(w, http.StatusServiceUnavailable, map[string]any{"error": "manual_jobs_unavailable"})
		return
	}

	select {
	case <-ctx.Done():
		s.endSpan(r.Context(), span, "STATUS_CODE_ERROR")
		writeJSON(w, http.StatusRequestTimeout, map[string]any{"error": "request_timeout"})
		return
	case s.manualJobs <- checkJob:
		if s.metrics != nil {
			s.metrics.IncJobs(checkJob.Type, string(checkJob.Source), "queued")
		}
		s.endSpan(r.Context(), span, "STATUS_CODE_OK")
		writeJSON(w, http.StatusAccepted, map[string]any{
			"accepted": true,
			"event_id": checkJob.EventID,
		})
	default:
		if s.metrics != nil {
			s.metrics.IncJobs(checkJob.Type, string(checkJob.Source), "queue_full")
		}
		s.log.Warn("manual checks queue is full", "event_id", checkJob.EventID, "check_type", checkJob.Type)
		s.endSpan(r.Context(), span, "STATUS_CODE_ERROR")
		writeJSON(w, http.StatusServiceUnavailable, map[string]any{"error": "manual_jobs_queue_full"})
	}
}

func (s *Server) authorized(r *http.Request) bool {
	if s.manualToken == "" {
		return true
	}

	return r.Header.Get("Authorization") == "Bearer "+s.manualToken
}

func (p manualCheckPayload) toJob() (jobs.CheckJob, error) {
	if p.EventID == "" {
		return jobs.CheckJob{}, errString("event_id is required")
	}
	if p.CheckType == "" {
		return jobs.CheckJob{}, errString("check_type is required")
	}
	if p.Target == "" && stringSetting(p.Settings, "url", "") == "" && stringSetting(p.Settings, "domain", "") == "" {
		return jobs.CheckJob{}, errString("target is required")
	}
	if p.RequestedAt == "" {
		return jobs.CheckJob{}, errString("requested_at is required")
	}

	requestedAt, err := time.Parse(time.RFC3339, p.RequestedAt)
	if err != nil {
		return jobs.CheckJob{}, errString("requested_at must be RFC3339")
	}

	return jobs.CheckJob{
		ID:            p.EventID,
		EventID:       p.EventID,
		MonitorID:     stringifyID(p.MonitorID),
		Type:          p.CheckType,
		Target:        p.Target,
		Settings:      nonNilMap(p.Settings),
		Expected:      nonNilMap(p.Expected),
		Timeout:       time.Duration(p.TimeoutMS) * time.Millisecond,
		RequestedAt:   requestedAt,
		Source:        jobs.SourceManual,
		CorrelationID: p.CorrelationID,
		TraceParent:   p.TraceParent,
	}, nil
}

type errString string

func (e errString) Error() string {
	return string(e)
}

func writeJSON(w http.ResponseWriter, status int, payload map[string]any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(payload)
}

func stringifyID(value any) string {
	switch typed := value.(type) {
	case string:
		return typed
	case float64:
		return strconv.FormatInt(int64(typed), 10)
	case int:
		return strconv.Itoa(typed)
	case int64:
		return strconv.FormatInt(typed, 10)
	default:
		return ""
	}
}

func nonNilMap(value map[string]any) map[string]any {
	if value == nil {
		return map[string]any{}
	}
	return value
}

func stringSetting(settings map[string]any, key string, fallback string) string {
	if settings == nil {
		return fallback
	}
	if value, ok := settings[key].(string); ok && strings.TrimSpace(value) != "" {
		return value
	}
	return fallback
}

func (s *Server) startManualSpan(payload manualCheckPayload) *observability.Span {
	if s.tracer == nil {
		return nil
	}

	return s.tracer.StartSpan("poller.manual_check.receive", payload.TraceParent, observability.SpanKindServer, map[string]any{
		"http.request.method": "POST",
		"url.path":            "/internal/manual-checks",
		"check.type":          payload.CheckType,
		"event.id":            payload.EventID,
	})
}

func (s *Server) endSpan(ctx context.Context, span *observability.Span, status string) {
	if span == nil {
		return
	}

	span.End(ctx, status)
}
