# PR13 review corrections — 8 November 2026 WIB

Target: existing feature/stage-b-encrypted-offline-sync /PR13. Runtime core/DRAFT fix4c927fa; manual-only wording26da1ff; verified-read auth-recovery follow-up39c4f2e.
Base inspected: remote PR13 head36673b1, main d5d39d2 (merged PR12), PR14 headd793ea2.
PR14 was unmerged during verification. No production data/infrastructure was used.

## Quarantine guarantee

Capture REJECTED/RECONCILIATION_REQUIRED before any manual reconciliation GET.
Automatic workers return immediately for either state, including after process
restart/re-login. Failed identity/status/history reads (network,429,5xx,401/403)
preserve the original quarantine and immutable UUID/date/version/body. Transient
reads keep bounded backoff/Retry-After and a read-failure message, not a new-delivery
claim. Authentication failures retain quarantine with AUTH_REQUIRED and invoke the
existing scope-guarded logout.409/422 reads likewise cannot unquarantine an item.
Manual reconciliation is read-only: no matching receipt leaves quarantine; exact
independently checked server history may become SYNCED; mismatch requires review.
No failed read can enable a later automatic POST. After all manual reads succeed
without a matching receipt, the state remains quarantined and its operational
reason becomes RECONCILIATION_NO_RECEIPT. This clears a stale AUTH_REQUIRED signal
without logging a newly validated session out again; two re-login UI regressions
and all36 error/GET-stage cases verify it. No PIN/token enters the queue.

## Obsolete DRAFT recovery

Display the original server WIB draft date and an explicit unsubmitted-draft action.
Confirmation explains permanent loss of local unsent answers. Queue.discardDraft
checks both DRAFT and the exact expected UUID under the namespace mutex; stale
confirmation cannot remove a queued, uncertain, rejected, quarantined, synced or
replacement item. Secure deletion/readback must succeed and identity scope remain
current before the UI resets. Failed deletion does not fetch/initialize a replacement.
After confirmed deletion fetch current server status (date/instrument/eligibility),
create a fresh server-identified draft when eligible, with null VAS/author/answers.
The old body is never re-dated, overwritten or repurposed. Stronger existing
reconciliation/history warnings and disposal remain for potentially submitted items.
No plaintext browser/file fallback, automatic late submission or clinical result.

## Integration and explicit conflict decisions

The remote PR13 head already contains latest main/PR12. It is retained as ancestor;
normal push only, no force-push. Both B1 publication and B2 queue security notes are
preserved. No historical migration, authentication/session implementation, controller
or demo-seeder behavior was replaced. Existing selected-schema demo enumeration stays.

A temporary `git merge-tree --write-tree` composition (no branch/main merge) with
PR14 retained researcher date/type filters, Gate+monitoringVisible scope, historical
entry-linked labels, exact own-UUID patient recovery and patient-only credential
revision. Controller auto-combination was inspected method by method. Tests and
appended dictionary/security text conflicted: retain all PR14 test methods plus
PR13 exact-UUID test, and both documentation sections. All composed tests pass.

Important: PR14's current head deletes PublicationQuestion and
InstrumentPublicationInput while retaining the publication endpoint reference.
An automatic textual merge reports OpenAPI clean but no-unresolved-refs fails.
The verified composition explicitly restores these two PR12/13 components, retaining
PR14 filters/labels and PR13 UUID/revision metadata. PR13 adds meaningful publication
schema descriptions so this deletion must be reviewed explicitly during integration.
**Retain both schema definitions when merging/rebasing PR14; do not accept the raw
composition as validated.** PR14 was not changed/pushed by this task. No entire
controller was copied over a newer implementation.

## Automated results

| Check | Actual result |
|---|---|
| PR13 full Laravel/MySQL8.4/PHP8.4 | PASS239 tests /2062 assertions; four opt-ins separately |
| Mobile Jest | PASS157 tests (63 additional cases), including 36 state/error/GET-stage combinations, restart/foreground, logout, exact vs mismatched receipt, draft date rollover/disposal/races |
| Dashboard PR13 /composed PR14 | PASS49 /53 tests |
| All migration lifecycle/historical upgrades | PASS13/13/19/9 assertions, each fresh isolated schema |
| Real-process credential/assessment concurrency | PASS; CAS, resets/revocations, immutable replay/daily uniqueness and mutation audits |
| Lint/TypeScript/OpenAPI/Composer/Pint | PASS local commands; raw PR14 composition OpenAPI FAIL until explicit schema restoration above |
| Dashboard build/Android JS export | PASS, not native/device evidence |
| Composed PR12/13/14 API | PASS242 tests /2115 assertions |
| Composed explicit API workflow | PASS publication→login/revision→ingestion→exact UUID recovery/replay→scoped filter/labels→expiry→logout; transactional synthetic cleanup |
| Hosted Actions/review threads | NOT VERIFIED; GitHub API Forbidden |
| Signed physical APK/TalkBack matrix | NOT VERIFIED; no release/EAS build authorized |

## Reproduce

Use pinned pnpm10.28.2,Node24: pnpm test,lint,typecheck,validate:openapi,build and
CI=1 pnpm build:mobile. apps/api composer validate --strict,composer lint and
APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=pleural_pr13_fix_test composer test.
Create a new isolated schema and apply pending migrations first. Run the four
TELENAPAS_MIGRATION_TESTS=1 suites in their own new _migration_test schemas, and
both tests/Concurrency scripts in fresh matching _concurrency_test schemas per CI.
Never reset/reseed deployed or existing research data; no trigger/FK was disabled.
Integration source remains temporary and does not change main or PR14. Repeat the
composition after any branch changes, restoring both publication schema components.

## Manual recovery UAT

1. Synthetic-only local API, isolated schema, controlled server WIB clock23:55.
   Login/acknowledge; create a DRAFT with explicit VAS, do not queue/send.
2. Force-stop; move only the test server clock to00:05 next WIB date; restart,
   validate identity. The old draft/date must remain unchanged and no POST occur.
3. Cancel draft disposal once, then explicitly confirm. Verify fresh server status,
   new UUID/date and unselected VAS. No old body may be POSTed.
4. In isolated proxy reject a reviewed POST with422. Trigger manual GET503, then
   foreground/restart. Require quarantine visible and zero additional POST requests.
   Repeat automated matrix for409 quarantine,429,network,5xx and401/403/logout.
5. Only after checking no server entry, use the existing stronger explicit disposal
   warning. Queue a new synthetic draft with delivery blocked, reconnect, drop201
   after commit. Require history reconciliation and exactly one server entry/audit.
6. Logout and verify server token deletion. Capture screenshots without credentials.
   Signed device/backup/TalkBack and real instrument approval remain separate.

No new clinical classification, colors, alerts, notifications, messaging or schedule.
No merge/deployment/database reset/infrastructure change/EAS build. Human review required.

## Focused security review

Reviewed the changed core/provider/form together: no new token/PIN persistence or
payload logging; captured identity scope and per-namespace serialization preserved;
DRAFT deletion checks state+UUID atomically; storage failure/replacement/accountswitch
cannot authorize disposal; sticky quarantine prevents implicit POST after GET failure;
exact receipt verification alone releases a reconciled item. Server policies, grant
scopes, immutable records, approved eligibility and revocation remain unchanged.
This local review is not a claim of a fresh remote Codex/security approval.

## Actual native UAT results

PASS: actual process restart across the isolated server WIB midnight retained the
old draft/date/UUID with zero POST; cancel kept it; confirmed disposal fetched a new
server date/UUID and unselected VAS. PASS: proxy422 quarantine followed by manual503
remained REJECTED across foreground and process restart, with zero additional POST.
PASS: after verified empty history and the stronger explicit warning, a fresh draft
queued with delivery blocked, then reconnected with201 dropped after commit. Own
exact-UUID history supplied the receipt with one forwarded POST, one immutable
entry/answer/submission audit, fixed redacted audit metadata and zero classification/
alert/notification effects. Final source reloaded before receipt restore and logout.
See [versioned screenshot evidence/method/limits](screenshots/pr13-fix/README.md).
The full native failure matrix is not claimed;157 Jest tests cover both states and
all requested failure classes, including successful re-login after an old auth failure.

Final-source receipt restore/logout PASS: no new POST; native login screen shown;
independent MySQL token count1→0 and immutable observation retained.
