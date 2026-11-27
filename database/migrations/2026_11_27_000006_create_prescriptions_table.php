<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('image_path')->nullable();
            $table->string('status', 20);
            $table->longText('extracted_payload')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('delete_after')->default(DB::raw('(CURRENT_TIMESTAMP + INTERVAL 3 DAY)'));
            $table->timestamp('image_deleted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE prescriptions
                ADD CONSTRAINT prescriptions_status_check
                    CHECK (CAST(status AS BINARY) IN ('uploaded', 'extracted', 'confirmed', 'failed'))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
