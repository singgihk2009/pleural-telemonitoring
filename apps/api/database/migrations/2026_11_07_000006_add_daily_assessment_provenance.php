<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entri_harian', function (Blueprint $table) {
            $table->foreignId('assessment_setting_id')->nullable()->constrained('pengaturan')->restrictOnDelete()->restrictOnUpdate();
            $table->char('submission_fingerprint', 64)->nullable();
        });
        DB::statement("ALTER TABLE entri_harian ADD CONSTRAINT assessment_provenance_pair CHECK ((assessment_setting_id IS NULL AND submission_fingerprint IS NULL) OR (assessment_setting_id IS NOT NULL AND submission_fingerprint IS NOT NULL AND submission_fingerprint REGEXP '^[0-9a-f]{64}$'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE entri_harian DROP CHECK assessment_provenance_pair');
        Schema::table('entri_harian', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assessment_setting_id');
            $table->dropColumn('submission_fingerprint');
        });
    }
};
