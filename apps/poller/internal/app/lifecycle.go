package app

import (
	"context"
	"errors"
	"net/http"
)

func (a *App) Run(ctx context.Context) error {
	a.log.Info(
		"starting poller",
		"env", a.cfg.AppEnv,
		"mode", a.cfg.Mode,
		"http_addr", a.cfg.HTTPAddr,
		"workers", a.cfg.Workers,
		"check_timeout", a.cfg.CheckTimeout.String(),
		"queue_buffer", a.cfg.QueueBuffer,
		"scheduler_interval", a.cfg.SchedulerInterval.String(),
		"fetch_due_limit", a.cfg.FetchDueLimit,
	)

	errCh := make(chan error, 3)
	go func() {
		errCh <- a.server.Start()
	}()
	go func() {
		if a.pool == nil {
			return
		}

		errCh <- a.pool.Run(ctx, a.jobs)
	}()
	go func() {
		if a.sched == nil {
			return
		}

		errCh <- a.sched.Run(ctx)
	}()

	select {
	case <-ctx.Done():
		shutdownCtx, cancel := context.WithTimeout(context.Background(), a.cfg.ShutdownTimeout)
		defer cancel()

		a.log.Info("shutting down poller")
		if err := a.server.Shutdown(shutdownCtx); err != nil {
			a.captureError(err, "poller_shutdown_failed")
			return err
		}

		a.sentry.Flush(a.cfg.SentryFlushTimeout)
		a.log.Info("poller stopped")
		return nil
	case err := <-errCh:
		if errors.Is(err, http.ErrServerClosed) {
			return nil
		}

		a.captureError(err, "poller_stopped_with_error")
		a.sentry.Flush(a.cfg.SentryFlushTimeout)
		return err
	}
}

func (a *App) captureError(err error, event string) {
	if a.sentry == nil {
		return
	}

	a.sentry.CaptureException(err, map[string]string{
		"event":       event,
		"environment": a.cfg.AppEnv,
	}, map[string]any{
		"mode": a.cfg.Mode,
	})
}
