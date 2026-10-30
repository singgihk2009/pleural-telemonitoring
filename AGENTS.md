# Repository instructions

## Architecture and PR-01 scope

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

## Security and privacy

Never commit credentials, tokens, application keys, `.env` files or generated
artifacts. Examples must contain disposable development values only. Preserve
TLS, package signatures and dependency verification. Keep local Compose ports
bound to loopback. Allow CORS only for the documented dashboard origin
`http://localhost:5173`; do not use wildcard origins or enable credentials for
this unauthenticated bootstrap. CORS is a browser policy, not access control.
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
in PR updates. For this review, push to `feature/pr-01-monorepo-bootstrap` for
existing PR #1; do not create another PR, merge, force-push or deploy. Do not
publish or send messages to external services unless explicitly authorized.
