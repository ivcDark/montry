<?php

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class DeleteUserProfile
{
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $organizationIds = DB::table('organization_users')
                ->where('user_id', $user->id)
                ->pluck('organization_id')
                ->all();

            if ($organizationIds !== []) {
                $this->deleteOrganizationData($organizationIds, (int) $user->id);
            }

            DB::table('product_ideas')
                ->where('user_id', $user->id)
                ->delete();

            DB::table('business_events')
                ->where('user_id', $user->id)
                ->delete();

            DB::table('audit_logs')
                ->where('actor_user_id', $user->id)
                ->delete();

            $user->delete();
        });
    }

    /**
     * @param  list<int>  $organizationIds
     */
    private function deleteOrganizationData(array $organizationIds, int $userId): void
    {
        $now = now();
        $monitorIds = DB::table('monitors')
            ->whereIn('organization_id', $organizationIds)
            ->pluck('id')
            ->all();
        $businessEventIds = DB::table('business_events')
            ->whereIn('organization_id', $organizationIds)
            ->pluck('id')
            ->all();

        DB::table('analytics_event_exports')
            ->whereIn('business_event_id', $businessEventIds)
            ->delete();

        DB::table('business_events')
            ->whereIn('organization_id', $organizationIds)
            ->delete();

        DB::table('audit_logs')
            ->whereIn('organization_id', $organizationIds)
            ->delete();

        DB::table('product_ideas')
            ->whereIn('organization_id', $organizationIds)
            ->delete();

        DB::table('incident_weekly_digest_logs')
            ->where('user_id', $userId)
            ->whereIn('organization_id', $organizationIds)
            ->delete();

        DB::table('incident_weekly_digest_preferences')
            ->where('user_id', $userId)
            ->whereIn('organization_id', $organizationIds)
            ->delete();

        DB::table('status_page_monitors')
            ->whereIn('monitor_id', $monitorIds)
            ->delete();

        DB::table('status_pages')
            ->whereIn('organization_id', $organizationIds)
            ->delete();

        DB::table('notification_logs')
            ->whereIn('organization_id', $organizationIds)
            ->delete();

        DB::table('notification_rules')
            ->whereIn('organization_id', $organizationIds)
            ->delete();

        DB::table('incident_comments')
            ->whereIn('organization_id', $organizationIds)
            ->delete();

        DB::table('incidents')
            ->whereIn('organization_id', $organizationIds)
            ->delete();

        DB::table('monitor_state_changes')
            ->whereIn('organization_id', $organizationIds)
            ->delete();

        DB::table('check_results')
            ->whereIn('organization_id', $organizationIds)
            ->delete();

        DB::table('notification_channels')
            ->whereIn('organization_id', $organizationIds)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);

        DB::table('monitors')
            ->whereIn('organization_id', $organizationIds)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);

        DB::table('monitored_resources')
            ->whereIn('organization_id', $organizationIds)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);

        DB::table('projects')
            ->whereIn('organization_id', $organizationIds)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);

        DB::table('organization_users')
            ->whereIn('organization_id', $organizationIds)
            ->delete();

        DB::table('organizations')
            ->whereIn('id', $organizationIds)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);
    }
}
