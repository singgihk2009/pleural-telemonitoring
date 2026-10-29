# Pleural Telemonitoring

PR-01 establishes a development monorepo. The mobile app and dashboard contain
placeholder screens; the API exposes infrastructure health checks only.

## Repository

- `apps/mobile`: React Native + Expo + TypeScript (Android first).
- `apps/api`: Laravel 12 REST API, PHP 8.4, Composer 2.
- `apps/dashboard`: React + Vite + TypeScript.
- `compose.yaml`: local MySQL 8.4, API, and dashboard.

No clinical modules, patient data, authentication, FCM, WhatsApp integration, or
production deployment are included. No application tables or seed records are created.

## Prerequisites

Node.js 24, pnpm 10.28.2 (see `packageManager`), and Docker with Compose v2.
For a host-run API, install PHP 8.4 with mbstring, PDO MySQL/SQLite, DOM/XML and zip,
and Composer 2. Native Android builds additionally require Android Studio/SDK;
iOS native builds require macOS/Xcode. Expo Go can run the development placeholder
on a compatible device; an emulator/device check is separate from the JS export.

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
curl --fail http://localhost:8000/api/health
curl --fail http://localhost:5173
pnpm dev:mobile
```

`GET /up` checks Laravel liveness. `GET /api/health` runs `SELECT 1` against MySQL
and returns `{"status":"ok","database":"connected"}`; database failures return
an error response. There are no authenticated or clinical endpoints. Mobile is
run on the host so the Expo QR code/device connection works normally. The apps
are not yet connected to the API.

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
