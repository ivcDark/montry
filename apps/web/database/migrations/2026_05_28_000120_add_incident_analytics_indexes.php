<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->index(['organization_id', 'started_at'], 'incidents_org_started_idx');
            $table->index(['organization_id', 'project_id', 'started_at'], 'incidents_org_project_started_idx');
            $table->index(['organization_id', 'monitored_resource_id', 'started_at'], 'incidents_org_resource_started_idx');
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->dropIndex('incidents_org_started_idx');
            $table->dropIndex('incidents_org_project_started_idx');
            $table->dropIndex('incidents_org_resource_started_idx');
        });
    }
};
