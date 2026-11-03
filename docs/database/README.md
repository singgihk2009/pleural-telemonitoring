# TeleNapas database foundation (PR-02)

PR-02 introduced an additive MySQL 8.4 schema, Laravel models and synthetic test
factories. PR-03 adds [authentication schema extensions](authentication-v1.1.md)
and [server-enforced authorization](../security/authentication-rbac.md). No clinical
engine, scheduled clinical jobs, migrations at server startup, real data or
external message senders are included.

- [Data Dictionary v1.0](data-dictionary-v1.0.md)
- [ERD](erd.md)
- [Explicit ADMIN_IT grant-scope dictionary v1.2](grant-scopes-v1.2.md)
- [Versioned research requirements and approved VAS bands](../research/requirements-v1.0.md)

## Review decisions before implementation

The existing checkout had no research tables or migrations; PR-01 explicitly
excluded these. The PR-02 request authorizes this scope and the new branch;
`AGENTS.md` now records that exception. The attachment's draft approval language
and clinical examples are distinguished from the explicit user approval in the
requirements document. No clinical proposals from the attachment are activated.

The dictionary's ambiguous classification uniqueness is resolved as
`UNIQUE(entri_id, ruleset_version)`: historical versions coexist, each immutable.
Baseline VAS remains `responden.vas_baseline_mm`, not duplicated in a second
table. Contact data is in a separate 1:1 table. Outcome is one final form per
participant, including controls. Usability is one form per participant/instrument
version; this makes the form identity explicit without defining eligibility or
scoring beyond the intervention group.

Technical additions to the draft are versioned symptom/follow-up catalogs,
questionnaire versions, research roles and explicit nurse assignments, sign-off
metadata, nullable outcome-definition provenance, append-only triggers and
notification-delivery metadata. No patient credentials are created: patient
login, messages, event reports, educational content, escalation workflows and
exports are deferred to separately scoped work. Roles/assignments are storage,
not permission grants by themselves. PR-03 now supplies server-side policies
and scoped reads; see its access matrix. Real data use still requires separately
approved governance and operational controls.

## Integrity and privacy

IDs are UUIDs for participants/client entries/forms/alerts and unsigned BIGINTs
for staff and catalog/detail records. All foreign keys restrict update/delete;
there are no cascading deletions. An entry carries its participant group and
discharge date with a composite FK to `responden`. This intentional technical
redundancy makes intervention eligibility and date calculations enforceable by
MySQL, and prevents parent edits from invalidating historical reports.
`hari_ke` is generated from WIB calendar dates. `tanggal_harian` is generated
only for `HARIAN`; a unique participant/date index rejects duplicate daily
reports while multiple `ULANG` entries remain valid. The received timestamp and
untrusted device timestamp never define the study day. A future ingestion layer
must convert the intended operational date to Asia/Jakarta, distinguish trusted
server time, and implement identical-UUID retry responses and payload comparison;
this PR guarantees uniqueness and no overwrite, not an ingestion API.

Database CHECKs enforce VAS 0–100, days 1–30, allowed groups/statuses, nullable
boolean 0/1 values, catalog identity, alert acknowledgement/time consistency,
recipient identity, delivery bookkeeping and version references. They validate
structure, not clinical appropriateness or authorization. Arbitrary JSON must be
valid JSON with the declared top-level object/array type. Approved final
questionnaire/ruleset-specific validation is deferred; no guessed score bounds,
missing-answer semantics, symptom options or follow-up choices are seeded.

`entri_harian`, symptom responses, classifications and audit rows reject UPDATE
and DELETE even through direct SQL. Settings, questionnaire definitions and
catalog options reject UPDATE. Foreign keys prevent deletion of referenced
versions. Audit is append-only and stores object identifiers, action/time/actor
and optional metadata with only `request_id`, `purpose_code`, `outcome_code` keys;
values are short scalar tokens (letters, digits, underscore or hyphen). Never
put names, contact details, free-text notes, PINs or tokens in those values.
This is not automatic audit capture or a content-redaction engine: future write
and access services must explicitly create safe audit events.

`ParticipantContact` encrypts its three identity/contact fields using Laravel's
application encryption key and hides them from serialization. Follow-up assessment
text and outcome hospital text are also encrypted/hidden. Tests verify ciphertext
at rest and decryption. Query-builder/raw SQL bypasses model encryption; use the
models for these writes. Schema separation/encryption does not grant access
control. Production key storage, rotation, backup/recovery, consent, data access,
retention and deidentification remain separate prerequisites. Never store direct
identity in study codes, drop-out reasons, clinical notes, JSON or audit fields.

Date-only operational fields use WIB; application datetimes are written/read in
UTC with microsecond precision. All lifecycle/receipt/audit fields are
`DATETIME(6)`; Laravel `created_at`/`updated_at` use `TIMESTAMP(6)` in UTC. Keep
application/connection timezone UTC; MySQL DATETIME itself does not carry a zone.
Unknown outcome booleans remain NULL, distinct from false. No recurrence or
Health-ITUES score is calculated. Notification rows contain channel, generic
template version, recipient FK, consent timestamp and delivery-state metadata;
there is no payload, address, device token, provider SDK or sender. WhatsApp
non-draft delivery states require consent metadata, but no transmission occurs.

## Migrations and validation

From `apps/api`, install locked Composer dependencies, then run `php artisan
migrate --pretend` for review and `php artisan migrate` only against the intended
new development schema. Migration execution is deliberately not added to Compose
startup. Do not use `migrate:fresh`, rollback or seeds on an existing research or
development database. `down()` exists for disposable test validation only; it
drops the newly added tables and is not a data-retention procedure. MySQL DDL is
not transactional: review errors before retrying a partially applied migration.

Use a schema-administrator migration connection. Creating triggers with MySQL
binary logging enabled may require SUPER in addition to schema privileges when
`log_bin_trust_function_creators` is disabled. Do not grant broad administrative
rights to a runtime application account or relax server policy to work around
this. CI applies migrations using its disposable service's root account and
runs integrity tests with the restricted `pleural` user. No production privilege
changes are performed by these migrations.

Integrity tests require a dedicated disposable MySQL database whose name ends in
`_test`, e.g. `pleural_pr02_test`. Provision it empty and grant the test application
user rights only to that schema; apply migrations with the administrator first.
Then, from `apps/api`, with that test database selected in the process environment:

```sh
composer validate --strict
composer lint
APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=pleural_pr02_test composer test
```

`DatabaseFoundationTest` applies only pending migrations, uses transactions and
rolls back fixture writes. It refuses a non-test MySQL database. SQLite runs skip
MySQL-specific integrity tests explicitly; they are not a substitute for this
suite. Existing liveness/CORS tests remain portable. No patient tables in the
existing `pleural_dev` database were migrated during PR-02 validation.

The opt-in lifecycle test requires its **own initially empty** database ending
in `_migration_test` and administrative migration privileges:

```sh
APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=pleural_migration_test \
  TELENAPAS_MIGRATION_TESTS=1 php artisan test --filter=MigrationLifecycleTest
```

It applies, rolls back and reapplies the migrations only in that disposable
schema. CI runs it separately. Do not enable the flag against a reused schema.
Factories use `SYN-` study codes, `SYNTHETIC` labels, `example.invalid` staff mail
and deliberately non-dialable contact strings. Catalogs/instruments are explicitly
test-only, with no clinical meaning. There is no automatic factory seeder.
