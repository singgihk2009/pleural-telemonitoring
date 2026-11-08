# Fresh PR14 browser integration evidence — 8 November 2026 WIB

Actual Chromium dashboard against current integrated Laravel/MySQL, local synthetic
fixture only. The observation was committed through the unchanged mobile queue
engine/API transport running in a Node test harness, then recovered by exact UUID
after the real HTTP201 response was dropped. These are browser captures, not Android.

- [01](01-http-reconciled-record.png): immutable recovered record with labels from
  the explicitly published synthetic instrument, version and server receipt.
- [02](02-date-filter.png): inclusive same-day HARIAN filter, one stored record.
- [03](03-empty-filter.png): no ULANG data, no inferred adherence/completion.
- [04](04-incompatible-api.png): missing filter echo hides results safely.

Also verified actual staff login/logout, Enter does not PATCH enrollment, no clinical
editing inputs and no browser persistence. Outer enrollment save controls are not
assessment mutation controls. No real identities, passwords, PINs or bearer tokens
are shown. Current mobile code equals main/merged PR13; no fresh native/signed-device
run is claimed. See ../../pr14-openapi-fix.md for complete checks and method limits.
