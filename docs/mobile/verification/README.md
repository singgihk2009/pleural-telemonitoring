# PR-05 verification evidence

Synthetic fixtures only. No real patient data, production deployment or automatic
merge was used. The supplied PDF's 15 pages were rendered and visually inspected.
See the [screen comparison and intentional differences](../PR-05.md).

## Local automated checks

| Check | Result |
| --- | --- |
| Frozen pnpm install | PASS |
| Workspace lint / typecheck | PASS |
| Dashboard tests | PASS — 13 |
| Mobile component/navigation/security tests | PASS — 40 |
| OpenAPI validation | PASS |
| Dashboard production build | PASS |
| Android JavaScript export | PASS — not an APK or native test |
| Full MySQL API suite | PASS — 163 tests, 1,373 assertions; 3 opt-in checks skipped here |
| Separate MySQL lifecycle / auth upgrade / grant-scope checks | PASS — 11 / 13 / 19 assertions, each in a new disposable schema |
| Composer validation / Pint | PASS — 90 PHP files clean |
| Compose configuration / readiness / CORS smoke | PASS |
| Real HTTP patient login/profile/logout smoke | PASS — isolated synthetic schema, revoked token rejected |
| Hosted GitHub Actions | NOT VERIFIED — GitHub API access is restricted |

Mobile tests cover invalid PINs, 401/429/503/network failures, patient-only identity,
SecureStore persistence/failure, no persisted PIN/study code, logout, restored
revocation, active-session expiration, acknowledgement, protected navigation,
day-12 VAS flow/back, day-31 final-only flow, no initial VAS, explicit zero/100,
clamped gesture input, one-mm accessibility actions, disabled symptom submission,
WIB/date serialization and emergency confirmation. A regression verifies that a
failed public-internet probe does not disable a reachable configured API; an
actual disconnected network disables login while preserving emergency access.
The original unused placeholder component test remains passing; it is not native
evidence.

## Actual Android execution

**PASS on Android 11 / API 30 AOSP x86_64, Expo Go 58.0.5**, using emulator
37.2.13, headless SwiftShader and software CPU emulation (`-accel off`). The
observed viewport is 640×1280 pixels, density 320 (320×640dp). Initial API 35 and
AOSP first-boot/System UI problems eventually resolved; they are not application
performance results. No physical device was connected.

The app used the real isolated Laravel API and `pleural_pr05_test` schema over a
loopback-only relay. One wholly fabricated intervention participant was used.
Native login, identity/profile retrieval, acknowledgement gating, study day 12,
explicit zero VAS, step-back preservation, step-scroll reset, disabled symptom
Send and all F1–F10 routes/placeholders were exercised. Native process restart
restored the secure token without entering the PIN and required a fresh
session-only acknowledgement. Native logout removed the server token (verified
row count **0**) and restarting stayed logged out.

The synthetic participant was then changed to day 31 only after verifying it had
no research history. Native closure hid daily navigation and allowed the final
placeholder. No daily entry or alert was created (**0 / 0**). This changed no
existing research database or production record. A server-expired synthetic token
was then rejected on foreground revalidation; Android cleared the protected
workspace and returned to [the expired-session login state](screenshots/F1-session-expired.png).

Emergency confirmation and cancellation were exercised on Android. No call was
placed. The exact `tel:119` dialer request is tested with a JavaScript mock; actual
native dialer opening/calling is not claimed. The nurse number and messaging
services remain unavailable, explicitly labelled, and inactive.

## Native screenshots

These PNGs come from `adb exec-out screencap -p`, not browser/device-shaped
mockups or JavaScript export. Active Android headers were checked before the
final captures; screenshots were visually inspected and headers additionally
cross-checked with OCR to catch captures taken before a route transition ended.
The Expo Go gear overlay/status bar is development-host chrome, not app UI.

| Screen / PDF page | Android evidence | Reference comparison |
| --- | --- | --- |
| F1 / 5 | [Login](screenshots/F1-login-top.png), [fields/actions](screenshots/F1-login-fields.png) | Empty code/PIN, no signup, login/error flow, fixed safety actions |
| F2 / 5 | [Warning](screenshots/F2-warning-top.png), [acknowledgement](screenshots/F2-acknowledgement.png) | Exact warning, checkbox and gated Continue; session-only notice |
| Home / 4–5, 8 | [Day 12](screenshots/Home-day12.png), [actions](screenshots/Home-actions.png), [day 31](screenshots/Home-day31.png) | Study day, prominent daily action, ordered menu; final-only closure |
| F3 VAS / 5 | [Unselected](screenshots/F3-VAS-empty.png), [explicit zero](screenshots/F3-VAS-zero.png) | No initial value/thumb; integer selection, no clinical band/result |
| F3 symptoms / 6 | [Notice and fields](screenshots/F3-symptoms.png), [disabled actions](screenshots/F3-symptoms-actions.png) | Wireframe examples, fixed choices; explicitly non-final and inactive |
| F4 / 6 | [Feedback placeholder](screenshots/F4-feedback-placeholder.png) | Neutral unavailable state replaces unapproved clinical feedback |
| F5 / 7 | [Contact](screenshots/F5-contact-messages.png), [message placeholder](screenshots/F5-message-placeholder.png) | No fabricated conversation/number; unavailable actions explicit |
| F6 / 4, 6 | [Repeat placeholder](screenshots/F6-repeat-placeholder.png) | No invented verified schedule, reminder or submission |
| F7 / 7 | [Education](screenshots/F7-education.png), [later topics](screenshots/F7-education-topics.png) | Four named topics; no invented researcher content |
| F8 / 7 | [Event types](screenshots/F8-event-placeholder.png), [fields](screenshots/F8-event-fields.png) | Disabled date/place/reason/Send; no false saved state |
| F9 / 7 | [Graph state](screenshots/F9-history.png), [calendar](screenshots/F9-calendar.png) | Empty graph, no fabricated baseline/adherence/check marks; responsive calendar |
| F10 / 8 | [Final placeholder](screenshots/F10-final-placeholder.png), [day 31](screenshots/F10-day31.png) | No invented Health-ITUES items, scores or enabled submission |

Additional safety evidence: [native confirmation](screenshots/Emergency-confirmation.png).
No pixel-perfect matching claim is made. Extra cards, scrolling on small screens,
neutral placeholders, and the responsive calendar column count are intentional.

## Accessibility

Source/component checks PASS: body text at least 16sp, scaling enabled without a
maximum multiplier, labelled roles/states, and buttons/back/checkbox/slider at
least 48dp. Noninteractive calendar cells are not touch targets. The two-step
form resets scrolling when the screen title changes, so its non-final-catalog
notice is visible immediately.

Native checks PASS: the adjustable VAS target measures **112px / 56dp**; back
measures **96px / 48dp**. At default density, nurse/emergency targets measure
**156px / 78dp** and **108px / 54dp**. No VAS score is selected until touch or an
accessibility adjustment. The VAS track uses an explicit opaque contrasting view
rather than relying on platform tint alpha.

At [200% font scaling](screenshots/F2-font200.png), both safety actions remain
inside the viewport: nurse `[24,908][310,1256]`, emergency
`[330,908][616,1160]`. The [scrolled warning](screenshots/F2-font200-scrolled.png)
remains accessible. At [125% display scaling](screenshots/F2-display125.png)
(density 400), actions wrap into full-width rows and measure **135px / 54dp**
each: `[30,956][610,1091]` and `[30,1116][610,1251]`. The decorative header
compacts for large text; notices/content scroll independently of the safety bar.
These are representative F2/frame scaling checks, not a claim that every screen
was exhaustively tested at every font/display combination.

Calculated sRGB palette contrast (not a device photometric measurement):

| Foreground / background | Ratio |
| --- | --- |
| Body `#24364B` / white | 12.32:1 |
| White / blue `#225FA4` | 6.48:1 |
| White / emergency red `#B82935` | 6.16:1 |
| Muted `#52657B` / background `#F3F7FC` | 5.57:1 |

## Remaining limits

TalkBack spoken order/gestures, a physical handset, Android 7 compatibility,
standalone APK minimum OS/size and generated SecureStore backup exclusions remain
NOT VERIFIED. Expo Go's APK size is not the application's APK size. Standalone
native configuration needs a separate build; JavaScript export does not verify it.
The existing Expo 58 platform should not be downgraded to satisfy the old PDF's
Android 7 proposal without a separate compatibility review.

Backend-dependent daily submission/classification, history synchronization,
researcher education content, messages, nurse-number settings, event reports,
repeat scheduling, final questionnaire/scoring and versioned server
acknowledgement are intentionally inactive. No clinical engine, notifications,
FCM, WhatsApp, real patient dataset, deployment or automatic merge is included.


## PR #5 P2 session-race correction — 2026-11-06

PASS: 16 new deferred-promise regressions plus the original 24 mobile tests.
Each of token issuance, identity lookup, participant lookup and SecureStore write
is paused while background/foreground is emitted; successful login survives,
without a second restore or deletion. Each stage is also interrupted by logout,
provider unmount and a newer login; abandoned tokens are revoked and only the
newest login can persist. The overlapping SecureStore-write test checks cleanup
ordering before the replacement write. Existing patient-only checks, secure-store
failure, logout and expiration tests remain passing.

PASS: dashboard 13 tests; MySQL 8.4 API 163 tests / 1,373 assertions;
separate lifecycle/auth-upgrade/grant-scope tests 11/13/19 assertions in newly
created disposable schemas; Composer strict validation/Pint; workspace lint and
typecheck; OpenAPI; dashboard build; Android JavaScript export.

PDF pages 4–8 were visually re-inspected against the F1–F10 mapping above and
screen implementation. No layout changed; the existing 28 native screenshots
remain the visual baseline. No clinical engine, active alerts/messages or symptom
submission was added. Acknowledgement and backend-placeholder limitations remain.

Hosted GitHub checks and a fresh Codex review require GitHub API access, which
currently returns Forbidden from this environment. The native results above
belong to the original PR-05 validation; correction-specific native results are
reported separately below and must not be inferred from the JavaScript export.

Correction-specific Android smoke: NOT VERIFIED. A fresh launch of the existing
API 30 AOSP AVD under software emulation exposed an Android runtime failure:
`service check activity` reported `not found`; two Expo Go installation attempts
failed with `Broken pipe (32)` and `Can't find service: package`. Consequently
this revision has no new successful native login/lifecycle result or screenshot.
The original screenshots are retained, not relabelled as new evidence. A working
emulator/device must rerun login/background/foreground/logout before merge.
The deferred tests establish the race fix at each async boundary; they do not
establish native SecureStore timing or Android lifecycle behavior.

Regression sensitivity check: all 16 new tests fail against the original
`937703c` session implementation and pass with this correction.
