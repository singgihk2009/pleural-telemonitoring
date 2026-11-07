# Prepared draft PR description

Title: **PR-11 — Patient Daily Assessment (Android + Laravel API + MySQL)**

Base: `main`; head: `feature/pr-11-patient-daily-assessment`; create as **Draft**.
GitHub GraphQL and REST POST both returned Forbidden, so no PR was opened by this task.
[Open the published branch PR form](https://github.com/singgihk2009/pleural-telemonitoring/pull/new/feature/pr-11-patient-daily-assessment).

---

Daily assessment is currently a wireframe without an approved symptom instrument. This draft adds an authenticated, version-gated technical workflow across Android, Laravel and MySQL. With no approved effective definition the app shows **Assessment belum tersedia**; no clinical catalog or schedule is seeded or inferred from PDF examples.

### Architecture and changed files

- API: `DailyInstrument`, `AssessmentController`, patient status/submission/history routes and a staff-only policy/query-scoped history route; per-principal throttling; explicit projections and safe audit metadata.
- MySQL: additive migration `2026_11_07_000006_add_daily_assessment_provenance.php` adds nullable immutable setting provenance and hidden retry fingerprint to `entri_harian`, with restrictive FK and paired-provenance CHECK. Historical entries/audits and existing constraints/triggers are preserved.
- Android: server-loaded F3 VAS/questions/author, confirmation, neutral receipt, loading/error/offline states and F9 history; stable in-memory retry UUID/body. Existing Router, SecureStore and login/PIN/session rules and other clinical placeholders remain intact; no new dependencies. A late old-token failure cannot clear a newer session.
- Dashboard: on-demand read-only assessment panel in the enrollment editor, with timestamp/version/answer observations and server-enforced monitoring permissions.
- Regression tests/CI: strict validation, isolation, RBAC, rollback, exact replay, duplicate day, real-process contention, reset/revocation after middleware, UI/recovery/navigation tests and immutable upgrade checks.

### API contract and concurrency

`GET /api/v1/patient/assessments/status` returns the applicable approved definition, server WIB date/day, own status and a non-secret submission UUID. `POST /api/v1/patient/assessments` accepts that UUID, version/date, integer VAS, author, device timestamp and exact catalog answers; participant identity is derived from Sanctum. Own history is `GET /api/v1/patient/assessments`; staff history is `GET /api/v1/participants/{participant}/assessments` with existing blinding, nurse assignment and independent CLINICAL_READ restrictions.

Participant then credential locks recheck eligibility/token validity and serialize against PR-10 changes. First commit is201; identical same-owner retry returns the original receipt200 without duplicate audit; conflicting body/UUID/day/version is409. Browser/app abort is not server cancellation. Pending retry identity/body lives only in component memory; after closure/logout/restart, reload status/history before entering again. No clinical classification, scoring, alerts or messaging side effects.

### Validation

- MySQL: **228 tests / 1,983 assertions PASS**; four opt-in migration checks separately PASS (13/13/19/9 assertions).
- Real processes: identical retry, changed-body and daily conflicts PASS; actual PIN reset/revocation committed after middleware prevent the waiting old-token submission. Existing PR-10 concurrency regression also PASS.
- Dashboard **49 tests PASS**; Android **55 tests PASS**; lint/typecheck, Composer/Pint, OpenAPI, dashboard build, Android JS/Hermes export, Compose/HTTP/CORS PASS locally.
- **Actual Android11/API30 Expo Go smoke PASS**, with isolated synthetic credentials/database: unavailable gate, VAS/questions/confirmation, committed response held across background/foreground then dropped, explicit retry receipt, own history and native logout with server token count1→0. Exactly one report/answer/submission audit and no clinical side effects. Actual browser reads the Android-submitted observation; no editing or browser storage.
- Hosted Actions require GitHub verification. No signed APK/physical-device/TalkBack claim.

### Documentation, screenshots and UAT

Architecture/recovery: `docs/assessment/README.md`; pre-code audit: `audit-pr11.md`; exact commands/results: `validation-pr11.md`; screenshots/PDF comparison: `screenshots/README.md`; synthetic API/device checklist: `UAT.md`. OpenAPI, Data Dictionary, ERD and security documentation updated.

![Default unavailable state](https://github.com/singgihk2009/pleural-telemonitoring/blob/feature/pr-11-patient-daily-assessment/docs/assessment/screenshots/01-unavailable.png?raw=true)
![Native server receipt](https://github.com/singgihk2009/pleural-telemonitoring/blob/feature/pr-11-patient-daily-assessment/docs/assessment/screenshots/05-server-receipt.png?raw=true)
![Read-only dashboard](https://github.com/singgihk2009/pleural-telemonitoring/blob/feature/pr-11-patient-daily-assessment/docs/assessment/screenshots/08-dashboard-submitted.png?raw=true)

Remaining dependencies: independently reviewed symptom definitions/options and supported same-day schedule; separate late/offline/repeat policies, publication workflow, server-versioned acknowledgement and final Health-ITUES instrument. Synthetic test approvals are not research approval. No clinical engine, deployed database reset/seed, server/Cloudflare change, release APK/EAS build, deployment or merge performed.
