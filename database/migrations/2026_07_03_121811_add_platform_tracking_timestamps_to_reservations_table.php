<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dateTime('platform_created_at')->nullable()->after('external_id');
            $table->dateTime('platform_updated_at')->nullable()->after('platform_created_at');
            $table->dateTime('platform_cancelled_at')->nullable()->after('platform_updated_at');

            $table->dateTime('first_seen_at')->nullable()->after('platform_cancelled_at');
            $table->dateTime('last_seen_at')->nullable()->after('first_seen_at');

            $table->index('platform_created_at', 'reservations_platform_created_at_idx');
            $table->index('platform_updated_at', 'reservations_platform_updated_at_idx');
            $table->index('platform_cancelled_at', 'reservations_platform_cancelled_at_idx');
            $table->index('first_seen_at', 'reservations_first_seen_at_idx');
            $table->index('last_seen_at', 'reservations_last_seen_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_platform_created_at_idx');
            $table->dropIndex('reservations_platform_updated_at_idx');
            $table->dropIndex('reservations_platform_cancelled_at_idx');
            $table->dropIndex('reservations_first_seen_at_idx');
            $table->dropIndex('reservations_last_seen_at_idx');

            $table->dropColumn([
                'platform_created_at',
                'platform_updated_at',
                'platform_cancelled_at',
                'first_seen_at',
                'last_seen_at',
            ]);
        });
    }
};
