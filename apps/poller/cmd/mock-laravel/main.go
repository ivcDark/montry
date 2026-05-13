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
