# PR-02 validation evidence

Local validation on 3 November 2026, PHP 8.4, MySQL 8.4, Node 24 and pnpm 10.28.2.

| Check | Result |
| --- | --- |
| MySQL API tests, including database integrity | PASS — 39 tests, 214 assertions |
| Normal-run migration lifecycle test | SKIPPED intentionally — separately executed below |
| Empty-schema migration apply/rollback/reapply | PASS — 1 test, 11 assertions |
| Composer strict validation | PASS |
| API Pint lint | PASS — 64 PHP files |
| pnpm lint / typecheck / validate:openapi | PASS |
| pnpm test | PASS — 1 dashboard test and 2 mobile tests |
| Dashboard production build | PASS |
| Android JavaScript/assets export | PASS — not an APK/device validation |
| Compose configuration and existing development services | PASS |
| HTTP liveness, MySQL readiness, dashboard and approved/rejected CORS origins | PASS |
| CI YAML syntax / git diff whitespace | PASS |
| Hosted GitHub Actions | NOT VERIFIED locally |
| Native Android device/APK | NOT VERIFIED; outside this database change |

Research migration DDL was executed only in newly provisioned disposable test
schemas. The existing `pleural_dev` schema still had zero tables after checks.
Fixture transactions left zero participants in the final integrity-test schema.
No real patient data, production deployment, classification engine or active
notification integration was used. Migration DDL ran with the test-schema
administrator; integrity tests ran with the restricted application account.

See [database instructions](README.md#migrations-and-validation) for test database
guards and reproduction commands. Schema administration and any rollback must
remain confined to new disposable test schemas. MySQL checks are not validated
by SQLite's explicitly skipped integrity tests.
