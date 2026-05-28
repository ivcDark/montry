# Incident Analytics and Weekly Digest Design

Date: 2026-05-28

## Context

Montry currently has an `Incidents` page with open incidents, resolved incident history, warning incidents, basic summary cards, and period/type/search filters. The product also mentions reports in paid tariff positioning, but the `Reports` module is only a placeholder and a separate reports page would duplicate the incident workflow.

The better MVP direction is to make `Incidents` the operational analytics center: owners should understand which projects and sites had the most problems, how much downtime they caused, how quickly they recovered, and what changed compared with the previous period.

The first release should also add an automatic weekly email digest for paid accounts, because that gives the reports promise a concrete recurring value without creating a separate reporting product.

## Goals

- Remove the separate "Отчеты" navigation item for now.
- Keep the current incident list available as an operational view.
- Add paid incident analytics to the existing `Incidents` page for Pro and Plus plans.
- Show analytics by project first, then by site inside the selected project.
- Let users choose quick periods or a custom date range within tariff retention limits.
- Cache heavy analytics aggregates in Redis for 5 minutes to reduce PostgreSQL load.
- Add a weekly email digest for Pro and Plus users.
- Let each user enable or disable weekly digest delivery and set the delivery time.

## Non-Goals

- A standalone `Reports` page.
- PDF export, CSV export, white-label reports, or public client reports.
- A custom report builder.
- SLA contracts or formal uptime percentage guarantees.
- User-selectable timezones in the first release.
- Per-organization digest settings.
- Digest recipient lists outside organization users.

## Product Behavior

### Free Plan

Free users keep access to the incident list and warnings, but do not receive paid analytics or weekly digest emails.

The `Incidents` page should show a locked analytics block explaining that incident analytics are available on Pro and Plus. The CTA should lead to `/billing`.

Free users should not see fake demo analytics. The product should remain honest: the list is usable, analytics are paid.

### Pro and Plus Plans

Pro and Plus users see the enhanced incident analytics page.

The main workflow is:

1. Choose a period.
2. Optionally choose incident type: all, HTTP, SSL, or Domain.
3. Select a project from the project list.
4. Review charts and site metrics for that project.
5. Review the concrete incident journal below the analytics.

Analytics are based on `incidents`, not individual failed checks. In this feature, "failure" means a confirmed incident.

When no project is selected, the page should select the project with the highest incident count in the selected period. If incident counts tie, use the highest downtime. If there are no incidents in the period, select the first project by name and show empty analytics states.

## Period Rules

The page supports quick presets and custom date ranges.

Quick presets:

- 24 hours;
- 7 days;
- plan maximum, based on `history_retention_days`.

Custom date range:

- selected by `date_from` and `date_to`;
- inclusive by user intent;
- normalized server-side to the application date boundaries;
- rejected with a validation error when it exceeds the current plan retention window.

Plan limits:

- Pro: up to 14 days, based on current `history_retention_days`;
- Plus: up to 60 days, based on current `history_retention_days`;
- Free: no analytics access.

The backend is authoritative for period validation. The frontend may hide unavailable choices, but the server must still enforce the limit.

## Incident Analytics UI

### Desktop Layout

The selected layout is project-first.

Top section:

- KPI card: total incidents in selected period;
- KPI card: active incidents;
- KPI card: total downtime;
- KPI card: MTTR;
- comparison with the previous equivalent period.

Filter section:

- quick period presets;
- custom date range;
- type filter: all, HTTP, SSL, Domain.

Main section:

- left column: project list with incident count, downtime, MTTR, affected site count;
- right column: charts and site-level analytics for the selected project.

Selected project analytics:

- incidents by day;
- downtime by day;
- incident distribution by type;
- comparison with previous period;
- top problem sites;
- site summary table;
- incident journal for the selected project.

Incident journal:

- stays below the analytics on the same page;
- shows concrete incidents for the selected period/project/type;
- keeps links/actions to open the site and check the monitor again where applicable.

### Mobile Layout

Mobile uses the same data, but the project list becomes a project select/dropdown and blocks are stacked vertically.

The mobile order should be:

1. Filters.
2. KPI cards.
3. Project selector.
4. Charts.
5. Site summaries.
6. Incident journal.

## Charts

Use Chart.js with Vue integration, for example `chart.js` and `vue-chartjs`.

First release charts:

- incidents by day;
- downtime by day;
- distribution by type.

MTTR remains in KPI cards, project/site summaries, and previous-period comparison. It does not need its own chart in the first release.

## Data Model

No new table is required for the interactive analytics itself.

Analytics should read from existing incidents and their relations:

- `incidents.organization_id`;
- `incidents.project_id`;
- `incidents.monitored_resource_id`;
- `incidents.monitor_id`;
- `incidents.status`;
- `incidents.severity`;
- `incidents.started_at`;
- `incidents.resolved_at`;
- `incidents.duration_seconds`;
- `monitors.type`;
- project and monitored resource names for display.

Indexes may need to be added after implementation review if query plans require them. Likely useful indexes are organization plus started date, organization plus project plus started date, and organization plus monitored resource plus started date.

## Backend Architecture

Do not put analytics logic directly into `IncidentController`.

Add read-side classes inside the `Incidents` module, for example:

- `Application/Queries/IncidentAnalyticsQuery`;
- `Application/Handlers/BuildIncidentAnalyticsDashboardHandler`;
- `Application/DTO/IncidentAnalyticsFilters`;
- `Application/DTO/IncidentAnalyticsDashboard`.

The controller should:

1. Resolve the current organization.
2. Parse request filters.
3. Check plan access and retention limits.
4. Return locked analytics metadata for Free.
5. For Pro and Plus, call the analytics handler.
6. Return the Inertia page payload.

The handler should:

- build KPI totals;
- build previous-period comparison;
- build daily series;
- build type distribution;
- build project summaries;
- build selected project site summaries;
- build top problem sites;
- fetch the selected project incident journal.

The handler may use Eloquent or query builder, but the result should be returned as simple arrays/DTOs suitable for Inertia.

## Redis Cache Strategy

Use Redis for heavy analytics aggregates. The current Laravel app already has a Redis cache store configured; production should use Redis for cache either through `CACHE_STORE=redis` or explicit `Cache::store('redis')` usage for this feature.

Cache TTL: 5 minutes.

Cache these:

- KPI totals;
- previous-period comparison;
- daily incident counts;
- daily downtime;
- type distribution;
- project summary list;
- selected project site summary;
- top problem sites.

Do not cache these:

- active incident list;
- incident journal;
- permission checks;
- plan and retention checks.

Cache key inputs:

- organization id;
- plan code;
- date range;
- type filter;
- selected project id;
- analytics version.

Use an organization-level analytics version key so new or resolved incidents can invalidate previous aggregate keys without deleting by wildcard. For example:

- `incident_analytics_version:{organization_id}`;
- aggregate keys include the current version value.

When an incident opens or resolves, increment the organization analytics version. If the version key is missing, initialize it.

If Redis is unavailable, the page should compute aggregates from PostgreSQL and continue working. Cache failure should not break incident operations.

## Weekly Email Digest

### Availability

Weekly digest is available only for Pro and Plus organizations.

If an organization downgrades to Free, weekly digest delivery stops automatically even if user preferences remain enabled.

### Recipients

Send the digest to every active, non-blocked user in the organization who has weekly digest enabled.

The current product assumption is one organization per user, but digest preference and log records must still store `organization_id` so a future multi-organization model is not blocked.

### User Settings

Each user can:

- enable or disable weekly digest;
- set the delivery time.

First release timezone:

- fixed to `Europe/Moscow`;
- no timezone picker in the UI.

Default:

- weekly digest enabled by default for Pro and Plus users;
- users can explicitly disable it in settings;
- Free users may keep a saved preference, but delivery is skipped while the organization is on Free.

### Schedule

Digest day is fixed:

- Monday.

Digest period is fixed:

- previous calendar week, Monday 00:00:00 through Sunday 23:59:59 in `Europe/Moscow`.

The configured user time controls only delivery time, not report period.

The scheduler command should run every 5 minutes and select users whose configured Monday delivery time is due.

### Email Content

The email should include:

- organization name;
- report period;
- total incident count for the week;
- CTA to open `/incidents`;
- if incident count is zero, a short positive message that no incidents were recorded;
- if incident count is greater than zero, a table with:
  - site name;
  - incident type;
  - started at;
  - duration.

The first email version should stay concise. It does not need charts inside the email.

### Delivery Idempotency

Add delivery logs or another durable idempotency mechanism so the same user does not receive the same weekly report twice.

The natural unique key is:

- `user_id`;
- `organization_id`;
- `week_start_date`.

The command should create or claim a digest log before queueing email. Re-running the command must not duplicate messages.

### Data Structures

Create a preferences table:

- `incident_weekly_digest_preferences`
  - `id`;
  - `user_id`;
  - `organization_id`;
  - `enabled`;
  - `send_time`;
  - `timezone`;
  - timestamps.

Create a delivery log table:

- `incident_weekly_digest_logs`
  - `id`;
  - `user_id`;
  - `organization_id`;
  - `week_start_date`;
  - `week_end_date`;
  - `status`;
  - `sent_at`;
  - `error_message`;
  - timestamps;
  - unique index on `user_id`, `organization_id`, `week_start_date`.

## Routes and Pages

Update the existing incidents page route:

- `GET /incidents`

Expected query parameters:

- `period` for presets;
- `date_from`;
- `date_to`;
- `type`;
- `project_id`;
- `search` for the incident journal.

Add a simple user setting surface for weekly digest. If the general settings page is not implemented yet, place the control on the `Incidents` page near the paid analytics section for the first release.

Remove the sidebar navigation item:

- `Отчеты`.

## Error Handling

- Free requesting analytics receives locked metadata, not raw analytics.
- Date ranges outside retention are rejected with validation errors. Presets outside the current plan should be hidden or disabled in the frontend.
- Unknown project id is ignored and the server selects the default project for the current filter set.
- Unknown type returns a validation error.
- Redis failures do not break page rendering.
- Weekly digest send failures are logged and can be retried safely without duplicate successful sends.

## Testing

Laravel feature tests:

- Free user sees incident list and locked analytics block.
- Pro user sees analytics payload.
- Plus user can request up to the Plus retention period.
- Pro cannot request a period longer than plan retention.
- Project summaries include incident count, downtime, MTTR, and affected site count.
- Selected project site summaries only include sites from that project.
- Type filter limits analytics to HTTP/SSL/domain incidents.
- Incident journal remains uncached and respects selected filters.
- Reports navigation item is not shown.

Laravel unit tests:

- analytics period normalization;
- previous-period date calculation;
- MTTR calculation;
- Redis cache key generation;
- cache fallback when Redis throws;
- analytics version increment on incident open/resolve.

Weekly digest tests:

- Pro/Plus enabled user receives a Monday digest.
- Free enabled user does not receive a digest.
- inactive or blocked users do not receive a digest.
- zero-incident week still sends a digest.
- non-zero week renders the incident table.
- duplicate command runs do not send duplicate weekly digest emails.
- configured send time is interpreted in `Europe/Moscow`.

Frontend tests:

- Free locked state renders.
- Pro analytics chart data renders without layout errors.
- project selection changes selected project query state.

## Implementation Notes

Keep the first implementation scoped:

- no PDF;
- no export;
- no white label;
- no client recipient lists;
- no report builder;
- no email charts.

Prefer explicit query/handler classes in `Incidents` over a generic `Reports` service. The product concept is reporting, but the domain object is still an incident.

Use simple arrays for Chart.js datasets in the Inertia payload. Avoid sending raw incident rows for chart calculations to the browser.

Document any new plan limit keys if implementation adds them. The current design can use existing `history_retention_days` and plan code checks without a new limit key.
