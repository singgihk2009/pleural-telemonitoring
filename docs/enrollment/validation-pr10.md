# PR-10 initial validation — 7 November 2026 (WIB)

Historical results for the original PR-10 implementation. The concurrency/recovery
protocol and current results are in [correction validation](validation-pr10-correction.md).

Scope: researcher-managed patient Android access, from main `af20230`.
Synthetic records only; no deployment, EAS build, database reset, schema change,
clinical rule or integration activation. Existing server `infra/` was untouched.

| Check | Result |
| --- | --- |
| MySQL 8.4 / PHP 8.4 full API suite | PASS — 202 tests, 1,699 assertions; 3 opt-in migration tests not executed (no migration changes) |
| Existing authentication/RBAC, grant scopes, assessor restrictions and research integrity | PASS within the full MySQL run; security tests were not skipped for SQLite |
| Credential lifecycle and status | PASS — never-issued/active/revoked exact projections, hashed leading-zero PIN, real patient login/identity/profile/logout, reset rejects old PIN/tokens, revoke denies login |
| Negative credential authorization | PASS — control/inactive participants; nurses, IT, assessors, patient/staff-patient identities; researcher+IT/assessor combinations with blinding on/off |
| Failed-operation integrity | PASS — invalid ASCII/string shapes, same-PIN reuse rejection, transaction rollback on audit failure for issue/reset/revoke, preserved participant/baseline/daily entry and immutable audit constraints |
| Dashboard tests | PASS — 33 tests (existing enrollment tests plus credential component/editor/security cases) |
| Mobile tests | PASS — existing 40, including session-race/auth/navigation cases; mobile source unchanged |
| Workspace lint / TypeScript | PASS — repository oxlint and typecheck scripts; ESLint is not the configured linter |
| Composer strict validation / Pint | PASS — 93 PHP files clean |
| OpenAPI | PASS — new authenticated GET documented; existing POST shape/message-only response retained; same-PIN 422 documented |
| Dashboard production build | PASS |
| Android JavaScript export | PASS — not a native APK test or EAS build |
| Real Chromium researcher/API smoke | PASS — described below |
| Installed UAT APK / physical Android integration | NOT EXECUTED — no APK artifact/device supplied for this branch; no deployment/build was authorized |
| Hosted GitHub Actions | NOT VERIFIED — GitHub API access has returned Forbidden in this workspace; local checks are reported separately |

## Browser and patient API integration

Actual Chromium with the dashboard at its approved `http://localhost:5173`
origin used a test-harness API route to an isolated Laravel HTTP server over a
loopback relay. CORS, Bearer authentication, policies, hashing and MySQL were real;
API responses were not mocked. The shared development database was not used.
The full suite used `pleural_pr10_suite_test`; browser fixtures used the separate
`pleural_pr10_test` schema. Both were newly created disposable schemas, with
original migrations installed by a test administrator and normal API requests
using the restricted application user. No existing schema was reset.

The researcher enrolled a new synthetic intervention participant through the
editor, issued a generated PIN, and used that string for the existing patient
API contract (`Accept: application/json`): login, identity, own profile and logout.
A second active patient session was invalidated by a confirmed dashboard reset;
old PIN login and the old bearer were rejected with 401. The new PIN logged in
successfully. Dashboard revoke then rejected the new bearer/login with 401.
Server-derived Dicabut status was displayed; a control participant had no issuance
action. Staff logout completed. The browser's localStorage/sessionStorage were
empty. PINs/tokens were kept transiently inside the harness and never printed or
saved in screenshots/files. Local staff-fixture setup credentials were temporary,
private and removed after the smoke test.

Visual checks at 1280×900 and 480×900 confirmed the Android section fits the
existing scrollable editor, responsive actions and footer, without horizontal
overflow. Screenshots were inspected locally with the PIN closed, not committed
as credential-bearing artifacts. Focus tests cover confirmation/one-time regions
and return to the action button, alongside the existing outer-editor keyboard trap.

This verifies the Android-facing API sequence, not the installed UAT APK. Before
UAT acceptance on a handset, repeat the exact
[researcher/Android workflow](pin-management-pr10.md) with synthetic participants:
issue → F1 login/profile → logout → reset → reject old PIN → accept new PIN →
revoke → reject login. Existing APK/API URL compatibility is preserved, but a
physical-device result must be recorded separately after reviewed deployment.

## Review boundaries

The only API extension is a three-value credential-status projection. POST still
accepts the researcher-supplied six-digit string and never returns plaintext.
Successful reset now requires a different PIN. Audit action codes distinguish
status reads, issuance, reset and revocation; no PIN/hash/body is logged.
No role or ADMIN_IT grant scope is added. Credential access is deny-first for any
assessor role even with blinding disabled, consistent with enrollment restrictions.

Clear UI state does not claim guaranteed JS-memory erasure. A lost response or
abandoned write can have committed server-side, requiring a status reload and
new confirmed reset. Concurrent authorized writers remain serialized with
last-write behavior; credential versioning was not invented in this PR.
