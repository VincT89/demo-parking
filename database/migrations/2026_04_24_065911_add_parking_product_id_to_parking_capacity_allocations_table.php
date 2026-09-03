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
        Schema::table('parking_capacity_allocations', function (Blueprint $table) {
            $table->foreignId('parking_product_id')->nullable()->after('parking_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parking_capacity_allocations', function (Blueprint $table) {
            $table->dropForeign(['parking_product_id']);
            $table->dropColumn('parking_product_id');
        });
    }
};
