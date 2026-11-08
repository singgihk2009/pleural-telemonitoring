# B2 validation — 8 November 2026 WIB

Base: merged PR11 main `be6702e`. Queue producer `57b92f8`; recovery uses
`0305c86` (same persisted schema, additionally requires confirmed active AppState
and labels an in-progress secure write). Documentation commits do not change runtime.
All data are fabricated and restricted to local disposable `_test` schemas.

| Check | Result | Evidence / limits |
|---|---|---|
| MySQL 8.4 / PHP 8.4 API suite | PASS | 230 tests, 2006 assertions; four opt-ins separately |
| Migration lifecycle / auth upgrade / grant upgrade / assessment upgrade | PASS | Fresh dedicated schemas; 13 / 13 / 19 / 9 assertions |
| Real-process credential and assessment races | PASS | Separate processes; immutable constraints and token serialization preserved |
| Mobile Jest | PASS | 94 tests, including lost response, restart, UUID/body stability, midnight/version change, 429, scopes and storage failures |
| Dashboard | PASS | 49 existing tests |
| Lint / typecheck / OpenAPI / Composer / Pint | PASS | Local checks; hosted Actions not accessible |
| Dashboard build / Android JavaScript export | PASS | Export is not an APK or native UAT |
| SDK dependency compatibility | PASS (offline manifest) | Expo install --check; external directory/online doctor unavailable |
| Native Android | PASS — tested scenarios | Android 11/API30 x86_64 emulator, Expo Go 58.0.5; not signed physical-device APK |
| GitHub Actions / signed APK / hardware backup restore | NOT VERIFIED | Requires repository API access and separately approved native build/UAT |

Native sequence passed: encrypted queue with delivery blocked; zero server writes;
AES/GCM native envelope/no plaintext markers; unchanged ciphertext after device reboot;
cold offline lock; reconnect and commit201 with response withheld; process termination;
restart and exact UUID history receipt with only one POST; server one entry/answer/audit,
zero classification/alert/notification side effects. See screenshot index for scope.

## Repeatable automated checks

Use Node24 and repository-pinned pnpm10.28.2. Run `pnpm lint`, `pnpm typecheck`,
`pnpm test`, `pnpm validate:openapi`, `pnpm build`, `CI=1 pnpm build:mobile`.
From apps/api: `composer validate --strict`, `composer lint`, and
`APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=pleural_b2_test composer test`.
Create a new disposable schema first; never use a deployed research database.
Run the four opt-in migration suites and credential/assessment concurrency scripts
following their existing guard requirements. They must use separate fresh schemas.
Do not substitute a skipped SQLite run for MySQL trigger/RBAC verification.

## Manual synthetic UAT

1. Create a new isolated test schema and synthetic intervention patient using existing
   guarded test factories; do not reseed/reset an existing database. Initially leave
   the instrument unavailable. Login, acknowledge, open Daily Assessment: no submission.
2. Add only the test fixture instrument in that isolated schema. Confirm VAS begins
   unselected, required answers/author gate review, and no risk result is computed.
3. Block delivery before confirming. Review, queue, inspect neutral pending text.
   Verify zero server entries/audits, native encrypted storage, and no plaintext payload.
4. Force-stop and reboot without clearing app data. Verify ciphertext survives.
   Cold-start offline: no authenticated draft access until server identity validation.
5. Restore connectivity/session. Hold the first server201 after commit; force-stop
   before delivering it. Restart/revalidate, reconcile exact UUID via own history.
   Require one server entry, one answer and one submission audit, no second POST,
   and zero classifications/alerts/notifications. Screenshot actual receipt/history.
6. Logout: confirm server token deletion and hidden draft. Re-login same revision
   may recover; different patient/revision must not. Run automated scope/rotation tests.
7. Test 0/100, missing answers, changed/expired instrument, WIB midnight,409,422,
   401/403,429 and exhausted backoff using isolated fixtures/fault injection. Never
   change a rejected report's date/body/UUID. Unsupported cases stay blocked visibly.
8. Test maximum approved instrument size and signed physical APK backup behavior
   separately before real UAT. No EAS build is authorized by this task.

## Rollback / operational limits

No database migration. Stop foreground synchronization before rollback; reconcile
pending UUIDs against server history first. Preserve encrypted records and API
credential_revision metadata during any rollback. Removing that metadata or the
native crypto module can lock pending copies; never silently clear storage to fix it.
No background synchronization, offline identity verification, signed APK verification,
forensic erase guarantee, clinical schedule approval or real instrument approval.
Retention (seven-day quarantine) and old-revision recovery require human governance.
Review B1/B2/B3 together in an isolated integration environment before deployment.

GitHub API access in this environment returns Forbidden, including draft creation;
Git transport remains available. PR text is prepared in PR-B2.md. No hosted check,
review approval, pull-request number or merge is inferred from a successful push.

Native logout PASS: existing UI logout deleted the synthetic patient's sole server
token (1→0); the login screen replaced authenticated draft/history access.
