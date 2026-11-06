# Android APK UAT preparation — validation

Date: 6 November 2026 (WIB). Base: merged main `1dbd2f9` (PR #5).
Branch: `feature/android-apk-uat`.

| Check | Result |
| --- | --- |
| Root package manager / local runtime | PASS — pnpm 10.28.2, Node 24.19.0; EAS profile matches |
| Frozen workspace install | PASS |
| Filtered monorepo copy install | PASS — offline frozen lockfile install, scripts disabled for the local archive check; mobile and root manifests retained |
| Installed SDK compatibility | PASS — all 11 applicable installed dependencies match SDK 58's bundled ranges; no dependency or lockfile changes |
| `expo install --check` | PASS against local SDK data; CLI warns offline validation is limited; remote Expo validation NOT VERIFIED |
| EAS profile schema | PASS — `@expo/eas-json` 27.5.0 read and Android profile resolution; internal, APK, release (not development client) |
| Public app config | PASS — UAT ID/name/scheme and SecureStore plugin preserved; original identity unchanged without variant |
| Fail-closed config | PASS — unknown variant, missing URL, HTTP URL and different HTTPS URL rejected |
| Native config introspection | PASS — UAT manifest package, INTERNET retained, unused overlay/storage/vibration permissions marked for removal; SecureStore backup/data-extraction rules declared |
| Archive exclusions | PASS — local Gitignore-pattern assertions and filtered-copy check exclude backend/dashboard/infra/docs/test fixtures, local env, signing files and generated artifacts |
| Bundle marker checks | PASS — UAT export contains exact API URL; known synthetic token/credential/private-key markers absent; actual APK scan remains required |
| Workspace lint/typecheck | PASS |
| Mobile tests | PASS — 40, including all 16 PR-05 race regressions |
| Dashboard tests | PASS — 13 |
| OpenAPI/dashboard build | PASS |
| Android JavaScript export with UAT variables | PASS — not an APK/native test |
| Development API TLS/health reachability | NOT VERIFIED — workspace request returned Forbidden; verify DNS/TLS/health from the authorized device network |
| Authenticated EAS project/actual archive inspection | NOT VERIFIED — authorized owner/project link not configured |
| EAS cloud build / APK signing/install / physical device | NOT VERIFIED — not started; explicit build approval required |
| Hosted GitHub checks | NOT VERIFIED — GitHub API returns Forbidden |

No EAS cloud build, submission, update or deployment was started. No database was
reset or seeded. Patient/auth/session/UI source, backend, research thresholds and
clinical placeholders are unchanged. UAT-specific native permission reductions
are configuration only; final manifest merging still requires the APK audit.

Local archive-pattern simulation does not replace `eas build:inspect --stage
archive` after Expo account/project setup. Native introspection does not replace
inspection of the signed APK. The bundle marker check is limited to known test
and credential markers, not a certification that a future binary is secret-free.
Review the actual build inputs and artifact before distribution.

Remaining prerequisites and physical-device checklist are in
[APK-UAT.md](APK-UAT.md). No prior Expo Go evidence is relabelled as standalone APK
verification. The four original research migrations and existing environment data
were not changed for this preparation.
