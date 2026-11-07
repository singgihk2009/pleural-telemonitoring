<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_credentials', function (Blueprint $table) {
            // Legacy rows remain null until an authorized mutation; never derive from PIN/hash.
            $table->uuid('revision')->nullable()->unique('patient_credential_revision_unique');
        });
    }

    public function down(): void
    {
        Schema::table('patient_credentials', function (Blueprint $table) {
            $table->dropUnique('patient_credential_revision_unique');
            $table->dropColumn('revision');
        });
    }
};
