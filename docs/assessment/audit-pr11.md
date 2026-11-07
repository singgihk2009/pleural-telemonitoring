# PR-11 repository and research audit (7 November 2026, WIB)

Baseline: main `7637fac`, merged PR-10 including credential revisions. No PIN,
authentication lifetime, logout or SecureStore semantics need changing.

Reviewed: all five migrations; daily/symptom/settings/questionnaire, participant,
credential and audit models/factories; Sanctum active-principal middleware;
participant policies/query scopes; enrollment/auth/participant controllers;
routes, OpenAPI and security tests; Expo Router screens, API/session/storage;
Data Dictionary, research requirements v1.0, supplied SRS draft and visual PDF
(in particular F3/F4, history and offline proposals).

## Findings before implementation

- `entri_harian` already enforces intervention-only matching parent/day-zero,
  integer VAS 0–100, days 1–30, UUID identity, one HARIAN per participant/WIB date.
  Entries and symptom answers are immutable. Reuse these constraints.
- `jawaban_gejala` references exact immutable `symptom_options`, but entries do
  not yet record the instrument/settings version. Add nullable provenance and a
  retry fingerprint, without changing existing records or constraints.
- `pengaturan` provides immutable JSON versions, approver/time and effective time;
  `symptom_options` can represent approved answers. No approved daily instrument
  is seeded. `questionnaire_versions` backs Health-ITUES/usability, so it must not
  be repurposed as a daily clinical instrument.
- Research requirements approve storage range and dictionary day window, not the
  final symptom catalog, clock/late policy, scoring or emergency criteria. The SRS
  is explicitly draft; PDF page 6 says some answer choices require confirmation.
  Document examples are evidence, not authority to enable proposed clinical rules.
- The current F3 offers a neutral VAS and disabled illustrative symptoms; history
  is a placeholder. There is no assessment ingestion, approved-version API or
  idempotency implementation. No read-only dashboard monitoring panel exists;
  its existing participant entries API is policy/query scoped but lacks timestamps.
- Blinded assessors and technical/contact-only IT must remain denied monitoring.
  Existing researcher/contact denial and PR-10 must remain unchanged.

## Implementation boundary

Define a technical adapter for a separately approved `daily_assessment` setting,
including ordered question labels, exact catalog options and explicit supported
same-day WIB schedule. No migration/seeder creates or approves such a setting.
Unsupported, unsigned, future, ambiguous or malformed definitions fail closed.
Approval of this JSON format is not approval of its clinical contents or schedule.
Without that approval the real app shows **Assessment belum tersedia** and cannot
submit. Only fabricated test fixtures exercise enabled submission. No scoring,
classification, alerts, notifications, repeat entries or late/offline ingestion.
