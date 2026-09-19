# Spec gaps

Differences between `Clinic_Management_System_Module_Design_Final.pdf` (the
module design document, v1.0, August 2026) and what is built.

`IMPLEMENTATION_ROADMAP.md` is a log of work completed. This started as the
opposite — what the specification asked for that did not exist. As of
September 2026 every item has been closed; what follows is the record of what
was missing and how each was resolved, plus the caveats worth knowing.

Verified against `backend/schema_mysql.sql` and the module and report
definitions in `js/app.js`, and covered by `backend/tests/cases/`.

## Closed

### Features that did not exist

| Spec item | Module | Resolution |
| --- | --- | --- |
| PDF / image attachments | 1 | `attachments` table, upload/list/download/delete endpoints, and an Attachments panel on the student and medical record views. Stored in the database, not on disk — see the caveat below. |
| Email / SMS notification | 4 | SMTP sender configured by environment variables, fired on booking, confirmation and cancellation. SMS goes through an email-to-SMS gateway rather than a second provider. See DEPLOYMENT.md. |
| Queue number | 2, workflow step 3 | Assigned per day on check-in, from the highest number currently held. |
| Age and gender distribution | 9 | `students.gender` and `students.birthdate` added; age is computed at report time. |

### Fields that were missing

`students` gained `section`, `gender`, `birthdate` and `email`. `visits` gained
`respiration`, `height` and `weight` — three of the six specified vital signs
were absent — plus `status` and `queueno`. `medicine` gained `supplier` and
`batchnumber`. `staff` gained `email`.

Existing databases pick all of these up through the declarative migration map
in `applyMigrations()`; there is no manual step.

### Clearance types

`Fit-to-Study` and `OJT Clearance` are now offered. "Fitness to Return" and
"Health Certificate" already covered return-to-school and medical certificate
under different names.

### Reports

Nine named reports had no implementation: Students with Allergies, Students
with Asthma, Immunization Status, Medical Certificates (staff), Annual Report,
User Activity Log, Role and Permission Matrix, Backup Status and Privacy
Consent Status. Age, Gender and Strand Distribution were added alongside them.

## Caveats worth knowing

**Attachments live in the database.** The application filesystem does not
survive a redeploy on this host while the managed database does, so a scanned
referral written to disk would disappear on the next deploy. The cost is that
attachments inflate the database and every backup. Limits are 5MB per file and
PDF, PNG, JPEG or WebP only.

**Allergies and conditions are stored twice**, on `students` and again on
`medicalrecords`. The reports merge by student ID, because reading either table
alone silently omits whoever was recorded in the other. The duplication is the
underlying problem and is still worth normalising.

**The permission catalog is seeded on demand.** `seedPermissions()` runs only
through `POST /seed/permissions`. Until it does, `permissions` and
`role_permissions` are empty and `hasPermission()` deliberately falls back to
`getDefaultRolePermissions()` — rules are still enforced. The Role and
Permission Matrix report says which of the two it is reporting.

**`getAllowedColumns()` filters writes silently.** A column added to the schema
but not to that whitelist accepts a value, returns 201, and discards it with no
error. Any future column needs an entry in both places; the tests write and
read back every field for this reason.

**Query strings do not reach the API.** Every request arrives as
`?route=<path>`, so anything after a second `?` is never seen as `$_GET`.
Filtered endpoints take their arguments as path segments.

**Backups previously omitted four tables.** `privacy_consents`, `roles`,
`record_folders` and `attachments` were not in `BACKUP_TABLES`, so a restore
silently dropped consent records, custom roles, record folders and clinical
documents. All four are now included.

## Still worth doing

Not spec gaps, but known weaknesses:

- Normalise the duplicated allergy and condition storage.
- Seed the permission catalog on the production database so RBAC is explicit
  rather than relying on the built-in fallback.
