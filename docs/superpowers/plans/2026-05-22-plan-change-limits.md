# Plan Change Limits Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement tariff upgrade/downgrade behavior so upgrades apply after payment immediately, downgrades are scheduled for the current paid period end, and downgraded limits pause excess monitors deterministically.

**Architecture:** Reuse the existing `subscriptions` table with `status = scheduled` for delayed downgrades. Compare plans by `sort_order`, keep payment checkout for upgrades, add a downgrade scheduling service, and apply limits when scheduled subscriptions become active. Excess enabled monitors are paused by keeping the oldest `created_at ASC, id ASC` monitors allowed by the new plan.

**Tech Stack:** Laravel, Inertia, Vue 3, existing Billing models, Docker/Makefile PHPUnit flow.

---

## File Map

- Create `apps/web/app/Modules/Billing/Application/Services/PlanChangeClassifier.php`: classify selected plan as `same`, `upgrade`, or `downgrade`.
- Create `apps/web/app/Modules/Billing/Application/Services/ScheduleDowngrade.php`: create or replace scheduled downgrade subscriptions.
- Create `apps/web/app/Modules/Billing/Application/Services/ApplySubscriptionLimits.php`: pause monitors after downgrade based on new plan limits.
- Modify `apps/web/app/Modules/Billing/Application/Services/CheckoutService.php`: cancel scheduled downgrades when paid upgrade is confirmed.
- Modify `apps/web/app/Modules/Billing/Presentation/Http/Controllers/BillingController.php`: expose scheduled downgrade, route downgrade requests away from payment, and include richer plan/usage payloads.
- Create `apps/web/app/Modules/Billing/Presentation/Http/Requests/ScheduleDowngradeRequest.php`: validate downgrade plan code.
- Modify `apps/web/app/Modules/Billing/Presentation/Routes/web.php`: add `POST /billing/schedule-downgrade`.
- Modify `apps/web/routes/console.php`: add `billing:activate-scheduled-subscriptions`.
- Modify `apps/web/resources/js/Pages/Billing/Index.vue`: show current limits/usage, comparison table, pending downgrade, and downgrade confirmation modal.
- Test `apps/web/tests/Feature/Billing/BillingFlowTest.php`: scheduling, upgrade cancellation, activation, and limit enforcement.

## Task 1: Classification and Downgrade Scheduling

- [ ] Write failing feature tests proving Pro -> Free creates a scheduled subscription, keeps Pro active, creates no payment, and duplicate downgrade requests replace the previous scheduled plan.
- [ ] Implement `PlanChangeClassifier` using `sort_order`.
- [ ] Implement `ScheduleDowngrade` with a transaction, locking the organization, requiring an active current subscription, requiring a real `ends_at`, and using `starts_at = currentSubscription.ends_at`.
- [ ] Add `ScheduleDowngradeRequest`, route, and controller action.
- [ ] Run `make test -- --filter=BillingFlowTest`.

## Task 2: Upgrade Behavior

- [ ] Write failing tests proving Free -> Pro still creates a payment and paid Pro/Plus upgrade cancels scheduled downgrades.
- [ ] Update `BillingController::checkout` to classify selected plans: same/free current redirects to billing, downgrade redirects to schedule endpoint usage, upgrade starts payment.
- [ ] Update `CheckoutService::confirm` to mark scheduled subscriptions for the same organization as `canceled` when an upgrade payment is confirmed.
- [ ] Run `make test -- --filter=BillingFlowTest`.

## Task 3: Scheduled Activation and Limit Enforcement

- [ ] Write failing tests proving `billing:activate-scheduled-subscriptions` activates due scheduled downgrades and pauses excess enabled monitors.
- [ ] Implement `ApplySubscriptionLimits`: for selected plan, pause enabled monitors of disallowed types first, then keep only oldest allowed monitors up to `max_monitors`; set paused monitors to `enabled = false`, `status = paused`.
- [ ] Add console command `billing:activate-scheduled-subscriptions` that activates due scheduled subscriptions, replaces active subscriptions, and calls `ApplySubscriptionLimits`.
- [ ] Run `make test -- --filter=BillingFlowTest`.

## Task 4: Billing Page UI

- [ ] Update `Billing/Index.vue` to show usage/limits for current tariff.
- [ ] Add tariff comparison section with Free/Pro/Plus limits.
- [ ] Add downgrade modal: cancel closes it, confirm posts to `/billing/schedule-downgrade`.
- [ ] Show scheduled downgrade block with target plan and start date.
- [ ] Keep upgrade buttons as `POST /billing/checkout`.
- [ ] Run `npm run build`.

## Task 5: Final Verification

- [ ] Run `make test -- --filter=BillingFlowTest`.
- [ ] Run `make test`.
- [ ] Run `npm run build`.
- [ ] Check `git status --short` and `git diff --stat`.
