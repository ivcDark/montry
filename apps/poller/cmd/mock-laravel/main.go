package main

import (
	"encoding/json"
	"log"
	"net/http"
	"os"
	"time"
)

func main() {
	addr := env("MOCK_LARAVEL_ADDR", ":8081")

	mux := http.NewServeMux()
	mux.HandleFunc("/internal/monitors/due", dueChecks)
	mux.HandleFunc("/internal/check-results", checkResults)
	mux.HandleFunc("/health", health)

	log.Printf("mock Laravel internal API listening on %s", addr)
	if err := http.ListenAndServe(addr, mux); err != nil {
		log.Fatal(err)
	}
}

func dueChecks(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		writeJSON(w, http.StatusMethodNotAllowed, map[string]any{"error": "method_not_allowed"})
		return
	}

	now := time.Now().UTC().Format(time.RFC3339)
	writeJSON(w, http.StatusOK, map[string]any{
		"data": []map[string]any{
			{
				"id":         "mock-http-job",
				"event_id":   "mock-http-event",
				"monitor_id": 1,
				"check_type": "http",
				"target":     "https://example.com",
				"settings": map[string]any{
					"method":           "GET",
					"follow_redirects": true,
					"verify_ssl":       true,
				},
				"expected": map[string]any{
					"status_codes":         []int{200},
					"max_response_time_ms": 5000,
				},
				"timeout_ms":   10000,
				"requested_at": now,
			},
			{
				"id":         "mock-ssl-job",
				"event_id":   "mock-ssl-event",
				"monitor_id": 2,
				"check_type": "ssl",
				"target":     "example.com",
				"settings": map[string]any{
					"domain":       "example.com",
					"port":         443,
					"warning_days": []int{30, 14, 7, 3, 1},
				},
				"expected":     map[string]any{},
				"timeout_ms":   10000,
				"requested_at": now,
			},
			{
				"id":         "mock-domain-job",
				"event_id":   "mock-domain-event",
				"monitor_id": 3,
				"check_type": "domain",
				"target":     "example.com",
				"settings": map[string]any{
					"domain":       "example.com",
					"warning_days": []int{30, 14, 7, 3, 1},
				},
				"expected":     map[string]any{},
				"timeout_ms":   15000,
				"requested_at": now,
			},
			{
				"id":         "mock-dns-job",
				"event_id":   "mock-dns-event",
				"monitor_id": 4,
				"check_type": "dns",
				"target":     "example.com",
				"settings": map[string]any{
					"domain":       "example.com",
					"record_types": []string{"A", "AAAA"},
					"nameservers":  []string{},
				},
				"expected": map[string]any{
					"resolves":    true,
					"min_records": 1,
				},
				"timeout_ms":   10000,
				"requested_at": now,
			},
			{
				"id":         "mock-robots-job",
				"event_id":   "mock-robots-event",
				"monitor_id": 5,
				"check_type": "robots_txt",
				"target":     "https://example.com/robots.txt",
				"settings": map[string]any{
					"url":              "https://example.com/robots.txt",
					"follow_redirects": true,
					"verify_ssl":       true,
				},
				"expected": map[string]any{
					"exists":               true,
					"status_codes":         []int{200},
					"max_response_time_ms": 5000,
				},
				"timeout_ms":   10000,
				"requested_at": now,
			},
			{
				"id":         "mock-sitemap-job",
				"event_id":   "mock-sitemap-event",
				"monitor_id": 6,
				"check_type": "sitemap_xml",
				"target":     "https://example.com/sitemap.xml",
				"settings": map[string]any{
					"url":              "https://example.com/sitemap.xml",
					"follow_redirects": true,
					"verify_ssl":       true,
				},
				"expected": map[string]any{
					"exists":               true,
					"valid_xml":            true,
					"status_codes":         []int{200},
					"max_response_time_ms": 5000,
				},
				"timeout_ms":   10000,
				"requested_at": now,
			},
			{
				"id":         "mock-api-job",
				"event_id":   "mock-api-event",
				"monitor_id": 7,
				"check_type": "api_endpoint",
				"target":     "https://example.com",
				"settings": map[string]any{
					"method":           "GET",
					"url":              "https://example.com",
					"headers":          map[string]string{},
					"follow_redirects": true,
					"verify_ssl":       true,
				},
				"expected": map[string]any{
					"status_codes":         []int{200},
					"max_response_time_ms": 5000,
				},
				"timeout_ms":   10000,
				"requested_at": now,
			},
			{
				"id":         "mock-tcp-job",
				"event_id":   "mock-tcp-event",
				"monitor_id": 8,
				"check_type": "tcp_port",
				"target":     "example.com",
				"settings": map[string]any{
					"host": "example.com",
					"port": 443,
				},
				"expected": map[string]any{
					"open":                 true,
					"max_response_time_ms": 5000,
				},
				"timeout_ms":   10000,
				"requested_at": now,
			},
		},
	})
}

func checkResults(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		writeJSON(w, http.StatusMethodNotAllowed, map[string]any{"error": "method_not_allowed"})
		return
	}

	var payload map[string]any
	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]any{"error": "invalid_json"})
		return
	}

	log.Printf("received check result: event_id=%v monitor_id=%v check_type=%v status=%v error=%v",
		payload["event_id"],
		payload["monitor_id"],
		payload["check_type"],
		payload["status"],
		payload["error"],
	)

	writeJSON(w, http.StatusCreated, map[string]any{
		"id":     time.Now().UnixNano(),
		"status": payload["status"],
	})
}

func health(w http.ResponseWriter, _ *http.Request) {
	writeJSON(w, http.StatusOK, map[string]any{"status": "ok"})
}

func writeJSON(w http.ResponseWriter, status int, payload map[string]any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(payload)
}

func env(key string, fallback string) string {
	value := os.Getenv(key)
	if value == "" {
		return fallback
	}
	return value
}
