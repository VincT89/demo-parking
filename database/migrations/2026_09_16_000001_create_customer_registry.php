<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('person');
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('phone', 50)->nullable();
            $table->string('phone_normalized', 50)->nullable()->index();
            $table->string('fiscal_code', 16)->nullable()->index();
            $table->string('vat_number', 28)->nullable()->index();
            $table->string('vat_country', 2)->default('IT');
            $table->string('recipient_code', 7)->nullable();
            $table->string('pec')->nullable();
            $table->string('address', 60)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city', 60)->nullable();
            $table->string('province', 2)->nullable();
            $table->string('country', 2)->default('IT');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('customer_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('license_plate', 32)->index();
            $table->unique(['customer_id', 'license_plate']);
        });

        foreach (['reservations', 'parking_subscriptions', 'parking_stays'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['parking_stays', 'parking_subscriptions', 'reservations'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('customer_id'));
        }
        Schema::dropIfExists('customer_vehicles');
        Schema::dropIfExists('customers');
    }
};
