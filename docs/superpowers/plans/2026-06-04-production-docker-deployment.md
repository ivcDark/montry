# Production Docker Deployment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a standalone production Docker Compose, repeatable Makefile deployment commands, production environment templates, and an operator deployment guide.

**Architecture:** Build frontend assets with a one-shot Node Compose service, then bake application code and assets into immutable Laravel and Nginx images. Run only Nginx, Laravel PHP-FPM, Laravel scheduler, PostgreSQL, Redis, and the compiled Go poller as persistent production services.

**Tech Stack:** Docker Compose, Docker multi-stage builds, Laravel 13/PHP-FPM, Nginx, Node/Vite, PostgreSQL, Redis, Go

---

### Task 1: Production images and Compose

**Files:**
- Create: `.dockerignore`
- Modify: `docker/php/Dockerfile`
- Create: `docker/nginx/Dockerfile`
- Create: `docker/nginx/prod.conf`
- Create: `docker-compose.prod.yml`
- Create: `apps/poller/.dockerignore`

- [x] Add production health behavior and immutable Laravel/Nginx image definitions.
- [x] Exclude secrets, local dependencies, Git history, and unrelated artifacts from Docker build contexts.
- [x] Define the one-shot `node` build service and persistent production services.
- [x] Validate the Compose structure with `docker compose config`.

### Task 2: Production environment contracts

**Files:**
- Modify: `.gitignore`
- Create: `.env.production.example`
- Create: `apps/web/.env.production.example`
- Create: `apps/poller/.env.production.example`

- [x] Add explicit root, Laravel, and poller production environment templates.
- [x] Ensure real production env files are ignored while examples remain tracked.
- [x] Validate the examples contain all variables referenced by production Compose.

### Task 3: Production Makefile workflow

**Files:**
- Modify: `Makefile`

- [x] Add production Compose variables and env preflight checks.
- [x] Add `prod-build-frontend`, `prod-build`, `prod-up`, `prod-down`, `prod-restart`, `prod-logs`, `prod-ps`, `prod-migrate`, and `prod-optimize-clear`.
- [x] Add repeatable `prod-deploy` and `prod-update` workflows.
- [x] Dry-run Makefile targets to verify command ordering.

### Task 4: Production deployment documentation

**Files:**
- Create: `docs/deployment/production.md`
- Modify: `README.md`

- [x] Document server prerequisites, environment preparation, first deployment, updates, rollback, backups, and verification.
- [x] Add the required container responsibility table, including ports, dependencies, and stop impact.
- [x] Explicitly document that Node is one-shot and should not remain running.
- [x] Link the production guide from the README.

### Task 5: Verification

**Files:**
- Verify all modified production deployment files.

- [x] Run `docker compose --env-file .env.production.example -f docker-compose.prod.yml config`.
- [x] Run frontend production build through the one-shot Node service.
- [x] Build production application images.
- [x] Run `git diff --check`.
- [x] Review the final diff against the confirmed design.
