<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_allocations', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->unsignedBigInteger('reservation_item_id');
            $table->unsignedBigInteger('inventory_item_id');
            $table->unsignedBigInteger('batch_id');
            $table->unsignedInteger('quantity');
            $table->string('status', 20);
            $table->timestamp('released_at')->nullable();
            $table->string('release_reason', 30)->nullable();
            $table->timestamps();

            $table->foreign(['reservation_item_id', 'inventory_item_id'], 'batch_allocations_reservation_item_fk')
                ->references(['id', 'inventory_item_id'])->on('reservation_items')->restrictOnDelete();
            $table->foreign(['inventory_item_id', 'batch_id'], 'batch_allocations_inventory_batch_fk')
                ->references(['inventory_item_id', 'id'])->on('inventory_batches')->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE batch_allocations
                ADD CONSTRAINT batch_allocations_quantity_check
                    CHECK (quantity > 0),
                ADD CONSTRAINT batch_allocations_status_check
                    CHECK (CAST(status AS BINARY) IN ('held', 'fulfilled', 'released'))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_allocations');
    }
};
