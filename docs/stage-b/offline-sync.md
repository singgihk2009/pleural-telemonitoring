# B2 — Encrypted Android queue and controlled foreground synchronization

Base merged PR11 main be6702e; branch feature/stage-b-encrypted-offline-sync.
This is synthetic research/UAT work, not a production deployment or clinical engine.

## Architecture / database impact

Reuses PR11 ingestion, exact UUID replay, canonical server fingerprint, immutable
entries/answers/audits, WIB schedule and participant/credential locks. No migration
or historical record change. Auth me adds patient-only nonsecret credential_revision;
legacy NULL disables persistent mode. Staff never receive a patient's revision here.
Own history supports exact submission_id filtering for reconciliation; foreign UUID
returns an empty page, malformed UUID422. PIN hashing/reset/revocation unchanged.
Demo emptiness checks enumerate the selected schema only, avoiding cross-schema
names without relaxing any nonempty-data denial.

Expo58 uses existing SecureStore58 and new manifest-compatible expo-crypto~58.0.7.
Expo install was invoked through pinned pnpm10.28.2 (global pnpm11 was rejected).
Only this dependency and its lock entry were added. SDK source was inspected:
SecureStore Android uses AES/GCM and Android Keystore; async getRandomBytesAsync
uses native randomness with no development Math.random fallback. The synchronous
random helper is not used. External SDK docs fetch returned403, so local SDK source,
compatibility manifest and actual native tests are evidence, not an online doctor
claim. Preserve Router, SecureStore backup plugin, UAT package/API/EAS configuration.

## Confidential storage and isolation

Android only; unavailable native encryption, corrupt salt/index/item, failed writes
or readback mismatch fail closed. No AsyncStorage/file/plaintext fallback. No token
or PIN in queue records/index. An installation secret (32 native random bytes)
is stored in SecureStore, and SHA256(domain+secret+API origin+verified patient ID+credential
revision) derives a nonreversible item key. A separately salted owner key indexes
hashed namespaces only. The encrypted owner index warns about locked predecessors
without decrypting old-revision bodies or another patient's index.

Root opens a queue only after existing patient identity/profile validation.
Logout/expired/revoked session hides plaintext immediately through scope invalidation;
records remain encrypted, never silently erased. Same patient/revision can recover
after validated re-login with a new token; other patients or credential revisions
cannot load that item. Credential rotation (including rehash) locks old copies.
These copies remain encrypted and are not migrated automatically. Use server history
and a separately approved recovery policy; clearing app data/uninstall is an informed,
OS-level last resort, losing all local unsynchronized copies, not server records.
No root/compromised-OS or instantaneous offline remote-revocation guarantee is made.

Cold restart while the API is unreachable stays locked until the session is validated
again. This is deliberate privacy-first behavior, not a claim of offline authentication.
SecureStore backup exclusion is preserved, but signed-app backup/restore and physical
hardware Keystore behavior require separate APK UAT. WHEN_UNLOCKED_THIS_DEVICE_ONLY
is set for compatibility; Android protection is its native Keystore/sandbox, not a
claim that the Apple accessibility option enforces Android biometric presence.

## Queue lifecycle and recovery

One outstanding item per current identity/revision. No unconfirmed item can be
replaced by a new day's report. A slot contains original UUID, version, intended
server WIB date, immutable device timestamp/body, approved display snapshot and
local state. DRAFT begins with null VAS/author. Changes are serialized and verified
before confirmation. PENDING_SYNC freezes the body. SYNCING is durably read back
before POST; restart treats it as uncertain. SYNCED requires an exact matching
server receipt/history (ID/version/date/time/VAS/author/answers), not an HTTP success
or active credential alone. No local state is sent as if it were a server receipt.

Snapshot/body fields are whitelisted; no accidental auth metadata is persisted.
Per-item JSON is conservatively bounded to16KiB using a UTF8 upper bound, rejecting
oversized instruments before sending. Platform limits can still fail closed. One
owner index supports32 credential namespaces; capacity/corruption requires explicit
review, never silent eviction. Real clinical instrument size must be verified in UAT.

Root has one foreground-only worker per namespace with a shared in-process mutex
across instances. No OS/headless task or multi-process worker is configured or claimed.
Connectivity return, foreground and explicit action can synchronize only when the
patient has acknowledged the existing statement. Worker transport always captures
the original token; an account switch can never send an old body under a new login.
An in-flight request can commit after background/closure; cancellation is never inferred.

Worker validates auth me identity/revision, reads current status and exact own history,
then acknowledges any identical committed receipt. It checks current version/date/
eligibility before a new POST, never changes date or body. Original POST exact replay
remains safe under the unchanged server contract.409 requires read-only reconciliation,
422 freezes for validation review,401/403 stops and invokes existing logout. Network/
429/5xx retain data with bounded exponential backoff, honoring Retry-After, at most
five automatic attempts before explicit manual action. No early manual rate bypass.
A mismatched receipt/unsupported/currently unavailable/late version never claims success.

Seven-day local age triggers retention REVIEW/quarantine, not clinical expiry or
automatic deletion. Unsynchronized data is retained encrypted until explicit informed
disposal/reconciliation. Retention/locked-old-credential recovery governance still
requires research approval before real data. Explicit discard uses SecureStore delete
plus verification; it does not cancel a server request or promise forensic flash erasure.
Corrupt storage is retained for review, never overwritten or silently reset.

## UI and visual guide

PR11 VAS/questions/author/review/receipt components are reused; no default VAS,
computed risk colors, delta interpretation, alerts, messages or final questionnaire.
Persistent mode adds neutral local-storage/acknowledgement/reconciliation text and
Home draft entry. Required choices and verified writes gate review/confirmation.
Old-server metadata absence preserves labelled RAM-only online behavior; new-server
storage failure disables submission instead of silently falling back. Cached drafts
are provisional; validity is checked at synchronization. Validated server receipt alone shows
success. Help/119 remain available. Unavailable approved instruments still cannot
create new drafts/submissions. Existing session-scoped acknowledgement is unchanged;
server-versioned consent persistence remains separate approved work.

Remaining design differences from PDF: no colored clinical F4; durable local states
are not risk levels; F9 uses server observations rather than invented compliance/
classification; no offline late-date substitution, background jobs or automatic alerts.
See screenshots/B2 evidence index and validation-b2.md (actual emulator vs export).
