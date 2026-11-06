# Repository instructions

## Architecture and current scope

Use the existing checkout; cloud tasks are already isolated. Do not create a Git
worktree unless requested. This pnpm monorepo contains `apps/mobile` (Expo Router,
React Native, TypeScript), `apps/dashboard` (React, Vite, TypeScript), and
`apps/api` (Laravel REST API, Composer). MySQL is the development database.
Keep platform code in its app; keep API contracts in `docs/api/openapi.yaml`.
Version API routes under `/api/v1`. Preserve mobile-specific instructions in
`apps/mobile/AGENTS.md`; they supplement this file.

PR-01 covers scaffolding, placeholder screens, health endpoints, local tooling,
API documentation and CI only. Do not add clinical modules, patient records,
authentication, FCM, WhatsApp or production infrastructure under this scope.

PR-02 explicitly authorizes additive MySQL research-schema migrations, models,
synthetic factories, integrity tests and database documentation. See
`docs/database/README.md` and `docs/research/requirements-v1.0.md`. Do not add a
clinical engine, diagnosis, active notifications or real patient data. Run
database migration tests only against a dedicated disposable test database;
never run `migrate:fresh`, rollback or seed on an existing research database.

PR-03 authorizes Sanctum bearer authentication, separate researcher-issued
patient PIN credentials, six-role server policies, scoped participant reads,
explicit identified IT access grants and safe audit events. See
`docs/security/authentication-rbac.md`. Blinding defaults on. Never introduce a
role-combination bypass, control-patient credentials, clinical evaluator or real
notifications. New migrations must be additive; test authentication on MySQL,
not a SQLite run with skipped security tests. ADMIN_IT needs explicit CONTACT
for contacts and CLINICAL_READ for summaries, monitoring and outcomes. TECHNICAL
and legacy unscoped grants permit no patient reads; scopes never imply others.

PR-04 authorizes researcher-only enrollment/baseline/status management and the
React dashboard on top of merged PR-03. See `docs/enrollment/README.md`. Preserve
study group/code and historical research data; freeze baseline/day 0 after history.
Audit enrollment/updates without identifiers or clinical values. Use existing
encrypted contacts and exact optimistic versions. Do not edit historical migrations.

## Security and privacy

Never commit credentials, tokens, application keys, `.env` files or generated
artifacts. Examples must contain disposable development values only. Preserve
TLS, package signatures and dependency verification. Keep local Compose ports
bound to loopback. Allow CORS only for the documented dashboard origin
`http://localhost:5173`; do not use wildcard origins or enable cookie
credentials. Bearer auth permits GET/POST/PATCH/DELETE/OPTIONS and Authorization.
CORS is a browser policy, not access control.
Do not expose exception details, connection strings or host information in
health responses. Never log patient information or send it to external tools.

## Synthetic data and clinical safety

PR-01 must create no patient tables, fixtures or seed records. If a later,
explicitly approved task needs patient-shaped test data, use clearly labelled,
wholly fabricated synthetic data with no real identifiers or copied records.
Never use production exports, screenshots or de-identified real patient data as
fixtures. Do not treat synthetic examples as evidence of clinical validity.
Do not implement diagnosis, triage, treatment advice, clinical thresholds or
alerting decisions without a separately reviewed clinical specification and
appropriate human clinical review. Health endpoints indicate infrastructure
availability only; they must not imply patient safety or clinical readiness.

## Testing and API contracts

Use Node 24, the pinned pnpm version, PHP 8.4 and Composer 2. Preserve lockfiles;
install with `pnpm install --frozen-lockfile` and `composer install`.
Run `pnpm lint`, `pnpm typecheck`, `pnpm test`, `pnpm validate:openapi`,
`pnpm build`, and `CI=1 pnpm build:mobile` for affected bootstrap changes.
Run API `composer validate --strict`, `composer lint`, and `composer test`.
When Docker is available, validate Compose, wait for service readiness and make
real HTTP requests to `/up`, `/api/v1/health` and the dashboard. Exercise CORS for
both approved and disallowed origins. Keep route implementations, tests,
OpenAPI, Compose, CI and documentation consistent. An Android JS export does
not verify a native APK or a device. Report each check as PASS, FAIL or NOT
VERIFIED, distinguishing local checks from hosted GitHub Actions.

## Pull requests

Keep changes focused on the approved scope and add regression tests for changed
behavior. Preserve user changes. Summarize changes, checks and remaining blockers
in PR updates. For PR-04, push to `feature/pr-04-research-enrollment` and create
a pull request targeting `main`; do not merge, force-push or deploy. Do not publish
or send messages to external services unless explicitly authorized.

PR-05 authorizes the Android patient Expo Router UI and existing patient-auth API
integration with SecureStore. Follow `docs/mobile/PR-05.md` and the supplied visual
PDF for screens, while preserving explicit task restrictions: no client clinical
classification, final symptom catalog, Health-ITUES invention, unavailable API
submission, notifications, real patient data or deployment. Mark placeholders and
separate native emulator results from JavaScript export evidence.
