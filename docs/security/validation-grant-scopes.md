# PR #3 ADMIN_IT scope correction — validation report

Initial state: clean `feature/pr-03-authentication-rbac`, local/remote branch and
GitHub `refs/pull/3/head` all at `c1f24790c7de787c97a5c3ae9d7ad26c4c851996`.
Validation used only fabricated fixtures and newly created disposable MySQL 8.4
schemas, PHP 8.4, Node 24 and pinned pnpm 10.28.2.

| Check | Result |
| --- | --- |
| Full MySQL authentication/security/research API suite | PASS — 113 tests, 953 assertions |
| Scope matrix and negative query/authorization coverage within that suite | PASS — 25 cases, 348 assertions |
| Normal-suite opt-in migration checks | 3 SKIPPED intentionally; executed separately below |
| Full empty-schema migration apply/rollback/reapply | PASS — 1 test, 11 assertions |
| Research-to-authentication upgrade/auth-only rollback | PASS — 1 test, 13 assertions |
| Legacy grant scope upgrade/scope-only rollback/reapply | PASS — 1 test, 19 assertions |
| Composer strict validation / PHP Pint lint | PASS — 88 PHP files |
| pnpm lint / typecheck / validate:openapi | PASS |
| JS tests | PASS — 1 dashboard test, 2 mobile tests |
| Dashboard build / Android JavaScript export | PASS; Android export is not an APK/device check |
| Compose config and live health/dashboard/CORS smoke | PASS |
| CI YAML, documentation fences, diff whitespace | PASS |

Scope tests cover all eight combinations (including legacy unscoped), denied
cross-participant reads, clinical list counts, CONTACT/CLINICAL_READ independence,
TECHNICAL exclusion from every patient resource, researcher+ADMIN role combinations,
blinded assessors, exact scope CHECK/unique/FK enforcement, malformed/missing/empty/
duplicate approval inputs, expired/revoked scope parents, independent grants and
loss of approver authority. Populated fixtures establish that allowed reads
return actual rows; denied reads cannot pass by returning empty fixture data.
Query-defense tests deliberately allow policies too broadly and verify SQL still
withholds unapproved contacts, clinical data and counts.

The additive scope migration does not modify existing research, credential,
approval or immutable audit records. It does not backfill permissions. The
upgrade test snapshots old grants and participants, verifies no scope inference,
checks audit immutability across scope-only rollback, and verifies reapply leaves
legacy approvals unscoped. CI includes the same isolated scope-upgrade check.

Use a dedicated MySQL `_test` schema with administrator-applied migrations for
`composer test`; fixture transactions roll back. Each opt-in migration check
requires its own initially empty schema and `TELENAPAS_MIGRATION_TESTS=1`.
`GrantScopeMigrationTest` requires the suffix `_grant_scope_migration_test`.
Never run migration rollback or destructive resets against an existing research
or development database. See [scope schema and semantics](../database/grant-scopes-v1.2.md).

These are local CI-equivalent results. Hosted GitHub Actions results require a
separate check on the pushed commit; they are not inferred from local success.
