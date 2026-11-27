<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('batch_id')->constrained('inventory_batches')->restrictOnDelete();
            $table->string('type', 20);
            $table->integer('quantity');
            $table->string('reason', 255)->nullable();
            $table->string('reference', 100)->nullable();
            $table->foreignId('reservation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE inventory_movements
                ADD CONSTRAINT inventory_movements_type_check
                    CHECK (CAST(type AS BINARY) IN ('receipt', 'adjustment', 'dispense', 'write_off'))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
