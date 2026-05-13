package config

import (
	"fmt"
	"os"
	"strconv"
	"time"
)

type Config struct {
	AppEnv                    string
	Mode                      string
	HTTPAddr                  string
	Concurrency               int
	Workers                   int
	CheckTimeout              time.Duration
	QueueBuffer               int
	LaravelInternalAPIURL     string
	LaravelInternalAPIToken   string
	LaravelInternalAPITimeout time.Duration
	ResultRetryAttempts       int
	ResultRetryDelay          time.Duration
	SchedulerInterval         time.Duration
	FetchDueLimit             int
	ManualAPIToken            string
	ManualRequestTimeout      time.Duration
	ShutdownTimeout           time.Duration
}

func Load() (Config, error) {
	concurrency, err := intFromEnv("POLLER_CONCURRENCY", 10)
	if err != nil {
		return Config{}, err
	}

	if concurrency < 1 {
		return Config{}, fmt.Errorf("POLLER_CONCURRENCY must be greater than 0")
	}

	workers, err := intFromEnv("POLLER_WORKERS", concurrency)
	if err != nil {
		return Config{}, err
	}

	if workers < 1 {
		return Config{}, fmt.Errorf("POLLER_WORKERS must be greater than 0")
	}

	checkTimeoutSeconds, err := intFromEnv("POLLER_CHECK_TIMEOUT_SECONDS", 10)
	if err != nil {
		return Config{}, err
	}

	if checkTimeoutSeconds < 1 {
		return Config{}, fmt.Errorf("POLLER_CHECK_TIMEOUT_SECONDS must be greater than 0")
	}

	queueBuffer, err := intFromEnv("POLLER_QUEUE_BUFFER", 100)
	if err != nil {
		return Config{}, err
	}

	if queueBuffer < 0 {
		return Config{}, fmt.Errorf("POLLER_QUEUE_BUFFER must be greater than or equal to 0")
	}

	laravelInternalAPITimeoutSeconds, err := intFromEnv("LARAVEL_INTERNAL_API_TIMEOUT_SECONDS", 10)
	if err != nil {
		return Config{}, err
	}

	if laravelInternalAPITimeoutSeconds < 1 {
		return Config{}, fmt.Errorf("LARAVEL_INTERNAL_API_TIMEOUT_SECONDS must be greater than 0")
	}

	resultRetryAttempts, err := intFromEnv("POLLER_RESULT_RETRY_ATTEMPTS", 3)
	if err != nil {
		return Config{}, err
	}

	if resultRetryAttempts < 1 {
		return Config{}, fmt.Errorf("POLLER_RESULT_RETRY_ATTEMPTS must be greater than 0")
	}

	resultRetryDelaySeconds, err := intFromEnv("POLLER_RESULT_RETRY_DELAY_SECONDS", 1)
	if err != nil {
		return Config{}, err
	}

	if resultRetryDelaySeconds < 1 {
		return Config{}, fmt.Errorf("POLLER_RESULT_RETRY_DELAY_SECONDS must be greater than 0")
	}

	schedulerIntervalSeconds, err := intFromEnv("POLLER_SCHEDULER_INTERVAL_SECONDS", 30)
	if err != nil {
		return Config{}, err
	}

	if schedulerIntervalSeconds < 1 {
		return Config{}, fmt.Errorf("POLLER_SCHEDULER_INTERVAL_SECONDS must be greater than 0")
	}

	fetchDueLimit, err := intFromEnv("POLLER_FETCH_DUE_LIMIT", 100)
	if err != nil {
		return Config{}, err
	}

	if fetchDueLimit < 1 {
		return Config{}, fmt.Errorf("POLLER_FETCH_DUE_LIMIT must be greater than 0")
	}

	manualRequestTimeoutSeconds, err := intFromEnv("POLLER_MANUAL_REQUEST_TIMEOUT_SECONDS", 5)
	if err != nil {
		return Config{}, err
	}

	if manualRequestTimeoutSeconds < 1 {
		return Config{}, fmt.Errorf("POLLER_MANUAL_REQUEST_TIMEOUT_SECONDS must be greater than 0")
	}

	shutdownTimeout, err := durationFromEnv("POLLER_SHUTDOWN_TIMEOUT", 10*time.Second)
	if err != nil {
		return Config{}, err
	}

	if shutdownTimeout <= 0 {
		return Config{}, fmt.Errorf("POLLER_SHUTDOWN_TIMEOUT must be greater than 0")
	}

	return Config{
		AppEnv:                    stringFromEnv("APP_ENV", "local"),
		Mode:                      stringFromEnv("POLLER_MODE", "service"),
		HTTPAddr:                  stringFromEnv("POLLER_HTTP_ADDR", ":8090"),
		Concurrency:               concurrency,
		Workers:                   workers,
		CheckTimeout:              time.Duration(checkTimeoutSeconds) * time.Second,
		QueueBuffer:               queueBuffer,
		LaravelInternalAPIURL:     stringFromEnv("LARAVEL_INTERNAL_API_URL", ""),
		LaravelInternalAPIToken:   stringFromEnv("LARAVEL_INTERNAL_API_TOKEN", ""),
		LaravelInternalAPITimeout: time.Duration(laravelInternalAPITimeoutSeconds) * time.Second,
		ResultRetryAttempts:       resultRetryAttempts,
		ResultRetryDelay:          time.Duration(resultRetryDelaySeconds) * time.Second,
		SchedulerInterval:         time.Duration(schedulerIntervalSeconds) * time.Second,
		FetchDueLimit:             fetchDueLimit,
		ManualAPIToken:            stringFromEnv("POLLER_MANUAL_API_TOKEN", ""),
		ManualRequestTimeout:      time.Duration(manualRequestTimeoutSeconds) * time.Second,
		ShutdownTimeout:           shutdownTimeout,
	}, nil
}

func stringFromEnv(key string, fallback string) string {
	value := os.Getenv(key)
	if value == "" {
		return fallback
	}

	return value
}

func intFromEnv(key string, fallback int) (int, error) {
	value := os.Getenv(key)
	if value == "" {
		return fallback, nil
	}

	parsed, err := strconv.Atoi(value)
	if err != nil {
		return 0, fmt.Errorf("%s must be an integer: %w", key, err)
	}

	return parsed, nil
}

func durationFromEnv(key string, fallback time.Duration) (time.Duration, error) {
	value := os.Getenv(key)
	if value == "" {
		return fallback, nil
	}

	parsed, err := time.ParseDuration(value)
	if err != nil {
		return 0, fmt.Errorf("%s must be a duration: %w", key, err)
	}

	return parsed, nil
}
