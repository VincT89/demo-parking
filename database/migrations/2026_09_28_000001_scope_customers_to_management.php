<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->runSafely(fn () => $this->applyUp());
    }

    public function down(): void
    {
        $this->runSafely(fn () => $this->applyDown());
    }

    private function runSafely(Closure $operation): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $operation();
            return;
        }
        // SQLite table rebuilds must not cascade into existing child records.
        // PRAGMA foreign_keys cannot be changed inside an outer transaction.
        if (DB::transactionLevel() > 0) {
            throw new RuntimeException('Run this SQLite migration outside an existing transaction. No data was changed.');
        }
        $foreignKeysEnabled = (bool) DB::scalar('PRAGMA foreign_keys');
        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($operation) {
                $operation();
                if (DB::select('PRAGMA foreign_key_check') !== []) {
                    throw new RuntimeException('Existing relationships could not be preserved. The migration was rolled back.');
                }
            });
        } finally {
            if ($foreignKeysEnabled) {
                Schema::enableForeignKeyConstraints();
            }
        }
    }

    private function applyUp(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('management_id')->nullable()->constrained('managements')->restrictOnDelete();
        });
        // The boundary migration placed all pre-existing records in this management.
        $initialId = DB::table('managements')->where('is_initial', true)->value('id');
        if (! $initialId) {
            throw new RuntimeException('Initial management is missing.');
        }
        DB::table('customers')->update(['management_id' => $initialId]);
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('management_id')->nullable(false)->change();
            $table->index(['management_id', 'email']);
        });
    }

    private function applyDown(): void
    {
        if (DB::table('managements')->count() > 1) {
            throw new RuntimeException('Customer isolation cannot be removed with multiple managements.');
        }
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['management_id', 'email']);
            $table->dropConstrainedForeignId('management_id');
        });
    }
};
