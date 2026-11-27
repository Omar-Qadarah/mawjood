<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_batches', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->string('batch_no', 60);
            $table->date('expiry_date');
            $table->unsignedInteger('quantity_on_hand');
            $table->unsignedInteger('quantity_held')->default(0);
            $table->string('status', 20);
            $table->timestamps();

            $table->unique(['inventory_item_id', 'id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE inventory_batches
                ADD CONSTRAINT inventory_batches_held_quantity_check
                    CHECK (quantity_held <= quantity_on_hand),
                ADD CONSTRAINT inventory_batches_status_check
                    CHECK (CAST(status AS BINARY) IN ('active', 'quarantined', 'damaged', 'recalled', 'inactive'))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_batches');
    }
};
