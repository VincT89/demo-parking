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
        Schema::create('availability_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('parking_listing_id')->nullable();
            $table->foreign('parking_listing_id')->references('id')->on('parking_listings')->nullOnDelete();
            $table->string('type')->default('closure');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedInteger('spots')->default(1);
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['parking_id', 'starts_at', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('availability_blocks');
    }
};
