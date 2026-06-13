# Montry Laravel Architecture Refactor Plan

Дата анализа: 2026-05-12.

Документ описывает план приведения текущего Laravel-приложения `apps/web` к архитектуре из `AGENTS.md`. На этом этапе код не меняется, Docker и Go poller не затрагиваются.

## 1. Текущее состояние `apps/web`

Laravel-приложение уже выделено в `apps/web` и использует Inertia/Vue для интерфейса. Внутри `app` есть ранняя модульная структура:

- `app/Models/User.php` - базовая Eloquent-модель пользователя.
- `app/Application/Onboarding/Actions/CreateAccount.php` - сценарий регистрации аккаунта: создать пользователя, организацию и default folder.
- `app/Modules/Auth` - регистрация, логин, logout, DTO, form requests, routes.
- `app/Modules/Organizations` - организация, pivot `organization_users`, роли, план, статус.
- `app/Modules/Sites` - текущий основной модуль сайтов, папок и site monitors.
- `app/Modules/Checks/Models/Check.php` - черновая модель, миграции под нее нет.
- `app/Modules/Dashboard`, `Notifications`, `RabbitMQ`, `Users` - каталоги без файлов.
- `routes/web.php` - landing `/` и `/dashboard`.
- Модульные routes подключаются через service providers в `bootstrap/providers.php`.

Существующие миграции:

- Laravel defaults: `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`.
- `organizations`.
- `organization_users`.
- `folders`.
- `sites`.
- `site_monitors`.

Существующие feature tests:

- `tests/Feature/Auth/RegisterTest.php` - регистрация создает пользователя и организацию.
- `tests/Feature/Sites/CreateSiteTest.php` - создание сайта создает default HTTP monitor с путем из URL.

## 2. Текущие сущности и целевые модули

| Сейчас | Целевой модуль | Целевое имя/роль | Решение |
| --- | --- | --- | --- |
| `App\Models\User` | `Identity` | `User` | Оставить физически в `App\Models` на MVP или перенести позже в `Modules/Identity/Infrastructure/Persistence/Models`; не смешивать с бизнес-логикой. |
| `Modules/Auth` | `Identity` | Authentication presentation/application | Перенести в `Modules/Identity`; `Auth` как отдельный модуль удалить после переноса. |
| `Application/Onboarding/Actions/CreateAccount` | `Identity` + `Organizations` orchestration | `RegisterAccountHandler` или `CreateAccountHandler` | Переписать как application handler, который вызывает команды Identity/Organizations/Projects. |
| `Organizations\Models\Organization` | `Identity` или `Organizations` boundary | `Organization` | Оставить, но перенести в целевую структуру `Modules/Identity` или отдельный `Organizations` submodule. В списке AGENTS отдельного `Organizations` модуля нет, но концепт есть; практично держать в `Identity` до появления team roles. |
| `Organizations\Models\OrganizationUser` | `Identity` | membership pivot | Оставить как pivot/model, привести enum casts и timestamps. |
| `OrganizationPlan` enum | `Billing` | plan code | Перенести в `Billing` или заменить связью с таблицей `plans`. |
| `OrganizationRole`, `OrganizationStatus` | `Identity` | membership/status enums | Оставить, перенести в `Identity/Domain` или `Identity/Infrastructure`. |
| `Sites\Models\Folder` | `Projects` | `Project` | Переименовать `folders` в `projects`; модель `Folder` заменить на `Project`. |
| `Sites\Models\Site` | `MonitoredResources` | `MonitoredResource` | Переименовать `sites` в `monitored_resources`; ресурс должен поддерживать `type=website/domain`, а не только сайт. |
| `Sites\Models\SiteMonitor` | `Monitoring` | `Monitor` | Переименовать `site_monitors` в generic `monitors`; добавить `organization_id`, `project_id`, `monitored_resource_id`, `status`, `expected`, counters и timestamps состояния. |
| `Sites\Enums\MonitorType` | `CheckTypes` | check type definitions | Удалить enum как источник бизнес-логики; заменить registry pattern и definition classes `HttpCheck`, `SslCheck`, `DomainCheck`. |
| `Sites\Enums\SiteStatus` | `MonitoredResources`/`Monitoring` | resource/monitor state | Переписать: статусы ресурса и монитора разделить. |
| `Sites\Http\Requests\SaveMonitorRequest` | `CheckTypes` + `Monitoring` | validation via registry | Переписать: общие поля валидирует Monitoring request, type-specific settings/expected валидируют check type definitions. |
| `Sites\Actions\CreateSiteAction` | `MonitoredResources` + `Monitoring` | create resource + default monitor | Разделить на команды: create monitored resource, create monitor. Автоматический HTTP-monitor можно оставить как onboarding/application сценарий. |
| `Sites\Actions\CreateMonitorAction` | `Monitoring` | create monitor handler | Перенести и переписать через command/handler + repository + check type registry. |
| `Sites\Actions\UpdateMonitorAction` | `Monitoring` | update monitor handler | Перенести, добавить нормализацию settings/expected через registry. |
| `Sites\Actions\ToggleMonitorAction` | `Monitoring` | pause/resume monitor | Переименовать в `PauseMonitor`/`ResumeMonitor` или `SetMonitorEnabled`. |
| `Sites\Actions\DeleteMonitorAction` | `Monitoring` | archive/delete monitor | Перенести, предпочтительно soft delete. |
| `Sites\Actions\GetCurrentOrganization` | `Identity`/`Shared` | current organization resolver | Перенести из `Sites`, так как это не ответственность сайтов. |
| `Checks\Models\Check` | none | duplicate draft | Удалить после создания `Monitoring\Models\Monitor`/`CheckResult`; сейчас не используется и не имеет таблицы. |
| Empty `Notifications` module | `Notifications` | future channels/rules/logs | Оставить каталог только если будет наполнен в отдельном этапе; иначе создать заново при реализации notifications. |
| Empty `RabbitMQ` module | `WorkerGateway` | poller transport later | Удалить или заменить на `WorkerGateway`; RabbitMQ не является MVP-решением из AGENTS. |
| Empty `Dashboard`, `Users` modules | `Identity`/Presentation | views/controllers later | Удалить пустые каталоги или не переносить в целевую структуру. |

## 3. Что оставить, перенести, переименовать, удалить, переписать

### Оставить как есть на первом шаге

- Корневую структуру репозитория.
- Docker-related файлы.
- Laravel default migrations для users/cache/jobs/sessions.
- `UserFactory`, базовые auth tests.
- Inertia/Vue страницы как временный presentation слой, пока backend schema стабилизируется.

### Перенести

- `Modules/Auth/*` -> `Modules/Identity/Presentation/Http`, `Modules/Identity/Application`, `Modules/Identity/Application/DTO`.
- `Modules/Organizations/*` -> `Modules/Identity` или отдельный внутренний namespace `Modules/Identity/Organizations`.
- `GetCurrentOrganization` -> `Identity/Application/Services/CurrentOrganizationResolver`.
- Site monitor actions -> `Modules/Monitoring/Application/Commands|Handlers`.
- Site resource creation actions -> `Modules/MonitoredResources/Application/Commands|Handlers`.

### Переименовать

- `Sites` module -> split into `Projects`, `MonitoredResources`, `Monitoring`.
- `Folder` -> `Project`.
- `folders` table -> `projects`.
- `Site` -> `MonitoredResource`.
- `sites` table -> `monitored_resources`.
- `SiteMonitor` -> `Monitor`.
- `site_monitors` table -> `monitors`.
- UI route names can remain `/sites` temporarily for product language, but backend names should move to resources/monitors.

### Удалить

- `Modules/Checks/Models/Check.php`, если не появится отдельная таблица `checks`. Целевая сущность - `check_results`.
- Empty modules `Dashboard`, `RabbitMQ`, `Users`; `Notifications` можно удалить и создать заново в правильной структуре на этапе notifications.
- `MonitorType` enum как главный механизм расширения типов проверок. Его заменяет `CheckTypeRegistry`.

### Переписать

- `SaveMonitorRequest`: убрать match по enum, использовать registry.
- `CreateDefaultHttpMonitorAction`: использовать `HttpCheckDefinition::defaultSettings/defaultExpected`.
- `CreateSiteAction`: разделить создание monitored resource и monitor.
- `IndexController` и `SiteMonitorController`: сделать тоньше, вынести read mapping в queries/resources.
- `CreateAccount`: оформить как application use case с явными commands/handlers.

## 4. Целевая схема БД

Так как проект не в production, старые миграции можно удалить и заменить чистыми миграциями. Рекомендуемый минимальный набор для ближайшего рефакторинга:

- `users` - оставить Laravel default.
- `organizations` - оставить, но убрать `plan` из организации после появления `subscriptions`; временно можно оставить `plan_code`.
- `organization_users` - оставить.
- `projects` - заменить `folders`; поля: `id`, `organization_id`, `name`, `slug/null`, `color/null`, `is_default`, `sort_order`, timestamps, soft deletes опционально.
- `monitored_resources` - заменить `sites`; поля: `id`, `organization_id`, `project_id`, `created_user_id`, `type`, `name`, `target`, `scheme/null`, `host/null`, `port/null`, `path/null`, `status`, `notes/null`, timestamps, soft deletes.
- `monitors` - заменить `site_monitors`; поля из AGENTS: `organization_id`, `project_id`, `monitored_resource_id`, `type`, `name`, `enabled`, `status`, `interval_seconds`, `timeout_ms`, `settings`, `expected`, `last_check_at`, `next_check_at`, `last_success_at`, `last_failure_at`, `consecutive_successes`, `consecutive_failures`, timestamps, soft deletes.
- `check_results` - новая таблица для результатов poller/manual checks.
- `monitor_state_changes` - новая таблица для истории статусов.
- `incidents`, `incident_comments` - новые таблицы, но лучше отдельным этапом после `check_results`.
- `notification_channels`, `notification_rules`, `notification_logs` - отдельный этап после incidents.
- `plans`, `plan_limits`, `subscriptions`, `payments` - отдельный Billing этап; для MVP можно начать с `plans`/`plan_limits` seed data.
- `outbox_messages` - добавить перед интеграцией с poller/notifications, не в первом рефакторинге.

Важно: `monitors.settings` и `monitors.expected` должны быть generic JSON. Не создавать `http_monitors`, `ssl_monitors`, `domain_monitors`.

## 5. Целевая структура модулей

```text
apps/web/app/
  Shared/
  Modules/
    Identity/
    Billing/
    Projects/
    MonitoredResources/
    Monitoring/
    CheckTypes/
      HttpCheck/
      SslCheck/
      DomainCheck/
    Incidents/
    Notifications/
    WorkerGateway/
```

На раннем этапе допустимо держать Eloquent models в `Infrastructure/Persistence/Models`, а application команды и handlers - в `Application/Commands` и `Application/Handlers`. Главное - не класть бизнес-правила в controllers и не собирать новый `MonitorService`.

## 6. Поэтапный безопасный план

### Этап 0. Зафиксировать baseline

- Запустить текущие Laravel tests и сохранить список падающих/проходящих тестов.
- Не менять Docker.
- Не менять Go poller.
- Убедиться, что service providers подключаются через `bootstrap/providers.php`.

Результат: понятный baseline перед переносами.

### Этап 1. Создать целевые пустые модули и Shared contracts

- Создать каталоги целевых модулей.
- Добавить module service providers без изменения поведения.
- Добавить `CheckTypeDefinitionInterface` и `CheckTypeRegistry` в `CheckTypes` или `Shared/Domain`.
- Зарегистрировать MVP definitions: `http`, `ssl`, `domain`.
- Покрыть definitions unit tests.

Результат: новая архитектурная рамка существует, старый код еще работает.

### Этап 2. Чистые миграции под новую БД

- Заменить миграции `folders`, `sites`, `site_monitors` на `projects`, `monitored_resources`, `monitors`.
- Добавить `check_results` и `monitor_state_changes`.
- Пока не добавлять полную notifications/billing/incidents реализацию, если она не нужна для прохождения текущих сценариев.
- Обновить factories/test setup.

Результат: БД соответствует MVP-схеме AGENTS.

### Этап 3. Перенести Projects

- Перенести `Folder` в `Projects` как `Project`.
- Перенести create default folder logic как default project logic.
- Обновить регистрацию аккаунта: создавать default project.
- Обновить тест регистрации.

Результат: `folders` больше не является бизнес-термином backend.

### Этап 4. Перенести MonitoredResources

- Переписать `Site` как `MonitoredResource`.
- Вынести URL normalization из `StoreSiteRequest` в application/domain service или value object.
- Сохранить текущий UI `/sites` как presentation alias, чтобы не делать лишний frontend-рефакторинг.
- Добавить типы ресурса `website` и `domain`, но не добавлять новую функциональность сверх текущего создания website.

Результат: создание сайта становится созданием monitored resource.

### Этап 5. Перенести Monitoring

- Переписать `SiteMonitor` как `Monitor`.
- Перенести create/update/toggle/delete actions в command/handler flow.
- Добавить `expected` JSON и monitor state поля.
- Заменить `is_enabled` на `enabled`.
- Настройки и expected значения нормализовать через `CheckTypeRegistry`.
- Убрать `MonitorType` enum из request validation.

Результат: monitoring core становится generic и готовым к HTTP/SSL/domain без отдельных таблиц.

### Этап 6. Добавить CheckResults без poller-интеграции

- Создать model/repository для `check_results`.
- Добавить application handler для сохранения normalized result.
- Добавить status resolver через check type definition.
- Пока не открывать incidents и не отправлять notifications.

Результат: Laravel умеет принимать и хранить результаты проверок.

### Этап 7. WorkerGateway internal API contracts

- Добавить routes/controllers для internal API:
  - `GET /internal/monitors/due`
  - `POST /internal/check-results`
  - `POST /internal/manual-checks`
- Использовать DTO/resources для stable payload contracts.
- Добавить auth mechanism для internal endpoints отдельно от user session.
- Обновить `docs/api/internal-api.md`.

Результат: Go poller сможет интегрироваться без знания Laravel internals.

### Этап 8. Incidents

- Добавить incident domain service с правилами consecutive failures/successes.
- Открывать incident после 2-3 failures, закрывать после 1-2 successes.
- Добавить события `IncidentOpened`, `IncidentResolved`.
- Покрыть unit tests.

Результат: failures перестают быть равны incidents.

### Этап 9. Notifications

- Создать channels/rules/logs.
- Реализовать listeners на incident events и expiration warning events.
- Сначала email/telegram MVP, без webhook/SMS/Slack.

Результат: notifications живут отдельно от Monitoring и Incidents.

### Этап 10. Billing

- Создать `plans`, `plan_limits`, `subscriptions`.
- Добавить `LimitChecker` в Billing.
- Проверять лимиты перед create monitor, enable monitor, interval change, manual check, notification channel create.

Результат: тарифные правила не размазаны по controllers.

## 7. Риски и порядок миграции UI

- Текущие Vue pages завязаны на route names и payload keys `sites`, `site`, `monitors`. На backend-рефакторинге можно сохранить UI naming `/sites`, но внутри отдавать данные из `MonitoredResource`.
- Самый рискованный переход - одновременная замена migrations, models и routes. Его нужно делать по одному вертикальному сценарию: registration -> default project -> create resource -> create monitor -> show resource.
- Так как production данных нет, data migration не нужна. Но tests должны описывать новую схему, чтобы не тащить старые названия.
- Не добавлять DNS/Ping/PageSpeed и другие future check types в этом рефакторинге. В текущем `MonitorType` есть `ping` и `dns`, но по AGENTS MVP - только `http`, `ssl`, `domain`.

## 8. Документация, которую обновить во время реализации

- `docs/architecture/overview.md` - общая архитектура Laravel + Go.
- `docs/architecture/modules.md` - границы модулей.
- `docs/architecture/monitoring-flow.md` - создание monitor, scheduled/manual checks, status update.
- `docs/architecture/database.md` - новая схема таблиц.
- `docs/api/internal-api.md` - контракты WorkerGateway.
- `docs/product/mvp.md` - что входит в MVP и что отложено.
- `docs/product/tariffs.md` - после этапа Billing.

## 9. Definition of done для полного рефакторинга

- Старый `Sites` module удален или оставлен только как presentation alias без domain logic.
- В backend используются `Projects`, `MonitoredResources`, `Monitoring`, `CheckTypes`.
- `monitors` generic table содержит `settings` и `expected` JSON.
- Check types добавляются через registry, а не через правку monitoring core.
- Controllers тонкие: request -> command/query -> handler -> resource/response.
- Tests покрывают регистрацию, создание resource, создание monitor, check type validation, сохранение check result и incident rules.
- Документация отражает фактические endpoints и таблицы.
