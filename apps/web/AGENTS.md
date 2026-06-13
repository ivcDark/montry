# Laravel app instructions

This directory contains the Laravel application.

Use modular monolith + DDD-lite.

Docker containers are expected to be running. Run PHP, Composer, Artisan, Pint and PHPUnit commands through Docker/the root Makefile instead of the host PHP, for example `make artisan cmd="about"`, `make test`, or `docker compose exec web php artisan ...`.

Main business code lives in:

app/Modules
app/Shared

Do not put domain logic directly in controllers or Eloquent models.

Preferred write flow:

Controller
-> Form Request
-> Command
-> Handler
-> Domain service/entity
-> Repository interface
-> Eloquent repository/model

Preferred read flow:

Controller
-> Query
-> Query handler/read repository
-> Resource/DTO

Main MVP modules:

- Identity
- Billing
- Projects
- MonitoredResources
- Monitoring
- CheckTypes/HttpCheck
- CheckTypes/SslCheck
- CheckTypes/DomainCheck
- Incidents
- Notifications
- WorkerGateway

The Monitoring core must stay generic.
HTTP, SSL, Domain, DNS, Ping and other check types must be implemented as pluggable CheckType modules.

When adding a new monitor type:
1. Create a module in `app/Modules/CheckTypes`.
2. Implement `CheckTypeDefinitionInterface`.
3. Register it in `CheckTypeRegistry`.
4. Add validation/resource/view support.
5. Do not rewrite Monitoring core.
6. Add tests.

Use Laravel queues for async notifications/reports.
Use events/listeners for cross-module side effects.
Use policies/application services for billing limits.

Do not modify Go poller code from this directory unless the task explicitly requires full-stack changes.
