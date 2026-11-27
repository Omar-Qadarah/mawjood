<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_items', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->unsignedBigInteger('reservation_id');
            $table->unsignedBigInteger('pharmacy_id');
            $table->unsignedBigInteger('inventory_item_id');
            $table->string('medicine_name_snapshot', 255);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 3);
            $table->timestamps();

            $table->unique(['reservation_id', 'inventory_item_id']);
            $table->unique(['id', 'inventory_item_id']);
            $table->foreign(['reservation_id', 'pharmacy_id'], 'reservation_items_reservation_pharmacy_fk')
                ->references(['id', 'pharmacy_id'])->on('reservations')->restrictOnDelete();
            $table->foreign(['pharmacy_id', 'inventory_item_id'], 'reservation_items_inventory_pharmacy_fk')
                ->references(['pharmacy_id', 'id'])->on('inventory_items')->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE reservation_items
                ADD CONSTRAINT reservation_items_quantity_check
                    CHECK (quantity > 0)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_items');
    }
};
