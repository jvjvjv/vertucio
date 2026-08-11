<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * This application has no user accounts: it is a single-purpose SERP site
 * with no login, registration, or profile. The `sessions` table stays —
 * the database session driver needs it regardless of authentication — but
 * `users` and `password_reset_tokens` exist only to support Fortify's
 * auth scaffolding, which has been removed.
 *
 * A new migration rather than editing 0001_01_01_000000_create_users_table
 * because that migration has already run in this application's database;
 * editing it would not retroactively drop the tables there.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
};
