<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identified_access_grant_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grant_id')->constrained('identified_access_grants')->restrictOnDelete()->restrictOnUpdate();
            $table->string('scope', 16)->collation('utf8mb4_bin');
            $table->timestamps(6);
            $table->unique(['grant_id', 'scope'], 'identified_grant_scope_unique');
        });
        DB::statement("ALTER TABLE identified_access_grant_scopes ADD CONSTRAINT identified_grant_scope_value CHECK (CAST(scope AS BINARY) IN ('TECHNICAL', 'CONTACT', 'CLINICAL_READ'))");
        // Deliberately no backfill: legacy grants authorize no data until explicitly reapproved.
    }

    public function down(): void
    {
        Schema::dropIfExists('identified_access_grant_scopes');
    }
};
