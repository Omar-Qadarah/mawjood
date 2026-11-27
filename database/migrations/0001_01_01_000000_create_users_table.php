<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 190)->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('google_id', 64)->nullable()->unique();
            $table->string('password')->nullable();
            $table->string('phone', 20)->nullable()->unique();
            $table->timestamp('phone_verified_at')->nullable();
            $table->enum('platform_role', ['customer', 'admin'])->default('customer');
            $table->char('locale', 2)->default('ar');
            $table->boolean('is_active')->default(true);
            $table->timestamp('anonymized_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 190)->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};