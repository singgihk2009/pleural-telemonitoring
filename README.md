# Pleural Telemonitoring

TeleNapas is a research development monorepo with a Laravel API, researcher
dashboard and Android patient interface. PR-01 established the original scaffold.

## Repository

- `apps/mobile`: React Native + Expo Router + TypeScript (Android first).
- `apps/api`: Laravel 12 REST API, PHP 8.4, Composer 2.
- `apps/dashboard`: React + Vite + TypeScript.
- `compose.yaml`: local MySQL 8.4, API, and dashboard.

PR-02 adds a [research database foundation](docs/database/README.md), models and
synthetic test factories. PR-03 adds [authentication and RBAC](docs/security/authentication-rbac.md)
with Sanctum bearer tokens, separate hashed patient PINs and participant-scoped
policies. PR-04 adds [research enrollment and baseline management](docs/enrollment/README.md)
with a researcher dashboard, validated forms, encrypted contacts and safe audit.
PR-05 adds the [Android patient interface](docs/mobile/PR-05.md), secure patient
login and explicitly unavailable placeholders for unapproved backend workflows.
No clinical engine, active FCM/WhatsApp integration or production
deployment is included. Migrations are explicit; no
application tables or seed records are created by Compose startup.

## Prerequisites

Node.js 24, pnpm 10.28.2 (see `packageManager`), and Docker with Compose v2.
For a host-run API, install PHP 8.4 with mbstring, PDO MySQL/SQLite, DOM/XML and zip,
and Composer 2. Native Android builds additionally require Android Studio/SDK;
iOS native builds require macOS/Xcode. A compatible SDK 58 Expo Go runtime can run the patient development UI;
emulator/device checks are separate from JavaScript export and standalone APK verification.

## Quick start

```sh
corepack enable
pnpm install --frozen-lockfile
pnpm dev:up
```

Compose installs locked dependencies, initializes the API's ignored `.env` and
application key on first start, and waits for readiness. Check locally:

```sh
curl --fail http://localhost:8000/up
curl --fail http://localhost:8000/api/v1/health
curl --fail http://localhost:5173
pnpm dev:mobile
```

`GET /up` checks Laravel liveness. `GET /api/v1/health` runs `SELECT 1` against MySQL
and returns `{"status":"ok","database":"connected"}`; database query failures
return HTTP 503 with `{"status":"unavailable","database":"disconnected"}`.
Mobile runs on the host so the Expo QR code/device connection works normally.
The dashboard uses staff authentication and enrollment APIs; mobile uses patient
authentication and its authorized profile endpoint. Configure the non-secret
`EXPO_PUBLIC_API_BASE_URL` as described in the mobile guide. Research schemas and
synthetic test credentials must be provisioned explicitly; startup does not seed them.

All published Compose ports bind to loopback. Database credentials in Compose and
`.env.example` are disposable local defaults. Never use them in production or
put real data into this environment. API debug output is enabled for local work.

```sh
pnpm dev:down                    # retain local database and dependency volumes
# Destructive, disposable local data only:
# docker compose down --volumes
```

First start needs Internet access to Docker Hub, Debian package mirrors,
Packagist/GitHub, and npm. Under an HTTPS inspection proxy, retain TLS verification
and mount its trusted CA into dependency-installing containers; see
[development notes](docs/development.md).

## Host development

With MySQL running (`docker compose up -d mysql`):

```sh
cd apps/api
composer install
cp .env.example .env             # only if .env does not already exist
php artisan key:generate         # only for a new local .env
php artisan serve
```

In another shell from the repository root, run `pnpm dev:dashboard` or
`pnpm dev:mobile`. Containers use MySQL hostname `mysql`; the host `.env.example`
uses `127.0.0.1`. No migrations are required by this bootstrap.

## Checks

```sh
pnpm install --frozen-lockfile
pnpm validate:openapi             # OpenAPI 3.1 schema and contract lint
pnpm lint
pnpm typecheck
pnpm test
pnpm build                      # dashboard production bundle
CI=1 pnpm build:mobile          # Android JavaScript/assets export, not an APK

docker compose exec api composer validate --strict
docker compose exec api composer lint
docker compose exec api composer test
```

The API tests exercise liveness and a real database query. Host test runs default
to in-memory SQLite in `phpunit.xml`; Compose and CI provide MySQL through process
environment variables. GitHub Actions runs the JS checks, API tests with MySQL,
and a Compose startup/HTTP smoke check. It has read-only repository permissions
and no deploy step.

## API contract, routing and browser access

The health contract is documented in [OpenAPI 3.1](docs/api/openapi.yaml) and
validated by `pnpm validate:openapi` locally and in CI. `/up` remains a separate
unversioned Laravel liveness probe. API routes use the `/api/v1` prefix.

The mobile entry point is `expo-router/entry`, with a minimal Stack layout and
index route in `apps/mobile/src/app`. The index shows the development placeholder;
there are no clinical routes. Non-route components and tests live outside that
route directory. Existing mobile-specific guidance remains in
`apps/mobile/AGENTS.md`; repository-wide rules are in `AGENTS.md`.

Development CORS permits exactly `http://localhost:5173` for GET/OPTIONS requests
to `/api/v1/*`, with Accept/Content-Type headers and credentials disabled.
Use that dashboard URL in the browser: `127.0.0.1`, another port, and other
origins are deliberately not allowed. Vite uses port 5173 with strict port
selection so it cannot silently move to an unapproved origin. CORS does not
protect the API from non-browser clients and is not authentication. This policy
is for the local bootstrap; production configuration is outside PR-01.

PR-05 patient UI scope, reference comparison and verification boundaries:
[Android patient interface](docs/mobile/PR-05.md).
