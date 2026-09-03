<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('electronic_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('parking_id')->constrained()->cascadeOnDelete();
            $table->string('number', 32);
            $table->unsignedBigInteger('sequence');
            $table->unsignedSmallInteger('year');
            $table->string('provider_mode')->default('simulator');
            $table->string('status')->default('draft');

            $table->string('customer_type')->default('person');
            $table->string('customer_name');
            $table->string('customer_vat_country', 2)->default('IT');
            $table->string('customer_vat_number', 28)->nullable();
            $table->string('customer_fiscal_code', 16)->nullable();
            $table->string('customer_recipient_code', 7)->nullable();
            $table->string('customer_pec')->nullable();
            $table->string('customer_address');
            $table->string('customer_postal_code', 10);
            $table->string('customer_city', 60);
            $table->string('customer_province', 2)->nullable();
            $table->string('customer_country', 2)->default('IT');

            $table->decimal('taxable_amount', 12, 2);
            $table->decimal('vat_rate', 5, 2);
            $table->decimal('vat_amount', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->longText('xml_payload')->nullable();
            $table->string('remote_id')->nullable();
            $table->string('remote_filename')->nullable();
            $table->string('sdi_id')->nullable();
            $table->text('last_error')->nullable();
            $table->json('status_history')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('last_status_check_at')->nullable();
            $table->timestamps();

            $table->index(['parking_id', 'year', 'sequence']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('electronic_invoices');
    }
};
