# Laravel Modules

Дата обновления: 2026-06-07.

`apps/web` развивается как modular monolith с DDD-lite границами. Новая целевая структура создана рядом с существующими рабочими модулями, чтобы не ломать текущие routes, controllers и Inertia/Vue страницы во время поэтапного рефакторинга.

## Базовая структура модуля

Каждый целевой модуль имеет одинаковую структуру:

```text
ModuleName/
  Domain/
  Application/
  Infrastructure/
    Providers/
  Presentation/
```

- `Domain` - сущности, value objects, domain events, policies и contracts модуля.
- `Application` - commands, handlers, queries, DTO и application services.
- `Infrastructure` - Eloquent models/repositories, providers, integrations и технические adapters.
- `Presentation` - HTTP controllers, form requests, resources, routes и другой входной слой.

## Целевые модули

- `Identity` - пользователи, аутентификация, организации, membership и текущая организация пользователя.
- `Billing` - планы, лимиты, подписки, платежи, платные мониторинги/add-ons и `LimitChecker`.
- `Projects` - группы monitored resources, текущая замена старого понятия `Folder`.
- `MonitoredResources` - сайты, домены и другие объекты мониторинга.
- `Monitoring` - generic monitors, состояние проверок, история результатов и orchestration вокруг мониторинга.
- `CheckTypes` - registry и definitions для базовых и платных типов проверок. Базовые типы после редизайна: доступность сайта, SSL, проверка домена, DNS мониторинг и наличие `robots.txt`. Платные типы/опции: `sitemap_xml`, `tcp_port`, `api_endpoint` и пакет `extra_5_sites` в Billing.
- `Incidents` - открытие/закрытие инцидентов и история деградаций.
- `Notifications` - каналы, правила и журналы уведомлений.
- `Reports` - будущие отчеты и выгрузки.
- `WorkerGateway` - внутренние API/DTO contracts между Laravel и Go poller.

## UI и продуктовые правила

Редизайн описан в `docs/product/redesign.md`, тарифы и paid add-ons - в `docs/product/tariffs.md`. При изменениях в Vue/Inertia-кабинете нужно сверяться с этими документами:

- кабинет должен быть компактным рабочим интерфейсом, без размашистых hero-блоков;
- настройка мониторингов должна быть рядом с конкретным сайтом/ресурсом;
- платные мониторинги должны показывать цену, количество и влияние на итоговую стоимость;
- все пользовательские действия должны давать понятный toast;
- новые check types можно добавлять в Laravel и UI до полной реализации Go poller, но заглушечные результаты не должны открывать incidents.

## Текущие legacy-модули

Пока остаются на месте:

- `Auth`
- `Organizations`
- `Sites`

Они будут переноситься маленькими этапами согласно `docs/refactoring/architecture-refactor-plan.md`. До переноса их service providers остаются зарегистрированными, чтобы текущие страницы `/login`, `/register`, `/sites` и связанные actions продолжили работать.

`Organizations\Models\Organization`, `Organizations\Models\OrganizationUser`, `Sites\Models\Folder`, `Sites\Models\Site` и `Sites\Models\SiteMonitor` являются временными совместимыми классами поверх новых моделей `Identity`, `Projects`, `MonitoredResources` и `Monitoring`. Новые use cases должны импортировать модели из целевых модулей, а legacy-классы нужны только для текущих routes/controllers до их отдельного переноса.

Пустые старые каталоги `Dashboard`, `RabbitMQ`, `Users` не развиваются дальше. Новые изменения нужно вносить в целевые модули, если задача явно не требует правки существующего legacy-кода.
