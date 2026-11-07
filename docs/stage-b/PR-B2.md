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
