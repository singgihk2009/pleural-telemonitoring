# TeleNapas Data Dictionary v1.0 — implemented PR-02 schema

Source: supplied SRS/Data Dictionary `1.0-draft`, 3 November 2026, plus the
explicit PR-02 approval and [review decisions](README.md). Indonesian table and
field names preserve the source vocabulary; Laravel model names are English.

All tables use InnoDB/utf8mb4 through the Laravel MySQL connection. Every FK
uses RESTRICT for UPDATE/DELETE; no cascades. UUID columns use CHAR(36),
Code/version VARCHAR columns use case-sensitive utf8mb4_bin collation; staff
name/email retain the default collation. BIGINT IDs are unsigned, booleans are nullable TINYINT(1) constrained to 0/1,
and JSON top-level shapes are validated. All tables except `audit_log` have
nullable Laravel `created_at`/`updated_at` TIMESTAMP(6) fields. Lifecycle times
are DATETIME(6) in UTC; DATE fields are Asia/Jakarta calendar dates. NULL means
missing/unknown, not false. Generated columns are read-only.

## pengguna

Staff accounts only; password hashes are hidden and cast with Laravel hashing. No login endpoints.

| Column | SQL type | Nullable / key / default | Meaning |
| --- | --- | --- | --- |
| id | BIGINT UNSIGNED | PRIMARY KEY, auto-increment | Internal ID. |
| nama | VARCHAR(120) | NOT NULL | Staff name, never patient identity. |
| email | VARCHAR(190) | NULL, UNIQUE | Nullable unique staff email. |
| password_hash | VARCHAR(255) | NOT NULL | Adaptive hash, hidden; no plaintext PIN/password storage. |
| status | VARCHAR(20) | NOT NULL, default 'AKTIF' | Explicit lifecycle state, constrained where defined below. |
## user_roles

Six research actor codes from the SRS; no privilege enforcement is implemented.

| Column | SQL type | Nullable / key / default | Meaning |
| --- | --- | --- | --- |
| id | BIGINT UNSIGNED | PRIMARY KEY, auto-increment | Internal ID. |
| kode | VARCHAR(40) | NOT NULL, UNIQUE | Unique study code or catalog/role code as identified by table. |
## role_assignments

Unique staff/role memberships; no implicit participant access.

| Column | SQL type | Nullable / key / default | Meaning |
| --- | --- | --- | --- |
| id | BIGINT UNSIGNED | PRIMARY KEY, auto-increment | Internal ID. |
| pengguna_id | BIGINT UNSIGNED | NOT NULL | Staff reference. |
| role_id | BIGINT UNSIGNED | NOT NULL | Research role reference. |

Keys and indexes (Laravel notation):

- `foreign('pengguna_id')->references('id')->on('pengguna')->restrictOnDelete()->restrictOnUpdate()`
- `foreign('role_id')->references('id')->on('user_roles')->restrictOnDelete()->restrictOnUpdate()`
- `unique(['pengguna_id', 'role_id'])`

## responden

Pseudonymous study identity, group, discharge day zero, consent metadata and baseline VAS. No contact fields.

| Column | SQL type | Nullable / key / default | Meaning |
| --- | --- | --- | --- |
| id | CHAR(36) | NOT NULL, PRIMARY KEY | Internal primary key (client-generated UUID for reports). |
| kode | VARCHAR(32) | NOT NULL, UNIQUE | Unique study code or catalog/role code as identified by table. |
| kelompok | VARCHAR(16) | NOT NULL | INTERVENSI or KONTROL; report/alert/usability discriminator must be INTERVENSI. |
| tanggal_pulang | DATE | NOT NULL | Day-zero date in Asia/Jakarta; in reports, must match the parent participant. |
| vas_baseline_mm | TINYINT UNSIGNED | NOT NULL | Stored baseline VAS in mm, 0–100; no baseline clinical definition or delta calculation. |
| status | VARCHAR(16) | NOT NULL, default 'AKTIF' | Explicit lifecycle state, constrained where defined below. |
| alasan_drop_out | TEXT | NULL | Required nonempty reason only for DROP_OUT; never include identity. |
| tanggal_consent | DATE | NOT NULL | Paper-consent date, metadata only. |
| versi_consent | VARCHAR(40) | NOT NULL | Paper-consent document version. |

Keys and indexes (Laravel notation):

- `unique(['id', 'kelompok'], 'responden_group_identity')`
- `unique(['id', 'kelompok', 'tanggal_pulang'], 'responden_entry_identity')`
- `index(['kelompok', 'status'])`

## kontak_responden

Restricted 1:1 identity/contact table. The three *_encrypted fields are Laravel encrypted casts and hidden from serialization.

| Column | SQL type | Nullable / key / default | Meaning |
| --- | --- | --- | --- |
| responden_id | CHAR(36) | NOT NULL, PRIMARY KEY | Participant reference; contact table also uses this as its primary key. |
| inisial_encrypted | TEXT | NULL | Encrypted participant initials; hidden. |
| no_hp_pasien_encrypted | TEXT | NULL | Encrypted participant contact; hidden. |
| no_hp_keluarga_encrypted | TEXT | NULL | Encrypted family contact; hidden. |

Keys and indexes (Laravel notation):

- `foreign('responden_id')->references('id')->on('responden')->restrictOnDelete()->restrictOnUpdate()`

## participant_assignments

Explicit monitoring/backup nurse assignment history with start/end times.

| Column | SQL type | Nullable / key / default | Meaning |
| --- | --- | --- | --- |
| id | BIGINT UNSIGNED | PRIMARY KEY, auto-increment | Internal ID. |
| responden_id | CHAR(36) | NOT NULL | Participant reference; contact table also uses this as its primary key. |
| pengguna_id | BIGINT UNSIGNED | NOT NULL | Staff reference. |
| jenis | VARCHAR(16) | NOT NULL | Assignment type PEMANTAU or CADANGAN. |
| mulai_pada | DATETIME(6) | NOT NULL | Assignment start, UTC. |
| berakhir_pada | DATETIME(6) | NULL | Nullable assignment end, UTC. |

Keys and indexes (Laravel notation):

- `foreign('responden_id')->references('id')->on('responden')->restrictOnDelete()->restrictOnUpdate()`
- `foreign('pengguna_id')->references('id')->on('pengguna')->restrictOnDelete()->restrictOnUpdate()`
- `unique(['responden_id', 'pengguna_id', 'mulai_pada'], 'assignment_history_unique')`
- `index(['pengguna_id', 'berakhir_pada'])`

## pengaturan

Immutable versioned configuration objects. No clinical values or activated settings are seeded.

| Column | SQL type | Nullable / key / default | Meaning |
| --- | --- | --- | --- |
| id | BIGINT UNSIGNED | PRIMARY KEY, auto-increment | Internal ID. |
| kunci | VARCHAR(120) | NOT NULL | Configuration namespace. |
| nilai_json | JSON | NOT NULL | Versioned object; no clinical configuration seeded. |
| versi | VARCHAR(40) | NOT NULL | Immutable version identifier. |
| berlaku_mulai | DATETIME(6) | NULL | Optional activation metadata after sign-off; not consumed by a runtime engine. |
| disetujui_oleh | BIGINT UNSIGNED | NULL | Nullable approving staff reference. |
| disetujui_pada | DATETIME(6) | NULL | Nullable approval timestamp, paired with approver. |

Keys and indexes (Laravel notation):

- `foreign('disetujui_oleh')->references('id')->on('pengguna')->restrictOnDelete()->restrictOnUpdate()`
- `unique(['kunci', 'versi'])`

## symptom_options

Exact versioned symptom-code/answer catalog; entries are test-only until approved research definitions are supplied.

| Column | SQL type | Nullable / key / default | Meaning |
| --- | --- | --- | --- |
| id | BIGINT UNSIGNED | PRIMARY KEY, auto-increment | Internal ID. |
| pengaturan_id | BIGINT UNSIGNED | NOT NULL | Versioned configuration reference. |
| kode_gejala | VARCHAR(48) | NOT NULL | Approved symptom code, no clinical default. |
| jawaban | VARCHAR(32) | NOT NULL | Approved response value matching catalog. |

Keys and indexes (Laravel notation):

- `foreign('pengaturan_id')->references('id')->on('pengaturan')->restrictOnDelete()->restrictOnUpdate()`
- `unique(['id', 'kode_gejala', 'jawaban'], 'symptom_option_identity')`
- `unique(['pengaturan_id', 'kode_gejala', 'jawaban'], 'symptom_option_unique')`

## follow_up_options

Versioned follow-up decision catalog; no clinical choices are invented or seeded.

| Column | SQL type | Nullable / key / default | Meaning |
| --- | --- | --- | --- |
| id | BIGINT UNSIGNED | PRIMARY KEY, auto-increment | Internal ID. |
| pengaturan_id | BIGINT UNSIGNED | NOT NULL | Versioned configuration reference. |
| kode | VARCHAR(48) | NOT NULL | Unique study code or catalog/role code as identified by table. |

Keys and indexes (Laravel notation):

- `foreign('pengaturan_id')->references('id')->on('pengaturan')->restrictOnDelete()->restrictOnUpdate()`
- `unique(['id', 'kode'], 'follow_up_option_identity')`
- `unique(['pengaturan_id', 'kode'])`

## questionnaire_versions

Immutable final-instrument definition/scoring provenance, when supplied. No Health-ITUES instrument or scores are invented.

| Column | SQL type | Nullable / key / default | Meaning |
| --- | --- | --- | --- |
| id | BIGINT UNSIGNED | PRIMARY KEY, auto-increment | Internal ID. |
| versi | VARCHAR(40) | NOT NULL, UNIQUE | Immutable version identifier. |
| definisi_item | JSON | NOT NULL | Ordered instrument-definition array, unseeded. |
| aturan_skoring | JSON | NULL | Nullable scoring-definition object, unseeded and not evaluated. |
| disetujui_oleh | BIGINT UNSIGNED | NULL | Nullable approving staff reference. |
| disetujui_pada | DATETIME(6) | NULL | Nullable approval timestamp, paired with approver. |

Keys and indexes (Laravel notation):

- `foreign('disetujui_oleh')->references('id')->on('pengguna')->restrictOnDelete()->restrictOnUpdate()`

## entri_harian

Client-UUID reports. Composite group/discharge reference prevents control enrollment or forged study dates; generated columns implement study day and daily-only uniqueness.

| Column | SQL type | Nullable / key / default | Meaning |
| --- | --- | --- | --- |
| id | CHAR(36) | NOT NULL, PRIMARY KEY | Internal primary key (client-generated UUID for reports). |
| responden_id | CHAR(36) | NOT NULL | Participant reference; contact table also uses this as its primary key. |
| kelompok | VARCHAR(16) | NOT NULL, default 'INTERVENSI' | INTERVENSI or KONTROL; report/alert/usability discriminator must be INTERVENSI. |
| tanggal_pulang | DATE | NOT NULL | Day-zero date in Asia/Jakarta; in reports, must match the parent participant. |
| tanggal_wib | DATE | NOT NULL | Operational date in Asia/Jakarta. |
| hari_ke | INT | NOT NULL, GENERATED STORED | Generated elapsed calendar days since discharge (1–30). |
| vas_mm | TINYINT UNSIGNED | NOT NULL | Stored VAS in mm, 0–100; no color computed. |
| tipe | VARCHAR(12) | NOT NULL | HARIAN or ULANG. |
| tanggal_harian | DATE | NULL, GENERATED STORED | Generated tanggal_wib for HARIAN, otherwise NULL; never writable. |
| diisi_oleh | VARCHAR(12) | NOT NULL | PASIEN or KELUARGA. |
| dibuat_di_perangkat_pada | DATETIME(6) | NOT NULL | Untrusted client-reported time normalized to UTC; retained unchanged. |
| diterima_server_pada | DATETIME(6) | NOT NULL | Trusted receipt timestamp supplied by future ingestion service, UTC. |

Keys and indexes (Laravel notation):

- `foreign(['responden_id', 'kelompok', 'tanggal_pulang'], 'entry_participant_identity')->references(['id', 'kelompok', 'tanggal_pulang'])->on('responden')->restrictOnDelete()->restrictOnUpdate()`
- `unique(['responden_id', 'tanggal_harian'], 'one_daily_entry_per_wib_date')`
- `unique(['id', 'responden_id'], 'entry_participant_pair')`
- `index(['responden_id', 'tanggal_wib'])`
- `index('diterima_server_pada')`

## jawaban_gejala

One answer per entry/symptom, matching an exact catalog option. Immutable after submission.

| Column | SQL type | Nullable / key / default | Meaning |
| --- | --- | --- | --- |
| id | BIGINT UNSIGNED | PRIMARY KEY, auto-increment | Internal ID. |
| entri_id | CHAR(36) | NOT NULL | Report reference; nullable on alerts unrelated to a particular report. |
| symptom_option_id | BIGINT UNSIGNED | NOT NULL | Exact symptom catalog option reference. |
| kode_gejala | VARCHAR(48) | NOT NULL | Approved symptom code, no clinical default. |
| jawaban | VARCHAR(32) | NOT NULL | Approved response value matching catalog. |

Keys and indexes (Laravel notation):

- `foreign('entri_id')->references('id')->on('entri_harian')->restrictOnDelete()->restrictOnUpdate()`
- `foreign(['symptom_option_id', 'kode_gejala', 'jawaban'], 'answer_catalog_identity')->references(['id', 'kode_gejala', 'jawaban'])->on('symptom_options')->restrictOnDelete()->restrictOnUpdate()`
- `unique(['entri_id', 'kode_gejala'])`

## klasifikasi

Manually supplied classification storage, one per entry/ruleset version. No evaluator, automatic label or alert exists.

| Column | SQL type | Nullable / key / default | Meaning |
| --- | --- | --- | --- |
| id | BIGINT UNSIGNED | PRIMARY KEY, auto-increment | Internal ID. |
| entri_id | CHAR(36) | NOT NULL | Report reference; nullable on alerts unrelated to a particular report. |
| level | VARCHAR(12) | NOT NULL | Stored classification HIJAU/KUNING/MERAH, or alert KUNING/MERAH. |
| aturan_terpicu | JSON | NOT NULL | Array of externally supplied triggered rule codes, never derived here. |
| ruleset_key | VARCHAR(120) | NOT NULL, default 'classification' | Fixed classification namespace for the composite version FK. |
| ruleset_version | VARCHAR(40) | NOT NULL | Immutable configuration version reference. |

Keys and indexes (Laravel notation):

- `foreign('entri_id')->references('id')->on('entri_harian')->restrictOnDelete()->restrictOnUpdate()`
- `foreign(['ruleset_key', 'ruleset_version'], 'classification_ruleset_version')->references(['kunci', 'versi'])->on('pengaturan')->restrictOnDelete()->restrictOnUpdate()`
- `unique(['entri_id', 'ruleset_version'])`

## peringatan

Manually supplied intervention alert state and nullable response deadline. No SLA/default clinical cause is configured.

| Column | SQL type | Nullable / key / default | Meaning |
| --- | --- | --- | --- |
| id | CHAR(36) | NOT NULL, PRIMARY KEY | Internal primary key (client-generated UUID for reports). |
| responden_id | CHAR(36) | NOT NULL | Participant reference; contact table also uses this as its primary key. |
| kelompok | VARCHAR(16) | NOT NULL, default 'INTERVENSI' | INTERVENSI or KONTROL; report/alert/usability discriminator must be INTERVENSI. |
| entri_id | CHAR(36) | NULL | Report reference; nullable on alerts unrelated to a particular report. |
| level | VARCHAR(12) | NOT NULL | Stored classification HIJAU/KUNING/MERAH, or alert KUNING/MERAH. |
| sebab | VARCHAR(60) | NOT NULL | Externally supplied cause code; no invented clinical causes. |
| dibuat_pada | DATETIME(6) | NOT NULL | Alert creation time, UTC. |
| batas_respons_pada | DATETIME(6) | NULL | Nullable explicit deadline, UTC; no SLA computed. |
| dikonfirmasi_oleh | BIGINT UNSIGNED | NULL | Nullable acknowledging staff reference. |
| dikonfirmasi_pada | DATETIME(6) | NULL | Nullable acknowledgement time, paired with actor. |
| selesai_pada | DATETIME(6) | NULL | Nullable completion time, UTC. |
| status | VARCHAR(24) | NOT NULL, default 'BARU' | Explicit lifecycle state, constrained where defined below. |

Keys and indexes (Laravel notation):

- `foreign(['responden_id', 'kelompok'], 'alert_intervention_participant')->references(['id', 'kelompok'])->on('responden')->restrictOnDelete()->restrictOnUpdate()`
- `unique(['id', 'responden_id'], 'alert_participant_pair')`
- `foreign('responden_id')->references('id')->on('responden')->restrictOnDelete()->restrictOnUpdate()`
- `foreign(['entri_id', 'responden_id'], 'alert_entry_participant')->references(['id', 'responden_id'])->on('entri_harian')->restrictOnDelete()->restrictOnUpdate()`
- `foreign('dikonfirmasi_oleh')->references('id')->on('pengguna')->restrictOnDelete()->restrictOnUpdate()`
- `index(['status', 'level', 'dibuat_pada'])`
- `index(['responden_id', 'dibuat_pada'])`

## tindak_lanjut

Nurse action history referencing catalogued decisions. Assessment text is encrypted/hidden. No decision workflow is implemented.

| Column | SQL type | Nullable / key / default | Meaning |
| --- | --- | --- | --- |
| id | CHAR(36) | NOT NULL, PRIMARY KEY | Internal primary key (client-generated UUID for reports). |
| peringatan_id | CHAR(36) | NOT NULL | Alert reference; participant recipients must match alert participant. |
| perawat_id | BIGINT UNSIGNED | NOT NULL | Acting staff reference; role authorization remains future work. |
| waktu | DATETIME(6) | NOT NULL | Action timestamp, UTC. |
| hasil_asesmen | TEXT | NULL | Nullable encrypted assessment text, hidden. |
| follow_up_option_id | BIGINT UNSIGNED | NOT NULL | Exact versioned decision option reference. |
| keputusan | VARCHAR(48) | NOT NULL | Exact catalog decision code, no clinical default. |
| vas_ulang | TINYINT UNSIGNED | NULL | Nullable repeat VAS in mm, 0–100. |

Keys and indexes (Laravel notation):

- `foreign('peringatan_id')->references('id')->on('peringatan')->restrictOnDelete()->restrictOnUpdate()`
- `foreign('perawat_id')->references('id')->on('pengguna')->restrictOnDelete()->restrictOnUpdate()`
- `foreign(['follow_up_option_id', 'keputusan'], 'follow_up_catalog_identity')->references(['id', 'kode'])->on('follow_up_options')->restrictOnDelete()->restrictOnUpdate()`
- `index(['peringatan_id', 'waktu'])`

## formulir_hasil

One final outcome for either group, preserving NULL versus false. Optional configuration provenance; no recurrence/readmission derivation. Hospital text uses encrypted TEXT rather than draft VARCHAR(255).

| Column | SQL type | Nullable / key / default | Meaning |
| --- | --- | --- | --- |
| id | CHAR(36) | NOT NULL, PRIMARY KEY | Internal primary key (client-generated UUID for reports). |
| responden_id | CHAR(36) | NOT NULL, UNIQUE | Participant reference; contact table also uses this as its primary key. |
| vas_hari30 | TINYINT UNSIGNED | NULL | Nullable final VAS in mm, 0–100. |
| kekambuhan_a | TINYINT(1) | NULL | Nullable observed criterion A; definition pending. |
| kekambuhan_b | TINYINT(1) | NULL | Nullable observed criterion B; definition pending. |
| kekambuhan_c | TINYINT(1) | NULL | Nullable observed criterion C; definition pending. |
| kekambuhan_akhir | TINYINT(1) | NULL | Nullable supplied final outcome; never derived. |
| readmission | TINYINT(1) | NULL | Nullable observed/verified readmission value, distinct from missing. |
| tanggal_readmission | DATE | NULL | Nullable readmission date, requires readmission=true when present. |
| rumah_sakit | TEXT | NULL | Nullable encrypted hospital text, hidden. |
| sumber_data | VARCHAR(80) | NULL | Nullable source code; final catalog pending. |
| dinilai_oleh | BIGINT UNSIGNED | NOT NULL | Assessor staff reference. |
| tanggal_penilaian | DATETIME(6) | NOT NULL | Assessment timestamp, UTC. |
| pengaturan_id | BIGINT UNSIGNED | NULL | Versioned configuration reference. |

Keys and indexes (Laravel notation):

- `foreign('responden_id')->references('id')->on('responden')->restrictOnDelete()->restrictOnUpdate()`
- `foreign('dinilai_oleh')->references('id')->on('pengguna')->restrictOnDelete()->restrictOnUpdate()`
- `foreign('pengaturan_id')->references('id')->on('pengaturan')->restrictOnDelete()->restrictOnUpdate()`

## usability

Raw answer array and nullable explicit scores tied to a questionnaire version. No scoring is performed or guessed score bounds enforced.

| Column | SQL type | Nullable / key / default | Meaning |
| --- | --- | --- | --- |
| id | CHAR(36) | NOT NULL, PRIMARY KEY | Internal primary key (client-generated UUID for reports). |
| responden_id | CHAR(36) | NOT NULL | Participant reference; contact table also uses this as its primary key. |
| kelompok | VARCHAR(16) | NOT NULL, default 'INTERVENSI' | INTERVENSI or KONTROL; report/alert/usability discriminator must be INTERVENSI. |
| versi_kuesioner | VARCHAR(40) | NOT NULL | Questionnaire-version reference. |
| jawaban_item | JSON | NOT NULL | Raw ordered JSON answer array; NULL elements represent missing responses. |
| skor_dampak | DECIMAL(4,2) | NULL | Nullable explicit subscale/total score; no scoring or guessed bounds. |
| skor_kegunaan | DECIMAL(4,2) | NULL | Nullable explicit subscale/total score; no scoring or guessed bounds. |
| skor_kemudahan | DECIMAL(4,2) | NULL | Nullable explicit subscale/total score; no scoring or guessed bounds. |
| skor_kendali | DECIMAL(4,2) | NULL | Nullable explicit subscale/total score; no scoring or guessed bounds. |
| skor_total | DECIMAL(4,2) | NULL | Nullable explicit subscale/total score; no scoring or guessed bounds. |
| tanggal_pengisian | DATETIME(6) | NOT NULL | Form completion timestamp, UTC. |
| pengisi | VARCHAR(24) | NOT NULL | PASIEN or KELUARGA. |

Keys and indexes (Laravel notation):

- `foreign(['responden_id', 'kelompok'], 'usability_intervention_participant')->references(['id', 'kelompok'])->on('responden')->restrictOnDelete()->restrictOnUpdate()`
- `foreign('versi_kuesioner')->references('versi')->on('questionnaire_versions')->restrictOnDelete()->restrictOnUpdate()`
- `unique(['responden_id', 'versi_kuesioner'])`

## audit_log

Append-only explicit events, not automatic interception. Safe metadata keys only; identifiers/action codes must not contain patient data.

| Column | SQL type | Nullable / key / default | Meaning |
| --- | --- | --- | --- |
| id | BIGINT UNSIGNED | PRIMARY KEY, auto-increment | Internal ID. |
| actor_id | BIGINT UNSIGNED | NULL | Nullable actor reference; NULL for system activity. |
| aksi | VARCHAR(80) | NOT NULL | Action code, no clinical/person data. |
| entity_type | VARCHAR(80) | NOT NULL | Entity-type code. |
| entity_id | VARCHAR(40) | NULL | Opaque object reference; polymorphic target intentionally not an FK. |
| terjadi_pada | DATETIME(6) | NOT NULL | Event timestamp, UTC. |
| metadata_redacted | JSON | NULL | Optional object with request_id/purpose_code/outcome_code only. No sensitive content. |

Keys and indexes (Laravel notation):

- `foreign('actor_id')->references('id')->on('pengguna')->restrictOnDelete()->restrictOnUpdate()`
- `index(['entity_type', 'entity_id', 'terjadi_pada'], 'audit_entity_time')`
- `index(['actor_id', 'terjadi_pada'])`

## notification_deliveries

Inert delivery bookkeeping, exactly one participant/staff recipient. No payload, address, token, provider integration or dispatch code.

| Column | SQL type | Nullable / key / default | Meaning |
| --- | --- | --- | --- |
| id | CHAR(36) | NOT NULL, PRIMARY KEY | Internal primary key (client-generated UUID for reports). |
| peringatan_id | CHAR(36) | NULL | Alert reference; participant recipients must match alert participant. |
| responden_id | CHAR(36) | NULL | Participant reference; contact table also uses this as its primary key. |
| pengguna_id | BIGINT UNSIGNED | NULL | Staff reference. |
| channel | VARCHAR(16) | NOT NULL | FCM or WHATSAPP, metadata only. |
| template_version | VARCHAR(40) | NOT NULL | Generic message-template version, never a message payload. |
| status | VARCHAR(16) | NOT NULL, default 'PLANNED' | Explicit lifecycle state, constrained where defined below. |
| attempts | INT UNSIGNED | NOT NULL, default 0 | Delivery-attempt count, initially zero. |
| error_code | VARCHAR(64) | NULL | Optional uppercase machine code, never raw provider text. |
| consent_recorded_at | DATETIME(6) | NULL | Nullable channel-consent timestamp, UTC. |
| last_attempt_at | DATETIME(6) | NULL | Nullable attempt time, UTC. |
| delivered_at | DATETIME(6) | NULL | Nullable delivered time, UTC; consistency checked with SENT state. |

Keys and indexes (Laravel notation):

- `foreign(['peringatan_id', 'responden_id'], 'notification_alert_participant')->references(['id', 'responden_id'])->on('peringatan')->restrictOnDelete()->restrictOnUpdate()`
- `foreign('peringatan_id')->references('id')->on('peringatan')->restrictOnDelete()->restrictOnUpdate()`
- `foreign('responden_id')->references('id')->on('responden')->restrictOnDelete()->restrictOnUpdate()`
- `foreign('pengguna_id')->references('id')->on('pengguna')->restrictOnDelete()->restrictOnUpdate()`
- `index(['status', 'last_attempt_at'])`

## Database CHECK constraints and immutability

The following checks are enforced by MySQL in addition to the keys above.

- **pengguna.user_status**: `status IN ('AKTIF','NONAKTIF')`
- **user_roles.research_role**: `kode IN ('PASIEN_KELUARGA','PERAWAT_PEMANTAU','PERAWAT_CADANGAN','PENELITI_UTAMA','PENILAI_HASIL','ADMIN_IT')`
- **responden.participant_group**: `kelompok IN ('INTERVENSI','KONTROL')`
- **responden.participant_status**: `status IN ('AKTIF','SELESAI','DROP_OUT')`
- **responden.dropout_reason**: `(status = 'DROP_OUT' AND alasan_drop_out IS NOT NULL AND CHAR_LENGTH(TRIM(alasan_drop_out)) > 0) OR (status <> 'DROP_OUT' AND alasan_drop_out IS NULL)`
- **participant_assignments.assignment_kind**: `jenis IN ('PEMANTAU','CADANGAN')`
- **pengaturan.configuration_object**: `JSON_TYPE(nilai_json) = 'OBJECT'`
- **questionnaire_versions.questionnaire_items**: `JSON_TYPE(definisi_item) = 'ARRAY'`
- **questionnaire_versions.questionnaire_scoring**: `aturan_skoring IS NULL OR JSON_TYPE(aturan_skoring) = 'OBJECT'`
- **entri_harian.client_uuid**: `id REGEXP '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$'`
- **entri_harian.intervention_entry**: `kelompok = 'INTERVENSI'`
- **entri_harian.entry_type**: `tipe IN ('HARIAN','ULANG')`
- **entri_harian.entry_author**: `diisi_oleh IN ('PASIEN','KELUARGA')`
- **klasifikasi.classification_level**: `level IN ('HIJAU','KUNING','MERAH')`
- **klasifikasi.classification_ruleset**: `ruleset_key = 'classification'`
- **klasifikasi.classification_triggers**: `JSON_TYPE(aturan_terpicu) = 'ARRAY'`
- **peringatan.alert_level**: `level IN ('KUNING','MERAH')`
- **peringatan.alert_status**: `status IN ('BARU','DIKONFIRMASI','DITINDAKLANJUTI','SELESAI')`
- **peringatan.alert_state_timestamps**: `(status = 'BARU' AND dikonfirmasi_pada IS NULL AND selesai_pada IS NULL) OR (status IN ('DIKONFIRMASI','DITINDAKLANJUTI') AND dikonfirmasi_pada IS NOT NULL AND selesai_pada IS NULL) OR (status = 'SELESAI' AND dikonfirmasi_pada IS NOT NULL AND selesai_pada IS NOT NULL)`
- **usability.intervention_usability**: `kelompok = 'INTERVENSI'`
- **usability.usability_author**: `pengisi IN ('PASIEN','KELUARGA')`
- **usability.usability_answers**: `JSON_TYPE(jawaban_item) = 'ARRAY'`
- **audit_log.audit_metadata_keys**: `metadata_redacted IS NULL OR (JSON_TYPE(metadata_redacted) = 'OBJECT' AND JSON_LENGTH(JSON_REMOVE(metadata_redacted, '$.request_id', '$.purpose_code', '$.outcome_code')) = 0)`
- **audit_log.audit_metadata_tokens**: `metadata_redacted IS NULL OR ((JSON_EXTRACT(metadata_redacted, '$.request_id') IS NULL OR (JSON_TYPE(JSON_EXTRACT(metadata_redacted, '$.request_id')) = 'STRING' AND JSON_UNQUOTE(JSON_EXTRACT(metadata_redacted, '$.request_id')) REGEXP '^[A-Za-z0-9_-]{1,80}$')) AND (JSON_EXTRACT(metadata_redacted, '$.purpose_code') IS NULL OR (JSON_TYPE(JSON_EXTRACT(metadata_redacted, '$.purpose_code')) = 'STRING' AND JSON_UNQUOTE(JSON_EXTRACT(metadata_redacted, '$.purpose_code')) REGEXP '^[A-Za-z0-9_-]{1,80}$')) AND (JSON_EXTRACT(metadata_redacted, '$.outcome_code') IS NULL OR (JSON_TYPE(JSON_EXTRACT(metadata_redacted, '$.outcome_code')) = 'STRING' AND JSON_UNQUOTE(JSON_EXTRACT(metadata_redacted, '$.outcome_code')) REGEXP '^[A-Za-z0-9_-]{1,80}$')))`
- **notification_deliveries.notification_channel**: `channel IN ('FCM','WHATSAPP')`
- **notification_deliveries.notification_status**: `status IN ('PLANNED','PENDING','SENT','FAILED','CANCELLED')`
- **notification_deliveries.notification_error_code**: `error_code IS NULL OR error_code REGEXP '^[A-Z0-9_]{1,64}$'`
- **notification_deliveries.notification_delivery_time**: `(status = 'SENT' AND delivered_at IS NOT NULL AND last_attempt_at IS NOT NULL AND attempts > 0 AND delivered_at >= last_attempt_at) OR (status <> 'SENT' AND delivered_at IS NULL)`
- **notification_deliveries.whatsapp_consent**: `channel <> 'WHATSAPP' OR status IN ('PLANNED','CANCELLED') OR consent_recorded_at IS NOT NULL`
- **peringatan.intervention_alert**: `kelompok = 'INTERVENSI'`

Database triggers reject UPDATE/DELETE for submitted entries, symptom responses,
classifications and audit rows. They reject UPDATE for versioned settings,
questionnaire definitions, symptom options and follow-up options. The triggers
do not classify, decide, send or automatically audit anything. Referenced records
cannot be deleted because all FKs restrict deletion. Unreferenced version/catalog
rows are deletable; there is no automatic cleanup.

Encryption requires the Laravel model write path and managed application key;
the database cannot distinguish encrypted ciphertext from raw strings. Never
use raw SQL to write identity fields. Role memberships, approvals, timestamps
and audit codes record provenance, not proof of actual human authorization.

## PR-11 additive daily assessment amendment

See [daily assessment v1.4](daily-assessment-v1.4.md) for nullable immutable
`entri_harian.assessment_setting_id` (restrictive FK), hidden
`submission_fingerprint` CHAR(64), paired-provenance CHECK, existing-row behavior
and canonical retry semantics. `pengaturan` daily_assessment versions and exact
symptom catalog references are consumed only after approval/effectivity/shape
validation. No clinical definitions, scores or schedules are seeded.
