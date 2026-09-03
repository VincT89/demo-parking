<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_product_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parking_product_id')->constrained()->cascadeOnDelete();

            $table->string('external_ref')->nullable();
            $table->string('external_name')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['platform_id', 'is_active']);
            $table->index(['parking_product_id', 'is_active']);
            // $table->unique(['platform_id', 'external_ref']); // Rimossa prudenzialmente come da consiglio
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_product_mappings');
    }
};
