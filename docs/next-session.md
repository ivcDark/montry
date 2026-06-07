# Montry: Next Session Handoff

## Current product direction

- Redesign direction and compact UI rules are documented in `docs/product/redesign.md`.
- New canonical tariffs and paid monitoring add-ons are documented in `docs/product/tariffs.md`.
- Visual direction: light, clean, green-accented interface inspired by `prufen.ru`, without wide decorative dashboard layouts.
- Competitor UX references: `prufen.ru`, `enterno.io`, `statuser.cloud`, `monisite.ru`.
- Basic monitoring types: website availability, SSL, domain check, DNS monitoring, `robots.txt`.
- Paid monitoring/add-ons: `sitemap_xml` (+20 руб/site/month), `tcp_port` (+20 руб/port/month), `extra_5_sites` (+150 руб/month), `api_endpoint` (+30 руб/endpoint/month).
- New monitor types may be added to Laravel/UI first; Go poller can return safe stub results until check implementations are added.

## Current state

- Base local stack is running through Docker Compose.
- Laravel app lives in `apps/web`.
- PostgreSQL, Redis, Mailpit, and RabbitMQ are connected in local development.
- Auth is implemented:
  - registration
  - login/logout
  - password reset via temporary email link
- Domains are implemented:
  - create domain
  - domains list
  - domain card
- Checks are implemented:
  - manual domain check from UI
  - automatic periodic HTTP, SSL and domain checks through Go poller
  - check history
  - SSL inspection
  - WHOIS expiration lookup
- Problems are implemented:
  - active problems are synced after each check
  - problem records are stored in DB
- Notifications are implemented:
  - new problem emails are sent through Mailpit
  - email delivery is asynchronous through RabbitMQ
- Domain check dispatch is also asynchronous through RabbitMQ.
- Generic monitor dispatch is available through the Go poller internal API:
  - Laravel exposes `GET /internal/monitors/due`
  - Laravel exposes `POST /internal/check-results`
  - poller scheduler fetches due monitors and posts results back
- Due monitor dispatch uses a short lease on `monitors.check_in_progress_until` to avoid handing the same monitor to the poller repeatedly while a check is in flight.

## Important architecture decisions

- Project structure follows a modular monolith approach inside Laravel.
- Main modules currently:
  - `Auth`
  - `Domains`
  - `Checks`
  - `Problems`
  - `Notifications`
- Business logic is kept out of controllers and placed into actions/services.
- RabbitMQ is integrated through a lightweight custom AMQP publisher/consumer approach.
- We did not use a Laravel RabbitMQ queue driver.

## RabbitMQ flow

- Manual domain check flow:
  - UI submits request
  - app publishes message to `domain.checks`
  - `worker` consumes the message
  - worker runs the domain check
  - problems are synced
  - if a new problem appears, a mail job is published to `mail.notifications`
  - worker consumes the mail job
  - email is sent to Mailpit

## Useful local commands

```bash
make up
make down
make ps
make logs
make worker-logs
make migrate
make artisan cmd="route:list"
```

## Local URLs

- App: `http://localhost:8080`
- Mailpit UI: `http://localhost:8025`
- RabbitMQ UI: `http://localhost:15672`

## RabbitMQ credentials

- user: `montry`
- password: `montry`

## Important queues

- `domain.checks`
- `mail.notifications`

## Verified recently

- Async email delivery through RabbitMQ works.
- Async domain check dispatch through RabbitMQ works.
- End-to-end verification was done with test domains like:
  - `neverssl.com`
  - `php.net`

## Notes about local test data

- Local DB contains test user:
  - `alice@example.com`
- Local DB also contains several test domains created during verification.

## Recommended next steps

1. Verify scheduled monitor lease behavior under real poller load.
2. Improve dashboard with summary counters and recent activity.
3. Add tests for auth, domain checks, poller lease behavior and RabbitMQ-related flows when we decide to invest in coverage.
4. Continue polishing explicit "checking / last checked / next check" UI states.
5. Decide whether old RabbitMQ domain-check flow should be retired or kept as a compatibility path.

## Start of next session

When continuing tomorrow, start from:

1. `make up`
2. `make ps`
3. `make worker-logs`
4. Open Mailpit and RabbitMQ UI if needed
5. Pick the next feature from the list above
