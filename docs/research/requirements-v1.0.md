# TeleNapas research requirements v1.0 — PR-02

Recorded: 3 November 2026 (Asia/Jakarta). Status: database-foundation scope and
VAS bands approved by researchers as relayed in the PR-02 request. This file is
a versioned research requirement, not executable configuration or an algorithm.

| Requirement | Inclusive VAS interval | Database label |
| --- | --- | --- |
| TN-VAS-1.0-GREEN | 0–39 mm | HIJAU |
| TN-VAS-1.0-YELLOW | 40–69 mm | KUNING |
| TN-VAS-1.0-RED | 70–100 mm | MERAH |

The integer endpoints are inclusive. No delta rule, symptom precedence,
baseline definition beyond storage, combined classification, diagnosis,
treatment decision or notification trigger is approved by this table. PR-02
stores VAS values and manually supplied classification records; it never
calculates or assigns a classification, including at these boundaries.

The supplied `TeleNapas_SRS_v1_0_dan_Data_Dictionary_Draft_Review_Peneliti.docx`
identifies itself as `1.0-draft`, dated 3 November 2026, with unsigned approval
fields. The user's explicit PR-02 instruction authorizes database implementation
from its dictionary and supersedes its pending status for this scope. The direct
VAS approval above supersedes only the absolute VAS bands in its clinical-rule
examples. The remaining draft clinical proposals have **not** been promoted to
approved requirements. No signatures, ethics approval or final instruments are
inferred from the attachment.

Still pending: D-01 baseline/delta/symptom rules; D-02 hours, response SLA and
escalation; D-03 recurrence A/B/C, readmission/drop-out definitions and sources;
D-04 final Health-ITUES instrument, scales and scoring; D-05 late/offline and
clock conflict policy; D-06 WhatsApp consent/provider/templates; D-07 blinded
access, retention and export governance. The research window (day 0 discharge;
reporting on WIB days 1–30), group separation and 0–100 mm storage bounds come
from the dictionary. No new clinical thresholds or response times are supplied.

Versioned `pengaturan`, `symptom_options`, `follow_up_options` and
`questionnaire_versions` hold future reviewed definitions. Migrations seed none
of them. Approved settings carry approver/time metadata; activation is nullable
and no code consumes it. Definitions are immutable: approval or revision creates
a new version rather than mutating a historical one. Final questionnaire item
counts, accepted answer values and scoring require a separate reviewed task.

All classifications, alerts and scores in factories are explicitly fabricated
test fixtures, never computed clinical outputs. Boundary tests prove values can
be stored without creating a classification or alert. They do not validate a
clinical decision system.

Source attachment SHA-256: `811cc0d5a97def3888354b58b5e173036074acb6b80953310139d10f388bf522`.

