# B3 — Read-only dashboard monitoring

Base: merged PR11 main be6702e; branch feature/stage-b-dashboard-monitoring.
Independent of B1/B2. No migration, package dependency, patient write endpoint,
clinical logic or mobile change. Reuses existing participant selection/search/group/
status filtering and assessment panel; does not rebuild enrollment or authentication.

## Contract and privacy

Staff history validates optional inclusive `from` / `to` WIB dates and stored `type`
HARIAN/ULANG, with to>=from when both provided. Existing Gate authorization executes
before filter validation. Existing monitoringVisible query scope ANDs participant
identity and all date/type conditions. Blinded combinations, current nurse assignment,
independent ADMIN_IT CLINICAL_READ and other research restrictions are unchanged.
Contacts, outcomes, classifications, PINs and local queue state are not serialized.
Existing privileged-access audit is retained with no filter/clinical value in logs.

Uses the existing `(responden_id,tanggal_wib)` index and 25-row stable date/UUID
pagination; eager loads immutable linked setting/answers. No new exclusive locks or
indexes. Staff `meta.filters` echoes normalized applied values (null when absent).
Dashboard verifies exact echo whenever filters are active; an older/mismatched API
cannot silently show broad unfiltered observations as a filtered result.

Optional question_label/answer_label come only from each entry's immutable
assessmentSetting JSON and exact question code/option ID. Missing/legacy linkage
returns null and UI falls back to stored code/value. Today's instrument, expiry or
publication never rewrites historical wording. Patient history/receipt projection
is unchanged by this branch. Values are rendered as React text, never HTML.

## User interface

Within the selected authorized participant: apply/clear inclusive WIB date/type
filters, refresh, count/paginate, read device and server times distinctly, author,
raw VAS and version. Results say Tersimpan (server) only for actual stored rows.
Empty results do not imply missed reports or adherence. No completion denominator
or compliance status is invented without an approved schedule. ULANG is a historical
read filter; it does not enable repeat submission. No clinical colors/scores/alerts.
Filter inputs are local React state, not browser storage. Enter applies filtering
without submitting the containing enrollment form. Existing enrollment save controls
remain separate. Abort/key guards hide late responses on close/participant/tokenchange.
Native date controls use browser locale; applied date labels remain explicit ISO WIB.

## Manual synthetic UAT

1. Use an isolated `_test` MySQL schema and fabricated intervention participant.
   Prefer the single server record from B2's native recovery check; no production data.
2. Login as authorized non-assessor researcher, search/select that participant in
   the existing list, open the read-only assessment panel. Confirm raw VAS, author,
   historical approved labels/version and actual server receipt time.
3. Apply matching from/to dates (inclusive) and HARIAN. Confirm one record and correct
   normalized filter echo. Enter on a date input must not PATCH enrollment data.
4. Select ULANG where no repeat record exists: empty stored result, no adherenceclaim.
   Clear filters and refresh. Test >25 synthetic rows for bounded pagination.
5. Make server return an absent/mismatched filter echo: hide all observations and
   show the compatibility error, never claim a filtered result. Restore API.
6. Test malformed/reversed dates, wrong type, unauthorized nurse, assessor combinations,
   patient bearer, ADMIN_IT technical/contact-only, expired/revoked clinical grant.
  403/401 must reveal no prior clinical results; follow existing session-expiry flow.
7. Switch participant/close/logout during a slow read: late response stays hidden.
   Confirm no browser persistence and no assessment editing/write action.
8. Verify immutable triggers, audits and zero classification/alert/notification effects.
   Run the complete MySQL/security/migration and workspace checks in validation-b3.md.

## Rollback and limitations

No database rollback. Roll back dashboard/API together. A newer dashboard fails closed
for active filters on an older API; an old dashboard remains compatible with new
optional fields. Historical data/labels remain immutable. Do not delete records to
fix a display issue. No approved real instrument, schedule, deployment, infrastructure,
EAS build or signed APK is included. B2 native evidence is branch-specific; no new
Android code is claimed verified on B3. Human review/integrated UAT precede release.
