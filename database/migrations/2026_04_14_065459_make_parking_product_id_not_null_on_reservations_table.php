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
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['parking_product_id']);
            $table->unsignedBigInteger('parking_product_id')->nullable(false)->change();
            $table->foreign('parking_product_id')->references('id')->on('parking_products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['parking_product_id']);
            $table->unsignedBigInteger('parking_product_id')->nullable()->change();
            $table->foreign('parking_product_id')->references('id')->on('parking_products')->nullOnDelete();
        });
    }
};
