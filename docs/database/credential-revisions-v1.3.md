# Patient credential revisions v1.3 (PR-10 correction)

Additive migration `2026_11_07_000005_add_patient_credential_revision.php` adds
`patient_credentials.revision CHAR(36) NULL`, with a unique nullable index.
Existing rows/hashes/issuers/revocations are untouched and initially retain NULL.
No research table, existing constraint or immutable audit record changes.

The revision is a non-secret random UUID, independent of PINs/hashes, and is not
an authentication capability. Supported model creation and PIN/revocation changes
rotate it; legacy rows get a version on their next authorized mutation. Never use
raw SQL to bypass the model's hashing, revision or session-revocation hooks.

GET projects status and revision from a single indexed participant/credential join
with current intervention/active filters after policy authorization. POST/DELETE
require `expected_revision` (present, UUID or explicit null). Under the existing
participant/credential write locks, mismatch returns 409 before any change. Audit
failure rolls back hash/revision/revocation and token changes together. Receipts
return only the committed non-secret revision alongside the existing message.

Use explicit null only when that is the latest server snapshot (no credential or
legacy version). Two simultaneous writers using the same snapshot cannot both
commit. A later writer may explicitly reload and confirm another mutation; no
long-lived DB lock or disclosure lease blocks legitimate reset/revocation.

Deploy this additive migration/API/dashboard together after review. Old `{pin}`-only
management callers must supply expected_revision; patient login is unchanged.
No existing database was reset for this correction. Upgrade, locking and integrity
checks use disposable MySQL schemas only.
