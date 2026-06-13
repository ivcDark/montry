# Montry — project context for Codex

## Project idea

Montry is a small SaaS project for website, domain, SSL and infrastructure monitoring.

The goal is not to become a market leader. The goal is to build a stable micro-SaaS for small businesses, freelancers, SEO specialists and web studios with around 100–300 clients and roughly 50,000 RUB/month revenue.

The product must be simple, reliable and easy to extend with new monitoring modules.

Core value:
- notify when a website is down;
- notify when SSL is expiring;
- notify when domain is expiring;
- help web studios monitor client websites;
- provide a clean personal account and later reports/status pages.

## Repository structure

The repository is a monorepo:

/montry
├── apps/
│   ├── web/       # Laravel app: landing, admin panel, user dashboard, billing, incidents, notifications
│   └── poller/    # Go service: performs monitoring checks
├── docker/        # Docker configs
├── docs/          # Project documentation
├── scripts/       # Scripts for setup, CI/CD, backup, deploy
├── .env           # Root Docker Compose env only
├── .env.example
├── .gitignore
├── docker-compose.yml
├── Makefile
└── README.md

Important:
- root `.env` is only for docker-compose;
- Laravel has its own env: `apps/web/.env`;
- Go poller can have its own env: `apps/poller/.env`;
- do not mix root Docker env with Laravel application env.
- Docker containers are expected to be running during development; run PHP, Composer, Artisan, Pint and PHPUnit commands through Docker/the Makefile instead of the host PHP.
- Prefer Makefile targets for Laravel commands, for example `make artisan cmd="about"`, `make test`, `make composer-install`, or `docker compose exec web php artisan ...` when a direct command is clearer.

## High-level architecture

The project has two main services:

### 1. Laravel web app

Path:

apps/web

Responsibilities:
- landing page;
- authentication;
- user dashboard;
- admin panel;
- organizations/accounts;
- projects/clients;
- monitored resources;
- monitor definitions;
- check settings;
- incidents;
- notifications;
- billing and tariffs;
- reports;
- status pages later;
- internal API for the Go poller.

Laravel is the source of truth for business logic and data.

### 2. Go poller service

Path:

apps/poller

Responsibilities:
- execute scheduled checks;
- execute manual checks requested from the Laravel dashboard;
- perform HTTP/HTTPS checks;
- perform SSL checks;
- perform domain expiration checks;
- later perform DNS, Ping, Port, Robots.txt, Sitemap, RKN, PageSpeed and other checks;
- return normalized raw check results back to Laravel.

The Go service should not own business rules about incidents, billing, tariffs, users or notifications.

Go performs checks.
Laravel interprets results and updates product state.

## Communication between Laravel and Go

Preferred MVP communication:

Laravel -> Go:
- request manual check;
- provide monitor settings.

Go -> Laravel:
- submit check result to Laravel internal API.

Recommended internal endpoints:

GET  /internal/monitors/due
POST /internal/check-results
POST /internal/manual-checks

Possible future improvement:
- use Redis Streams or another queue/broker;
- use Outbox pattern in Laravel for reliable event delivery.

Important rules:
- Go must not directly change incident status.
- Go must not directly send user notifications.
- Go must not enforce billing limits.
- Go should not depend on Laravel PHP classes.
- Laravel should expose stable DTO/API contracts for the poller.

## Laravel architecture style

Use modular monolith + DDD-lite.

Do not build an over-engineered academic DDD project.
Use clear modules, domain boundaries and application services.

Preferred Laravel module structure:

apps/web/app/
├── Shared/
│   ├── Domain/
│   ├── Application/
│   ├── Infrastructure/
│   └── Presentation/
└── Modules/
├── Identity/
├── Billing/
├── Projects/
├── MonitoredResources/
├── Monitoring/
├── CheckTypes/
│   ├── HttpCheck/
│   ├── SslCheck/
│   └── DomainCheck/
├── Incidents/
├── Notifications/
├── Reports/
└── WorkerGateway/

Each module may use this structure:

ModuleName/
├── Domain/
│   ├── Entities/
│   ├── ValueObjects/
│   ├── Events/
│   ├── Contracts/
│   └── Policies/
├── Application/
│   ├── Commands/
│   ├── Handlers/
│   ├── Queries/
│   ├── DTO/
│   └── Services/
├── Infrastructure/
│   ├── Persistence/
│   ├── Repositories/
│   ├── Providers/
│   └── Integrations/
└── Presentation/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Resources/
└── Routes/

## Laravel coding rules

Use this flow for write actions:

Controller
-> Form Request
-> Command
-> Handler
-> Domain entity/service
-> Repository interface
-> Eloquent repository/model

Use this flow for read actions:

Controller
-> Query
-> Query handler / read repository
-> Resource / DTO

Rules:
- keep controllers thin;
- do not put business logic in controllers;
- do not put complex business logic in Eloquent models;
- do not create a giant `MonitorService`;
- do not create helpers for domain logic;
- do not create traits for core business behavior unless truly necessary;
- prefer explicit classes over magic;
- prefer typed DTOs/value objects for important data;
- keep module dependencies explicit;
- use events/listeners for cross-module reactions;
- use Laravel queues for async Laravel-side work such as notifications and reports.

## Core domain concepts

### Organization

A customer account. A user can belong to one or more organizations.

### Project

A group of monitored resources, usually representing a client, website group or company.

### MonitoredResource

An object that can be monitored.

Examples:
- website;
- domain;
- API;
- server;
- IP.

For MVP:
- website;
- domain.

### Monitor

A configured check for a monitored resource.

Examples:
- HTTP monitor for https://example.ru;
- SSL monitor for example.ru;
- Domain expiration monitor for example.ru.

Important:
Use one generic `monitors` table with `type`, `settings` and `expected` JSON fields.
Do not create separate tables like `http_monitors`, `ssl_monitors`, `domain_monitors` at MVP stage.

### CheckType

A pluggable monitoring type.

MVP check types:
- http;
- ssl;
- domain.

Future check types:
- dns;
- ping;
- port;
- keyword;
- robots_txt;
- sitemap;
- redirect;
- rkn;
- blacklist;
- pagespeed;
- malware.

### CheckResult

A single result returned by the Go poller.

### Incident

A period of degraded or failed state.
An incident is not every failed check.
An incident opens only after configured confirmation rules, for example 2–3 consecutive failures.

### Notification

Message sent to user via Telegram, email, webhook or future channels.

## MVP functionality

The MVP must include:

### Account and dashboard

- registration;
- login;
- user dashboard;
- organization/account support if not too expensive;
- projects/clients;
- list of monitored resources;
- monitor status overview.

### Monitored resources

- add website/domain;
- edit website/domain;
- pause/resume monitoring;
- delete/archive resource;
- group resources by project/client.

### HTTP/HTTPS monitoring

- check website availability;
- check HTTP status code;
- check response time;
- follow redirects setting;
- timeout setting;
- expected status codes;
- detect website down;
- detect recovery;
- save check history.

### SSL monitoring

- check certificate validity;
- check expiration date;
- warn before expiration;
- default warning days: 30, 14, 7, 3, 1;
- save SSL check result.

### Domain monitoring

- check domain expiration date;
- warn before expiration;
- default warning days: 30, 14, 7, 3, 1;
- save domain check result.

### Manual checks

- user can click “Check now” in dashboard;
- Laravel validates permissions and tariff limits;
- Laravel sends check event to Go poller;
- Go executes check;
- Go returns result;
- Laravel saves result and updates monitor state.

### Incidents

- open incident after confirmed failures;
- close incident after confirmed recovery;
- save incident start time;
- save incident resolved time;
- calculate downtime duration;
- show incident history.

### Notifications

MVP channels:
- Telegram;
- Email.

Events:
- website down;
- website recovered;
- SSL expiring;
- domain expiring.

### Billing

MVP tariffs:
- Free;
- Solo;
- Studio.

Limits may include:
- max monitors;
- minimum check interval;
- notification channels;
- manual checks per day;
- history retention days.

### Admin panel

Basic admin functions:
- users;
- organizations;
- monitors;
- tariffs;
- payments/subscriptions later;
- system logs or check logs.

## Future functionality

Add later, not in the first MVP unless explicitly requested:

- DNS monitoring;
- DNS record change monitoring;
- Ping monitoring;
- TCP port monitoring;
- Cron/heartbeat monitoring;
- robots.txt monitoring;
- sitemap.xml monitoring;
- redirect checks;
- keyword/content checks;
- HTTP header checks;
- RKN block check;
- DNSBL/blacklist check;
- malware check;
- PageSpeed monitoring;
- public status pages;
- private status pages;
- reports;
- PDF reports;
- white label reports;
- team roles;
- webhooks;
- SMS;
- phone calls;
- Slack;
- Discord;
- VK Teams;
- Mattermost;
- API for clients;
- import resources from CSV;
- mass resource management;
- partner/referral program;
- promo codes;
- free public tools: SSL checker, WHOIS checker, DNS lookup, HTTP status checker, redirect checker.

## Main database tables for MVP

Recommended tables:

users
organizations
organization_users
projects
monitored_resources
monitors
check_results
monitor_state_changes
incidents
incident_comments
notification_channels
notification_rules
notification_logs
plans
plan_limits
subscriptions
payments
outbox_messages

## monitors table idea

The `monitors` table should be generic:

id
organization_id
project_id
monitored_resource_id
type
name
enabled
status
interval_seconds
timeout_ms
settings JSON
expected JSON
last_check_at
next_check_at
last_success_at
last_failure_at
consecutive_successes
consecutive_failures
created_at
updated_at
deleted_at

Example HTTP settings:

{
"method": "GET",
"url": "https://example.ru",
"follow_redirects": true,
"verify_ssl": true
}

Example HTTP expected:

{
"status_codes": [200],
"max_response_time_ms": 5000
}

Example SSL settings:

{
"domain": "example.ru",
"port": 443,
"warning_days": [30, 14, 7, 3, 1]
}

Example Domain settings:

{
"domain": "example.ru",
"warning_days": [30, 14, 7, 3, 1]
}

## check_results table idea

id
monitor_id
organization_id
check_type
status
checked_at
response_time_ms
status_code
error_code
error_message
raw_result JSON
normalized_result JSON
created_at

## Extensible check type architecture

The Monitoring module must not know implementation details of HTTP, SSL, Domain, DNS, Ping or other check types.

Use a registry of check type definitions.

Every check type should define:
- type code;
- label;
- default settings;
- default expected values;
- settings validation;
- expected validation;
- settings normalization;
- worker result normalization;
- status resolver.

Suggested PHP interface:

interface CheckTypeDefinitionInterface
{
public function type(): string;

    public function label(): string;

    public function defaultSettings(): array;

    public function defaultExpected(): array;

    public function validateSettings(array $settings): array;

    public function validateExpected(array $expected): array;

    public function normalizeSettings(array $settings): array;

    public function normalizeWorkerResult(array $result): array;

    public function resolveStatus(array $normalizedResult, array $expected): string;
}

Suggested registry:

final class CheckTypeRegistry
{
private array $types = [];

    public function register(CheckTypeDefinitionInterface $definition): void
    {
        $this->types[$definition->type()] = $definition;
    }

    public function get(string $type): CheckTypeDefinitionInterface
    {
        if (! isset($this->types[$type])) {
            throw new InvalidArgumentException("Unknown check type: {$type}");
        }

        return $this->types[$type];
    }

    public function all(): array
    {
        return $this->types;
    }
}

Each check type module should register itself via its ServiceProvider.

Example:

CheckTypes/HttpCheck
CheckTypes/SslCheck
CheckTypes/DomainCheck
CheckTypes/DnsCheck

Adding a new monitoring type should usually require:
1. Create a new CheckTypes module in Laravel.
2. Add a CheckTypeDefinition.
3. Register it in the registry.
4. Add request/resource/view support if needed.
5. Add check implementation in Go poller.
6. Add tests.
7. Do not rewrite Monitoring core.

## Go poller architecture

Path:

apps/poller

Suggested structure:

apps/poller/
├── cmd/
│   └── poller/
│       └── main.go
├── internal/
│   ├── app/
│   ├── checks/
│   │   ├── httpcheck/
│   │   ├── sslcheck/
│   │   ├── domaincheck/
│   │   ├── dnscheck/
│   │   ├── pingcheck/
│   │   └── portcheck/
│   ├── scheduler/
│   ├── transport/
│   │   ├── http/
│   │   └── redis/
│   ├── laravel/
│   ├── config/
│   └── logger/
├── pkg/
├── tests/
├── Dockerfile
├── go.mod
└── go.sum

Go rules:
- each check type should be a separate package;
- common check result format should be shared;
- checker implementations should be behind interfaces;
- scheduler should not contain HTTP/SSL/domain-specific logic;
- Laravel client should be isolated in `internal/laravel`;
- timeout and retry behavior should be explicit;
- poller should return raw technical result to Laravel;
- poller should not create incidents;
- poller should not notify end users;
- poller should not know tariff rules.

## Worker payload example

Laravel -> Go:

{
"event_id": "uuid",
"event_type": "manual_check_requested",
"monitor_id": "uuid",
"check_type": "http",
"target": "https://example.ru",
"settings": {
"method": "GET",
"follow_redirects": true,
"verify_ssl": true
},
"expected": {
"status_codes": [200],
"max_response_time_ms": 5000
},
"requested_at": "2026-05-12T12:00:00+03:00"
}

Go -> Laravel:

{
"event_id": "uuid",
"monitor_id": "uuid",
"check_type": "http",
"status": "success",
"checked_at": "2026-05-12T12:00:05+03:00",
"duration_ms": 341,
"result": {
"status_code": 200,
"response_time_ms": 341,
"ip": "1.2.3.4",
"headers": {
"server": "nginx"
}
},
"error": null
}

## Incident rules

Default MVP logic:
- 1 failed check is suspicious but may not open an incident;
- 2 or 3 consecutive failures open an incident;
- 1 or 2 consecutive successful checks close an incident;
- notifications should be sent on incident open and incident resolved;
- do not spam notifications on every failed check during the same incident.

## Notification rules

MVP:
- Telegram;
- Email.

Future:
- Webhook;
- SMS;
- phone call;
- Slack;
- Discord;
- VK Teams;
- Mattermost.

Notification logic should be in the Notifications module.
Monitoring and Incidents modules should emit domain events.
Notifications module should listen to these events.

## Billing rules

Billing limits should be checked in application services/policies before:
- creating monitor;
- enabling monitor;
- changing interval;
- requesting manual check;
- adding notification channel;
- enabling advanced features.

Do not scatter tariff checks across controllers.

Use a `LimitChecker` or similar application service inside Billing module.

## Testing expectations

When implementing features, add or update tests where practical.

Laravel:
- feature tests for HTTP endpoints;
- unit tests for domain services;
- unit tests for CheckTypeDefinition classes;
- tests for incident open/resolve logic;
- tests for billing limit checks.

Go:
- unit tests for each check package;
- tests for payload parsing;
- tests for Laravel client;
- tests for scheduler behavior where practical.

## Development commands

Prefer using Makefile commands when available.

Common commands may include:

make up
make down
make logs
make web-shell
make poller-shell
make migrate
make fresh
make test-web
make test-poller

If commands are missing, add them carefully to Makefile.

## Documentation rules

Update docs when changing architecture or API contracts.

Important docs:
- docs/architecture/overview.md
- docs/architecture/modules.md
- docs/architecture/monitoring-flow.md
- docs/architecture/poller-flow.md
- docs/architecture/database.md
- docs/api/internal-api.md
- docs/product/mvp.md
- docs/product/roadmap.md
- docs/product/tariffs.md

## Code style

General:
- write simple, explicit code;
- avoid premature abstraction;
- keep modules independent;
- prefer small classes with clear responsibility;
- do not modify unrelated files;
- do not reformat entire files unless needed;
- keep diffs focused;
- keep names domain-oriented.

Laravel:
- use strict types where reasonable;
- use typed properties and return types;
- use Form Requests for validation;
- use API Resources for API output;
- use Service Providers for module bindings;
- use Events/Listeners for cross-module side effects;
- use Queues for async jobs.

Go:
- keep packages small;
- use context.Context for external operations;
- set timeouts;
- return structured errors;
- avoid global mutable state;
- keep interfaces near consumers where appropriate.

## What not to do

Do not:
- turn Laravel into a microservice system too early;
- create separate DB tables for every check type at MVP stage;
- put business logic in controllers;
- create a giant `MonitoringService`;
- let Go own incident or notification logic;
- let Go directly depend on Laravel database schema unless explicitly requested;
- add many integrations before MVP core is stable;
- add enterprise features before MVP;
- introduce Kubernetes, complex observability, SSO or heavy infrastructure unless explicitly requested;
- implement new monitoring modules by rewriting the core Monitoring module.

## Definition of done

A task is done when:
- implementation matches this architecture;
- MVP scope is respected unless task explicitly says otherwise;
- code is placed in the correct module;
- new check types are added through the registry pattern;
- Laravel and Go contracts remain clear;
- migrations are included if database schema changes;
- tests are added or updated where practical;
- docs are updated if architecture/API changes;
- no unrelated files are modified.
