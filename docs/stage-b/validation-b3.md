# B3 validation — 8 November 2026 WIB

Independent branch based on merged PR11 main be6702e. Synthetic data only, fresh
local MySQL8.4/PHP8.4 test schemas. No deployed server or production database touched.

| Check | Result | Actual evidence |
|---|---|---|
| Full MySQL API suite | PASS | 231 tests /2036 assertions; four opt-ins run separately |
| Migration lifecycle/auth/grant/assessment upgrades | PASS | Separate fresh schemas,13/13/19/9 assertions |
| Real-process credential/assessment concurrency | PASS | Correct CAS/revocation, immutable UUID replay/daily conflicts, audit consistency |
| Dashboard tests | PASS | 53, including legacy API echo denial, date validation, pagination, stale reads and no outer form submission |
| Existing mobile tests | PASS | 55; this independent branch does not contain B2's new94-test suite |
| Lint/typecheck/OpenAPI/Composer/Pint | PASS | Local CI-equivalent commands |
| Dashboard build/Android JavaScript export | PASS | JS export is not native/device verification |
| Actual Chromium UAT | PASS | Staff login; Android-produced report; immutable labels; inclusive filters; empty result; Enter sends no enrollment PATCH; incompatible API hides results; no browser storage; awaited server logout |
| Composed three-branch API suite | PASS | 242 tests /2115 assertions in a test-only source copy/new isolated schema; no Git merge |
| Explicit cross-branch workflow | PASS | Opt-in tests/Integration/stage_b_workflow.php; publication→ingestion→UUID recovery→filter/labels→expiry→logout; transactional fixture rollback |
| Fresh B3 native Android / signed physical APK | NOT VERIFIED | No Android source changed; B2 actual emulator evidence remains branch-specific |
| Hosted GitHub Actions / PR creation | NOT VERIFIED / BLOCKED | GitHub API returns Forbidden; normal Git push is available |

## Commands and fixture guards

Repository-pinned pnpm10.28.2 /Node24: `pnpm lint`, `pnpm typecheck`, `pnpm test`,
`pnpm validate:openapi`, `pnpm build`, `CI=1 pnpm build:mobile`.
API: `composer validate --strict`, `composer lint`,
`APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=pleural_stage_b3_test composer test`.
Apply pending migrations only to a newly created disposable schema first using its
schema administrator. Run four opt-in upgrade/lifecycle tests in separate new
`_migration_test` schemas and both existing tests/Concurrency scripts in their own
`_credential_concurrency_test` / `_assessment_concurrency_test` schemas per CI.
No migrate:fresh, truncation, deployed rollback/reseed, trigger/FK disable or production
credential is needed. The demo enumeration fix selects only the active schema and
continues rejecting any nonempty research dataset.

## Cross-branch compatibility evidence

A temporary source copy combined B1 API files/migration, B2 patient revision/exact
UUID reads and B3 filtering/labels. B2's two history methods/tests were composed with
B3's separate researcher method; no repository branch was merged or force-pushed.
The existing full suites (242 tests) passed in `pleural_stage_b_integration_test`.
The optional standalone workflow below then exercised one full API chain, using
random local credentials, redacted console messages and transactional cleanup:

```sh
# Only after reviewed B1/B2/B3 code is present and pending migrations applied
# to a NEW empty disposable schema. Never reset an existing schema to run this.
APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=pleural_stage_b_integration_test \
  TELENAPAS_STAGE_B_UAT=1 BCRYPT_ROUNDS=4 \
  php tests/Integration/stage_b_workflow.php
```

This script refuses production/non-MySQL/non-test/nonempty participant/instrument
schemas and requires B1's publication implementation. It is explicitly outside the
default PHPUnit suite because it requires all three independent PRs together.
It does not authorize a real clinical instrument. It tests API compatibility, not
an all-three-branches signed-Android end-to-end release. B2 native lost-response
recovery plus B3 actual browser reading the same MySQL record were separately
observed. Repeat full signed-device UAT on the final human-reviewed integration.

## Manual UAT / rollback / remaining review

Follow dashboard-monitoring.md and screenshots/b3/README.md. Review applied-filter
compatibility, unchanged deny-first scopes and historical label provenance. No
migration/clinical threshold changed. Deploy/rollback API and dashboard together;
new dashboard hides actively filtered results from an old API. Preserve immutable
history and pending encrypted copies across any coordinated B2 rollback.
Real instrument/schedule approval, retention/recovery governance, signed hardware,
TalkBack/font-scale matrix and hosted CI still require human review before release.
No merge, deployment, infrastructure/Cloudflare change, release APK or EAS build.

Compose config PASS; existing local API `/up` and `/api/v1/health`, dashboard HTTP200.
CORS PASS: middleware emits only fixed http://localhost:5173, even for another Origin;
browsers reject that mismatch. No wildcard/reflection/cookie credentials. The initial
manual assertion expecting header absence was corrected to test origin mismatch;
no CORS code/config changed. Existing automated CORS tests also pass.
