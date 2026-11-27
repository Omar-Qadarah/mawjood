<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_requests', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('feature', 20);
            $table->string('model', 60);
            $table->string('status', 20);
            $table->foreignId('prescription_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('cost_usd', 8, 5)->nullable();
            $table->string('error_code', 40)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE ai_requests
                ADD CONSTRAINT ai_requests_feature_check
                    CHECK (CAST(feature AS BINARY) IN ('smart_search', 'prescription_read')),
                ADD CONSTRAINT ai_requests_status_check
                    CHECK (CAST(status AS BINARY) IN ('queued', 'ok', 'failed', 'rejected'))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_requests');
    }
};
