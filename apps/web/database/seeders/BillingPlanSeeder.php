<?php

namespace Database\Seeders;

use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use Illuminate\Database\Seeder;

final class BillingPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            'free' => [
                'name' => 'Free',
                'description' => 'Базовый мониторинг для первых сайтов.',
                'price_cents' => 0,
                'sort_order' => 10,
                'limits' => [
                    'max_sites' => ['limit' => 3],
                    'max_monitors' => ['limit' => 6],
                    'allowed_monitor_types' => ['types' => ['http', 'ssl']],
                    'history_retention_days' => ['days' => 3],
                    'minimum_check_interval_seconds' => ['seconds' => 900],
                    'notification_channels' => ['channels' => ['email']],
                    'can_create_projects' => ['enabled' => false],
                ],
            ],
            'pro' => [
                'name' => 'Pro',
                'description' => 'Мониторинг сайтов, SSL и доменов для малого портфеля.',
                'price_cents' => 99000,
                'sort_order' => 20,
                'limits' => [
                    'max_sites' => ['limit' => 15],
                    'max_monitors' => ['limit' => 35],
                    'allowed_monitor_types' => ['types' => ['http', 'ssl', 'domain']],
                    'history_retention_days' => ['days' => 14],
                    'minimum_check_interval_seconds' => ['seconds' => 300],
                    'notification_channels' => ['channels' => ['email', 'telegram']],
                    'can_create_projects' => ['enabled' => false],
                ],
            ],
            'plus' => [
                'name' => 'Plus',
                'description' => 'Расширенный мониторинг с проектами и длинной историей.',
                'price_cents' => 249000,
                'sort_order' => 30,
                'limits' => [
                    'max_sites' => ['limit' => 50],
                    'max_monitors' => ['limit' => null],
                    'allowed_monitor_types' => ['types' => ['*']],
                    'history_retention_days' => ['days' => 60],
                    'minimum_check_interval_seconds' => ['seconds' => 300],
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
