-- Run this in your SQL editor to create all tables

-- Users table
CREATE TABLE IF NOT EXISTS users (
  id VARCHAR(48) PRIMARY KEY,
  fullname TEXT NOT NULL DEFAULT '',
  username TEXT UNIQUE NOT NULL,
  password TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'Staff Encoder',
  status TEXT NOT NULL DEFAULT 'Active',
  failed_login_count INTEGER NOT NULL DEFAULT 0,
  locked_until BIGINT NOT NULL DEFAULT 0,
  last_login BIGINT NOT NULL DEFAULT 0,
  two_factor_secret TEXT NOT NULL DEFAULT '',
  two_factor_enabled INTEGER NOT NULL DEFAULT 0,
  linked_record_id TEXT NOT NULL DEFAULT '',
  created_at BIGINT NOT NULL DEFAULT 0
);

-- Student Medical Records
CREATE TABLE IF NOT EXISTS students (
  id VARCHAR(48) PRIMARY KEY,
  name TEXT NOT NULL DEFAULT '',
  studentid TEXT NOT NULL DEFAULT '',
  course TEXT NOT NULL DEFAULT '',
  yearlevel TEXT NOT NULL DEFAULT '',
  bloodtype TEXT NOT NULL DEFAULT '',
  allergies TEXT NOT NULL DEFAULT '',
  conditions TEXT NOT NULL DEFAULT '',
  contactnumber TEXT NOT NULL DEFAULT '',
  emergencycontact TEXT NOT NULL DEFAULT '',
  status TEXT NOT NULL DEFAULT 'Active',
  created_at BIGINT NOT NULL DEFAULT 0
);

-- Medical Records
CREATE TABLE IF NOT EXISTS medicalrecords (
  id VARCHAR(48) PRIMARY KEY,
  recordid TEXT NOT NULL DEFAULT '',
  status TEXT NOT NULL DEFAULT 'Active',
  studentid TEXT NOT NULL DEFAULT '',
  studentname TEXT NOT NULL DEFAULT '',
  department TEXT NOT NULL DEFAULT '',
  yearlevel TEXT NOT NULL DEFAULT '',
  section TEXT NOT NULL DEFAULT '',
  bloodtype TEXT NOT NULL DEFAULT '',
  allergies TEXT NOT NULL DEFAULT '',
  medicalconditions TEXT NOT NULL DEFAULT '',
  height TEXT NOT NULL DEFAULT '',
  weight TEXT NOT NULL DEFAULT '',
  vision TEXT NOT NULL DEFAULT '',
  hearing TEXT NOT NULL DEFAULT '',
  immunizationstatus TEXT NOT NULL DEFAULT '',
  remarks TEXT NOT NULL DEFAULT '',
  groupfolder TEXT NOT NULL DEFAULT '',
  created_at BIGINT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS record_folders (
  name VARCHAR(255) PRIMARY KEY,
  meta TEXT NOT NULL DEFAULT '',
  created_at BIGINT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS roles (
  id VARCHAR(48) PRIMARY KEY,
  name VARCHAR(255) NOT NULL DEFAULT '',
  description TEXT NOT NULL DEFAULT '',
  is_self_service INTEGER NOT NULL DEFAULT 0,
  created_at BIGINT NOT NULL DEFAULT 0
);

-- Medical History
CREATE TABLE IF NOT EXISTS medicalhistory (
  id VARCHAR(48) PRIMARY KEY,
  historyid TEXT NOT NULL DEFAULT '',
  studentid TEXT NOT NULL DEFAULT '',
  studentname TEXT NOT NULL DEFAULT '',
  department TEXT NOT NULL DEFAULT '',
  yearlevel TEXT NOT NULL DEFAULT '',
  section TEXT NOT NULL DEFAULT '',
  bloodtype TEXT NOT NULL DEFAULT '',
  course TEXT NOT NULL DEFAULT '',
  diagnosis TEXT NOT NULL DEFAULT '',
  treatment TEXT NOT NULL DEFAULT '',
  doctor TEXT NOT NULL DEFAULT '',
  visitdate TEXT NOT NULL DEFAULT '',
  created_at BIGINT NOT NULL DEFAULT 0
);

-- Clinic Visits
CREATE TABLE IF NOT EXISTS visits (
  id VARCHAR(48) PRIMARY KEY,
  patientname TEXT NOT NULL DEFAULT '',
  patienttype TEXT NOT NULL DEFAULT '',
  studentid TEXT NOT NULL DEFAULT '',
  staffid TEXT NOT NULL DEFAULT '',
  date TEXT NOT NULL DEFAULT '',
  time TEXT NOT NULL DEFAULT '',
  complaint TEXT NOT NULL DEFAULT '',
  diagnosis TEXT NOT NULL DEFAULT '',
  treatment TEXT NOT NULL DEFAULT '',
  temperature TEXT NOT NULL DEFAULT '',
  bloodpressure TEXT NOT NULL DEFAULT '',
  pulserate TEXT NOT NULL DEFAULT '',
  assessment TEXT NOT NULL DEFAULT '',
  medicinedispensed TEXT NOT NULL DEFAULT '',
  nurseonduty TEXT NOT NULL DEFAULT '',
  disposition TEXT NOT NULL DEFAULT '',
  created_at BIGINT NOT NULL DEFAULT 0
);

-- Medicine Inventory
CREATE TABLE IF NOT EXISTS medicine (
  id VARCHAR(48) PRIMARY KEY,
  name TEXT NOT NULL DEFAULT '',
  category TEXT NOT NULL DEFAULT '',
  stock INTEGER NOT NULL DEFAULT 0,
  unit TEXT NOT NULL DEFAULT '',
  expirydate TEXT NOT NULL DEFAULT '',
  reorderlevel INTEGER NOT NULL DEFAULT 0,
  created_at BIGINT NOT NULL DEFAULT 0
);

-- Medicine Dispensing Log
CREATE TABLE IF NOT EXISTS dispensing (
  id VARCHAR(48) PRIMARY KEY,
  studentid TEXT NOT NULL DEFAULT '',
  patientname TEXT NOT NULL DEFAULT '',
  medicine_id TEXT NOT NULL DEFAULT '',
  medicine_name TEXT NOT NULL DEFAULT '',
  quantity INTEGER NOT NULL DEFAULT 0,
  date_released TEXT NOT NULL DEFAULT '',
  released_by TEXT NOT NULL DEFAULT '',
  created_at BIGINT NOT NULL DEFAULT 0
);

-- Appointments
CREATE TABLE IF NOT EXISTS appointments (
  id VARCHAR(48) PRIMARY KEY,
  patientname TEXT NOT NULL DEFAULT '',
  patienttype TEXT NOT NULL DEFAULT '',
  studentid TEXT NOT NULL DEFAULT '',
  staffid TEXT NOT NULL DEFAULT '',
  doctor_id TEXT NOT NULL DEFAULT '',
  date TEXT NOT NULL DEFAULT '',
  time TEXT NOT NULL DEFAULT '',
  type TEXT NOT NULL DEFAULT '',
  status TEXT NOT NULL DEFAULT 'Pending',
  notes TEXT NOT NULL DEFAULT '',
  created_at BIGINT NOT NULL DEFAULT 0
);

-- Doctor availability, used to validate appointment scheduling
CREATE TABLE IF NOT EXISTS doctor_schedule (
  id VARCHAR(48) PRIMARY KEY,
  doctor_id TEXT NOT NULL DEFAULT '',
  date TEXT NOT NULL DEFAULT '',
  available_time TEXT NOT NULL DEFAULT '',
  status TEXT NOT NULL DEFAULT 'Available',
  created_at BIGINT NOT NULL DEFAULT 0
);

-- Incident Reports
CREATE TABLE IF NOT EXISTS incidents (
  id VARCHAR(48) PRIMARY KEY,
  caseno TEXT NOT NULL DEFAULT '',
  date TEXT NOT NULL DEFAULT '',
  personinvolved TEXT NOT NULL DEFAULT '',
  location TEXT NOT NULL DEFAULT '',
  description TEXT NOT NULL DEFAULT '',
  severity TEXT NOT NULL DEFAULT '',
  status TEXT NOT NULL DEFAULT 'Open',
  actiontaken TEXT NOT NULL DEFAULT '',
  created_at BIGINT NOT NULL DEFAULT 0
);

-- Emergency Treatment Log (multiple entries per incident)
CREATE TABLE IF NOT EXISTS emergency_treatment (
  id VARCHAR(48) PRIMARY KEY,
  incident_id TEXT NOT NULL DEFAULT '',
  treatment TEXT NOT NULL DEFAULT '',
  medicine TEXT NOT NULL DEFAULT '',
  nurse TEXT NOT NULL DEFAULT '',
  date TEXT NOT NULL DEFAULT '',
  remarks TEXT NOT NULL DEFAULT '',
  created_at BIGINT NOT NULL DEFAULT 0
);

-- Faculty & Staff Health Services
CREATE TABLE IF NOT EXISTS staff (
  id VARCHAR(48) PRIMARY KEY,
  name TEXT NOT NULL DEFAULT '',
  department TEXT NOT NULL DEFAULT '',
  position TEXT NOT NULL DEFAULT '',
  bloodtype TEXT NOT NULL DEFAULT '',
  healthnotes TEXT NOT NULL DEFAULT '',
  lastcheckup TEXT NOT NULL DEFAULT '',
  contactnumber TEXT NOT NULL DEFAULT '',
  created_at BIGINT NOT NULL DEFAULT 0
);

-- Faculty & Staff: dedicated medical record, visit log, and dispensing log
CREATE TABLE IF NOT EXISTS employee_medical_record (
  id VARCHAR(48) PRIMARY KEY,
  staff_id TEXT NOT NULL DEFAULT '',
  bloodtype TEXT NOT NULL DEFAULT '',
  allergies TEXT NOT NULL DEFAULT '',
  medicalconditions TEXT NOT NULL DEFAULT '',
  immunizationstatus TEXT NOT NULL DEFAULT '',
  lastphysicalexam TEXT NOT NULL DEFAULT '',
  remarks TEXT NOT NULL DEFAULT '',
  created_at BIGINT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS employee_visit (
  id VARCHAR(48) PRIMARY KEY,
  staff_id TEXT NOT NULL DEFAULT '',
  staffname TEXT NOT NULL DEFAULT '',
  date TEXT NOT NULL DEFAULT '',
  time TEXT NOT NULL DEFAULT '',
  complaint TEXT NOT NULL DEFAULT '',
  diagnosis TEXT NOT NULL DEFAULT '',
  treatment TEXT NOT NULL DEFAULT '',
  nurseonduty TEXT NOT NULL DEFAULT '',
  created_at BIGINT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS employee_medicine (
  id VARCHAR(48) PRIMARY KEY,
  staff_id TEXT NOT NULL DEFAULT '',
  staffname TEXT NOT NULL DEFAULT '',
  medicine_id TEXT NOT NULL DEFAULT '',
  medicine_name TEXT NOT NULL DEFAULT '',
  quantity INTEGER NOT NULL DEFAULT 0,
  date_released TEXT NOT NULL DEFAULT '',
  released_by TEXT NOT NULL DEFAULT '',
  created_at BIGINT NOT NULL DEFAULT 0
);

-- Health Programs
CREATE TABLE IF NOT EXISTS programs (
  id VARCHAR(48) PRIMARY KEY,
  name TEXT NOT NULL DEFAULT '',
  category TEXT NOT NULL DEFAULT '',
  startdate TEXT NOT NULL DEFAULT '',
  enddate TEXT NOT NULL DEFAULT '',
  targetparticipants INTEGER NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'Upcoming',
  description TEXT NOT NULL DEFAULT '',
  created_at BIGINT NOT NULL DEFAULT 0
);

-- School Health Program Monitoring: participants, attendance, assessment
CREATE TABLE IF NOT EXISTS participants (
  id VARCHAR(48) PRIMARY KEY,
  program_id TEXT NOT NULL DEFAULT '',
  studentid TEXT NOT NULL DEFAULT '',
  studentname TEXT NOT NULL DEFAULT '',
  eligibility TEXT NOT NULL DEFAULT '',
  status TEXT NOT NULL DEFAULT 'Registered',
  created_at BIGINT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS attendance (
  id VARCHAR(48) PRIMARY KEY,
  program_id TEXT NOT NULL DEFAULT '',
  participant_id TEXT NOT NULL DEFAULT '',
  attendance_date TEXT NOT NULL DEFAULT '',
  status TEXT NOT NULL DEFAULT 'Present',
  created_at BIGINT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS assessment (
  id VARCHAR(48) PRIMARY KEY,
  program_id TEXT NOT NULL DEFAULT '',
  participant_id TEXT NOT NULL DEFAULT '',
  result TEXT NOT NULL DEFAULT '',
  remarks TEXT NOT NULL DEFAULT '',
  assessed_by TEXT NOT NULL DEFAULT '',
  created_at BIGINT NOT NULL DEFAULT 0
);

-- Health Clearance
CREATE TABLE IF NOT EXISTS clearance (
  id VARCHAR(48) PRIMARY KEY,
  name TEXT NOT NULL DEFAULT '',
  persontype TEXT NOT NULL DEFAULT '',
  studentid TEXT NOT NULL DEFAULT '',
  staffid TEXT NOT NULL DEFAULT '',
  clearancetype TEXT NOT NULL DEFAULT '',
  dateissued TEXT NOT NULL DEFAULT '',
  expirydate TEXT NOT NULL DEFAULT '',
  status TEXT NOT NULL DEFAULT 'Pending',
  issuedby TEXT NOT NULL DEFAULT '',
  qrcode TEXT NOT NULL DEFAULT '',
  created_at BIGINT NOT NULL DEFAULT 0
);

-- Seed default admin user is handled by the /seed API endpoint

-- AI Audit Log: tracks who sent medical information to external AI services
CREATE TABLE IF NOT EXISTS audit_logs (
  id VARCHAR(48) PRIMARY KEY,
  user_id TEXT NOT NULL DEFAULT '',
  username TEXT NOT NULL DEFAULT '',
  action TEXT NOT NULL DEFAULT '',
  resource TEXT NOT NULL DEFAULT '',
  resource_id TEXT NOT NULL DEFAULT '',
  details TEXT NOT NULL DEFAULT '',
  ip_address TEXT NOT NULL DEFAULT '',
  created_at BIGINT NOT NULL DEFAULT 0
);

-- Privacy consent tracking (generic: subject, consent type, status, date granted)
CREATE TABLE IF NOT EXISTS privacy_consents (
  id VARCHAR(48) PRIMARY KEY,
  subject_id TEXT NOT NULL DEFAULT '',
  subject_name TEXT NOT NULL DEFAULT '',
  subject_type TEXT NOT NULL DEFAULT '',
  consent_type TEXT NOT NULL DEFAULT '',
  status TEXT NOT NULL DEFAULT 'Granted',
  granted_at TEXT NOT NULL DEFAULT '',
  notes TEXT NOT NULL DEFAULT '',
  created_at BIGINT NOT NULL DEFAULT 0
);

-- RBAC: permissions catalog (module + action, e.g. module='students', action='write')
CREATE TABLE IF NOT EXISTS permissions (
  id VARCHAR(48) PRIMARY KEY,
  module TEXT NOT NULL DEFAULT '',
  action TEXT NOT NULL DEFAULT '',
  description TEXT NOT NULL DEFAULT '',
  created_at BIGINT NOT NULL DEFAULT 0
);

-- RBAC: grants a permission to a role
CREATE TABLE IF NOT EXISTS role_permissions (
  id VARCHAR(48) PRIMARY KEY,
  role TEXT NOT NULL DEFAULT '',
  permission_id TEXT NOT NULL DEFAULT '',
  created_at BIGINT NOT NULL DEFAULT 0
);

-- AI Request Log: records each AI analysis request and its outcome
CREATE TABLE IF NOT EXISTS ai_requests (
  id VARCHAR(48) PRIMARY KEY,
  user_id TEXT NOT NULL DEFAULT '',
  username TEXT NOT NULL DEFAULT '',
  visit_id TEXT NOT NULL DEFAULT '',
  diagnosis TEXT NOT NULL DEFAULT '',
  request_payload TEXT NOT NULL DEFAULT '',
  response_summary TEXT NOT NULL DEFAULT '',
  status TEXT NOT NULL DEFAULT 'pending',
  error TEXT NOT NULL DEFAULT '',
  ip_address TEXT NOT NULL DEFAULT '',
  created_at BIGINT NOT NULL DEFAULT 0
);
