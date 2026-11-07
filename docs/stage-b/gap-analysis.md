# Stage B audit and implementation plan — 8 November 2026 WIB

PR-11 is squash-merged as main be6702e. Its tree exactly matches PR11 head
9e48a4a (GitHub commit authored/committed as #11). GitHub API metadata is
unavailable (Forbidden); this is Git-object evidence, not an API merge-state claim.

Reviewed existing migrations/models/controllers/scopes, assessment/auth tests,
DailyInstrument, Android form/session/API/SecureStore, dashboard history, OpenAPI,
research requirements and visual PDF (F3/F9 and page13 offline proposals). Reuse
all existing ingestion, exact replay, eligibility, immutable data and permissions.

Gaps: no publication API/approval trail/expiry; drafts only in RAM; no durable
single-worker sync or restart reconciliation; dashboard lacks date filters.
PDF examples do not approve symptoms/schedules. Clinical content remains absent.

Plan: three independent branches based on merged PR11, each reviewable alone.
1. Researcher-only explicit atomic publication API using immutable settings/options,
   approval reference, server actor/time, explicit effectivity/expiry and serialized
   publication; no automatic publication or default instrument.
2. Existing platform SecureStore evaluated for bounded encrypted drafts, crypto
   namespace bound to verified identity/credential revision, one outstanding item,
   durable state/UUID/body, foreground-only single worker, exact receipt validation,
   reconciliation/backoff and explicit disposal. No tokens/PINs in queue, no plaintext
   fallback. Cold offline restart stays locked until session validation; remote
   revocation cannot erase an offline device instantaneously. Retain uncertain items.
3. Extend existing read-only dashboard with server date filters, participant selection
   through existing authorized enrollment, bounded pagination and observed receipt
   status only. No inferred missed/completion status from an unapproved schedule.

Validate each independently on fresh disposable MySQL and synthetic native fixtures.
No production data/server/Cloudflare changes, reset, deployment, EAS build or merge.
Signed APK/device/TalkBack evidence stays distinct from emulator/Jest/export evidence.
