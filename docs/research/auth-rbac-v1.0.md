# TeleNapas authorization requirements v1.0

Recorded 3 November 2026 (Asia/Jakarta). Source: explicit PR-03 Authentication &
RBAC request, SRS v1.0-draft actor table/FR-01/FR-02 and PR-02 Data Dictionary.
This scope authorizes authentication/authorization, not a clinical engine or
production study operation. The [VAS research requirements](requirements-v1.0.md)
remain unchanged; pending clinical definitions, instrument scoring, messaging,
retention and export governance are not inferred or activated.

- AUTH-01: staff passwords and patient PINs have separate credential models;
  patient PINs are researcher-issued six-digit strings and adaptively hashed.
- AUTH-02: only active intervention participants receive app credentials;
  controls remain researcher/assessor-recorded participants without app accounts.
- AUTH-03: expiring, revocable Sanctum bearer sessions; no public registration,
  cookie/session fallback, plaintext credential storage or token logging.
- AUTH-04: durable account and IP login limits, generic errors, safe audit events.
- AUTH-05: the six approved roles use server policies and participant scopes;
  nurses require active matching monitoring/backup assignments.
- AUTH-06: blinding defaults on. Assessors cannot read alerts, classifications,
  entries or follow-up notes; summary responses omit group, code and discharge
  date. Outcome projections remain available for both groups without identity.
- AUTH-07: IT administration is not clinical authority. Identified read access
  requires a different principal researcher's explicit participant-specific,
  expiring grant with explicit CONTACT and/or CLINICAL_READ scopes, and cannot
  override assessor restrictions. TECHNICAL authorizes no patient reads. Neither
  CONTACT nor CLINICAL_READ implies the other; legacy unscoped grants deny all
  patient resources and clinical list counts. No self-approval
  or role-combination bypass is allowed.
- AUTH-08: every protected endpoint authenticates first; explicit response
  projections and pre-pagination query scopes prevent relationship/count leaks.
- AUTH-09: append-only audit storage and prior database integrity constraints
  remain unchanged; all tests use fabricated fixtures and disposable MySQL.

The [access matrix and operational defaults](../security/authentication-rbac.md)
make implementation decisions reviewable. Role/assignment provisioning is a
trusted operator responsibility; this PR exposes no public role-editing API.
The authenticated research resource endpoints are read-only authorization
surfaces. Clinical data entry, outcome editing, notification delivery and patient
registration workflows require separately scoped implementation.
