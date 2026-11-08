# Stage B1: Explicit approved daily instrument publication

Base main (merged PR11 be6702e); branch feature/stage-b-instrument-publication.
Request human review as a draft; do not merge or deploy automatically.

Adds researcher-only review/publication endpoints compatible with DailyInstrument.
Operators must supply a separately approved definition, schedule, external approval
reference, immutable version, effectivity/expiry and explicit confirmation. No
instrument is auto-published, no clinical catalog/default is supplied and no scoring,
classification or notification code is added.

Changes: InstrumentPublicationController, DailyInstrument expiry check, additive
publication-receipt migration, security tests, API routes/OpenAPI and research/
database/security/UAT notes. Demo safety checks enumerate only the selected schema;
its strict nonempty-data denial remains intact.

Transactions serialize on the existing researcher-role row and revalidate the
staff/token before atomically storing immutable settings/options/receipt/audit.
Migration is additive, with restrictive FK/checks and immutable receipt triggers;
rollback only on an empty disposable schema, never a populated study database.
Legacy approved settings retain PR11 compatibility with no invented expiry.

Validation: 237 MySQL tests /2,039 assertions; 9 focused publication cases; four
isolated upgrades/lifecycle checks and both real-process concurrency suites PASS.
Composer/Pint, JS lint/typecheck/tests (mobile55/dashboard49), OpenAPI, dashboard
build and Android JS export PASS locally. Hosted CI and fresh publication-expiry
native UAT NOT VERIFIED. No Android code change; unchanged PR11 Android screenshots
are baseline evidence only: docs/assessment/screenshots/README.md.

Architecture/approval/rollback/UAT: docs/stage-b/instrument-publication.md.
Detailed commands/results: docs/stage-b/validation-b1.md. Missing real signed
clinical definitions and schedule remain activation blockers. No production data,
server/Cloudflare change, database reset, APK/EAS build, deployment or merge.
