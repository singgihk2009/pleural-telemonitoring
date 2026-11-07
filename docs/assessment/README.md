# Patient daily assessment — PR-11

This is a technical foundation for synthetic UAT, not a clinical decision system.
No daily instrument is activated or seeded. The [pre-code audit](audit-pr11.md)
records the missing approvals. Without a supported approved instrument, Android
shows **Assessment belum tersedia** and the API rejects new submissions.

## Architecture and approval gate

`DailyInstrument` reads the latest effective immutable `pengaturan` row with
`kunci=daily_assessment`. It requires approver/time, an active PENELITI_UTAMA
approver without ADMIN_IT/PENILAI_HASIL/PASIEN_KELUARGA, a supported JSON format
and matching `symptom_options`. Tied effective times, an invalid latest definition,
foreign/missing option IDs and unsupported schedules fail closed. Future versions
are not yet applicable. There is no fallback to illustrative wireframe content.
`questionnaire_versions` remains Health-ITUES/usability; no reuse or scoring.

The technical JSON contract (not an approved questionnaire):

- `format`: `telenapas.daily.v1`.
- `schedule`: explicit `timezone=Asia/Jakarta`, integer `first_day/last_day`
  within existing DB bounds 1–30, ordered, and `late_policy=same_day_only`.
  These values must be approved with the instrument; supporting the adapter does
  not approve a schedule. Hours, offline/late handling and repeats are unsupported.
- `questions`: ordered nonempty list (max 50) of `code`, `label`, boolean `required`
  and `options` (max 20). Each option has the immutable `symptom_options.id` and
  approved display `label`. Its setting and symptom code must match. Codes and
  IDs must be distinct. No answer value or clinical meaning is invented by the app.
- Approval/effective time/version are the existing relational columns, not JSON
  flags supplied by the patient. New versions require new immutable rows/options.

A separately reviewed research content/publication workflow must provide the
actual definition, options, consent/initial acknowledgement integration, approved
same-day schedule, translation and UAT approval. PR-11 provides no publication
endpoint, default clinical catalog, seed, approval switch or deployment command.
Existing settings approvals record provenance, not proof of a signed instrument.

## API and transaction guarantees

All routes require the existing expiring `api` Sanctum bearer token and active
principal middleware; no cookies. Shared assessment throttle: 30/minute/principal.
Responses have no-store/private cache headers. See [OpenAPI](../api/openapi.yaml).

| Method / path under `/api/v1` | Behavior |
| --- | --- |
| GET `/patient/assessments/status` | Patient-only own server-WIB date/day, applicable public definition, eligibility, today's receipt and nonsecret random `submission_id` for a new attempt. ID is not a reservation. |
| POST `/patient/assessments` | Required UUID, version, date_wib, integer VAS, filled_by, UTC device timestamp and exact answers. Participant ID/group/day-zero/server time are never client inputs. |
| GET `/patient/assessments?page=N` | Own immutable history, 25/page, latest date/ID first, including legacy entries with null version. |
| GET `/participants/{participant}/assessments?page=N` | Existing staff monitoring policy AND query scope, safe explicit projection, privileged-access audit. No editing endpoint. |

POST locks participant then patient credential in the same order as PR-10 and
rechecks active intervention membership, credential revocation, token existence,
ability and expiration **inside** the transaction. PIN operations are unchanged.
A reset/revocation that acquires the participant lock first causes the old token's
submission to be rejected; a submission that commits first remains valid history.

For new UUIDs, the server requires the applicable version, approved same-day WIB
schedule, matching required answers/options, and no existing daily entry. It
inserts `entri_harian`, `jawaban_gejala` and one `assessment.submitted` audit in a
single transaction. Existing unique/FK/check/immutable constraints remain active.
No classification, alert, score, message or notification is created.

The UUID is the idempotency identity. Canonical validated request values (including
UUID/version/date/time/answers, sorted by code) are fingerprinted. An exact replay
for the same owner returns the original receipt with HTTP 200/replayed=true and
no second mutation audit; first commit is HTTP 201/replayed=false. Changed payload,
foreign UUID, different UUID for the same date, changed version or ineligible date
returns 409 with no partial write. Replay checks occur before current date/version
checks but after current authentication/eligibility, allowing recovery across
midnight/version changes while the participant/session remains authorized.
A legacy entry without provenance/fingerprint cannot be impersonated as a retry.

422 means invalid/missing/unapproved answers or unexpected fields, 401 means no
valid active session, 403 means wrong principal/read permission, 429 means wait,
503 means storage failed with rollback. SQL failure responses/log messages exclude
bindings and sensitive values. Audit metadata contains only a fixed success code
and opaque entry UUID; no answers, VAS, identifiers, PINs or bearer tokens.

## Android and recovery

F3 retains neutral VAS with **no default**, then approved structured symptom
choices, PASIEN/KELUARGA, a review/confirmation step and explicit submission.
The help bar stays available. Successful storage shows a neutral receipt and WIB
timestamp, not the PDF's proposed colored clinical feedback. F9 shows paginated
stored observations, not invented classification/compliance or a fabricated graph.
Home labels now describe server availability; history remains accessible after
day 30 while the participant/session remains active. Placeholder repeat, event, messaging and final-questionnaire actions remain
unavailable. UI uses existing scalable 16sp text/52dp buttons and wraps long labels.

The stable UUID, device timestamp and body live only in component memory for an
uncertain retry. Repeated taps are blocked synchronously. A timeout/abort/app close
is **not server cancellation**. Retry with the same identity/body while the screen
remains open. The form is frozen after an uncertain send; no silent changed-body
retry. Explicit reload discards the draft and checks server status. If the screen
was closed, the app/process restarted or logout occurred, first reload status and
history. Today's committed report blocks a new submission; the database unique
constraint still prevents duplication if the first request commits after that read.
No background/offline auto-upload, device-clock trust or browser/file persistence.
After midnight an uncommitted old-day draft is rejected; late reporting needs its
own approved workflow. Backgrounding hides form content and does not initiate a
second write; returning keeps the in-memory operation. Logout/session change
unmounts/discards the draft and ignores late responses. An old-token authentication
failure cannot clear a newer session; current-token401/403 still invalidates it.

The existing initial acknowledgement remains local/session scoped, as documented
in PR-05; server-versioned acknowledgement is not retroactively claimed here.
An Android export is not an APK/native validation; see the validation report.

## Researcher view

The enrollment editor offers an on-demand read-only assessment panel (date, day,
VAS, author, immutable version, answer codes/values, receipt time). It has no edit
controls. Access is enforced by Laravel even if the UI is bypassed. Blinded
assessor combinations are denied, nurses need a current assignment and ADMIN_IT
needs an independent CLINICAL_READ grant; TECHNICAL/CONTACT never imply it.
The enrollment editor itself retains its stricter researcher/contact restrictions.

See [UAT](UAT.md), [validation](validation-pr11.md) and
[database amendment](../database/daily-assessment-v1.4.md).
