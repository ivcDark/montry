# Database Schema

Дата обновления: 2026-05-12.

Проект еще не в production, поэтому доменная схема Laravel приведена к чистому MVP-набору миграций без сохранения исторических данных.

## Framework Tables

Эти таблицы остаются как инфраструктура Laravel и не являются доменной моделью Montry:

- `users`
- `password_reset_tokens`
- `sessions`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`

`users` используется модулем `Identity`. `cache` и `jobs` нужны текущей конфигурации Laravel cache/queue.

## Identity

### `organizations`

Customer account.

Основные поля:

- `id`
- `name`
- `slug`
- `timezone`
- `status`
- timestamps
- soft deletes

### `organization_users`

Membership пользователя в организации.

Основные поля:

- `organization_id`
- `user_id`
- `role`
- `status`
- `invited_at`
- `joined_at`

Ограничение: один пользователь не может быть добавлен в одну организацию дважды.

## Projects

### `projects`

Группа monitored resources, обычно клиент или набор сайтов.

Основные поля:

- `organization_id`
- `name`
- `slug`
- `color`
- `is_default`
- `sort_order`
- timestamps
- soft deletes

На уровне БД есть частичный уникальный индекс: одна default project на организацию.

## Monitored Resources

### `monitored_resources`

Объект мониторинга: website, domain, API, server или другой будущий тип.

Основные поля:

- `organization_id`
- `project_id`
- `created_user_id`
- `type`
- `name`
- `target`
- `scheme`
- `host`
- `port`
- `path`
- `status`
- `notes`
- timestamps
- soft deletes

Для MVP основные типы ресурсов: `website`, `domain`.

## Monitoring

### `monitors`

Универсальная таблица для всех типов проверок.

Основные поля:

- `organization_id`
- `project_id`
- `monitored_resource_id`
- `type`
- `name`
- `enabled`
- `status`
- `interval_seconds`
- `timeout_ms`
- `settings` JSON
- `expected` JSON
- `last_check_at`
- `next_check_at`
- `last_success_at`
- `last_failure_at`
- `consecutive_successes`
- `consecutive_failures`
- timestamps
- soft deletes

Нельзя создавать отдельные таблицы `http_monitors`, `ssl_monitors`, `domain_monitors`. Новые типы проверок добавляются через `CheckTypes` registry и используют `settings`/`expected`.

### `check_results`

Один технический результат проверки, полученный от poller или manual check.

Основные поля:

- `monitor_id`
- `organization_id`
- `check_type`
- `status`
- `checked_at`
- `response_time_ms`
- `status_code`
- `error_code`
- `error_message`
- `raw_result` JSON
- `normalized_result` JSON

### `monitor_state_changes`

История изменения состояния monitor.

Основные поля:

- `monitor_id`
- `organization_id`
- `check_result_id`
- `from_status`
- `to_status`
- `reason`
- `changed_at`

## Incidents

### `incidents`

Период деградации или отказа. Incident не равен каждому failed check.

Основные поля:

- `organization_id`
- `project_id`
- `monitored_resource_id`
- `monitor_id`
- `status`
- `severity`
- `title`
- `summary`
- `started_at`
- `resolved_at`
- `duration_seconds`
- `opened_by_check_result_id`
- `resolved_by_check_result_id`

### `incident_comments`

Комментарии к incident.

Основные поля:

- `incident_id`
- `organization_id`
- `user_id`
- `body`

## Notifications

### `notification_channels`

Канал доставки уведомлений.

Основные поля:

- `organization_id`
- `user_id`
- `type`
- `name`
- `enabled`
- `settings` JSON
- `verified_at`
- timestamps
- soft deletes

MVP-типы: `email`, `telegram`.

### `notification_rules`

Правила подписки channel на события.

Основные поля:

- `organization_id`
- `notification_channel_id`
- `event_type`
- `enabled`
- `conditions` JSON

### `notification_logs`

Журнал попыток отправки уведомлений.

Основные поля:

- `organization_id`
- `notification_channel_id`
- `incident_id`
- `event_type`
- `status`
- `payload` JSON
- `error_message`
- `sent_at`

## Billing

### `plans`

Тарифный план.

Основные поля:

- `code`
- `name`
- `description`
- `price_cents`
- `currency`
- `is_active`
- `sort_order`

### `plan_limits`

Лимиты тарифного плана.

Основные поля:

- `plan_id`
- `key`
- `value` JSON

Ограничение: один `key` на план.

### `subscriptions`

Подписка организации на тариф.

Основные поля:

- `organization_id`
- `plan_id`
- `status`
- `starts_at`
- `ends_at`
- `trial_ends_at`

### `payments`

Платежи организации.

Основные поля:

- `organization_id`
- `subscription_id`
- `provider`
- `provider_payment_id`
- `status`
- `amount_cents`
- `currency`
- `payload` JSON
- `paid_at`

## WorkerGateway

### `outbox_messages`

Outbox для надежной доставки событий во внешние обработчики или poller-интеграции.

Основные поля:

- `event_id`
- `event_type`
- `aggregate_type`
- `aggregate_id`
- `payload` JSON
- `status`
- `attempts`
- `available_at`
- `published_at`
- `last_error`
