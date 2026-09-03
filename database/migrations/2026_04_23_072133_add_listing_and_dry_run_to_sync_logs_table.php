<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sync_logs', function (Blueprint $table) {
            $table->foreignId('parking_listing_id')->nullable()->after('platform_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('reservations_skipped')->default(0)->after('reservations_failed');
            $table->boolean('is_dry_run')->default(false)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sync_logs', function (Blueprint $table) {
            $table->dropForeign(['parking_listing_id']);
            $table->dropColumn(['parking_listing_id', 'reservations_skipped', 'is_dry_run']);
        });
    }
};
