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
