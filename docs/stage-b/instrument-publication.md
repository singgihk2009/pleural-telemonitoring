# B1 — Explicit approved instrument publication

Branch feature/stage-b-instrument-publication, base main be6702e (merged PR11).

## Workflow and approval

An active non-assessor/non-IT PENELITI_UTAMA reviews a separately approved
instrument and external approval reference. No illustrative PDF item is supplied
as a clinical instrument. GET /api/v1/research/daily-instruments reviews immutable
versions, approval/effectivity/expiry and current version. POST
/api/v1/research/daily-instruments/publish requires explicit confirmation, version,
expected_current_version (nullable), approval_reference (opaque code, no patient
information), effective_at/expires_at (UTC milliseconds/Z), schedule and questions.
OpenAPI specifies the full body. No automatic job, UI mount, migration or seeder
publishes a definition. Review the complete JSON before the explicit POST.

Questions specify code, label, boolean required and options [{value,label}]. Values
are fixed approved codes, not free clinical interpretation. The server allocates
safe-integer random option IDs, inserts immutable settings/options once, and links
an immutable publication receipt. A rare PK collision fails atomically409, never
rewrites a definition. No plaintext credential, patient ID or scoring field belongs
in this API. Server derives approval actor/time and records a fixed-code audit.
The reference records provenance, not cryptographic proof of external approval.

Schedule is explicit and limited to the existing same_day_only/Asia/Jakarta adapter
and days within storage bounds1–30; no default schedule/hours/late policy is inferred.
Future versions activate only when effective; expired current versions fail closed
without resurrecting an older instrument. Legacy signed settings remain compatible
with PR11 and have no invented expiry; new publications always require expiry.
No mutation/withdrawal API or clinical decision system is added: versions/receipts
are immutable and require human governance; emergency withdrawal is separate work.

## Integrity and permissions

Existing seeded researcher-role row serializes publication transactions. Then the
staff principal is locked and active authority/token existence/ability/expiration
rechecked. Current-version mismatch, duplicate version/effective time and elapsed
activation return409. The global role lock is held only for a publication, never
patient status GET. Settings, options, receipt and publication audit commit together.
Invalid inputs422, permission401/403, rate429, storage failure503 sanitized/no bindings.
Approver disabled/role changes still invalidate applicable definitions. Existing
blinding, independent IT clinical grants, participant identity and PIN logic remain.

Migration2026_11_08_000007 adds only instrument_publications, restrictive setting FK,
approval reference, unique effective time, expiry CHECK and UPDATE/DELETE rejection
triggers. It seeds nothing. Rollback drops only the new table; never rollback a
populated research schema. Use migration administrator on a new disposable schema,
restricted app user afterward. Historical records/options are untouched. API rollout
requires the additive migration first. The demo safety check now enumerates only
its selected schema, preserving its emptiness gate and avoiding other DB table names.

## Manual UAT and visual comparison

Use disposable MySQL and synthetic test identities only. Without an approved row,
Android must retain “Assessment belum tersedia”. Operator supplies approved JSON
via a private local file and existing short-lived bearer header file (mode0600):

```sh
curl --fail-with-body --header @"$UAT_STAFF_HEADER" "$UAT_API_BASE/research/daily-instruments"
curl --fail-with-body --header @"$UAT_STAFF_HEADER" -H 'Content-Type: application/json' \
  --data-binary @approved-instrument.json "$UAT_API_BASE/research/daily-instruments/publish"
```

Review reference, version, schedule, content and timestamps before POST; no real
content is provided by this task. Synthetic fixtures belong only to isolated tests.
Verify unauthorized/combined roles cannot list/publish; future is unavailable,
then applicable, then expired; duplicate/stale actions409 without partial data.
Read history after expiry; no reports or clinical alerts are created by publication.
Remove private headers/files and revoke local test tokens afterward.

No Android UI source changes in B1. The unchanged F3/F9 baseline screenshots and
PDF comparison remain in ../assessment/screenshots/README.md; these are PR11
captures, not fresh native evidence for B1. Instrument expiry/rendering should be
included in signed APK/device UAT after human review. No release/EAS build or deploy.

See gap-analysis.md and validation-b1.md. Missing approved real clinical content is
an explicit activation blocker; this API does not constitute approval of any item.
