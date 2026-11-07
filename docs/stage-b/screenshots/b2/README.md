# B2 actual Android evidence

Captured 8 November 2026 WIB, Android11/API30 AOSP x86_64 software emulator,
Expo Go58.0.5,640×1280 at320dpi (320dp). No signed APK/physical device claim.
Loopback-only test API, disposable `pleural_stage_b2_native_test`, fictitious
SYN-STAGE-B2, synthetic test-only question. No clinical approval is implied.

Producer source57b92f8; after real reboot recovery source0305c86 adds only a stricter
initial foreground guard and in-progress write text, same storage schema. The queue
was not cleared, migrated, re-keyed or recreated during the test. System/SystemUI
startup ANRs on this unaccelerated emulator were dismissed with Wait; they are a
native test-environment limitation, not omitted app failures.

| Screenshot | Actual check |
|---|---|
| [01](01-unavailable.png) | Native crypto/SecureStore ready; no instrument, no submission |
| [02](02-durable-review.png) | Reused F3/structured answer review with explicit VAS/author |
| [03](03-offline-pending.png) | Delivery blocked, encrypted pending item, no success claim |
| [04](04-cold-start-locked.png) | Actual reboot offline, authenticated draft remains hidden |
| [05](05-server-receipt.png) | Recovered committed UUID after process death/lost response |
| [06](06-server-history.png) | F9 actual server-backed immutable history |
| [07](07-logged-out.png) | Logged-out native state, clinical draft hidden |

Host-side AES/GCM envelope inspection found hashed namespace/ciphertext and no
plaintext clinical payload, participant ID or PIN markers. The pending ciphertext
hash survived the actual reboot unchanged. This checks native storage behavior,
not hardware-backed StrongBox, rooted-device resistance or forensic deletion.
Before reconnect: zero server entries/submission audits. Proxy held201 aftercommit;
client force-stopped before response. Onrestart exact own-history reconciliation
produced receipt with one POST total, one entry/answer/audit, zero clinical side effects.
No raw preferences, tokens, PINs or fault-proxy payloads are committed.

## PDF comparison / intentional differences

All15 pages were visually inspected during audit (gap-analysis.md). Reuse PR11's
F1 login, F2 acknowledgement, Home and F3 unselected VAS. F4 is a stored-receipt/local
state, never the PDF's clinical color/interpretation. F5–F8/F10 unchanged placeholders
remain clearly unavailable; this branch does not invent their questions or backend.
F9 shows server observations and approved historical version, no inferred adherence.
Help and119 remain fixed. Queue/reconciliation text is an intentional research-safety
addition. Labels remain Indonesian; typography uses existing16sp minimum/48dpcontrols
and scrolling. New text was checked at320dp viewport; full signed-device scaling,
TalkBack and multiple-device font-scale verification remain separate UAT.
The PDF wireframes are structural references, not a pixel-perfect claim.
