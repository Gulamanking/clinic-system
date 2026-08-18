# Implementation Roadmap — Spec Gaps

Tracks gaps between `Clinic_Management_System_Module_Design_Final.pdf` (10-module design doc) and the current codebase. One phase = one module/gap, worked in priority order. Update status inline as work lands.

**Legend:** ✅ Done · 🚧 In Progress · ⬜ Not Started

---

## Phase 1 — User Access & Confidentiality Control (Module 10) ✅ Done

RBAC was hardcoded (4 roles, 4 flags) with no per-module write restriction, and `audit_logs` only fired for AI-assistant calls.

- [x] `permissions` + `role_permissions` tables (DB-driven grants, no admin UI yet)
- [x] Per-resource `<resource>:write` permission check on every non-GET route
- [x] `audit_logs` wired into create/update/delete for all resources
- [x] Login success/failed/blocked events logged
- [x] `/seed/permissions` endpoint seeds catalog + role grants matching the doc's Role and Access Summary
- [x] Fail-open fallback so existing installs aren't locked out pre-seed

**Deferred (not in this phase):** normalized `Roles` table (kept as role-name strings), `PrivacyConsents` (no consent workflow exists anywhere upstream — needs its own design pass), admin UI for editing permissions, per-owner record restriction (students/faculty should only see their own records — currently any authenticated role can read any record).

**Files:** `backend/auth.php`, `backend/index.php`, `backend/database.php`, `backend/schema.sql`, `backend/schema_mysql.sql`

---

## Phase 2 — Medicine Inventory & Dispensing (Module 3) ✅ Done

No `Dispensing` table existed — dispensing was only an untracked free-text field on `visits`.

- [x] `dispensing` table: `id, studentid, patientname, medicine_id, medicine_name, quantity, date_released, released_by, created_at`
- [x] Deduct `medicine.stock` on dispense create, reject with 409 if insufficient stock (`handleDispenseCreate()`)
- [x] Dispensing records are immutable (PUT/PATCH/DELETE return 405) — it's a transaction log, not editable data
- [x] `dispensing:write` permission + audit logging (reused Phase 1 plumbing)
- [x] `seedPermissions()` made idempotent per-row (was all-or-nothing; new phases each add permissions, so re-running the seed now only inserts what's missing instead of no-op'ing if any permission already exists)

**Deferred:** wiring dispensing directly into the visit consultation UI (currently a standalone `/api/dispensing` endpoint — the frontend doesn't yet call it from the visit form's `medicineDispensed` field), and the Most Dispensed Medicine / Medicine Usage reports. Revisit once Phase 3's visit-field persistence bug is fixed, since both touch the same visit-medicine linkage.

**Files:** `backend/index.php` (`handleDispenseCreate`, `resourceMap`, `seedPermissions`), `backend/database.php`, `backend/schema.sql`, `backend/schema_mysql.sql`

---

## Phase 3 — Clinic Visit & Consultation Logging (Module 2) ✅ Done

Two separate bugs found during the audit:

- [x] **Routing regression:** commit `6c40560` had removed the `visits`/`appointments` branches from `render()`/`renderMainContent()` in `js/app.js`, so both views silently fell back to the generic CRUD table even though the dedicated `renderVisitsModule()`/`renderAppointmentsModule()` functions still existed (unreachable dead code). This also silently broke visit CSV import — `#import-visits-file-input` only exists in the dedicated view. Restored both router branches in `render()` (~line 2317) and `renderMainContent()` (~line 3240).
- [x] **Silent data loss:** `VISIT_FIELDS` collects `temperature`, `bloodPressure`, `pulseRate`, `assessment`, `medicineDispensed`, `disposition`, but the DB schema/whitelist didn't have those columns — `array_intersect_key` in `dbCreate`/`dbUpdate` dropped them before the INSERT. Added the 6 columns to `visits` in both schema files + `getAllowedColumns()`.
- [x] Added a schema migration step (`applyMigrations()` in `database.php`) since `CREATE TABLE IF NOT EXISTS` never adds columns to a table that already exists — needed for any DB (dev or prod) that already had the old `visits` shape. Generalized the prior MySQL-only migration helper to also cover SQLite (`PRAGMA table_info`).
- [x] **Pre-existing crash bugs, found by the user clicking through the restored views in a real browser** (both had been unreachable dead code since commit `6c40560`, so neither had ever actually run):
  - `renderAppointmentsModule()` used `ms.items.length` directly instead of the null-safe local `items` variable used everywhere else in the function — crashed with `TypeError: Cannot read properties of undefined (reading 'length')` under certain state timing. Fixed to use `items.length`.
  - **Bigger one:** in both `renderVisitsModule()` and `renderAppointmentsModule()`, the `if (ms.viewTarget) { if (v) { ... } }` block was missing one closing brace — only one `}` closed two nested `if`s, leaving `if (ms.viewTarget)` open for the rest of the function. Since `viewTarget` is falsy by default, that swallowed the `deleteTarget` check, `html += '</div>'`, and `return html;` into a block that never ran — the function fell off the end and returned `undefined` with no error, which is why the UI showed "Unable to load the visits view" instead of crashing. This bug had nothing to do with the routing regression — it was independently broken and simply never exercised until the routing was restored. Root-caused via direct Node execution of the actual file (stubbed browser globals, called the functions directly, bisected with temporary marker returns) rather than static reading, since the symptom (silent `undefined`, no thrown error) couldn't be explained by inspection alone.

**Verified live (all via direct execution, not just code review):**
- Created a visit with all 6 vitals fields via the running dev server against the pre-existing SQLite file (proving the migration upgrades an already-deployed DB, not just a fresh one) — reloaded and all fields came back intact.
- Both `renderVisitsModule()` and `renderAppointmentsModule()` now return real HTML in all three states: default list view, the view-details modal (the actual broken path), and the delete-confirmation dialog.
- Still recommend one manual click-through in the browser for visual/CSS confirmation — logic-level verification doesn't catch layout issues.

**Files:** `js/app.js` (`render`, `renderMainContent`, `renderVisitsModule`, `renderAppointmentsModule`), `backend/database.php` (`getAllowedColumns`, `dbKeyMap`, `applyMigrations`), `backend/schema.sql`, `backend/schema_mysql.sql`

---

## Phase 4 — Incident & Emergency Case Management (Module 5) ✅ Done

No `EmergencyTreatment` table existed — treatment was only a free-text field on `incidents`.

- [x] `emergency_treatment` table: `id, incident_id, treatment, medicine, nurse, date, remarks, created_at` (standard editable CRUD via `resourceMap`, not append-only like dispensing — no side effect to protect, corrections should be allowed)
- [x] Incident detail view (the generic view-modal used by `renderCrudModule`) now shows a treatment timeline + inline quick-add form when viewing an incident specifically (`renderEmergencyTreatmentSection()`, `addEmergencyTreatment()` in `js/app.js`) — lazy-loads all treatment rows once, filters client-side by `incidentId`, same pattern as the existing record-folders lazy-load
- [x] `emergencyTreatment:write` permission + audit logging (reused Phase 1 plumbing)
- [x] **Spec-compliance fix while in this area:** the design doc's Role and Access Summary lists School Nurse as a user of Module 5, but Phase 1's role grants never gave School Nurse `incidents` write access — only Administrator/Physician/Staff Encoder had it. Added `incidents` + `emergencyTreatment` to School Nurse's grants to match the spec.

**Verified live:** created an incident as admin, added a treatment entry, confirmed it lists correctly filtered by incident. Registered a fresh School Nurse test account and confirmed it can now write both `incidents` and `emergencyTreatment` (previously would have 403'd on incidents).

**Files:** `js/app.js` (`renderEmergencyTreatmentSection`, `addEmergencyTreatment`, view-modal hook in `renderCrudModule`, click handler), `backend/index.php` (`resourceMap`, `seedPermissions`), `backend/database.php`, `backend/schema.sql`, `backend/schema_mysql.sql`

---

## Phase 5 — Faculty & Staff Health Services (Module 6) ✅ Done

Only a flat `staff` table existed. No employee-specific medical record, visit, or medicine-dispensing structure.

- [x] `employee_medical_record` table + full view/upsert UI: viewing a staff member's details now shows their medical profile (blood type, allergies, conditions, immunization status, last physical exam) with an inline save form that creates-or-updates (`renderEmployeeHealthSection`, `saveEmployeeMedicalRecord` in `js/app.js`)
- [x] `employee_visit` and `employee_medicine` tables added on the backend (mirroring `visits`/`dispensing`) — `employeeMedicine` reuses Phase 2's stock-deduction logic via a shared `dispenseMedicine()` helper (refactored `handleDispenseCreate` to use it too, instead of duplicating the stock-check/deduct logic per subject type). Same immutability rule as `dispensing` (405 on edit/delete).
- [x] **Spec-compliance fix while in this area:** Module 6 (Faculty and Staff Health Services) lists School Nurse and Physician as users per the design doc, but they had no `staff` write grant (same class of gap as Phase 4's incidents fix). Added `staff` + all three new `employee*` grants to both roles.
- [x] Found and fixed a real bug during testing: `staffName` wasn't persisting on `employeeMedicine`/`employee_visit` creates — the column is named `staffname` (no underscore, matching the rest of the schema's convention) but the generic camelCase→snake_case auto-conversion produces `staff_name`, so the whitelist filter silently dropped it. Added an explicit `dbKeyMap` entry (same root cause as the Phase 3 vitals bug — worth remembering as a recurring failure mode in this codebase whenever a camelCase field doesn't cleanly auto-convert to its actual no-underscore column name).

**Deferred:** dedicated employee visit-log and dispensing UI (backend fully works via `/api/employeeVisit` and `/api/employeeMedicine`, but no view built yet — the existing shared `visits`/`medicine` UI with the `patientType: 'Faculty/Staff'` flag remains the practical day-to-day path until this gets a proper interface). Employee Visits / Medical Certificates / Employee Health Summary reports deferred to Phase 8.

**Verified live:** created a staff member, added a medical record, dispensed employee medicine (stock deducted correctly, immutable), confirmed a Physician account can now write `employeeMedicalRecord` (previously would 403).

---

## Phase 6 — School Health Program Monitoring (Module 7) ✅ Done

Was the weakest existing module — `programs` only had a `targetParticipants` integer, no actual roster, attendance, or outcomes.

- [x] `participants`, `attendance`, `assessment` tables added (both schema files)
- [x] Full working UI: viewing a program now shows its participant roster, attendance history, and assessment results, with inline quick-add forms for all three (`renderProgramMonitoringSection`, `addProgramParticipant`, `addProgramAttendance`, `addProgramAssessment` in `js/app.js`) — attendance/assessment forms use a participant dropdown populated from the program's own roster
- [x] `participants:write` / `attendance:write` / `assessment:write` permissions + audit logging
- [x] **Spec-compliance fix (3rd instance of this pattern):** Module 7 lists School Nurse and Physician as users, but `programs` write was Administrator/Staff-Encoder-only. Added `programs` + all three new grants to both roles.
- [x] Learned from Phase 5's bug: chose column names that either auto-convert cleanly from camelCase (`programId`→`program_id`, `participantId`→`participant_id`, `attendanceDate`→`attendance_date`, `assessedBy`→`assessed_by`) or reuse existing `dbKeyMap` entries (`studentId`→`studentid`, `studentName`→`studentname`) — avoided the recurring column-name-mismatch bug entirely instead of fixing it after the fact.

**Verified live:** created a program, added a participant, recorded attendance and an assessment result, confirmed all three list correctly filtered by program. Confirmed a fresh School Nurse account can now write `programs`.

**Deferred:** Program Attendance / Vaccination Coverage / Health Program Completion / Student Participation reports — Phase 8 as planned.

---

## Phase 7 — Appointment Scheduling System (Module 4) ✅ Done

No `DoctorSchedule` table existed — appointments were a flat list with no availability model, so double-booking wasn't prevented. (The dedicated appointments UI was already restored back in Phase 3.)

- [x] `doctor_schedule` table: `doctor_id, date, available_time, status`
- [x] `handleAppointmentCreate()` now validates on every appointment booking: rejects if the same doctor already has a Pending/Confirmed appointment at that exact date+time (409); if the doctor has declared availability for that date, the requested time must match a non-Booked slot (409 otherwise) — if no schedule exists for that date, this second check is skipped so appointments aren't blocked before any availability has been entered
- [x] `doctorSchedule:write` permission + audit logging
- [x] **Found and fixed a real, more fundamental bug during testing:** the `appointments` table never had a `doctor_id` column at all — not a naming mismatch this time, the column was simply missing from the original schema despite the design doc listing `DoctorID` as a field. Every appointment's `doctorId` was being silently dropped, which is exactly why the double-booking check didn't fire on the first test. Added the column to both schema files + a migration entry (existing DBs, including the dev one, get it via `applyMigrations()`) + the whitelist. Used `doctor_id` (underscore) rather than matching the table's older no-underscore fields, specifically to auto-convert cleanly and avoid colliding with `doctor_schedule`'s own `doctorId`→`doctor_id` mapping (the same collision class as Phase 3/5 — avoided this time by choosing the column name deliberately instead of patching it after).

**Deferred:** UI for managing doctor availability (backend-only via `/api/doctorSchedule` for now, same as `employeeVisit`/`employeeMedicine`); Today's/Missed/Completed/Cancelled appointment reports — Phase 8.

**Verified live:** booked an appointment, confirmed a same-doctor/same-slot booking gets 409, confirmed a different time slot succeeds, declared doctor availability for a specific time, confirmed booking outside that declared time gets 409 and booking within it succeeds.

---

## Phase 8 — Reporting & Compliance (Module 9) ✅ Done

Dashboard + CSV export existed but only covered the original 9 tables — everything added in Phases 2–7 (dispensing, doctor schedule, emergency treatment, employee records, program participants/attendance/assessment) had no reporting/export path, and there was no compliance-specific report at all.

- [x] `handleReports()` now dumps all 20 datasets, not just the original 9 — every table added this session is exportable the same way the original modules were (reuses the exact existing CSV-export mechanism in `renderReports()`, which is fully data-driven off `state.reportsData.records[key]` — no export code changes needed, just registering the new datasets)
- [x] **Audit Trail report** — full `audit_logs` dump, exportable
- [x] **Login Attempts report** — derived from `audit_logs` filtered to `login.*` actions (success/failed/blocked), exportable
- [x] Two new dashboard summary tiles: Audit Trail Events, Failed/Blocked Logins
- [x] Certificate-expiry compliance — already covered by the existing Expired Clearances tile + Health Clearances export from before this session; no new work needed there

**Verified live:** hit `/reports` directly — confirmed all 20 dataset keys present, `auditTrail`/`loginAttempts` populated with real data accumulated across this session's testing (48 audit events, 22 login attempts, matching what was actually done).

**Not done:** filtering the audit trail by user/date/module in the UI (currently a flat CSV export like every other dataset, no in-browser filter controls) — acceptable for now since the CSV itself contains those columns and can be filtered in a spreadsheet, but a real filter UI would be a nice follow-up if this becomes a frequently-used report rather than an occasional compliance pull.

---

## Phase 9 — Polish & Deferred Items 🚧 Partially Done

Items intentionally deferred from earlier phases. Unlike Phases 1–8, these three are qualitatively different in scope — not mechanical gap-fills — so they were triaged individually with the user rather than plowed through:

- [x] **Admin UI for managing roles/permissions** — done. `permissions` and `role_permissions` are now exposed via `/api/permissions` and `/api/rolePermissions`, gated by `manage_users` just like `/api/users` (new `ADMIN_ONLY_RESOURCES` constant in `index.php` generalizes what was previously a `users`-only special case). Frontend: a "Roles & Permissions" button on the Users page (visible only to Clinic Administrator) opens a role×permission matrix (`renderRolesPermissionsModule`) where checkboxes grant/revoke — `toggleRoleGrant()` creates or deletes the specific `role_permissions` row and refetches. Not added to the sidebar nav (reached only via the button, matching how the record-folder view also isn't a top-level nav item).
- [ ] **`PrivacyConsents`** — still deferred. No consent workflow exists anywhere upstream (consent to what, granted by whom, gates what access — genuine open questions, not implementation questions). Needs a real design conversation before writing code, per the user's explicit choice not to do this now.
- [ ] **Per-owner record access restriction** — still deferred. Discovered this is bigger than a permission tweak: **Student and Faculty aren't functional login roles yet** — `getDefaultRolePermissions()` in `auth.php` only defines the 4 clinical roles. Building this properly means designing a self-service account model first (how does a Student-role account link to a specific `students` row — self-registration matching a student ID? admin-linked?) before the restriction itself makes sense. Per the user's choice, left as backlog rather than guessed at.
- [ ] Normalize `role` from a free-text string into a proper `Roles` table — still makes sense to defer until/unless the two items above are tackled (nothing currently needs it; the 4 role names are stable and only referenced as strings).

**Verified live:** confirmed a Staff Encoder account gets 403 on `GET /api/permissions` (admin-only enforced correctly); revoked School Nurse's `visits:write` grant via the API, confirmed it was gone, re-granted it to restore the original seeded state.

---

## Phase 10 — Named/Aggregated Reports (Module 9, all modules) ✅ Done

A follow-up gap check against the design doc after Phase 9 found the biggest remaining item: the doc lists ~30 specifically named reports across every module (Most Common Illnesses, Most Dispensed Medicine, Missed Appointments, Accident Statistics, Vaccination Coverage, etc.), but Phase 8 only added flat CSV exports of raw tables — none of the named reports were actually computed.

- [x] `handleReports()` now derives ~20 named/aggregated datasets from data already fetched in that request (no new queries) — filters, groupings, and counts, e.g.:
  - Visits: Daily/Weekly/Monthly Patients (date-filtered), Most Common Illnesses (diagnosis frequency count)
  - Medicine: Expired / Near-Expiration / Low-Stock (date and threshold filters), Most Dispensed Medicine (aggregated quantity across both `dispensing` and `employeeMedicine`), Medicine Usage (the two logs combined)
  - Appointments: Today's / Missed / Completed / Cancelled (status and date filters)
  - Incidents: Emergency Cases (High/Critical severity), Referral Report (text search on `actionTaken`), Accident Statistics (severity breakdown counts)
  - Programs: Vaccination Coverage (participants whose program category matches "vaccin*"), Health Program Completion (status filter)
  - Clearance: Issued vs Pending Certificates (status filter)
- [x] Frontend `renderReports()` reorganized into module-grouped sections (was one flat table) with report names matching the design doc's terminology instead of generic dataset names — reuses the exact same CSV-export mechanism as every other dataset, no new export code
- [x] Reports that are just the existing raw table under the spec's name (Student Visit History = `visits`, Incident Summary = `incidents`, Certificate History = `clearance`, Program Attendance = `attendance`, Student Participation = `participants`) were relabeled rather than duplicated

**Verified live:** seeded visits with duplicate diagnoses, an expired medicine, a cancelled appointment, and a high-severity incident with a referral note — confirmed every aggregation counted correctly (`mostCommonIllnesses` grouped 2+1, `expiredMedicines`/`cancelledAppointments`/`emergencyCases`/`incidentReferrals` all counted 1, `accidentStatistics` showed the correct severity breakdown).

**Still not implemented (raised but out of scope for this pass, per user's explicit choice to stop at reports):** QR verification for certificates (`clearance` has no `qrcode` column, no generation logic — a genuine gap the original audit missed), account lockout / failed-login tracking (`users` table has no `FailedLoginCount`/`LastLogin` columns despite the spec listing them), backup-and-restore, field-level data encryption, optional 2FA, `PrivacyConsents`, and per-owner record access (Student/Faculty aren't functional login roles yet).

---

## Phase 11 — Post-audit follow-ups (lockout, QR verification, backup/restore) ✅ Done

Raised after Phase 10 as remaining gaps against the design doc, tackled in order of how well-defined they were (2FA and field-level encryption deliberately held back — see below).

- [x] **Account lockout / failed-login tracking (Module 10).** `users` gained `failed_login_count`, `locked_until`, `last_login` columns (spec listed these but they never existed). `handleLogin()` now locks an account for 15 minutes after 5 failed attempts, checked *before* password verification (doesn't waste bcrypt cycles on a locked account, doesn't reveal whether the password would've been right). Resets on successful login.
- [x] **Found and fixed a real pre-existing bug while testing lockout:** `jsonResponse($data, int $code = 200)` always called `http_response_code(200)` even when `handleLogin()`/`handleRegister()` had already set 401/403/409 internally — meaning login failures always returned HTTP 200 to the client. Since `api.js` checks `res.ok` (200–299) to decide whether to throw, **every login error was being silently swallowed** — a wrong password just silently redisplayed the login screen with no error message, no exception, nothing. Changed `jsonResponse`'s `$code` to default to `null` (skip re-setting) instead of `200`, so a status code set earlier in the request is preserved. Verified this didn't affect any of the dozens of other `jsonResponse(...)` call sites with explicit codes.
- [x] **QR verification for certificates (Module 8).** `clearance` gained a `qrcode` column. `handleClearanceCreate`/`handleClearanceUpdate` compute an HMAC-signed verification code (`id.signature`, signed with the JWT secret) covering the certificate's id/type/expiry/status — editing any of those fields naturally invalidates the old code without extra bookkeeping. New public (no-login) `GET /verifyCertificate?code=...` endpoint checks the signature and returns only non-sensitive fields (name, type, status, dates) — never full health data. Frontend renders an actual scannable QR image in the certificate view modal via a new CDN library (`qrcodejs`, same pattern as the existing Tailwind/Lucide/Chart.js CDN scripts), encoding a URL that hits the verify endpoint directly.
- [x] **Backup and restore (Module 10).** New admin-only `GET /backup` (streams a full JSON dump of all 23 tables) and `POST /restore` (delete-then-reinsert per table, preserving original row IDs so cross-table references stay intact). Both log to `audit_logs`.

**Verified live — carefully, given the destructive nature of restore:** tested the full backup→restore round-trip against an **isolated copy** of the dev SQLite file (temporarily repointed `config.local.php` at the copy, ran real HTTP requests against it, restored the original config immediately after) rather than risking the actual dev DB. Confirmed all 23 table row counts matched before/after (the one intentional +1 on `audit_logs` was the restore action logging itself), and spot-checked that a user row's `id` and `createdAt` survived the round-trip unchanged. Confirmed the real dev DB was untouched afterward (audit event count still showed the full accumulated session history). QR generation/verification tested directly: valid code verifies, tampered signature rejected, editing a certificate invalidates its old code and issues a new one, expired status correctly reported.

**Held back at first, then completed in Phase 12 per the user's explicit go-ahead:** two-factor authentication, field-level encryption at rest. `PrivacyConsents` and per-owner record access remain deferred exactly as in Phase 9, for the reasons stated there (those weren't revisited).

---

## Phase 12 — Two-Factor Authentication & Encryption at Rest (Module 10) ✅ Done

The two items held back from Phase 11 pending design decisions. User explicitly said to stop holding back and finish them, so the calls below were made directly rather than asked about, and are documented here for that reason.

**Two-factor authentication — chose TOTP** (Google Authenticator / Authy-style, RFC 6238), not email/SMS: no external service dependency, fully self-contained like the rest of this app, and reuses the QR library already added for certificate verification.
- [x] `users` gained `two_factor_secret` and `two_factor_enabled` columns
- [x] Dependency-free TOTP implementation in `auth.php` (base32 encode/decode, HMAC-SHA1, 30s step, 6 digits) — **verified against the official RFC 6238 Appendix B test vector** before trusting it with real authentication (`base32Encode('12345678901234567890')` at T=59 produces code `287082`, exactly matching the spec's published test case)
- [x] Login flow: password success with 2FA enabled returns a short-lived (5 min) `pending2fa`-flagged temp token instead of a real session, requiring `POST /login/verify-2fa` with a valid TOTP code to complete. `getAuthUser()` explicitly rejects any token carrying `pending2fa` — confirmed by test that a temp token cannot be used to call a real protected endpoint (403/401, not the students list)
- [x] Self-service enrollment: `POST /2fa/setup` generates a secret and returns an `otpauth://` URL (secret isn't marked enabled until confirmed with a real code, so a half-finished setup can't lock anyone out), `POST /2fa/confirm` verifies and enables, `POST /2fa/disable` requires a current code to turn off
- [x] Frontend: a "Security" entry point on every logged-in user's topbar avatar (not admin-gated — this is a self-service feature, any role can secure their own account) opens a modal that walks through QR scan → confirm code → enabled, or shows a disable form if already enabled. Login flow gained a dedicated 2FA code-entry screen (`renderTwoFactorScreen`) since without it 2FA would only be usable via direct API calls, not through the actual app

**Field-level encryption at rest — chose column-level AES-256-GCM applied only at the DB read/write boundary** (inside `dbCreate`/`dbUpdate`/`dbGetAll`/`dbGetById`), not application-wide: this means every existing filter, search, CSV export, and report keeps working completely unchanged, because they all operate on the already-decrypted PHP arrays these functions return — only what's physically written to disk is ciphertext.
- [x] `ENCRYPTED_FIELDS` allowlist covers free-text health-detail fields only — `medicalrecords` (allergies/medicalconditions/remarks), `medicalhistory` (diagnosis/treatment), `visits` (complaint/diagnosis/treatment/assessment), `staff` (healthnotes), `employee_medical_record` (allergies/medicalconditions/remarks), `incidents` (description/actiontaken), `emergency_treatment` (treatment/remarks) — deliberately excludes ids/usernames/statuses/dates/names, i.e. anything ever used in a raw SQL `WHERE` clause elsewhere, since AES-GCM ciphertext can't be matched or searched at the SQL level
- [x] Backward compatible by construction: `decryptValue()` checks for an `enc:` prefix and returns any value without it unchanged, so rows written before this phase (plaintext) keep working with no migration needed — new writes are encrypted going forward
- [x] `encryption_key` added to config (separate from `jwt_secret`)

**Verified live, not just unit-level:**
- Created a visit with a diagnosis/complaint through the real API, then read the **raw SQLite file directly** (bypassing the app entirely) and confirmed the stored value is genuinely `enc:`-prefixed ciphertext, not plaintext — while the API still returned the correct plaintext
- Created a second visit with the same diagnosis and confirmed Phase 10's `mostCommonIllnesses` report still correctly grouped and counted both (2), proving report aggregation is unaffected by encryption
- Manually inserted a raw plaintext row (simulating pre-encryption legacy data) and confirmed it reads back correctly through `dbGetById` — proving the backward-compatibility fallback actually works, not just assumed
- Full 2FA round trip: wrong code rejected on setup confirm, correct code enables it, a subsequent login correctly demands the second factor, completing it with a fresh code issues a real session, and — the security-critical check — the intermediate temp token was confirmed **unable** to authenticate a normal API call

---

## Phase 13 — PrivacyConsents & Per-Owner Record Access (Module 10) ✅ Done

The two items deferred since Phase 9, finished per the user's explicit go-ahead. Both required real judgment calls documented below rather than being pure mechanical gap-fills.

**PrivacyConsents — implemented as generic consent tracking**, not a full legal consent workflow (guardian e-signatures, versioned consent forms, expiry reminders would be a materially bigger project than what the spec's literal `PrivacyConsents` table — `ConsentID; SubjectID; ConsentType; GrantedAt; Status` — describes).
- [x] `privacy_consents` table + standard generic CRUD (`resourceMap`, `privacyConsents:write` permission, audit logging) — reuses the exact same pattern as every other admin-managed table this session
- [x] Granted to Administrator, School Nurse, and Staff Encoder (the roles that register students/employees and would capture consent at intake) — verified a Student account correctly gets 403 trying to write it

**Per-owner record access — the bigger one.** Root problem found in Phase 11: Student and "Faculty and Staff" weren't functional login roles at all (`getDefaultRolePermissions()` only had the 4 clinical roles). Design chosen: **admin-linked**, not self-registration — an administrator explicitly links a login account to a specific `students`/`staff` row via a new `linkedRecordId` field, rather than letting someone claim to be a given student. Safer, and matches how a real school would provision portal accounts.
- [x] `users` gained a `linked_record_id` column
- [x] Both roles added with a real user-facing capability (Student books own appointments; Faculty and Staff can too) rather than being read-only stubs
- [x] **Row-level filtering** added directly in `handleResourceList`/`handleResourceGet` via a new `ownerFilterFor()` function — the only place in the whole backend that does row-level (not just table-level) access control. For Student/Faculty, every list is filtered and every direct-by-id fetch 404s (not 403 — doesn't reveal that the record exists) unless it belongs to them.
- [x] **Documented, not silently patched, a real pre-existing data-model gap:** `medicalrecords`/`medicalhistory` have a `studentId` column to key off, but `visits`/`appointments`/`clearance` only ever identified the patient by free-text `patientName`/`name` — no foreign key. Row-level matching for those three tables is therefore by exact name match, which is a genuine limitation (a mistyped or duplicate name could under/over-match) — flagged in code comments rather than glossed over, since fixing it properly means adding a `studentId`/`staffId` column to three tables and backfilling existing rows, which is real schema-migration work beyond this pass's scope.
- [x] Appointment booking forces a Student/Faculty user's own identity onto the request server-side (`patientName`/`patientType` overwritten from their linked record, `status` forced to `Pending`) — a self-service user cannot book an appointment under someone else's name no matter what the request body says
- [x] `/dashboard` was found to leak clinic-wide data to these new roles (it only required `requireAuth()`, no role check) — added `handleSelfServiceDashboard()` returning only their own upcoming appointments and history, routed by role before it would've reached the full aggregation
- [x] Frontend: `role` dropdown and a `linkedRecordId` field added to the Users form so an admin can actually create these accounts; a dedicated self-service dashboard view for these roles instead of the full clinical dashboard

**Verified live — the security-critical parts, not just happy path:**
- A Student account's `GET /api/students` returns exactly 1 row (their own); `GET /api/students/{otherId}` for a different student returns 404, not their data
- The same student attempting `GET /api/incidents` (a table they have no ownership mapping for) gets an empty list, not an error and not everyone else's incidents
- Booking an appointment while passing a different patient's name in the request body was silently overridden to their own linked identity server-side
- A Staff Encoder (existing clinical role) was re-verified to still see all students unfiltered — confirming `ownerFilterFor()` correctly returns `null` (no filtering) for every role except Student/Faculty, i.e. this didn't regress the roles that already worked correctly

---

## Phase 14 — Closed the two remaining backend-only UI gaps ✅ Done

Both features had working, tested APIs since Phases 5 and 7 but no screen — closed now.

- [x] **Employee Visit & Medicine Dispensing UI (Module 6).** Extended the staff detail view (already had an Employee Medical Profile section from Phase 5) with a Visit History section — a running log with a quick-log form (complaint/diagnosis/treatment) — and a Medicine Dispensed section with a dropdown sourced from live inventory and a dispense form that hits the same stock-deducting endpoint from Phase 5.
- [x] **Doctor Schedule management UI (Module 4).** New "Doctor Schedule" button on the Appointments toolbar opens a modal listing declared availability with delete, plus a quick-add form (doctor, date, time) that creates an `Available` slot — the same table `handleAppointmentCreate()` has been validating bookings against since Phase 7.

**Verified live:** logged an employee visit and dispensed employee medicine through the actual endpoints these buttons call; created and deleted a doctor availability slot the same way. All three confirmed working against the real backend, not just wired up and assumed correct.

**Still not closed, and not closeable by more coding this session:**
- The `visits`/`appointments`/`clearance` name-based (not foreign-key) patient matching from Phase 13 — fixing it means adding `studentId`/`staffId` columns to three tables and backfilling existing rows, real schema migration work, not a UI gap.
- Visual/browser verification of anything built after the Visits/Appointments fix in Phase 3 — no browser-driving tool was available this session, so everything since then was verified via direct API calls against real data rather than a human (or automated browser) actually clicking through the screens. Worth a manual pass before considering the UI itself fully done, separate from the backend logic being correct.

---

## Phase 15 — Real browser verification (Playwright) — found and fixed 2 genuine bugs that survived the entire session ✅ Done

The user set up a Playwright MCP server specifically so the UI could finally be clicked through for real, instead of verified only via direct API calls (the caveat repeated at the end of nearly every phase above). This immediately paid off — two real bugs were found that every prior API-based test had structurally been unable to catch, because both live in the browser-side save/init code that a direct API call bypasses entirely.

**Bug 1 — vitals silently never saved, in the exact feature Phase 3 was supposed to fix.** Logged a real visit through the actual "Log Visit" form (temperature, blood pressure, pulse rate) and the Vital Signs column showed "—". Root cause: `doSave()` in `js/app.js` had a leftover `EXTRA_FIELDS` block — `visits: ['grade', 'temperature', 'bloodPressure', 'pulseRate', 'assessment', 'medicineDispensed', 'disposition']` — that unconditionally deleted those exact fields from the save payload before every API call, commented "Strip fields not yet in the database schema." That comment was true when it was written; it stopped being true the moment Phase 3 added those columns, and nobody removed the strip. This means **vitals had never actually saved through the real UI at any point since Phase 3**, even though the backend, the form rendering, and every direct-API test all checked out individually — removed the stale strip. While in the same function, also found and fixed a second issue: an unconditional `data.date = new Date()...` override that ran on both create *and edit*, meaning editing an old visit's date/time would silently reset it to right now — rescoped to only apply on create (the date/time fields are intentionally readonly, meant to auto-refresh if the create modal sits open a while, but must never touch an edit of historical data).

**Bug 2 — first click into almost any module crashed, right after login.** Clicking "Incident & Emergency" (and, it turned out, Staff/Programs/Users/Privacy Consents/Medicine/Clearance — anything using the generic `renderCrudModule`) threw `TypeError: Cannot read properties of undefined (reading 'length')` on the very first navigation after login. Traced via `browser_evaluate` (comparing the live function source to disk, then testing module-state keys one at a time) to a loop in `init()`:
```js
Object.keys(MODULES).forEach(function(key) {
  var ms = getModuleState(key);
  if (Array.isArray(ms.items) && !ms.items.length) { delete ms.items; }
});
```
This deliberately deleted `items` from every module's state right after login, apparently as an attempted "not yet loaded" sentinel — but `ms.loading` already correctly serves that purpose everywhere, and nothing actually checked for `items` being *absent* (only `ms.loading`), so this only ever accomplished turning a safe empty array into an unsafe `undefined` that `renderCrudModule`'s `ms.items.length` (and potentially other unguarded call sites) would crash on during the synchronous `render()` that runs *before* the async data fetch completes. This is almost certainly the same root cause behind the `ms.items.length` bug fixed in Phase 5 for `renderAppointmentsModule` — that fix patched one symptom without this underlying cause being known. Removed the loop entirely.

**Re-verified after both fixes, via real clicks, not API calls:**
- Visits: entered vitals through the actual form, edited the same record, vitals correctly displayed with icons (🌡️ 37.8°C ❤️ 120/80 💓 88 bpm), date/time unchanged by the edit
- Incidents: created via the real form, opened detail view, added an Emergency Treatment Log entry (Phase 4) — displayed correctly
- Staff, Programs, Users, Clearance: all load without error on first click (previously all would have crashed)
- Roles & Permissions (Phase 9): matrix renders with real seeded data, toggled a checkbox, confirmed via direct DB query that the grant was actually created, reverted it
- Clearance QR (Phase 11): opened a certificate's detail view, the QR code renders as an actual scannable image, not a placeholder
- Console errors across the entire verification pass: 0 (after fixes; both bugs above were caught as real console errors before being fixed)

**Minor, non-blocking, noted but not fixed:** the sidebar's "active" highlight and the topbar breadcrumb can go stale for a beat after certain navigations (e.g. closing the Roles & Permissions view briefly showed "Reporting & Compliance" highlighted) — cosmetic only, doesn't affect functionality or data, not worth the risk of touching working navigation code for a highlight glitch in this pass.

---

## Phase 16 — Browser-verified all PRE-EXISTING functionality (not just the PDF-gap features) — found and fixed 3 more genuine bugs ✅ Done

Phase 15 verified the newly-built PDF-gap features plus a couple of bug-fix regressions, but never went back to click through features that existed *before* this project's PDF-gap work started (Appointments booking flow, Medicine dispensing, Medical Records/History, Reports export, Privacy Consents, 2FA setup, Backup, CSV import). Prompted by "have you tested the other pages that already exist before we implemented the docs" — the answer was no, so this phase did.

**Clean on first pass (0 console errors, verified via real clicks):** Appointment booking form (full submit with all fields), Medicine Inventory add + dispense (stock correctly decremented 100→95), Medical History (department/year/date filters), Reporting & Compliance (CSV export downloads verified for Audit Trail and Student Medical Profiles), Privacy Consent Tracking (add/delete), 2FA setup modal (real otpauth:// secret displayed, invalid code correctly rejected with a clear message), Backup button (real JSON file downloads).

**Bug 3 — Medical Records folder view hard-looped, hammering the backend.** Navigating to Medical Records (with 0 folders, the actual state of a fresh dev DB) produced 24,000+ identical `GET /api/medicalRecords/folders` requests within seconds, eventually throwing `net::ERR_NETWORK_IO_SUSPENDED`. Root cause in `renderMedicalRecordsModule()` (`js/app.js`):
```js
if (Array.isArray(ms.groups) && !ms.groups.length) { delete ms.groups; }
loadMedicalRecordGroups(ms);
```
`loadMedicalRecordGroups()` only fetches when `ms.groups` is `undefined` (its loading guard). But this line deleted `ms.groups` back to `undefined` on every render whenever the folder list was legitimately empty — which it always is on a fresh install — so every re-render re-triggered a fetch, which resolved to `[]` again, which triggered another delete, forever. Fixed by removing the delete entirely; an empty array is a valid loaded state, not a "not yet loaded" sentinel.

**Bug 4 — Medical Record folder create/rename/delete silently no-op'd because they read from a different data source than the list did.** After the infinite-loop fix, creating a folder through the actual "New Group" UI reported success but the folder never appeared. Traced to `backend/index.php`: `handleRecordFoldersList()` queried `SELECT DISTINCT groupfolder FROM medicalrecords` (migrated to DB-backed in an earlier commit), while `handleRecordFoldersCreate()`/`Update()`/`Delete()` were never migrated and still read/wrote folder metadata as directories under `public/records/<name>/metadata.json` on the filesystem — two completely disconnected sources of truth. A folder created via the filesystem-based `Create` handler could never appear in the DB-based `List` query unless a medical record row happened to reference that exact folder name. Fixed properly, not patched around: added a `record_folders` table (`backend/schema.sql`, `backend/schema_mysql.sql`), rewrote all four handlers in `backend/index.php` to read/write that table, `List` now unions `record_folders` with any legacy `groupfolder` values still only present on `medicalrecords` rows (so old data isn't orphaned), and removed the now-dead filesystem `rrmdir()` helper. Verified via a full create → list → rename → list → delete → list round-trip through both direct API calls and the real "New Group" UI button.

**Bug 5 — CSV import silently corrupted every quoted field.** The official `students_import_template.csv` (and the equivalent for visits) quotes every field, per normal CSV convention. `handleImportStudents()`/`handleImportVisits()` in `js/app.js` parsed each line with a naive `line.split(',')` — no quote-awareness — so every imported value kept its literal surrounding `"..."` characters baked into the stored data (e.g. a student's name was stored as `"PW Import Student"`, quotes included). Reproduced by importing a CSV built in the exact template format and confirming the corrupted values in the resulting record. Fixed by adding a proper quote-aware `parseCsvLine()` (handles quoted fields containing commas, and `""` as an escaped quote) and using it in both import handlers instead of the naive split. Verified by re-importing the same file after the fix and confirming clean values with no stray quotes.

**Test data cleanup:** all appointments, students, medicine, medical record groups, and privacy consent records created during this verification pass were deleted afterward; the four leftover Phase-7 test appointments (Test A/B/C/E) and the "Juan Playwright" test student from Phase 15 were also removed. Screenshots and Playwright output artifacts from this and prior phases were deleted per request (`.playwright-mcp/` directory and stray `*.png` files in repo root).

**Not tested this pass (explicitly deferred, not skipped by oversight):** the Restore button — intentionally not exercised against the live dev database, consistent with the caution already applied in Phase 11 (a real restore is destructive and was only ever tested against an isolated DB copy).

---

## Phase 17 — Closed the three remaining real gaps from Phase 16 ✅ Done

The three actionable items flagged at the end of Phase 16 (excluding the two intentionally-deferred backlog items and the untested-by-design Restore button).

- [x] **Medicine Dispensed wired into the visit UI.** The visit "Medicine Dispensed" field was free text with no connection to the actual stock-deducting `/api/dispensing` endpoint — a nurse had to separately go to Medicine Inventory to record what was really given. Added a `renderVisitDispenseWidget()` to the visit detail view (same select-medicine + quantity + Dispense pattern as the existing Employee Visit section from Phase 14), backed by a live-loaded medicine list (`ms.allMedicines`, lazy-fetched the same way `renderVisitsModule` already lazy-fetches other lookups). Clicking Dispense calls the real `/api/dispensing` endpoint (decrements stock, exactly like the Medicine Inventory page's own dispense button) and appends `"<name> x<qty>"` to the visit's `medicineDispensed` text field, so the free-text summary and the real inventory transaction stay in sync instead of being two disconnected things.
- [x] **`visits`/`appointments`/`clearance` linked to `students`/`staff` by id, not just name.** The Phase 13 limitation (row-level access only matched by exact `patientName`/`name` string, flagged as a real gap needing schema migration) is now closed. Added `studentid`/`staffid` columns to all three tables (`backend/schema.sql`, `backend/schema_mysql.sql`, plus `applyMigrations()` in `database.php` for databases that already exist). A new `autoLinkPatientRecord()` in `index.php` runs on every create for these three tables: if the patient's name uniquely matches one `students` or `staff` row, the id is stamped automatically — ambiguous or unmatched names are left alone rather than guessed at. Self-service bookings (Student/Faculty accounts) skip the name-lookup entirely and stamp the id directly from the account's own `linkedRecordId`, since that's already known with certainty. `ownerFilterFor()` now prefers the id match and only falls back to the old name match when a row has no id (pre-migration legacy data, or a name that couldn't be uniquely resolved) — via a new `rowMatchesOwnerFilter()` helper, so existing rows keep working exactly as before while new rows get the more precise link.
  - **One-time backfill for existing data.** `backfillPatientLinkIds()` in `database.php` runs automatically the moment the new columns are added to a pre-existing database: for every legacy visit/appointment/clearance row, it looks up a uniquely-matching student/staff by exact name and stamps the id, same ambiguity rule as create-time linking (skip if the name isn't unique). Runs once — the columns' presence is the guard against re-running it every request.
  - **Verified live:** created a real student, then a visit under that exact name via the API — confirmed `studentId` was auto-stamped to the student's real id, not left blank. Confirmed a genuinely non-matching name (no such student exists) correctly leaves `studentId` blank rather than guessing.
- [x] **Audit Trail filter UI.** The Reports page's Audit Trail and Login Attempts rows previously had no way to narrow results in-browser — CSV export was the only way to filter (in a spreadsheet, after downloading). Added a filter bar (username text, module dropdown populated from the real distinct `resource` values already in the data, date-from/date-to) directly above the Audit Trail row in `renderReports()`. A new `filterAuditRows()` helper applies the same filter to both the on-screen count and the CSV export data for both rows, so what's displayed is exactly what downloads. **Verified live:** filtering by module "medicine" correctly dropped the Audit Trail count from 140 to 9 and Login Attempts to 0 (no login events are module "medicine"); Clear correctly restored both to their unfiltered counts (140/54).

**Also cleaned up while in the area:** removed three leftover `console.log()` debug statements in `renderSidebar()` that had nothing to do with any current feature.

**Re-confirmed not a real bug, not fixed:** the "sidebar/breadcrumb stale highlight" item noted in Phase 15 — traced both `renderSidebar()`'s active-item highlight and `renderTopbar()`'s breadcrumb title, and both are pure functions of `state.currentView` recomputed fresh on every render with no separate cached variable that could go stale. There's nothing to fix here; the earlier observation was most likely a one-off rendering race caught mid-transition during rapid automated clicking, not a reproducible defect — left alone rather than changing working navigation code to chase a non-repro.

**Still deferred (unchanged, lower priority, no new action taken):** normalizing `role` into a proper `Roles` table (cosmetic, nothing currently needs it); the Restore button has still never been clicked live in the browser against the dev DB, intentionally, given how destructive a real restore is — it stays verified only via the isolated-DB-copy test from Phase 11.

**Test data cleanup:** all students/visits/medicine created during this phase's live verification were deleted afterward via direct API calls, then reloaded to confirm a clean 0-record state everywhere touched.

---

## Phase 18 — Closed the last two backlog items ✅ Done

Both items previously left as intentionally-deferred backlog (not urgent, not blocking anything) — closed per the user's explicit go-ahead.

- [x] **Restore button verified live via a real UI click, not just an isolated-DB API test.** Made a fresh isolated copy of the dev SQLite file, temporarily repointed `config.local.php` at it (same caution as Phase 11), then actually clicked the real "Backup" and "Restore" buttons in the browser. Hit one real automation snag worth recording: the Restore handler's `confirm(...)` dialog is a native browser prompt, and Playwright's default behavior auto-dismisses any native dialog it isn't explicitly told to handle *before* it fires — since `confirm()` fires synchronously the instant the file is selected, there's no reliable window to intercept it reactively. Worked around by stubbing `window.confirm = () => true` via `browser_evaluate` immediately before the click, which let the real production code run entirely unmodified (real button click → real file chooser → real file selection → real `fetch('/restore')` → real backend endpoint) with only the native OS-level prompt bypassed. Downloaded a real backup, created a throwaway student, restored the backup through the actual button, and confirmed the throwaway student was gone — full round trip through the genuine UI path. Reverted `config.local.php` back to the real dev database afterward and deleted the temporary copy.
- [x] **`role` normalized into a proper `roles` table.** Added a `roles` table (`backend/schema.sql`, `backend/schema_mysql.sql`: id, name, description, is_self_service) plus an idempotent `seedRoles()` in `database.php` that runs on every request (same pattern as the schema's own `CREATE TABLE IF NOT EXISTS` — only inserts a role name that isn't already present, so nothing ever gets overwritten if an admin later renames or adds one). Exposed as `/api/roles`, gated the same as `permissions` (`ADMIN_ONLY_RESOURCES`). The Users form's role dropdown and the Roles & Permissions matrix previously each hardcoded their own separate list of role names (three different literals across the codebase, one of which — the matrix's 4-role list — silently excluded the two self-service roles with no comment explaining why); both now read from this one table instead, with the matrix specifically filtering on `is_self_service = 0` rather than a second hardcoded exclusion list. `users.role` itself stays a plain string column — no FK constraint added, since SQLite/MySQL FK enforcement on a column already holding years of string data across every seeded test account was judged not worth the migration risk for what's fundamentally a lookup-table normalization, not a referential-integrity gap.
  - **Verified live:** reloaded and confirmed the `roles` table auto-seeded with all 6 correct names and `is_self_service` flags with zero console errors; opened the real "Add New" user form and confirmed the role dropdown lists all 6 roles (sourced from `/api/roles`, not the old hardcoded array); opened the real Roles & Permissions matrix and confirmed it now shows exactly the 4 non-self-service roles as columns, correctly excluding Student/Faculty and Staff, matching prior behavior but now driven by data instead of a hardcoded literal.

**Backlog fully closed — nothing deferred remains from the original PDF-gap audit.**

---

## Phase 19 — Closed the studentId/staffId ambiguity gap for real, plus a real multi-role browser flow that surfaced a serious access-control bug ✅ Done

Phase 17 added `studentId`/`staffId` columns and best-effort auto-linking, but explicitly left the ambiguous case (two people sharing a name) falling back to name-matching — flagged as a "documented tradeoff, not a bug." The user asked to close it properly, and to verify with a real multi-role browser flow (accident → nurse → physician → student self-service) rather than more API-level checks.

- [x] **"Link to Existing Record" selector added to the create forms for visits, appointments, and clearance.** A dropdown listing every real student (name + student ID, to tell duplicates apart) and staff member — selecting one fills the name/type fields and stamps `studentId`/`staffId` directly, bypassing `autoLinkPatientRecord()`'s name-lookup entirely. Only shown when creating (not editing) and only for staff/admin roles — self-service Student/Faculty bookers never see it, since their identity is already forced server-side. A genuine walk-in not yet enrolled still has a "Not yet in the system" option that falls back to the pre-existing name-based auto-link, which is correct — there's nothing to link to yet.
  - **Real implementation snag:** visits and appointments each turned out to have their own dedicated render functions (`renderVisitsModule`, `renderAppointmentsModule`) with independent form-building code, separate from the generic `renderCrudModule` used by clearance — the first pass only wired the selector into the generic path and silently did nothing for visits/appointments. Caught by actually opening the Log Visit form in the browser and finding the selector missing, not by re-reading the code. Fixed by extracting a shared `renderPatientLinkSelector()` and `lazyLoadPatientLinkLists()` and calling them from all three render paths.
  - **Verified live with the actual failure scenario:** created two students both named "Maria Santos" (different student IDs, different courses), opened Log Visit, selected the second one specifically from the dropdown, and confirmed via a direct API check that the resulting visit's `studentId` pointed to the exact selected record — not the other same-named student, not blank.
- [x] **Found and fixed a real, unrelated, more serious bug while doing the requested multi-role flow test.** `canAccessView()` in `js/app.js` was a leftover debug stub — `// Always return true for debugging` plus a stray `console.log` — meaning **every logged-in user saw every single nav item regardless of role**, including a Physician account seeing "User Access Control" in their sidebar. Clicking it didn't leak any data (the backend correctly 403'd both `/api/users` and `/api/roles`), but it landed on a misleading "0 records — Add New" screen for a page they had zero business seeing, and would do the same for a Student/Faculty self-service account browsing modules meant only for clinical staff. Replaced with real logic mirroring the backend's actual enforcement (`ADMIN_ONLY_RESOURCES` → `manage_users`, reports → `view_reports`, self-service roles → `appointments`/`dashboard` only, everything else open to any authenticated clinical role) instead of the stale, narrower 3-bucket permission model that existed before it was ever stubbed out.

**The full multi-role flow actually run through the browser, not simulated:**
1. **Nurse Admin** logged a real "accident" visit (ankle sprain from a fall) for one of the two duplicate-named students via the new selector, confirmed `studentId` linked precisely; logged a matching Incident & Emergency case for the same event; added an Emergency Treatment Log entry.
2. **Physician** (`Dr. Alan Reyes`, freshly created account) logged in — sidebar correctly showed only clinically-relevant modules post-fix (no more "User Access Control"); viewed the visit, edited it to add an assessment note and close out the disposition as "Treated & Discharged"; confirmed the `studentId` link survived the edit unchanged.
3. **School Nurse** account created for completeness (not separately exercised beyond creation, since the Nurse Admin account already covers that role's day-to-day actions).
4. **Student self-service** (`maria.santos`, linked via `linkedRecordId` to the exact BSN Maria Santos, not her BSIT namesake) logged in — sidebar correctly minimal (Dashboard + Appointment Scheduling only, post-fix); booked a follow-up appointment while deliberately typing a different name in the Patient Name field, and confirmed the backend still forced her real identity and her real `studentId` onto the record regardless of what she typed (Phase 13 protection, re-confirmed still intact).
5. **Row-level isolation double-checked via direct API calls as the logged-in student** (not just UI): `GET /api/visits` returned exactly her one visit, not her namesake's; `GET /api/students` returned exactly her own record, not the other Maria Santos; `GET /api/incidents` and `GET /api/medicine` returned empty arrays rather than the full table — confirming `ownerFilterFor()`'s default-deny fallback (not an open-read gap, as a code-only read might have suggested) actually holds for tables it has no explicit ownership mapping for.

**Test data cleanup:** all visits, appointments, incidents, students, and the three test user accounts (Physician, Nurse, Student) created during this flow were deleted via direct API calls afterward, confirmed via a clean reload showing 0 records everywhere touched.

---

## Phase 20 — Closed 2 of the 4 remaining caveats: automated test suite + real concurrency testing ✅ Done

The four caveats named after Phase 19 ("fully functional, but—"): no automated test suite, AI assistant untested with a real key, no concurrency/load testing, MySQL only verified once against a throwaway instance. This phase closes the two that are actually fixable by writing code; the other two are addressed below under "still open."

- [x] **A real automated test suite — dependency-free, matching the project's zero-dependency style.** No PHPUnit/composer added; this project runs on plain XAMPP with no build step, and a test harness that needs `composer install` before it works would be a worse fit than one that just runs with `php backend/tests/run_tests.php`. Built from scratch: `backend/tests/TestClient.php` (a ~90-line HTTP client + assertion helper, no framework), `backend/tests/run_tests.php` (orchestrator), and 6 test case files covering auth (login, wrong password, lockout, real 2FA/TOTP round trip using the actual RFC 6238 math — not a stub), RBAC (admin-only resources, self-service row-level isolation, identity-spoofing protection), CRUD + field-level encryption (raw SQLite file read to confirm ciphertext), the Phase 19 patient-link fix (explicit id / unique-name auto-link / ambiguous-name refusal, all three paths), backup/restore, and a full **migration self-heal test** that strips columns/tables to simulate a collaborator's pre-existing "original schema" database and confirms the app repairs itself on the next request — the same scenario verified manually against real MariaDB in Phase 18, now automated and repeatable on every run.
  - **Isolation:** the runner spins up its own throwaway SQLite file and its own `php -S` instance on a scratch port, temporarily swapping `config.local.php` to point at it (restored via a shutdown handler no matter how the run ends) — the real dev database is never touched. 50/50 assertions passing.
  - **Two genuine bugs found and fixed while building this, not contrived for the test:**
    1. The lockout test's first draft deliberately failed-logged-in 5 times against the **real `admin` account** to trigger lockout, which then broke every subsequent test case's admin login for the rest of the suite (since admin was now actually locked out). Fixed by creating disposable throwaway accounts for the lockout and 2FA tests instead of touching admin — a mistake worth naming because it's the same category of mistake a real test suite protects against: a destructive action bleeding into unrelated state.
    2. A real, deterministic hang chasing down: `proc_open()`'s pipes fill their OS buffer after enough requests (the dev server logs one line per request, and nothing was reading the pipe) and then block the child process on its next write — silently hanging every request after that point. Fixed by redirecting the server's output to a file instead of a pipe. Then found a *second* hang from the same category: two separate file handles (stdout in `'w'` mode, stderr in `'a'` mode) writing the same path concurrently is a real sharing-violation hazard on Windows — fixed by merging stderr into stdout at the shell level (`2>&1`) so there's only ever one handle.
- [x] **Real concurrency testing** (`backend/tests/concurrency_test.php`), run separately since it's heavier — fires genuinely concurrent requests via `curl_multi` (not sequential calls) at the two endpoints most likely to have a race condition: 20 simultaneous dispense requests against a medicine with only 10 in stock (confirmed: stock never goes negative, exactly 10 of 20 succeeded — no overselling), and 10 simultaneous appointment bookings for the same doctor/date/time slot (confirmed: exactly 1 succeeded, the double-booking check holds under real concurrent load, not just sequential calls).
  - **A real, valuable fix came out of chasing the same hang described above**, once isolated to SQLite specifically: added `PRAGMA journal_mode = WAL` and `PRAGMA busy_timeout = 5000` to the SQLite connection setup in `database.php`. This isn't just a test-fixture workaround — every request opens a fresh PDO connection to the same file (PHP tears down all state between requests), so without WAL mode, SQLite's default rollback-journal locking was genuinely making concurrent requests block each other far more than necessary. This is a real production concurrency improvement, found because the test suite ran enough real rapid-fire requests to expose it — exactly the kind of thing "no concurrency testing" was flagging as unverified.

**Also done in this phase, unrelated to testing:** documented `config.local.php`'s role as the project's de facto `.env` (there is no `.env` file or dotenv loader in this codebase — `config.php`'s `getenv()` calls only see real OS environment variables, which nobody sets; `config.local.php`, tracked in git with safe XAMPP defaults, is what actually configures the database). Added a comment block to both `config.php` and `config.local.php` explaining this, per the user's explicit choice not to introduce a second, overlapping config mechanism.

**Still open, and cannot be closed by writing more code:**
- **AI assistant untested with a real API key** — see Phase 21: the provider was swapped to Gemini, and the "no key configured" path is now verified, but an actual successful round trip still needs a real key.
- **MySQL only verified against a throwaway MariaDB instance, not collaborators' actual databases** — Phase 18 already did the most realistic verification possible without direct access to a collaborator's machine (stripped a real MariaDB instance down to simulate their exact "original schema" scenario, confirmed self-healing). The new migration self-heal test case in this phase re-verifies the same scenario on SQLite, automatically, on every future run — but nothing can substitute for actually running it against a collaborator's real database, which remains genuinely untestable from here.

---

## Phase 21 — Swapped the AI provider from Anthropic to Gemini (cost reason), verified degradation both via automated test and a real browser click-through ✅ Done

Anthropic API usage isn't free; Gemini has a usable free tier, which matters for a capstone-scale project with no budget. Explicit user instruction: "yes because anthropic key is not free, we need to change it to gemini."

- [x] **Replaced `backend/claude.php` with `backend/gemini.php`** (old file deleted, not kept alongside). Same `AI_DISCLAIMER`/`AI_SYSTEM_PROMPT` constants and helper functions unchanged; only the actual API call function changed. Key differences from the old Anthropic integration: Gemini authenticates via a `?key=` query param instead of a header, and uses a `{system_instruction, contents, generationConfig}` request shape with a `{candidates: [{content: {parts: [{text}]}}]}` response shape (Anthropic's was `{content: [{type, text}]}`). Also handles Gemini's safety-filter case specifically — an empty `parts` array with a `promptFeedback.blockReason` means the prompt or response was blocked, not a transport error, and is reported as such rather than a generic failure.
- [x] **Config keys renamed**: `anthropic_api_key`/`anthropic_model` → `gemini_api_key`/`gemini_model` in `config.php`, defaulting to `gemini-2.0-flash` (documented as needing a check against Google's current model list if it ever starts 404ing, since Google retires model snapshots over time).
- [x] **`backend/index.php`** updated: `require_once` and the `/api/ai/analyze` call site now point at `callGemini()` instead of `callClaude()`. Stray "Claude" reference in a `schema.sql` comment fixed to say "AI" generically.
- [x] **New test case** `backend/tests/cases/07_ai_assistant.php` — verifies `/api/ai/analyze` returns a clean `200` with `status: 'not_configured'` and the disclaimer still present, rather than crashing, when no key is set. Full suite: 53/53 passing.
- [x] **Real browser click-through**, not just the API-level test: logged in as admin, created a visit with a diagnosis through the actual Log Visit form, and went looking for the UI element that triggers `/api/ai/analyze`. Found there isn't one — `AI_DISCLAIMER` and everything else AI-related is defined in `js/app.js` but never referenced, and there's no `/ai/analyze` fetch call anywhere in `js/`. The AI assistant is backend/API-only right now; it has no wired-up frontend trigger. This is a pre-existing gap, not something the Gemini swap introduced or broke — flagging it here since it means the "not configured" message can currently only be confirmed via the API test, not by clicking anything in the app.
- **Still open:** the AI feature needs a frontend UI (a button/panel wired to `/api/ai/analyze`) before it's usable by an actual clinic user at all — the endpoint works but nothing in the app calls it.

---

## Phase 22 — Real round-trip test against the live Gemini API with a user-supplied key, found and fixed 3 real bugs ✅ Done

User added a real `gemini_api_key` to `config.local.php`. Since there's still no frontend trigger (Phase 21), tested the endpoint directly with `curl` (login → create a visit → `POST /api/ai/analyze`) rather than through the UI.

- [x] **Bug: missing CA bundle broke every HTTPS call from PHP.** First real call failed with `unable to get local issuer certificate` — `php -i` confirmed `curl.cainfo`/`openssl.cafile` were both unset. This is a stock Windows/XAMPP PHP condition, not specific to this machine, so it would silently break the AI feature for any collaborator too. Fixed by bundling Mozilla's CA bundle at `backend/cacert.pem` and pointing `CURLOPT_CAINFO` at it in `gemini.php` — works for every collaborator without them touching `php.ini`.
- [x] **Bug: default model `gemini-2.0-flash` is retired.** Google's error response named the replacement directly (`models/gemini-2.0-flash is no longer available ... use models/gemini-3.6-flash`). Updated the default in both `config.php` and `gemini.php` to `gemini-3.6-flash`.
- [x] **Bug: PHP's `max_execution_time` (30s default) raced curl's own timeout (also 30s), so a slow Gemini response killed the whole request with an uncaught fatal error** (`PHP Fatal error: Maximum execution time of 30 seconds exceeded in gemini.php`) instead of the graceful JSON error response the code was supposed to always return. Fixed by dropping `CURLOPT_TIMEOUT` to 20s — comfortably under PHP's limit, so curl always loses the race and its own error handling path runs instead. Confirmed: a request that previously fatal-errored now returns `{"ok":false,"status":"error","message":"Operation timed out after ..."}` instead of a 500.
- [x] **Successful round trip confirmed**, after fixing the above: submitted a visit with diagnosis "pharyngitis," got back a real structured Gemini response (possible causes, general medical info, factors worth checking further) that respected the system prompt's constraint not to diagnose or prescribe. Full test suite rerun after all fixes: 53/53 still passing.
- **Note:** `config.local.php` is tracked in git with a real API key now in it (per the project's existing "tracked config, not a secrets file" design — see Phase 20). This is fine for a single-collaborator/local setup but means anyone who clones this repo gets that key; rotate it if the repo ever becomes public or gains untrusted collaborators.

## Phase 23 — Wired the missing frontend AI trigger into the Clinic Visit module ✅ Done

The design doc (`Clinic_Management_System_Module_Design_Final.pdf`) has no AI module at all — 10 modules, none of them AI. So the AI assistant isn't a documented requirement; it's an added feature. Placed it where it fits the doc's own workflow: Module 2 (Clinic Visit and Consultation Logging), right after step 6 "Diagnosis and treatment are encoded" — matches `callGemini()`'s existing design (takes a `visitId`, reads the diagnosis already entered, never diagnoses itself).

- [x] **`js/api.js`**: added `API.analyzeVisit(visitId)` → `POST /api/ai/analyze`.
- [x] **`js/app.js`**: added `renderVisitAiWidget()` (only shown once a diagnosis is present, mirroring the existing `renderVisitDispenseWidget()` pattern) and `analyzeVisitAi()`, wired into the Visit Details modal and the `data-action="ai-analyze-visit"` click handler. States: "Get AI Insights" button → loading → result text + disclaimer, or an inline error message on failure.
- [x] **Verified live in the actual browser**, not just code review: logged in, created a visit with diagnosis "viral fever," opened its View modal, clicked "Get AI Insights," got back a real Gemini response rendered in the UI with the disclaimer beneath it.

---

## Notes

- Each phase should get its own DB migration additions to **both** `backend/schema.sql` and `backend/schema_mysql.sql` (SQLite and MySQL variants must stay in sync).
- Reuse Phase 1's `dbLogAudit()` and `<resource>:write` permission pattern for every new resource — don't re-invent per phase.
- Test locally via the SQLite dev setup (`backend/config.local.php` → `db_driver: sqlite`) before touching a shared MySQL/Postgres instance.
- Run `php backend/tests/run_tests.php` (and, for a heavier check, `php backend/tests/concurrency_test.php`) before considering any change to `backend/` done — both are self-isolating and never touch the real dev database or `config.local.php`.
