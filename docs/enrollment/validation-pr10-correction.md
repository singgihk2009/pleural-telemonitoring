# PR-10 correction validation — 7 November 2026 (WIB)

Amends the existing PR #10 branch after original head `2e77e1f`. The original
[validation](validation-pr10.md) remains historical evidence, not proof of this
revised protocol. No deployment, EAS build or production infrastructure change.

| Check | Result |
| --- | --- |
| Laravel/MySQL security and integrity suite | PASS — 206 tests, 1,800 assertions; 3 opt-in checks run separately below |
| Simultaneous authorized mutations | PASS — two real PHP/Laravel processes and MySQL connections; reset/reset and reset/revoke each yield exactly one success and one 409 |
| Status-read lock contention | PASS — another process completes authorized GET while the participant row is exclusively locked by the test coordinator |
| Revision receipts / supported model updates / legacy NULL | PASS — fresh random versions, exact receipt/snapshot match, stale/reset/revoke conflicts, rejected missing/malformed versions and legacy hash preservation |
| Status read privacy/performance | PASS — safe status/revision projection, no patient hash query, no FOR UPDATE or successful-read audit insertion; non-cacheable and policy protected |
| Dashboard regressions | PASS — 46 tests, including mismatch with AKTIF, missing receipt, lost-response recovery, manual/automatic/focus/visibility checks, pending-close/Save/Escape guards, logout, forced context changes and timeout |
| Existing mobile tests | PASS — 40; no mobile source/auth/session changes |
| Workspace lint and typecheck | PASS |
| Composer strict validation / Pint | PASS — 95 PHP files clean |
| OpenAPI | PASS — required expected_revision, public revision snapshots/receipts and 409 semantics |
| Dashboard production build | PASS |
| Android JS export | PASS — not an APK, device test or EAS build |
| Isolated migration lifecycle | PASS — 11 assertions in a new disposable schema |
| Historical authentication upgrade | PASS — 13 assertions in a new disposable schema; old-schema fixture retains hashing casts while excluding the not-yet-existing revision hook |
| Grant-scope upgrade | PASS — 19 assertions in a new disposable schema |
| Hosted GitHub checks | NOT VERIFIED locally — API access has returned Forbidden; local equivalents above are separate evidence |
| Installed UAT APK / physical device | NOT EXECUTED — this correction does not change the Android client or initiate deployment/build |

## Real concurrency regression

`apps/api/tests/Concurrency/credential_operations.php` is a guarded standalone
regression, also wired into the existing API CI job. It requires APP_ENV=testing,
MySQL and an otherwise empty fixture schema ending `_credential_concurrency_test`,
with additive migrations already applied. It refuses non-empty participant/staff
fixtures; it never resets a schema or removes immutable audit records.

The coordinator creates two synthetic researchers and a synthetic participant,
then holds the participant row lock. Both worker processes authenticate through
the Laravel HTTP kernel and signal immediately before their own locking reads.
Only after both have reached that barrier does the coordinator release its lock.
Thus the requests really contend, rather than merely being submitted sequentially.
The test checks the winning revision/hash, old-session rejection, one audit per
committed mutation, stale 409 results and a non-locking status-read process while
the row is held. Tokens/PINs travel in private process pipes, not CLI arguments or
logs. Test staff sessions are revoked when the regression completes.

Local command after preparing a **new disposable** schema and migrating it:

```sh
APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=<new_prefix>_credential_concurrency_test \
BCRYPT_ROUNDS=4 php tests/Concurrency/credential_operations.php
```

Run from `apps/api` with the existing test DB connection settings. The lower work
factor is test-only. CI uses its ephemeral MySQL service and restricted runtime
user after administrator migration; no production service configuration changes.
All local migration checks used newly created test schemas. No existing research
or shared development database was reset, and existing constraints/audit history
remain intact.

## Final guarantees and limits

- Mandatory compare-and-swap versions prevent two writers from committing from
  the same snapshot. Stale conflicts do not change hashes, tokens or mutation audits.
- Plaintext is shown only for the exact receipt revision matching an active status
  snapshot. A different active credential is not evidence that this PIN is current.
- Reads are point-in-time: another explicitly confirmed authorized change can
  happen later. The UI labels the verification result as a snapshot, rechecks an
  open display every five seconds/on focus/visibility, hides during checking and
  clears on mismatch/failure. It does not promise continuous validity or block
  legitimate revocation with a human-duration DB lock/lease. Login is authoritative.
- Pending ordinary editor closure, Save and Escape are blocked until verification
  completes or the 30-second client timeout. Explicit logout remains possible and
  clears data immediately. Browser unload warnings are best-effort only.
- An abort/lost response/forced closure is not server cancellation. No PIN is kept
  for recovery in browser storage. Reload and explicitly reset using the current
  revision; never automatically replay a timed-out write. Immutable JS strings
  cannot be promised cryptographically erased from browser internals.

See the [workflow and API protocol](pin-management-pr10.md) and
[additive revision schema](../database/credential-revisions-v1.3.md). Old management
callers must add expected_revision; the patient login contract is unchanged.
