# Observability Platform Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build mature observability, business monitoring, logging, tracing, alerting and admin dashboards for Montry from the start.

**Architecture:** Laravel and Go poller emit business events, metrics, logs and traces through explicit adapters. PostgreSQL remains the operational source of truth, ClickHouse stores analytics events, Prometheus stores time-series metrics, Loki stores structured logs, Tempo stores traces, Sentry stores exceptions, and Grafana provides dashboards and alerts.

**Tech Stack:** Laravel, Go, PostgreSQL, ClickHouse, Redis queues, Prometheus, Grafana, Loki, Tempo, OpenTelemetry Collector, Sentry, Docker Compose, Node Exporter, cAdvisor, Blackbox Exporter.

---

## Scope

This is a master implementation plan. It is intentionally split into independent tasks because the full observability platform touches application code, Go poller, infrastructure, dashboards and operational processes.

Each task must be implemented with focused tests and a separate commit. Do not implement user-facing monitoring check types inside this plan; this plan is about monitoring Montry itself.

## Files and Areas

- Create `docs/architecture/observability.md` as the architecture source of truth.
- Modify `docker-compose.yml` to add observability services.
- Add Docker configs under `docker/observability/`.
- Add Laravel module `apps/web/app/Modules/Observability/`.
- Add Laravel migrations for business events, audit logs and dead-letter records.
- Add Laravel config in `apps/web/config/observability.php`.
- Modify Laravel logging config in `apps/web/config/logging.php`.
- Modify Laravel bootstrap/middleware to propagate correlation IDs.
- Add Go poller metrics, structured logging and tracing under `apps/poller/internal/observability/`.
- Add Grafana dashboards under `docker/observability/grafana/dashboards/`.
- Add Prometheus alert rules under `docker/observability/prometheus/rules/`.
- Add documentation for dashboards, alerts and runbooks under `docs/operations/`.

---

## Epic 1: Local Observability Stack

**Goal:** Run Grafana, Prometheus, Loki, Tempo, OpenTelemetry Collector, ClickHouse, exporters and Sentry-compatible configuration locally through Docker Compose.

**Files:**
- Modify: `docker-compose.yml`
- Create: `docker/observability/prometheus/prometheus.yml`
- Create: `docker/observability/prometheus/rules/montry.yml`
- Create: `docker/observability/loki/loki.yml`
- Create: `docker/observability/tempo/tempo.yml`
- Create: `docker/observability/otel-collector/config.yml`
- Create: `docker/observability/grafana/provisioning/datasources/datasources.yml`
- Create: `docker/observability/grafana/provisioning/dashboards/dashboards.yml`
- Modify: `Makefile`
- Create: `docs/operations/observability-local.md`

- [ ] Add Docker Compose services for `grafana`, `prometheus`, `loki`, `tempo`, `otel-collector`, `clickhouse`, `node-exporter`, `cadvisor` and `blackbox-exporter`.
- [ ] Configure Prometheus scrape targets for Laravel, Go poller, exporters and OpenTelemetry Collector.
- [ ] Configure Loki storage and retention suitable for local development.
- [ ] Configure Tempo local block storage.
- [ ] Configure OpenTelemetry Collector pipelines for traces, metrics and logs.
- [ ] Configure Grafana datasources for Prometheus, Loki, Tempo, ClickHouse and PostgreSQL.
- [ ] Add Makefile targets: `observability-up`, `observability-down`, `observability-logs`, `grafana-ui`, `prometheus-ui`.
- [ ] Verify `make observability-up` starts all services.
- [ ] Verify Grafana opens and datasources are healthy.
- [ ] Commit as `infra: add local observability stack`.

## Epic 2: Correlation IDs and Request Context

**Goal:** Every important Laravel and poller operation has `correlation_id`, `event_id` where appropriate, and trace context propagation.

**Files:**
- Create: `apps/web/app/Modules/Observability/Infrastructure/Http/Middleware/CorrelationIdMiddleware.php`
- Create: `apps/web/app/Modules/Observability/Application/Context/CorrelationContext.php`
- Create: `apps/web/app/Modules/Observability/Infrastructure/Providers/ObservabilityServiceProvider.php`
- Modify: `apps/web/bootstrap/app.php`
- Modify: `apps/web/bootstrap/providers.php`
- Create: `apps/web/tests/Feature/Observability/CorrelationIdMiddlewareTest.php`
- Modify: `apps/poller/internal/jobs/job.go`
- Create: `apps/poller/internal/observability/correlation.go`
- Create: `apps/poller/internal/observability/correlation_test.go`

- [ ] Add Laravel middleware that accepts `X-Correlation-ID` or creates a UUID.
- [ ] Add the correlation ID to request attributes, response headers, logs and outbound internal API calls.
- [ ] Add Go poller correlation helpers and include correlation ID in `CheckJob`.
- [ ] Preserve correlation ID from Laravel manual and scheduled payloads into poller result publishing.
- [ ] Add tests for generated, accepted and propagated correlation IDs.
- [ ] Verify Laravel tests with `make test`.
- [ ] Verify Go tests with `make poller-test`.
- [ ] Commit as `feat: add correlation id propagation`.

## Epic 3: Business Events Store

**Goal:** Capture product and business process events in a durable append-only store.

**Files:**
- Create: `apps/web/database/migrations/2026_05_25_000000_create_business_events_table.php`
- Create: `apps/web/app/Modules/Observability/Domain/BusinessEvent.php`
- Create: `apps/web/app/Modules/Observability/Application/DTO/RecordBusinessEventData.php`
- Create: `apps/web/app/Modules/Observability/Application/Services/BusinessEventRecorder.php`
- Create: `apps/web/app/Modules/Observability/Infrastructure/Persistence/Models/BusinessEvent.php`
- Create: `apps/web/app/Modules/Observability/Infrastructure/Persistence/EloquentBusinessEventRepository.php`
- Create: `apps/web/app/Modules/Observability/Domain/Contracts/BusinessEventRepositoryInterface.php`
- Create: `apps/web/tests/Unit/Observability/BusinessEventRecorderTest.php`

- [ ] Create `business_events` with indexed `event_type`, `occurred_at`, `organization_id`, `user_id`, `plan_code`, `subject_type`, `subject_id`, `status` and `correlation_id`.
- [ ] Implement `BusinessEventRecorder` with strict validation of event type and timestamp.
- [ ] Ensure payload is JSON and does not store secrets, tokens or verification codes.
- [ ] Add unit tests for recording a valid event.
- [ ] Add unit tests rejecting empty `event_type`.
- [ ] Add unit tests redacting sensitive payload keys.
- [ ] Verify with `make test`.
- [ ] Commit as `feat: add business event recorder`.

## Epic 4: Instrument Core Product Events

**Goal:** Record business events for registration, plans, resources, monitors, checks, incidents, notifications, payments and billing limits.

**Files:**
- Modify registration actions/controllers under `apps/web/app/Modules/Auth/`
- Modify billing services under `apps/web/app/Modules/Billing/`
- Modify monitored resource handlers under `apps/web/app/Modules/MonitoredResources/`
- Modify monitor handlers under `apps/web/app/Modules/Monitoring/`
- Modify incident listeners/services under `apps/web/app/Modules/Incidents/`
- Modify notification senders/loggers under `apps/web/app/Modules/Notifications/`
- Create focused tests under `apps/web/tests/Feature/Observability/`

- [ ] Record `registration.form_opened` when the registration page is opened.
- [ ] Record `registration.email_submitted` when a registration attempt starts.
- [ ] Record `registration.code_sent` when email verification code is sent.
- [ ] Record `registration.code_verified` when the code is accepted.
- [ ] Record `registration.completed` when the account and default organization are created.
- [ ] Record `plan.selected`, `plan.changed` and `billing.limit_hit`.
- [ ] Record `site.created`, `site.paused`, `site.resumed` and `site.deleted`.
- [ ] Record `monitor.created`, `monitor.enabled`, `monitor.disabled` and `monitor.deleted`.
- [ ] Record `check.scheduled`, `check.started`, `check.finished` and `check.failed`.
- [ ] Record `incident.opened` and `incident.resolved`.
- [ ] Record notification sent/failed events for email and Telegram.
- [ ] Record payment started/succeeded/failed and subscription activated/expired.
- [ ] Add feature tests for each critical product flow.
- [ ] Verify with `make test`.
- [ ] Commit as `feat: record core business events`.

## Epic 5: Metrics in Laravel

**Goal:** Expose product and technical metrics from Laravel for Prometheus.

**Files:**
- Create: `apps/web/app/Modules/Observability/Application/Services/MetricsRecorder.php`
- Create: `apps/web/app/Modules/Observability/Presentation/Http/Controllers/MetricsController.php`
- Create: `apps/web/app/Modules/Observability/Presentation/Routes/observability.php`
- Modify: `apps/web/routes/internal.php` or module route registration
- Create: `apps/web/config/observability.php`
- Create: `apps/web/tests/Feature/Observability/MetricsEndpointTest.php`

- [ ] Add `/internal/metrics` protected for internal network or token access.
- [ ] Expose counters for registrations, plan selections, sites, monitors, incidents, notifications, payments and limit hits.
- [ ] Expose gauges for active sites, enabled monitors, open incidents and queue depth.
- [ ] Expose histograms for HTTP request duration, queue job duration and internal API duration.
- [ ] Prevent high-cardinality labels such as user, organization, monitor, site, email and domain.
- [ ] Add tests that `/internal/metrics` returns Prometheus text format.
- [ ] Add tests that forbidden labels are not emitted.
- [ ] Verify Prometheus can scrape Laravel.
- [ ] Commit as `feat: expose laravel prometheus metrics`.

## Epic 6: Metrics in Go Poller

**Goal:** Expose poller metrics for scheduled/manual jobs, check durations, failures, queue pressure and result delivery.

**Files:**
- Create: `apps/poller/internal/observability/metrics.go`
- Create: `apps/poller/internal/observability/metrics_test.go`
- Modify: `apps/poller/internal/runner/worker.go`
- Modify: `apps/poller/internal/runner/result_publisher.go`
- Modify: `apps/poller/internal/scheduler/scheduler.go`
- Modify: `apps/poller/internal/transport/http/server.go`
- Modify: `apps/poller/internal/config/config.go`

- [ ] Add `/metrics` endpoint to the poller HTTP server.
- [ ] Count jobs by check type, source and status.
- [ ] Measure check duration by check type.
- [ ] Count result delivery attempts, successes and failures.
- [ ] Expose queue buffer usage and worker count.
- [ ] Add tests for metric registration and label boundaries.
- [ ] Verify with `make poller-test`.
- [ ] Verify Prometheus can scrape poller metrics.
- [ ] Commit as `feat: expose poller prometheus metrics`.

## Epic 7: Structured Logging to Loki

**Goal:** All Laravel, queue and poller logs are structured JSON and usable in Loki.

**Files:**
- Modify: `apps/web/config/logging.php`
- Create: `apps/web/app/Modules/Observability/Infrastructure/Logging/ContextProcessor.php`
- Modify: `apps/poller/internal/logger/logger.go`
- Create: `apps/poller/internal/logger/logger_test.go`
- Modify: `docker/observability/loki/loki.yml`
- Modify: `docker/observability/otel-collector/config.yml`

- [ ] Configure Laravel logs as JSON with `service`, `component`, `level`, `event`, `correlation_id`.
- [ ] Configure queue and scheduler logs with the same structure.
- [ ] Configure Go poller logger to emit JSON with stable fields.
- [ ] Ensure sensitive values are redacted.
- [ ] Configure log collection into Loki through OpenTelemetry Collector or Grafana Alloy.
- [ ] Add tests for Laravel log context processor.
- [ ] Add tests for Go logger fields.
- [ ] Verify logs appear in Grafana Explore.
- [ ] Commit as `feat: add structured logs for loki`.

## Epic 8: Distributed Tracing

**Goal:** Trace critical operations across Laravel, queues, Go poller and internal API.

**Files:**
- Create: `apps/web/app/Modules/Observability/Infrastructure/Tracing/OpenTelemetryService.php`
- Modify Laravel HTTP middleware and queue middleware
- Modify: `apps/poller/internal/observability/tracing.go`
- Modify: `apps/poller/internal/laravel/client.go`
- Modify: `docker/observability/otel-collector/config.yml`
- Modify: `docker/observability/tempo/tempo.yml`

- [ ] Add OpenTelemetry config for service name, environment and exporter endpoint.
- [ ] Trace registration, plan selection, manual checks, check result processing, notifications and payments.
- [ ] Propagate trace context from Laravel to Go poller.
- [ ] Propagate trace context from Go poller back to Laravel result API.
- [ ] Add traces around external integrations: mail, Telegram, payment provider.
- [ ] Verify traces appear in Tempo and can be opened from Loki logs through correlation fields.
- [ ] Commit as `feat: add distributed tracing`.

## Epic 9: ClickHouse Analytics Pipeline

**Goal:** Replicate business events from PostgreSQL/outbox into ClickHouse for fast dashboards.

**Files:**
- Create: `apps/web/database/migrations/2026_05_25_000010_create_analytics_event_exports_table.php`
- Create: `apps/web/app/Modules/Observability/Application/Jobs/ExportBusinessEventsToClickHouse.php`
- Create: `apps/web/app/Modules/Observability/Infrastructure/ClickHouse/ClickHouseClient.php`
- Create: `apps/web/app/Modules/Observability/Infrastructure/ClickHouse/ClickHouseBusinessEventExporter.php`
- Create: `docker/observability/clickhouse/init/001_create_analytics_events.sql`
- Create: `apps/web/tests/Unit/Observability/ClickHouseBusinessEventExporterTest.php`

- [ ] Create ClickHouse `analytics_events` table.
- [ ] Track export cursor or export batches without losing events.
- [ ] Export events idempotently.
- [ ] Retry temporary ClickHouse failures.
- [ ] Store permanent failures in dead-letter storage.
- [ ] Add tests for successful export.
- [ ] Add tests for retryable and non-retryable export failures.
- [ ] Verify Grafana can query ClickHouse analytics events.
- [ ] Commit as `feat: export business events to clickhouse`.

## Epic 10: Admin and Owner Dashboards in Grafana

**Goal:** Provide ready-to-use dashboards for owner, operations, monitoring product, billing, notifications, security and audit.

**Files:**
- Create: `docker/observability/grafana/dashboards/owner.json`
- Create: `docker/observability/grafana/dashboards/operations.json`
- Create: `docker/observability/grafana/dashboards/monitoring-product.json`
- Create: `docker/observability/grafana/dashboards/billing.json`
- Create: `docker/observability/grafana/dashboards/notifications.json`
- Create: `docker/observability/grafana/dashboards/security-audit.json`
- Create: `docs/operations/dashboards.md`

- [ ] Build Owner dashboard from ClickHouse/PostgreSQL and Prometheus.
- [ ] Build Operations dashboard from Prometheus and Loki.
- [ ] Build Monitoring Product dashboard from Prometheus and ClickHouse.
- [ ] Build Billing dashboard from ClickHouse/PostgreSQL.
- [ ] Build Notifications dashboard from ClickHouse/PostgreSQL and Loki.
- [ ] Build Security and Audit dashboard from business events, audit logs and Loki.
- [ ] Document each dashboard panel, datasource and intended decision.
- [ ] Verify dashboards provision automatically in Grafana.
- [ ] Commit as `ops: add grafana dashboards`.

## Epic 11: Alerts and Runbooks

**Goal:** Alert on technical failures and business process degradation with actionable runbooks.

**Files:**
- Create: `docker/observability/prometheus/rules/availability.yml`
- Create: `docker/observability/prometheus/rules/business.yml`
- Create: `docker/observability/prometheus/rules/billing.yml`
- Create: `docker/observability/prometheus/rules/notifications.yml`
- Create: `docker/observability/prometheus/rules/poller.yml`
- Create: `docs/operations/alerts.md`
- Create: `docs/operations/runbooks/laravel-down.md`
- Create: `docs/operations/runbooks/poller-no-results.md`
- Create: `docs/operations/runbooks/email-failures.md`
- Create: `docs/operations/runbooks/payment-failures.md`
- Create: `docs/operations/runbooks/queue-backlog.md`

- [ ] Add alert for Laravel unavailable.
- [ ] Add alert for poller unavailable.
- [ ] Add alert for PostgreSQL/Redis unavailable.
- [ ] Add alert for queue backlog and failed jobs.
- [ ] Add alert for no check results.
- [ ] Add alert for poller result delivery failures.
- [ ] Add alert for internal API 5xx.
- [ ] Add alert for email/Telegram failure rate.
- [ ] Add alert for payment failure rate.
- [ ] Add alert for registration funnel breakage.
- [ ] Add runbook for each alert with symptoms, checks, likely causes and remediation.
- [ ] Verify alerts load in Prometheus/Grafana.
- [ ] Commit as `ops: add observability alerts and runbooks`.

## Epic 12: Audit Log and Admin Security Events

**Goal:** Track security-sensitive and admin operations separately from product analytics.

**Files:**
- Create: `apps/web/database/migrations/2026_05_25_000020_create_audit_logs_table.php`
- Create: `apps/web/app/Modules/Observability/Application/Services/AuditLogger.php`
- Create: `apps/web/app/Modules/Observability/Infrastructure/Persistence/Models/AuditLog.php`
- Modify admin controllers under `apps/web/app/Modules/Admin/`
- Create: `apps/web/tests/Feature/Observability/AuditLoggerTest.php`

- [ ] Record admin login, failed login and logout.
- [ ] Record admin changes to users, organizations, plans, monitors and subscriptions.
- [ ] Record internal API auth failures.
- [ ] Record payment callback signature failures.
- [ ] Record rate limit hits for sensitive endpoints.
- [ ] Add feature tests for key audit events.
- [ ] Add dashboard panels for audit event volume and failures.
- [ ] Commit as `feat: add audit logging`.

## Epic 13: Dead Letter and Failure Control

**Goal:** Make observability and business side effects reliable through retry visibility and dead-letter handling.

**Files:**
- Create: `apps/web/database/migrations/2026_05_25_000030_create_dead_letters_table.php`
- Create: `apps/web/app/Modules/Observability/Application/Services/DeadLetterRecorder.php`
- Create: `apps/web/app/Modules/Observability/Presentation/Http/Controllers/DeadLetterIndexController.php`
- Create: `apps/web/app/Modules/Observability/Presentation/Routes/admin.php`
- Create: `apps/web/tests/Feature/Observability/DeadLetterTest.php`

- [ ] Record failed notification deliveries after retries are exhausted.
- [ ] Record failed ClickHouse exports after retries are exhausted.
- [ ] Record failed poller result processing when the payload is invalid or non-recoverable.
- [ ] Add admin list for dead-letter records.
- [ ] Add retry command for recoverable dead-letter records.
- [ ] Add tests for dead-letter creation and retry flow.
- [ ] Add metrics and alerts for dead-letter growth.
- [ ] Commit as `feat: add dead letter control`.

## Epic 14: Sentry Integration

**Goal:** Capture exceptions with release, environment, user/organization context and correlation IDs.

**Files:**
- Modify: `apps/web/config/services.php`
- Modify: `apps/web/bootstrap/app.php`
- Modify: `apps/poller/internal/app/app.go`
- Modify: `apps/poller/internal/config/config.go`
- Create: `docs/operations/sentry.md`

- [ ] Configure Laravel Sentry DSN and environment.
- [ ] Attach correlation ID and safe user/organization context.
- [ ] Configure Go poller Sentry DSN and environment if using Sentry for Go.
- [ ] Ensure secrets and payloads are not sent to Sentry.
- [ ] Verify a controlled test exception appears in Sentry.
- [ ] Document release and environment naming.
- [ ] Commit as `ops: add sentry exception tracking`.

## Epic 15: Backups and Self-Monitoring

**Goal:** Monitor Montry's own availability and data safety.

**Files:**
- Create: `scripts/backup-postgres.sh`
- Create: `scripts/verify-postgres-backup.sh`
- Modify: `docker/observability/prometheus/rules/availability.yml`
- Create: `docs/operations/backups.md`
- Create: `docs/operations/self-monitoring.md`

- [ ] Add PostgreSQL backup script.
- [ ] Add backup verification script that restores into a temporary database.
- [ ] Emit backup success/failure metrics or business events.
- [ ] Add Blackbox Exporter checks for public Montry endpoints.
- [ ] Add alerts for failed backup and failed blackbox checks.
- [ ] Document restore procedure.
- [ ] Commit as `ops: add backup monitoring`.

## Epic 16: Final Verification

**Goal:** Prove the full observability platform works end to end.

**Files:**
- Create: `docs/operations/observability-verification.md`

- [ ] Start the full stack with Docker Compose.
- [ ] Register a test user and complete verification.
- [ ] Select each tariff at least once.
- [ ] Add a test site and create HTTP, SSL and domain monitors.
- [ ] Trigger manual checks.
- [ ] Force one notification success and one notification failure.
- [ ] Force one payment success and one payment failure in test mode.
- [ ] Force one incident open and resolve.
- [ ] Confirm business events are present in PostgreSQL.
- [ ] Confirm analytics events are present in ClickHouse.
- [ ] Confirm metrics are visible in Prometheus.
- [ ] Confirm logs are visible in Loki.
- [ ] Confirm traces are visible in Tempo.
- [ ] Confirm exceptions are visible in Sentry.
- [ ] Confirm Grafana dashboards show data.
- [ ] Confirm alert rules load.
- [ ] Commit as `test: document observability verification`.

---

## Implementation Order

1. Epic 1: Local Observability Stack
2. Epic 2: Correlation IDs and Request Context
3. Epic 3: Business Events Store
4. Epic 4: Instrument Core Product Events
5. Epic 5: Metrics in Laravel
6. Epic 6: Metrics in Go Poller
7. Epic 7: Structured Logging to Loki
8. Epic 8: Distributed Tracing
9. Epic 9: ClickHouse Analytics Pipeline
10. Epic 10: Admin and Owner Dashboards in Grafana
11. Epic 11: Alerts and Runbooks
12. Epic 12: Audit Log and Admin Security Events
13. Epic 13: Dead Letter and Failure Control
14. Epic 14: Sentry Integration
15. Epic 15: Backups and Self-Monitoring
16. Epic 16: Final Verification

## Completion Criteria

- Grafana contains owner, operations, monitoring, billing, notifications and security dashboards.
- Prometheus scrapes Laravel, poller and infrastructure exporters.
- Loki receives structured logs from Laravel, queue workers and Go poller.
- Tempo receives traces for critical flows.
- Sentry receives exceptions with release and correlation context.
- PostgreSQL stores business events.
- ClickHouse stores analytics events.
- Alerts exist for critical technical and business failures.
- Runbooks exist for every critical alert.
- No observability dependency can break core product flows when temporarily unavailable.

