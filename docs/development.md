# Development environment notes

Use the existing checkout: each cloud task is already isolated. No Git worktree
is necessary. Use the pinned Node and pnpm versions and preserve both lockfiles.

The initial repository contained a README only, with no AGENTS.md or stored
PR-01 plan. Bootstrap scope follows the explicit PR-01 request. Future features
need separate changes; do not add clinical data, authentication or messaging here.

## Managed cloud proxies

Keep inherited HTTP(S) proxies and TLS verification enabled. Containers need a
read-only mount of the environment's combined trusted CA bundle. Set
`NODE_EXTRA_CA_CERTS` for Node and `COMPOSER_CAFILE`/`GIT_SSL_CAINFO` for Composer
and Git to the mounted path, using a local Compose override outside the checkout.
Do not commit proxy addresses, certificates or credentials. BuildKit network
steps need the platform's trusted CA too if their destinations use HTTPS.

If the GitHub archive API is denied but GitHub source access is allowed, Composer
supports `--prefer-source` (requires Git and its CA trust). This changes transport
only; it must retain the committed package versions. A Dockerfile build still
needs access to the configured Debian mirrors. Never disable TLS verification.

`docker compose up --wait` is the readiness gate. Inspect `docker compose logs`
when it fails, and verify both `/api/health` and the dashboard with HTTP requests.
Live processes do not survive an environment snapshot; restart Compose and Expo
in each task. Do not publish localhost preview links from cloud onboarding.

The container uses `artisan serve --no-reload` so Laravel preserves Compose
environment overrides (including the MySQL service hostname) in its HTTP worker.
After editing API environment values, restart the API container. PHP source edits
still take effect on subsequent requests.
