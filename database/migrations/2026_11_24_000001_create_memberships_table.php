<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('pharmacy_id')
                ->constrained('pharmacies')
                ->restrictOnDelete();
            $table->string('role', 30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'pharmacy_id'], 'memberships_user_pharmacy_unique');
        });

        DB::statement(
            "ALTER TABLE memberships
             ADD CONSTRAINT memberships_role_check
             CHECK (role IN ('owner', 'staff'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
