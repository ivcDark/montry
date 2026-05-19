package app

import (
	"fmt"

	"montry/apps/poller/internal/checks"
	"montry/apps/poller/internal/checks/domaincheck"
	"montry/apps/poller/internal/checks/httpcheck"
	"montry/apps/poller/internal/checks/sslcheck"
	"montry/apps/poller/internal/config"
	"montry/apps/poller/internal/jobs"
	"montry/apps/poller/internal/laravel"
	"montry/apps/poller/internal/logger"
	"montry/apps/poller/internal/runner"
	"montry/apps/poller/internal/scheduler"
	transporthttp "montry/apps/poller/internal/transport/http"
)

type App struct {
	cfg    config.Config
	log    *logger.Logger
	checks checks.Registry
	jobs   chan jobs.CheckJob
	pool   *runner.WorkerPool
	sched  *scheduler.Scheduler
	server *transporthttp.Server
}

func NewFromEnv() (*App, error) {
	cfg, err := config.Load()
	if err != nil {
		return nil, fmt.Errorf("load config: %w", err)
	}

	log := logger.New("poller")

	return New(cfg, log)
}

func New(cfg config.Config, log *logger.Logger) (*App, error) {
	checkRegistry := checks.NewRegistry()
	if err := checkRegistry.Register(httpcheck.New()); err != nil {
		return nil, fmt.Errorf("register %s checker: %w", httpcheck.Type, err)
	}
	if err := checkRegistry.Register(sslcheck.New()); err != nil {
		return nil, fmt.Errorf("register %s checker: %w", sslcheck.Type, err)
	}
	if err := checkRegistry.Register(domaincheck.New()); err != nil {
		return nil, fmt.Errorf("register %s checker: %w", domaincheck.Type, err)
	}

	laravelClient := laravel.NewHTTPClient(laravel.HTTPClientConfig{
		BaseURL: cfg.LaravelInternalAPIURL,
		Token:   cfg.LaravelInternalAPIToken,
		Timeout: cfg.LaravelInternalAPITimeout,
	})

	resultPublisher, err := runner.NewLaravelResultPublisher(laravelClient, runner.ResultPublisherConfig{
		RetryAttempts: cfg.ResultRetryAttempts,
		RetryDelay:    cfg.ResultRetryDelay,
		Logger:        log,
	})
	if err != nil {
		return nil, fmt.Errorf("create result publisher: %w", err)
	}

	workerPool, err := runner.NewWorkerPool(checkRegistry, resultPublisher, runner.WorkerPoolConfig{
		Workers:      cfg.Workers,
		CheckTimeout: cfg.CheckTimeout,
	})
	if err != nil {
		return nil, fmt.Errorf("create worker pool: %w", err)
	}

	jobCh := make(chan jobs.CheckJob, cfg.QueueBuffer)
	sched := scheduler.New(laravelClient, jobCh, scheduler.Config{
		Interval:   cfg.SchedulerInterval,
		FetchLimit: cfg.FetchDueLimit,
		Logger:     log,
	})

	return &App{
		cfg:    cfg,
		log:    log,
		checks: checkRegistry,
		jobs:   jobCh,
		pool:   workerPool,
		sched:  sched,
		server: transporthttp.NewServer(cfg.HTTPAddr, log, transporthttp.Options{
			ManualJobs:           jobCh,
			CheckRegistry:        checkRegistry,
			ManualAuthToken:      cfg.ManualAPIToken,
			ManualRequestTimeout: cfg.ManualRequestTimeout,
		}),
	}, nil
}
