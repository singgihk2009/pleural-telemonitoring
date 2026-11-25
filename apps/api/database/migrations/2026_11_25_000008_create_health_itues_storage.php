<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_itues_versions', function (Blueprint $table) {
            $table->string('version', 40)->collation('utf8mb4_bin')->primary();
            $table->char('definition_sha256', 64);
            $table->json('definition');
        });

        Schema::create('health_itues_responses', function (Blueprint $table) {
            $table->uuid('submission_id')->primary();
            $table->string('version', 40)->collation('utf8mb4_bin');
            $table->foreign('version')->references('version')->on('health_itues_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->json('answers');
            $table->dateTime('received_at', 6);
        });

        DB::statement("ALTER TABLE health_itues_versions ADD CHECK (JSON_TYPE(definition) = 'OBJECT')");
        DB::statement("ALTER TABLE health_itues_responses ADD CHECK (JSON_TYPE(answers) = 'OBJECT')");
        foreach (['health_itues_versions', 'health_itues_responses'] as $table) {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                $trigger = $table.'_no_'.strtolower($operation);
                DB::unprepared("CREATE TRIGGER $trigger BEFORE $operation ON $table FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable Health-ITUES record'");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_itues_responses');
        Schema::dropIfExists('health_itues_versions');
    }
};
