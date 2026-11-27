<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->decimal('selling_price', 10, 3);
            $table->boolean('is_listed')->default(true);
            $table->timestamp('stock_verified_at')->nullable();
            $table->timestamps();

            $table->unique(['pharmacy_id', 'medicine_id']);
            $table->unique(['pharmacy_id', 'id']);
        });

        DB::statement(
            'ALTER TABLE inventory_items ADD CONSTRAINT inventory_items_selling_price_check CHECK (selling_price >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
