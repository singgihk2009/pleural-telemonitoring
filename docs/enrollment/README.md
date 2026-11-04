# Research enrollment and baseline management (PR-04)

PR-04 builds on merged PR-03 (`fa2188f` on main), including its explicit ADMIN_IT
grant-scope correction. It adds researcher enrollment APIs and an Indonesian
React/Vite dashboard. The supplied dashboard images informed the sidebar,
summary cards, searchable participant table and form layout. No sample metrics,
clinical charts, integration buttons or records are shipped in the application.
Only test fixtures and isolated browser checks contain fabricated study records.

## Access and API contract

The dashboard uses existing staff login and Sanctum tokens, then checks
`GET /api/v1/research/access`. Server policy requires an active PENELITI_UTAMA
that is neither ADMIN_IT nor PENILAI_HASIL, regardless of the blinding setting.
Assessor/researcher role combinations are denied all enrollment routes. Editor
serialization additionally checks the contact policy before loading or decrypting
contacts. Adding a researcher role to an
IT account, a contact grant, or any combination of TECHNICAL/CONTACT/CLINICAL_READ
grants does not authorize enrollment. Patients, nurses and outcome assessors
cannot access the researcher search/editor or mutate enrollment. Existing PR-03
read endpoints, assignment scopes, grant scopes and blinding remain unchanged.

| Endpoint | Purpose |
| --- | --- |
| GET /api/v1/research/access | Check enrollment authority; no participant data |
| POST /api/v1/research/participants/search | Researcher-only pagination, code search and group/status filters |
| POST /api/v1/research/participants | Enroll participant, optional encrypted contacts and safe audit atomically |
| GET /api/v1/research/participants/{id} | Read full researcher editor and version |
| PATCH /api/v1/research/participants/{id} | Update permitted baseline/consent/contact/status fields with a version check |

The [OpenAPI contract](../api/openapi.yaml) documents every field and response.
Search uses a JSON body so respondent codes do not enter URL access logs. It
matches literal study-code substrings, escaping SQL LIKE wildcard characters;
it never searches contacts or names. Search/filter counts and pagination apply
to the same filtered set (25 records/page), including empty and zero-code queries.
List responses omit contacts and drop-out reasons. Search is researcher-only so
group filters/counts cannot become a blinded-assessor inference channel.

## Data integrity decisions

Enrollment requires a unique nonblank code of at most 32 characters, INTERVENSI
or KONTROL, valid ISO calendar dates for discharge/day 0 and paper consent,
consent version (at most 40 characters), and a JSON integer VAS from 0 through
100 mm. Numeric strings, booleans, fractions and out-of-range values are rejected.
VAS has no default in the form and no classification, severity color or alert is
calculated. Calendar fields are Asia/Jakarta dates; server timestamps remain UTC.
No additional consent/discharge ordering, clinical baseline definition, study
eligibility threshold or clinical timing rule is invented.

Study code and group are immutable after enrollment; PATCH must omit both,
even if unchanged. This protects study identity and allocation. Baseline VAS and
discharge/day 0 may be corrected only before any daily entry, alert, outcome or
usability record exists. Classifications/symptoms and follow-ups are covered by
their entry/alert parents. Later changes need a separately reviewed correction
or versioning workflow; PR-04 rejects them rather than silently reinterpreting
history. Consent/contact/status updates remain possible without altering old
research rows. No historical records or participants are deleted.

Status defaults to AKTIF. AKTIF, SELESAI and DROP_OUT can be recorded by a
researcher. DROP_OUT requires a nonempty reason of at most 1,000 characters;
other statuses clear it, and cannot accept a nonempty reason. Reasons must not
contain direct identity/contact information. Entering a non-active status revokes
existing patient tokens within the update transaction. Reactivating a participant
does not restore old tokens; an otherwise valid existing intervention credential
may log in for a new session. No credential is automatically issued to either
group, and the existing control-credential FK/CHECK protections remain intact.

PATCH requires `expected_updated_at`, copied verbatim from the editor response.
The participant row is locked and the timestamp checked before writing. Stale
forms return 409 and must be reloaded; the UI never silently retries an overwrite.
The parent timestamp advances monotonically even for contact-only changes.
Legacy rows with a nullable timestamp accept an explicit null version once;
subsequent writes require the returned timestamp. JavaScript must not round or
reformat the microsecond version. Missing versions are rejected. The same parent
lock order as credential management serializes status-related token revocation.
Historical-data checks run while holding that row lock.

## Contact encryption, audit and logging

Optional `contact` fields are initials (20 characters), patient contact (40), and
family contact (40). They are saved only through the existing
`ParticipantContact` encrypted casts in the separate 1:1 table. Omitted contact
fields preserve their values. Explicit null contact clears its values through the
model without deleting records; an empty new contact does not create a placeholder
row. The dashboard resends contact fields only when changed. Contacts are decrypted
only in the researcher editor; they never appear in list rows or summary counts.

Every successful enrollment and update appends an immutable `participant.enrolled`
or `participant.updated` audit event in the same transaction. These events contain
the staff actor and fixed SUCCESS code only: **no participant UUID/code, contact,
calendar dates, baseline values, status values, reasons or before/after payloads**.
Researcher list/editor reads also use fixed audit codes without search terms or
participant IDs. Existing auth-denial auditing remains unchanged.

A failed audit write rolls back participant and contact writes. Unique-code races
become safe 422 errors; storage exceptions become generic 503 errors without
reporting SQL/bindings containing enrollment inputs. Tests exercise rollback and
logging privacy. The current application does not log request bodies. Do not add
request/SQL payload logging to these routes. The immutable audit table remains
unchanged; no audit-update/delete endpoint is introduced.

## Dashboard behavior and development

The UI checks server permission before fetching research records. It provides
loading, retry, empty/filter-empty and validation states; group/status filters;
pagination; researcher enrollment/editing; historical-baseline locks; drop-out
reasons; and duplicate/conflict errors. Readonly code/group fields are excluded
from PATCH. Contacts are optional. The editor supports keyboard focus trapping,
Escape/cancel, labelled fields, visible focus and a scrollable mobile layout.
Tables scroll within their container on small screens. The browser document is
marked Indonesian; calendar display uses Asia/Jakarta.

Tokens live in React memory only. Requests omit cookies and use no-store caching;
no token, form data, search term or direct identifier is stored in
localStorage/sessionStorage or browser history. Search codes and form values stay
out of URLs; resource paths use opaque UUIDs rather than study codes. Logout clears local records immediately and requests
server revocation. Offline revocation cannot be guaranteed: the UI explicitly
reports that limitation while discarding the local token. A 401/403 or token
expiry clears the workspace and editor. Pending reads are aborted or ignored
when filters/session/editor change. A page reload requires logging in again.
The UI includes no account signup or patient PIN issuance workflow.

The non-secret Vite setting `VITE_API_BASE_URL` defaults to
`http://localhost:8000/api/v1`. Start with the existing documented development
services and `corepack pnpm dev:dashboard`; an operational API schema, application
encryption key and researcher staff account are prerequisites. Use the trusted
`php artisan telenapas:create-staff` command from PR-03 for account provisioning.
No account, clinical data or development database migration is created on app
startup. Keep the exact documented dashboard origin; CORS now additionally permits
PATCH, still without wildcard origins or credentialed cookies.

Run Composer validation/lint/tests with a pre-migrated disposable MySQL `_test`
schema and the restricted application user. Existing migrations are unchanged;
there is no PR-04 migration. Run all pnpm lint, typecheck, tests, OpenAPI lint,
dashboard build and Android JS export. The new tests use synthetic records only;
no real patient data or production deployment is involved. Hosted Actions results
must be verified separately from local CI-equivalent commands.

See the [PR-04 validation report](validation-pr04.md) for test counts, security
checks and remaining limitations.
