# Demo seeder validation

Baseline: `main` / `07d8aee`. All database execution used new, isolated MySQL 8.4
schemas, not the existing baseline or a research/production database.

| Check | Result |
| --- | --- |
| Full MySQL API suite | PASS — 175 tests, 1,515 assertions; three opt-in migration checks skipped in this invocation |
| New demo cases within that suite | PASS — 12 cases, 142 assertions |
| Separate migration lifecycle | PASS — 1 test, 11 assertions |
| Separate authentication upgrade | PASS — 1 test, 13 assertions |
| Separate explicit-scope upgrade | PASS — 1 test, 19 assertions |
| Composer strict validation | PASS |
| Pint | PASS — 93 PHP files |
| Actual first/repeat Artisan CLI seed | PASS — private generated file; repeat works without its path |
| Actual production `--force` rejection | PASS — no write, even against an initialized demo schema |
| Actual baseline database name rejection | PASS — `pleural_dev` rejected before any data mutation |
| Real HTTP verification | PASS — researcher login/list, scoped nurse monitoring, nurse/IT/assessor denials, control enrollment, duplicate rejection, logout |
| Rerun after API enrollment | PASS — no-op; added enrollment retained |
| Compose / liveness / MySQL readiness / dashboard / CORS | PASS |
| Historical migrations, existing models/policies, default seeder and lockfiles | UNCHANGED |
| Production deployment / merge / deployment-server `infra/` changes | NOT PERFORMED |
| Hosted GitHub Actions | NOT VERIFIED — separate from local results |

The ordinary tests wrap changes in transactions against
`pleural_demo_seeder_test`. They verify encrypted contacts, hashed credentials,
exact-row idempotence after a dashboard-style edit/addition, no password rotation,
no unapproved catalogs/results, safe audits, role/assignment restrictions,
immutable-entry/audit UPDATE/DELETE rejection, FK/daily-unique/VAS constraints,
production/disabled/private-file/driver guards, a competing connection's named
lock, whole-fixture rollback on audit failure and successful retry/lock release.

The real CLI/HTTP smoke run used a separate `pleural_seeder_smoke_demo`. A
cryptographically generated password was kept in a mode-600 temporary file and
read privately by the seeder and HTTP client. Its contents and bearer tokens were
never printed; the file was removed after verification. Five initial accounts,
four participants and six observations were created. An additional synthetic
control was enrolled through the authorized API, then another seeder invocation
preserved the resulting five participants. The normal default `DatabaseSeeder`
remains empty and no startup seeding was introduced.

Alerts, classifications, follow-ups, clinical catalogs, outcomes, questionnaire
items/scores, patient credentials, identified grants and notification records
remain intentionally empty. Their omission follows the validated-rule/workflow
requirements; it is not a claim that those clinical features were exercised by
this demo. No clinical rules or thresholds were changed. Existing full API tests
continue covering authentication, research integrity and scoped authorization.

JavaScript applications and their dependencies were not changed; frontend builds
were not rerun for this API-only change. No browser/device compatibility claim is
made. The [usage guide](demo-seeder.md) contains secure setup, dashboard login,
example API verification commands and safe rerun instructions.
