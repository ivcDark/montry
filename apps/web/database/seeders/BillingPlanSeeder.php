<?php

namespace Database\Seeders;

use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Monitoring\Application\Services\MonitorTypeCatalog;
use Illuminate\Database\Seeder;

final class BillingPlanSeeder extends Seeder
{
    public function run(): void
    {
        $allMonitorTypes = app(MonitorTypeCatalog::class)->allCodes();

        Plan::query()
            ->where('code', 'plus')
            ->whereDoesntHave('subscriptions')
            ->delete();

        Plan::query()
            ->where('code', 'plus')
            ->update([
                'code' => 'team',
                'name' => 'Team',
            ]);

        $plans = [
            'free' => [
                'name' => 'Free',
                'description' => 'Базовый мониторинг доступности сайта и SSL.',
                'price_cents' => 0,
                'sort_order' => 10,
                'limits' => [
                    'max_sites' => ['limit' => null],
                    'max_monitors' => ['limit' => 5],
                    'allowed_monitor_types' => ['types' => ['http', 'ssl']],
                    'history_retention_days' => ['days' => 7],
                    'minimum_check_interval_seconds' => ['seconds' => 300],
                    'notification_channels' => ['channels' => ['email']],
                    'can_create_projects' => ['enabled' => false],
                ],
            ],
            'pro' => [
                'name' => 'Pro',
                'description' => 'До 100 активных мониторингов со всеми типами проверок.',
                'price_cents' => 59000,
                'sort_order' => 20,
                'limits' => [
                    'max_sites' => ['limit' => null],
                    'max_monitors' => ['limit' => 100],
                    'allowed_monitor_types' => ['types' => $allMonitorTypes],
                    'history_retention_days' => ['days' => 30],
                    'minimum_check_interval_seconds' => ['seconds' => 60],
                    'notification_channels' => ['channels' => ['email', 'telegram']],
                    'can_create_projects' => ['enabled' => true],
                ],
            ],
            'team' => [
                'name' => 'Team',
                'description' => 'До 500 активных мониторингов и командная работа.',
                'price_cents' => 149000,
                'sort_order' => 30,
                'limits' => [
                    'max_sites' => ['limit' => null],
                    'max_monitors' => ['limit' => 500],
                    'allowed_monitor_types' => ['types' => $allMonitorTypes],
                    'history_retention_days' => ['days' => 90],
                    'minimum_check_interval_seconds' => ['seconds' => 60],
                    'notification_channels' => ['channels' => ['email', 'telegram']],
                    'can_create_projects' => ['enabled' => true],
                ],
            ],
        ];

        foreach ($plans as $code => $data) {
            $plan = Plan::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'price_cents' => $data['price_cents'],
                    'currency' => 'RUB',
                    'is_active' => true,
                    'sort_order' => $data['sort_order'],
                ],
            );

            foreach ($data['limits'] as $key => $value) {
                $plan->limits()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $value],
                );
            }
        }
    }
}
