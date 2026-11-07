# PR-11 validation — 7 November 2026 (WIB)

Base main: `7637fac`. Branch: `feature/pr-11-patient-daily-assessment`.
Synthetic fixtures only. No deployed database reset/seed, Ubuntu/Cloudflare change,
clinical engine, notification, release APK/cloud build, production deployment or merge.

## Automated and service checks

| Check | Result |
| --- | --- |
| Complete Laravel/MySQL suite | PASS — 228 tests / 1,983 assertions; opt-in migration checks run separately below |
| New daily assessment feature cases | PASS — 22 cases, including approved synthetic success, required/invalid/foreign/duplicate answers, missing instrument, malformed/future/tied versions, dates, revoked/expired/ineligible/control sessions, owner isolation, exact retry/conflict, rollback, IT scope combinations, nurse assignment, blinding and throttle |
| Real simultaneous assessment requests | PASS — two separate PHP processes/MySQL connections reach a held participant lock before release; same UUID/body201+200 with identical receipt, different UUID same date201+409, same UUID changed body201+409; one immutable entry/answer/audit; actual reset/revocation endpoints committed after middleware prevent the blocked old-token writer; no clinical side effects |
| Existing PR-10 concurrent credential regression | PASS — reset/reset, reset/revoke, revision receipts, nonlocking status read and token revocation |
| Dashboard | PASS — 49 tests including on-demand immutable history, denial, expiry and abandoned reads; existing enrollment/PIN tests pass |
| Android JavaScript/native-component tests | PASS — 55 tests including old-session failure isolation, approved loading/required choices/review/success, unavailable instrument, error states, duplicate taps, timeout stable-body retry, close/reopen recovery, identity change, background/foreground and history; existing login/session tests pass |
| Lint / TypeScript | PASS — dashboard and mobile, no lint warnings |
| Composer strict validation / Pint | PASS |
| OpenAPI 3.1 validation | PASS |
| Dashboard production build | PASS |
| Android JS/Hermes export | PASS — not an APK or physical-device test |
| Empty-schema migration lifecycle | PASS — 13 assertions; rollback/reapply only in a newly created empty test schema |
| Historical authentication upgrade | PASS — 13 assertions |
| Grant-scope upgrade | PASS — 19 assertions |
| PR-11 upgrade with existing immutable records | PASS — 9 assertions; legacy report/audit unchanged, new fields NULL, immutable deletion triggers intact |
| Compose syntax / HTTP / CORS | PASS — isolated API /up and health200, dashboard200; approved origin matches ACAO and unapproved origin does not |
| Hosted GitHub Actions | NOT VERIFIED — GitHub API returns Forbidden in this execution environment |
| Actual Android Expo Go/emulator | PASS — synthetic login, unavailable gate, VAS/questions/confirmation, committed-response hold across background/foreground, dropped-response retry and own history; screenshots linked below |
| Actual researcher browser | PASS — login, empty then submitted read-only history against the same isolated API; no editing or browser storage |
| Signed release APK / physical device | NOT VERIFIED — not built, deployed or installed by this task |

No dependency/lockfile, EAS, PIN controller/model, SecureStore or Compose changes.
The patient session wrapper accepts an optional authenticated request body and
ignores stale-token authentication failures when a newer session has replaced it;
current-session failure handling and PIN/login/expiry/logout/storage behavior are
preserved. Existing security tests
remain passing. Expo58 versioned documentation fetch was attempted but the cloud
proxy returned403; the code uses existing React Native/AppState and Router APIs
already present in this repository, with no new Expo/native dependencies.

## Commands and databases

From repository root, pinned Node24/pnpm10.28.2 (locally invoked with corepack):

```sh
pnpm lint
pnpm typecheck
pnpm test
pnpm --filter @pleural/mobile test
pnpm --filter @pleural/dashboard test
pnpm validate:openapi
pnpm build
CI=1 EXPO_NO_TELEMETRY=1 pnpm build:mobile
```

API commands from apps/api using the existing PHP8.4 container and MySQL8.4:

```sh
composer validate --strict
composer lint
DB_DATABASE=pleural_pr11_final_test composer test
APP_ENV=testing BCRYPT_ROUNDS=4 DB_DATABASE=pleural_pr11_session_race_assessment_concurrency_test \
  php tests/Concurrency/daily_assessments.php
APP_ENV=testing BCRYPT_ROUNDS=4 DB_DATABASE=pleural_pr11_credential_concurrency_test \
  php tests/Concurrency/credential_operations.php
TELENAPAS_MIGRATION_TESTS=1 DB_DATABASE=pleural_pr11_migration_test \
  php artisan test --filter=MigrationLifecycleTest
TELENAPAS_MIGRATION_TESTS=1 DB_DATABASE=pleural_pr11_auth_migration_test \
  php artisan test --filter=AuthenticationMigrationTest
TELENAPAS_MIGRATION_TESTS=1 DB_DATABASE=pleural_pr11_grant_scope_migration_test \
  php artisan test --filter=GrantScopeMigrationTest
TELENAPAS_MIGRATION_TESTS=1 DB_DATABASE=pleural_pr11_assessment_migration_test \
  php artisan test --filter=DailyAssessmentMigrationTest
```

Local wrapper: `docker compose -f compose.yaml -f /workspace/pleural-setup/compose.cloud.json exec -T`.
Database environment variables above were supplied with `-e` to that wrapper;
migrations/opt-in checks used the disposable schema administrator and ordinary
feature/concurrency tests used the restricted app DB user. Each concurrency and
migration schema was newly created; no previous schema was dropped/reset.

An initial new-schema migration attempt under the restricted user was rejected by
MySQL trigger privileges. Its partial test schema was left untouched; a separate
new schema was migrated as administrator, then tests ran as the restricted user.
No trigger, FK or server variable was disabled to make tests pass.

CI adds the real assessment concurrency command and isolated upgrade check to its
existing ephemeral MySQL job. These local results are separate from hosted CI.

## Native and browser evidence

Actual Android11/API30 AOSP x86_64, installed Expo Go58.0.5, emulator37.2.13,
software acceleration/SwiftShader, 640×1280px at density320. No release APK was
built. The emulator status bar uses UTC; application study date/receipt labels use
Asia/Jakarta as required. All device/API fixtures lived in the separate new
`pleural_pr11_native_test` schema, never the deployed/shared development schema.

Native controls first logged in and rendered **Assessment belum tersedia** with no
instrument. Only then was a fabricated `SyntheticDailyInstrument` added to that
isolated fixture schema. It explicitly labels its item/options synthetic and is
not a clinical instrument or UAT publication. Actual taps selected a VAS and
required option/author, reviewed and submitted. A loopback-only test proxy held
the real successful201 response after commit; the app was backgrounded then
foregrounded before that response was deliberately dropped. The app's explicit
retry recovered the receipt, and F9 loaded its own record. Database verification
found exactly one entry, answer and submission audit and zero classification,
alert or notification records. The receipt retained its original server time.
The real browser then displayed that same synthetic answer read-only. Native
logout returned to login and the sole patient token count dropped from1 to0.
The final stale-session-error guard was subsequently added and covered by the
55-test suite; it has no visual/UI change. The native captures precede that
guard-only refinement and do not claim a native reproduction of that extra race.

Local evidence commands: `python /tmp/pr11-device.py`,
`python /tmp/pr11-enabled-native.py`, `python /tmp/pr11-browser.py`, and
`python /tmp/pr11-browser-submitted.py`, followed by
`python /tmp/pr11-native-logout-finish.py`, using the private fixture file and local
ADB reverse/HTTP fault harness. These session-specific scripts/credentials are
not committed; automated portable regressions are in the repository. No token,
PIN or secret is included in the screenshots. See the
[screenshot index and PDF comparison](screenshots/README.md).

Native automation initially targeted non-editable labels and an adjustable label
without Android's appended value. The harness was corrected to target EditText
nodes and the actual accessible value label; these were test-harness failures,
not evidence of a passing smoke test. The final flow above was actually executed.
TalkBack, alternate devices/OS versions, large system fonts and a signed standalone
APK remain NOT VERIFIED and are listed in UAT; Jest checks existing scalable16sp
body styles and >=48dp actionable targets, and native screenshots demonstrate
scrolling/wrapping on the small test viewport.

## Research and UAT limits

The wireframe/SRS do not establish a final approved symptom catalog or late/clock
policy. No instrument is published; the actual default is unavailable. Synthetic
success tests prove transport, authorization and data integrity, not clinical
validity. All PDF clinical colors/deltas/scoring/alerts remain inactive. Content
and supported schedule require independent researcher approval/publication before
real UAT activation. Final Health-ITUES, repeat scheduling, server-versioned initial
acknowledgement and offline/late ingestion remain separate work.

See [architecture](README.md), [pre-code audit](audit-pr11.md),
[UAT/recovery procedure](UAT.md), [schema](../database/daily-assessment-v1.4.md)
and [visual evidence](screenshots/README.md).
