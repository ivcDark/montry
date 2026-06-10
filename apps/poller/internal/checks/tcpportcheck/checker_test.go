package tcpportcheck

import (
	"context"
	"net"
	"strconv"
	"testing"

	"montry/apps/poller/internal/checks"
	"montry/apps/poller/internal/jobs"
)

func TestCheckerReturnsLaravelTCPPortContract(t *testing.T) {
	listener, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		t.Fatalf("listen: %v", err)
	}
	defer listener.Close()

	host, portString, err := net.SplitHostPort(listener.Addr().String())
	if err != nil {
		t.Fatalf("split address: %v", err)
	}
	port, _ := strconv.Atoi(portString)

	result, err := New().Check(context.Background(), jobs.CheckJob{
		EventID:   "event-1",
		MonitorID: "monitor-1",
		Type:      Type,
		Settings: map[string]any{
			"host": host,
			"port": float64(port),
		},
		Expected: map[string]any{
			"open": true,
		},
	})
	if err != nil {
		t.Fatalf("check: %v", err)
	}
	if result.Status != checks.ResultStatusSuccess {
		t.Fatalf("expected success, got %q", result.Status)
	}
	if result.Raw["open"] != true || result.Raw["host"] != host || result.Raw["port"] != port {
		t.Fatalf("unexpected raw result: %#v", result.Raw)
	}
}

func TestCheckerCanExpectClosedPort(t *testing.T) {
	listener, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		t.Fatalf("listen: %v", err)
	}
	address := listener.Addr().String()
	_ = listener.Close()

	host, portString, err := net.SplitHostPort(address)
	if err != nil {
		t.Fatalf("split address: %v", err)
	}
	port, _ := strconv.Atoi(portString)

	result, err := New().Check(context.Background(), jobs.CheckJob{
		Type: Type,
		Settings: map[string]any{
			"host": host,
			"port": float64(port),
		},
		Expected: map[string]any{
			"open": false,
		},
	})
	if err != nil {
		t.Fatalf("check: %v", err)
	}
	if result.Status != checks.ResultStatusSuccess {
		t.Fatalf("expected success for closed port expectation, got %q with error %#v", result.Status, result.Error)
	}
	if result.Raw["open"] != false {
		t.Fatalf("expected open=false, got %#v", result.Raw["open"])
	}
}
