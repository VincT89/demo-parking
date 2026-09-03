<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->unsignedInteger('capacity');
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['parking_id', 'code']);
            $table->index(['parking_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_products');
    }
};
