package checks

import (
	"context"

	"montry/apps/poller/internal/jobs"
)

type Checker interface {
	Type() string
	Check(ctx context.Context, job jobs.CheckJob) (CheckResult, error)
}
