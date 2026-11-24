<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacies', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('license_no', 100)->unique();
            $table->string('phone', 20)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('address', 255);
            $table->string('city', 100);
            $table->decimal('latitude', 9, 6);
            $table->decimal('longitude', 9, 6);
            $table->json('opening_hours')->nullable();
            $table->timestamps();

            $table->index(['latitude', 'longitude'], 'pharmacies_coordinates_index');
        });

        DB::statement(
            "ALTER TABLE pharmacies
             ADD CONSTRAINT pharmacies_status_check
             CHECK (status IN ('pending', 'active', 'suspended'))"
        );

        DB::statement(
            'ALTER TABLE pharmacies
             ADD CONSTRAINT pharmacies_latitude_check
             CHECK (latitude BETWEEN -90 AND 90)'
        );

        DB::statement(
            'ALTER TABLE pharmacies
             ADD CONSTRAINT pharmacies_longitude_check
             CHECK (longitude BETWEEN -180 AND 180)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacies');
    }
};
