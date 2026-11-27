<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicines', function (Blueprint $table): void {
            $table->id();
            $table->string('generic_name_en', 200);
            $table->string('generic_name_ar', 200)->nullable();
            $table->string('name_en', 200);
            $table->string('name_ar', 200)->nullable();
            $table->string('dosage_form', 40);
            $table->string('route', 40);
            $table->string('strength_text', 120);
            $table->string('brand_name', 150);
            $table->string('manufacturer_name', 150);
            $table->decimal('pack_size', 10, 3);
            $table->string('pack_unit', 30);
            $table->string('barcode', 64)->nullable();
            $table->string('jfda_registration_no', 100)->nullable();
            $table->string('registration_status', 30)->nullable();
            $table->date('registration_expiry')->nullable();
            $table->decimal('official_price', 10, 3)->nullable();
            $table->char('currency', 3)->default('JOD');
            $table->date('official_price_effective_date')->nullable();
            $table->string('dispensing_class', 30);
            $table->string('search_normalized', 500);
            $table->string('catalog_status', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicines');
    }
};
