<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('invoice_mode')->default('simulator');
            $table->text('aruba_username')->nullable();
            $table->text('aruba_password')->nullable();
            $table->string('issuer_business_name')->nullable();
            $table->string('issuer_vat_country', 2)->default('IT');
            $table->string('issuer_vat_number', 28)->nullable();
            $table->string('issuer_fiscal_code', 16)->nullable();
            $table->string('issuer_tax_regime', 4)->default('RF01');
            $table->string('issuer_address')->nullable();
            $table->string('issuer_postal_code', 10)->nullable();
            $table->string('issuer_city', 60)->nullable();
            $table->string('issuer_province', 2)->nullable();
            $table->string('issuer_country', 2)->default('IT');
            $table->string('invoice_prefix', 12)->default('DEMO');
            $table->unsignedBigInteger('next_invoice_number')->default(1);
            $table->decimal('default_vat_rate', 5, 2)->default(22);
            $table->boolean('prices_include_vat')->default(true);

            $table->string('ticket_format')->default('a6');
            $table->string('ticket_orientation')->default('portrait');
            $table->unsignedSmallInteger('ticket_width_mm')->default(80);
            $table->unsignedSmallInteger('ticket_height_mm')->default(60);
            $table->boolean('ticket_auto_open_on_exit')->default(true);
            $table->boolean('ticket_show_logo')->default(true);
            $table->string('ticket_title')->default('Ricevuta di ritiro veicolo');
            $table->text('ticket_footer')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_settings');
    }
};
