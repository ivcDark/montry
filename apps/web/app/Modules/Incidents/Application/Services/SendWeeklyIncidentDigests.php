<?php

namespace App\Modules\Incidents\Application\Services;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Incidents\Application\Mail\WeeklyIncidentDigestMail;
use App\Modules\Incidents\Infrastructure\Persistence\Models\IncidentWeeklyDigestLog;
use App\Modules\Incidents\Infrastructure\Persistence\Models\IncidentWeeklyDigestPreference;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class SendWeeklyIncidentDigests
{
    public function handle(?CarbonImmutable $now = null): int
    {
        $now = ($now ?? CarbonImmutable::now('Europe/Moscow'))->setTimezone('Europe/Moscow');

        if (! $now->isMonday()) {
            return 0;
        }

        $sent = 0;
        $weekStart = $now->subWeek()->startOfWeek();
        $weekEnd = $weekStart->endOfWeek();
        $weekStartKey = $weekStart->startOfDay()->toDateTimeString();
        $weekEndKey = $weekEnd->startOfDay()->toDateTimeString();

        foreach ($this->eligibleRecipients() as $recipient) {
            $preference = IncidentWeeklyDigestPreference::query()
                ->where('user_id', $recipient->user_id)
                ->where('organization_id', $recipient->organization_id)
                ->first();

            $enabled = $preference?->enabled ?? true;
            $sendTime = substr((string) ($preference?->send_time ?? '09:00'), 0, 5);

            if (! $enabled || $sendTime > $now->format('H:i')) {
                continue;
            }

            $log = IncidentWeeklyDigestLog::query()->firstOrCreate(
                [
                    'user_id' => $recipient->user_id,
                    'organization_id' => $recipient->organization_id,
                    'week_start_date' => $weekStartKey,
                ],
                [
                    'week_end_date' => $weekEndKey,
                    'status' => 'pending',
                ],
            );

            if (! $log->wasRecentlyCreated || $log->status === 'sent') {
                continue;
            }

            try {
                $incidents = $this->incidentRows((int) $recipient->organization_id, $weekStart, $weekEnd);

                Mail::to((string) $recipient->email)->queue(new WeeklyIncidentDigestMail(
                    organizationName: (string) $recipient->organization_name,
                    weekStart: $weekStart,
                    weekEnd: $weekEnd,
                    incidentCount: count($incidents),
                    incidents: $incidents,
                ));

                $log->forceFill([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'error_message' => null,
                ])->save();

                $sent++;
            } catch (Throwable $exception) {
                $log->forceFill([
                    'status' => 'failed',
                    'error_message' => $exception->getMessage(),
                ])->save();
            }
        }

        return $sent;
    }

    /**
     * @return iterable<int, object{user_id:int,email:string,organization_id:int,organization_name:string}>
     */
    private function eligibleRecipients(): iterable
    {
        return DB::table('organization_users')
            ->join('users', 'users.id', '=', 'organization_users.user_id')
            ->join('organizations', 'organizations.id', '=', 'organization_users.organization_id')
            ->where('organization_users.status', 'active')
            ->where('users.is_blocked', false)
            ->whereExists(function (Builder $query): void {
                $query
                    ->selectRaw('1')
                    ->from('subscriptions')
                    ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
                    ->whereColumn('subscriptions.organization_id', 'organization_users.organization_id')
                    ->where('subscriptions.status', 'active')
                    ->where('subscriptions.starts_at', '<=', now())
                    ->where(function (Builder $query): void {
                        $query->whereNull('subscriptions.ends_at')->orWhere('subscriptions.ends_at', '>', now());
                    })
                    ->whereIn('plans.code', ['pro', 'plus']);
            })
            ->select([
                'users.id as user_id',
                'users.email',
                'organizations.id as organization_id',
                'organizations.name as organization_name',
            ])
            ->orderBy('organizations.id')
            ->orderBy('users.id')
            ->cursor();
    }

    /**
     * @return array<int, array{site:string,type:string,started_at:string,duration:string}>
     */
    private function incidentRows(int $organizationId, CarbonImmutable $weekStart, CarbonImmutable $weekEnd): array
    {
        return DB::table('incidents')
            ->join('monitors', 'monitors.id', '=', 'incidents.monitor_id')
            ->join('monitored_resources', 'monitored_resources.id', '=', 'incidents.monitored_resource_id')
            ->where('incidents.organization_id', $organizationId)
            ->where('incidents.severity', '!=', 'warning')
            ->whereBetween('incidents.started_at', [$weekStart->utc(), $weekEnd->utc()])
            ->orderBy('incidents.started_at')
            ->get([
                'monitored_resources.host',
                'monitored_resources.name',
                'monitors.type',
                'incidents.started_at',
                'incidents.duration_seconds',
            ])
            ->map(fn (object $row): array => [
                'site' => (string) ($row->host ?? $row->name ?? 'Без сайта'),
                'type' => $this->typeLabel((string) $row->type),
                'started_at' => CarbonImmutable::parse($row->started_at)->setTimezone('Europe/Moscow')->format('d.m.Y H:i'),
                'duration' => $this->duration((int) ($row->duration_seconds ?? 0)),
            ])
            ->all();
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'http' => 'HTTP',
            'ssl' => 'SSL',
            'domain' => 'Domain',
            default => mb_strtoupper($type),
        };
    }

    private function duration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '—';
        }

        if ($seconds < 60) {
            return "{$seconds} сек";
        }

        if ($seconds < 3600) {
            return round($seconds / 60).' мин';
        }

        if ($seconds < 86400) {
            return round($seconds / 3600).' ч';
        }

        return round($seconds / 86400).' дн';
    }
}
