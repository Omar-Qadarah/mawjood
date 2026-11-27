<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->char('code', 10)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('pharmacy_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('prescription_id')->nullable();
            $table->string('status', 20);
            $table->timestamp('status_changed_at')->useCurrent();
            $table->string('idempotency_key', 64);
            $table->timestamp('response_deadline')->default(DB::raw('(CURRENT_TIMESTAMP + INTERVAL 30 MINUTE)'));
            $table->timestamp('pickup_deadline')->nullable();
            $table->char('pickup_code_hash', 64)->nullable();
            $table->unsignedTinyInteger('pickup_attempts')->default(0);
            $table->string('rejection_reason', 255)->nullable();
            $table->string('attention_reason', 40)->nullable();
            $table->string('customer_note', 500)->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key']);
            $table->unique(['id', 'pharmacy_id']);
            $table->foreign(['user_id', 'prescription_id'], 'reservations_prescription_owner_fk')
                ->references(['user_id', 'id'])->on('prescriptions')->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE reservations
                ADD CONSTRAINT reservations_status_check
                    CHECK (CAST(status AS BINARY) IN ('pending', 'confirmed', 'rejected', 'cancelled', 'completed', 'expired'))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
