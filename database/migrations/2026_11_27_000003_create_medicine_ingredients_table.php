<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicine_ingredients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('active_ingredient_id')->constrained()->restrictOnDelete();
            $table->decimal('strength_value', 12, 4);
            $table->string('strength_unit', 10);
            $table->decimal('per_value', 12, 4)->nullable();
            $table->string('per_unit', 10)->nullable();
            $table->timestamps();

            $table->unique(['medicine_id', 'active_ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicine_ingredients');
    }
};
