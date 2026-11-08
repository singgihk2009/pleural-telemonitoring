<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instrument_publications', function (Blueprint $table) {
            $table->foreignId('setting_id')->primary()->constrained('pengaturan')->restrictOnDelete()->restrictOnUpdate();
            $table->string('approval_reference', 80);
            $table->dateTime('effective_at', 6)->unique();
            $table->dateTime('expires_at', 6);
        });
        DB::statement('ALTER TABLE instrument_publications ADD CONSTRAINT publication_window CHECK (expires_at > effective_at)');
        DB::statement("ALTER TABLE instrument_publications ADD CONSTRAINT publication_reference CHECK (approval_reference REGEXP '^[A-Za-z0-9_-]{1,80}$')");
        foreach (['UPDATE', 'DELETE'] as $operation) {
            DB::unprepared('CREATE TRIGGER instrument_publications_no_'.strtolower($operation)." BEFORE $operation ON instrument_publications FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable publication record'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('instrument_publications');
    }
};
