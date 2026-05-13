package checks

import (
	"context"

	"montri/apps/poller/internal/jobs"
)

type Checker interface {
	Type() string
	Check(ctx context.Context, job jobs.CheckJob) (CheckResult, error)
}
