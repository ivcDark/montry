package app

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"
	"time"

	"montri/apps/poller/internal/config"
	"montri/apps/poller/internal/logger"
)

func TestAppLifecycleFetchesDueChecksAndStops(t *testing.T) {
	dueCalled := make(chan struct{}, 1)
	laravelServer := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch r.URL.Path {
		case "/internal/monitors/due":
			dueCalled <- struct{}{}
			_ = json.NewEncoder(w).Encode(map[string]any{"data": []any{}})
		default:
			http.NotFound(w, r)
		}
	}))
	defer laravelServer.Close()

	application, err := New(config.Config{
		AppEnv:                    "testing",
		Mode:                      "service",
		HTTPAddr:                  "127.0.0.1:0",
		Workers:                   1,
		CheckTimeout:              time.Second,
		QueueBuffer:               1,
		LaravelInternalAPIURL:     laravelServer.URL,
		LaravelInternalAPITimeout: time.Second,
		ResultRetryAttempts:       1,
		ResultRetryDelay:          time.Millisecond,
		SchedulerInterval:         time.Hour,
		FetchDueLimit:             1,
		ManualRequestTimeout:      time.Second,
		ShutdownTimeout:           time.Second,
	}, logger.New("test"))
	if err != nil {
		t.Fatalf("new app: %v", err)
	}

	ctx, cancel := context.WithCancel(context.Background())
	done := make(chan error, 1)
	go func() {
		done <- application.Run(ctx)
	}()

	select {
	case <-dueCalled:
	case <-time.After(time.Second):
		cancel()
		t.Fatal("expected scheduler to fetch due checks")
	}

	cancel()

	select {
	case err := <-done:
		if err != nil {
			t.Fatalf("run app: %v", err)
		}
	case <-time.After(time.Second):
		t.Fatal("app did not stop")
	}
}
