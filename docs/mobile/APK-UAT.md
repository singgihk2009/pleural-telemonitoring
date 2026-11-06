# TeleNapas Android APK UAT preparation

This prepares an internal, signed **release APK**, not an AAB, Expo Go session,
development client, Play Store submission or deployment. **No EAS cloud build has
been started. Obtain explicit approval before the build command below.**

## Configuration

| Setting | UAT value |
| --- | --- |
| Profile | `uat` in `apps/mobile/eas.json` |
| Distribution / Android artifact | `internal` / `apk` |
| Launcher name | `TeleNapas UAT` |
| Android application ID | `id.telenapas.patient.uat` |
| Deep-link scheme | `telenapas-uat` |
| API | `https://telenapas.sintaraniapp.net/api/v1` |
| EAS environment | `preview` |
| Node / pnpm | `24.19.0` / `10.28.2` |
| Expo SDK / native generation | SDK 58 / Expo CNG |
| Version / version source | `1.0.0` / local; choose and record Android versionCode before the first build |
| Signing | EAS-managed remote credentials, separately approved UAT keystore |

`app.config.ts` layers UAT identity over the existing `app.json`, retaining its
icons, Router, orientation and SecureStore backup plugin. For UAT only it blocks
unused overlay, shared-storage and vibration permissions inherited from the Expo
template; network and SecureStore capabilities are retained. With no `APP_VARIANT`,
the original application ID/name/scheme remain unchanged. UAT and the original
app can coexist with separate Android sandboxes and SecureStore records.
An unknown variant, or UAT with a missing/different API URL, fails configuration.
No patient/session, navigation, clinical or API code is changed.

The profile supplies `APP_VARIANT=uat` and the existing
`EXPO_PUBLIC_API_BASE_URL`. Both are non-secret build inputs. The API URL is
compiled into the JS bundle: changing it requires rebuilding this APK. Do not
put credentials, PINs, bearer tokens, database variables or private keys in Expo
public variables, app config, `extra`, assets or source files. Users enter their
researcher-issued synthetic UAT credentials at runtime. Authentication and
SecureStore remain the PR-05 implementation; there is no demo-auth bypass.

## Prerequisites before an approved build

1. An authorized Expo account/organization, confirmed project owner and actual
   EAS project ID. None is invented or linked by this PR.
2. Link the approved existing project using `npx eas-cli@latest init` from
   `apps/mobile` after the owner confirms the project. Do not silently create a
   different organization's project. If the CLI cannot edit dynamic config, add
   the **verified** UUID to `expo.extra.eas.projectId` in `app.json`; the dynamic
   config preserves it. Add the confirmed `expo.owner` if the project requires it.
   Commit/review this public metadata before building.
3. Confirm the approved `preview` EAS environment contains no conflicting
   variant/API settings or application secrets. Do not upload a server `.env` or
   set secrets as `EXPO_PUBLIC_*`. This profile needs only the two public values
   above, already in `eas.json`.
4. An authorized EAS Android signing keystore for **the UAT application ID**.
   Select/create it through the approved EAS credential workflow; never commit
   `credentials.json`, keystores, passwords or private keys. Record its public
   certificate fingerprint for update verification. Reuse this UAT signing key
   for subsequent UAT updates; do not reuse a production key by assumption.
5. Confirm the development HTTPS API's DNS, trusted TLS chain and reachability
   from a physical device. `/api/v1/health` returning 200 proves infrastructure
   only. An authorized researcher must supply synthetic active intervention
   credentials in an isolated UAT dataset. This task does not reset/reseed any DB.
6. Confirm EAS SDK-58 build-image availability and device OS compatibility. The
   old PDF's Android 7 proposal is not a promise from Expo 58/RN 0.88. Do not lower
   native requirements to make an unsupported device install.
7. Approve the source commit, versionCode, signing choice, EAS account/build
   quota and APK access audience, then explicitly approve starting the cloud build.

## Local preparation (no cloud build)

Use Node 24 and Corepack at the repository root. Keep the root lockfile and
`pnpm-workspace.yaml`; run EAS commands from the **mobile app directory**.
The profile pins pnpm to match the root `packageManager`; isolated linking stays.

```sh
corepack pnpm install --frozen-lockfile
corepack pnpm lint
corepack pnpm typecheck
corepack pnpm --filter @pleural/mobile test
cd apps/mobile
export APP_VARIANT=uat
export EXPO_PUBLIC_API_BASE_URL=https://telenapas.sintaraniapp.net/api/v1
npx expo config --type public
npx expo install --check
```

Confirm name, package, scheme and SecureStore plugin before continuing. Exported
configuration and the URL are public. Do not include real tokens in diagnostics.
The dynamic-config variables must also be present for EAS project-linking and
inspection commands. EAS supplies them automatically when evaluating `--profile uat`.

After account/project setup, inspect the actual EAS upload locally:

```sh
npx eas-cli@latest --version
npx eas-cli@latest project:info
npx eas-cli@latest build:inspect --platform android --profile uat --stage archive --output /tmp/telenapas-uat-eas-archive
```

The archive command does not start a build. Use a new output directory each time.
Review its contents before authorizing upload. The Git-root `.easignore` replaces
all nested Git ignores, so it includes the existing secret/generated-file rules
plus exclusions for API/dashboard, docs, infra, test fixtures and signing files.
It retains the workspace manifests/lockfile, mobile source, assets and Expo/EAS
configuration. Keep it synchronized with future ignore rules. No backend `.env`,
local credentials, `node_modules`, generated native tree or prior APK belongs in
the archive. Actual authenticated EAS archive inspection remains a prerequisite;
the local rule/copy check is not a claim of a cloud upload audit.

## Cloud build — only after explicit approval

From `apps/mobile`, with the confirmed project and signing credentials:

```sh
npx eas-cli@latest build --platform android --profile uat
```

Record the CLI version, source SHA, EAS build ID, public signing fingerprint,
artifact SHA-256, package/versionCode and build log. Do not run `eas submit`,
`eas update`, deployment commands or another profile. This profile embeds its
bundle and does not need Metro on testers' computers. It does not configure OTA
updates. Internal-distribution links must be shared only with the approved UAT
team; do not post artifacts/links publicly or send them automatically.

Download the completed **.apk** from the authorized EAS build detail page.
Before distribution, inspect with Android SDK tools:

```sh
sha256sum TeleNapas-UAT.apk
apksigner verify --verbose --print-certs TeleNapas-UAT.apk
aapt dump badging TeleNapas-UAT.apk
```

Check the package is `id.telenapas.patient.uat`, the expected versionCode/minSdk,
and the APK has the approved signature. Inspect the generated manifest and
backup rules to confirm SecureStore entries are excluded from backup; this
requires the real APK, not Expo Go. Verify the release bundle targets the exact
HTTPS API, contains no login fixtures/private material, and ships no debug
client. Artifact size, permissions, OS compatibility and secret scans cannot be
certified before the APK exists. Keep signing credentials out of log attachments.

## Install on a physical Android device

Use a dedicated test handset with synthetic data. Transfer the approved APK
through the agreed internal channel. Verify its hash and signature first.
For a USB-connected authorized handset:

```sh
adb devices
adb install -r TeleNapas-UAT.apk
```

Alternatively allow installation from the approved file source for this install,
then remove that permission. Launch **TeleNapas UAT**, confirm its separate icon
and app ID, and confirm it runs without Metro. A signature mismatch must be
investigated; do not uninstall an existing research app or erase its data to work
around it. The separate UAT ID should not replace `id.telenapas.patient`.

## Physical-device UAT checklist

Record PASS/FAIL/NOT VERIFIED, handset/model/OS, APK hash/version/signature,
source SHA, network and synthetic scenario. Do not attach patient data or tokens.

- [ ] Package/name/signature and API target match; original app remains separate.
- [ ] Cold start works without Metro; UI is Indonesian with nurse/119 actions.
- [ ] F1: invalid PIN, invalid credentials, throttling, loading/offline and staff
      identity rejection behave safely; no PIN appears in logs or storage.
- [ ] Background during patient login, foreground during identity/persistence,
      then confirm successful login. Repeat on cellular/Wi-Fi with a controlled
      test-service delay; do not alter clinical records to introduce latency.
- [ ] F2 explicit warning/checkbox gates Home; acknowledgement is session-only.
- [ ] F3 VAS starts unselected; explicit zero works; Back preserves the value.
      Symptom choices/Send stay disabled and no clinical result is computed.
- [ ] F4/F6 placeholders stay inactive; F5 messaging/nurse number unavailable
      states remain honest; F7 four topics; F8 no event submission; F9 no invented
      history; F10 no invented Health-ITUES items, score or submission.
- [ ] Day 0/day 1–30/day 31 navigation matches authorized synthetic scenarios.
- [ ] Force-stop/relaunch restores SecureStore session and revalidates identity;
      logout clears local access, revokes the server token and stays logged out.
      Server revocation/expiry returns safely to login; patient isolation holds.
- [ ] Poor connection and airplane mode do not cause false submission/success;
      session expiry and permission errors disclose no sensitive response body.
- [ ] Minimum 16sp text, 48dp targets, large fonts/display scaling, TalkBack order,
      keyboard/back/rotation and scrolling retain accessible safety controls.
- [ ] Emergency confirmation/cancel is safe. Do **not** place a test call to 119.
- [ ] Inspect actual APK SecureStore backup exclusions, permissions, release
      flags, minSdk, size and absence of embedded secrets with approved tooling.

## UAT limitations and verification status

This APK is a patient UI/authentication UAT build. It is not a clinical monitoring
release: no client classification/threshold changes, active alerts, messaging,
notifications or unapproved symptom submission. Existing backend-dependent
placeholders and session-only acknowledgement remain. No real patient data.
The API URL is development-only; no production infrastructure is changed.

See [preparation validation](APK-UAT-validation.md). A successful JS export is not
an APK build, signature check or physical-device result. Prior PR-05 Expo Go native
evidence does not validate this new application ID's standalone build/backup
configuration. Cloud build and all artifact/device checks remain NOT VERIFIED
until separately approved and executed.

References checked: Expo SDK 58 config, APK build, app-variant, monorepo and
`.easignore` docs; EAS JSON schema 27.5.0. The docs site was blocked in this
workspace; the matching official Expo GitHub sources and installed SDK types/map
were inspected instead. EAS CLI's latest available version at preparation was
27.5.0; record the actual CLI version again before the approved build.
