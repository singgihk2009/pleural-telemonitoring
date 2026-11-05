# TeleNapas development demo seeder

Based on `main` at `07d8aee`. This opt-in fixture supports researcher dashboard,
enrollment, staff authentication and scoped monitoring checks. It never runs from
`DatabaseSeeder`, migrations, API requests or Compose startup. No deployment or
`infra/` change is required.

## Dataset and clinical boundaries

| Data | Created on first initialization |
| --- | --- |
| Staff | Five visibly SYNTHETIC DEMO accounts, one approved role each |
| Participants | SYN-DEMO-I-001/I-002 (INTERVENSI, AKTIF), SYN-DEMO-C-001 (KONTROL, AKTIF), SYN-DEMO-C-002 (KONTROL, DROP_OUT with a synthetic reason) |
| Contacts | Four encrypted model records; non-dialable synthetic contact strings |
| Baseline | Explicit fabricated 0–100mm observations and synthetic paper-consent metadata |
| Nurse assignment | Monitoring nurse -> I-001; backup nurse -> I-002 |
| Monitoring | Three HARIAN observations per intervention participant; generated study days 1–3; patient/family authorship; WIB dates and UTC timestamps |
| Audit | 18 immutable, fixed-code demo/provisioning/enrollment/observation records; no participant identifiers, contact values or clinical values in payloads |

The initial discharge anchor is seven calendar days before execution in
Asia/Jakarta. Reruns do **not** move this anchor or recreate observations.

Review included all four migrations, models, factories, policies, access scopes,
and the [dictionary](../database/data-dictionary-v1.0.md) with its auth/grant/enrollment
supplements. Factories create the records, with explicit demo-only overrides;
contact encrypted casts and Laravel password hashing remain in use.

The schema stores classifications/alerts but contains no validated clinical
evaluator. Therefore neither is created. Follow-ups require an alert and a
versioned decision catalog; they are omitted rather than inventing these.
Outcomes/assessor assessments, symptom answers, questionnaires/scores and
notifications remain empty because the approved definitions/workflow needed to
populate them are not available. A baseline/raw VAS observation is not a clinical
result. No threshold, delta rule, diagnosis, treatment, clinical approval or
notification is computed or invented.

No patient PIN credentials or identified-access grants are seeded. Controls
remain credential-free. ADMIN_IT has no participant-data access; a future
separately authorized grant must still explicitly select TECHNICAL, CONTACT or
CLINICAL_READ. No grant scope or researcher approval is inferred. Existing blinded
assessor and unconditional assessor-contact restrictions remain enforced.

## Safety and idempotence

All of these must be true:

- `APP_ENV=local` or `testing` (production and staging are rejected, even with `--force`).
- `TELENAPAS_DEMO_ENABLED=true` (defaults false).
- MySQL **8.4**, with an actual connected database name ending `_demo` or `_test`.
- First initialization: every application table is empty, except generic role
  definitions, migration history and authentication rate-limit infrastructure.
- First initialization: an explicitly provided, private regular password file.

The existing `pleural_dev` baseline deliberately does **not** satisfy the name
guard. Create a separate empty demo schema; never rename or reset a research
schema to make it pass. Pre-existing accounts/participants are never adopted,
merged, overwritten or elevated, even if their email/code matches a demo key.

A MySQL named lock serializes initializers. The complete fixture and audit marker
`demo.seeded.v1` are inserted in one transaction. Any failure rolls everything
back and releases the lock. No FK, CHECK or trigger is disabled; no migration is
changed. The immutable marker makes later invocations a genuine **no-op**. They
preserve account status/roles/passwords/tokens, edited contacts/consent, added
enrollments and immutable histories/audits. A missing secret file on rerun is OK.
No repair, date refresh, password rotation, cleanup or deletion is attempted.

## Local environment and credentials

Use PHP 8.4, Composer 2, a valid **existing local APP_KEY**, and the repository's
MySQL connection configuration. Keep the same encryption key for the CLI and
API. Do not regenerate an existing key or overwrite an existing `.env`.

Have your local schema administrator create a **new** empty database, for example
`telenapas_demo`, and grant the local development application user access to it.
Use a password prompt or your existing secure client configuration, not password
literals in shell arguments. Apply the existing migrations only to this new schema. For the standard local
Compose development user, enter your schema administrator's MySQL client using a
password prompt and run:

```sql
CREATE DATABASE telenapas_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL ON telenapas_demo.* TO 'pleural'@'%';
```

Adapt the grant to your **local** application account. Do not grant privileges on
any research/production schema.
The following assumes host PHP, the existing local DB credentials in `.env`, and
MySQL bound to loopback. Run from repository root:

```sh
cd apps/api
export APP_ENV=local DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306
export DB_DATABASE=telenapas_demo
export TELENAPAS_DEMO_ENABLED=true

# Generate a local secret without displaying it or placing it in command history.
umask 077
demo_secret_file="$(mktemp "${TMPDIR:-/tmp}/telenapas-demo.XXXXXX")"
php -r 'file_put_contents($argv[1], bin2hex(random_bytes(24)).PHP_EOL, LOCK_EX);' "$demo_secret_file"
chmod 600 "$demo_secret_file"
export TELENAPAS_DEMO_PASSWORD_FILE="$demo_secret_file"

php artisan migrate --force
php artisan db:seed --class=TeleNapasDemoSeeder
```

`TELENAPAS_DEMO_PASSWORD_FILE` is a path, **not** a password value. Its regular file
must have no group/world permissions (`chmod 600`), no symlink, one UTF-8 password
of at least 12 characters and at most 72 bytes, and optionally a trailing newline.
The generated secret above has 48 random hexadecimal characters. All five
strictly local demo accounts share that operator-controlled password:

| Email (public fixture identifier) | Sole role |
| --- | --- |
| researcher@telenapas-demo.invalid | PENELITI_UTAMA |
| nurse@telenapas-demo.invalid | PERAWAT_PEMANTAU |
| backup@telenapas-demo.invalid | PERAWAT_CADANGAN |
| assessor@telenapas-demo.invalid | PENILAI_HASIL |
| it@telenapas-demo.invalid | ADMIN_IT |

The seeder never prints or stores plaintext passwords; only adaptive hashes enter
MySQL. Keep the private file in your own secure local storage/password manager
for login. Do not send its contents to chat, commit it, enable shell tracing or
put passwords/tokens into URLs. There is no default password and hashes cannot
be used to retrieve it. Changing the file or rerunning does **not** reset existing
credentials; use separately authorized account maintenance if it is lost.

For container CLI execution, use the same environment overrides with
`docker compose exec -e DB_DATABASE=... -e TELENAPAS_DEMO_ENABLED=true ... api php artisan ...`.
Copy your private file into the container with `docker compose cp`, retain mode
600, and point `TELENAPAS_DEMO_PASSWORD_FILE` at its **container** path. Do not edit
Compose's default `pleural_dev` database or the deployment server's untracked
`infra/` directory. No production server access is needed.

## Serve the local demo and verify APIs

In a second terminal at repository root, retain the same non-secret DB environment settings above and
local encryption key. Use a loopback-only server, leaving any existing API alone:

```sh
cd apps/api/public
php -S 127.0.0.1:8015 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

For the dashboard, start it with
`VITE_API_BASE_URL=http://127.0.0.1:8015/api/v1 corepack pnpm dev:dashboard` from the
repository root, and open exactly `http://localhost:5173`. Keep the existing exact
CORS origin; do not add wildcards. Log in using the researcher email and the
password from your own private file/password manager.

Example API verification command, in the shell where `demo_secret_file` is set.
It reads credentials privately and keeps the bearer token in process memory;
neither is printed or passed as a process argument:

```sh
python3 - "$demo_secret_file" <<'PY'
import json, pathlib, sys, urllib.request
base = 'http://127.0.0.1:8015/api/v1'
def call(method, path, body=None, token=None):
    headers = {'Accept': 'application/json', 'Content-Type': 'application/json'}
    if token:
        headers['Authorization'] = 'Bearer ' + token
    request = urllib.request.Request(base + path, method=method, headers=headers,
        data=None if body is None else json.dumps(body).encode())
    with urllib.request.urlopen(request, timeout=15) as response:
        return json.load(response)
password = pathlib.Path(sys.argv[1]).read_text().rstrip('\r\n')
session = call('POST', '/auth/staff/login', {
    'email': 'researcher@telenapas-demo.invalid', 'password': password})
token = session['access_token']
try:
    assert call('GET', '/auth/me', token=token)['data']['kind'] == 'staff'
    rows = call('POST', '/research/participants/search', {'search': 'SYN-DEMO'}, token)
    ids = {row['kode']: row['id'] for row in rows['data']}
    entries = call('GET', '/participants/' + ids['SYN-DEMO-I-001'] + '/entries', token=token)
    assert len(entries['data']) == 3
    print('PASS: researcher login, authorized listing and raw monitoring access')
    print('Matching demo enrollments:', rows['meta']['total'])
finally:
    call('POST', '/auth/logout', {}, token)
PY
```

Use the enrollment dashboard to add another **synthetic** participant; do not
add real patients. Nurse/backup logins see only their assigned intervention record.
IT listing returns no participants; contacts and monitoring are denied. Blinded
assessors cannot read monitoring. An assessor cannot read contacts even when
blinding is disabled. No password or token should be pasted into source/tests.

## Safe reruns and tests

```sh
# Same dedicated DB and APP_ENV; re-enable the opt-in if previously unset.
export TELENAPAS_DEMO_ENABLED=true
php artisan db:seed --class=TeleNapasDemoSeeder
# Expected: already initialized; no records/passwords/dates/audits changed.
```

Unset `TELENAPAS_DEMO_ENABLED` after initialization if desired; this disables
seeding, **not** existing demo account login. Retain the private file if you need
to log in again, or remove it using your local secret-management procedure. Do
not run destructive reset, truncate, trigger-disabling or audit-deletion commands.
For a fresh fixture use a different, newly created empty `_demo` schema.

Run tests only on a newly created disposable `_test` schema with the four
migrations already applied. Do **not** use the seeded demo database as the test
suite database, because first-initialization guards intentionally reject it.

```sh
APP_ENV=testing DB_DATABASE=telenapas_demo_test php artisan test --filter=TeleNapasDemoSeederTest
APP_ENV=testing DB_DATABASE=telenapas_demo_test composer test
composer validate --strict
composer lint
```

See [validation results](demo-seeder-validation.md) for executed checks and limits.
