# TeleNapas authentication and RBAC v1.0 (PR-03)

This implements the PR-03 request and SRS FR-01/02, actor restrictions and audit
requirements on the merged PR-02 schema. Read the [API contract](../api/openapi.yaml)
and [research authorization requirements](../research/auth-rbac-v1.0.md).
PR-04 adds a [researcher enrollment workflow](../enrollment/README.md).
No clinical evaluator, daily-report write workflow, active notification sender,
patient self-registration, export or production deployment is included.

## Credentials, sessions and provisioning

Staff authenticate at `POST /api/v1/auth/staff/login` with email and password.
Patient/family authenticate at `POST /api/v1/auth/patient/login` with study `code`
and `pin`, exactly six ASCII digits **as a string**. Leading zeros are preserved;
whitespace, Unicode digits and numeric JSON values are rejected. Password input
is limited to 72 bytes to avoid bcrypt truncation. Staff email matching uses
lowercase/trimmed exact spelling, not accent-insensitive database aliases, so
accepted aliases cannot split account rate-limit buckets.

`StaffUser` uses `pengguna.password_hash`; `PatientCredential` uses the separate
`patient_credentials.pin_hash`. Laravel's configured adaptive password hasher
(default bcrypt, normal runtime work factor 12) hashes values before persistence.
Tests deliberately use Laravel's lower test-only cost. A database CHECK rejects
plaintext-looking PIN values; model/service hashing is still the authoritative
write path. Never send or log credentials outside TLS or place them in URLs.
Only synthetic credentials occur in tests/factories; no default account is seeded.

Sanctum issues a random bearer token once, persists only its SHA-256 hash, and
sets a mandatory absolute expiry. This API deliberately does not use Sanctum SPA
cookie/session fallback, browser cookies or refresh tokens. `GET /auth/me` returns
principal kind, opaque ID and current roles. Responses with tokens and protected
data use `Cache-Control: no-store`. Clients must keep mobile tokens in platform
secure storage and web tokens in memory, not browser localStorage; implementing
a client login UI/storage is outside this PR.

`POST /auth/logout` revokes the current token; `/auth/logout-all` revokes every
token for that principal. `DELETE /staff/{staff}/tokens` lets ADMIN_IT revoke staff
sessions without reading clinical data. Current database status, credential
revocation, participant group/status and token scope are checked on every
protected request. Eloquent staff password/status changes and patient PIN or
revocation changes delete their tokens. Query-builder/raw SQL bypasses model
hooks and must not be used for credential maintenance. Active-status checks still
deny suspended/revoked principals independently of token deletion. Role and
assignment changes take effect on the next request without trusting token role
snapshots. Already executing requests are not cancelled retroactively.

From `apps/api`, provision the initial staff/researcher account through a trusted
interactive console operator:

```sh
php artisan telenapas:create-staff
```

The command prompts for staff email/name, one staff role, and a hidden confirmed
password of at least 12 characters (at most 72 bytes). There is no password CLI
argument, default account, public registration or account-creation API. It hashes
the password explicitly and appends a provisioning audit event without recording
name/email/password. Host operators must protect console/database access; this
command is not an unauthenticated web route. It never creates patient credentials.

A non-blinded principal researcher issues/resets a PIN through
`POST /participants/{participant}/credentials`. The request contains the new PIN and required expected_revision;
the response never returns it. PR-10 adds the researcher dashboard workflow and a
policy-protected GET on the same path returning credential status and a non-secret
random revision. POST/DELETE compare the expected revision under their write locks
and return a committed revision receipt; stale writes fail with 409 before changes.
The researcher communicates it using a separately
approved secure process, not FCM/WhatsApp. Issuance requires an active intervention
participant; the composite FK and CHECK also reject control credentials at the
database layer. Reset requires a different PIN and revokes prior tokens. Status values are exactly
`BELUM_DITERBITKAN`, `AKTIF`, and `DICABUT`; revoked rows remain stored, so no
never-issued state is inferred from a deleted record. GET, POST and DELETE all
require current active intervention eligibility and exclude any assessor or IT
role combination regardless of blinding. Issuance/reset/revoke events remain transactionally audited. Status snapshots are
non-cacheable indexed reads without exclusive participant locks or repetitive
success-read audits; permission denials remain audited. DELETE on the same path revokes the
credential and its sessions. Participant/credential locks serialize these
operations with login, so reset cannot leave a concurrently issued old-PIN token.

## Server-enforced access

All paths below are under `/api/v1`. Except public health and the two logins,
routes require `auth:sanctum` and an active supported principal. Policies run
before loading sensitive relationships. Participant collections apply database
scope **before** pagination/counts; response projections never serialize arbitrary
relationships or hidden credential fields.

| Role | Participant scope | Contact | Monitoring | Outcome | Administrative action |
| --- | --- | --- | --- | --- | --- |
| PASIEN_KELUARGA | Own active intervention participant | Own only | Own entries/classifications; no nurse alerts/notes | Own only | Logout own sessions |
| PERAWAT_PEMANTAU | Intervention participants with a currently active PEMANTAU assignment | Assigned only | Assigned only | Assigned only | No credential/grant authority |
| PERAWAT_CADANGAN | Intervention participants with a currently active CADANGAN assignment | Assigned only | Assigned only | Assigned only | No inherited access to all cases |
| PENELITI_UTAMA | Both groups | Yes | Yes (controls have no daily reports) | Both groups | Issue/revoke patient credentials; separately authorize IT |
| PENILAI_HASIL | Both groups, projected summary | Never | Denied when blinded; pseudonymous reads when unblinded | Both groups | No patient credential authority; no grant authority while blinded |
| ADMIN_IT | CLINICAL_READ grant for summaries/list | CONTACT grant only | CLINICAL_READ grant only | CLINICAL_READ grant only | Revoke staff sessions; no self-grant; TECHNICAL grants no patient reads |

Monitoring paths are `entries`, `classifications`, `alerts`, `follow-ups` beneath
`/participants/{participant}`. Summary/list and `outcome` do not embed those
relationships. Blinded summaries omit study code, group and discharge date to
avoid straightforward unblinding. Outcome projections omit facility/contact data.
Only authorized contact reads decrypt identity fields; authorized follow-up reads
explicitly expose assessment notes. Reads generate no classification, alert or
notification. Existing records remain subject to immutable database constraints.

Deny-first rules apply across role combinations: any ADMIN_IT role still requires
an explicit matching participant scope even alongside PENELITI_UTAMA. Any blinded-assessor
role denies monitoring and grant issuance even alongside researcher or nurse
roles. Any assessor role denies patient credential status/issuance/reset/revocation
even with blinding disabled. Assessors never gain contact access through another role. A
PASIEN_KELUARGA assignment on a staff account is not a patient identity and cannot
authenticate as staff; patient role identity comes only from the separate credential.
There is no public role-assignment endpoint or self-escalation operation.

## Separate identified IT access

Only an active, non-IT, non-blinded principal researcher can POST an
`identified-access` grant under a participant. It names a different active
ADMIN_IT staff account, a short purpose code, a future expiry (at most the
configured limit, default 24 hours), and a required nonempty `scopes` array.
Scope values are exactly `TECHNICAL`, `CONTACT`, and `CLINICAL_READ`; duplicates,
unknown values, missing/empty lists and non-list objects are rejected. No scope
is inferred or assigned by default. Every grant remains participant-specific;
no patient credential management, clinical write permissions or role assignment
is granted. Existing assessor blinding/contact restrictions
still win. Dates with offsets are normalized to UTC before storage.

DELETE `/participants/{participant}/identified-access/{grant}` revokes it.
Expiry, revocation or loss of the approver's active/non-IT/non-blinded researcher
authority invalidates access on the next request. Approvals/access/revocations
are audited. This is a technical mechanism for explicit authorization; it does
not establish ethics approval or replace the research governance process.

### Explicit scope boundaries (PR #3 correction)

| Explicit scope | Allowed patient resources | Excluded resources |
| --- | --- | --- |
| TECHNICAL | None in the current patient API | Contacts, summaries/baseline, list rows/counts, monitoring, outcomes |
| CONTACT | `/participants/{id}/contact` only | Clinical summary/list, entries, classifications, alerts, follow-ups, outcomes |
| CLINICAL_READ | Summary/list, entries, classifications, alerts, follow-ups, outcome | Contact endpoint and embedded contact details |

Scopes are independent. CONTACT+CLINICAL_READ permits both categories, subject
to the existing role/blinding rules. Adding TECHNICAL expands neither category.
ADMIN_IT's existing staff-session revocation operation remains a technical role
operation; a participant TECHNICAL grant does not add patient-data permissions.
CONTACT-only holders can address the approved contact endpoint using the supplied
participant UUID but cannot discover clinical summaries through the list.

Every clinical list/count and resource query filters participant IDs through an
active CLINICAL_READ grant. Contact queries independently filter through CONTACT.
Monitoring query scopes also enforce blinding, and staff-only monitoring queries
exclude patients. Policies reject unauthorized requests before querying data;
the resource queries independently withhold data even if a policy is accidentally
made overly permissive. No generic “any grant exists” fallback remains.

The additive scope migration never backfills historical grants. Legacy approvals
with no scope bindings retain their metadata but authorize zero patient reads.
A researcher must issue a new grant with explicit scopes. There is no scope-edit
API; changing the approved set requires revocation and a new approval. Expiration,
revocation or lost approver authority invalidates every scope under that grant.
Separate active grants may contribute separate scopes for the same participant;
an expired/revoked CONTACT or CLINICAL_READ grant cannot borrow validity from
another active TECHNICAL grant or another participant's approval.

Approval appends the existing grant audit event and `grant.scopes.approved`,
whose outcome is a canonical list of fixed scope codes, not a request payload.
Audit rows remain immutable. See the [scope Data Dictionary](../database/grant-scopes-v1.2.md)
for schema details and the safe legacy-upgrade behavior.

## Brute-force protection and privacy

Both login routes use Laravel's database-backed rate limiter, separately from
`CACHE_STORE`. It persists counters in `auth_rate_limits` with database locks in
`auth_rate_limit_locks`; there is no in-memory fallback. Each route permits at
most 5 attempts per normalized account per 15 minutes, regardless of IP, and 30
attempts per IP per minute across account names. Successful and malformed
attempts count too. Account/IP keys are HMACs using the application key; raw
codes, emails and IPs are not stored. Throttles return 429 with Retry-After;
unknown, inactive, revoked and incorrect credentials return the same generic 401.
Unknown accounts still perform verification against a non-account hash made
with the configured algorithm/cost and cached in the shared store. The counters
limit online guessing, not offline attacks against a stolen database. Keep the
application key/private database protected. Only trust explicitly configured
reverse proxies; never accept arbitrary client-supplied forwarded IP headers.

Audit events include `login.success`, `login.failed`, `logout`, `logout.all`,
`permission.denied`, `privileged.access`, credential lifecycle, staff-session
revocation, IT grants/revocation and console provisioning. Staff events use the
staff FK; patient events use no staff actor FK, with opaque credential references
where needed. Only fixed server outcome codes are written to redacted metadata.
Never record PINs/passwords, bearer tokens, respondent codes, contact data, raw
request paths/bodies, clinical payloads, or provider error messages. Reads and
privileged mutations fail if their required audit write fails. Login/token
creation and privileged mutations audit in the same transaction. Existing
audit rows remain append-only even through raw SQL.

Operational settings in `config/research_auth.php`:

| Variable | Default | Allowed effect |
| --- | --- | --- |
| AUTH_TOKEN_TTL_MINUTES | 60 | Absolute lifetime, clamped to 5–1440 minutes; new tokens only |
| AUTH_BLINDED_ASSESSMENT | true | Deny monitoring and redact group/code/date for assessors |
| AUTH_GRANT_MAX_HOURS | 24 | Maximum new IT grant duration, clamped to 1–24 hours |

These are security settings, not clinical research rules. Changing blinding is
a deliberate governance decision. Defaults fail closed for assessor access.
API exception responses remain generic even with debug enabled; validation
responses contain field errors without input values. CORS retains the exact
dashboard origin and no credentialed cookies; POST, PATCH,
DELETE and Authorization headers are added for bearer authentication. CORS does
not authorize requests. Health remains public and sanitized.

## Development and tests

Install with locked `composer install`. Under this environment's archive-API
restriction, use documented `--prefer-source` with verified TLS/CA trust. Sanctum
4.3.3 is locked; unrelated dependencies and old migrations are unchanged.
Apply the new additive migration only to an appropriate new development/test
schema. It creates credentials/tokens/grants/cache tables and does not create
accounts, modify research records, change old constraints or run on app startup.
Use the [database administrator/test instructions](../database/README.md) for the
existing append-only trigger privileges. Never use rollback or `migrate:fresh`
on a populated research/development database.

The security suite requires MySQL and a dedicated disposable name ending `_test`,
with migrations pre-applied by the schema administrator. From `apps/api`, select
that database and run `composer test`. Assertions use real bearer-token HTTP
requests and the durable MySQL limiter, not `Sanctum::actingAs` or disabled
authorization middleware. Transactional synthetic fixtures roll back. The
separate lifecycle suite must use its own initially empty `_migration_test`
schema and `TELENAPAS_MIGRATION_TESTS=1`. CI executes both paths. A SQLite run
explicitly skips these MySQL security/integrity tests and is not security evidence.

PR-04 adds researcher-only enrollment routes. Existing ADMIN_IT read grants do
not authorize enrollment, baseline edits, status changes or enrollment searches.
See its [access and integrity rules](../enrollment/README.md).

PR-04 enrollment access denies every PENILAI_HASIL role combination regardless of
`AUTH_BLINDED_ASSESSMENT`, including PENILAI_HASIL + PENELITI_UTAMA. Create,
show, update and search share this restriction. Editor serialization additionally
authorizes `viewContact` before loading/decrypting contacts. Existing contact
policy/query restrictions and ADMIN_IT scope requirements remain unchanged.


## PR-10 browser PIN handling

The enrollment editor's Android access section loads authoritative status before
showing actions. It is limited to the existing authorized researcher workspace;
the API independently checks the credential policy before reading or writing.
Controls and inactive intervention participants have no issuance/reset actions;
the editor accurately says their credential status is not loaded. Inactivation
continues to revoke sessions through the existing enrollment workflow; it does
not erase credentials or history.

`crypto.getRandomValues` with rejection sampling generates an unbiased six-digit
string, including leading zeros. There is no Math.random/default/derived PIN and
no insecure fallback. Use HTTPS or localhost so Web Crypto is available. The
POST contract now requires `{pin, expected_revision}` and returns a non-secret
revision receipt: no stored PIN or hash can be retrieved.
Plaintext is held only in an in-flight browser operation and its one-time display,
never URLs, browser storage, analytics, console/audit output or server responses.
The display clears on close, editor/participant change, logout and invalidation/
expiry; stale responses cannot restore it. An atomic pending guard blocks repeat
clicks, and reset/revoke require explicit confirmation. The receipt revision must
match an active snapshot before disclosure; AKTIF alone is insufficient. Open
views revalidate every five seconds and on focus/visibility return, hide during
checks and clear on mismatch/failure. Verification is explicitly point-in-time;
subsequent authorized writes can invalidate it, and login remains authoritative.
Ordinary editor closure/save is blocked during a pending write; explicit logout
still discards data immediately. A 30-second client timeout or abort is not proof
of server cancellation; reload/reset recovery guidance is shown.

Mutation audit action codes remain `credential.issued`, `credential.reset`,
and `credential.revoked`, with only fixed outcome metadata.
PIN reuse is rejected with 422 before any token deletion; audit/storage failures
roll back hash/revocation changes and token deletion together. No audit row is
updated or deleted. Existing login limits, token expiry and ADMIN_IT scopes remain.

See [researcher instructions and reviewed contract gaps](../enrollment/pin-management-pr10.md).

## PR-11 patient assessment ingestion

Patient-only `/patient/assessments` derives identity from the existing Sanctum
principal. Under participant then credential locks it rechecks current eligibility,
revocation, token existence/ability/expiration before writes or retry recovery.
PR-10 issuance/reset/revocation behavior is unchanged. Current approved instrument,
strict catalog, integer VAS, explicit same-day schedule and DB immutable/unique/FK
constraints guard atomic submissions. Stable UUID plus canonical fingerprint
supports same-owner exact retries with no duplicate mutation audit. Unknown identity
fields, foreign UUIDs and changed-body replays never reveal another participant.

Staff history `/participants/{participant}/assessments` applies existing staff-only
monitoring policy and query scope. Blinding, assignment validity, active researcher
approval and explicit CLINICAL_READ grant restrictions remain enforced. It exposes
safe observations/timestamps, not contact fields, hashes, retry fingerprints,
classifications, alerts or arbitrary model relationships. Enrollment access itself
remains researcher-only and unconditionally excludes assessors from contacts.
No assessment update endpoint is provided. Assessment routes share a 30/minute
principal throttle; response caching and payload/secret logging are prohibited.
Audit codes for submission/privileged read use opaque IDs and fixed outcome codes.
See [architecture and recovery guarantees](../assessment/README.md).

### B2 encrypted pending assessments

Patient-only profile metadata exposes the existing opaque credential revision for
local namespace isolation, never a PIN/hash. Staff profile responses omit it.
Exact UUID history lookup retains owner scoping and all existing credential checks.
Offline data is unlocked only after server identity/revision validation. Transport
captures the original token, foreground scope changes stop further sends, and an
in-flight request may still commit. Logout hides RAM state immediately and preserves
encrypted uncertain copies. No offline remote-revocation/secure-flash-erasure claim.
See [queue security and rollback limits](../stage-b/offline-sync.md).

Stage B1 instrument publication reuses researcher-only deny-first enrollment
permission and fresh staff/token checks under serialization. Approval actor/time
are server derived; settings/options/publication/audit are atomic and immutable.
No new patient-data access or clinical grants are implied. See the
[publication security/rollback notes](../stage-b/instrument-publication.md).
