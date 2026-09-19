# Spec gaps

Differences between `Clinic_Management_System_Module_Design_Final.pdf` (the
module design document, v1.0, August 2026) and what is actually built.

`IMPLEMENTATION_ROADMAP.md` is a log of work completed. This is the opposite:
what the specification asks for that does not exist yet. Most items here were
never claimed as done, so the two documents do not contradict each other.

Verified against `backend/schema_mysql.sql` and the module and report
definitions in `js/app.js`. This audit covers whether a feature is *present*,
not whether it *behaves correctly* — a working-looking feature with a broken
handler will not show up here.

## Still open

### Not implemented at all

| Spec item | Module | Notes |
| --- | --- | --- |
| PDF / image attachments on medical records | 1 (key feature) | No `$_FILES`, `move_uploaded_file` or base64 handling exists in the backend. The `record_folders` table groups records by folder name; it does not store files. |
| Email / SMS appointment notification | 4 (key feature) | No mail or SMS sending anywhere in the codebase. |
| Queue number | 2 (workflow step 3) | "System generates queue number" is a numbered step in the specified visit workflow. There is no column and no generation. |
| Age and gender distribution analytics | 9 (key feature) | Cannot be built as the schema stands: `students` has neither a birthdate nor a gender column. Course distribution is possible; the other two are not. |

### Missing database fields

| Table | Spec field | Status |
| --- | --- | --- |
| `students` | Section | Lives on `medicalrecords` and `medicalhistory` instead, duplicated per record rather than held once on the student. This is why section has to be passed between modules as a data attribute. |
| `students` | Guardian | Only `emergencycontact` exists. Arguably equivalent, but the spec names Guardian. |
| `visits` | Respiration, Height, Weight | Three of the six specified vital signs are absent. `temperature`, `bloodpressure` and `pulserate` exist. |
| `visits` | Status | Only `disposition` exists, so the specified eight-step check-in to check-out lifecycle is not modelled. |
| `medicine` | Supplier, batch details | Neither exists. Both appear in the spec under key features and in the Medicines table. |

### Missing clearance types

The spec lists six certificate types for Module 8. Two are not offered in
`js/app.js` (`CLEARANCE_FIELDS`):

- Fit-to-Study
- OJT Clearance

"Fitness to Return" covers return-to-school and "Health Certificate" covers
medical certificate, so those two are naming differences only.

## Closed — reports (September 2026)

Nine reports named in the spec had no implementation. All are now derived in
`handleReports()` and listed in the Reporting & Compliance view, and covered by
`backend/tests/cases/08_reports.php`.

| Report | Module | Derived from |
| --- | --- | --- |
| Students with Allergies | 1 | `students` merged with `medicalrecords` by student ID |
| Students with Asthma | 1 | as above, matching `asthma` in medical conditions |
| Immunization Status Report | 1 | as above; students with nothing on file read "No record" |
| Medical Certificates | 6 | `clearance` rows belonging to staff rather than students |
| Annual Report | 9 | year-to-date totals across visits, appointments, incidents, certificates and dispensing |
| User Activity Log | 10 | per-user rollup of the audit trail |
| Role and Permission Matrix | 10 | `permissions` + `role_permissions`, or the built-in defaults |
| Backup Status | 10 | `backup.*` entries in the audit trail |
| Privacy Consent Status | 10 | `privacy_consents` |

Two notes on those.

**Allergies and conditions are stored twice** — on `students` and again on
`medicalrecords`. The reports merge by student ID, because reading either table
alone silently omits whoever was recorded in the other. That duplication is the
underlying problem and is worth normalising.

**The permission catalog is seeded on demand, not automatically.**
`seedPermissions()` is reachable only through `POST /seed/permissions`. Until it
runs, `permissions` and `role_permissions` are empty and `hasPermission()`
deliberately falls back to `getDefaultRolePermissions()` — rules are still
enforced. The matrix report therefore reports the built-in defaults when the
catalog is empty, labelled as such, rather than showing an empty table while
rules are actually in force.

## What matches the spec

Modules 2, 3, 5 and 7 track the specification closely, several with more
reports than required. Module 10 is the most complete: two-factor
authentication, account lockout via `failed_login_count` and `locked_until`,
`ip_address` on audit logs, encryption at rest and consent tracking are all
present. Every table the spec names exists.

## Suggested order for what remains

1. **One combined migration** rather than several: `students.section`,
   `students.gender`, `students.birthdate`, `visits.respiration`,
   `visits.height`, `visits.weight`, `visits.status`, `visits.queueno`,
   `medicine.supplier`, `medicine.batch`. Adding gender and birthdate is what
   unblocks the Module 9 analytics.
2. **The two missing clearance types**, which are a one-line change.
3. **Attachments and notifications**, scoped separately — each needs an
   infrastructure decision (file storage, and an email or SMS provider).
