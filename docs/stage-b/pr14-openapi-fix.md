# PR14 OpenAPI repair and merged PR13 integration — 8 November 2026 WIB

Original PR14 headd793ea219c5386578a2a7b3abc28fc07f1603529 omitted
PublicationQuestion and InstrumentPublicationInput while retaining the publication
endpoint's request reference. No Redocly rule, ignore file or CI check was disabled.

## Change and merge decisions

Latest main323f13b already squash-merged PR13 on top of merged PR12 d5d39d2.
Merged that main into the existing PR14 branch, retaining both complete publication
schemas. They exactly equal current main; removing only PR13's two new top-level
descriptions gives exact structural equality with merged PR12. Fields, required
lists, enums, patterns, option arrays, activation/expiry and confirmation contracts
are intact. One-off JSON-pointer traversal resolves all172 local $refs; Redocly
validates the full document. No external $ref is present.

Preserved PR14's inclusive researcher from/to/type parameters, normalized filter
echo, historical entry-linked question/answer labels, Gate/RBAC/query scopes and
pagination. Preserved PR13's own exact-UUID history query and patient-only credential
revision metadata. Controller merged method by method; no complete older controller
was copied. Combined tests retain all three PR14 monitoring tests and PR13's UUID
privacy test. Appended dictionary/security conflicts retain both sections.
The demo seeder's selected-schema guard is unchanged from main. No migration or
publication controller/route/validation/response behavior changed.

Root package.json adds `validate` as an alias to existing `validate:openapi`, so the
requested `pnpm validate` is executable. CI still runs the original Redocly command.
No dependencies were added; mobile code and lockfile are byte-identical to main.
The inherited mobile/lockfile diff from old PR14 is the already merged PR13 work.

## Results on integrated PR14

| Check | Actual result |
|---|---|
| pnpm validate /validate:openapi | PASS;172 local references resolve |
| pnpm lint /typecheck /test | PASS; mobile157/dashboard53 |
| pnpm build | PASS |
| CI=1 pnpm build | PASS |
| CI=1 pnpm build:mobile | PASS Android JavaScript export; not native APK |
| Laravel/MySQL8.4/PHP8.4 full suite | PASS242 tests /2115 assertions; four opt-ins separately |
| Composer strict /Pint | PASS |
| Migration lifecycle/auth/grant/assessment upgrades | PASS13/13/19/9 assertions, four separate new schemas |
| Real-process credential/assessment concurrency | PASS; CAS/revocation and immutable UUID/daily conflicts |
| Existing explicit API integration workflow | PASS publication→login/revision→ingestion→UUID recovery/replay→staff filters/labels→expiry→logout |
| Fresh real-HTTP queue workflow | PASS before-delivery outage, sealed pending body, reconstructed queue worker, dropped committed201, exact-UUID history acknowledgement; one forwarded POST |
| Real HTTP authorization/logout | PASS patient denied staff monitoring; POST logout-all revokes UAT sessions, old tokens401 |
| Fresh Chromium dashboard UAT | PASS actual API login, recovered record, historical labels, inclusive filters, empty ULANG, incompatible-echo denial, Enter without PATCH, no browser storage, awaited logout |
| MySQL UAT invariants | PASS one publication/entry/answer/submission audit; redacted metadata; zero classifications/alerts/notifications/remaining tokens |
| Compose/HTTP smoke | PASS config; existing health/dashboard200 |
| Hosted GitHub Actions | NOT VERIFIED at local validation; inspect new-head run, do not infer green from local checks |
| Fresh native emulator/signed physical APK | NOT RERUN; mobile source unchanged from merged PR13; existing versioned native evidence remains explicitly historical |

## UAT method and limits

All schemas were newly created local disposable `_test` databases. No deployed
schema/data reset/reseed, FK/trigger disable, infrastructure change or clinical
classification/notification activation. The existing opt-in integration script
uses transactional fixture cleanup. HTTP/browser fixtures are wholly fabricated.

Fresh HTTP UAT transpiles the unchanged mobile Queue and API transport for Node,
uses a test-only AES-GCM sealed IO adapter, and calls actual Laravel/MySQL through
a loopback proxy. It reconstructs a queue worker over the same sealed item; this is
not an Android process/Keystore/physical-device verification. The proxy drops201
only after actual commit, then exact own-UUID history supplies a verified receipt.
A researcher sees that same immutable record in the actual dashboard. No PIN/token
or clinical payload is logged/committed. Synthetic keys/passwords remain private
and temporary. The initial standalone cleanup mistakenly used GET logout (405);
the driver was corrected to the existing POST contract and logout-all/old-token
rejection were rerun successfully. Application logout code was not changed.

See [fresh browser screenshots](screenshots/pr14-openapi/README.md). Prior PR13
native midnight/quarantine/lost-response evidence is inherited under
screenshots/pr13-fix and must not be relabelled as a new native run for this repair.

## Reproduction and review

Use pinned pnpm10.28.2/Node24. Run the exact pnpm commands above. In apps/api run
composer validate --strict,composer lint and APP_ENV=testing DB_CONNECTION=mysql
DB_DATABASE=pleural_pr14_fix_test composer test after applying pending migrations
to a NEW disposable schema. Run four TELENAPAS_MIGRATION_TESTS=1 suites and both
Concurrency scripts using their dedicated fresh suffix-guarded schemas per CI.
For full publication/UUID/filter integration, use a new empty
`_stage_b_integration_test` schema and TELENAPAS_STAGE_B_UAT=1 BCRYPT_ROUNDS=4
php tests/Integration/stage_b_workflow.php. Never reset an existing schema to rerun.

PR14 now includes current main/PR13, not a temporary composition. No new API/database
rollback is needed for the documentation fix. Keep both publication definitions and
both history methods on future updates; full OpenAPI lint catches missing refs.
Human review and green hosted checks remain required; no merge/deployment/EAS build.
