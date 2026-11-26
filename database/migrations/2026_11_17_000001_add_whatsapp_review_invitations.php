<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parkings', function (Blueprint $table): void {
            $table->unsignedBigInteger('whatsapp_reviews_enabled_at')->nullable();
            $table->text('whatsapp_review_settings')->nullable();
        });

        Schema::table('reservations', function (Blueprint $table): void {
            $table->unsignedBigInteger('exited_at')->nullable();
            $table->text('whatsapp_review_consent')->nullable();
            $table->string('whatsapp_review_status', 16)->nullable();
            $table->unsignedBigInteger('whatsapp_review_attempted_at')->nullable();
            $table->string('whatsapp_review_message_sid', 34)->nullable();
            $table->index(['parking_id', 'whatsapp_review_status', 'exited_at'], 'reservations_whatsapp_review_due_idx');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropIndex('reservations_whatsapp_review_due_idx');
            $table->dropColumn([
                'exited_at', 'whatsapp_review_consent', 'whatsapp_review_status',
                'whatsapp_review_attempted_at', 'whatsapp_review_message_sid',
            ]);
        });

        Schema::table('parkings', function (Blueprint $table): void {
            $table->dropColumn(['whatsapp_reviews_enabled_at', 'whatsapp_review_settings']);
        });
    }
};
