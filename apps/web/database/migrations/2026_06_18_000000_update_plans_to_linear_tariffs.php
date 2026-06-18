<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $allTypes = ['http', 'ssl', 'domain', 'dns', 'robots_txt', 'sitemap_xml', 'api_endpoint', 'tcp_port'];

            $this->updatePlan('free', [
                'name' => 'Free',
                'description' => 'Базовый мониторинг доступности сайта и SSL.',
                'price_cents' => 0,
                'sort_order' => 10,
            ], [
                'max_sites' => ['limit' => null],
                'max_monitors' => ['limit' => 5],
                'allowed_monitor_types' => ['types' => ['http', 'ssl']],
                'history_retention_days' => ['days' => 7],
                'minimum_check_interval_seconds' => ['seconds' => 300],
                'notification_channels' => ['channels' => ['email']],
                'can_create_projects' => ['enabled' => false],
            ]);

            $this->updatePlan('pro', [
                'name' => 'Pro',
                'description' => 'До 100 активных мониторингов со всеми типами проверок.',
                'price_cents' => 59000,
                'sort_order' => 20,
            ], [
                'max_sites' => ['limit' => null],
                'max_monitors' => ['limit' => 100],
                'allowed_monitor_types' => ['types' => $allTypes],
                'history_retention_days' => ['days' => 30],
                'minimum_check_interval_seconds' => ['seconds' => 60],
                'notification_channels' => ['channels' => ['email', 'telegram']],
                'can_create_projects' => ['enabled' => true],
            ]);

            DB::table('plans')->where('code', 'plus')->update([
                'code' => 'team',
                'name' => 'Team',
                'description' => 'До 500 активных мониторингов и командная работа.',
                'price_cents' => 149000,
                'sort_order' => 30,
                'updated_at' => now(),
            ]);

            $this->updatePlan('team', [
                'name' => 'Team',
                'description' => 'До 500 активных мониторингов и командная работа.',
                'price_cents' => 149000,
                'sort_order' => 30,
            ], [
                'max_sites' => ['limit' => null],
                'max_monitors' => ['limit' => 500],
                'allowed_monitor_types' => ['types' => $allTypes],
                'history_retention_days' => ['days' => 90],
                'minimum_check_interval_seconds' => ['seconds' => 60],
                'notification_channels' => ['channels' => ['email', 'telegram']],
                'can_create_projects' => ['enabled' => true],
            ]);
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $allTypes = ['http', 'ssl', 'domain', 'dns', 'robots_txt', 'sitemap_xml', 'api_endpoint', 'tcp_port'];

            $this->updatePlan('free', [
                'name' => 'Free',
                'description' => 'Базовый мониторинг для первого сайта.',
                'price_cents' => 0,
                'sort_order' => 10,
            ], [
                'max_sites' => ['limit' => 1],
                'max_monitors' => ['limit' => null],
                'allowed_monitor_types' => ['types' => $allTypes],
                'history_retention_days' => ['days' => 3],
                'minimum_check_interval_seconds' => ['seconds' => 900],
                'notification_channels' => ['channels' => ['email']],
                'can_create_projects' => ['enabled' => false],
            ]);

            $this->updatePlan('pro', [
                'name' => 'Pro',
                'description' => 'Мониторинг до 10 сайтов с Telegram-уведомлениями и историей 30 дней.',
                'price_cents' => 39000,
                'sort_order' => 20,
            ], [
                'max_sites' => ['limit' => 10],
                'max_monitors' => ['limit' => null],
                'allowed_monitor_types' => ['types' => $allTypes],
                'history_retention_days' => ['days' => 30],
                'minimum_check_interval_seconds' => ['seconds' => 300],
                'notification_channels' => ['channels' => ['email', 'telegram']],
                'can_create_projects' => ['enabled' => false],
            ]);

            $this->updatePlan('team', [
                'name' => 'Plus',
                'description' => 'Расширенный мониторинг до 30 сайтов с проверками от 3 минут.',
                'price_cents' => 69000,
                'sort_order' => 30,
            ], [
                'max_sites' => ['limit' => 30],
                'max_monitors' => ['limit' => null],
                'allowed_monitor_types' => ['types' => $allTypes],
                'history_retention_days' => ['days' => 60],
                'minimum_check_interval_seconds' => ['seconds' => 180],
                'notification_channels' => ['channels' => ['email', 'telegram']],
                'can_create_projects' => ['enabled' => true],
            ]);

            DB::table('plans')->where('code', 'team')->update([
                'code' => 'plus',
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, array<string, mixed>>  $limits
     */
    private function updatePlan(string $code, array $plan, array $limits): void
    {
        $planId = DB::table('plans')->where('code', $code)->value('id');

        if ($planId === null) {
            return;
        }

        DB::table('plans')->where('id', $planId)->update([
            ...$plan,
            'updated_at' => now(),
        ]);

        foreach ($limits as $key => $value) {
            DB::table('plan_limits')->updateOrInsert(
                ['plan_id' => $planId, 'key' => $key],
                [
                    'value' => json_encode($value, JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
};
