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
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->default('email');
            $table->string('status')->default('success');
            $table->unsignedInteger('reservations_created')->default(0);
            $table->unsignedInteger('reservations_updated')->default(0);
            $table->unsignedInteger('reservations_failed')->default(0);
            $table->text('notes')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
