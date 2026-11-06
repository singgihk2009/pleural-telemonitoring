# PR #5 final verification — 6 November 2026 (WIB)

Reviewed application head: `b4470f1ac433a8e0d766ef0fb5fea98830396920`.
This report records a new run; older screenshot results remain historical evidence.

| Check | Status | Evidence |
| --- | --- | --- |
| Session correction review | PASS | Foreground/retry yields while `pendingLogin` owns token issuance, identity/profile verification and persistence. Generation checks prevent stale commits; `finally` attempts revocation for every issued, uncommitted token. SecureStore writes and stale cleanup share a serial queue. No additional defect found in the reviewed race paths. |
| Sixteen race regressions | PASS | All four async boundaries tested for foreground/retry, logout, unmount and superseding login; replacement-token cleanup ordering checked. |
| Existing mobile authentication/navigation/UI tests | PASS | All 40 mobile tests, including the original 24, pass. Patient-only identity, persistence failure, logout, revocation and expiry checks remain passing. |
| MySQL API/security suite | PASS | MySQL 8.4, PHP 8.4: 175 tests / 1,515 assertions; three opt-in migration tests skipped in the normal suite. |
| Dashboard tests | PASS | 13 tests. |
| Lint/typecheck | PASS | Both workspace apps. |
| Composer validation/Pint | PASS | Strict Composer validation; 93 PHP files clean. |
| OpenAPI/dashboard build/Android JavaScript export | PASS | Export is not native lifecycle evidence. |
| Synthetic native-test fixture | PASS | New disposable `pr05_final_native_test` schema; four original migrations applied using schema administrator; runtime uses restricted application user. One fabricated intervention participant and hashed test PIN. No real research database reset. |
| Real HTTP patient login/profile/logout | PASS | Login 200, patient identity/profile 200, logout 200, revoked bearer rejected with 401. Tokens never printed or committed. |
| Native login/background/foreground | PASS | Held successful login response while Android paused/resumed the app; patient identity/profile retrieval completed and F2 then day-12 Home were reached. Native SecureStore restoration and logout also passed; see evidence below. |
| Fresh external code/security review | NOT VERIFIED | Authorized `@codex review` request attempted for b4470f1; GitHub GraphQL endpoint returned Forbidden, so the request was not posted. Local review above is not a substitute for independent review. |
| Hosted CI | NOT VERIFIED | GitHub API unavailable. Local test results do not establish hosted checks. |
| Existing P2 thread resolution | NOT VERIFIED | Correction confirmed by regressions and native execution; thread remains unchanged because GitHub review-thread API access is unavailable. |

## F1–F10 comparison

Visually re-inspected PDF pages 4–8 and the existing native F1–F10 captures.
The PDF SHA-256 remains
`0a564e84de7f45acf15d176de4db3167dda7d0f0047cd9d216219d3039516a1b`.
There are no mobile source changes between the corrected f9de98a and b4470f1.

| Screen | Status | Comparison and intentional boundary |
| --- | --- | --- |
| F1 | PASS | Respondent code/six-digit PIN, no self-registration; fixed safety actions. |
| F2 | PASS | Initial warning/explicit checkbox; session-only acknowledgement remains an explicit difference from server-versioned persistence. |
| F3 | PASS | Empty VAS until interaction; two-step structure; symptom catalog/Send disabled and labelled non-final. |
| F4 | PASS | Neutral unavailable-server-result placeholder; no computed clinical result or reassurance. |
| F5 | PASS | Contact/messages structure; no invented nurse number, conversation or active messaging. |
| F6 | PASS | Repeat-VAS placeholder; no active schedule/reminder/submission. |
| F7 | PASS | Four education topics; approved content remains unavailable. |
| F8 | PASS | Event categories/date/place/reason structure; fields/submission disabled. |
| F9 | PASS | Thirty-day graph/calendar structure; no invented history/adherence. |
| F10 | PASS | Final-form shell; no invented Health-ITUES items or score. |

PASS here means comparison with the approved UI/placeholder scope, not complete
backend functionality or pixel-perfect matching. Navy masthead/cards, spacing,
scrolling and neutral placeholders remain documented intentional differences.
The existing [28 captures and accessibility results](README.md#native-screenshots)
remain prior native evidence, not recaptures of this head. No classification,
alerts, messaging, notifications or unapproved symptom submission was enabled.

## Native lifecycle evidence

Actual Android execution: Android 11 / API 30 AOSP x86_64, Expo Go 58.0.5,
emulator 37.2.13, SwiftShader, no KVM (`-accel off`), 640×1280 px at density 320.
The disposable AVD was reset after confirming there was no live emulator process;
initial Android/System UI ANRs and lengthy first-install dex compilation recovered.
These startup delays are environment observations, not app performance results.

The unmodified app connected through adb reverse to a loopback-only test relay
and the real Laravel API using `pr05_final_native_test`. The relay forwarded real
patient authentication, held its successful response, then released it after
background/foreground commands. It neither fabricated authentication responses
nor changed mobile timeout/session behavior. Android lifecycle events confirm:

- Issued response held: 18:31:09.756 WIB.
- ExperienceActivity paused: 18:31:14.691 WIB.
- Foreground launch command completed / response released: 18:31:19.796 WIB.
- ExperienceActivity resumed: 18:31:22.133 WIB.
- Real identity/profile reads completed at 18:31:22–24 WIB; the app reached F2,
  then day-12 Home after acknowledgement. No competing restore interrupted login.

Native SecureStore read also passed: force-stop/relaunch restored F2 without PIN
entry or another login request; identity/profile reads returned 200. This confirms
native persistence/read behavior in Expo Go, not standalone backup configuration.
Native logout passed: the app returned to F1, the real logout endpoint returned
200, and replaying the same bearer immediately returned 401. A direct count in
the disposable database confirmed **zero remaining token rows**. Daily entries
and alerts remained **0 / 0**. No call, message, symptom submission or clinical
result was produced. The PIN and token were never logged or committed.

New captures of the reviewed application head:

| Capture | Meaning |
| --- | --- |
| [Login](screenshots/final-b4470f1/01-login-synthetic.png) | Synthetic PIN masked; no token displayed. |
| [After auth foreground](screenshots/final-b4470f1/02-after-auth-foreground.png) | Successful pending login reached F2. |
| [Home](screenshots/final-b4470f1/03-home-after-login.png) | Day 12 after acknowledgement. |
| [SecureStore restore](screenshots/final-b4470f1/04-securestore-restored.png) | F2 restored after process restart without PIN. |
| [Restored Home](screenshots/final-b4470f1/05-home-after-restore.png) | Day-12 access after restored-session acknowledgement. |
| [Before logout](screenshots/final-b4470f1/06-before-logout.png) | Native logout control. |
| [After logout](screenshots/final-b4470f1/07-after-logout.png) | F1 returned; server token revoked. |

## Remaining limits

Revocation is a best-effort network request; when unreachable, server token
expiration bounds its remaining lifetime. Physical-device behavior, standalone
release backup configuration, TalkBack spoken order and Android 7 compatibility
are not established by Jest, API tests or an Expo Go run.
