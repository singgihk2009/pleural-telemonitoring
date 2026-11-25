<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('education_topics', function (Blueprint $table) {
            $table->string('code', 24)->primary();
            $table->unsignedInteger('latest_version')->default(0);
            $table->unsignedInteger('published_version')->nullable();
        });
        Schema::create('education_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('topic_code', 24);
            $table->foreign('topic_code')->references('code')->on('education_topics')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedInteger('version');
            $table->enum('status', ['DRAFT', 'PUBLISHED', 'ARCHIVED']);
            $table->string('title', 160);
            $table->text('description');
            $table->unsignedSmallInteger('display_order');
            $table->string('approval_reference', 80)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('pengguna')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('recorded_at', 6);
            $table->unique(['topic_code', 'version']);
        });
        Schema::create('education_references', function (Blueprint $table) {
            $table->foreignId('revision_id')->constrained('education_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedSmallInteger('position');
            $table->enum('type', ['ARTICLE', 'JOURNAL', 'DOI', 'PDF', 'YOUTUBE']);
            $table->string('label', 500);
            $table->string('url', 2048)->nullable();
            $table->string('canonical_url', 2048)->nullable();
            $table->primary(['revision_id', 'position']);
        });
        foreach (['education_revisions', 'education_references'] as $table) {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                $trigger = $table.'_no_'.strtolower($operation);
                DB::unprepared("CREATE TRIGGER $trigger BEFORE $operation ON $table FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable education revision'");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('education_references');
        Schema::dropIfExists('education_revisions');
        Schema::dropIfExists('education_topics');
    }
};
