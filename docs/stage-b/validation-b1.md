# B1 validation — 8 November 2026 WIB

Tests use newly created local schemas, no deployed data or server configuration.
Commands use docker compose -f compose.yaml -f /workspace/pleural-setup/compose.cloud.json
exec -T api, PHP8.4/MySQL8.4. No FK/trigger disabled, no reset/reseed of deployed data.

Publication tests cover future/current/expired versions, immutable receipt, audit,
role combinations with blinding off, anonymous/patient denial, missing approval/
confirmation, unsupported schedule/catalog, duplicate/stale writes and audit rollback.
Full results are recorded before push below. Existing Android source is unchanged;
no fresh native screenshot is relabelled from the PR11 baseline.

Required local commands: composer validate --strict; composer lint; DB_DATABASE=
pleural_stage_b1_verified_test composer test; pnpm lint; pnpm typecheck; pnpm test;
pnpm validate:openapi; pnpm build; CI=1 pnpm build:mobile. New empty-schema lifecycle
and historical upgrade checks use TELENAPAS_MIGRATION_TESTS=1 only in new schemas.
Hosted CI/PR creation currently blocked by GitHub API Forbidden, distinct from
local results. Signed device APK/TalkBack and real instrument publication NOT VERIFIED.

| Check | Actual result |
| --- | --- |
| Full Laravel/MySQL | PASS 237 tests / 2,039 assertions; 4 opt-in checks below |
| Final focused publication suite | PASS 9 tests / 56 assertions |
| Migration lifecycle / auth / grants / assessment upgrades | PASS 13 / 13 / 19 / 9 assertions, each new isolated schema |
| Credential and assessment real-process races | PASS, including reset/revocation after middleware |
| Composer strict / Pint | PASS |
| Workspace lint / typecheck / tests | PASS; mobile55 and dashboard49 baseline cases retained |
| OpenAPI / dashboard build / Android JS export | PASS; not an APK or native device test |
| Fresh native publication/expiry UI | NOT VERIFIED; no Android source changed; PR11 captures explicitly labelled baseline |
| Hosted Actions / signed physical-device UAT | NOT VERIFIED |

Race schemas: pleural_b1_credential_concurrency_test and
pleural_b1_assessment_concurrency_test. Commands APP_ENV=testing BCRYPT_ROUNDS=4
php tests/Concurrency/credential_operations.php and daily_assessments.php after
administrator migration. Migration schemas pleural_b1_{migration,auth_migration,
grant_scope_migration,assessment_migration}_test use the existing matching opt-in
PHPUnit filters. No existing schema was dropped or reset. The initial mutex-row
prototype was discarded before commit because it conflicted with the demo's strict
empty-data guard. Publication instead locks the existing seeded researcher-role row.
