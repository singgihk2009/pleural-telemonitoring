# PR-03 test report

For the later ADMIN_IT scope correction, see the
[scope validation report](validation-grant-scopes.md). These original results
below describe the initial PR-03 implementation.

Local validation on 3 November 2026 (Asia/Jakarta), using PHP 8.4, MySQL 8.4,
Node 24, pnpm 10.28.2 and locked Sanctum 4.3.3. No real patient data was used.

| Check | Result |
| --- | --- |
| Full MySQL API suite | PASS — 88 tests, 605 assertions |
| Authentication/authorization cases included above | 49 cases with real bearer requests and database-backed rate limits |
| Normal-suite lifecycle/upgrade cases | 2 SKIPPED intentionally; separately executed below |
| Empty-schema migration apply/rollback/reapply | PASS — 1 test, 11 assertions |
| PR-02 upgrade/auth-only rollback/data preservation | PASS — 1 test, 13 assertions |
| Temporary live HTTP server, separate smoke schema | PASS — staff/patient login, own-record access, cross-participant and unassigned-nurse denial, logout/reuse rejection and denial audit |
| Strict Composer validation | PASS |
| API Pint lint | PASS — 83 PHP files |
| JS lint and type checks | PASS |
| JS tests | PASS — 1 dashboard test and 2 mobile tests |
| OpenAPI schema and contract lint | PASS |
| Dashboard build | PASS |
| Android JavaScript/assets export | PASS — not an APK or device check |
| Compose configuration / CI YAML / diff whitespace | PASS |
| Hosted GitHub Actions | NOT VERIFIED locally |
| Native APK/device behavior | NOT VERIFIED; outside this backend change |

Coverage includes all protected routes rejecting unauthenticated requests;
separate patient/staff credentials; exact PIN string format and secure hashes;
patient isolation; scoped/paginated nurse reads; expired/future/mismatched nurse
assignments; both research groups; blinded projection and monitoring denial;
role-combination and wildcard-token bypass attempts; separate/expiring/revocable
IT grants; UTC normalization of offset-bearing expiry input; grant invalidation
when researcher authority changes; durable account/IP brute-force limits;
rotation, logout, logout-all, token expiry and credential/status revocation;
console provisioning; no public registration; sanitized errors even with debug;
CORS bearer headers without cookies; audit privacy and unchanged audit immutability.

Schema DDL used the disposable database administrator; normal integrity/security
tests used the restricted application account. The full lifecycle and upgrade
checks used different, initially empty, explicitly guarded test schemas. The
upgrade check retained fabricated research records and the original audit
trigger across auth-only rollback/reapply. No prior migration was edited.
The pre-existing `pleural_dev` database still had zero tables after validation;
transactional fixtures left zero participants in the ordinary test schema.
Live HTTP fixtures remained wholly synthetic in their separate disposable schema;
the temporary server was stopped after checks. No production deployment occurred.

See [authentication/RBAC instructions](authentication-rbac.md) and the
[database extension](../database/authentication-v1.1.md) for reproduction and guards.
The normal suite must run on a MySQL `_test` database with administrator-applied
migrations. Run `MigrationLifecycleTest` and `AuthenticationMigrationTest` separately,
with `TELENAPAS_MIGRATION_TESTS=1`, each against its own initially empty schema
ending `_migration_test` and `_auth_migration_test`, respectively. CI includes
both commands and the restricted-user ordinary suite. Skipped SQLite security
cases do not establish readiness.
