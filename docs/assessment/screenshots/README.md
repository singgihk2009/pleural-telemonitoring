# PR-11 visual verification

Only actual captured synthetic screenshots belong here. Native device evidence
and browser dashboard evidence must be identified separately from Jest and export.
All captures contain fabricated data only; no PIN, bearer token or real patient information.

| Evidence | Capture |
| --- | --- |
| Android default gate | [01 unavailable](01-unavailable.png) |
| Android VAS with no selection | [02 VAS](02-vas-no-default.png) |
| Android synthetic options/author (scrolled continuation) | [03 questions](03-synthetic-questions.png) |
| Android confirmation | [04 confirmation](04-confirmation.png) |
| Android original receipt recovered after dropped response | [05 receipt](05-server-receipt.png) |
| Android own stored history | [06 history](06-patient-history.png) |
| Actual browser, empty read-only panel | [07 dashboard](07-dashboard-read-only.png) |
| Actual browser, Android-submitted observation | [08 dashboard submitted](08-dashboard-submitted.png) |

Android: API30/Android11 AOSP x86_64, Expo Go58.0.5, 640×1280px/density320. The gear overlay/status bar belong to Expo Go/Android. Browser captures show the real dashboard panel against the same isolated API, rather than an Android render. No release APK or pixel-perfect claim. Long cards deliberately scroll; image03 shows the author continuation, with the selected synthetic answer repeated in confirmation/history.

PDF comparison: F3 preserves VAS (no default) → structured questions → author →
confirmation and send. The fixed help actions remain available. Proposed PDF F4
clinical colors/results are intentionally replaced by a neutral server receipt.
F9 lists immutable observations/timestamps with pagination; no unapproved graph,
compliance calculation or clinical interpretation is presented. No pixel-perfect
claim is made. Approved instrument labels can wrap; existing 16sp scalable body
text and >=48dp buttons are retained. Device-wide scaling/TalkBack and signed APK
UAT still need the dedicated checklist in ../UAT.md.
