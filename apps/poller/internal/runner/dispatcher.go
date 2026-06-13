package runner

import (
	"context"

	"montry/apps/poller/internal/jobs"
)

type Dispatcher struct {
	pool *WorkerPool
}

func NewDispatcher(pool *WorkerPool) *Dispatcher {
	return &Dispatcher{pool: pool}
}

func (d *Dispatcher) Run(ctx context.Context, jobCh <-chan jobs.CheckJob) error {
	return d.pool.Run(ctx, jobCh)
}
