<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->text('failure_reason')->nullable();

            $table->index(['provider', 'status', 'created_at']);
            $table->index(['failed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['provider', 'status', 'created_at']);
            $table->dropIndex(['failed_at']);
            $table->dropColumn(['failed_at', 'failure_code', 'failure_reason']);
        });
    }
};
