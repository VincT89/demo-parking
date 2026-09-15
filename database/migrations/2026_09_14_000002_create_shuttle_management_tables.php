<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shuttle_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->unsignedInteger('default_capacity')->default(1);
            $table->unsignedInteger('grouping_window_minutes')->default(30);
            $table->unsignedInteger('turnaround_minutes')->default(45);
            $table->integer('outbound_offset_minutes')->default(15);
            $table->integer('return_offset_minutes')->default(0);
            $table->timestamps();
        });

        Schema::create('shuttle_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('license_plate', 32)->nullable();
            $table->unsignedInteger('seats');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['parking_id', 'is_active']);
        });

        Schema::create('shuttle_trips', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('parking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shuttle_vehicle_id')->nullable()->constrained('shuttle_vehicles')->nullOnDelete();
            $table->string('direction');
            $table->dateTime('scheduled_at');
            $table->string('status')->default('proposed');
            $table->unsignedInteger('capacity');
            $table->boolean('is_generated')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['parking_id', 'scheduled_at', 'direction']);
            $table->index(['shuttle_vehicle_id', 'scheduled_at']);
        });

        Schema::create('shuttle_trip_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shuttle_trip_id')->constrained('shuttle_trips')->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('passengers');
            $table->timestamps();

            $table->unique(['shuttle_trip_id', 'reservation_id']);
            $table->index(['reservation_id', 'shuttle_trip_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shuttle_trip_assignments');
        Schema::dropIfExists('shuttle_trips');
        Schema::dropIfExists('shuttle_vehicles');
        Schema::dropIfExists('shuttle_settings');
    }
};
