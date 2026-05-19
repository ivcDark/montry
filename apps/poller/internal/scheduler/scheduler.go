package scheduler

import (
	"context"
	"fmt"
	"time"

	"montry/apps/poller/internal/jobs"
	"montry/apps/poller/internal/laravel"
)

type Logger interface {
	Error(message string, fields ...any)
	Warn(message string, fields ...any)
}

type Config struct {
	Interval   time.Duration
	FetchLimit int
	Logger     Logger
}

type Scheduler struct {
	fetcher DueFetcher
	jobs    chan<- jobs.CheckJob
	cfg     Config
}

func New(client laravel.LaravelClient, jobCh chan<- jobs.CheckJob, cfg Config) *Scheduler {
	if cfg.Interval <= 0 {
		cfg.Interval = time.Minute
	}
	if cfg.FetchLimit <= 0 {
		cfg.FetchLimit = 100
	}

	return &Scheduler{
		fetcher: NewDueFetcher(client, cfg.FetchLimit),
		jobs:    jobCh,
		cfg:     cfg,
	}
}

func (s *Scheduler) Run(ctx context.Context) error {
	if err := s.Tick(ctx); err != nil {
		return err
	}

	ticker := time.NewTicker(s.cfg.Interval)
	defer ticker.Stop()

	for {
		select {
		case <-ctx.Done():
			return nil
		case <-ticker.C:
			if err := s.Tick(ctx); err != nil {
				return err
			}
		}
	}
}

func (s *Scheduler) Tick(ctx context.Context) error {
	if err := ctx.Err(); err != nil {
		return err
	}

	checkJobs, err := s.fetcher.Fetch(ctx)
	if err != nil {
		s.logError("failed to fetch due checks", "error", err)
		return nil
	}

	for _, job := range checkJobs {
		if err := s.enqueue(ctx, job); err != nil {
			return err
		}
	}

	return nil
}

func (s *Scheduler) enqueue(ctx context.Context, job jobs.CheckJob) error {
	select {
	case <-ctx.Done():
		return ctx.Err()
	case s.jobs <- job:
		return nil
	default:
		s.logWarn(
			"jobs queue is full, dropping due check",
			"event_id", job.EventID,
			"monitor_id", job.MonitorID,
			"check_type", job.Type,
		)
		return nil
	}
}

func (s *Scheduler) logError(message string, fields ...any) {
	if s.cfg.Logger == nil {
		return
	}

	s.cfg.Logger.Error(message, fields...)
}

func (s *Scheduler) logWarn(message string, fields ...any) {
	if s.cfg.Logger == nil {
		return
	}

	s.cfg.Logger.Warn(message, fields...)
}

func (s *Scheduler) String() string {
	return fmt.Sprintf("scheduler(interval=%s, fetch_limit=%d)", s.cfg.Interval, s.cfg.FetchLimit)
}
