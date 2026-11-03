<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_credentials', function (Blueprint $table) {
            $table->uuid('responden_id')->primary();
            $table->string('kelompok', 16)->collation('utf8mb4_bin')->default('INTERVENSI');
            $table->foreign(['responden_id', 'kelompok'], 'credential_intervention_participant')->references(['id', 'kelompok'])->on('responden')->restrictOnDelete()->restrictOnUpdate();
            $table->string('pin_hash');
            $table->foreignId('issued_by')->constrained('pengguna')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('issued_at', 6);
            $table->dateTime('revoked_at', 6)->nullable();
            $table->timestamps(6);
        });
        DB::statement("ALTER TABLE patient_credentials ADD CONSTRAINT credential_intervention_only CHECK (kelompok = 'INTERVENSI')");

        DB::statement("ALTER TABLE patient_credentials ADD CONSTRAINT credential_hash_format CHECK (CHAR_LENGTH(pin_hash) >= 60 AND (pin_hash LIKE '\$2y\$%' OR pin_hash LIKE '\$2a\$%' OR pin_hash LIKE '\$2b\$%' OR pin_hash LIKE '\$argon2id\$%' OR pin_hash LIKE '\$argon2i\$%'))");

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type', 16)->collation('utf8mb4_bin');
            // Both BIGINT staff identities and UUID patient identities use this polymorphic key.
            $table->string('tokenable_id', 36);
            $table->index(['tokenable_type', 'tokenable_id']);
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
        DB::statement("ALTER TABLE personal_access_tokens ADD CONSTRAINT token_principal_type CHECK (tokenable_type IN ('staff', 'patient'))");

        Schema::create('identified_access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengguna_id')->constrained('pengguna')->restrictOnDelete()->restrictOnUpdate();
            $table->uuid('responden_id');
            $table->foreign('responden_id')->references('id')->on('responden')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('approved_by')->constrained('pengguna')->restrictOnDelete()->restrictOnUpdate();
            $table->string('purpose_code', 80)->collation('utf8mb4_bin');
            $table->dateTime('starts_at', 6);
            $table->dateTime('expires_at', 6);
            $table->dateTime('revoked_at', 6)->nullable();
            $table->timestamps(6);
            $table->index(['pengguna_id', 'responden_id', 'expires_at'], 'identified_access_scope');
        });
        DB::statement('ALTER TABLE identified_access_grants ADD CONSTRAINT grant_separate_approver CHECK (pengguna_id <> approved_by)');
        DB::statement('ALTER TABLE identified_access_grants ADD CONSTRAINT grant_time_window CHECK (expires_at > starts_at)');
        DB::statement("ALTER TABLE identified_access_grants ADD CONSTRAINT grant_purpose_code CHECK (purpose_code REGEXP '^[A-Z0-9_]{1,80}$')");

        Schema::create('auth_rate_limits', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration')->index();
        });
        Schema::create('auth_rate_limit_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_rate_limit_locks');
        Schema::dropIfExists('auth_rate_limits');
        Schema::dropIfExists('identified_access_grants');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('patient_credentials');
    }
};
