<?php

use App\Modules\Billing\Application\Services\ApplySubscriptionLimits;
use App\Modules\Billing\Application\Services\ProcessPastDueSubscriptions;
use App\Modules\Billing\Application\Services\SendSubscriptionRenewalReminders;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\Observability\Infrastructure\ClickHouse\ClickHouseBusinessEventExporter;
use App\Modules\Observability\Infrastructure\Persistence\Models\AnalyticsEventExport;
use App\Modules\Observability\Infrastructure\Persistence\Models\DeadLetter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('billing:send-renewal-reminders', function (SendSubscriptionRenewalReminders $reminders): int {
    $sent = $reminders->handle();

    $this->info("Sent {$sent} billing renewal reminders.");

    return self::SUCCESS;
})->purpose('Send paid billing renewal reminders before tariff expiration.');

Artisan::command('billing:process-past-due-subscriptions', function (ProcessPastDueSubscriptions $processor): int {
    $result = $processor->handle();

    $this->info(
        "Moved {$result['past_due']} subscriptions to past_due, sent {$result['warned']} warnings and switched {$result['switched_to_free']} subscriptions to free."
    );

    return self::SUCCESS;
})->purpose('Process paid subscription grace periods and switch unpaid accounts to free.');

Artisan::command('billing:expire-subscriptions', function (ProcessPastDueSubscriptions $processor): int {
    $result = $processor->handle();

    $this->info(
        "Moved {$result['past_due']} subscriptions to past_due, sent {$result['warned']} warnings and switched {$result['switched_to_free']} subscriptions to free."
    );

    return self::SUCCESS;
})->purpose('Compatibility alias for billing:process-past-due-subscriptions.');

Artisan::command('billing:activate-scheduled-subscriptions', function (ApplySubscriptionLimits $applySubscriptionLimits): int {
    $subscriptionIds = Subscription::query()
        ->where('status', 'scheduled')
        ->where('starts_at', '<=', now())
        ->orderBy('starts_at')
        ->pluck('id');

    $activated = 0;

    foreach ($subscriptionIds as $subscriptionId) {
        DB::transaction(function () use ($subscriptionId, $applySubscriptionLimits, &$activated): void {
            $subscription = Subscription::query()
                ->with('plan.limits')
                ->lockForUpdate()
                ->find($subscriptionId);

            if ($subscription === null || $subscription->status !== 'scheduled' || $subscription->starts_at->isFuture()) {
                return;
            }

            Subscription::query()
                ->where('organization_id', $subscription->organization_id)
                ->where('id', '!=', $subscription->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'replaced',
                    'ends_at' => $subscription->starts_at,
                ]);

            if ($subscription->plan->code === 'free' || $subscription->plan->price_cents === 0) {
                $subscription->forceFill([
                    'status' => 'active',
                ])->save();

                $applySubscriptionLimits->handle($subscription->organization_id, $subscription->plan);

                $activated++;

                return;
            }

            $subscription->forceFill([
                'status' => 'past_due',
                'ends_at' => $subscription->starts_at,
            ])->save();

            $activated++;
        });
    }

    $this->info("Activated {$activated} scheduled subscriptions.");

    return self::SUCCESS;
})->purpose('Activate scheduled billing downgrades and apply new plan limits.');

Artisan::command('observability:export-business-events {--batch= : Maximum business events to export}', function (ClickHouseBusinessEventExporter $exporter): int {
    $batch = $this->option('batch');
    $result = $exporter->export($batch !== null ? (int) $batch : null);

    $this->info("Selected {$result['selected']} business events, exported {$result['exported']}, failed {$result['failed']}.");

    return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
})->purpose('Export business events from PostgreSQL to ClickHouse analytics storage.');

Artisan::command('observability:retry-dead-letter {id? : Dead-letter id to retry} {--all : Retry all open recoverable dead letters}', function (ClickHouseBusinessEventExporter $exporter): int {
    $id = $this->argument('id');
    $all = (bool) $this->option('all');

    if (! $all && $id === null) {
        $this->error('Pass a dead-letter id or use --all.');

        return self::FAILURE;
    }

    $query = DeadLetter::query()
        ->where('status', DeadLetter::STATUS_OPEN)
        ->where('recoverable', true)
        ->orderBy('failed_at');

    if (! $all) {
        $query->whereKey((int) $id);
    }

    $deadLetters = $query->get();

    if ($deadLetters->isEmpty()) {
        $this->info('No open recoverable dead letters found.');

        return self::SUCCESS;
    }

    $clickHouseExportIds = [];
    $unsupported = 0;

    foreach ($deadLetters as $deadLetter) {
        if ($deadLetter->source === 'clickhouse' && $deadLetter->type === 'business_event_export') {
            $exportId = (int) ($deadLetter->payload['analytics_event_export_id'] ?? 0);

            if ($exportId <= 0) {
                $unsupported++;

                continue;
            }

            AnalyticsEventExport::query()
                ->whereKey($exportId)
                ->update([
                    'status' => AnalyticsEventExport::STATUS_PENDING,
                    'attempts' => 0,
                    'last_error' => null,
                    'last_attempted_at' => null,
                    'updated_at' => now(),
                ]);

            $deadLetter->forceFill([
                'status' => DeadLetter::STATUS_RETRYING,
                'last_retry_at' => now(),
            ])->save();

            $clickHouseExportIds[$deadLetter->id] = $exportId;

            continue;
        }

        $unsupported++;
    }

    if ($clickHouseExportIds !== []) {
        $exporter->export(count($clickHouseExportIds));

        foreach ($clickHouseExportIds as $deadLetterId => $exportId) {
            $export = AnalyticsEventExport::query()->find($exportId);
            $deadLetter = DeadLetter::query()->find($deadLetterId);

            if ($deadLetter === null) {
                continue;
            }

            $deadLetter->forceFill([
                'status' => $export?->status === AnalyticsEventExport::STATUS_EXPORTED
                    ? DeadLetter::STATUS_RESOLVED
                    : DeadLetter::STATUS_OPEN,
                'resolved_at' => $export?->status === AnalyticsEventExport::STATUS_EXPORTED ? now() : null,
            ])->save();
        }
    }

    $this->info('Queued retry for '.count($clickHouseExportIds).' ClickHouse dead letters.');

    if ($unsupported > 0) {
        $this->warn("Skipped {$unsupported} dead letters without an automatic retry handler.");
    }

    return self::SUCCESS;
})->purpose('Retry recoverable observability dead-letter records.');

Artisan::command('observability:test-sentry', function (): int {
    if (! config('sentry.dsn')) {
        $this->warn('Sentry is disabled. Set SENTRY_DSN or SENTRY_LARAVEL_DSN.');

        return self::SUCCESS;
    }

    \Sentry\captureException(new RuntimeException('Controlled Montry Laravel Sentry test exception.'));
    $this->info('Controlled Sentry test exception submitted.');

    return self::SUCCESS;
})->purpose('Submit a controlled Laravel exception event to Sentry.');

Schedule::command('billing:send-renewal-reminders')->dailyAt('09:00');
Schedule::command('billing:activate-scheduled-subscriptions')->dailyAt('09:10');
Schedule::command('billing:process-past-due-subscriptions')->dailyAt('09:20');
Schedule::command('observability:export-business-events')->everyMinute();
