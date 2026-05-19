package runner

import (
	"context"
	"fmt"
	"sync"
	"time"

	"montry/apps/poller/internal/checks"
	"montry/apps/poller/internal/jobs"
)

type WorkerPoolConfig struct {
	Workers      int
	CheckTimeout time.Duration
}

type WorkerPool struct {
	registry     checks.Registry
	publisher    ResultPublisher
	workers      int
	checkTimeout time.Duration
}

func NewWorkerPool(registry checks.Registry, publisher ResultPublisher, cfg WorkerPoolConfig) (*WorkerPool, error) {
	if registry == nil {
		return nil, fmt.Errorf("registry is required")
	}

	if publisher == nil {
		return nil, fmt.Errorf("result publisher is required")
	}

	if cfg.Workers < 1 {
		return nil, fmt.Errorf("workers must be greater than 0")
	}

	if cfg.CheckTimeout <= 0 {
		return nil, fmt.Errorf("check timeout must be greater than 0")
	}

	return &WorkerPool{
		registry:     registry,
		publisher:    publisher,
		workers:      cfg.Workers,
		checkTimeout: cfg.CheckTimeout,
	}, nil
}

func (p *WorkerPool) Run(ctx context.Context, jobCh <-chan jobs.CheckJob) error {
	var wg sync.WaitGroup

	for workerID := 1; workerID <= p.workers; workerID++ {
		worker := NewWorker(workerID, p.registry, p.publisher, p.checkTimeout)
		wg.Add(1)

		go func() {
			defer wg.Done()
			worker.Run(ctx, jobCh)
		}()
	}

	wg.Wait()

	return nil
}
