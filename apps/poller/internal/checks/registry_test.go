package checks

import (
	"context"
	"testing"

	"montri/apps/poller/internal/jobs"
)

type fakeChecker struct {
	checkType string
}

func (f fakeChecker) Type() string {
	return f.checkType
}

func (f fakeChecker) Check(context.Context, jobs.CheckJob) (CheckResult, error) {
	return CheckResult{Type: f.checkType}, nil
}

func TestRegistryReturnsRegisteredChecker(t *testing.T) {
	registry := NewRegistry()
	checker := fakeChecker{checkType: "http"}

	if err := registry.Register(checker); err != nil {
		t.Fatalf("register checker: %v", err)
	}

	got, err := registry.Get("http")
	if err != nil {
		t.Fatalf("get checker: %v", err)
	}

	if got.Type() != "http" {
		t.Fatalf("expected http checker, got %q", got.Type())
	}
}

func TestRegistryAllReturnsRegisteredCheckers(t *testing.T) {
	registry := NewRegistry()

	if err := registry.Register(fakeChecker{checkType: "ssl"}); err != nil {
		t.Fatalf("register ssl checker: %v", err)
	}

	if err := registry.Register(fakeChecker{checkType: "http"}); err != nil {
		t.Fatalf("register http checker: %v", err)
	}

	checkers := registry.All()
	if len(checkers) != 2 {
		t.Fatalf("expected 2 checkers, got %d", len(checkers))
	}

	if checkers[0].Type() != "http" {
		t.Fatalf("expected first checker http, got %q", checkers[0].Type())
	}

	if checkers[1].Type() != "ssl" {
		t.Fatalf("expected second checker ssl, got %q", checkers[1].Type())
	}
}

func TestRegistryRejectsDuplicateType(t *testing.T) {
	registry := NewRegistry()

	if err := registry.Register(fakeChecker{checkType: "http"}); err != nil {
		t.Fatalf("register first checker: %v", err)
	}

	if err := registry.Register(fakeChecker{checkType: "http"}); err == nil {
		t.Fatal("expected duplicate checker error")
	}
}

func TestRegistryReturnsErrorForUnknownType(t *testing.T) {
	registry := NewRegistry()

	if _, err := registry.Get("ssl"); err == nil {
		t.Fatal("expected unknown checker error")
	}
}
