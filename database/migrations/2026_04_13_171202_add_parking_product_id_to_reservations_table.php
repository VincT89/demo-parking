<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('parking_product_id')
                ->nullable()
                ->after('parking_id')
                ->constrained('parking_products')
                ->nullOnDelete();

            $table->index(['parking_id', 'parking_product_id']);
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['parking_id', 'parking_product_id']);
            $table->dropConstrainedForeignId('parking_product_id');
        });
    }
};
