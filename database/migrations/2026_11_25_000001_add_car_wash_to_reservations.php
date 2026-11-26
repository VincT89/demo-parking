<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->string('car_wash', 16)->nullable()->index();
            $table->decimal('car_wash_surcharge', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropIndex(['car_wash']);
            $table->dropColumn(['car_wash', 'car_wash_surcharge']);
        });
    }
};
