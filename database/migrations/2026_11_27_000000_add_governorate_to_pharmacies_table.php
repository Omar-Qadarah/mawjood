<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharmacies', function (Blueprint $table): void {
            $table->string('governorate', 100)->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('pharmacies', function (Blueprint $table): void {
            $table->dropColumn('governorate');
        });
    }
};
