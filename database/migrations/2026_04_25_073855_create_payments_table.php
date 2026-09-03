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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reservation_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('provider'); // stripe, paypal
            $table->string('status')->default('pending');
            // pending, paid, failed, cancelled, expired, refunded

            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');

            $table->string('provider_payment_id')->nullable()->index();
            $table->string('provider_order_id')->nullable()->index();
            $table->string('provider_session_id')->nullable()->index();

            $table->json('raw_data')->nullable();

            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->unique(['provider', 'provider_payment_id']);
            $table->unique(['provider', 'provider_order_id']);
            $table->unique(['provider', 'provider_session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
