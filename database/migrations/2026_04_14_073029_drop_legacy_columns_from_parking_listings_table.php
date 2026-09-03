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
        Schema::table('parking_listings', function (Blueprint $table) {
            $table->dropColumn(['inventory_type', 'allotment']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parking_listings', function (Blueprint $table) {
            $table->string('inventory_type')->default('isolated');
            $table->unsignedInteger('allotment')->default(0);
        });
    }
};
