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
        Schema::create('parking_capacity_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_id')->constrained()->cascadeOnDelete();
            $table->string('allocation_type'); // rentcar, internal_use, partner, maintenance, other
            $table->integer('spots');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parking_capacity_allocations');
    }
};
