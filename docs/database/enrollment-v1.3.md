# Enrollment Data Dictionary supplement v1.3 (PR-04)

No table, column, historical migration, existing CHECK/FK or immutable trigger is
changed. PR-04 uses the [v1.0 participant/contact dictionary](data-dictionary-v1.0.md)
and PR-03 auth/RBAC. This supplement defines the validated researcher write paths.

| Existing field / table | Enrollment management behavior |
| --- | --- |
| responden.id | Server-generated UUID, never accepted from a client |
| responden.kode | Required unique study code, max 32; immutable after enrollment |
| responden.kelompok | INTERVENSI/KONTROL, selected by researcher; immutable after enrollment |
| responden.tanggal_pulang | Valid ISO DATE, day 0 in Asia/Jakarta; frozen once research history exists |
| responden.vas_baseline_mm | Required strict JSON integer 0–100; frozen with history; no clinical interpretation |
| responden.tanggal_consent | Required valid DATE metadata for paper consent; editable with safe audit |
| responden.versi_consent | Required nonempty VARCHAR metadata, max 40 |
| responden.status | AKTIF (default), SELESAI or DROP_OUT; non-active state revokes patient tokens |
| responden.alasan_drop_out | Mandatory nonempty reason only for DROP_OUT; no direct identifiers; API max 1,000; omitted from lists |
| responden.updated_at | Existing TIMESTAMP(6) used as an exact optimistic version; no new version column |
| kontak_responden.*_encrypted | Optional model-encrypted initials/patient/family contacts; omitted inputs preserve, null clears; no plaintext SQL write path |
| audit_log | Append-only staff/action/fixed outcome for enrollment and updates; no participant reference or values in these new events |

The editor returns an `updated_at` string (or null for legacy rows) and
`baseline_locked`. PATCH requires that exact `expected_updated_at`; successful
writes advance the timestamp even when only contact data changed. A stale write
gets 409. An explicit null version is accepted only while the stored version is
also null, then normal version checks apply. Code/group must never be included
in PATCH. History checks cover daily entries, alerts, final outcomes and usability
(and thus their dependent classifications/symptoms/follow-ups). All prior rows
and constraints are retained. These are technical research-integrity decisions,
not clinical classification or a new baseline definition.

Parent-row locking, validation, contact encryption, token revocation and audit
writes run transactionally. Controls never receive app credentials through
enrollment or updates. Optional contacts stay in their existing separate table.
No identity fields are added to pseudonymous research records. The schema ERD is
unchanged. See [enrollment implementation and operational notes](../enrollment/README.md)
and [OpenAPI](../api/openapi.yaml).
