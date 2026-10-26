<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('porthole_oauth_clients', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('name');
            $table->json('redirect_uris');
            $table->string('logo_uri')->nullable();
            $table->string('client_uri')->nullable();
            $table->timestamps();
        });

        Schema::create('porthole_oauth_codes', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('client_id', 40)->index();
            $table->string('user_id');
            $table->string('redirect_uri');
            $table->string('code_challenge');
            $table->timestamp('expires_at');
        });

        Schema::create('porthole_oauth_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('client_id', 40)->index();
            $table->string('user_id');
            $table->string('access_token_hash', 64)->unique();
            $table->string('refresh_token_hash', 64)->nullable()->unique();
            $table->string('scope');
            $table->timestamp('expires_at');
            $table->timestamp('refresh_expires_at');
            $table->boolean('revoked')->default(false);
            $table->uuid('replaced_by_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('porthole_oauth_tokens');
        Schema::dropIfExists('porthole_oauth_codes');
        Schema::dropIfExists('porthole_oauth_clients');
    }
};
