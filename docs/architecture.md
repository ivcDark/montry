# Montri Architecture Notes

## MVP scope

- Main product service: Laravel
- Database: PostgreSQL
- Cache and queues: Redis
- Local development: Docker Compose
- Repository shape: modular monolith inside `apps/web`

## Repository areas

- `apps/web` contains the main product application
- `apps/poller` is reserved for the future Go checker service
- `docker` stores local infrastructure configuration
- `docs` stores project documentation
- `scripts` stores support scripts

## Pragmatic boundary for the first version

All user-facing and business workflows live in Laravel first. A separate Go poller is added later only when background checks need stronger isolation or throughput.

## Registration verification

Registration uses a 5-digit email code before the customer account is fully initialized.

Flow:

1. The registration form creates only an unverified `users` row.
2. Laravel creates a hashed verification code in `email_verification_codes` and sends the plaintext code by email.
3. The pending user id is stored in the session as `pending_registration_user_id`.
4. The user enters the code on `/register/verify-code`.
5. If the code is correct, unexpired and unused, Laravel sets `users.email_verified_at`, creates the organization and default project, logs the user in and redirects to the dashboard.
6. A repeated code can be requested after 120 seconds. The old active code is consumed and a new 10-minute code is created.
