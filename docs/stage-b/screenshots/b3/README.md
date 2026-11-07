# B3 actual browser evidence — 8 November 2026 WIB

Chromium1365×1000, real dashboard/API login, local isolated MySQL schema containing
B2's actual Android-generated synthetic report. Screenshot captures the existing
participant editor's read-only panel; its outer enrollment save footer is unrelated
to immutable assessment history. No real identity, PIN or bearer token is pictured.

- [01](01-android-record.png): actual Android-reconciled record, historical question/
  option label, server/device times, raw VAS and version, server-only receipt status.
- [02](02-date-filter.png): inclusive same-day HARIAN filter and one stored result.
- [03](03-empty-filter.png): no ULANG record, no adherence/completion inference.
- [04](04-incompatible-api.png): proxy removes applied-filter echo, observationshidden.

Browser automation also checks Enter applies without PATCHing enrollment, no
non-filter clinical inputs, no browser storage and real UI logout. Native date
input display follows browser locale; applied dates explicitly use ISO/WIB.

## Android / PDF evidence boundaries

No Android source changes in B3. Existing PR11 Android screenshots are in
[assessment/screenshots](../../../assessment/screenshots/) and its comparison/report.
These are baseline evidence, not fresh B3 native execution. Fresh encrypted queue,
real reboot, lost-response recovery and logout screenshots belong to independent
B2 branch949432f at docs/stage-b/screenshots/b2; do not mislabel them B3 results.
All15 PDF pages were inspected during audit; gap-analysis.md records the plan.
F1/F2/Home/F3 and F9 are reused, no default VAS; F4 receipt remains non-clinical,
F5–F8/F10 unavailable workflows stay marked. No risk colors, clinical catalog,
alerts, messaging, questionnaire invention or signed physical-device claim.
