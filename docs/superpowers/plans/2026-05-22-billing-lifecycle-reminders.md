# Billing Lifecycle Reminders Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add daily billing lifecycle commands that warn paid users before and after tariff expiration, activate scheduled tariffs automatically, and move unpaid expired paid tariffs to Free after a 3-day grace period.

**Architecture:** Billing owns subscription state transitions and billing emails. Commands delegate to small application services; email sending is idempotent through a billing notification log. Existing `ApplySubscriptionLimits` is reused when the organization finally falls back to Free.

**Tech Stack:** Laravel console commands, Eloquent models, Mailables, PHPUnit feature tests, existing Billing module.

---

### Task 1: Notification Log And Reminder Emails

**Files:**
- Create: `apps/web/database/migrations/*_create_billing_notification_logs_table.php`
- Create: `apps/web/app/Modules/Billing/Infrastructure/Persistence/Models/BillingNotificationLog.php`
- Create: `apps/web/app/Modules/Billing/Application/Mail/SubscriptionRenewalReminderMail.php`
- Create: `apps/web/app/Modules/Billing/Application/Mail/SubscriptionPastDueReminderMail.php`
- Create: `apps/web/resources/views/emails/billing/subscription-renewal-reminder.blade.php`
- Create: `apps/web/resources/views/emails/billing/subscription-past-due-reminder.blade.php`
- Modify: `apps/web/tests/Feature/Billing/BillingFlowTest.php`

- [ ] Write failing tests for 3-day and 1-day paid renewal reminders, Free exclusion, and duplicate suppression.
- [ ] Add the billing notification log table with a unique key on `subscription_id`, `event_type`, and `event_date`.
- [ ] Add mail classes and Blade views with concise Russian copy.
- [ ] Implement reminder dispatch service and command.

### Task 2: Scheduled Activation And Grace Period

**Files:**
- Modify: `apps/web/routes/console.php`
- Create: `apps/web/app/Modules/Billing/Application/Services/ProcessPastDueSubscriptions.php`
- Modify: `apps/web/tests/Feature/Billing/BillingFlowTest.php`

- [ ] Write failing tests showing paid scheduled tariffs activate as `past_due`, Free scheduled tariffs activate as `active`, and previous active subscriptions become `replaced`.
- [ ] Update `billing:activate-scheduled-subscriptions` to set paid scheduled plans to `past_due`.
- [ ] Add `billing:process-past-due-subscriptions` and keep `billing:expire-subscriptions` as a compatibility alias.
- [ ] When a paid `past_due` tariff is older than 3 days, create/activate Free and apply Free limits.

### Task 3: Verification

**Files:**
- Modify only files required by the feature.

- [ ] Run billing feature tests.
- [ ] Run full Laravel test suite.
- [ ] Report exact verification output.
