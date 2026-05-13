# Go poller instructions

This directory contains the Go monitoring service.

The poller performs technical checks and returns results to Laravel.

The poller does not own:
- users;
- billing;
- tariffs;
- incidents;
- notifications;
- reports.

Laravel is the source of truth for business state.

Suggested structure:

cmd/poller/main.go
internal/app
internal/checks/httpcheck
internal/checks/sslcheck
internal/checks/domaincheck
internal/checks/dnscheck
internal/scheduler
internal/transport/http
internal/transport/redis
internal/laravel
internal/config
internal/logger

Rules:
- each check type should be a separate package;
- scheduler must not contain check-specific logic;
- Laravel API client must be isolated in `internal/laravel`;
- use context.Context;
- use explicit timeouts;
- return structured check results;
- do not send user notifications;
- do not create or close incidents;
- do not enforce billing rules;
- do not directly depend on Laravel PHP classes.

When adding a new check type:
1. Add a new package in `internal/checks`.
2. Implement the checker interface.
3. Add payload/result support if needed.
4. Add tests.
5. Keep result format compatible with Laravel internal API.