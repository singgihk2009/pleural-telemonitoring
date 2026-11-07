# PR-11 synthetic Android UAT

Use only an independently approved disposable environment, synthetic participants
and expiring tokens. Do not reset/reseed the deployed database or paste credentials,
PINs, bearer tokens or assessment payloads into reports/screenshots. No automatic
Ubuntu/Cloudflare change, release APK build, deployment or merge is authorized.

## Before testing

1. Apply the additive migration to a newly created disposable MySQL 8.4 schema
   using its schema administrator (triggers require appropriate privileges).
   Test the API under the restricted application DB user after migration.
2. Preserve the existing `EXPO_PUBLIC_API_BASE_URL` workflow. Development loopback
   can use adb reverse/Expo Go; UAT uses its approved HTTPS endpoint. Never embed
   credentials in public Expo variables. No new app dependencies are required.
3. With no daily instrument, verify “Assessment belum tersedia” and no submit
   action. This is the expected default. Do not copy PDF examples into live settings.
4. Enabled synthetic test cases use `Tests\Support\SyntheticDailyInstrument` only
   in disposable tests. These are fabricated technical definitions, not research
   approval. Real UAT enablement awaits the separately reviewed instrument and
   schedule; there is no approval/publication UI in PR-11.

## API verification (local secure token entry, not command-line secrets)

From a trusted local shell, use existing synthetic patient credentials to obtain
a short-lived token through the documented login endpoint, storing it in process
memory or a permission-0600 temporary curl header file. Do not use shell tracing.
The following uses a private header file containing `Authorization: Bearer ...`:

```sh
curl --fail-with-body --header @"$UAT_HEADER_FILE" "$UAT_API_BASE/patient/assessments/status"
curl --fail-with-body --header @"$UAT_HEADER_FILE" "$UAT_API_BASE/patient/assessments?page=1"
# body.json must contain only fabricated data; mode 0600, remove after verification.
curl --fail-with-body --header @"$UAT_HEADER_FILE" -H 'Content-Type: application/json' \
  --data-binary @body.json "$UAT_API_BASE/patient/assessments"
```

`UAT_API_BASE` ends `/api/v1`; no secret belongs in it. Build body.json from the
status UUID/version/date, an explicitly chosen VAS, approved option IDs, PASIEN
or KELUARGA and an ISO UTC device timestamp with milliseconds. Keep the exact file
for a lost-response retry: first201, exact replay200, changed-body/secondUUID409.
Never print/store real patient payloads. Revoke test sessions using existing logout
and securely remove temporary headers/bodies; deletion is not a cryptographic
browser/storage erasure guarantee.

## Device checklist

- Login with synthetic intervention credentials; acknowledge initial statement.
  Controls cannot log in; revoked/expired sessions return to login.
- Open F3: load server status, select no-default VAS including0/100, load approved
  labels, complete required items/author, review then confirm. Missing answers
  cannot proceed. Verify keyboard-free choices, screen reader labels, text scaling,
  16sp body text and >=48dp actionable targets with long approved labels.
- Double-tap submit, background/foreground, interrupt network after commit, retry:
  one saved report, no automatic duplicate or clinical feedback. Same retry UUID
  and body return the same receipt. Help/dialer actions remain available.
- Close during unknown response, reopen F3 and F9, verify committed status/history.
  Logout/session change clears in-memory drafts; late responses do not restore them.
  No offline persistent queue is promised. Across WIB midnight, stale new attempts
  conflict; exact already-committed retries still recover while authorized.
- F9: own history/timestamps and pagination, no other participant or computed color.
  Confirm history remains accessible after day30 while the participant/session is active while daily creation is closed.
- Dashboard: authorized researcher reads the same immutable observations. No editing
  controls. Blinded assessor combinations, unassigned nurses and IT with only
  TECHNICAL/CONTACT cannot read the new endpoint. Explicit CLINICAL_READ remains
  subject to approval/revocation/expiry/blinding restrictions.
- No alerts, classifications, WhatsApp, FCM, final questionnaire or repeats activate.

Record device/OS/host, commit and evidence. Distinguish Jest, Android JS export,
Expo Go/emulator, signed release APK and physical-device evidence. See
[validation-pr11.md](validation-pr11.md); screenshots contain synthetic data only.
