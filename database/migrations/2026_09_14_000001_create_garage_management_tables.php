<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garage_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parking_product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind');
            $table->string('billing_unit');
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->unsignedInteger('grace_minutes')->default(0);
            $table->unsignedInteger('minimum_units')->default(1);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['parking_id', 'kind', 'is_active']);
            $table->index(['parking_product_id', 'is_active']);
        });

        Schema::create('parking_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('parking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parking_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('garage_rate_id')->nullable()->constrained('garage_rates')->nullOnDelete();
            $table->string('reference');
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('license_plate', 32);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('paid_through')->nullable();
            $table->date('next_billing_on')->nullable();
            $table->string('status')->default('active');
            $table->unsignedInteger('reserved_spots')->default(1);
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('payment_mode')->default('manual');
            $table->string('stripe_customer_id')->nullable()->index();
            $table->string('stripe_subscription_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['parking_id', 'reference']);
            $table->index(['parking_product_id', 'status', 'starts_on', 'ends_on'], 'subscriptions_capacity_lookup');
            $table->index(['parking_id', 'status', 'next_billing_on']);
            $table->index(['parking_id', 'license_plate']);
        });

        Schema::create('parking_stays', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('parking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parking_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('garage_rate_id')->nullable()->constrained('garage_rates')->nullOnDelete();
            $table->foreignId('parking_subscription_id')->nullable()->constrained('parking_subscriptions')->nullOnDelete();
            $table->string('reference');
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('license_plate', 32);
            $table->dateTime('starts_at');
            $table->dateTime('expected_ends_at');
            $table->dateTime('ended_at')->nullable();
            $table->string('status')->default('active');
            $table->string('billing_unit')->nullable();
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('estimated_total', 10, 2)->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->string('payment_status')->default('unpaid');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['parking_id', 'reference']);
            $table->index(['parking_product_id', 'status', 'starts_at', 'expected_ends_at'], 'stays_capacity_lookup');
            $table->index(['parking_id', 'license_plate']);
            $table->index(['parking_id', 'payment_status']);
        });

        Schema::create('garage_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parking_subscription_id')->nullable()->constrained('parking_subscriptions')->cascadeOnDelete();
            $table->foreignId('parking_stay_id')->nullable()->constrained('parking_stays')->cascadeOnDelete();
            $table->string('provider')->default('manual');
            $table->string('method');
            $table->string('status')->default('pending');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->date('billing_period_start')->nullable();
            $table->date('billing_period_end')->nullable();
            $table->string('provider_payment_id')->nullable()->index();
            $table->string('provider_session_id')->nullable()->index();
            $table->string('provider_subscription_id')->nullable()->index();
            $table->json('raw_data')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reversal_reason')->nullable();
            $table->timestamps();

            $table->index(['parking_subscription_id', 'status', 'paid_at'], 'subscription_payment_history');
            $table->index(['parking_stay_id', 'status', 'paid_at'], 'stay_payment_history');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garage_payments');
        Schema::dropIfExists('parking_stays');
        Schema::dropIfExists('parking_subscriptions');
        Schema::dropIfExists('garage_rates');
    }
};
