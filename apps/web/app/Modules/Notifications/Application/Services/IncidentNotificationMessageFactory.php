<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Incidents\Infrastructure\Persistence\Models\Incident;
use App\Modules\Notifications\Application\DTO\NotificationMessage;

final class IncidentNotificationMessageFactory
{
    public function opened(Incident $incident): NotificationMessage
    {
        $incident->loadMissing(['monitor.monitoredResource', 'openedByCheckResult']);

        $details = $this->details($incident);
        $error = $this->error($incident);

        return new NotificationMessage(
            eventType: 'incident.opened',
            subject: "Проблема на сайте: {$details['resource_name']}",
            body: implode("\n", [
                '🔴 Обнаружена проблема',
                '',
                "Сайт: {$details['resource_name']}",
                "Адрес: {$details['target']}",
                "Тип мониторинга: {$details['monitor_type_label']}",
                "Ошибка: {$error}",
                "Описание: {$details['description']}",
                "Время: {$this->formatDate($incident->started_at)}",
            ]),
            payload: [
                ...$details,
                'incident_id' => $incident->id,
                'monitor_id' => $incident->monitor_id,
                'title' => $incident->title,
                'summary' => $incident->summary,
                'error' => $error,
                'status' => $incident->status,
                'started_at' => $incident->started_at?->toIso8601String(),
                'started_at_formatted' => $this->formatDate($incident->started_at),
            ],
            organizationId: $incident->organization_id,
            incidentId: $incident->id,
        );
    }

    public function resolved(Incident $incident): NotificationMessage
    {
        $incident->loadMissing(['monitor.monitoredResource']);

        $details = $this->details($incident);
        $duration = $this->formatDuration((int) $incident->duration_seconds);

        return new NotificationMessage(
            eventType: 'incident.resolved',
            subject: "Мониторинг восстановлен: {$details['resource_name']}",
            body: implode("\n", [
                '🟢 Мониторинг восстановлен',
                '',
                "Сайт: {$details['resource_name']}",
                "Адрес: {$details['target']}",
                "Тип мониторинга: {$details['monitor_type_label']}",
                'Описание: проверка снова проходит успешно, инцидент закрыт.',
                "Время восстановления: {$this->formatDate($incident->resolved_at)}",
                "Длительность сбоя: {$duration}",
            ]),
            payload: [
                ...$details,
                'incident_id' => $incident->id,
                'monitor_id' => $incident->monitor_id,
                'status' => $incident->status,
                'resolved_at' => $incident->resolved_at?->toIso8601String(),
                'resolved_at_formatted' => $this->formatDate($incident->resolved_at),
                'duration_seconds' => $incident->duration_seconds,
                'duration_formatted' => $duration,
            ],
            organizationId: $incident->organization_id,
            incidentId: $incident->id,
        );
    }

    /**
     * @return array{
     *     resource_name: string,
     *     target: string,
     *     monitor_name: string,
     *     monitor_type: string,
     *     monitor_type_label: string,
     *     description: string
     * }
     */
    private function details(Incident $incident): array
    {
        $monitor = $incident->monitor;
        $resource = $monitor?->monitoredResource;
        $type = (string) ($monitor?->type ?? 'unknown');

        return [
            'resource_name' => $resource?->name ?: $resource?->target ?: 'Неизвестный сайт',
            'target' => $resource?->target ?: $this->targetFromSettings($monitor?->settings ?? []),
            'monitor_name' => $monitor?->name ?: 'Мониторинг',
            'monitor_type' => $type,
            'monitor_type_label' => $this->typeLabel($type),
            'description' => $this->typeDescription($type),
        ];
    }

    private function error(Incident $incident): string
    {
        $checkResult = $incident->openedByCheckResult;
        $message = trim((string) ($checkResult?->error_message ?: $incident->summary));

        if ($message !== '') {
            return $message;
        }

        if ($checkResult?->status_code !== null) {
            return "Получен HTTP-статус {$checkResult->status_code}.";
        }

        return 'Проверка завершилась с ошибкой без дополнительного описания.';
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function targetFromSettings(array $settings): string
    {
        foreach (['url', 'domain', 'host'] as $key) {
            $value = $settings[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return 'Адрес не указан';
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'http' => 'Доступность сайта (HTTP/HTTPS)',
            'ssl' => 'SSL-сертификат',
            'domain' => 'Срок регистрации домена',
            'dns' => 'DNS',
            'tcp_port' => 'TCP-порт',
            'api_endpoint' => 'API endpoint',
            'robots_txt' => 'robots.txt',
            'sitemap_xml' => 'sitemap.xml',
            default => strtoupper($type),
        };
    }

    private function typeDescription(string $type): string
    {
        return match ($type) {
            'http' => 'Сайт не ответил корректно или вернул неожиданный HTTP-статус.',
            'ssl' => 'Не удалось подтвердить корректность или срок действия SSL-сертификата.',
            'domain' => 'Не удалось проверить срок регистрации домена.',
            'dns' => 'Не удалось корректно разрешить DNS-записи.',
            'tcp_port' => 'Не удалось установить соединение с указанным TCP-портом.',
            'api_endpoint' => 'API не ответил согласно заданным условиям.',
            'robots_txt' => 'Файл robots.txt недоступен или содержит некорректный ответ.',
            'sitemap_xml' => 'Файл sitemap.xml недоступен или содержит некорректный ответ.',
            default => 'Проверка не прошла согласно заданным условиям мониторинга.',
        };
    }

    private function formatDate(mixed $date): string
    {
        return $date?->format('d.m.Y H:i:s') ?? 'неизвестно';
    }

    private function formatDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;
        $parts = [];

        if ($hours > 0) {
            $parts[] = "{$hours} ч";
        }

        if ($minutes > 0) {
            $parts[] = "{$minutes} мин";
        }

        if ($remainingSeconds > 0 || $parts === []) {
            $parts[] = "{$remainingSeconds} сек";
        }

        return implode(' ', $parts);
    }
}
