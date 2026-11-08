# PR13 corrective native UAT — 8 November 2026 WIB

Actual Android11/API30 AOSP x86_64 emulator, Expo Go58.0.5,640×1280/320dpi.
Loopback API only; fresh pleural_pr13_fix_native_test MySQL8.4 schema, wholly
fabricated patient and test-only instrument. No real clinical data or signed APK.

| Image | Actual observation |
|---|---|
| [01](01-old-draft.png) | Unsubmitted encrypted draft, original2026-11-07 WIB date |
| [02](02-restored-old-date.png) | Actual process restart after server midnight; old date retained, zero POST |
| [03](03-confirm-draft-loss.png) | Date and permanent unsent-answer-loss warning; cancel tested before confirm |
| [04](04-new-day-draft.png) | Confirmed removal fetches2026-11-08 WIB and fresh server UUID |
| [05](05-new-day-unselected.png) | New-day VAS is unselected; old answers not reused |
| [06](06-quarantine-read-failed.png) | Isolated proxy422 then manual GET503; original REJECTED state retained, no further POST |
| [07](07-quarantine-restarted.png) | Same encrypted quarantine after foreground and actual process/bundle restart |
| [08](08-manual-reconciliation-only.png) | Successful read-only check still cannot deliver quarantined body |
| [09](09-strong-disposal-warning.png) | Existing stronger warning remains for potentially submitted data |
| [10](10-offline-pending.png) | Fresh reviewed report queued with delivery blocked |
| [11](11-reconciled-receipt.png) | Exact receipt after reconnect, dropped201 and history reconciliation; restored on final source |
| [12](12-logged-out.png) | Final native logout; token deletion checked independently |

Primary fixes captured on4c927fa; manual-only retry wording on26da1ff; final receipt
restore/logout uses39c4f2e, which additionally clears stale AUTH_REQUIRED metadata
after all manual reads succeed without a matching receipt. Storage schema/body
format unchanged throughout; no app-data reset, direct state editing or plaintext
queue write. The auth-follow-up and complete failure matrix are verified by Jest;
native outage is the representative REJECTED/503 case, not every hardware scenario.
Image06 predates the wording clarification; subsequent bundle removes the generic
five-automatic-attempt phrasing for quarantine. No source byte-for-byte/pixel claim.

## Controlled clock and fault method

Only the isolated API Carbon clock moved23:55 Nov7→00:05 Nov8 WIB. Device/host clock
and production settings were not changed. The emulator's real clock was later;
initial short-TTL login correctly revoked an already expired response. To run the
midnight scenario, only this test server used the existing bounded
AUTH_TOKEN_TTL_MINUTES=1440 setting. Default auth/expiry behavior is unchanged and
its automated tests pass. Fixed-clock received/device timestamps deliberately differ.

A local proxy rejected one POST422 without delivery, failed one manual GET503,
then counted zero additional POSTs through foreground and process restart. Database
had zero entries/audits before the stronger informed disposal. A fresh reviewed
body was queued offline, reconnected, committed201, then its response dropped.
Exact UUID history reconciliation produced one forwarded POST total, one immutable
entry/answer/submission audit and no classification/alert/notification effects.
The rejected UUID and obsolete draft UUID never became server observations.
No credentials, raw queue/preferences, headers or clinical payload logs are committed.

Software-emulator startup ANRs were dismissed with Wait. The UAT driver was corrected
to verify checkbox acknowledgement, handle uppercase native dialog labels and wait
for actual screen results; application data were preserved. New source was explicitly
reloaded because CI-mode Metro does not watch. Signed physical devices, backup/restore,
StrongBox/forensic deletion, full TalkBack/font-scale matrix and all error statuses on
native hardware remain NOT VERIFIED. Automated regressions cover both quarantine
states, GET stages/statuses, retry limits, account/revision isolation and storagefailure.
