package observability

import (
	"time"

	"github.com/getsentry/sentry-go"
)

type SentryConfig struct {
	DSN         string
	Enabled     bool
	Environment string
	Release     string
	ServiceName string
}

type SentryReporter struct {
	enabled bool
}

func NewSentryReporter(cfg SentryConfig) (*SentryReporter, error) {
	if !cfg.Enabled || cfg.DSN == "" {
		return &SentryReporter{}, nil
	}

	if err := sentry.Init(sentry.ClientOptions{
		Dsn:              cfg.DSN,
		Environment:      cfg.Environment,
		Release:          cfg.Release,
		ServerName:       cfg.ServiceName,
		AttachStacktrace: true,
		BeforeSend: func(event *sentry.Event, hint *sentry.EventHint) *sentry.Event {
			delete(event.Extra, "target")
			delete(event.Extra, "url")
			delete(event.Extra, "domain")
			delete(event.Extra, "token")
			delete(event.Extra, "authorization")
			delete(event.Extra, "password")

			return event
		},
	}); err != nil {
		return nil, err
	}

	return &SentryReporter{enabled: true}, nil
}

func (r *SentryReporter) CaptureException(err error, tags map[string]string, extra map[string]any) {
	if r == nil || !r.enabled || err == nil {
		return
	}

	sentry.WithScope(func(scope *sentry.Scope) {
		scope.SetTag("service", "poller")

		for key, value := range tags {
			scope.SetTag(key, value)
		}

		for key, value := range extra {
			scope.SetExtra(key, value)
		}

		sentry.CaptureException(err)
	})
}

func (r *SentryReporter) CaptureMessage(message string, tags map[string]string, extra map[string]any) {
	if r == nil || !r.enabled || message == "" {
		return
	}

	sentry.WithScope(func(scope *sentry.Scope) {
		scope.SetTag("service", "poller")

		for key, value := range tags {
			scope.SetTag(key, value)
		}

		for key, value := range extra {
			scope.SetExtra(key, value)
		}

		sentry.CaptureMessage(message)
	})
}

func (r *SentryReporter) Flush(timeout time.Duration) {
	if r == nil || !r.enabled {
		return
	}

	sentry.Flush(timeout)
}
