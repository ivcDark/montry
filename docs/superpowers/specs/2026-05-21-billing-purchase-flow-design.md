# Billing Purchase Flow Design

Date: 2026-05-21

## Context

Montry already has the base billing pieces: plans, plan limits, subscriptions, payments, a billing page, and a manual checkout confirmation flow. The missing part is a coherent purchase process that starts from a public pricing card and survives registration, email verification, and login.

The MVP must keep Free as the default subscription for every new organization. Paid plan purchase is an additional checkout flow created after the account exists.

## Goals

- Preserve the selected pricing plan when a visitor starts from the landing page.
- Create a Free subscription for every newly registered organization.
- For paid plans, create a pending purchase after registration or login and show a purchase confirmation page.
- Keep the flow simple enough for MVP and avoid a persistent cart or bank integration.
- Make the fake payment step behave like an external bank redirect: wait briefly, confirm payment, then send the user to the dashboard.

## Non-Goals

- Real bank acquiring integration.
- Promocodes, invoices, fiscal receipts, refunds, trial periods, or recurring payment automation.
- A separate `purchase_intents` table.
- Changing plan limit enforcement beyond the existing Billing module services.

## Selected Approach

Use a session-based purchase intent.

When a user clicks a pricing card, the selected `plan_code` is stored in the session as the intended plan. This intent is used after registration verification or login to start checkout. The intent is cleared once a pending payment is created or when it resolves to the Free plan.

This is an MVP-friendly choice because it does not require another table, fits the current Laravel session/auth flow, and is easy to replace later with a persisted checkout intent if bank integration requires it.

## User Flows

### New User, Free Plan

1. User clicks "Выбрать тариф" or the Free plan CTA.
2. User is sent to the registration page.
3. User submits registration form.
4. User receives and submits email verification code.
5. Laravel verifies the code, creates the account records, organization, default folder, and Free subscription.
6. User is redirected to `/dashboard`.

No payment or pending paid subscription is created.

### New User, Paid Plan

1. User clicks "Выбрать тариф" on a paid plan.
2. Laravel stores the selected `plan_code` in session and sends the user to registration.
3. User submits registration form.
4. User receives and submits email verification code.
5. Laravel verifies the code and creates the account records, organization, default folder, and Free subscription.
6. Laravel creates a pending subscription and pending payment for the selected paid plan.
7. User is redirected to a purchase confirmation page that shows the selected tariff and price.
8. User clicks "Перейти к оплате".
9. User sees a fake payment page that waits for 1 second, confirms the payment, activates the paid subscription, replaces the Free subscription, and redirects to `/dashboard`.

### Existing User, Not Authenticated

1. User clicks "Выбрать тариф" on a paid plan.
2. User is sent to registration with the selected plan preserved in session.
3. User clicks "Войти" near "Уже есть аккаунт?".
4. Login page keeps the selected paid plan intent.
5. After successful login, Laravel creates a pending subscription and pending payment for the selected paid plan.
6. User is redirected to the purchase confirmation page.

If the selected plan is Free, login redirects to `/dashboard`.

### Authenticated User

1. User clicks "Выбрать тариф" on the landing page or billing page.
2. If the selected plan is Free or already active, the user goes to the billing page without creating a payment.
3. If the selected plan is paid and different from the active plan, Laravel creates a pending subscription and payment.
4. User is redirected to the purchase confirmation page.

## Routes and Pages

The flow should use these routes:

- `GET /register?plan={code}`: stores valid `plan_code` as session intent and renders registration.
- `GET /login?plan={code}`: stores valid `plan_code` as session intent and renders login.
- `POST /register`: starts email verification and keeps the session intent.
- `POST /register/verify-code`: completes registration and dispatches post-auth billing redirect logic.
- `POST /login`: logs in and dispatches post-auth billing redirect logic.
- `GET /billing/payments/{payment}`: shows purchase confirmation for a pending payment.
- `POST /billing/checkout`: starts checkout for an authenticated user and redirects to confirmation.
- `GET /billing/payments/{payment}/fake-bank`: shows the fake bank page.
- `POST /billing/payments/{payment}/confirm`: confirms the fake payment and redirects to `/dashboard`.

The existing `Billing/Payment.vue` becomes the purchase confirmation page with selected tariff, price, and the "Перейти к оплате" button.

Add `Billing/FakeBankPayment.vue` for the 1-second fake payment transition.

## Data Model

No new database tables are required.

Existing tables are used as follows:

- `subscriptions`: every organization starts with an active Free subscription.
- `subscriptions`: paid checkout creates a second subscription with `status = pending`.
- `payments`: paid checkout creates a payment with `status = pending`.
- `payments.provider`: use `manual` or `fake_bank` for MVP.
- On fake payment confirmation, paid payment becomes `paid`, paid subscription becomes `active`, and the previous active Free subscription becomes `replaced`.

Free plan selection must not create a pending payment.

## Domain Rules

- Free is always the default subscription for new organizations.
- Paid checkout can only be started for an active paid plan.
- A user can only view or confirm payments that belong to their current organization.
- Go poller is not involved in billing.
- Billing limits remain enforced through Billing application services, not controllers.
- Controllers should remain thin: intent handling, checkout start, and payment confirmation should live in small application services.

## Error Handling

- Unknown or inactive `plan_code` from public registration or login links: clear the intent and continue with the default Free/dashboard flow.
- Unknown or inactive `plan_code` submitted by an authenticated checkout request: return validation error and do not create a payment.
- Current plan selected: do not create a duplicate pending payment.
- Payment already paid: redirect to dashboard or billing instead of confirming twice.
- Payment owned by another organization: return 404.
- Expired or missing registration session: redirect back to registration.

## Testing

Add or update Laravel feature tests for:

- new user selecting Free reaches dashboard after verification and gets an active Free subscription;
- new user selecting a paid plan gets Free plus pending paid payment after verification;
- existing unauthenticated user selecting a paid plan reaches purchase confirmation after login;
- authenticated user selecting a paid plan reaches purchase confirmation;
- fake bank payment confirms the payment, activates the paid subscription, replaces Free, and redirects to dashboard;
- users cannot access another organization's payment;
- invalid plan codes do not create payments.

## Implementation Notes

Prefer a small Billing application service for post-auth purchase intent handling, for example `ResolvePendingPlanIntent` or `StartIntendedCheckout`. It should:

1. Read the selected plan code from session.
2. Validate the plan.
3. Clear the intent when it is consumed.
4. Return the correct redirect target.
5. Start checkout only when the plan is paid and not already active.

This keeps registration and login controllers from growing billing-specific business logic.
