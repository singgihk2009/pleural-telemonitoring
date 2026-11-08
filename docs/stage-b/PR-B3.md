# B3: TeleNapas dashboard monitoring filters and UAT

Researchers can inspect a selected authorized participant's immutable observations
with inclusive WIB date/type filters, server-confirmed counts/times and labels from
each record's linked historical instrument. An older API that ignores filters is
rejected visibly. Existing enrollment/participant selection, RBAC/query scopes,
blinding, IT grants and auditing are preserved. No mobile write workflow or clinical
classification, completion inference, alerts or messaging is added.

Changes: existing history controller/panel, regressions, OpenAPI/dictionary/security,
manual UAT, actual Chromium screenshots and cross-branch verification report.
No migration/dependency change. The guarded demo schema enumeration correction is
identical across B1/B2/B3 and does not relax nonempty-data safety.

Validation: MySQL231/2036 assertions; dashboard53/mobile55; four migration opt-ins
and both real-process races; composed API242/2115 assertions and explicit workflow;
lint/typecheck/OpenAPI/Composer/Pint/build/export and real Chromium UAT. See [actual results](validation-b3.md), [screenshots](screenshots/b3/README.md)
and [architecture/UAT/rollback](dashboard-monitoring.md). Local tests are distinct
from unavailable hosted Actions or signed-device UAT. No merge/deployment/EAS build.
Human review required; branches are independently based on merged PR11 main.

PR14 correction: latest main323f13b includes merged PR13. Retained complete
PublicationQuestion/InstrumentPublicationInput schemas, PR14 filters/historical labels
and PR13 exact-UUID recovery/revision metadata. Added root pnpm validate alias.
Integrated validation: API242/2115 assertions; mobile157/dashboard53; all migration
and concurrency suites; lint/types/OpenAPI/build/export; fresh real-HTTP queue recovery
and real-browser dashboard UAT. See [current repair verification](pr14-openapi-fix.md).
Hosted Actions must be checked on the new head; no automatic merge/deployment.
