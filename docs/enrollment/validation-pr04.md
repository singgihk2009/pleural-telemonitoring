# PR-04 validation report

Validated against merged PR-03 on main (`fa2188f`), using synthetic records and
isolated disposable MySQL schemas. No development or production schema was
migrated. Historical migrations, dependency locks and approved VAS requirements
are unchanged.

## Changes

- API: `EnrollmentController`, researcher enrollment policies/routes, PATCH CORS,
  enrollment integrity/security tests and existing CORS expectations.
- Dashboard: staff login, capability check, participant list/filter/pagination,
  enrollment and version-checked editing, encrypted-contact fields, responsive
  layout and API/error handling; 13 component tests.
- Documentation: OpenAPI 0.4, database dictionary supplement, enrollment guide,
  RBAC/security documentation, repository scope and this report.
- No new database migration: the existing participant/contact schema supports
  the requested fields and constraints.

## Automated results

| Check | Result |
| --- | --- |
| Full MySQL API suite, restricted application account | 155 passed; 1,247 assertions; 3 opt-in migration tests skipped |
| Migration lifecycle, separate disposable schema | 1 passed; 11 assertions |
| Authentication migration upgrade, separate disposable schema | 1 passed; 13 assertions |
| Grant-scope upgrade, separate disposable schema | 1 passed; 19 assertions |
| Dashboard component tests | 13 passed |
| Existing mobile tests | 2 passed |
| Composer strict validation / Pint | Passed; 90 PHP files clean |
| Workspace lint / typecheck | Passed |
| OpenAPI validation | Passed |
| Dashboard production build / Android JS export | Passed |
| Compose configuration / service readiness smoke | Passed |
| YAML, Markdown fences and git whitespace checks | Passed |

The 42 new backend cases cover validation, both groups, encrypted storage,
immutable allocation, historical baseline protection, version conflicts including
legacy null timestamps, transactions, audit privacy, search/pagination, role
restrictions and patient token revocation. Existing PR-01 through PR-03 suites
continue passing. The final mobile CSS containment change was followed by a
fresh dashboard build and lint check.

## Security and browser checks

A real HTTP smoke run against a separate disposable MySQL schema verified staff
login, both-group enrollment, filters, encrypted contacts, duplicate/stale/history
rejections, safe audit, logout and refusal of control credentials.

Negative authorization covers unauthenticated clients, patients, nurses,
assessors, blinded role combinations and ADMIN_IT grant combinations. Grants do
not authorize enrollment. Tests verify rollback on audit failure and safe storage
errors without logging identifiers, SQL bindings or clinical inputs.

Chromium checks with explicitly synthetic intercepted fixtures verified desktop
and 390px mobile list/editor layouts, including internal table scrolling and no
page overflow. The application ships no seeded participant records or fabricated
summary metrics.

## Remaining limitations

- Hosted GitHub Actions must be checked separately; local command results do not
  establish a hosted CI result. GitHub API access was forbidden in this runtime.
- Historical baseline corrections require a separately reviewed workflow; this
  implementation rejects changes after dependent research records exist.
- Browser tokens are memory-only. Offline logout clears local data but cannot
  confirm server revocation; the dashboard warns explicitly.
- No patient credential issuance UI, clinical classification, automatic alert,
  messaging integration, real patient data, deployment or automatic merge.


## P1 assessor-contact correction (2026-11-04)

Enrollment policies now unconditionally deny PENILAI_HASIL, including combined
researcher/nurse roles with blinding disabled. All five enrollment endpoints use
this deny-first rule. The shared editor resource also authorizes `viewContact`
before loading the contact relationship or accessing encrypted casts. Search
continues omitting contacts. Existing contact policy/query restrictions, IT grant
scopes, encryption and immutable audit behavior are unchanged.

Eight new MySQL cases verify both blinding modes, assessor role combinations,
alternative contact/summary routes, no unauthorized writes, and continued
non-assessor researcher create/show/update/list functionality. Invalid synthetic
ciphertext makes unintended decryption observable without using patient data.

Local checks for this correction: full API **163 passed / 1,373 assertions**;
three opt-in checks skipped there and separately passed in new disposable schemas
(lifecycle 11, authentication upgrade 13, grant-scope upgrade 19 assertions).
Dashboard **13 passed**, mobile **2 passed**; Composer validation, Pint (90 files),
workspace lint/typecheck, OpenAPI lint, dashboard build and Android export passed.
Compose configuration and infrastructure/CORS smoke checks passed. Hosted CI and
the fresh Codex review request require GitHub API access; local results do not
establish hosted check or review completion.
