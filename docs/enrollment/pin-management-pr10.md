# PR-10 patient Android access: reviewed contract gaps

The existing POST `/api/v1/participants/{id}/credentials` accepts `{pin: string}`
(exactly six ASCII digits), hashes through PatientCredential and returns only a
201 message; it revokes old tokens on reset. DELETE on that path marks the row
revoked and deletes sessions. No existing endpoint returns a PIN. Login uses
`{code, pin}`; identity/profile use `/auth/me` and `/participants/{id}`.

Before API changes, inspection identified:

- No authoritative credential-status read. Add GET on the same path, policy
  protected, returning only `data.status`: BELUM_DITERBITKAN, AKTIF or DICABUT.
  The row is retained on revoke, so never-issued and revoked are distinguishable.
- The credential policy currently allows assessor/researcher combinations when
  blinding is off. Deny assessor roles unconditionally for credential management.
- Revoke acquires the participant lock but does not reauthorize the freshly locked
  record. Recheck the same eligibility/role policy while holding the lock.
- Reset can accept the current PIN again. Reject reuse with 422 so a successful
  reset always invalidates the previous PIN; the existing request/response shape
  stays unchanged. Record reset with a safe, distinct audit action.

Browser PIN generation uses Web Crypto rejection sampling, retains the new value
only until successful one-time display is closed, and sends it in the existing
POST body. No server-generated plaintext response, schema migration, seeded PIN,
clinical rule, delivery integration or new permission is introduced.


## Researcher workflow (development/UAT; synthetic participants only)

1. Sign in using the existing staff researcher authentication. Demo-seeded staff
   accounts may be used through their documented secure credential setup; the
   demo seeder still creates no patient PINs.
2. Enroll an INTERVENSI/AKTIF participant with consent/baseline and save. Open its
   editor from Pendaftaran Studi. No credential action exists on an unsaved record.
3. In Akses Aplikasi Android, wait for the server status. Terbitkan PIN Android
   uses Web Crypto and the existing POST; the issued PIN is never returned by GET.
4. Note the code and one-time PIN and communicate them through the approved secure
   research procedure. Do not send them to logs, shared screenshots, analytics or
   unapproved messaging. Close the PIN display when finished.
5. On the existing Android F1 screen, enter that code and the six-digit string,
   preserving any leading zero. Login/identity/profile endpoints are unchanged.
6. For reset, confirm Reset PIN Android, securely convey the new one-time PIN,
   then close it. The old PIN and all old patient tokens stop working. For revoke,
   confirm Cabut Akses Android; subsequent login is denied without deleting data.
7. Control/inactive participants cannot be provisioned. Inactive status already
   denies patient access and revokes tokens through enrollment; the credential
   management policy remains restricted to active intervention records.

## Failure and concurrency limits

If a response is lost, status verification fails, or the editor closes during a
write, the server may have completed the operation. No plaintext is recovered;
reload authoritative status and perform a confirmed reset if necessary. No request
is automatically retried. Multiple authorized researchers must coordinate: row
locks serialize writes, but a later authorized reset can supersede an earlier PIN.
A non-active status recheck prevents display; identical active status alone is not
an optimistic credential version. There is no new cross-researcher version API.

The transient browser display is not a password vault. Clearing React state is
not a cryptographic erasure guarantee for immutable JS strings or browser internals.
There is no clipboard persistence, automatic message, seeded patient credential,
PIN recovery or offline credential-management mode. A real UAT APK/device run and
normal reviewed deployment remain separate from this local branch's validation.
