-- MySQL-compatible schema for Clinic System

CREATE TABLE IF NOT EXISTS users (
  id VARCHAR(48) PRIMARY KEY,
  fullname TEXT NOT NULL DEFAULT '',
  username VARCHAR(255) UNIQUE NOT NULL,
  password TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'Staff Encoder',
  status TEXT NOT NULL DEFAULT 'Active',
  created_at BIGINT NOT NULL DEFAULT 0
);

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

CREATE TABLE IF NOT EXISTS medicalhistory (
  id VARCHAR(48) PRIMARY KEY,
  historyid TEXT NOT NULL DEFAULT '',
  studentid TEXT NOT NULL DEFAULT '',
  diagnosis TEXT NOT NULL DEFAULT '',
  treatment TEXT NOT NULL DEFAULT '',
  doctor TEXT NOT NULL DEFAULT '',
  visitdate TEXT NOT NULL DEFAULT '',
  created_at BIGINT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS visits (
  id VARCHAR(48) PRIMARY KEY,
  patientname TEXT NOT NULL DEFAULT '',
  patienttype TEXT NOT NULL DEFAULT '',
  date TEXT NOT NULL DEFAULT '',
  time TEXT NOT NULL DEFAULT '',
  complaint TEXT NOT NULL DEFAULT '',
  diagnosis TEXT NOT NULL DEFAULT '',
  treatment TEXT NOT NULL DEFAULT '',
  nurseonduty TEXT NOT NULL DEFAULT '',
  created_at BIGINT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS medicine (
  id VARCHAR(48) PRIMARY KEY,
  name TEXT NOT NULL DEFAULT '',
  category TEXT NOT NULL DEFAULT '',
  stock INT NOT NULL DEFAULT 0,
  unit TEXT NOT NULL DEFAULT '',
  expirydate TEXT NOT NULL DEFAULT '',
  reorderlevel INT NOT NULL DEFAULT 0,
  created_at BIGINT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS appointments (
  id VARCHAR(48) PRIMARY KEY,
  patientname TEXT NOT NULL DEFAULT '',
  patienttype TEXT NOT NULL DEFAULT '',
  date TEXT NOT NULL DEFAULT '',
  time TEXT NOT NULL DEFAULT '',
  type TEXT NOT NULL DEFAULT '',
  status TEXT NOT NULL DEFAULT 'Pending',
  notes TEXT NOT NULL DEFAULT '',
  created_at BIGINT NOT NULL DEFAULT 0
);

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

CREATE TABLE IF NOT EXISTS programs (
  id VARCHAR(48) PRIMARY KEY,
  name TEXT NOT NULL DEFAULT '',
  category TEXT NOT NULL DEFAULT '',
  startdate TEXT NOT NULL DEFAULT '',
  enddate TEXT NOT NULL DEFAULT '',
  targetparticipants INT NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'Upcoming',
  description TEXT NOT NULL DEFAULT '',
  created_at BIGINT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS clearance (
  id VARCHAR(48) PRIMARY KEY,
  name TEXT NOT NULL DEFAULT '',
  persontype TEXT NOT NULL DEFAULT '',
  clearancetype TEXT NOT NULL DEFAULT '',
  dateissued TEXT NOT NULL DEFAULT '',
  expirydate TEXT NOT NULL DEFAULT '',
  status TEXT NOT NULL DEFAULT 'Pending',
  issuedby TEXT NOT NULL DEFAULT '',
  created_at BIGINT NOT NULL DEFAULT 0
);

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
