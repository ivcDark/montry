package config

import (
	"testing"
	"time"
)

func TestLoadUsesDefaults(t *testing.T) {
	t.Setenv("LARAVEL_INTERNAL_API_URL", "")
	t.Setenv("LARAVEL_INTERNAL_API_TOKEN", "")
	t.Setenv("LARAVEL_INTERNAL_API_TIMEOUT_SECONDS", "")
	t.Setenv("POLLER_RESULT_RETRY_ATTEMPTS", "")
	t.Setenv("POLLER_RESULT_RETRY_DELAY_SECONDS", "")
	t.Setenv("POLLER_SCHEDULER_INTERVAL_SECONDS", "")
	t.Setenv("POLLER_FETCH_DUE_LIMIT", "")
	t.Setenv("POLLER_MANUAL_API_TOKEN", "")
	t.Setenv("POLLER_MANUAL_REQUEST_TIMEOUT_SECONDS", "")

	cfg, err := Load()
	if err != nil {
		t.Fatalf("load config: %v", err)
	}

	if cfg.AppEnv != "local" {
		t.Fatalf("expected default app env local, got %q", cfg.AppEnv)
	}

	if cfg.HTTPAddr != ":8090" {
		t.Fatalf("expected default HTTP addr :8090, got %q", cfg.HTTPAddr)
	}

	if cfg.Concurrency != 10 {
		t.Fatalf("expected default concurrency 10, got %d", cfg.Concurrency)
	}

	if cfg.Workers != 10 {
		t.Fatalf("expected default workers 10, got %d", cfg.Workers)
	}

	if cfg.CheckTimeout != 10*time.Second {
		t.Fatalf("expected default check timeout 10s, got %s", cfg.CheckTimeout)
	}

	if cfg.QueueBuffer != 100 {
		t.Fatalf("expected default queue buffer 100, got %d", cfg.QueueBuffer)
	}

	if cfg.LaravelInternalAPIURL != "" {
		t.Fatalf("expected empty Laravel API URL, got %q", cfg.LaravelInternalAPIURL)
	}

	if cfg.LaravelInternalAPIToken != "" {
		t.Fatalf("expected empty Laravel API token, got %q", cfg.LaravelInternalAPIToken)
	}

	if cfg.LaravelInternalAPITimeout != 10*time.Second {
		t.Fatalf("expected Laravel API timeout 10s, got %s", cfg.LaravelInternalAPITimeout)
	}

	if cfg.ResultRetryAttempts != 3 {
		t.Fatalf("expected result retry attempts 3, got %d", cfg.ResultRetryAttempts)
	}

	if cfg.ResultRetryDelay != time.Second {
		t.Fatalf("expected result retry delay 1s, got %s", cfg.ResultRetryDelay)
	}

	if cfg.SchedulerInterval != 30*time.Second {
		t.Fatalf("expected scheduler interval 30s, got %s", cfg.SchedulerInterval)
	}

	if cfg.FetchDueLimit != 100 {
		t.Fatalf("expected fetch due limit 100, got %d", cfg.FetchDueLimit)
	}

	if cfg.ManualAPIToken != "" {
		t.Fatalf("expected empty manual API token, got %q", cfg.ManualAPIToken)
	}

	if cfg.ManualRequestTimeout != 5*time.Second {
		t.Fatalf("expected manual request timeout 5s, got %s", cfg.ManualRequestTimeout)
	}

	if cfg.ShutdownTimeout != 10*time.Second {
		t.Fatalf("expected shutdown timeout 10s, got %s", cfg.ShutdownTimeout)
	}
}

func TestLoadReadsEnvironment(t *testing.T) {
	t.Setenv("APP_ENV", "testing")
	t.Setenv("POLLER_MODE", "worker")
	t.Setenv("POLLER_HTTP_ADDR", ":9090")
	t.Setenv("POLLER_CONCURRENCY", "25")
	t.Setenv("POLLER_WORKERS", "15")
	t.Setenv("POLLER_CHECK_TIMEOUT_SECONDS", "7")
	t.Setenv("POLLER_QUEUE_BUFFER", "250")
	t.Setenv("LARAVEL_INTERNAL_API_URL", "http://web")
	t.Setenv("LARAVEL_INTERNAL_API_TOKEN", "secret")
	t.Setenv("LARAVEL_INTERNAL_API_TIMEOUT_SECONDS", "4")
	t.Setenv("POLLER_RESULT_RETRY_ATTEMPTS", "5")
	t.Setenv("POLLER_RESULT_RETRY_DELAY_SECONDS", "2")
	t.Setenv("POLLER_SCHEDULER_INTERVAL_SECONDS", "15")
	t.Setenv("POLLER_FETCH_DUE_LIMIT", "50")
	t.Setenv("POLLER_MANUAL_API_TOKEN", "manual-secret")
	t.Setenv("POLLER_MANUAL_REQUEST_TIMEOUT_SECONDS", "6")
	t.Setenv("POLLER_SHUTDOWN_TIMEOUT", "3s")

	cfg, err := Load()
	if err != nil {
		t.Fatalf("load config: %v", err)
	}

	if cfg.AppEnv != "testing" {
		t.Fatalf("expected app env testing, got %q", cfg.AppEnv)
	}

	if cfg.Mode != "worker" {
		t.Fatalf("expected mode worker, got %q", cfg.Mode)
	}

	if cfg.HTTPAddr != ":9090" {
		t.Fatalf("expected HTTP addr :9090, got %q", cfg.HTTPAddr)
	}

	if cfg.Concurrency != 25 {
		t.Fatalf("expected concurrency 25, got %d", cfg.Concurrency)
	}

	if cfg.Workers != 15 {
		t.Fatalf("expected workers 15, got %d", cfg.Workers)
	}

	if cfg.CheckTimeout != 7*time.Second {
		t.Fatalf("expected check timeout 7s, got %s", cfg.CheckTimeout)
	}

	if cfg.QueueBuffer != 250 {
		t.Fatalf("expected queue buffer 250, got %d", cfg.QueueBuffer)
	}

	if cfg.LaravelInternalAPIURL != "http://web" {
		t.Fatalf("expected Laravel API URL http://web, got %q", cfg.LaravelInternalAPIURL)
	}

	if cfg.LaravelInternalAPIToken != "secret" {
		t.Fatalf("expected Laravel API token secret, got %q", cfg.LaravelInternalAPIToken)
	}

	if cfg.LaravelInternalAPITimeout != 4*time.Second {
		t.Fatalf("expected Laravel API timeout 4s, got %s", cfg.LaravelInternalAPITimeout)
	}

	if cfg.ResultRetryAttempts != 5 {
		t.Fatalf("expected result retry attempts 5, got %d", cfg.ResultRetryAttempts)
	}

	if cfg.ResultRetryDelay != 2*time.Second {
		t.Fatalf("expected result retry delay 2s, got %s", cfg.ResultRetryDelay)
	}

	if cfg.SchedulerInterval != 15*time.Second {
		t.Fatalf("expected scheduler interval 15s, got %s", cfg.SchedulerInterval)
	}

	if cfg.FetchDueLimit != 50 {
		t.Fatalf("expected fetch due limit 50, got %d", cfg.FetchDueLimit)
	}

	if cfg.ManualAPIToken != "manual-secret" {
		t.Fatalf("expected manual API token manual-secret, got %q", cfg.ManualAPIToken)
	}

	if cfg.ManualRequestTimeout != 6*time.Second {
		t.Fatalf("expected manual request timeout 6s, got %s", cfg.ManualRequestTimeout)
	}

	if cfg.ShutdownTimeout != 3*time.Second {
		t.Fatalf("expected shutdown timeout 3s, got %s", cfg.ShutdownTimeout)
	}
}

func TestLoadRejectsInvalidConcurrency(t *testing.T) {
	t.Setenv("POLLER_CONCURRENCY", "0")

	if _, err := Load(); err == nil {
		t.Fatal("expected invalid concurrency error")
	}
}

func TestLoadRejectsInvalidWorkers(t *testing.T) {
	t.Setenv("POLLER_WORKERS", "0")

	if _, err := Load(); err == nil {
		t.Fatal("expected invalid workers error")
	}
}

func TestLoadRejectsInvalidCheckTimeout(t *testing.T) {
	t.Setenv("POLLER_CHECK_TIMEOUT_SECONDS", "0")

	if _, err := Load(); err == nil {
		t.Fatal("expected invalid check timeout error")
	}
}

func TestLoadRejectsInvalidQueueBuffer(t *testing.T) {
	t.Setenv("POLLER_QUEUE_BUFFER", "-1")

	if _, err := Load(); err == nil {
		t.Fatal("expected invalid queue buffer error")
	}
}

func TestLoadRejectsInvalidLaravelAPITimeout(t *testing.T) {
	t.Setenv("LARAVEL_INTERNAL_API_TIMEOUT_SECONDS", "0")

	if _, err := Load(); err == nil {
		t.Fatal("expected invalid Laravel API timeout error")
	}
}

func TestLoadRejectsInvalidResultRetryAttempts(t *testing.T) {
	t.Setenv("POLLER_RESULT_RETRY_ATTEMPTS", "0")

	if _, err := Load(); err == nil {
		t.Fatal("expected invalid result retry attempts error")
	}
}

func TestLoadRejectsInvalidResultRetryDelay(t *testing.T) {
	t.Setenv("POLLER_RESULT_RETRY_DELAY_SECONDS", "0")

	if _, err := Load(); err == nil {
		t.Fatal("expected invalid result retry delay error")
	}
}

func TestLoadRejectsInvalidSchedulerInterval(t *testing.T) {
	t.Setenv("POLLER_SCHEDULER_INTERVAL_SECONDS", "0")

	if _, err := Load(); err == nil {
		t.Fatal("expected invalid scheduler interval error")
	}
}

func TestLoadRejectsInvalidFetchDueLimit(t *testing.T) {
	t.Setenv("POLLER_FETCH_DUE_LIMIT", "0")

	if _, err := Load(); err == nil {
		t.Fatal("expected invalid fetch due limit error")
	}
}

func TestLoadRejectsInvalidManualRequestTimeout(t *testing.T) {
	t.Setenv("POLLER_MANUAL_REQUEST_TIMEOUT_SECONDS", "0")

	if _, err := Load(); err == nil {
		t.Fatal("expected invalid manual request timeout error")
	}
}
