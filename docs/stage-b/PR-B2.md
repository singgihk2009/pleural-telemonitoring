# B2: Encrypted Android offline queue and controlled synchronization

Patients can preserve a single immutable pending observation in native encrypted
storage and reconcile interrupted delivery by its original UUID. Confirmation is
shown only for an exact matching server receipt. Existing PR11 assessment screens,
server eligibility/idempotency, patient authentication and research permissions remain
in force. No clinical interpretation, late submission, alerts or messaging is enabled.

Changes: mobile queue/storage/provider and existing form integration, native crypto
SDK dependency, patient revision metadata and own exact-UUID history filter, tests,
OpenAPI, dictionary/security and recovery/UAT documentation. No migration.

Validation: MySQL230 tests/2006 assertions; four isolated migration opt-ins; both
real-process concurrency tests; mobile94/dashboard49 tests; lint/typecheck/OpenAPI,
Composer/Pint, dashboard build and Android JS export. Actual emulator evidence and
limits: [validation](validation-b2.md), [screenshots](screenshots/b2/README.md).
Hosted Actions and signed physical-device APK are not verified locally.

Review [architecture/retention/rollback](offline-sync.md). One outstanding item,
16KiB conservative cap, cold offline restart stays locked until identity validation,
old credential copies remain encrypted and require an approved recovery policy.
No cloud build, merge or deployment. Human review and integrated synthetic UAT required.

PR13 pre-merge corrections: sticky quarantine across failed read-only reconciliation,
confirmed UUID/state-checked disposal of unsubmitted obsolete drafts, and fresh
server-day eligibility after removal. Mobile157 and MySQL239/2062 pass; the PR14
compatibility copy passes API242/2115 and dashboard53 after explicitly retaining
PublicationQuestion/InstrumentPublicationInput omitted by PR14's current head.
See [current review-fix verification](pr13-review-fixes.md) for conflicts, native
UAT evidence and remaining review/network limitations. Do not silently drop those
publication schemas or overwrite either controller history method during integration.
