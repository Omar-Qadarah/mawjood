<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('active_ingredients', function (Blueprint $table): void {
            $table->id();
            $table->string('name_en', 150)->unique();
            $table->string('name_ar', 150)->nullable();
            $table->string('atc_code', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('active_ingredients');
    }
};
