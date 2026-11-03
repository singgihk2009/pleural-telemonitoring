<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('TeleNapas research migrations require MySQL 8.0.16+ (validated on 8.4).');
        }
        Schema::create('pengguna', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 120);
            $table->string('email', 190)->nullable()->unique();
            $table->string('password_hash');
            $table->string('status', 20)->collation('utf8mb4_bin')->default('AKTIF');
            $table->timestamps(6);
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 40)->collation('utf8mb4_bin')->unique();
            $table->timestamps(6);
        });

        Schema::create('role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengguna_id');
            $table->foreignId('role_id');
            $table->foreign('pengguna_id')->references('id')->on('pengguna')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('role_id')->references('id')->on('user_roles')->restrictOnDelete()->restrictOnUpdate();
            $table->unique(['pengguna_id', 'role_id']);
            $table->timestamps(6);
        });

        Schema::create('responden', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 32)->collation('utf8mb4_bin')->unique();
            $table->string('kelompok', 16)->collation('utf8mb4_bin');
            $table->date('tanggal_pulang');
            $table->unsignedTinyInteger('vas_baseline_mm');
            $table->string('status', 16)->collation('utf8mb4_bin')->default('AKTIF');
            $table->text('alasan_drop_out')->nullable();
            $table->date('tanggal_consent');
            $table->string('versi_consent', 40)->collation('utf8mb4_bin');
            $table->unique(['id', 'kelompok'], 'responden_group_identity');
            $table->unique(['id', 'kelompok', 'tanggal_pulang'], 'responden_entry_identity');
            $table->index(['kelompok', 'status']);
            $table->timestamps(6);
        });

        Schema::create('kontak_responden', function (Blueprint $table) {
            $table->uuid('responden_id')->primary();
            $table->foreign('responden_id')->references('id')->on('responden')->restrictOnDelete()->restrictOnUpdate();
            $table->text('inisial_encrypted')->nullable();
            $table->text('no_hp_pasien_encrypted')->nullable();
            $table->text('no_hp_keluarga_encrypted')->nullable();
            $table->timestamps(6);
        });

        Schema::create('participant_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('responden_id');
            $table->foreignId('pengguna_id');
            $table->foreign('responden_id')->references('id')->on('responden')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('pengguna_id')->references('id')->on('pengguna')->restrictOnDelete()->restrictOnUpdate();
            $table->string('jenis', 16)->collation('utf8mb4_bin');
            $table->dateTime('mulai_pada', 6);
            $table->dateTime('berakhir_pada', 6)->nullable();
            $table->unique(['responden_id', 'pengguna_id', 'mulai_pada'], 'assignment_history_unique');
            $table->index(['pengguna_id', 'berakhir_pada']);
            $table->timestamps(6);
        });

        Schema::create('pengaturan', function (Blueprint $table) {
            $table->id();
            $table->string('kunci', 120)->collation('utf8mb4_bin');
            $table->json('nilai_json');
            $table->string('versi', 40)->collation('utf8mb4_bin');
            $table->dateTime('berlaku_mulai', 6)->nullable();
            $table->foreignId('disetujui_oleh')->nullable();
            $table->foreign('disetujui_oleh')->references('id')->on('pengguna')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('disetujui_pada', 6)->nullable();
            $table->unique(['kunci', 'versi']);
            $table->timestamps(6);
        });

        Schema::create('symptom_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengaturan_id');
            $table->foreign('pengaturan_id')->references('id')->on('pengaturan')->restrictOnDelete()->restrictOnUpdate();
            $table->string('kode_gejala', 48)->collation('utf8mb4_bin');
            $table->string('jawaban', 32)->collation('utf8mb4_bin');
            $table->unique(['id', 'kode_gejala', 'jawaban'], 'symptom_option_identity');
            $table->unique(['pengaturan_id', 'kode_gejala', 'jawaban'], 'symptom_option_unique');
            $table->timestamps(6);
        });

        Schema::create('follow_up_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengaturan_id');
            $table->foreign('pengaturan_id')->references('id')->on('pengaturan')->restrictOnDelete()->restrictOnUpdate();
            $table->string('kode', 48)->collation('utf8mb4_bin');
            $table->unique(['id', 'kode'], 'follow_up_option_identity');
            $table->unique(['pengaturan_id', 'kode']);
            $table->timestamps(6);
        });

        Schema::create('questionnaire_versions', function (Blueprint $table) {
            $table->id();
            $table->string('versi', 40)->collation('utf8mb4_bin')->unique();
            $table->json('definisi_item');
            $table->json('aturan_skoring')->nullable();
            $table->foreignId('disetujui_oleh')->nullable();
            $table->foreign('disetujui_oleh')->references('id')->on('pengguna')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('disetujui_pada', 6)->nullable();
            $table->timestamps(6);
        });

        Schema::create('entri_harian', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('responden_id');
            $table->string('kelompok', 16)->collation('utf8mb4_bin')->default('INTERVENSI');
            $table->date('tanggal_pulang');
            $table->date('tanggal_wib');
            $table->integer('hari_ke')->storedAs('DATEDIFF(tanggal_wib, tanggal_pulang)');
            $table->unsignedTinyInteger('vas_mm');
            $table->string('tipe', 12)->collation('utf8mb4_bin');
            $table->date('tanggal_harian')->nullable()->storedAs("CASE WHEN tipe = 'HARIAN' THEN tanggal_wib ELSE NULL END");
            $table->string('diisi_oleh', 12)->collation('utf8mb4_bin');
            $table->dateTime('dibuat_di_perangkat_pada', 6);
            $table->dateTime('diterima_server_pada', 6);
            $table->foreign(['responden_id', 'kelompok', 'tanggal_pulang'], 'entry_participant_identity')->references(['id', 'kelompok', 'tanggal_pulang'])->on('responden')->restrictOnDelete()->restrictOnUpdate();
            $table->unique(['responden_id', 'tanggal_harian'], 'one_daily_entry_per_wib_date');
            $table->unique(['id', 'responden_id'], 'entry_participant_pair');
            $table->index(['responden_id', 'tanggal_wib']);
            $table->index('diterima_server_pada');
            $table->timestamps(6);
        });

        Schema::create('jawaban_gejala', function (Blueprint $table) {
            $table->id();
            $table->uuid('entri_id');
            $table->foreign('entri_id')->references('id')->on('entri_harian')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('symptom_option_id');
            $table->string('kode_gejala', 48)->collation('utf8mb4_bin');
            $table->string('jawaban', 32)->collation('utf8mb4_bin');
            $table->foreign(['symptom_option_id', 'kode_gejala', 'jawaban'], 'answer_catalog_identity')->references(['id', 'kode_gejala', 'jawaban'])->on('symptom_options')->restrictOnDelete()->restrictOnUpdate();
            $table->unique(['entri_id', 'kode_gejala']);
            $table->timestamps(6);
        });

        Schema::create('klasifikasi', function (Blueprint $table) {
            $table->id();
            $table->uuid('entri_id');
            $table->foreign('entri_id')->references('id')->on('entri_harian')->restrictOnDelete()->restrictOnUpdate();
            $table->string('level', 12)->collation('utf8mb4_bin');
            $table->json('aturan_terpicu');
            $table->string('ruleset_key', 120)->collation('utf8mb4_bin')->default('classification');
            $table->string('ruleset_version', 40)->collation('utf8mb4_bin');
            $table->foreign(['ruleset_key', 'ruleset_version'], 'classification_ruleset_version')->references(['kunci', 'versi'])->on('pengaturan')->restrictOnDelete()->restrictOnUpdate();
            $table->unique(['entri_id', 'ruleset_version']);
            $table->timestamps(6);
        });

        Schema::create('peringatan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('responden_id');
            $table->string('kelompok', 16)->collation('utf8mb4_bin')->default('INTERVENSI');
            $table->foreign(['responden_id', 'kelompok'], 'alert_intervention_participant')->references(['id', 'kelompok'])->on('responden')->restrictOnDelete()->restrictOnUpdate();
            $table->unique(['id', 'responden_id'], 'alert_participant_pair');
            $table->foreign('responden_id')->references('id')->on('responden')->restrictOnDelete()->restrictOnUpdate();
            $table->uuid('entri_id')->nullable();
            $table->foreign(['entri_id', 'responden_id'], 'alert_entry_participant')->references(['id', 'responden_id'])->on('entri_harian')->restrictOnDelete()->restrictOnUpdate();
            $table->string('level', 12)->collation('utf8mb4_bin');
            $table->string('sebab', 60)->collation('utf8mb4_bin');
            $table->dateTime('dibuat_pada', 6);
            $table->dateTime('batas_respons_pada', 6)->nullable();
            $table->foreignId('dikonfirmasi_oleh')->nullable();
            $table->foreign('dikonfirmasi_oleh')->references('id')->on('pengguna')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('dikonfirmasi_pada', 6)->nullable();
            $table->dateTime('selesai_pada', 6)->nullable();
            $table->string('status', 24)->collation('utf8mb4_bin')->default('BARU');
            $table->index(['status', 'level', 'dibuat_pada']);
            $table->index(['responden_id', 'dibuat_pada']);
            $table->timestamps(6);
        });

        Schema::create('tindak_lanjut', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('peringatan_id');
            $table->foreign('peringatan_id')->references('id')->on('peringatan')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('perawat_id');
            $table->foreign('perawat_id')->references('id')->on('pengguna')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('waktu', 6);
            $table->text('hasil_asesmen')->nullable();
            $table->foreignId('follow_up_option_id');
            $table->string('keputusan', 48)->collation('utf8mb4_bin');
            $table->foreign(['follow_up_option_id', 'keputusan'], 'follow_up_catalog_identity')->references(['id', 'kode'])->on('follow_up_options')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedTinyInteger('vas_ulang')->nullable();
            $table->index(['peringatan_id', 'waktu']);
            $table->timestamps(6);
        });

        Schema::create('formulir_hasil', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('responden_id')->unique();
            $table->foreign('responden_id')->references('id')->on('responden')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedTinyInteger('vas_hari30')->nullable();
            $table->boolean('kekambuhan_a')->nullable();
            $table->boolean('kekambuhan_b')->nullable();
            $table->boolean('kekambuhan_c')->nullable();
            $table->boolean('kekambuhan_akhir')->nullable();
            $table->boolean('readmission')->nullable();
            $table->date('tanggal_readmission')->nullable();
            $table->text('rumah_sakit')->nullable();
            $table->string('sumber_data', 80)->collation('utf8mb4_bin')->nullable();
            $table->foreignId('dinilai_oleh');
            $table->foreign('dinilai_oleh')->references('id')->on('pengguna')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('tanggal_penilaian', 6);
            $table->foreignId('pengaturan_id')->nullable();
            $table->foreign('pengaturan_id')->references('id')->on('pengaturan')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamps(6);
        });

        Schema::create('usability', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('responden_id');
            $table->string('kelompok', 16)->collation('utf8mb4_bin')->default('INTERVENSI');
            $table->foreign(['responden_id', 'kelompok'], 'usability_intervention_participant')->references(['id', 'kelompok'])->on('responden')->restrictOnDelete()->restrictOnUpdate();
            $table->string('versi_kuesioner', 40)->collation('utf8mb4_bin');
            $table->foreign('versi_kuesioner')->references('versi')->on('questionnaire_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->json('jawaban_item');
            $table->decimal('skor_dampak', 4, 2)->nullable();
            $table->decimal('skor_kegunaan', 4, 2)->nullable();
            $table->decimal('skor_kemudahan', 4, 2)->nullable();
            $table->decimal('skor_kendali', 4, 2)->nullable();
            $table->decimal('skor_total', 4, 2)->nullable();
            $table->dateTime('tanggal_pengisian', 6);
            $table->string('pengisi', 24)->collation('utf8mb4_bin');
            $table->unique(['responden_id', 'versi_kuesioner']);
            $table->timestamps(6);
        });

        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable();
            $table->foreign('actor_id')->references('id')->on('pengguna')->restrictOnDelete()->restrictOnUpdate();
            $table->string('aksi', 80)->collation('utf8mb4_bin');
            $table->string('entity_type', 80)->collation('utf8mb4_bin');
            $table->string('entity_id', 40)->collation('utf8mb4_bin')->nullable();
            $table->dateTime('terjadi_pada', 6);
            $table->json('metadata_redacted')->nullable();
            $table->index(['entity_type', 'entity_id', 'terjadi_pada'], 'audit_entity_time');
            $table->index(['actor_id', 'terjadi_pada']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('peringatan_id')->nullable();
            $table->foreign(['peringatan_id', 'responden_id'], 'notification_alert_participant')->references(['id', 'responden_id'])->on('peringatan')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('peringatan_id')->references('id')->on('peringatan')->restrictOnDelete()->restrictOnUpdate();
            $table->uuid('responden_id')->nullable();
            $table->foreign('responden_id')->references('id')->on('responden')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('pengguna_id')->nullable();
            $table->foreign('pengguna_id')->references('id')->on('pengguna')->restrictOnDelete()->restrictOnUpdate();
            $table->string('channel', 16)->collation('utf8mb4_bin');
            $table->string('template_version', 40)->collation('utf8mb4_bin');
            $table->string('status', 16)->collation('utf8mb4_bin')->default('PLANNED');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('error_code', 64)->collation('utf8mb4_bin')->nullable();
            $table->dateTime('consent_recorded_at', 6)->nullable();
            $table->dateTime('last_attempt_at', 6)->nullable();
            $table->dateTime('delivered_at', 6)->nullable();
            $table->index(['status', 'last_attempt_at']);
            $table->timestamps(6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('usability');
        Schema::dropIfExists('formulir_hasil');
        Schema::dropIfExists('tindak_lanjut');
        Schema::dropIfExists('peringatan');
        Schema::dropIfExists('klasifikasi');
        Schema::dropIfExists('jawaban_gejala');
        Schema::dropIfExists('entri_harian');
        Schema::dropIfExists('questionnaire_versions');
        Schema::dropIfExists('follow_up_options');
        Schema::dropIfExists('symptom_options');
        Schema::dropIfExists('pengaturan');
        Schema::dropIfExists('participant_assignments');
        Schema::dropIfExists('kontak_responden');
        Schema::dropIfExists('responden');
        Schema::dropIfExists('role_assignments');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('pengguna');
    }
};
