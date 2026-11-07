# PR-10 patient Android access and concurrency protocol

Development/UAT only, using synthetic participants. The editor remains under
**Pendaftaran Studi → Ubah data partisipan → Akses Aplikasi Android**. Principal
researchers may manage only active intervention records. IT/assessor combinations
are denied regardless of blinding; controls and inactive participants have no
issuance/reset action. Patient login and research records are unchanged.

## Final API contract (PR-10 review correction)

All paths below are `/api/v1/participants/{id}/credentials`, authenticated and
policy protected, with non-cacheable responses. No plaintext PIN/hash is returned.

| Method | Request | Response |
| --- | --- | --- |
| GET | No body | `data: {status, revision}` |
| POST | `{pin: string, expected_revision: UUID or null}` | 201 `{message, data: {revision}}` |
| DELETE | `{expected_revision: UUID or null}` | 200 `{message, data: {revision}}` |

Status is BELUM_DITERBITKAN, AKTIF or DICABUT. Revision is a non-secret random UUID,
not a PIN-derived value, hash or authentication capability. Explicit null means
no credential or a legacy row not yet assigned a revision. It must be the exact
latest GET value. Missing/malformed expected_revision returns 422; stale values
return 409 before hash, token, revision or mutation-audit changes. The original
`{pin}`-only management callers must be updated; this is a narrowly scoped review
correction to the unmerged PR. Android `{code, pin}` login is unchanged.

A new additive [nullable revision column](../database/credential-revisions-v1.3.md)
preserves existing credentials. Supported model PIN/revocation changes rotate it.
Mutations retain participant/credential locks, fresh policy checks, adaptive
hashing, token revocation and transactional audit. A successful reset requires a
different PIN. Mutations remain audited as credential.issued/reset/revoked; all
old audit rows are retained unchanged.

GET uses one indexed, consistent participant/credential snapshot after policy
checks and current active/intervention filters. It does not acquire an exclusive
participant lock or append an audit for each successful status poll. Permission
denials still use the existing security audit middleware; Sanctum's normal token
usage bookkeeping remains. No sensitive relationship is loaded/decrypted.

## Disclosure and simultaneous operations

Two authorized writers using the same expected revision cannot both commit.
Exactly one wins; the other receives 409 and must reload status and explicitly
confirm a new operation. Reset/revoke have the same comparison, so a stale revoke
cannot silently revoke a newer credential. No request is automatically retried.

The browser generates six ASCII digits through Web Crypto rejection sampling;
leading zeros are valid and there is no random/default fallback. After POST,
**AKTIF alone never permits display**. The receipt revision must exactly match an
active GET snapshot. A missing receipt, failed verification or different revision
suppresses the PIN and gives reload/reset guidance.

Verification is point-in-time, not a promise that later authorized changes cannot
occur. No long-lived transaction or disclosure lease blocks legitimate revocation.
The display states when a verification result was received and warns that access
may subsequently change. While open it rechecks every five seconds and on focus/
visibility return; it hides plaintext while checking or hidden, and clears it on
mismatch/failure. **Verifikasi ulang PIN** checks immediately before handoff.
There is necessarily a time gap between any snapshot and a later server write;
only patient login is the final authority on whether a PIN still authenticates.
The UI does not describe a checked PIN as continuously valid. A superseded
revision observed before disclosure is never shown as the successful new PIN.

## Researcher workflow

1. Sign in with the existing staff researcher account; the demo seeder still
   creates no patient PIN. Enroll/save an INTERVENSI/AKTIF synthetic participant.
2. Open its editor and wait for authoritative Android status/revision. Use
   **Terbitkan PIN Android** or the confirmed **Reset PIN Android** action.
3. Wait for the committed receipt and matching status verification. Normal editor
   Close/Cancel, Escape and enrollment Save are blocked during this operation
   (up to a 30-second client timeout) to avoid discarding a successfully issued PIN.
   A browser unload warning is best-effort; browsers may ignore it.
4. **Keluar dan hapus PIN** remains available while a write is pending. It uses
   normal staff logout and clears local data immediately, without waiting for the
   credential request. Forced participant/session changes also discard plaintext
   and prevent late callbacks from displaying it under a different context.
5. Record and communicate the respondent code and verified one-time PIN through
   the approved secure research procedure. Use **Verifikasi ulang PIN** immediately
   before handoff. Do not put it in logs/shared screenshots or unapproved messages.
6. **Tutup PIN**, closing the editor, participant changes, logout or expiry discard
   the display. No plaintext goes into URLs, browser storage, analytics or audit.
   Stored adaptive hashes cannot recover it. No existing-PIN read endpoint exists.
7. On Android F1 enter the code and six-digit string, preserving leading zeros.
   Identity/profile and logout endpoints are unchanged. Confirmed reset invalidates
   old PIN/tokens; confirmed revoke denies login without deleting research data.

## Lost responses, forced closure and recovery

A browser abort, timeout, logout, navigation or closed editor means only that the
client stopped waiting. **It is not a confirmed server cancellation.** The server
may have committed and revoked old sessions. The UI warns before/during a write
and on an ambiguous failure; it never claims that cancellation restored the old PIN.
No plaintext is retained across logout/closure to make the result recoverable.

After returning, reload authoritative status. If a write may have committed but its
PIN/receipt was lost, use the current revision and explicitly confirm a fresh reset
(or revoke instead). Coordinate with other researchers. Do not replay an old
expected_revision or blindly retry a timed-out POST. A stale 409 is a confirmed
non-write, whereas a network failure can be an unknown commit. Clear UI state
makes no cryptographic JS-memory erasure guarantee.

## Reviewed original gaps and validation

Initial inspection found no status read; the previous status-only recheck could
not distinguish another active credential. It also allowed closing during pending
issuance, and status reads took a write lock and generated repetitive audits.
The final protocol above replaces those limitations; no plaintext response,
clinical scoring, messaging, role escalation, production deployment or EAS change
is introduced. See [correction validation](validation-pr10-correction.md), including
real two-process MySQL contention and pending/lost-response UI regressions.
