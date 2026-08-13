/* ============================== STATE ============================== */
const state = {
  authLoading: true,
  loggedOut: false,
  currentUser: null,
  currentView: 'dashboard',
  showLogoutConfirm: false,
  loadingData: false,
  sidebarCollapsed: false,
  moduleState: {},
  toast: null,
  toastTimeout: null,
};

/* ============================== CONFIG ============================== */
const NAV_ITEMS = [
  { key: 'dashboard', label: 'Dashboard' },
  { key: 'students', label: 'Student Medical Records Management' },
  { key: 'visits', label: 'Clinic Visit & Consultation Logging' },
  { key: 'medicine', label: 'Medicine Inventory & Dispensing' },
  { key: 'appointments', label: 'Appointment Scheduling System' },
  { key: 'incidents', label: 'Incident & Emergency Case Management' },
  { key: 'staff', label: 'Faculty & Staff Health Services' },
  { key: 'programs', label: 'School Health Program Monitoring' },
  { key: 'clearance', label: 'Health Clearance and Certification' },
  { key: 'reports', label: 'Reporting and Compliance' },
  { key: 'users', label: 'User Access & Confidentiality' },
];

const ICON_MAP = {
  dashboard: 'layout-dashboard',
  students: 'file-text',
  medicalRecords: 'heart-pulse',
  medicalHistory: 'history',
  visits: 'stethoscope',
  medicine: 'pill',
  appointments: 'calendar-clock',
  incidents: 'alert-triangle',
  staff: 'heart',
  programs: 'activity',
  clearance: 'badge-check',
  reports: 'clipboard-list',
  users: 'user-cog',
};

const AI_DISCLAIMER = 'AI-generated information is for medical support and reference only. It does not replace professional medical judgment. Final assessment and diagnosis must be performed by authorized healthcare personnel.';

/* ============================== RBAC ============================== */
const ROLE_PERMISSIONS = {
  'Clinic Administrator': ['manage_users', 'view_reports', 'use_ai_assistant', 'access_patient_records'],
  'School Nurse': ['view_reports', 'use_ai_assistant', 'access_patient_records'],
  'Physician': ['view_reports', 'use_ai_assistant', 'access_patient_records'],
  'Staff Encoder': ['view_reports', 'access_patient_records'],
};

function currentRole() {
  return state.currentUser ? (state.currentUser.role || '') : '';
}

function can(permission) {
  var role = currentRole();
  if (!role) return false;
  var perms = ROLE_PERMISSIONS[role] || [];
  return perms.indexOf(permission) !== -1;
}

function modulePermission(key) {
  if (key === 'users') return 'manage_users';
  if (key === 'reports') return 'view_reports';
  return 'access_patient_records';
}

function canAccessView(key) {
  return can(modulePermission(key));
}

const STUDENT_FIELDS = [
  { name: 'name', label: 'Full Name', type: 'text', required: true },
  { name: 'studentId', label: 'Student ID', type: 'text', required: true },
  { name: 'course', label: 'Course / Program', type: 'select', options: ['BSIT', 'BSED', 'BSN', 'BSCS', 'BSA', 'BSBA', 'BSE', 'BSM', 'Other'] },
  { name: 'yearLevel', label: 'Year Level', type: 'select', options: ['1st Year', '2nd Year', '3rd Year', '4th Year'] },
  { name: 'status', label: 'Status', type: 'select', options: ['Active', 'Inactive'] },
  { name: 'bloodType', label: 'Blood Type', type: 'select', options: ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-', 'Unknown'] },
  { name: 'allergies', label: 'Allergies', type: 'text' },
  { name: 'conditions', label: 'Medical Conditions', type: 'textarea' },
  { name: 'contactNumber', label: 'Contact Number', type: 'text' },
  { name: 'emergencyContact', label: 'Emergency Contact', type: 'text' },
];
const VISIT_FIELDS = [
  { name: 'patientName', label: 'Patient Name', type: 'text', required: true },
  { name: 'patientType', label: 'Patient Type', type: 'select', options: ['Student', 'Faculty/Staff'] },
  { name: 'date', label: 'Date', type: 'date', required: true },
  { name: 'time', label: 'Time', type: 'time' },
  { name: 'complaint', label: 'Chief Complaint', type: 'text' },
  { name: 'diagnosis', label: 'Diagnosis', type: 'text' },
  { name: 'treatment', label: 'Treatment Given', type: 'textarea' },
  { name: 'temperature', label: 'Temperature (°C)', type: 'text' },
  { name: 'bloodPressure', label: 'Blood Pressure (mmHg)', type: 'text' },
  { name: 'pulseRate', label: 'Pulse Rate (bpm)', type: 'text' },
  { name: 'assessment', label: 'Assessment', type: 'textarea' },
  { name: 'medicineDispensed', label: 'Medicine Dispensed', type: 'text' },
  { name: 'nurseOnDuty', label: 'Nurse on Duty', type: 'text' },
  { name: 'disposition', label: 'Disposition', type: 'select', options: ['Treated & Discharged', 'Referred to Doctor', 'Sent Home', 'Emergency Referral', 'Observation'] },
];
const MEDICINE_FIELDS = [
  { name: 'name', label: 'Medicine Name', type: 'text', required: true },
  { name: 'category', label: 'Category', type: 'select', options: ['Antibiotic', 'Analgesic', 'Antihistamine', 'Antipyretic', 'First Aid', 'Vitamins', 'Other'] },
  { name: 'stock', label: 'Stock Quantity', type: 'number', required: true },
  { name: 'unit', label: 'Unit', type: 'select', options: ['tablets', 'bottles', 'boxes', 'pcs', 'ml'] },
  { name: 'expiryDate', label: 'Expiry Date', type: 'date' },
  { name: 'reorderLevel', label: 'Reorder Level', type: 'number' },
];
const MEDICAL_RECORD_FIELDS = [
  { name: 'recordId', label: 'Record ID', type: 'text', required: true },
  { name: 'status', label: 'Status', type: 'select', options: ['Active', 'Inactive'], required: true },
  { name: 'groupFolder', label: 'Group', type: 'select', required: true },
  { name: 'studentId', label: 'Student ID', type: 'text', required: true },
  { name: 'studentName', label: 'Student Name', type: 'text', required: true },
  { name: 'department', label: 'Department', type: 'select', options: ['College of Engineering', 'College of Education', 'College of Arts & Sciences', 'College of Business', 'College of Nursing', 'College of IT', 'Senior High School', 'Junior High School', 'Elementary'] },
  { name: 'yearLevel', label: 'Year Level', type: 'select', options: ['1st Year', '2nd Year', '3rd Year', '4th Year', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'] },
  { name: 'section', label: 'Section', type: 'text' },
  { name: 'bloodType', label: 'Blood Type', type: 'select', options: ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-', 'Unknown'] },
  { name: 'allergies', label: 'Allergies', type: 'textarea' },
  { name: 'medicalConditions', label: 'Medical Conditions', type: 'textarea' },
  { name: 'height', label: 'Height (cm)', type: 'text' },
  { name: 'weight', label: 'Weight (kg)', type: 'text' },
  { name: 'vision', label: 'Vision', type: 'text' },
  { name: 'hearing', label: 'Hearing', type: 'text' },
  { name: 'immunizationStatus', label: 'Immunization Status', type: 'select', options: ['Complete', 'Incomplete', 'Pending', 'Unknown'] },
  { name: 'remarks', label: 'Remarks', type: 'textarea' },
];
const APPOINTMENT_FIELDS = [
  { name: 'patientName', label: 'Patient Name', type: 'text', required: true },
  { name: 'patientType', label: 'Patient Type', type: 'select', options: ['Student', 'Faculty/Staff'] },
  { name: 'date', label: 'Date', type: 'date', required: true },
  { name: 'time', label: 'Time', type: 'time' },
  { name: 'type', label: 'Appointment Type', type: 'select', options: ['General Check-up', 'Follow-up Consultation', 'Vaccination', 'Dental Check-up', 'Physical Exam', 'Other'] },
  { name: 'status', label: 'Status', type: 'select', options: ['Pending', 'Confirmed', 'Completed', 'Cancelled', 'No Show'] },
  { name: 'notes', label: 'Notes', type: 'textarea' },
];
const INCIDENT_FIELDS = [
  { name: 'caseNo', label: 'Case Number', type: 'text', readonly: true },
  { name: 'date', label: 'Date', type: 'date', required: true },
  { name: 'personInvolved', label: 'Person Involved', type: 'text' },
  { name: 'location', label: 'Location', type: 'text' },
  { name: 'description', label: 'Description', type: 'textarea' },
  { name: 'severity', label: 'Severity', type: 'select', options: ['Low', 'Moderate', 'High', 'Critical'] },
  { name: 'status', label: 'Status', type: 'select', options: ['Open', 'Under Investigation', 'Resolved'] },
  { name: 'actionTaken', label: 'Action Taken', type: 'textarea' },
];
const STAFF_FIELDS = [
  { name: 'name', label: 'Full Name', type: 'text', required: true },
  { name: 'department', label: 'Department', type: 'text' },
  { name: 'position', label: 'Position', type: 'text' },
  { name: 'bloodType', label: 'Blood Type', type: 'select', options: ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-', 'Unknown'] },
  { name: 'healthNotes', label: 'Health Notes', type: 'textarea' },
  { name: 'lastCheckup', label: 'Last Checkup', type: 'date' },
  { name: 'contactNumber', label: 'Contact Number', type: 'text' },
];
const PROGRAM_FIELDS = [
  { name: 'name', label: 'Program Name', type: 'text', required: true },
  { name: 'category', label: 'Category', type: 'select', options: ['Vaccination', 'Dental', 'Nutrition', 'Mental Health', 'General Wellness', 'Other'] },
  { name: 'startDate', label: 'Start Date', type: 'date' },
  { name: 'endDate', label: 'End Date', type: 'date' },
  { name: 'targetParticipants', label: 'Target Participants', type: 'number' },
  { name: 'status', label: 'Status', type: 'select', options: ['Upcoming', 'Ongoing', 'Completed'] },
  { name: 'description', label: 'Description', type: 'textarea' },
];
const CLEARANCE_FIELDS = [
  { name: 'name', label: 'Full Name', type: 'text', required: true },
  { name: 'personType', label: 'Person Type', type: 'select', options: ['Student', 'Faculty/Staff'] },
  { name: 'clearanceType', label: 'Clearance Type', type: 'select', options: ['Medical Clearance', 'Fitness to Return', 'Sports Clearance', 'Health Certificate', 'Other'] },
  { name: 'dateIssued', label: 'Date Issued', type: 'date' },
  { name: 'expiryDate', label: 'Expiry Date', type: 'date' },
  { name: 'status', label: 'Status', type: 'select', options: ['Valid', 'Pending', 'Expired'] },
  { name: 'issuedBy', label: 'Issued By', type: 'text' },
];
const MEDICAL_HISTORY_FIELDS = [
  { name: 'historyId', label: 'History ID', type: 'text', required: true },
  { name: 'studentId', label: 'Student ID', type: 'text', required: true },
  { name: 'diagnosis', label: 'Diagnosis', type: 'textarea' },
  { name: 'treatment', label: 'Treatment', type: 'textarea' },
  { name: 'doctor', label: 'Doctor', type: 'text' },
  { name: 'visitDate', label: 'Visit Date', type: 'date' },
];
const USER_FIELDS = [
  { name: 'fullName', label: 'Full Name', type: 'text', required: true },
  { name: 'username', label: 'Username', type: 'text', required: true },
  { name: 'password', label: 'Password', type: 'password', required: true, hideInTable: true },
  { name: 'role', label: 'Role', type: 'select', options: ['Clinic Administrator', 'School Nurse', 'Physician', 'Staff Encoder'] },
  { name: 'status', label: 'Status', type: 'select', options: ['Active', 'Inactive'] },
];

const MODULES = {
  students: { title: 'Student Medical Records Management', color: 'blue', storageKey: 'students', fields: STUDENT_FIELDS, searchKeys: ['name', 'studentId', 'course'], primary: 'name' },
  medicalRecords: { title: 'Medical Records', color: 'rose', storageKey: 'medicalRecords', fields: MEDICAL_RECORD_FIELDS, searchKeys: ['recordId', 'studentId', 'studentName', 'department', 'yearLevel', 'section'], primary: 'recordId' },
  medicalHistory: { title: 'Medical History', color: 'indigo', storageKey: 'medicalHistory', fields: MEDICAL_HISTORY_FIELDS, searchKeys: ['historyId','studentId','diagnosis'], primary: 'historyId' },
  visits: { title: 'Clinic Visit & Consultation Logging', color: 'teal', storageKey: 'visits', fields: VISIT_FIELDS, searchKeys: ['patientName', 'complaint', 'diagnosis'], primary: 'patientName' },
  appointments: { title: 'Appointment Scheduling System', color: 'green', storageKey: 'appointments', fields: APPOINTMENT_FIELDS, searchKeys: ['patientName', 'type'], primary: 'patientName' },
  incidents: { title: 'Incident & Emergency Case Management', color: 'red', storageKey: 'incidents', fields: INCIDENT_FIELDS, searchKeys: ['caseNo', 'personInvolved', 'description'], primary: 'caseNo' },
  staff: { title: 'Faculty & Staff Health Services', color: 'purple', storageKey: 'staff', fields: STAFF_FIELDS, searchKeys: ['name', 'department'], primary: 'name' },
  programs: { title: 'School Health Program Monitoring', color: 'indigo', storageKey: 'programs', fields: PROGRAM_FIELDS, searchKeys: ['name', 'category'], primary: 'name' },
  clearance: { title: 'Health Clearance and Certification', color: 'teal', storageKey: 'clearance', fields: CLEARANCE_FIELDS, searchKeys: ['name', 'clearanceType'], primary: 'name' },
  users: { title: 'User Access & Confidentiality', color: 'slate', storageKey: 'users', fields: USER_FIELDS, searchKeys: ['fullName', 'username', 'role'], primary: 'fullName' },
  medicine: { title: 'Medicine Inventory & Dispensing', color: 'orange', storageKey: 'medicine', fields: MEDICINE_FIELDS, searchKeys: ['name', 'category'], primary: 'name' },
};

const COLOR_MAP = {
  blue: 'bg-[#F0ECF2] text-[#2A6B9B]',
  green: 'bg-[#F0ECF2] text-[#2A8B4A]',
  orange: 'bg-[#F0ECF2] text-[#C9A24E]',
  purple: 'bg-[#F0ECF2] text-[#8B3A8B]',
  red: 'bg-[#F0ECF2] text-[#C13030]',
  indigo: 'bg-[#F0ECF2] text-[#2A1B6B]',
  teal: 'bg-[#F0ECF2] text-[#2A8B7A]',
  slate: 'bg-[#F0ECF2] text-[#5A4A62]',
  rose: 'bg-[#F0ECF2] text-[#C13030]',
};
const BTN_COLOR_MAP = {
  blue: 'bg-[#7B1028] hover:bg-[#8A2346]',
  green: 'bg-[#2A8B4A] hover:bg-[#1B6B3A]',
  orange: 'bg-[#C9A24E] hover:bg-[#B8923E]',
  purple: 'bg-[#8B3A8B] hover:bg-[#7A2A7A]',
  red: 'bg-[#C13030] hover:bg-[#A82828]',
  indigo: 'bg-[#2A1B6B] hover:bg-[#1B1060]',
  teal: 'bg-[#2A8B7A] hover:bg-[#1B6B5A]',
  slate: 'bg-[#5A4A62] hover:bg-[#4A3A52]',
  rose: 'bg-[#C13030] hover:bg-[#A82828]',
};

/* ============================== UTILITIES ============================== */
function uid() {
  return Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
}
function todayStr() {
  return new Date().toISOString().slice(0, 10);
}
function fmtDate(d) {
  if (!d) return '\u2014';
  const dt = new Date(d + 'T00:00:00');
  if (isNaN(dt.getTime())) return d;
  return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
function timeAgo(ts) {
  if (!ts) return '';
  const diff = Date.now() - ts;
  const m = Math.floor(diff / 60000);
  if (m < 1) return 'just now';
  if (m < 60) return m + ' min ago';
  const h = Math.floor(m / 60);
  if (h < 24) return h + ' hr' + (h > 1 ? 's' : '') + ' ago';
  const d = Math.floor(h / 24);
  return d + ' day' + (d > 1 ? 's' : '') + ' ago';
}
function exportCSV(filename, rows) {
  if (!rows || !rows.length) return;
  const headers = Object.keys(rows[0]).filter(function(k) { return k !== 'id' && k !== 'createdAt' && k !== 'password'; });
  const csv = [headers.join(',')]
    .concat(rows.map(function(r) { return headers.map(function(h) { return '"' + String(r[h] || '').replace(/"/g, '""') + '"'; }).join(','); }))
    .join('\n');
  const blob = new Blob([csv], { type: 'text/csv' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}
function badgeColor(value) {
  var v = (value || '').toLowerCase();
  if (['resolved', 'completed', 'valid', 'active', 'confirmed'].indexOf(v) !== -1)
    return 'bg-[#F0ECF2] text-[#2A8B4A] border-[#2A8B4A]';
  if (['pending', 'open', 'upcoming', 'low'].indexOf(v) !== -1)
    return 'bg-[#FDF6F8] text-[#C9A24E] border-[#C9A24E]';
  if (['under investigation', 'ongoing', 'moderate'].indexOf(v) !== -1)
    return 'bg-[#F0ECF2] text-[#2A6B9B] border-[#2A6B9B]';
  if (['cancelled', 'expired', 'inactive', 'no show'].indexOf(v) !== -1)
    return 'bg-[#F0ECF2] text-[#5A4A62] border-[#E8D4DB]';
  if (['high', 'critical'].indexOf(v) !== -1)
    return 'bg-[#FDF6F8] text-[#C13030] border-[#C13030]';
  return 'bg-[#F8F7FA] text-[#5A4A62] border-[#E8D4DB]';
}
function icon(name, size, cls) {
  var s = size || 18;
  var n = name || 'circle';
  return '<i data-lucide="' + n + '" style="width:' + s + 'px;height:' + s + 'px" class="' + (cls || '') + '"></i>';
}

function renderLoader(label, size) {
  var s = size || 24;
  return '<div role="status" aria-live="polite" aria-busy="true" class="flex flex-col items-center justify-center gap-3 p-6">' +
    '<div style="color:#7A1F3D">' + icon('loader', s, 'animate-spin') + '</div>' +
    (label ? '<p class="text-sm" style="color:#5A4A62">' + esc(label) + '</p>' : '') +
    '</div>';
}

function logoIcon(size, cls) {
  var s = size || 18;
  var c = cls || '';
  return '<svg xmlns="http://www.w3.org/2000/svg" width="' + s + '" height="' + s + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' + c + '"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
}
function esc(str) {
  if (str == null) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

/* ============================== BADGE ============================== */
function renderBadge(value) {
  if (!value) return '<span style="color:#5A4A62">&mdash;</span>';
  return '<span class="inline-block rounded-full border px-2.5 py-0.5 text-xs font-medium ' + badgeColor(value) + '">' + esc(value) + '</span>';
}

/* ============================== FIELD INPUT ============================== */
function renderFieldInput(field, value, namePrefix, readonly) {
  if (readonly === undefined) readonly = field.readonly;
  var id = namePrefix;
  if (!id) id = 'field_' + field.name;
  var val = value !== undefined && value !== null ? value : '';
  var baseClass = 'w-full rounded-lg border border-[#E8D4DB] px-3 py-2 text-sm text-[#2B2B2B] focus:border-[#7B1028] focus:outline-none focus:ring-2 focus:ring-[#C9A24E]/30';
  var html = '';
  if (field.type === 'select') {
    html += '<select id="' + id + '" name="' + id + '" class="' + baseClass + '">';
    html += '<option value="">Select ' + esc(field.label) + '</option>';
    (field.options || []).forEach(function(o) {
      html += '<option value="' + esc(o) + '"' + (val === o ? ' selected' : '') + '>' + esc(o) + '</option>';
    });
    html += '</select>';
  } else if (field.type === 'textarea') {
    html += '<textarea id="' + id + '" name="' + id + '" rows="3" class="' + baseClass + '">' + esc(val) + '</textarea>';
  } else if (field.type === 'password') {
    html += '<input type="password" id="' + id + '" name="' + id + '" value="' + esc(val) + '" class="' + baseClass + '" placeholder="Leave blank to keep current password" />';
  } else {
    html += '<input type="' + esc(field.type) + '" id="' + id + '" name="' + id + '" value="' + esc(val) + '" class="' + baseClass + '"' + (readonly ? ' readonly' : '') + ' />';
  }
  return html;
}

/* ============================== MODAL ============================== */
function renderModal(title, content, wide) {
  var w = wide ? 'max-w-2xl' : 'max-w-md';
  return '<div id="modal-overlay" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.7)">' +
    '<div class="w-full ' + w + ' max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-xl">' +
    '<div class="flex items-center justify-between border-b px-6 py-4" style="border-color:#E8D4DB">' +
    '<h3 class="font-serif-heading text-base font-semibold" style="color:#2B2B2B">' + esc(title) + '</h3>' +
    '<button data-action="close-modal" class="rounded-full p-1 hover:bg-[#FDF6F8]" style="color:#5A4A62">' + icon('x', 18) + '</button>' +
    '</div>' +
    '<div class="p-6">' + content + '</div>' +
    '</div>' +
    '</div>';

  // (removed duplicate folder cards from modal)
}

/* ============================== CONFIRM DIALOG ============================== */
function renderConfirmDialog(title, message, confirmLabel, danger) {
  if (!title) return '';
  return '<div id="confirm-overlay" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.7)">' +
    '<div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-xl">' +
    '<div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full ' + (danger ? 'bg-[#FDF6F8]' : 'bg-[#FDF6F8]') + '">' +
    '<i data-lucide="power" style="width:26px;height:26px" class="' + (danger ? 'text-[#C13030]' : 'text-[#C13030]') + '"></i>' +
    '</div>' +
    '<h3 class="font-serif-heading mb-1 text-lg font-semibold" style="color:#2B2B2B">' + esc(title) + '</h3>' +
    '<p class="mb-6 text-sm" style="color:#5A4A62">' + esc(message) + '</p>' +
    '<div class="flex gap-3">' +
    '<button data-action="cancel-confirm" class="flex-1 rounded-lg border py-2.5 text-sm font-medium hover:bg-[#FDF6F8]" style="border-color:#E8D4DB;color:#5A4A62">Cancel</button>' +
    '<button data-action="confirm-action" class="flex-1 rounded-lg py-2.5 text-sm font-medium text-white" style="background:#C13030;hover:background:#A82828">' + esc(confirmLabel || 'Confirm') + '</button>' +
    '</div>' +
    '</div>' +
    '</div>';
}

/* ============================== MODULE STATE HELPERS ============================== */
function getModuleState(key) {
  if (!state.moduleState[key]) {
    state.moduleState[key] = {
      items: [],
      search: '',
      modalOpen: false,
      editing: null,
      form: {},
      error: '',
      deleteTarget: null,
      viewTarget: null,
      page: 1,
      statusFilter: 'All',
      dateFilter: '',
      typeFilter: 'All',
      dispFilter: 'All',
      dispenseQty: {},
      loading: true,
      expandedGroups: {},
      expandedFolders: {},
      folderActionModalOpen: false,
      folderModalGroup: '',
      folderModalPage: 1,
      enrollmentModalOpen: false,
      enrollmentForm: {},
      enrollmentError: '',
    };
  }
  return state.moduleState[key];
}

async function loadModuleData(key) {
  var ms = getModuleState(key);
  ms.loading = true;
  renderMainContent();
  try {
    ms.items = await API.list(key);
  } catch (e) {
    ms.items = [];
  }
  ms.loading = false;
  renderMainContent();
}

function loadMedicalRecordGroups(ms) {
  if (typeof ms.groups !== 'undefined') return;
  ms.groups = null; // loading
  API.listRecordFolders().then(function(list) {
    ms.groups = list || [];
    if (!ms.groupSelected && ms.groups.length) ms.groupSelected = ms.groups[0].name;
    if (ms.modalOpen && ms.form && !ms.form.groupFolder && ms.groupSelected) {
      ms.form.groupFolder = ms.groupSelected;
    }
    renderMainContent();
  }).catch(function() {
    ms.groups = [];
    renderMainContent();
  });
}

/* ============================== VIEW RENDERERS ============================== */

function renderLoadingScreen() {
  return '<div class="flex min-h-screen items-center justify-center" style="background:#F8F7FA">' +
    renderLoader('Loading School Clinic Management System…', 40) +
    '</div>';
}

function renderLoginScreen() {
  return '<div class="flex w-full min-h-screen items-center justify-center p-4" style="background:#F8F7FA">' +
    '<div class="grid w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-xl md:grid-cols-2">' +
    '<div class="flex flex-col justify-center p-8 sm:p-10">' +
    '<div class="mb-6 flex flex-col items-center text-center">' +
    '<div class="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl" style="background:#F0ECF2">' +
    logoIcon(26, 'text-[#7B1028]') +
    '</div>' +
    '<h1 class="font-serif-heading text-lg font-semibold" style="color:#2B2B2B">School Clinic Management System</h1>' +
    '<p class="mt-3 text-sm" style="color:#5A4A62">Sign in to your account</p>' +
    '</div>' +
    '<form id="login-form" class="space-y-4">' +
    '<div>' +
    '<label class="mb-1 block text-xs font-medium" style="color:#5A4A62">Username or Email</label>' +
    '<div class="relative">' +
    icon('mail', 15, 'absolute left-3 top-1/2 -translate-y-1/2 text-[#5A4A62]') +
    '<input id="login-username" type="text" class="w-full rounded-lg border border-[#E8D4DB] py-2.5 pl-9 pr-3 text-sm text-[#2B2B2B] focus:border-[#7B1028] focus:outline-none focus:ring-2 focus:ring-[#C9A24E]/30" placeholder="Enter your username or email" />' +
    '</div>' +
    '</div>' +
    '<div>' +
    '<label class="mb-1 block text-xs font-medium" style="color:#5A4A62">Password</label>' +
    '<div class="relative">' +
    icon('lock', 15, 'absolute left-3 top-1/2 -translate-y-1/2 text-[#5A4A62]') +
    '<input id="login-password" type="password" class="w-full rounded-lg border border-[#E8D4DB] py-2.5 pl-9 pr-9 text-sm text-[#2B2B2B] focus:border-[#7B1028] focus:outline-none focus:ring-2 focus:ring-[#C9A24E]/30" placeholder="Enter your password" />' +
    '<button type="button" id="toggle-password" class="absolute right-3 top-1/2 -translate-y-1/2 text-[#5A4A62]">' + icon('eye', 15) + '</button>' +
    '</div>' +
    '</div>' +
    '<div id="login-error" class="hidden rounded-lg px-3 py-2 text-xs" style="background:#FDF6F8;color:#C13030"></div>' +
    '<div class="flex items-center justify-between text-xs">' +
    '<label class="flex items-center gap-1.5" style="color:#5A4A62"><input id="login-remember" type="checkbox" /> Remember me</label>' +
    '<button type="button" id="forgot-password" class="hover:underline" style="color:#C9A24E">Forgot Password?</button>' +
    '</div>' +
    '<div id="login-info" class="hidden text-xs" style="color:#5A4A62"></div>' +
    '<button type="submit" id="login-submit" class="w-full rounded-lg py-2.5 text-sm font-medium text-white disabled:opacity-60" style="background:#7B1028;hover:background:#8A2346">Log In</button>' +
    '</form>' +
    '<p class="mt-6 text-center text-[11px]" style="color:#5A4A62">Demo login &mdash; Username: <span class="font-medium">admin</span> &middot; Password: <span class="font-medium">admin123</span></p>' +
    '<p class="mt-2 text-center text-[11px]" style="color:#E8D4DB">&copy; 2025 School Clinic Management System. All rights reserved.</p>' +
    '</div>' +
    '<div class="relative hidden md:block overflow-hidden" style="background:linear-gradient(135deg,#7B1028,#3F0D1D)">' +
    '<img src="https://images.pexels.com/photos/33812025/pexels-photo-33812025.jpeg?auto=compress&cs=tinysrgb&w=900&h=1100&fit=crop" alt="Modern clinic reception" class="h-full w-full object-cover" loading="lazy" />' +
    '<div class="absolute inset-0" style="background:linear-gradient(180deg,rgba(123,16,40,0.15),rgba(63,13,29,0.35))"></div>' +
    '</div>' +
    '</div>' +
    '</div>';
}

function renderLoggedOutScreen() {
  return '<div class="flex w-full min-h-screen items-center justify-center p-4" style="background:#F8F7FA">' +
    '<div class="w-full max-w-sm rounded-2xl bg-white p-8 text-center shadow-xl">' +
    '<div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center overflow-hidden rounded-2xl">' +
    '<img src="https://images.pexels.com/photos/33812025/pexels-photo-33812025.jpeg?auto=compress&cs=tinysrgb&w=120&h=120&fit=crop" alt="Clinic reception" class="h-full w-full object-cover" loading="lazy" />' +
    '</div>' +
    '<div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center overflow-hidden rounded-full">' +
    '<img src="https://images.pexels.com/photos/16571732/pexels-photo-16571732.jpeg?auto=compress&cs=tinysrgb&w=120&h=120&fit=crop" alt="Medical consultation room" class="h-full w-full object-cover" loading="lazy" />' +
    '</div>' +
    '<h1 class="font-serif-heading mb-1 text-lg font-semibold" style="color:#2B2B2B">You have been logged out</h1>' +
    '<p class="mb-6 text-sm" style="color:#5A4A62">Thank you for using the School Clinic Management System.</p>' +
    '<button id="login-again-btn" class="w-full rounded-lg py-2.5 text-sm font-medium text-white" style="background:#7B1028;hover:background:#8A2346">Log In Again</button>' +
    '<p class="mt-6 text-[11px]" style="color:#E8D4DB">&copy; 2025 School Clinic Management System. All rights reserved.</p>' +
    '</div>' +
    '</div>';
}

var sidebarGroups = [
  { title: 'Overview', items: [{ key: 'dashboard', label: 'Dashboard', icon: 'layout-dashboard' }] },
  { title: 'Patient Services', items: [
    { key: 'students', label: 'Student Medical Records', icon: 'graduation-cap' },
    { key: 'medicalRecords', label: 'Medical Records', icon: 'file-text' },
    { key: 'medicalHistory', label: 'Medical History', icon: 'file-text' },
    { key: 'visits', label: 'Clinic Visit & Consultation', icon: 'stethoscope' },
    { key: 'appointments', label: 'Appointment Scheduling', icon: 'calendar-check' },
  ]},
  { title: 'Clinical Management', items: [
    { key: 'medicine', label: 'Medicine Inventory', icon: 'pill' },
    { key: 'incidents', label: 'Incident & Emergency', icon: 'alert-triangle' },
  ]},
  { title: 'Health Services', items: [
    { key: 'staff', label: 'Faculty & Staff Health', icon: 'users' },
    { key: 'programs', label: 'Health Programs', icon: 'activity' },
    { key: 'clearance', label: 'Health Clearance', icon: 'badge-check' },
  ]},
  { title: 'Administration', items: [
    { key: 'reports', label: 'Reporting & Compliance', icon: 'clipboard-list' },
    { key: 'users', label: 'User Access Control', icon: 'shield' },
  ]},
];

function renderSidebar() {
  var collapsed = state.sidebarCollapsed;
  var asideWidth = collapsed ? 'w-16' : 'w-64';
  var userName = state.currentUser ? (state.currentUser.fullName || state.currentUser.username || '') : '';
  var userInitial = userName ? userName.slice(0, 1).toUpperCase() : 'U';
  var userRole = state.currentUser ? (state.currentUser.role || 'Staff') : '';

  var html = '<aside class="hidden shrink-0 flex-col md:flex relative" style="background:linear-gradient(135deg,#3F0D1D,#5C1730);border-right:1px solid rgba(201,162,78,0.15);transition:width 0.3s ease;width:' + (collapsed ? '64px' : '260px') + '">' +
    /* Logo */
    '<div class="flex items-center h-16 px-4 shrink-0" style="border-bottom:1px solid rgba(201,162,78,0.15)">' +
    '<div class="flex items-center gap-3 min-w-0">' +
    '<div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0" style="background:linear-gradient(135deg,rgba(255,255,255,0.15),rgba(255,255,255,0.05))">' +
    logoIcon(18, 'text-[#C9A227]') +
    '</div>' +
    (collapsed ? '' :
    '<div class="min-w-0 overflow-hidden">' +
    '<p class="text-sm font-semibold leading-tight whitespace-nowrap font-serif-heading text-white">School Clinic</p>' +
    '<p class="text-[10px] whitespace-nowrap text-[#C9A227]">Management System</p>' +
    '</div>') +
    '</div>' +
    '</div>' +
    /* Navigation */
    '<nav class="flex-1 overflow-x-hidden py-3">';
  sidebarGroups.forEach(function(group) {
    var allowedItems = group.items.filter(function(item) { return canAccessView(item.key); });
    if (allowedItems.length === 0) return;
    html += '<div class="mb-2">';
    if (!collapsed) {
      html += '<p class="px-4 mb-1 text-[10px] uppercase tracking-widest" style="color:rgba(255,255,255,0.4)">' + esc(group.title) + '</p>';
    }
    allowedItems.forEach(function(item) {
      var active = state.currentView === item.key;
      html += '<button data-nav="' + item.key + '" title="' + (collapsed ? esc(item.label) : '') + '" class="w-full flex items-center gap-3 px-4 py-2.5 relative transition-all duration-150" style="' +
        'color:' + (active ? '#FFFFFF' : 'rgba(255,255,255,0.65)') + ';' +
        'background:' + (active ? 'rgba(255,255,255,0.1)' : 'transparent') + '" ' +
        'onmouseenter="if(!this.classList.contains(\'active\')){this.style.color=\'#FFFFFF\';this.style.background=\'rgba(255,255,255,0.06)\'}" ' +
        'onmouseleave="if(!this.classList.contains(\'active\')){this.style.color=\'rgba(255,255,255,0.65)\';this.style.background=\'transparent\'}">' +
        (active ? '<div class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-r" style="background:#C9A227"></div>' : '') +
        icon(item.icon, 16, 'shrink-0' + (active ? ' text-[#C9A227]' : '')) +
        (collapsed ? '' : '<span class="text-sm text-left whitespace-nowrap overflow-hidden text-ellipsis">' + esc(item.label) + '</span>') +
        '</button>';
    });
    html += '</div>';
  });
  html += '</nav>' +
    /* User profile & logout */
    '<div class="shrink-0 p-3" style="border-top:1px solid rgba(201,162,78,0.15)">' +
    '<div class="flex items-center gap-3">' +
    '<div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold shrink-0" style="background:linear-gradient(135deg,rgba(255,255,255,0.2),rgba(255,255,255,0.08));color:#C9A227">' + esc(userInitial) + '</div>' +
    (collapsed ? '' :
    '<div class="flex-1 min-w-0 overflow-hidden">' +
    '<p class="text-xs font-medium whitespace-nowrap overflow-hidden text-ellipsis text-white">' + esc(userName) + '</p>' +
    '<p class="text-[10px] whitespace-nowrap" style="color:rgba(255,255,255,0.5)">' + esc(userRole) + '</p>' +
    '</div>' +
    '<button data-action="logout" class="shrink-0 transition-colors" style="color:rgba(255,255,255,0.4)" title="Logout" ' +
    'onmouseenter="this.style.color=\'#FFFFFF\'" onmouseleave="this.style.color=\'rgba(255,255,255,0.4)\'">' +
    icon('log-out', 14) + '</button>') +
    '</div>' +
    '</div>' +
    /* Collapse toggle */
    '<button id="sidebar-toggle" class="absolute top-1/2 -translate-y-1/2 -right-3 w-6 h-6 rounded-full flex items-center justify-center z-30 transition-all duration-150" style="background:#FFFFFF;border:1px solid rgba(201,162,78,0.3);color:#7A1F3D" ' +
    'onmouseenter="this.style.background=\'#F0E6E8\'" onmouseleave="this.style.background=\'#FFFFFF\'">' +
    icon(collapsed ? 'chevron-right' : 'chevron-left', 12) +
    '</button>' +
    '</aside>';
  return html;
}

var MODULE_LABELS = {};
NAV_ITEMS.forEach(function(n) { MODULE_LABELS[n.key] = n.label; });
MODULE_LABELS.dashboard = 'Dashboard';

function renderTopbar() {
  var name = state.currentUser ? (state.currentUser.fullName || state.currentUser.username || 'U') : 'U';
  var initial = name.slice(0, 1).toUpperCase();
  var title = MODULE_LABELS[state.currentView] || 'Dashboard';
  var now = new Date();
  var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
  var dateStr = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });

  return '<header class="h-14 flex items-center justify-between px-5 shrink-0 relative z-10" style="background:#FFFFFF;border-bottom:1px solid #E8D4DB">' +
    /* Left: Toggle + Breadcrumb */
    '<div class="flex items-center gap-3 min-w-0">' +
    '<button id="sidebar-toggle-mobile" class="flex md:hidden w-8 h-8 rounded-lg items-center justify-center transition-colors shrink-0" style="color:#7A6880" ' +
    'onmouseenter="this.style.color=\'#7B1028\'" onmouseleave="this.style.color=\'#7A6880\'">' +
    icon('menu', 16) + '</button>' +
    '<div class="flex items-center gap-1.5 text-xs min-w-0" style="color:#7A7A7A">' +
    '<span>School Clinic</span>' +
    icon('chevron-right', 10, 'shrink-0') +
    '<span class="font-medium truncate" style="color:#7A1F3D">' + esc(title) + '</span>' +
    '</div>' +
    '</div>' +
    /* Right: Time, Notifications, User */
    '<div class="flex items-center gap-3 shrink-0">' +
    '<div class="text-right hidden sm:block">' +
    '<p class="text-xs font-medium font-mono-data" style="color:#7A1F3D">' + timeStr + '</p>' +
    '<p class="text-[10px]" style="color:#5A4A62">' + dateStr + '</p>' +
    '</div>' +
    '<div class="relative">' +
    '<button id="notif-btn" class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors relative" style="color:#7A6880" ' +
    'onmouseenter="this.style.color=\'#7B1028\'" onmouseleave="this.style.color=\'#7A6880\'">' +
    icon('bell', 16) +
    '<span class="absolute top-1 right-1 w-1.5 h-1.5 rounded-full" style="background:#C9A24E"></span>' +
    '</button>' +
    '<div id="notif-dropdown" class="hidden absolute right-0 top-10 w-80 rounded-xl p-1 z-50 shadow-2xl" style="background:#FFFFFF;border:1px solid #E8D4DB">' +
    '<div class="flex items-center justify-between px-3 py-2">' +
    '<span class="text-xs font-semibold" style="color:#2B2B2B">Notifications</span>' +
    '<button id="notif-close" style="color:#5A4A62">' + icon('x', 14) + '</button>' +
    '</div>' +
    '<div id="notif-list" class="max-h-64 overflow-y-auto"><p class="px-3 py-4 text-xs text-center" style="color:#5A4A62">Loading&hellip;</p></div>' +
    '</div>' +
    '</div>' +
    '<div class="flex items-center gap-2 cursor-pointer">' +
    '<div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-semibold" style="background:linear-gradient(135deg,#7B1028,#B01838);color:#F0ECF2">' + esc(initial) + '</div>' +
    '<span class="text-xs font-medium hidden sm:block" style="color:#5A4A62">' + esc(name) + '</span>' +
    '</div>' +
    '</div>' +
    '</header>';
}

function renderMobileNav() {
  var html = '<div class="fixed inset-x-0 bottom-0 z-30 flex justify-around border-t py-2 md:hidden" style="border-color:#E8D4DB;background:#FDF6F8">';
  NAV_ITEMS.filter(function(item) { return canAccessView(item.key); }).slice(0, 5).forEach(function(item) {
    var active = state.currentView === item.key;
    html += '<button data-nav="' + item.key + '" class="p-2 ' + (active ? 'text-[#7B1028]' : 'text-[#5A4A62]') + '">' +
      icon(ICON_MAP[item.key], 18) + '</button>';
  });
  html += '</div>';
  return html;
}

/* ============================== DASHBOARD ============================== */

function renderDashboard() {
  if (state.loadingData) {
    return renderLoader('Loading dashboard data…', 32);
  }

  var dashData = state.dashData || {
    stats: { todaysVisits: 0, pendingAppointments: 0, lowStock: 0, clearances: 0, openIncidents: 0 },
    chartData: [],
    recentActivity: [],
    upcomingAppointments: [],
    programUpdates: [],
    visits: [],
    students: [],
  };
  var stats = dashData.stats;
  var visits = dashData.visits || [];
  var students = dashData.students || [];
  var now = new Date();
  var greeting = now.getHours() < 12 ? 'morning' : now.getHours() < 18 ? 'afternoon' : 'evening';
  var dateStr = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  var userName = state.currentUser ? (state.currentUser.fullName || state.currentUser.username) : '';

  var html = '<div class="space-y-6">';

  /* ===== WELCOME BANNER ===== */
  html += '<div class="relative overflow-hidden rounded-2xl p-6" style="background:linear-gradient(135deg,#6D1B3B,#8A2346);border:1px solid rgba(201,162,78,0.3)">' +
    '<div class="absolute top-0 right-0 w-64 h-64 opacity-5 -translate-y-16 translate-x-16">' +
    logoIcon(64, 'text-[#C9A24E]') +
    '</div>' +
    '<div class="relative z-10 flex items-start justify-between">' +
    '<div>' +
    '<p class="text-xs mb-1 font-mono-data" style="color:rgba(255,255,255,0.85)">' + esc(dateStr) + '</p>' +
    '<h1 class="font-serif-heading text-xl font-semibold mb-1 text-white">Good ' + greeting + ', ' + esc(userName) + '!</h1>' +
    '<p class="text-xs" style="color:rgba(255,255,255,0.7)">Here\'s your clinic overview for today. Stay on top of all health services.</p>' +
    '</div>' +
    '<div class="hidden md:flex items-center gap-4">' +
    '<div class="text-right">' +
    '<p class="text-[10px]" style="color:rgba(255,255,255,0.6)">School Year</p>' +
    '<p class="text-xs font-semibold" style="color:rgba(255,255,255,0.95)">2024 \u2013 2025</p>' +
    '</div>' +
    '<div class="h-8 w-px" style="background:rgba(255,255,255,0.15)"></div>' +
    '<div class="text-right">' +
    '<p class="text-[10px]" style="color:rgba(255,255,255,0.6)">Total Staff</p>' +
    '<p class="text-xs font-semibold" style="color:rgba(255,255,255,0.95)">' + students.length + '</p>' +
    '</div>' +
    '</div>' +
    '</div>' +
    '<div class="mt-4 h-px" style="background:linear-gradient(90deg,rgba(201,162,78,0.4),transparent)"></div>' +
    '</div>';

  /* ===== 6 KPI CARDS ===== */
  var todayVisitsActual = Number(stats.todaysVisits) || 0;

  var kpiCards = [
    { label: 'Enrolled Students', value: students.length || 0, change: '+0', trend: 'up', icon: 'graduation-cap', color: '#2A6B9B', bg: 'rgba(42,107,155,0.12)', view: 'students' },
    { label: "Today's Visits", value: todayVisitsActual, change: '+0', trend: 'up', icon: 'stethoscope', color: '#2A8B4A', bg: 'rgba(42,139,74,0.12)', view: 'visits' },
    { label: 'Low Stock Alerts', value: stats.lowStock || 0, change: '+0', trend: 'up', icon: 'pill', color: '#C9A24E', bg: 'rgba(201,162,78,0.12)', view: 'medicine' },
    { label: 'Pending Appts.', value: stats.pendingAppointments || 0, change: '+0', trend: 'up', icon: 'calendar-check', color: '#8B3A8B', bg: 'rgba(139,58,139,0.12)', view: 'appointments' },
    { label: 'Open Incidents', value: stats.openIncidents || 0, change: '0', trend: 'same', icon: 'alert-triangle', color: '#C13030', bg: 'rgba(193,48,48,0.12)', view: 'incidents' },
    { label: 'Clearances', value: stats.clearances || 0, change: '+0', trend: 'up', icon: 'badge-check', color: '#2A8B7A', bg: 'rgba(42,139,122,0.12)', view: 'clearance' },
  ];

  html += '<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">';
  kpiCards.forEach(function(k) {
    var trendIcon = k.trend === 'up' ? 'trending-up' : k.trend === 'down' ? 'trending-down' : 'minus';
    var trendColor = k.trend === 'up' ? '#C9A24E' : k.trend === 'down' ? '#5A9BD4' : '#5A4A62';
    html += '<button data-nav="' + k.view + '" class="rounded-xl p-4 text-left transition-all duration-200 group" style="background:#FDF6F8;border:1px solid #E8D4DB;box-shadow:0 1px 3px rgba(0,0,0,0.04)" ' +
      'onmouseenter="this.style.border=\'1px solid #C9A227\';this.style.boxShadow=\'0 4px 12px rgba(0,0,0,0.08)\';this.style.transform=\'translateY(-2px)\'" ' +
      'onmouseleave="this.style.border=\'1px solid #E8D4DB\';this.style.boxShadow=\'0 1px 3px rgba(0,0,0,0.04)\';this.style.transform=\'translateY(0)\'">' +
      '<div class="flex items-start justify-between mb-3">' +
      '<div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:' + k.bg + '">' +
      icon(k.icon, 16, 'text-[' + k.color + ']') + '</div>' +
      '<div class="flex items-center gap-1">' +
      icon(trendIcon, 10, 'text-[' + trendColor + ']') +
      '<span class="text-[10px] font-mono-data" style="color:' + trendColor + '">' + k.change + '</span>' +
      '</div>' +
      '</div>' +
      '<p class="font-mono-data text-xl font-semibold mb-0.5" style="color:#2B2B2B">' + k.value + '</p>' +
      '<p class="text-[10px] leading-tight" style="color:#7A7A7A">' + k.label + '</p>' +
      '</button>';
  });
  html += '</div>';

  /* ===== CHARTS ROW ===== */
  html += '<div class="grid grid-cols-1 lg:grid-cols-5 gap-4">' +
    '<div class="lg:col-span-3 rounded-xl p-5" style="background:#FDF6F8;border:1px solid #E8D4DB;box-shadow:0 1px 3px rgba(0,0,0,0.04)">' +
    '<div class="flex items-center justify-between mb-5">' +
    '<div>' +
    '<h3 class="text-sm font-semibold" style="color:#2B2B2B">Weekly Clinic Visits</h3>' +
    '<p class="text-xs mt-0.5" style="color:#7A7A7A">Visits recorded this week</p>' +
    '</div>' +
    '<span class="text-xs px-2 py-1 rounded-full" style="background:#7A1F3D;color:#FFFFFF">This Week</span>' +
    '</div>' +
    '<div style="width:100%;height:180px"><canvas id="visits-bar-chart"></canvas></div>' +
    '</div>' +
    '<div class="lg:col-span-2 rounded-xl p-5" style="background:#FDF6F8;border:1px solid #E8D4DB;box-shadow:0 1px 3px rgba(0,0,0,0.04)">' +
    '<div class="mb-4">' +
    '<h3 class="text-sm font-semibold" style="color:#2B2B2B">Consultation Types</h3>' +
    '<p class="text-xs mt-0.5" style="color:#7A7A7A">Distribution this month</p>' +
    '</div>' +
    '<div style="width:100%;height:140px"><canvas id="visits-doughnut-chart"></canvas></div>' +
    '<div id="consultation-legend" class="space-y-1.5 mt-2"></div>' +
    '</div>' +
    '</div>';

  /* ===== RECENT VISITS + QUICK ACCESS ===== */
  var recentVisitsList = visits.slice(-5).reverse();

  html += '<div class="grid grid-cols-1 lg:grid-cols-5 gap-4">' +
    '<div class="lg:col-span-3 rounded-xl overflow-hidden" style="background:#FDF6F8;border:1px solid #E8D4DB;box-shadow:0 1px 3px rgba(0,0,0,0.04)">' +
    '<div class="flex items-center justify-between px-5 py-4" style="border-bottom:1px solid #E8D4DB">' +
    '<h3 class="text-sm font-semibold" style="color:#2B2B2B">Recent Clinic Visits</h3>' +
    '<button data-nav="visits" class="flex items-center gap-1 text-xs transition-colors" style="color:#7A1F3D">View All ' + icon('arrow-right', 12) + '</button>' +
    '</div>' +
    '<table class="w-full">' +
    '<thead>' +
    '<tr style="border-bottom:1px solid #E8D4DB">' +
    '<th class="text-left px-5 py-3 text-[10px] uppercase tracking-wider" style="color:#7A7A7A">Patient</th>' +
    '<th class="text-left px-5 py-3 text-[10px] uppercase tracking-wider" style="color:#7A7A7A">Grade / Dept.</th>' +
    '<th class="text-left px-5 py-3 text-[10px] uppercase tracking-wider" style="color:#7A7A7A">Chief Complaint</th>' +
    '<th class="text-left px-5 py-3 text-[10px] uppercase tracking-wider" style="color:#7A7A7A">Time</th>' +
    '<th class="text-left px-5 py-3 text-[10px] uppercase tracking-wider" style="color:#7A7A7A">Status</th>' +
    '</tr>' +
    '</thead>' +
    '<tbody>';

  if (recentVisitsList.length === 0) {
    html += '<tr><td colspan="5" class="px-5 py-10 text-center text-xs" style="color:#7A7A7A">No visits recorded yet.</td></tr>';
  } else {
    recentVisitsList.forEach(function(v, i) {
      var borderStyle = i < recentVisitsList.length - 1 ? '1px solid #F0E6E8' : 'none';
      var status = v.status || v.disposition || (v.diagnosis ? 'Treated' : 'Pending');
      var statusBg = status === 'Treated' || status === 'Treated & Discharged' ? 'rgba(42,139,74,0.15)' :
        status === 'Referred' || status === 'Referred to Doctor' ? 'rgba(201,162,78,0.15)' :
        status === 'Discharged' || status === 'Sent Home' ? 'rgba(42,107,155,0.15)' :
        'rgba(201,162,78,0.15)';
      var statusColor = status === 'Treated' || status === 'Treated & Discharged' ? '#2A8B4A' :
        status === 'Referred' || status === 'Referred to Doctor' ? '#C9A24E' :
        status === 'Discharged' || status === 'Sent Home' ? '#2A6B9B' : '#C9A24E';
      var gradeDept = v.grade || v.course || v.patientType || v.patient_type || '\u2014';
      var complaint = v.complaint || v.chiefComplaint || v.chief_complaint || v.diagnosis || '\u2014';
      var timeDisplay = v.time ? v.time.slice(0, 5) : '\u2014';

      html += '<tr style="border-bottom:' + borderStyle + '" onmouseenter="this.style.background=\'rgba(201,162,39,0.04)\'" onmouseleave="this.style.background=\'transparent\'">' +
        '<td class="px-5 py-3">' +
        '<p class="text-xs font-medium" style="color:#2B2B2B">' + esc(v.patientName || v.patient_name || '') + '</p>' +
        '<p class="text-[10px] font-mono-data" style="color:#7A7A7A">' + esc(v.id || '') + '</p>' +
        '</td>' +
        '<td class="px-5 py-3 text-xs" style="color:#7A7A7A">' + esc(gradeDept) + '</td>' +
        '<td class="px-5 py-3 text-xs" style="color:#555555">' + esc(complaint) + '</td>' +
        '<td class="px-5 py-3 text-xs font-mono-data" style="color:#7A7A7A">' + esc(timeDisplay) + '</td>' +
        '<td class="px-5 py-3">' +
        '<span class="text-[10px] px-2 py-0.5 rounded-full" style="background:' + statusBg + ';color:' + statusColor + '">' + esc(status) + '</span>' +
        '</td>' +
        '</tr>';
    });
  }

  html += '</tbody></table></div>';

  /* ===== QUICK ACCESS ===== */
  var quickItems = [
    { key: 'students', icon: 'graduation-cap', label: 'Student Medical Records', desc: (students.length || '0') + ' students enrolled', gradient: 'linear-gradient(135deg,#1B3A6B,#0D2040)', accent: '#5A9BD4' },
    { key: 'visits', icon: 'stethoscope', label: 'Clinic Visit & Consultation', desc: (todayVisitsActual || '0') + ' visits today', gradient: 'linear-gradient(135deg,#1B6B3A,#0D4020)', accent: '#5AD490' },
    { key: 'medicine', icon: 'pill', label: 'Medicine Inventory', desc: (stats.lowStock || '0') + ' low stock items', gradient: 'linear-gradient(135deg,#6B5A1B,#402E0D)', accent: '#D4B85A' },
    { key: 'appointments', icon: 'calendar-check', label: 'Appointment Scheduling', desc: (stats.pendingAppointments || '0') + ' pending', gradient: 'linear-gradient(135deg,#4A1B6B,#280D40)', accent: '#A45AD4' },
    { key: 'incidents', icon: 'alert-triangle', label: 'Incident & Emergency', desc: (stats.openIncidents || '0') + ' open cases', gradient: 'linear-gradient(135deg,#7B1028,#400813)', accent: '#D45A5A' },
    { key: 'staff', icon: 'users', label: 'Faculty & Staff Health', desc: 'Staff health records', gradient: 'linear-gradient(135deg,#1B5A6B,#0D3040)', accent: '#5ABCD4' },
  ];

  html += '<div class="lg:col-span-2 space-y-3">' +
    '<h3 class="text-sm font-semibold" style="color:#2B2B2B">Quick Access</h3>' +
    '<div class="grid grid-cols-1 gap-2">';
  quickItems.filter(function(q) { return canAccessView(q.key); }).forEach(function(q) {
    html += '<button data-nav="' + q.key + '" class="flex items-center gap-3 p-3 rounded-xl text-left transition-all duration-150 group" style="background:#FDF6F8;border:1px solid #E8D4DB;box-shadow:0 1px 3px rgba(0,0,0,0.04)" ' +
      'onmouseenter="this.style.border=\'1px solid #C9A227\';this.style.background=\'#FFFFFF\';this.style.boxShadow=\'0 4px 12px rgba(0,0,0,0.06)\'" ' +
      'onmouseleave="this.style.border=\'1px solid #E8D4DB\';this.style.background=\'#FDF6F8\';this.style.boxShadow=\'0 1px 3px rgba(0,0,0,0.04)\'">' +
      '<div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background:' + q.gradient + '">' +
      icon(q.icon, 16, 'text-[' + q.accent + ']') + '</div>' +
      '<div class="min-w-0 flex-1">' +
      '<p class="text-xs font-medium truncate" style="color:#2B2B2B">' + esc(q.label) + '</p>' +
      '<p class="text-[10px] truncate" style="color:#7A7A7A">' + esc(q.desc) + '</p>' +
      '</div>' +
      icon('arrow-right', 14, 'shrink-0 text-[#C9A227] opacity-0 group-hover:opacity-100 transition-opacity') +
      '</button>';
  });
  html += '</div></div></div>';

  /* ===== ALL MODULES ===== */
  var allMods = [
    { key: 'students', icon: 'graduation-cap', label: 'Student Medical Records', desc: (students.length || '0') + ' students', gradient: 'linear-gradient(135deg,#1B3A6B,#0D2040)', accent: '#5A9BD4' },
    { key: 'visits', icon: 'stethoscope', label: 'Clinic Visit & Consultation', desc: (todayVisitsActual || '0') + ' visits today', gradient: 'linear-gradient(135deg,#1B6B3A,#0D4020)', accent: '#5AD490' },
    { key: 'medicine', icon: 'pill', label: 'Medicine Inventory', desc: (stats.lowStock || '0') + ' low stock', gradient: 'linear-gradient(135deg,#6B5A1B,#402E0D)', accent: '#D4B85A' },
    { key: 'appointments', icon: 'calendar-check', label: 'Appointment Scheduling', desc: (stats.pendingAppointments || '0') + ' pending', gradient: 'linear-gradient(135deg,#4A1B6B,#280D40)', accent: '#A45AD4' },
    { key: 'incidents', icon: 'alert-triangle', label: 'Incident & Emergency', desc: (stats.openIncidents || '0') + ' open cases', gradient: 'linear-gradient(135deg,#7B1028,#400813)', accent: '#D45A5A' },
    { key: 'staff', icon: 'users', label: 'Faculty & Staff Health', desc: 'Staff records', gradient: 'linear-gradient(135deg,#1B5A6B,#0D3040)', accent: '#5ABCD4' },
    { key: 'programs', icon: 'activity', label: 'School Health Programs', desc: 'Program monitoring', gradient: 'linear-gradient(135deg,#2A1B6B,#160D40)', accent: '#8A5AD4' },
    { key: 'clearance', icon: 'badge-check', label: 'Health Clearance', desc: (stats.clearances || '0') + ' generated', gradient: 'linear-gradient(135deg,#1B6B5A,#0D4030)', accent: '#5AD4C0' },
    { key: 'reports', icon: 'clipboard-list', label: 'Reporting & Compliance', desc: 'Monthly reports', gradient: 'linear-gradient(135deg,#3A3A1B,#20200D)', accent: '#D4D45A' },
    { key: 'users', icon: 'shield', label: 'User Access Control', desc: 'User management', gradient: 'linear-gradient(135deg,#6B1B3A,#400D20)', accent: '#D45A9B' },
  ];

  html += '<div>' +
    '<h3 class="text-sm font-semibold mb-3" style="color:#2B2B2B">All Modules</h3>' +
    '<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">';
  allMods.filter(function(m) { return canAccessView(m.key); }).forEach(function(m) {
    html += '<button data-nav="' + m.key + '" class="rounded-xl p-4 text-left transition-all duration-200 group" style="background:' + m.gradient + ';border:1px solid rgba(255,255,255,0.05)" ' +
      'onmouseenter="this.style.transform=\'translateY(-3px)\';this.style.boxShadow=\'0 8px 24px rgba(0,0,0,0.4)\'" ' +
      'onmouseleave="this.style.transform=\'translateY(0)\';this.style.boxShadow=\'none\'">' +
      icon(m.icon, 20, 'mb-3 text-[' + m.accent + ']') +
      '<p class="text-xs font-semibold leading-tight mb-1" style="color:#F0ECF2">' + esc(m.label) + '</p>' +
      '<p class="text-[10px]" style="color:rgba(240,236,242,0.5)">' + esc(m.desc) + '</p>' +
      '</button>';
  });
  html += '</div></div></div>';

  return html;
}

/* ============================== REPORTS ============================== */
function renderReports() {
  var html = '<div>' +
    '<div class="mb-6 flex items-center justify-between">' +
    '<div>' +
    '<h2 class="font-serif-heading text-lg font-semibold" style="color:#2B2B2B">Reporting and Compliance</h2>' +
    '<p class="text-xs" style="color:#5A4A62">System-wide summary and exportable records</p>' +
    '</div>' +
    '<button id="refresh-reports" class="flex items-center gap-1.5 rounded-lg border px-3 py-2 text-sm hover:bg-[#FDF6F8]" style="border-color:#E8D4DB;color:#5A4A62">' + icon('refresh-cw', 14) + ' Refresh</button>' +
    '</div>';

  if (state.loadingData) {
    html += renderLoader('Loading reports…', 32) + '</div>';
    return html;
  }

  var reports = state.reportsData || { records: {}, summary: { lowStock: 0, openIncidents: 0, expiredClearance: 0, pendingAppointments: 0 } };
  var summary = reports.summary;

  html += '<div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">' +
    '<div class="rounded-2xl border p-4 shadow-sm" style="border-color:#E8D4DB;background:#FDF6F8">' +
    '<p class="text-xs" style="color:#5A4A62">Low Stock Medicines</p>' +
    '<p class="font-mono-data mt-1 text-2xl font-semibold" style="color:#C9A24E">' + summary.lowStock + '</p></div>' +
    '<div class="rounded-2xl border p-4 shadow-sm" style="border-color:#E8D4DB;background:#FDF6F8">' +
    '<p class="text-xs" style="color:#5A4A62">Open Incidents</p>' +
    '<p class="font-mono-data mt-1 text-2xl font-semibold" style="color:#C13030">' + summary.openIncidents + '</p></div>' +
    '<div class="rounded-2xl border p-4 shadow-sm" style="border-color:#E8D4DB;background:#FDF6F8">' +
    '<p class="text-xs" style="color:#5A4A62">Expired Clearances</p>' +
    '<p class="font-mono-data mt-1 text-2xl font-semibold" style="color:#5A4A62">' + summary.expiredClearance + '</p></div>' +
    '<div class="rounded-2xl border p-4 shadow-sm" style="border-color:#E8D4DB;background:#FDF6F8">' +
    '<p class="text-xs" style="color:#5A4A62">Pending Appointments</p>' +
    '<p class="font-mono-data mt-1 text-2xl font-semibold" style="color:#7B1028">' + summary.pendingAppointments + '</p></div>' +
    '</div>';

  var rows = [
    { label: 'Student Medical Records', key: 'students', filename: 'student_medical_records.csv' },
    { label: 'Clinic Visits Logged', key: 'visits', filename: 'clinic_visits.csv' },
    { label: 'Medicines in Inventory', key: 'medicine', filename: 'medicine_inventory.csv' },
    { label: 'Appointments Scheduled', key: 'appointments', filename: 'appointments.csv' },
    { label: 'Incident Reports', key: 'incidents', filename: 'incident_reports.csv' },
    { label: 'Faculty & Staff Records', key: 'staff', filename: 'staff_health_records.csv' },
    { label: 'Health Programs', key: 'programs', filename: 'health_programs.csv' },
    { label: 'Health Clearances', key: 'clearance', filename: 'health_clearances.csv' },
  ];

  html += '<div class="overflow-hidden rounded-2xl border shadow-sm" style="border-color:#E8D4DB;background:#FDF6F8">' +
    '<table class="w-full text-left text-sm">' +
    '<thead><tr class="border-b text-xs uppercase tracking-wide" style="border-color:#E8D4DB;background:#FDF6F8;color:#7A1F3D">' +
    '<th class="px-4 py-3 font-medium">Dataset</th><th class="px-4 py-3 font-medium">Total Records</th><th class="px-4 py-3 font-medium text-right">Export</th>' +
    '</tr></thead><tbody>';
  rows.forEach(function(r) {
    var items = reports.records[r.key] || [];
    html += '<tr class="border-b last:border-0" style="border-color:#E8D4DB">' +
      '<td class="px-4 py-3" style="color:#2B2B2B">' + esc(r.label) + '</td>' +
      '<td class="px-4 py-3" style="color:#5A4A62">' + items.length + '</td>' +
      '<td class="px-4 py-3 text-right">' +
      '<button data-export="' + r.key + '" data-filename="' + esc(r.filename) + '" class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium hover:bg-[#FDF6F8] disabled:opacity-40" style="border-color:#E8D4DB;color:#5A4A62"' + (items.length === 0 ? ' disabled' : '') + '>' +
      icon('download', 13) + ' CSV</button></td></tr>';
  });
  html += '</tbody></table></div></div>';

  return html;
}

/* ============================== APPOINTMENTS MODULE ============================== */
function renderAppointmentsModule() {
  var key = 'appointments';
  var config = MODULES[key];
  var ms = getModuleState(key);
  var items = ms.items || [];
  // lazy-load groups for this module
  if (typeof ms.groups === 'undefined') {
    ms.groups = null; // loading
    API.listRecordFolders().then(function(list) {
      ms.groups = list || [];
      if (!ms.groupSelected && ms.groups.length) ms.groupSelected = ms.groups[0].name;
      renderMainContent();
    }).catch(function() { ms.groups = []; renderMainContent(); });
  }
  var pendingCount = items.filter(function(i) { return i.status === 'Pending'; }).length;
  var confirmedCount = items.filter(function(i) { return i.status === 'Confirmed'; }).length;
  var completedCount = items.filter(function(i) { return i.status === 'Completed'; }).length;
  var cancelledCount = items.filter(function(i) { return i.status === 'Cancelled' || i.status === 'No Show'; }).length;

  var filtered = items;
  var s = (ms.search || '').toLowerCase();
  if (s) {
    filtered = filtered.filter(function(it) {
      return config.searchKeys.some(function(k) { return String(it[k] || '').toLowerCase().indexOf(s) !== -1; });
    });
  }
  var sf = ms.statusFilter || 'All';
  if (sf !== 'All') filtered = filtered.filter(function(it) { return it.status === sf; });
  var df = ms.dateFilter || '';
  if (df) filtered = filtered.filter(function(it) { return it.date === df; });

  var perPage = 8;
  var totalPages = Math.ceil(filtered.length / perPage);
  var pg = Math.max(1, Math.min(ms.page || 1, totalPages || 1));
  var paginated = filtered.slice((pg - 1) * perPage, pg * perPage);

  var html = '<div data-module="appointments">';

  html += '<div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">' +
    '<div class="flex items-center gap-3">' +
    '<div class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#F0ECF2] text-[#2A8B4A]">' +
    icon('calendar-check', 20) + '</div>' +
    '<div><h2 class="font-serif-heading text-lg font-semibold" style="color:#2B2B2B">Appointment Scheduling System</h2>' +
    '<p class="text-xs" style="color:#5A4A62">' + ms.items.length + ' appointment' + (ms.items.length !== 1 ? 's' : '') + ' on file</p></div>' +
    '</div>' +
    '<div class="flex gap-2">' +
    '<button id="export-appointments" class="flex items-center gap-1.5 rounded-lg border px-3 py-2 text-sm hover:bg-[#FDF6F8]" style="border-color:#E8D4DB;color:#5A4A62">' + icon('download', 15) + ' Export</button>' +
    '<button data-action="add" data-module="appointments" class="flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium text-white bg-[#2A8B4A] hover:bg-[#1B6B3A]">' +
    icon('plus', 16) + ' Schedule Appointment</button>' +
    '</div></div>';

  html += '<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">';
  [
    { label: 'Pending', value: pendingCount, color: '#C9A24E', bg: 'rgba(201,162,78,0.12)' },
    { label: 'Confirmed', value: confirmedCount, color: '#2A6B9B', bg: 'rgba(42,107,155,0.12)' },
    { label: 'Completed', value: completedCount, color: '#2A8B4A', bg: 'rgba(42,139,74,0.12)' },
    { label: 'Cancelled / No Show', value: cancelledCount, color: '#C13030', bg: 'rgba(193,48,48,0.12)' },
  ].forEach(function(c) {
    var active = sf === 'Cancelled' && c.label === 'Cancelled / No Show' || sf === c.label;
    html += '<button data-appt-filter="' + (c.label.indexOf('/') !== -1 ? 'Cancelled' : c.label) + '" class="p-4 rounded-xl text-left transition-all duration-150" style="background:' + (active ? '#F0ECF2' : '#FDF6F8') + ';border:1px solid ' + (active ? c.color : '#E8D4DB') + '" ' +
      'onmouseenter="this.style.border=\'1px solid ' + c.color + '\';this.style.background=\'#F0ECF2\'" ' +
      'onmouseleave="this.style.border=\'1px solid ' + (active ? c.color : '#E8D4DB') + '\';this.style.background=\'' + (active ? '#F0ECF2' : '#FDF6F8') + '\'">' +
      '<p class="text-xl font-semibold font-mono-data" style="color:' + c.color + '">' + c.value + '</p>' +
      '<p class="text-[10px] mt-0.5" style="color:#7A7A7A">' + c.label + '</p>' +
      '</button>';
  });
  html += '</div>';

  html += '<div class="flex flex-wrap items-center gap-3 mb-4">' +
    '<div class="relative flex-1 min-w-48">' +
    icon('search', 14, 'absolute left-3 top-1/2 -translate-y-1/2 text-[#5A4A62]') +
    '<input data-search="appointments" type="text" value="' + esc(ms.search) + '" placeholder="Search patient, ID, purpose..." class="w-full rounded-lg border border-[#E8D4DB] py-2 pl-9 pr-3 text-sm text-[#2B2B2B] focus:border-[#7B1028] focus:outline-none focus:ring-2 focus:ring-[#C9A24E]/30" />' +
    '</div>' +
    '<input type="date" data-appt-date-filter value="' + esc(df) + '" class="px-3 py-2 rounded-lg text-xs border border-[#DADADA] text-[#7A7A7A]" style="background:#FFFFFF" />' +
    '<select data-appt-status-filter class="px-3 py-2 rounded-lg text-xs border border-[#DADADA] text-[#7A7A7A]" style="background:#FFFFFF">' +
    ['All', 'Pending', 'Confirmed', 'Completed', 'Cancelled', 'No Show'].map(function(o) {
      return '<option value="' + o + '"' + (sf === o ? ' selected' : '') + '>' + o + '</option>';
    }).join('') +
    '</select>' +
    '<span class="text-xs" style="color:#7A7A7A">' + filtered.length + ' appointment' + (filtered.length !== 1 ? 's' : '') + '</span>' +
    '</div>';

  html += '<div class="overflow-hidden rounded-2xl border shadow-sm" style="border-color:#E8D4DB;background:#FDF6F8">' +
    '<div class="overflow-x-auto"><table class="w-full text-left text-sm">' +
    '<thead><tr class="border-b text-xs uppercase tracking-wide" style="border-color:#E8D4DB;background:#FDF6F8;color:#7A1F3D">' +
    '<th class="whitespace-nowrap px-4 py-3 font-medium">Appt. ID</th>' +
    '<th class="whitespace-nowrap px-4 py-3 font-medium">Patient</th>' +
    '<th class="whitespace-nowrap px-4 py-3 font-medium">Date &amp; Time</th>' +
    '<th class="whitespace-nowrap px-4 py-3 font-medium">Type</th>' +
    '<th class="whitespace-nowrap px-4 py-3 font-medium">Status</th>' +
    '<th class="px-4 py-3 font-medium text-right">Actions</th>' +
    '</tr></thead><tbody>';

  if (ms.loading) {
    html += '<tr><td colspan="6" class="px-4 py-10 text-center">' + renderLoader('Loading appointments…', 32) + '</td></tr>';
  } else if (paginated.length === 0) {
    html += '<tr><td colspan="6" class="px-4 py-10 text-center" style="color:#5A4A62">No appointments found. Click "Schedule Appointment" to create one.</td></tr>';
  } else {
    paginated.forEach(function(a, i) {
      var borderStyle = i < paginated.length - 1 ? '1px solid #F0E6E8' : 'none';
      var badgeCls = badgeColor(a.status);
      html += '<tr style="border-bottom:' + borderStyle + '" onmouseenter="this.style.background=\'rgba(201,162,39,0.04)\'" onmouseleave="this.style.background=\'transparent\'">' +
        '<td class="whitespace-nowrap px-4 py-3 text-[11px] font-mono-data" style="color:#C9A227">' + esc(a.id || '') + '</td>' +
        '<td class="whitespace-nowrap px-4 py-3"><p class="text-xs font-medium" style="color:#2B2B2B">' + esc(a.patientName || '') + '</p><p class="text-[10px]" style="color:#7A7A7A">' + esc(a.patientType || '') + (a.patientType && a.patientType !== a.patientName ? '' : '') + '</p></td>' +
        '<td class="whitespace-nowrap px-4 py-3"><p class="text-xs" style="color:#555555">' + esc(a.date || '') + '</p><p class="text-[10px] font-mono-data" style="color:#7A7A7A">' + esc(a.time || '') + '</p></td>' +
        '<td class="px-4 py-3 text-xs" style="color:#555555;max-width:160px"><p class="truncate">' + esc(a.type || a.purpose || '') + '</p></td>' +
        '<td class="whitespace-nowrap px-4 py-3">' + renderBadge(a.status) + '</td>' +
        '<td class="whitespace-nowrap px-4 py-3 text-right"><div class="flex justify-end gap-1">' +
        (a.status === 'Pending' ? '<button data-status-update="' + esc(a.id) + '-Confirmed" class="w-7 h-7 rounded flex items-center justify-center text-[9px] font-medium transition-colors" style="background:rgba(37,99,235,0.12);color:#2563EB" title="Confirm">' + icon('check', 13) + '</button>' : '') +
        (a.status === 'Pending' || a.status === 'Confirmed' ? '<button data-status-update="' + esc(a.id) + '-Completed" class="w-7 h-7 rounded flex items-center justify-center text-[9px] font-medium transition-colors" style="background:rgba(22,163,74,0.12);color:#16A34A" title="Mark Complete">' + icon('check-check', 13) + '</button>' : '') +
        '<button data-view="' + esc(a.id) + '" class="w-7 h-7 rounded flex items-center justify-center transition-colors" style="color:#7A7A7A" title="View" onmouseenter="this.style.color=\'#2563EB\'" onmouseleave="this.style.color=\'#7A7A7A\'">' + icon('eye', 13) + '</button>' +
        '<button data-edit="' + esc(a.id) + '" class="w-7 h-7 rounded flex items-center justify-center transition-colors" style="color:#7A7A7A" title="Edit" onmouseenter="this.style.color=\'#C9A227\'" onmouseleave="this.style.color=\'#7A7A7A\'">' + icon('pencil', 13) + '</button>' +
        '<button data-delete="' + esc(a.id) + '" class="w-7 h-7 rounded flex items-center justify-center transition-colors" style="color:#7A7A7A" title="Delete" onmouseenter="this.style.color=\'#C13030\'" onmouseleave="this.style.color=\'#7A7A7A\'">' + icon('trash-2', 13) + '</button>' +
        '</div></td></tr>';
    });
  }

  html += '</tbody></table></div></div>';

  html += '<div class="flex items-center justify-between mt-3">' +
    '<p class="text-xs" style="color:#7A7A7A">Showing ' + (filtered.length > 0 ? ((pg - 1) * perPage + 1) + '\u2013' + Math.min(pg * perPage, filtered.length) : '0') + ' of ' + filtered.length + '</p>' +
    '<div class="flex items-center gap-1">' +
    '<button data-page="' + (pg - 1) + '" class="w-7 h-7 rounded flex items-center justify-center disabled:opacity-30" style="background:#FFFFFF;color:#7A7A7A"' + (pg <= 1 ? ' disabled' : '') + '>' + icon('chevron-left', 13) + '</button>';
  for (var pi = 0; pi < Math.min(5, totalPages); pi++) {
    var pgn = pi + 1;
    html += '<button data-page="' + pgn + '" class="w-7 h-7 rounded text-xs font-medium" style="background:' + (pgn === pg ? '#2A8B4A' : '#FFFFFF') + ';color:' + (pgn === pg ? '#FFFFFF' : '#7A7A7A') + '">' + pgn + '</button>';
  }
  html += '<button data-page="' + (pg + 1) + '" class="w-7 h-7 rounded flex items-center justify-center disabled:opacity-30" style="background:#FFFFFF;color:#7A7A7A"' + (pg >= totalPages ? ' disabled' : '') + '>' + icon('chevron-right', 13) + '</button>' +
    '</div></div>';

  if (ms.modalOpen) {
    var modalTitle = ms.editing ? 'Edit Appointment' : 'Schedule Appointment';
    var mc = '';
    if (ms.error) mc += '<div class="mb-4 rounded-lg px-3 py-2 text-sm" style="background:#FDF6F8;color:#C13030">' + esc(ms.error) + '</div>';
    mc += '<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">';
    if (!ms.editing) {
      mc += '<div class="sm:col-span-2"><label class="mb-1 block text-xs font-medium" style="color:#5A4A62">Appointment ID</label>' +
        '<div class="w-full rounded-lg border border-[#E8D4DB] px-3 py-2 text-sm font-mono-data" style="background:#F8F7FA;color:#C9A227">' + esc(generateAppointmentId(items)) + '</div></div>';
    }
    APPOINTMENT_FIELDS.forEach(function(f) {
      mc += '<div class="' + (f.type === 'textarea' ? 'sm:col-span-2' : '') + '">' +
        '<label class="mb-1 block text-xs font-medium" style="color:#5A4A62">' + esc(f.label) + (f.required ? '<span style="color:#C13030"> *</span>' : '') + '</label>' +
        renderFieldInput(f, ms.form[f.name], key + '_' + f.name) +
        '</div>';
    });
    mc += '</div>' +
      '<div class="mt-6 flex justify-end gap-3">' +
      '<button data-action="cancel-modal" class="rounded-lg border px-4 py-2 text-sm font-medium hover:bg-[#FDF6F8]" style="border-color:#E8D4DB;color:#5A4A62">Cancel</button>' +
      '<button data-action="save" data-module="appointments" class="rounded-lg px-4 py-2 text-sm font-medium text-white bg-[#2A8B4A] hover:bg-[#1B6B3A]">' +
      (ms.editing ? 'Save Changes' : 'Schedule') + '</button>' +
      '</div>';
    html += renderModal(modalTitle, mc, true);
  }

  if (ms.viewTarget) {
    var v = ms.items.find(function(i) { return i.id === ms.viewTarget; });
    if (v) {
      html += '<div id="modal-overlay" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.7)">' +
        '<div class="w-full max-w-md max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-xl">' +
        '<div class="flex items-center justify-between border-b px-6 py-4" style="border-color:#E8D4DB">' +
        '<h3 class="font-serif-heading text-base font-semibold" style="color:#2B2B2B">Appointment Details</h3>' +
        '<button data-view-close class="rounded-full p-1 hover:bg-[#FDF6F8]" style="color:#5A4A62">' + icon('x', 18) + '</button>' +
        '</div>' +
      '<div class="p-6 space-y-2">' +
      [{ label: 'Appointment ID', val: v.id }, { label: 'Patient', val: v.patientName + ' (' + v.patientType + ')' }, { label: 'Date & Time', val: v.date + (v.time ? ' at ' + v.time : '') }, { label: 'Type', val: v.type || v.purpose || '\u2014' }, { label: 'Status', val: v.status }, { label: 'Notes', val: v.notes || '\u2014' }].map(function(item) {
        return '<div class="flex justify-between p-2 rounded-lg" style="background:#FDF6F8">' +
          '<span class="text-[10px]" style="color:#7A7A7A">' + esc(item.label) + '</span>' +
          '<span class="text-xs" style="color:#2B2B2B">' + esc(item.val) + '</span>' +
          '</div>';
      }).join('') +
      '</div>' +
      '<div class="px-6 pb-6"><button data-view-close class="w-full py-2 rounded-lg text-xs" style="background:#FFFFFF;color:#5A4A62;border:1px solid #E8D4DB">Close</button></div>' +
      '</div></div>';
  }

  if (ms.deleteTarget) {
    html += renderConfirmDialog('Delete Appointment?', 'This will permanently remove "' + esc(ms.deleteTarget[config.primary] || 'this record') + '" from the database.', 'Delete', true);
  }

  html += '</div>';
  return html;
}
}

/* ============================== VISITS MODULE ============================== */
function renderVisitsModule() {
  var key = 'visits';
  var config = MODULES[key];
  var ms = getModuleState(key);
  var items = ms.items || [];

  var filtered = items;
  var s = (ms.search || '').toLowerCase();
  if (s) {
    filtered = filtered.filter(function(it) {
      return config.searchKeys.some(function(k) { return String(it[k] || '').toLowerCase().indexOf(s) !== -1; });
    });
  }
  var tf = ms.typeFilter || 'All';
  if (tf !== 'All') filtered = filtered.filter(function(it) { return it.patientType === tf; });
  var dispf = ms.dispFilter || 'All';
  if (dispf !== 'All') filtered = filtered.filter(function(it) { return it.disposition === dispf; });
  var df = ms.dateFilter || '';
  if (df) filtered = filtered.filter(function(it) { return it.date === df; });

  var perPage = 8;
  var totalPages = Math.ceil(filtered.length / perPage);
  var pg = Math.max(1, Math.min(ms.page || 1, totalPages || 1));
  var paginated = filtered.slice((pg - 1) * perPage, pg * perPage);

  var dispColors = {
    'Treated & Discharged': { bg: 'rgba(22,163,74,0.12)', color: '#16A34A' },
    'Referred to Doctor': { bg: 'rgba(245,158,11,0.12)', color: '#F59E0B' },
    'Sent Home': { bg: 'rgba(37,99,235,0.12)', color: '#2563EB' },
    'Emergency Referral': { bg: 'rgba(220,38,38,0.12)', color: '#DC2626' },
    'Observation': { bg: 'rgba(139,58,139,0.15)', color: '#B45AD4' },
  };

  var html = '<div data-module="visits">';

  html += '<div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">' +
    '<div class="flex items-center gap-3">' +
    '<div class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#F0ECF2] text-[#2A8B7A]">' +
    icon('stethoscope', 20) + '</div>' +
    '<div><h2 class="font-serif-heading text-lg font-semibold" style="color:#2B2B2B">Clinic Visit &amp; Consultation Log</h2>' +
    '<p class="text-xs" style="color:#5A4A62">' + ms.items.length + ' visit' + (ms.items.length !== 1 ? 's' : '') + ' on file</p></div>' +
    '</div>' +
    '<div class="flex gap-2">' +
    '<button id="export-visits" class="flex items-center gap-1.5 rounded-lg border px-3 py-2 text-sm hover:bg-[#FDF6F8]" style="border-color:#E8D4DB;color:#5A4A62">' + icon('download', 15) + ' Export</button>' +
    '<button data-action="import-visits" class="flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-white bg-[#2A8B4A] hover:bg-[#1B6B3A]">' +
    icon('upload', 15) + ' Import</button>' +
    '<a href="visits_import_template.csv" download class="flex items-center gap-1.5 rounded-lg border px-3 py-2 text-sm hover:bg-[#FDF6F8]" style="border-color:#E8D4DB;color:#5A4A62">' +
    icon('file-text', 15) + ' Template</a>' +
    '<button data-action="add" data-module="visits" class="flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium text-white bg-[#2A8B7A] hover:bg-[#1B6B5A]">' +
    icon('plus', 16) + ' Log Visit</button>' +
    '<input type="file" id="import-visits-file-input" accept=".csv" class="hidden">' +
    '</div></div>';

  html += '<div class="flex flex-wrap items-center gap-3 mb-4">' +
    '<div class="relative flex-1 min-w-48">' +
    icon('search', 14, 'absolute left-3 top-1/2 -translate-y-1/2 text-[#5A4A62]') +
    '<input data-search="visits" type="text" value="' + esc(ms.search) + '" placeholder="Search patient, ID, complaint..." class="w-full rounded-lg border border-[#E8D4DB] py-2 pl-9 pr-3 text-sm text-[#2B2B2B] focus:border-[#7B1028] focus:outline-none focus:ring-2 focus:ring-[#C9A24E]/30" />' +
    '</div>' +
    '<input type="date" data-visit-date-filter value="' + esc(df) + '" class="px-3 py-2 rounded-lg text-xs border border-[#DADADA] text-[#7A7A7A]" style="background:#FFFFFF" />' +
    '<select data-visit-type-filter class="px-3 py-2 rounded-lg text-xs border border-[#DADADA] text-[#7A7A7A]" style="background:#FFFFFF">' +
    ['All', 'Student', 'Faculty/Staff'].map(function(o) {
      return '<option value="' + o + '"' + (tf === o ? ' selected' : '') + '>' + o + '</option>';
    }).join('') +
    '</select>' +
    '<select data-visit-disp-filter class="px-3 py-2 rounded-lg text-xs border border-[#DADADA] text-[#7A7A7A]" style="background:#FFFFFF">' +
    ['All', 'Treated & Discharged', 'Referred to Doctor', 'Sent Home', 'Emergency Referral', 'Observation'].map(function(o) {
      return '<option value="' + o + '"' + (dispf === o ? ' selected' : '') + '>' + o + '</option>';
    }).join('') +
    '</select>' +
    '<button data-action="generate-filtered-visits" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium text-white bg-[#7B1028] hover:bg-[#3F0D1D] transition-colors">' +
    icon('file-text', 14) + ' Generate' +
    '</button>' +
    '<span class="text-xs" style="color:#7A7A7A">' + filtered.length + ' visit' + (filtered.length !== 1 ? 's' : '') + '</span>' +
    '</div>';

  html += '<div class="overflow-hidden rounded-2xl border shadow-sm" style="border-color:#E8D4DB;background:#FDF6F8">' +
    '<div class="overflow-x-auto"><table class="w-full text-left text-sm">' +
    '<thead><tr class="border-b text-xs uppercase tracking-wide" style="border-color:#E8D4DB;background:#FDF6F8;color:#7A1F3D">' +
    '<th class="whitespace-nowrap px-4 py-3 font-medium">Visit ID</th>' +
    '<th class="whitespace-nowrap px-4 py-3 font-medium">Patient</th>' +
    '<th class="whitespace-nowrap px-4 py-3 font-medium">Type</th>' +
    '<th class="whitespace-nowrap px-4 py-3 font-medium">Date &amp; Time</th>' +
    '<th class="whitespace-nowrap px-4 py-3 font-medium">Chief Complaint</th>' +
    '<th class="whitespace-nowrap px-4 py-3 font-medium">Diagnosis</th>' +
    '<th class="whitespace-nowrap px-4 py-3 font-medium">Treatment</th>' +
    '<th class="whitespace-nowrap px-4 py-3 font-medium">Disposition</th>' +
    '<th class="px-4 py-3 font-medium text-right">Actions</th>' +
    '</tr></thead><tbody>';

  if (ms.loading) {
    html += '<tr><td colspan="11" class="px-4 py-10 text-center">' + renderLoader('Loading visits…', 32) + '</td></tr>';
  } else if (paginated.length === 0) {
    html += '<tr><td colspan="11" class="px-4 py-10 text-center" style="color:#5A4A62">No visits found. Click "Log Visit" to create one.</td></tr>';
  } else {
    paginated.forEach(function(v, i) {
      var borderStyle = i < paginated.length - 1 ? '1px solid #F0E6E8' : 'none';
      var dc = dispColors[v.disposition] || { bg: 'rgba(90,90,90,0.15)', color: '#909090' };
      html += '<tr style="border-bottom:' + borderStyle + '" onmouseenter="this.style.background=\'rgba(201,162,39,0.04)\'" onmouseleave="this.style.background=\'transparent\'">' +
        '<td class="whitespace-nowrap px-4 py-3 text-[11px] font-mono-data" style="color:#C9A227">' + esc(v.id || '') + '</td>' +
        '<td class="whitespace-nowrap px-4 py-3"><p class="text-xs font-medium" style="color:#2B2B2B">' + esc(v.patientName || '') + '</p></td>' +
        '<td class="whitespace-nowrap px-4 py-3 text-xs" style="color:#555555">' + esc(v.patientType || '') + '</td>' +
        '<td class="whitespace-nowrap px-4 py-3"><p class="text-xs" style="color:#555555">' + esc(v.date || '') + '</p><p class="text-[10px] font-mono-data" style="color:#7A7A7A">' + esc(v.time || '') + '</p></td>' +
        '<td class="px-4 py-3 text-xs" style="color:#555555;max-width:140px"><p class="truncate">' + esc(v.complaint || v.chiefComplaint || '') + '</p></td>' +
        '<td class="px-4 py-3 text-xs" style="color:#555555;max-width:140px"><p class="truncate">' + esc(v.diagnosis || v.assessment || '') + '</p></td>' +
        '<td class="px-4 py-3 text-xs" style="color:#555555;max-width:140px"><p class="truncate">' + esc(v.treatment || '') + '</p></td>' +
        '<td class="whitespace-nowrap px-4 py-3"><span class="text-[10px] px-2 py-0.5 rounded-full whitespace-nowrap" style="background:' + dc.bg + ';color:' + dc.color + '">' + esc(v.disposition || '\u2014') + '</span></td>' +
        '<td class="whitespace-nowrap px-4 py-3 text-right"><div class="flex justify-end gap-1">' +
        '<button data-view="' + esc(v.id) + '" class="w-7 h-7 rounded flex items-center justify-center transition-colors" style="color:#7A7A7A" title="View" onmouseenter="this.style.color=\'#2563EB\'" onmouseleave="this.style.color=\'#7A7A7A\'">' + icon('eye', 13) + '</button>' +
        '<button data-edit="' + esc(v.id) + '" class="w-7 h-7 rounded flex items-center justify-center transition-colors" style="color:#7A7A7A" title="Edit" onmouseenter="this.style.color=\'#C9A227\'" onmouseleave="this.style.color=\'#7A7A7A\'">' + icon('pencil', 13) + '</button>' +
        '<button data-delete="' + esc(v.id) + '" class="w-7 h-7 rounded flex items-center justify-center transition-colors" style="color:#7A7A7A" title="Delete" onmouseenter="this.style.color=\'#C13030\'" onmouseleave="this.style.color=\'#7A7A7A\'">' + icon('trash-2', 13) + '</button>' +
        '</div></td></tr>';
    });
  }

  html += '</tbody></table></div></div>';

  html += '<div class="flex items-center justify-between mt-3">' +
    '<p class="text-xs" style="color:#7A7A7A">Showing ' + (filtered.length > 0 ? ((pg - 1) * perPage + 1) + '\u2013' + Math.min(pg * perPage, filtered.length) : '0') + ' of ' + filtered.length + '</p>' +
    '<div class="flex items-center gap-1">' +
    '<button data-page="' + (pg - 1) + '" class="w-7 h-7 rounded flex items-center justify-center disabled:opacity-30" style="background:#FFFFFF;color:#7A7A7A"' + (pg <= 1 ? ' disabled' : '') + '>' + icon('chevron-left', 13) + '</button>';
  for (var pj = 0; pj < Math.min(5, totalPages); pj++) {
    var pgn2 = pj + 1;
    html += '<button data-page="' + pgn2 + '" class="w-7 h-7 rounded text-xs font-medium" style="background:' + (pgn2 === pg ? '#2A8B7A' : '#FFFFFF') + ';color:' + (pgn2 === pg ? '#FFFFFF' : '#7A7A7A') + '">' + pgn2 + '</button>';
  }
  html += '<button data-page="' + (pg + 1) + '" class="w-7 h-7 rounded flex items-center justify-center disabled:opacity-30" style="background:#FFFFFF;color:#7A7A7A"' + (pg >= totalPages ? ' disabled' : '') + '>' + icon('chevron-right', 13) + '</button>' +
    '</div></div>';

  if (ms.modalOpen) {
    var modalTitle = ms.editing ? 'Edit Visit Record' : 'Log New Clinic Visit';
    var mc2 = '';
    if (ms.error) mc2 += '<div class="mb-4 rounded-lg px-3 py-2 text-sm" style="background:#FDF6F8;color:#C13030">' + esc(ms.error) + '</div>';
    mc2 += '<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">';
    if (!ms.editing) {
      var newId = generateVisitId(ms.items);
      mc2 += '<div class="sm:col-span-2"><label class="mb-1 block text-xs font-medium" style="color:#5A4A62">Visit ID</label>' +
        '<div class="w-full rounded-lg border border-[#E8D4DB] px-3 py-2 text-sm font-mono-data" style="background:#F8F7FA;color:#C9A227">' + esc(newId) + '</div></div>';
    }
    config.fields.forEach(function(f) {
      mc2 += '<div class="' + (f.type === 'textarea' ? 'sm:col-span-2' : '') + '">' +
        '<label class="mb-1 block text-xs font-medium" style="color:#5A4A62">' + esc(f.label) + (f.required ? '<span style="color:#C13030"> *</span>' : '') + '</label>' +
        renderFieldInput(f, ms.form[f.name], key + '_' + f.name, key === 'visits' && (f.name === 'date' || f.name === 'time')) +
        '</div>';
    });
    mc2 += '</div>' +
      '<div class="mt-6 flex justify-end gap-3">' +
      '<button data-action="cancel-modal" class="rounded-lg border px-4 py-2 text-sm font-medium hover:bg-[#FDF6F8]" style="border-color:#E8D4DB;color:#5A4A62">Cancel</button>' +
      '<button data-action="save" data-module="visits" class="rounded-lg px-4 py-2 text-sm font-medium text-white bg-[#2A8B7A] hover:bg-[#1B6B5A]">' +
      (ms.editing ? 'Save Changes' : 'Log Visit') + '</button>' +
      '</div>';
    html += renderModal(modalTitle, mc2, true);
  }

  if (ms.viewTarget) {
    var vv = ms.items.find(function(i) { return i.id === ms.viewTarget; });
    if (vv) {
      var dc2 = dispColors[vv.disposition] || { bg: 'rgba(90,90,90,0.15)', color: '#909090' };
      html += '<div id="modal-overlay" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.7)">' +
        '<div class="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-xl">' +
        '<div class="flex items-center justify-between border-b px-6 py-4" style="border-color:#E8D4DB">' +
        '<h3 class="font-serif-heading text-base font-semibold" style="color:#2B2B2B">Visit Details</h3>' +
        '<button data-view-close class="rounded-full p-1 hover:bg-[#FDF6F8]" style="color:#5A4A62">' + icon('x', 18) + '</button>' +
        '</div>' +
        '<div class="p-6">' +
        '<div class="p-3 rounded-xl mb-4 flex items-center justify-between" style="background:rgba(123,16,40,0.1);border:1px solid #E8D4DB">' +
        '<div><p class="font-semibold text-sm" style="color:#2B2B2B">' + esc(vv.patientName) + '</p>' +
        '<p class="text-[10px] font-mono-data" style="color:#7A7A7A">' + esc(vv.id) + ' &middot; ' + esc(vv.date) + (vv.time ? ' ' + esc(vv.time) : '') + '</p></div>' +
        '<span class="text-[10px] px-2 py-1 rounded-full" style="background:' + dc2.bg + ';color:' + dc2.color + '">' + esc(vv.disposition || '\u2014') + '</span>' +
        '</div>' +
      '<div class="space-y-3">' +
      [{ label: 'Patient Type', val: vv.patientType || '\u2014' }, 
       { label: 'Chief Complaint', val: vv.complaint || vv.chiefComplaint || '\u2014' },
       { label: 'Vital Signs', val: 'Temp: ' + (vv.temperature || '\u2014') + ' | BP: ' + (vv.bloodPressure || '\u2014') + ' | PR: ' + (vv.pulseRate || '\u2014') + ' bpm' },
       { label: 'Assessment / Diagnosis', val: vv.diagnosis || vv.assessment || '\u2014' },
       { label: 'Treatment Given', val: vv.treatment || '\u2014' }, { label: 'Medicine Dispensed', val: vv.medicineDispensed || 'None' },
       { label: 'Attending Nurse', val: vv.nurseOnDuty || '\u2014' }].map(function(item) {
        return '<div class="p-3 rounded-lg" style="background:#FDF6F8"><p class="text-[10px] mb-1" style="color:#7A7A7A">' + esc(item.label) + '</p><p class="text-xs" style="color:#2B2B2B">' + esc(item.val) + '</p></div>';
      }).join('') +
      '</div>' +
      '<button data-view-close class="mt-4 w-full py-2 rounded-lg text-xs" style="background:#FFFFFF;color:#5A4A62;border:1px solid #E8D4DB">Close</button>' +
      '</div></div></div>';
  }

  if (ms.deleteTarget) {
    html += renderConfirmDialog('Delete Visit Record?', 'This action cannot be undone. The visit record will be permanently deleted.', 'Delete', true);
  }

  html += '</div>';
  return html;
}
}

function renderRecordGroupPage(ms) {
  var key = 'medicalRecords';
  var items = ms.items || [];
  var g = (ms.groups || []).find(function(x){ return x.name === ms.viewingGroup; });
  var gname = esc(ms.viewingGroup);
  var count = (items || []).filter(function(it){ return (it.groupFolder || '') === ms.viewingGroup; }).length;
  var ftype = (g && g.meta && g.meta.type) ? esc(g.meta.type) : 'Folder';
  var recs = (items || []).filter(function(it){ return (it.groupFolder || '') === ms.viewingGroup; });
  var html = '<div data-module="' + key + '">' +
    '<div class="mb-6 rounded-2xl border bg-white p-5" style="border-color:#E8D4DB">' +
    '<div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">' +
    '<div>' +
    '<button data-action="close-group-view" class="mb-3 inline-flex items-center gap-1.5 text-xs font-medium" style="color:#7A7A7A" onmouseenter="this.style.color=\'#7B1028\'" onmouseleave="this.style.color=\'#7A7A7A\'">&larr; Back to groups</button>' +
    '<h2 class="font-serif-heading text-xl font-semibold" style="color:#2B2B2B">' + gname + '</h2>' +
    '<div class="mt-2 flex flex-wrap items-center gap-2">' +
    '<span class="inline-flex items-center rounded-full bg-[#F0ECF2] px-2.5 py-1 text-[11px]" style="color:#7A1F3D">' + ftype + '</span>' +
    '<span class="inline-flex items-center rounded-full bg-[#F0F4FF] px-2.5 py-1 text-[11px]" style="color:#1D4ED8">' + count + ' student' + (count !== 1 ? 's' : '') + '</span>' +
    '</div>' +
    '</div>' +
    '<div class="flex flex-wrap items-center gap-2">' +
    '<button data-add-record-group="' + gname + '" type="button" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-white" style="background:#2563EB">' + icon('plus', 12) + ' Add Record</button>' +
    '<button data-action="rename-record-group" data-group-name="' + gname + '" type="button" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-white" style="background:#4B5563">' + icon('edit-2', 12) + ' Rename</button>' +
    '<button data-action="delete-record-group" data-group-name="' + gname + '" type="button" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-white" style="background:#C13030">' + icon('trash-2', 12) + ' Delete</button>' +
    '</div>' +
    '</div>' +
    '</div>';
  if (recs.length) {
    html += '<div class="space-y-2">' + recs.map(function(r){
      return '<div class="flex items-center justify-between rounded-xl border bg-white p-4" style="border-color:#E8D4DB">' +
        '<div class="flex items-center gap-3">' +
        '<div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-medium" style="background:#FDF6F8;color:#7A1F3D">' + esc((r.studentName ? r.studentName.charAt(0).toUpperCase() : 'S')) + '</div>' +
        '<div>' +
        '<p class="text-sm font-medium" style="color:#2B2B2B">' + esc(r.studentName || r.studentId || '') + '</p>' +
        '<p class="text-xs" style="color:#7A7A7A">' + (r.studentId ? esc(r.studentId) : '') + '</p>' +
        '</div>' +
        '</div>' +
        '<div class="flex items-center gap-1">' +
          '<button data-view="' + esc(r.id) + '" class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors" style="color:#7A7A7A" title="View" onmouseenter="this.style.color=\'#2563EB\'" onmouseleave="this.style.color=\'#7A7A7A\'">' + icon('eye', 13) + '</button>' +
          '<button data-edit="' + esc(r.id) + '" class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors" style="color:#7A7A7A" title="Edit" onmouseenter="this.style.color=\'#C9A227\'" onmouseleave="this.style.color=\'#7A7A7A\'">' + icon('pencil', 13) + '</button>' +
          '<button data-delete="' + esc(r.id) + '" class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors" style="color:#7A7A7A" title="Delete" onmouseenter="this.style.color=\'#C13030\'" onmouseleave="this.style.color=\'#7A7A7A\'">' + icon('trash-2', 13) + '</button>' +
        '</div>' +
      '</div>';
    }).join('') + '</div>';
  } else {
    html += '<div class="flex flex-col items-center justify-center rounded-2xl border p-10 text-center" style="border-color:#E8D4DB;background:#FDF6F8">' +
      '<p class="text-sm font-medium" style="color:#5A4A62">No records in this group</p>' +
      '<p class="mt-1 text-xs" style="color:#7A7A7A">Click "Add Record" to create one.</p>' +
      '</div>';
  }
  html += '</div>';
  return html;
}

function renderMedicalRecordModal(ms) {
  var key = 'medicalRecords';
  var config = MODULES[key];
  var isEdit = !!ms.editing;
  var formTitle = isEdit ? 'Edit Medical Record' : 'Add Medical Record';
  var formData = ms.form || {};

  var html = '<div id="modal-overlay" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.7)">' +
    '<div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-xl">' +
    '<div class="flex items-center justify-between border-b px-6 py-4" style="border-color:#E8D4DB">' +
    '<h3 class="font-serif-heading text-base font-semibold" style="color:#2B2B2B">' + esc(formTitle) + '</h3>' +
    '<button type="button" data-action="close-medical-record-modal" class="rounded-full p-1 hover:bg-[#FDF6F8]" style="color:#5A4A62">' + icon('x', 18) + '</button>' +
    '</div>' +
    '<div class="p-6">' +
    (ms.error ? '<div class="mb-4 rounded-lg px-3 py-2 text-sm" style="background:#FDF6F8;color:#C13030">' + esc(ms.error) + '</div>' : '') +
    '<form data-save-form="' + key + '">' +
    '<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">';

  config.fields.forEach(function(f) {
    var value = formData[f.name] || '';
    var required = f.required ? ' <span style="color:#C13030">*</span>' : '';
    var fieldHtml = '';

    if (f.name === 'groupFolder') {
      var opts = '<option value="">Select group</option>';
      if (ms.groups === null) {
        opts = '<option value="">Loading groups...</option>';
      } else if ((ms.groups || []).length === 0) {
        opts = '<option value="">No groups available</option>';
      } else {
        opts = '<option value="">Select group</option>' + (ms.groups || []).map(function(g) {
          return '<option value="' + esc(g.name) + '"' + (value === g.name ? ' selected' : '') + '>' + esc(g.name) + '</option>';
        }).join('');
      }
      fieldHtml = '<label class="mb-1 block text-xs font-medium" style="color:#5A4A62">' + esc(f.label) + required + '</label>' +
        '<select name="' + esc(f.name) + '" class="w-full rounded-lg border px-3 py-2 text-sm" style="border-color:#E8D4DB">' + opts + '</select>';
    } else if (f.type === 'select') {
      fieldHtml = '<label class="mb-1 block text-xs font-medium" style="color:#5A4A62">' + esc(f.label) + required + '</label>' +
        '<select name="' + esc(f.name) + '" class="w-full rounded-lg border px-3 py-2 text-sm" style="border-color:#E8D4DB">' +
        '<option value="">Select ' + esc(f.label.toLowerCase()) + '</option>';
      f.options.forEach(function(opt) {
        fieldHtml += '<option value="' + esc(opt) + '"' + (value === opt ? ' selected' : '') + '>' + esc(opt) + '</option>';
      });
      fieldHtml += '</select>';
    } else if (f.type === 'textarea') {
      fieldHtml = '<label class="mb-1 block text-xs font-medium" style="color:#5A4A62">' + esc(f.label) + required + '</label>' +
        '<textarea name="' + esc(f.name) + '" rows="3" class="w-full rounded-lg border px-3 py-2 text-sm" style="border-color:#E8D4DB" placeholder="Enter ' + esc(f.label.toLowerCase()) + '">' + esc(value) + '</textarea>';
    } else {
      var isReadOnly = f.name === 'recordId' && isEdit;
      fieldHtml = '<label class="mb-1 block text-xs font-medium" style="color:#5A4A62">' + esc(f.label) + required + '</label>' +
        '<input type="' + esc(f.type) + '" name="' + esc(f.name) + '" value="' + esc(value) + '" ' + (isReadOnly ? 'readonly' : '') + ' class="w-full rounded-lg border px-3 py-2 text-sm" style="border-color:#E8D4DB' + (isReadOnly ? ';background:#F8F7FA' : '') + '" placeholder="Enter ' + esc(f.label.toLowerCase()) + '">';
    }

    html += '<div>' + fieldHtml + '</div>';
  });

  html += '</div>' +
    '<div class="mt-6 flex justify-end gap-2">' +
    '<button type="button" data-action="close-medical-record-modal" class="rounded-lg px-4 py-2 text-sm" style="background:#FFFFFF;color:#5A4A62;border:1px solid #E8D4DB">Cancel</button>' +
    '<button type="submit" data-module="' + key + '" class="rounded-lg px-4 py-2 text-sm font-medium text-white" style="background:#C13030">' + (isEdit ? 'Update' : 'Save') + ' Record</button>' +
    '</div>' +
    '</form>' +
    '</div>' +
    '</div>' +
    '</div>';
  return html;
}

/* ============================== MEDICAL RECORDS MODULE ============================== */
function renderMedicalRecordsModule() {
  var key = 'medicalRecords';
  var config = MODULES[key];
  var ms = getModuleState(key);
  var items = ms.items || [];

  loadMedicalRecordGroups(ms);

  var filtered = items;
  var s = (ms.search || '').toLowerCase();
  if (s) {
    filtered = filtered.filter(function(it) {
      return config.searchKeys.some(function(k) { return String(it[k] || '').toLowerCase().indexOf(s) !== -1; });
    });
  }


  var html = '<div data-module="' + key + '">' +
    '<div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">' +
    '<div class="flex items-center gap-3">' +
    '</div>' +
    '<div class="flex items-center gap-2">' +
    '<button data-action="create-record-group" class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white" style="background:#6B7280">' + icon('plus', 14) + ' New Group</button>' +
    '</div>' +
    '</div>';

  if (ms.loading) {
    html += '<div class="rounded-2xl border p-4" style="border-color:#E8D4DB;background:#FDF6F8">' + renderLoader('Loading records…', 40) + '</div>';
  } else {
    // When there are no grouped records, show folder cards inside the main rounded area
    html += '<div class="rounded-2xl border p-6" style="border-color:#E8D4DB;background:#FDF6F8">';
    if (ms.groups && (ms.groups || []).length) {
      var allGroups = ms.groups || [];
      var foldersPerPage = 10;
      var folderPage = ms.folderPage || 1;
      var folderTotalPages = Math.max(1, Math.ceil(allGroups.length / foldersPerPage));
      if (folderPage > folderTotalPages) folderPage = folderTotalPages;
      if (folderPage < 1) folderPage = 1;
      ms.folderPage = folderPage;
      var pageGroups = allGroups.slice((folderPage - 1) * foldersPerPage, folderPage * foldersPerPage);
      html += '<div class="mb-4"><div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">' +
        pageGroups.map(function(g) {
          var gname = esc(g.name);
          var count = (items || []).filter(function(it){ return (it.groupFolder || '') === g.name; }).length;
          var ftype = (g.meta && g.meta.type) ? esc(g.meta.type) : 'Folder';
          var metaLabel = (g.meta && g.meta.yearLevel) ? esc(g.meta.yearLevel) + ' · ' : '';
          var isOpen = ms.expandedFolders && ms.expandedFolders[g.name];
          var card = '<div data-action="toggle-folder" data-group-name="' + gname + '" role="button" class="rounded-2xl border p-3 bg-white" style="border-color:#E8D4DB">' +
            '<div class="flex items-start justify-between gap-3">' +
              '<div class="flex items-center gap-3"><div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-medium" style="background:#FDF6F8;color:#7A1F3D">' + gname.charAt(0).toUpperCase() + '</div>' +
              '<div><p class="text-sm font-medium" style="color:#2B2B2B">' + gname + '</p>' +
              '<div class="mt-1 text-xs" style="color:#7A7A7A">' + metaLabel + '<span class="inline-block px-2 py-0.5 rounded-full bg-[#F8F8F8] text-[11px]">' + ftype + '</span> <span class="inline-block px-2 py-0.5 rounded-full bg-[#F0ECF2] text-[11px]">' + count + '</span></div></div></div>' +
              '<div><button data-action="toggle-folder" data-group-name="' + gname + '" class="inline-flex items-center gap-2 rounded px-3 py-1 text-xs font-medium text-white" style="background:#C13030">' + icon('plus', 12) + ' Open</button></div>' +
            '</div>' +
            '</div>';
          var detail = '';
          if (isOpen) {
            detail = '<div class="mt-3 rounded-2xl border bg-white p-4" style="grid-column:1 / -1;border-color:#E8D4DB">' +
              '<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">' +
                '<div>' +
                  '<p class="text-sm font-semibold" style="color:#2B2B2B">Records</p>' +
                  '<div class="mt-1 flex flex-wrap items-center gap-2 text-[11px]">' +
                    '<span class="inline-flex items-center rounded-full bg-[#F0F4FF] px-2 py-1 text-[#1D4ED8]">' + count + ' student' + (count !== 1 ? 's' : '') + '</span>' +
                    '<span class="inline-flex items-center rounded-full bg-[#F8F8F8] px-2 py-1 text-[#7A7A7A]">' + ftype + '</span>' +
                  '</div>' +
                '</div>' +
                '<div class="flex flex-wrap items-center gap-2">' +
                  '<button data-add-record-group="' + gname + '" type="button" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-white" style="background:#2563EB">' + icon('plus', 12) + ' Add</button>' +
                  '<button data-action="rename-record-group" data-group-name="' + gname + '" type="button" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-white" style="background:#4B5563">' + icon('edit-2', 12) + ' Rename</button>' +
                  '<button data-action="delete-record-group" data-group-name="' + gname + '" type="button" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-white" style="background:#C13030">' + icon('trash-2', 12) + ' Delete</button>' +
                '</div>' +
              '</div>';
            var recs = (items || []).filter(function(it){ return (it.groupFolder || '') === g.name; });
            if (recs.length) {
              detail += '<div class="mt-4 space-y-2">' + recs.map(function(r){
                return '<div class="flex items-center justify-between rounded-xl border bg-white p-3" style="border-color:#E8D4DB">' +
                  '<div class="flex items-center gap-3">' +
                  '<div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium" style="background:#FDF6F8;color:#7A1F3D">' + esc((r.studentName ? r.studentName.charAt(0).toUpperCase() : 'S')) + '</div>' +
                  '<div>' +
                  '<p class="text-sm font-medium" style="color:#2B2B2B">' + esc(r.studentName || r.studentId || '') + '</p>' +
                  '<p class="text-xs" style="color:#7A7A7A">' + (r.studentId ? esc(r.studentId) : '') + '</p>' +
                  '</div>' +
                  '</div>' +
                  '<div class="flex items-center gap-1">' +
                    '<button data-view="' + esc(r.id) + '" class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors" style="color:#7A7A7A" title="View" onmouseenter="this.style.color=\'#2563EB\'" onmouseleave="this.style.color=\'#7A7A7A\'">' + icon('eye', 13) + '</button>' +
                    '<button data-edit="' + esc(r.id) + '" class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors" style="color:#7A7A7A" title="Edit" onmouseenter="this.style.color=\'#C9A227\'" onmouseleave="this.style.color=\'#7A7A7A\'">' + icon('pencil', 13) + '</button>' +
                    '<button data-delete="' + esc(r.id) + '" class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors" style="color:#7A7A7A" title="Delete" onmouseenter="this.style.color=\'#C13030\'" onmouseleave="this.style.color=\'#7A7A7A\'">' + icon('trash-2', 13) + '</button>' +
                  '</div>' +
                '</div>';
              }).join('') + '</div>';
            } else {
              detail += '<div class="mt-3 rounded-xl border p-4 text-center" style="background:#FDF6F8;border-color:#E8D4DB">' +
                '<p class="text-sm font-medium" style="color:#5A4A62">No records in this folder</p>' +
                '<p class="mt-1 text-xs" style="color:#7A7A7A">Click Add to create one.</p>' +
              '</div>';
            }
            detail += '</div>';
          }
          return card;
        }).join('') +
      '</div></div>';
      html += '<div class="mt-4 flex flex-wrap items-center justify-center gap-2 text-xs" style="color:#5A4A62">' +
        '<button data-folder-page="' + (folderPage - 1) + '" type="button" class="rounded-lg px-3 py-1.5 text-xs font-medium" ' + (folderPage === 1 ? 'disabled style="background:#F8F7FB;color:#B0A8B5"' : 'style="background:#F0ECF2;color:#7A1F3D"') + '>&larr; Prev</button>' +
        new Array(folderTotalPages).fill(0).map(function(_, i) {
          var p = i + 1;
          var isActive = p === folderPage;
          return '<button data-folder-page="' + p + '" type="button" class="rounded-lg px-3 py-1.5 text-xs font-medium" ' + (isActive ? 'style="background:#7A1F3D;color:#FFFFFF"' : 'style="background:#F0ECF2;color:#7A1F3D"') + '>' + p + '</button>';
        }).join('') +
        '<button data-folder-page="' + (folderPage + 1) + '" type="button" class="rounded-lg px-3 py-1.5 text-xs font-medium" ' + (folderPage === folderTotalPages ? 'disabled style="background:#F8F7FB;color:#B0A8B5"' : 'style="background:#F0ECF2;color:#7A1F3D"') + '>Next &rarr;</button>' +
      '</div>';
      // Do not show the 'No records found' text; only show folders
      html += '';
    } else if (ms.groups === null) {
      html += '<div class="rounded-2xl border p-4" style="border-color:#E8D4DB;background:#FDF6F8">' + renderLoader('Loading groups…', 32) + '</div>';
    } else {
      // Do not show the 'No records found' text; only show folders
      html += '';
    }
    html += '</div>';
  }

  if (ms.expandedFolders && ms.groupSelected && ms.expandedFolders[ms.groupSelected]) {
    var g = (ms.groups || []).find(function(x){ return x.name === ms.groupSelected; });
    if (g) {
      var gname2 = esc(g.name);
      var count2 = (items || []).filter(function(it){ return (it.groupFolder || '') === g.name; }).length;
      var ftype2 = (g.meta && g.meta.type) ? esc(g.meta.type) : 'Folder';
      var recs2 = (items || []).filter(function(it){ return (it.groupFolder || '') === g.name; });
      var perPage = 25;
      var currentPage = ms.groupPage || 1;
      var totalPages = Math.max(1, Math.ceil(recs2.length / perPage));
      if (currentPage > totalPages) currentPage = totalPages;
      if (currentPage < 1) currentPage = 1;
      ms.groupPage = currentPage;
      var pageRecs = recs2.slice((currentPage - 1) * perPage, currentPage * perPage);
      html += '<div class="mt-4 rounded-2xl border bg-white p-4" style="border-color:#E8D4DB">' +
        '<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">' +
          '<div>' +
            '<p class="text-sm font-semibold" style="color:#2B2B2B">' + gname2 + ' records</p>' +
            '<div class="mt-1 flex flex-wrap items-center gap-2 text-[11px]">' +
              '<span class="inline-flex items-center rounded-full bg-[#F0F4FF] px-2 py-1 text-[#1D4ED8]">' + count2 + ' student' + (count2 !== 1 ? 's' : '') + '</span>' +
              '<span class="inline-flex items-center rounded-full bg-[#F8F8F8] px-2 py-1 text-[#7A7A7A]">' + ftype2 + '</span>' +
            '</div>' +
          '</div>' +
          '<div class="flex flex-wrap items-center gap-2">' +
            '<button data-add-record-group="' + gname2 + '" type="button" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-white" style="background:#2563EB">' + icon('plus', 12) + ' Add</button>' +
            '<button data-action="rename-record-group" data-group-name="' + gname2 + '" type="button" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-white" style="background:#4B5563">' + icon('edit-2', 12) + ' Rename</button>' +
            '<button data-action="delete-record-group" data-group-name="' + gname2 + '" type="button" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-white" style="background:#C13030">' + icon('trash-2', 12) + ' Delete</button>' +
          '</div>' +
        '</div>';
      if (recs2.length) {
        html += '<div class="mt-4 space-y-3">' + pageRecs.map(function(r){
          var recStatus = r.status || 'Active';
          var recStatusColor = recStatus === 'Active' ? { bg: 'rgba(42,139,74,0.12)', color: '#2A8B4A' } : { bg: 'rgba(90,90,90,0.12)', color: '#5A5A5A' };
          var immColor = { 'Complete': { bg: 'rgba(42,139,74,0.12)', color: '#2A8B4A' }, 'Incomplete': { bg: 'rgba(201,162,78,0.12)', color: '#C9A24E' }, 'Pending': { bg: 'rgba(245,158,11,0.12)', color: '#F59E0B' }, 'Unknown': { bg: 'rgba(90,90,90,0.12)', color: '#5A5A5A' } }[r.immunizationStatus || 'Unknown'];
          return '<div data-record-id="' + esc(r.id) + '" class="rounded-2xl border bg-white p-4" style="border-color:#E8D4DB">' +
            '<div class="flex items-start justify-between gap-3">' +
              '<div class="flex items-center gap-3">' +
                '<div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-medium" style="background:#FDF6F8;color:#7A1F3D">' + esc((r.studentName ? r.studentName.charAt(0).toUpperCase() : 'S')) + '</div>' +
                '<div>' +
                  '<p class="text-sm font-semibold" style="color:#2B2B2B">' + esc(r.studentName || r.studentId || '') + '</p>' +
                  '<p class="text-[11px]" style="color:#7A7A7A">' + (r.studentId ? esc(r.studentId) : '') + ' &middot; ' + (r.recordId ? esc(r.recordId) : '') + '</p>' +
                '</div>' +
              '</div>' +
              '<div class="flex items-center gap-1">' +
                '<button data-edit="' + esc(r.id) + '" class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors" style="color:#7A7A7A" title="Edit" onmouseenter="this.style.color=\'#C9A227\'" onmouseleave="this.style.color=\'#7A7A7A\'">' + icon('pencil', 13) + '</button>' +
                '<button data-delete="' + esc(r.id) + '" class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors" style="color:#7A7A7A" title="Delete" onmouseenter="this.style.color=\'#C13030\'" onmouseleave="this.style.color=\'#7A7A7A\'">' + icon('trash-2', 13) + '</button>' +
              '</div>' +
            '</div>' +
            '<div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">' +
              '<div class="rounded-lg p-2" style="background:#FDF6F8"><p class="text-[10px]" style="color:#7A7A7A">Group</p><p class="text-xs font-medium" style="color:#2B2B2B">' + esc(r.groupFolder || '—') + '</p></div>' +
              '<div class="rounded-lg p-2" style="background:#FDF6F8"><p class="text-[10px]" style="color:#7A7A7A">Department</p><p class="text-xs font-medium" style="color:#2B2B2B">' + esc(r.department || '—') + '</p></div>' +
              '<div class="rounded-lg p-2" style="background:#FDF6F8"><p class="text-[10px]" style="color:#7A7A7A">Year / Section</p><p class="text-xs font-medium" style="color:#2B2B2B">' + esc(r.yearLevel || '—') + ' &middot; ' + esc(r.section || '—') + '</p></div>' +
              '<div class="rounded-lg p-2" style="background:#FDF6F8"><p class="text-[10px]" style="color:#7A7A7A">Blood Type</p><p class="text-xs font-medium" style="color:#2B2B2B">' + esc(r.bloodType || '—') + '</p></div>' +
              '<div class="rounded-lg p-2" style="background:#FDF6F8"><p class="text-[10px]" style="color:#7A7A7A">Height / Weight</p><p class="text-xs font-medium" style="color:#2B2B2B">' + esc(r.height || '—') + ' cm &middot; ' + esc(r.weight || '—') + ' kg</p></div>' +
              '<div class="rounded-lg p-2" style="background:#FDF6F8"><p class="text-[10px]" style="color:#7A7A7A">Vision / Hearing</p><p class="text-xs font-medium" style="color:#2B2B2B">' + esc(r.vision || '—') + ' / ' + esc(r.hearing || '—') + '</p></div>' +
              '<div class="rounded-lg p-2" style="background:#FDF6F8"><p class="text-[10px]" style="color:#7A7A7A">Allergies</p><p class="text-xs font-medium" style="color:#2B2B2B">' + esc(r.allergies || 'None') + '</p></div>' +
              '<div class="rounded-lg p-2" style="background:#FDF6F8"><p class="text-[10px]" style="color:#7A7A7A">Conditions</p><p class="text-xs font-medium" style="color:#2B2B2B">' + esc(r.medicalConditions || 'None') + '</p></div>' +
              '<div class="rounded-lg p-2" style="background:#FDF6F8"><p class="text-[10px]" style="color:#7A7A7A">Remarks</p><p class="text-xs font-medium" style="color:#2B2B2B">' + esc(r.remarks || 'None') + '</p></div>' +
            '</div>' +
            '<div class="mt-3 flex items-center gap-2">' +
              '<span class="text-[10px] px-2 py-1 rounded-full" style="background:' + recStatusColor.bg + ';color:' + recStatusColor.color + '">' + esc(recStatus) + '</span>' +
              '<span class="text-[10px] px-2 py-1 rounded-full" style="background:' + immColor.bg + ';color:' + immColor.color + '">' + esc(r.immunizationStatus || 'Unknown') + '</span>' +
            '</div>' +
          '</div>';
        }).join('') + '</div>' +
        '<div class="mt-4 text-center text-xs" style="color:#5A4A62">' + recs2.length + ' record' + (recs2.length !== 1 ? 's' : '') + '</div>' +
        '<div class="mt-2 flex flex-wrap items-center justify-center gap-2 text-xs" style="color:#5A4A62">' +
          '<button data-group-page="' + (currentPage - 1) + '" type="button" class="rounded-lg px-3 py-1.5 text-xs font-medium" ' + (currentPage === 1 ? 'disabled style="background:#F8F7FB;color:#B0A8B5"' : 'style="background:#F0ECF2;color:#7A1F3D"') + '>&larr; Prev</button>' +
          new Array(totalPages).fill(0).map(function(_, i) {
            var p = i + 1;
            var isActive = p === currentPage;
            return '<button data-group-page="' + p + '" type="button" class="rounded-lg px-3 py-1.5 text-xs font-medium" ' + (isActive ? 'style="background:#7A1F3D;color:#FFFFFF"' : 'style="background:#F0ECF2;color:#7A1F3D"') + '>' + p + '</button>';
          }).join('') +
          '<button data-group-page="' + (currentPage + 1) + '" type="button" class="rounded-lg px-3 py-1.5 text-xs font-medium" ' + (currentPage === totalPages ? 'disabled style="background:#F8F7FB;color:#B0A8B5"' : 'style="background:#F0ECF2;color:#7A1F3D"') + '>Next &rarr;</button>' +
        '</div>';
      } else {
        html += '<div class="mt-3 rounded-xl border p-4 text-center" style="background:#FDF6F8;border-color:#E8D4DB">' +
          '<p class="text-sm font-medium" style="color:#5A4A62">No records in this folder</p>' +
          '<p class="mt-1 text-xs" style="color:#7A7A7A">Click Add to create one.</p>' +
        '</div>';
      }
      html += '</div>';
    }
  }

  // View modal
  if (ms.viewTarget) {
    var vv = ms.items.find(function(i) { return i.id === ms.viewTarget; });
    if (vv) {
      var immunColors2 = {
        'Complete': { bg: 'rgba(42,139,74,0.12)', color: '#2A8B4A' },
        'Incomplete': { bg: 'rgba(201,162,78,0.12)', color: '#C9A24E' },
        'Pending': { bg: 'rgba(245,158,11,0.12)', color: '#F59E0B' },
        'Unknown': { bg: 'rgba(90,90,90,0.12)', color: '#5A5A5A' }
      };
      var ic2 = immunColors2[vv.immunizationStatus] || immunColors2['Unknown'];
      
      html += '<div id="modal-overlay" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.7)">' +
        '<div class="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-xl">' +
        '<div class="flex items-center justify-between border-b px-6 py-4" style="border-color:#E8D4DB">' +
        '<h3 class="font-serif-heading text-base font-semibold" style="color:#2B2B2B">Medical Record Details</h3>' +
        '<button data-view-close class="rounded-full p-1 hover:bg-[#FDF6F8]" style="color:#5A4A62">' + icon('x', 18) + '</button>' +
        '</div>' +
        '<div class="p-6">' +
        '<div class="p-3 rounded-xl mb-4 flex items-center justify-between" style="background:rgba(123,16,40,0.1);border:1px solid #E8D4DB">' +
        '<div><p class="font-semibold text-sm" style="color:#2B2B2B">' + esc(vv.studentName) + '</p>' +
        '<p class="text-[10px] font-mono-data" style="color:#7A7A7A">' + esc(vv.recordId) + ' &middot; ' + esc(vv.studentId) + '</p></div>' +
        '<span class="text-[10px] px-2 py-1 rounded-full" style="background:' + ic2.bg + ';color:' + ic2.color + '">' + esc(vv.immunizationStatus || '\u2014') + '</span>' +
        '</div>' +
        '<div class="space-y-3">' +
        [{ label: 'Department', val: vv.department || '\u2014' }, { label: 'Year Level', val: vv.yearLevel || '\u2014' },
         { label: 'Section', val: vv.section || '\u2014' }, { label: 'Blood Type', val: vv.bloodType || '\u2014' },
         { label: 'Height', val: vv.height || '\u2014' }, { label: 'Weight', val: vv.weight || '\u2014' },
         { label: 'Vision', val: vv.vision || '\u2014' }, { label: 'Hearing', val: vv.hearing || '\u2014' },
         { label: 'Allergies', val: vv.allergies || 'None' }, { label: 'Medical Conditions', val: vv.medicalConditions || 'None' },
         { label: 'Remarks', val: vv.remarks || 'None' }].map(function(item) {
          return '<div class="p-3 rounded-lg" style="background:#FDF6F8"><p class="text-[10px] mb-1" style="color:#7A7A7A">' + esc(item.label) + '</p><p class="text-xs" style="color:#2B2B2B">' + esc(item.val) + '</p></div>';
        }).join('') +
        '</div>' +
        '<button data-view-close class="mt-4 w-full py-2 rounded-lg text-xs" style="background:#FFFFFF;color:#5A4A62;border:1px solid #E8D4DB">Close</button>' +
        '</div></div></div>';
    }
  }

  // Delete confirmation
  if (ms.deleteTarget) {
    html += renderConfirmDialog('Delete Medical Record?', 'This action cannot be undone. The medical record will be permanently deleted.', 'Delete', true);
  }

  // Add/Edit modal
  if (ms.modalOpen) {
    html += renderMedicalRecordModal(ms);
  }

  // New Group modal
  if (ms.newGroupModalOpen) {
    var gf = ms.groupForm || { name: '', description: '', yearLevel: '', department: '', type: 'Student' };
    var errHtml = ms.groupError ? '<div class="mb-4 rounded-lg px-3 py-2 text-sm" style="background:#FDF6F8;color:#C13030">' + esc(ms.groupError) + '</div>' : '';
    var modalContent = '' + errHtml +
      '<form data-save-form="medicalRecordGroup">' +
      '<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">' +
      '<div class="sm:col-span-2"><label class="mb-1 block text-xs font-medium" style="color:#5A4A62">Group name <span style="color:#C13030">*</span></label>' +
      '<input name="name" value="' + esc(gf.name) + '" class="w-full rounded-lg border px-3 py-2 text-sm" style="border-color:#E8D4DB" /></div>' +
      '<div class="sm:col-span-2"><label class="mb-1 block text-xs font-medium" style="color:#5A4A62">Description</label>' +
      '<input name="description" value="' + esc(gf.description) + '" class="w-full rounded-lg border px-3 py-2 text-sm" style="border-color:#E8D4DB" /></div>' +
      '<div><label class="mb-1 block text-xs font-medium" style="color:#5A4A62">Type</label>' +
      '<select name="type" class="w-full rounded-lg border px-3 py-2 text-sm" style="border-color:#E8D4DB">' +
      '<option value="Student"' + (gf.type === 'Student' ? ' selected' : '') + '>Student</option>' +
      '<option value="Faculty"' + (gf.type === 'Faculty' ? ' selected' : '') + '>Faculty</option>' +
      '</select></div>' +
      '<div><label class="mb-1 block text-xs font-medium" style="color:#5A4A62">Year level</label>' +
      '<select name="yearLevel" class="w-full rounded-lg border px-3 py-2 text-sm" style="border-color:#E8D4DB">' +
      '<option value="">Select year level</option>' +
      '<option value="1st Year"' + (gf.yearLevel === '1st Year' ? ' selected' : '') + '>1st Year</option>' +
      '<option value="2nd Year"' + (gf.yearLevel === '2nd Year' ? ' selected' : '') + '>2nd Year</option>' +
      '<option value="3rd Year"' + (gf.yearLevel === '3rd Year' ? ' selected' : '') + '>3rd Year</option>' +
      '<option value="4th Year"' + (gf.yearLevel === '4th Year' ? ' selected' : '') + '>4th Year</option>' +
      '</select></div>' +
      '<div class="sm:col-span-2"><label class="mb-1 block text-xs font-medium" style="color:#5A4A62">Department</label>' +
      '<select name="department" class="w-full rounded-lg border px-3 py-2 text-sm" style="border-color:#E8D4DB">' +
      '<option value="">Select department</option>' +
      '<option value="BSIT"' + (gf.department === 'BSIT' ? ' selected' : '') + '>BSIT</option>' +
      '<option value="CTE"' + (gf.department === 'CTE' ? ' selected' : '') + '>CTE</option>' +
      '<option value="BSCPE"' + (gf.department === 'BSCPE' ? ' selected' : '') + '>BSCPE</option>' +
      '<option value="BSED"' + (gf.department === 'BSED' ? ' selected' : '') + '>BSED</option>' +
      '<option value="Nursing"' + (gf.department === 'Nursing' ? ' selected' : '') + '>Nursing</option>' +
      '<option value="Other"' + (gf.department === 'Other' ? ' selected' : '') + '>Other</option>' +
      '</select></div>' +
      '</div>' +
      '<div class="mt-6 flex justify-end gap-2">' +
      '<button type="button" data-action="cancel-group-modal" class="rounded-lg px-4 py-2 text-sm" style="background:#FFFFFF;color:#5A4A62;border:1px solid #E8D4DB">Cancel</button>' +
      '<button type="submit" class="rounded-lg px-4 py-2 text-sm font-medium text-white" style="background:#6B7280">Create Group</button>' +
      '</div>' +
      '</form>';
    html += renderModal('New Group', modalContent, true);
  }

  if (ms.groupRenameModalOpen) {
    var grf = ms.groupRenameForm || { currentName: '', newName: '' };
    var errHtml2 = ms.groupRenameError ? '<div class="mb-4 rounded-lg px-3 py-2 text-sm" style="background:#FDF6F8;color:#C13030">' + esc(ms.groupRenameError) + '</div>' : '';
    var modalContent2 = '' + errHtml2 +
      '<form data-save-form="medicalRecordGroupRename">' +
      '<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">' +
      '<div class="sm:col-span-2"><label class="mb-1 block text-xs font-medium" style="color:#5A4A62">Current group name</label>' +
      '<input name="currentName" readonly value="' + esc(grf.currentName) + '" class="w-full rounded-lg border px-3 py-2 text-sm bg-[#F8F8F8]" style="border-color:#E8D4DB" /></div>' +
      '<div class="sm:col-span-2"><label class="mb-1 block text-xs font-medium" style="color:#5A4A62">New group name <span style="color:#C13030">*</span></label>' +
      '<input name="newName" value="' + esc(grf.newName) + '" class="w-full rounded-lg border px-3 py-2 text-sm" style="border-color:#E8D4DB" /></div>' +
      '</div>' +
      '<div class="mt-6 flex justify-end gap-2">' +
      '<button type="button" data-action="cancel-group-modal" class="rounded-lg px-4 py-2 text-sm" style="background:#FFFFFF;color:#5A4A62;border:1px solid #E8D4DB">Cancel</button>' +
      '<button type="submit" class="rounded-lg px-4 py-2 text-sm font-medium text-white" style="background:#4B5563">Rename Group</button>' +
      '</div>' +
      '</form>';
    html += renderModal('Rename Group', modalContent2, true);
  }



  html += '</div>';
  return html;
}

/* ============================== CRUD MODULE ============================== */
function renderCrudModule(key) {
  var config = MODULES[key];
  if (!config) return '<div style="color:#5A4A62">Module not found</div>';
  var ms = getModuleState(key);
  var dispensable = key === 'medicine';
  var columns = config.fields.filter(function(f) { return f.type !== 'textarea' && !f.hideInTable; }).slice(0, 6);
  var isStatusLike = function(name) { return ['status', 'severity', 'yearLevel', 'role'].indexOf(name) !== -1; };

  var html = '<div data-module="' + key + '">';

  /* header */
  html += '<div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">' +
    '<div class="flex items-center gap-3">' +
    '<div class="flex h-11 w-11 items-center justify-center rounded-xl ' + COLOR_MAP[config.color] + '">' +
    icon(ICON_MAP[key], 20) + '</div>' +
    '<div><h2 class="font-serif-heading text-lg font-semibold" style="color:#2B2B2B">' + esc(config.title) + '</h2>' +
    '<p class="text-xs" style="color:#5A4A62">' + ms.items.length + ' record' + (ms.items.length !== 1 ? 's' : '') + ' on file</p></div>' +
    '</div>' +
    '<div class="flex gap-2">' +
    '<div class="relative">' +
    icon('search', 15, 'absolute left-3 top-1/2 -translate-y-1/2 text-[#5A4A62]') +
    '<input data-search="' + key + '" type="text" value="' + esc(ms.search) + '" placeholder="Search records..." class="w-56 rounded-lg border border-[#E8D4DB] py-2 pl-9 pr-3 text-sm text-[#2B2B2B] focus:border-[#7B1028] focus:outline-none focus:ring-2 focus:ring-[#C9A24E]/30" />' +
    '</div>' +
    (key !== 'students' ? '<button data-action="add" data-module="' + key + '" class="flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium text-white ' + BTN_COLOR_MAP[config.color] + '">' +
    icon('plus', 16) + ' Add New</button>' : '') +
    '</div></div>';

  /* filter + search */
  var filtered = ms.items;
  if (ms.search.trim()) {
    var sq = ms.search.toLowerCase();
    filtered = filtered.filter(function(it) {
      return config.searchKeys.some(function(k) { return String(it[k] || '').toLowerCase().indexOf(sq) !== -1; });
    });
  }

  /* module-specific summary cards */
  if (key === 'medicine') {
    var totalItems = ms.items.length;
    var inStock = ms.items.filter(function(i) { return Number(i.stock) > 0; }).length;
    var lowStock = ms.items.filter(function(i) { return Number(i.stock) <= Number(i.reorderLevel || 0) && Number(i.stock) > 0; }).length;
    var outOfStock = ms.items.filter(function(i) { return Number(i.stock) <= 0; }).length;
    html += '<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">';
    [
      { label: 'Total Items', value: totalItems, color: '#2A6B9B', bg: 'rgba(42,107,155,0.12)' },
      { label: 'In Stock', value: inStock, color: '#2A8B4A', bg: 'rgba(42,139,74,0.12)' },
      { label: 'Low Stock', value: lowStock, color: '#C9A24E', bg: 'rgba(201,162,78,0.12)' },
      { label: 'Out of Stock', value: outOfStock, color: '#C13030', bg: 'rgba(193,48,48,0.12)' },
    ].forEach(function(c) {
      html += '<div class="p-4 rounded-xl" style="background:#FDF6F8;border:1px solid #E8D4DB"><p class="text-xl font-semibold font-mono-data" style="color:' + c.color + '">' + c.value + '</p><p class="text-[10px] mt-0.5" style="color:#7A7A7A">' + c.label + '</p></div>';
    });
    html += '</div>';
  }

  if (key === 'staff') {
    var totalStaff = ms.items.length;
    var activeStaff = ms.items.filter(function(i) { var s = (i.status || '').toLowerCase(); return !s || s === 'active'; }).length;
    var onLeave = ms.items.filter(function(i) { return (i.status || '').toLowerCase() === 'on leave'; }).length;
    html += '<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">';
    [
      { label: 'Total Staff', value: totalStaff, color: '#8B3A8B', bg: 'rgba(139,58,139,0.12)' },
      { label: 'Active', value: activeStaff, color: '#2A8B4A', bg: 'rgba(42,139,74,0.12)' },
      { label: 'On Leave', value: onLeave, color: '#C9A24E', bg: 'rgba(201,162,78,0.12)' },
      { label: 'Overdue Checkups', value: 0, color: '#C13030', bg: 'rgba(193,48,48,0.12)' },
    ].forEach(function(c) {
      html += '<div class="p-4 rounded-xl" style="background:#FDF6F8;border:1px solid #E8D4DB"><p class="text-xl font-semibold font-mono-data" style="color:' + c.color + '">' + c.value + '</p><p class="text-[10px] mt-0.5" style="color:#7A7A7A">' + c.label + '</p></div>';
    });
    html += '</div>';
  }

  if (key === 'incidents') {
    var openCount = ms.items.filter(function(i) { return (i.status || 'Open') === 'Open'; }).length;
    var investigatingCount = ms.items.filter(function(i) { return i.status === 'Under Investigation'; }).length;
    var resolvedCount = ms.items.filter(function(i) { return i.status === 'Resolved'; }).length;
    html += '<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">';
    [
      { label: 'Open', value: openCount, color: '#C13030', bg: 'rgba(193,48,48,0.12)' },
      { label: 'Under Investigation', value: investigatingCount, color: '#C9A24E', bg: 'rgba(201,162,78,0.12)' },
      { label: 'Resolved', value: resolvedCount, color: '#2A8B4A', bg: 'rgba(42,139,74,0.12)' },
    ].forEach(function(c) {
      html += '<div class="p-4 rounded-xl" style="background:#FDF6F8;border:1px solid #E8D4DB"><p class="text-xl font-semibold font-mono-data" style="color:' + c.color + '">' + c.value + '</p><p class="text-[10px] mt-0.5" style="color:#7A7A7A">' + c.label + '</p></div>';
    });
    html += '</div>';
  }

  /* module-specific filter dropdowns */
  var filterDropdowns = [];
  function getFieldOptions(fieldName) {
    for (var fi = 0; fi < config.fields.length; fi++) {
      if (config.fields[fi].name === fieldName && config.fields[fi].options) return config.fields[fi].options;
    }
    return [];
  }
  if (key === 'medicine') {
    filterDropdowns.push({ name: 'category', label: 'Category', options: getFieldOptions('category') });
  }
  // Removed blood type filter for students
  // if (key === 'students') {
  //   filterDropdowns.push({ name: 'bloodType', label: 'Blood Type', options: getFieldOptions('bloodType') });
  // }
  if (key === 'incidents') {
    filterDropdowns.push({ name: 'severity', label: 'Severity', options: getFieldOptions('severity') });
  }
  if (key === 'programs') {
    filterDropdowns.push({ name: 'category', label: 'Category', options: getFieldOptions('category') });
  }
  if (key === 'clearance') {
    filterDropdowns.push({ name: 'clearanceType', label: 'Type', options: getFieldOptions('clearanceType') });
  }
  if (key === 'users') {
    filterDropdowns.push({ name: 'role', label: 'Role', options: getFieldOptions('role') });
  }
  /* Status filter for modules with status field */
  var hasStatus = config.fields.some(function(f) { return f.name === 'status'; });
  if (hasStatus && key !== 'appointments') {
    filterDropdowns.push({ name: 'status', label: 'Status', options: getFieldOptions('status') });
  }

  if (filterDropdowns.length) {
    html += '<div class="flex flex-wrap items-center gap-2 mb-3">';
    filterDropdowns.forEach(function(fd) {
      var currentFilter = ms[fd.name + 'Filter'] || 'All';
      html += '<select data-filter="' + key + '-' + fd.name + '" class="px-3 py-2 rounded-lg text-xs border border-[#DADADA] text-[#7A7A7A]" style="background:#FFFFFF">' +
        '<option value="All">All ' + fd.label + 's</option>';
      fd.options.forEach(function(o) {
        html += '<option value="' + esc(o) + '"' + (currentFilter === o ? ' selected' : '') + '>' + esc(o) + '</option>';
      });
      html += '</select>';
    });
    html += '</div>';
  }

  /* apply module-specific filters */
  filterDropdowns.forEach(function(fd) {
    var currentFilter = ms[fd.name + 'Filter'] || 'All';
    if (currentFilter !== 'All') {
      filtered = filtered.filter(function(it) { return it[fd.name] === currentFilter; });
    }
  });

  /* pagination */
  var perPage = 10;
  var totalPages = Math.ceil(filtered.length / perPage);
  var pg = Math.max(1, Math.min(ms.page || 1, totalPages || 1));
  var paginated = filtered.slice((pg - 1) * perPage, pg * perPage);

  /* filter info row */
  html += '<div class="flex items-center justify-between mb-3">' +
    '<span class="text-xs" style="color:#7A7A7A">' + filtered.length + ' record' + (filtered.length !== 1 ? 's' : '') + '</span>' +
    (totalPages > 1 ? '<span class="text-xs" style="color:#7A7A7A">Page ' + pg + ' of ' + totalPages + '</span>' : '') +
    '</div>';

  /* enrollment link for students */
  if (key === 'students') {
    html += '<div class="mb-4 flex flex-wrap items-center justify-between gap-3">' +
      '<div class="flex flex-wrap gap-3">' +
      '<button data-action="open-enrollment-modal" class="inline-flex items-center gap-2 rounded-lg px-4 py-3 text-sm font-medium text-white bg-[#7B1028] hover:bg-[#3F0D1D] transition-colors">' +
      icon('user-plus', 16) + ' Enroll New Student' +
      '</button>' +
      '<button data-action="import-students" class="inline-flex items-center gap-2 rounded-lg px-4 py-3 text-sm font-medium text-white bg-[#2A8B4A] hover:bg-[#1B6B3A] transition-colors">' +
      icon('upload', 16) + ' Import Excel' +
      '</button>' +
      '<button data-action="export-students" class="inline-flex items-center gap-2 rounded-lg px-4 py-3 text-sm font-medium text-white bg-[#C9A24E] hover:bg-[#A6823D] transition-colors">' +
      icon('download', 16) + ' Export Excel' +
      '</button>' +
      '</div>' +
      '<a href="students_import_template.csv" download class="inline-flex items-center gap-2 rounded-lg px-4 py-3 text-sm font-medium text-white bg-[#5A4A62] hover:bg-[#3A3A4A] transition-colors">' +
      icon('file-text', 16) + ' Download Template' +
      '</a>' +
      '<input type="file" id="import-file-input" accept=".csv" class="hidden">' +
      '</div>';
  }

  /* table */
  html += '<div class="overflow-hidden rounded-2xl border shadow-sm" style="border-color:#E8D4DB;background:#FDF6F8">' +
    '<div class="overflow-x-auto">' +
    '<table class="w-full text-left text-sm">' +
    '<thead><tr class="border-b text-xs uppercase tracking-wide" style="border-color:#E8D4DB;background:#FDF6F8;color:#7A1F3D">';
  columns.forEach(function(c) {
    html += '<th class="whitespace-nowrap px-4 py-3 font-medium">' + esc(c.label) + '</th>';
  });
  if (dispensable) html += '<th class="px-4 py-3 font-medium">Dispense</th>';
  html += '<th class="px-4 py-3 font-medium text-right">Actions</th>';
  html += '</tr></thead><tbody>';

  if (ms.loading) {
    html += '<tr><td colspan="' + (columns.length + (dispensable ? 2 : 1)) + '" class="px-4 py-10 text-center">' + renderLoader('Loading records…', 32) + '</td></tr>';
  } else if (paginated.length === 0) {
    html += '<tr><td colspan="' + (columns.length + (dispensable ? 2 : 1)) + '" class="px-4 py-10 text-center" style="color:#5A4A62">No records found. Click "Add New" to create one.</td></tr>';
  } else {
    paginated.forEach(function(item) {
      var low = dispensable && Number(item.stock) <= Number(item.reorderLevel || 0);
      html += '<tr style="border-bottom:1px solid #F0E6E8' + (low ? ';background:rgba(193,48,48,0.05)' : '') + '" onmouseenter="this.style.background=\'rgba(201,162,39,0.04)\'" onmouseleave="this.style.background=\'transparent\'">';
      columns.forEach(function(c) {
        html += '<td class="whitespace-nowrap px-4 py-3" style="color:#2B2B2B">';
        if (c.type === 'date') {
          html += esc(fmtDate(item[c.name]));
        } else if (isStatusLike(c.name)) {
          html += renderBadge(item[c.name]);
        } else {
          var v = item[c.name];
          if (v) {
            html += esc(v);
          } else {
            html += '<span style="color:#E8D4DB">&mdash;</span>';
          }
        }
        if (c.name === 'stock' && low) html += ' <span class="ml-2 text-xs font-medium" style="color:#C13030">Low stock</span>';
        html += '</td>';
      });
      if (dispensable) {
        html += '<td class="px-4 py-3"><div class="flex items-center gap-1.5">' +
          '<input type="number" min="1" value="' + esc(ms.dispenseQty[item.id] || '') + '" class="dispense-qty w-16 rounded-md border px-2 py-1 text-xs" style="border-color:#E8D4DB;color:#2B2B2B" data-id="' + esc(item.id) + '" placeholder="qty" />' +
          '<button data-dispense="' + esc(item.id) + '" class="rounded-md px-2 py-1 text-xs font-medium text-white" style="background:#2A8B7A;hover:background:#1B6B5A">Dispense</button>' +
          '</div></td>';
      }
      html += '<td class="px-4 py-3 text-right"><div class="flex justify-end gap-1">' +
        '<button type="button" data-view="' + esc(item.id) + '" class="w-7 h-7 rounded flex items-center justify-center transition-colors" style="color:#7A7A7A" title="View" onmouseenter="this.style.color=\'#2563EB\'" onmouseleave="this.style.color=\'#7A7A7A\'">' + icon('eye', 13) + '</button>' +
        '<button type="button" data-edit="' + esc(item.id) + '" class="w-7 h-7 rounded flex items-center justify-center transition-colors" style="color:#7A7A7A" title="Edit" onmouseenter="this.style.color=\'#C9A227\'" onmouseleave="this.style.color=\'#7A7A7A\'">' + icon('pencil', 13) + '</button>' +
        (key === 'students' ? '<button type="button" data-view-medical-records="' + esc(item.studentId || item.id) + '" data-student-name="' + esc(item.name || '') + '" class="w-7 h-7 rounded flex items-center justify-center transition-colors" style="color:#7A7A7A" title="View Medical Records" onmouseenter="this.style.color=\'#2563EB\'" onmouseleave="this.style.color=\'#7A7A7A\'">' + icon('file-text', 13) + '</button><button type="button" data-create-medical-record="' + esc(item.id) + '" class="w-7 h-7 rounded flex items-center justify-center transition-colors" style="color:#7A7A7A" title="Create Medical Record" onmouseenter="this.style.color=\'#8B5CF6\'" onmouseleave="this.style.color=\'#7A7A7A\'">' + icon('heart-plus', 13) + '</button>' : '') +
        '<button type="button" data-delete="' + esc(item.id) + '" class="w-7 h-7 rounded flex items-center justify-center transition-colors" style="color:#7A7A7A" title="Delete" onmouseenter="this.style.color=\'#C13030\'" onmouseleave="this.style.color=\'#7A7A7A\'">' + icon('trash-2', 13) + '</button>' +
        '</div></td>';
      html += '</tr>';
    });
  }

  html += '</tbody></table></div></div>';

  /* pagination controls */
  html += '<div class="flex items-center justify-between mt-3">' +
    '<p class="text-xs" style="color:#7A7A7A">Showing ' + (filtered.length > 0 ? ((pg - 1) * perPage + 1) + '\u2013' + Math.min(pg * perPage, filtered.length) : '0') + ' of ' + filtered.length + '</p>' +
    '<div class="flex items-center gap-1">' +
    '<button data-page="' + (pg - 1) + '" class="w-7 h-7 rounded flex items-center justify-center disabled:opacity-30" style="background:#FFFFFF;color:#7A7A7A"' + (pg <= 1 ? ' disabled' : '') + '>' + icon('chevron-left', 13) + '</button>';
  for (var pi = 0; pi < Math.min(5, totalPages); pi++) {
    var pgn = pi + 1;
    var activeBg = ({
      blue: '#7B1028', green: '#2A8B4A', orange: '#C9A24E',
      purple: '#8B3A8B', red: '#C13030', indigo: '#2A1B6B',
      teal: '#2A8B7A', slate: '#5A4A62'
    })[config.color] || '#7B1028';
    html += '<button data-page="' + pgn + '" class="w-7 h-7 rounded text-xs font-medium" style="background:' + (pgn === pg ? activeBg : '#FFFFFF') + ';color:' + (pgn === pg ? '#FFFFFF' : '#7A7A7A') + '">' + pgn + '</button>';
  }
  html += '<button data-page="' + (pg + 1) + '" class="w-7 h-7 rounded flex items-center justify-center disabled:opacity-30" style="background:#FFFFFF;color:#7A7A7A"' + (pg >= totalPages ? ' disabled' : '') + '>' + icon('chevron-right', 13) + '</button>' +
    '</div></div>';

  /* Modal */
  if (ms.modalOpen) {
    var modalTitle = ms.editing ? 'Edit ' + config.title : 'Add ' + config.title;
    var modalContent = '';
    if (ms.error) {
      modalContent += '<div class="mb-4 rounded-lg px-3 py-2 text-sm" style="background:#FDF6F8;color:#C13030">' + esc(ms.error) + '</div>';
    }
    modalContent += '<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">';
    config.fields.forEach(function(f) {
      modalContent += '<div class="' + (f.type === 'textarea' ? 'sm:col-span-2' : '') + '">' +
        '<label class="mb-1 block text-xs font-medium" style="color:#5A4A62">' + esc(f.label) + (f.required ? '<span style="color:#C13030"> *</span>' : '') + '</label>' +
        renderFieldInput(f, ms.form[f.name], key + '_' + f.name) +
        '</div>';
    });
    modalContent += '</div>' +
      '<div class="mt-6 flex justify-end gap-3">' +
      '<button data-action="cancel-modal" class="rounded-lg border px-4 py-2 text-sm font-medium hover:bg-[#FDF6F8]" style="border-color:#E8D4DB;color:#5A4A62">Cancel</button>' +
      '<button data-action="save" data-module="' + key + '" class="rounded-lg px-4 py-2 text-sm font-medium text-white ' + BTN_COLOR_MAP[config.color] + '">' +
      (ms.editing ? 'Save Changes' : 'Add Record') + '</button>' +
      '</div>';
    html += renderModal(modalTitle, modalContent, true);
  }

  /* Medical record modal when opened from students view */
  var mms = getModuleState('medicalRecords');
  if (mms.modalOpen) {
    html += renderMedicalRecordModal(mms);
  }

  /* Enrollment Modal for Students */
  if (key === 'students' && ms.enrollmentModalOpen) {
    var enrollmentContent = '';
    if (ms.enrollmentError) {
      enrollmentContent += '<div class="mb-4 rounded-lg px-3 py-2 text-sm" style="background:#FDF6F8;color:#C13030">' + esc(ms.enrollmentError) + '</div>';
    }
    enrollmentContent += '<div class="space-y-6">' +
      '<div class="border-b pb-4" style="border-color:#E8D4DB">' +
      '<h3 class="text-sm font-medium mb-4 font-serif-heading" style="color:#2B2B2B">Personal Information</h3>' +
      '<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">' +
      '<div>' +
      '<label class="mb-1 block text-xs font-medium" style="color:#5A4A62">Full Name <span style="color:#C13030">*</span></label>' +
      '<input type="text" name="name" value="' + esc(ms.enrollmentForm.name || '') + '" class="w-full rounded-lg border px-3 py-2 text-sm" style="border-color:#E8D4DB" placeholder="Enter student\'s full name">' +
      '</div>' +
      '<div>' +
      '<label class="mb-1 block text-xs font-medium" style="color:#5A4A62">Student ID <span style="color:#C13030">*</span></label>' +
      '<input type="text" name="studentId" value="' + esc(ms.enrollmentForm.studentId || '') + '" class="w-full rounded-lg border px-3 py-2 text-sm" style="border-color:#E8D4DB" placeholder="e.g., BCP-2024-0001">' +
      '</div>' +
      '<div>' +
      '<label class="mb-1 block text-xs font-medium" style="color:#5A4A62">Course / Program</label>' +
      '<select name="course" class="w-full rounded-lg border px-3 py-2 text-sm" style="border-color:#E8D4DB">' +
      '<option value="">Select course</option>' +
      '<option value="BSIT"' + (ms.enrollmentForm.course === 'BSIT' ? ' selected' : '') + '>BSIT</option>' +
      '<option value="BSED"' + (ms.enrollmentForm.course === 'BSED' ? ' selected' : '') + '>BSED</option>' +
      '<option value="BSN"' + (ms.enrollmentForm.course === 'BSN' ? ' selected' : '') + '>BSN</option>' +
      '<option value="BSCS"' + (ms.enrollmentForm.course === 'BSCS' ? ' selected' : '') + '>BSCS</option>' +
      '<option value="BSA"' + (ms.enrollmentForm.course === 'BSA' ? ' selected' : '') + '>BSA</option>' +
      '<option value="BSBA"' + (ms.enrollmentForm.course === 'BSBA' ? ' selected' : '') + '>BSBA</option>' +
      '<option value="BSE"' + (ms.enrollmentForm.course === 'BSE' ? ' selected' : '') + '>BSE</option>' +
      '<option value="BSM"' + (ms.enrollmentForm.course === 'BSM' ? ' selected' : '') + '>BSM</option>' +
      '<option value="Other"' + (ms.enrollmentForm.course === 'Other' ? ' selected' : '') + '>Other</option>' +
      '</select>' +
      '</div>' +
      '<div>' +
      '<label class="mb-1 block text-xs font-medium" style="color:#5A4A62">Year Level</label>' +
      '<select name="yearLevel" class="w-full rounded-lg border px-3 py-2 text-sm" style="border-color:#E8D4DB">' +
      '<option value="">Select year level</option>' +
      '<option value="1st Year"' + (ms.enrollmentForm.yearLevel === '1st Year' ? ' selected' : '') + '>1st Year</option>' +
      '<option value="2nd Year"' + (ms.enrollmentForm.yearLevel === '2nd Year' ? ' selected' : '') + '>2nd Year</option>' +
      '<option value="3rd Year"' + (ms.enrollmentForm.yearLevel === '3rd Year' ? ' selected' : '') + '>3rd Year</option>' +
      '<option value="4th Year"' + (ms.enrollmentForm.yearLevel === '4th Year' ? ' selected' : '') + '>4th Year</option>' +
      '</select>' +
      '</div>' +
      '<div>' +
      '<label class="mb-1 block text-xs font-medium" style="color:#5A4A62">Status</label>' +
      '<select name="status" class="w-full rounded-lg border px-3 py-2 text-sm" style="border-color:#E8D4DB">' +
      '<option value="Active"' + (ms.enrollmentForm.status === 'Active' ? ' selected' : '') + '>Active</option>' +
      '<option value="Inactive"' + (ms.enrollmentForm.status === 'Inactive' ? ' selected' : '') + '>Inactive</option>' +
      '</select>' +
      '</div>' +
      '</div></div>' +
      '<div class="border-b pb-4" style="border-color:#E8D4DB">' +
      '<h3 class="text-sm font-medium mb-4 font-serif-heading" style="color:#2B2B2B">Medical Information</h3>' +
      '<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">' +
      '<div>' +
      '<label class="mb-1 block text-xs font-medium" style="color:#5A4A62">Blood Type</label>' +
      '<select name="bloodType" class="w-full rounded-lg border px-3 py-2 text-sm" style="border-color:#E8D4DB">' +
      '<option value="">Select blood type</option>' +
      '<option value="A+"' + (ms.enrollmentForm.bloodType === 'A+' ? ' selected' : '') + '>A+</option>' +
      '<option value="A-"' + (ms.enrollmentForm.bloodType === 'A-' ? ' selected' : '') + '>A-</option>' +
      '<option value="B+"' + (ms.enrollmentForm.bloodType === 'B+' ? ' selected' : '') + '>B+</option>' +
      '<option value="B-"' + (ms.enrollmentForm.bloodType === 'B-' ? ' selected' : '') + '>B-</option>' +
      '<option value="O+"' + (ms.enrollmentForm.bloodType === 'O+' ? ' selected' : '') + '>O+</option>' +
      '<option value="O-"' + (ms.enrollmentForm.bloodType === 'O-' ? ' selected' : '') + '>O-</option>' +
      '<option value="AB+"' + (ms.enrollmentForm.bloodType === 'AB+' ? ' selected' : '') + '>AB+</option>' +
      '<option value="AB-"' + (ms.enrollmentForm.bloodType === 'AB-' ? ' selected' : '') + '>AB-</option>' +
      '<option value="Unknown"' + (ms.enrollmentForm.bloodType === 'Unknown' ? ' selected' : '') + '>Unknown</option>' +
      '</select>' +
      '</div>' +
      '<div>' +
      '<label class="mb-1 block text-xs font-medium" style="color:#5A4A62">Allergies</label>' +
      '<input type="text" name="allergies" value="' + esc(ms.enrollmentForm.allergies || '') + '" class="w-full rounded-lg border px-3 py-2 text-sm" style="border-color:#E8D4DB" placeholder="e.g., Penicillin, Peanuts, None">' +
      '</div>' +
      '<div class="sm:col-span-2">' +
      '<label class="mb-1 block text-xs font-medium" style="color:#5A4A62">Medical Conditions</label>' +
      '<textarea name="conditions" rows="3" class="w-full rounded-lg border px-3 py-2 text-sm resize-none" style="border-color:#E8D4DB" placeholder="Any existing medical conditions, chronic illnesses, or important health information...">' + esc(ms.enrollmentForm.conditions || '') + '</textarea>' +
      '</div>' +
      '</div></div>' +
      '<div>' +
      '<h3 class="text-sm font-medium mb-4 font-serif-heading" style="color:#2B2B2B">Contact Information</h3>' +
      '<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">' +
      '<div>' +
      '<label class="mb-1 block text-xs font-medium" style="color:#5A4A62">Contact Number</label>' +
      '<input type="text" name="contactNumber" value="' + esc(ms.enrollmentForm.contactNumber || '') + '" class="w-full rounded-lg border px-3 py-2 text-sm" style="border-color:#E8D4DB" placeholder="e.g., 09171234567">' +
      '</div>' +
      '<div>' +
      '<label class="mb-1 block text-xs font-medium" style="color:#5A4A62">Emergency Contact</label>' +
      '<input type="text" name="emergencyContact" value="' + esc(ms.enrollmentForm.emergencyContact || '') + '" class="w-full rounded-lg border px-3 py-2 text-sm" style="border-color:#E8D4DB" placeholder="e.g., Maria Dela Cruz - 09179876543">' +
      '</div>' +
      '</div></div>' +
      '</div>' +
      '<div class="mt-6 flex justify-end gap-3">' +
      '<button data-action="cancel-enrollment-modal" class="rounded-lg border px-4 py-2 text-sm font-medium hover:bg-[#FDF6F8]" style="border-color:#E8D4DB;color:#5A4A62">Cancel</button>' +
      '<button data-action="submit-enrollment" class="rounded-lg px-4 py-2 text-sm font-medium text-white bg-[#7B1028] hover:bg-[#3F0D1D]">Enroll Student</button>' +
      '</div>';
    html += renderModal('Student Medical Enrollment', enrollmentContent, true);
  }

  /* View modal */
  if (ms.viewTarget) {
    var v = ms.items.find(function(i) { return i.id === ms.viewTarget; });
    if (v) {
      html += '<div id="modal-overlay" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.7)">' +
        '<div class="w-full max-w-md max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-xl">' +
        '<div class="flex items-center justify-between border-b px-6 py-4" style="border-color:#E8D4DB">' +
        '<h3 class="font-serif-heading text-base font-semibold" style="color:#2B2B2B">' + esc(config.title) + ' Details</h3>' +
        '<button data-view-close class="rounded-full p-1 hover:bg-[#FDF6F8]" style="color:#5A4A62">' + icon('x', 18) + '</button>' +
        '</div>' +
        '<div class="p-6 space-y-2">' +
        config.fields.filter(function(f) { return !f.hideInTable; }).map(function(f) {
          var val = v[f.name];
          if (f.type === 'date') val = fmtDate(val);
          return '<div class="flex justify-between p-2 rounded-lg" style="background:#FDF6F8">' +
            '<span class="text-[10px]" style="color:#7A7A7A">' + esc(f.label) + '</span>' +
            '<span class="text-xs" style="color:#2B2B2B">' + esc(val || '\u2014') + '</span>' +
            '</div>';
        }).join('') +
        '</div>' +
        '<div class="px-6 pb-6"><button data-view-close class="w-full py-2 rounded-lg text-xs" style="background:#FFFFFF;color:#5A4A62;border:1px solid #E8D4DB">Close</button></div>' +
        '</div></div>';
    }
  }

  /* Confirm dialog */
  if (ms.deleteTarget) {
    var primary = ms.deleteTarget[config.primary] || 'this record';
    html += renderConfirmDialog('Delete Record?', 'This will permanently remove "' + esc(primary) + '" from the database.', 'Delete', true);
  }

  html += '</div>';
  return html;
}

/* ============================== MAIN RENDER ============================== */
function render() {
  var root = document.getElementById('root');
  if (!root) return;

  if (state.authLoading) {
    root.innerHTML = renderLoadingScreen();
    return;
  }
  if (state.loggedOut) {
    root.innerHTML = renderLoggedOutScreen();
    return;
  }
  if (!state.currentUser) {
    root.innerHTML = renderLoginScreen();
    return;
  }

  if (state.currentView !== 'dashboard' && !canAccessView(state.currentView)) {
    state.currentView = 'dashboard';
  }

  var mainContent = '';
  if (state.currentView === 'dashboard') {
    mainContent = renderDashboard();
  } else if (state.currentView === 'reports') {
    mainContent = renderReports();
  } else if (state.currentView === 'appointments') {
    mainContent = renderAppointmentsModule();
  } else if (state.currentView === 'visits') {
    mainContent = renderVisitsModule();
  } else if (state.currentView === 'medicalRecords') {
    mainContent = renderMedicalRecordsModule();
  } else if (MODULES[state.currentView]) {
    mainContent = renderCrudModule(state.currentView);
  } else {
    mainContent = '<div style="color:#5A4A62">Page not found</div>';
  }

  root.innerHTML = renderSidebar() +
    '<div class="flex min-w-0 flex-1 flex-col">' +
    renderTopbar() +
    renderToast() +
    '<main class="flex-1 p-5">' + mainContent + '</main>' +
    '</div>' +
    renderMobileNav() +
    (state.showLogoutConfirm ? renderConfirmDialog('Confirm Logout', 'Are you sure you want to log out of the system?', 'Log Out', true) : '');

  lucide.createIcons();

  /* Initialize chart if on dashboard */
  if (state.currentView === 'dashboard' && !state.loadingData && state.dashData) {
    setTimeout(initDashboardChart, 50);
  }
}

function initDashboardChart() {
  var barCanvas = document.getElementById('visits-bar-chart');
  var doughnutCanvas = document.getElementById('visits-doughnut-chart');

  /* ===== BAR CHART ===== */
  if (barCanvas) {
    if (window.dashboardBarChart) { window.dashboardBarChart.destroy(); window.dashboardBarChart = null; }
    var data = state.dashData.chartData || [];
    var labels = data.length ? data.map(function(d) { return d.day; }) : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    var values = data.length ? data.map(function(d) { return d.visits; }) : [0, 0, 0, 0, 0, 0, 0];
    var ctx = barCanvas.getContext('2d');
    if (ctx) {
      window.dashboardBarChart = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [{
            label: 'Total Visits',
            data: values,
            backgroundColor: '#7B1028',
            borderRadius: 4,
            barThickness: 16,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              backgroundColor: '#150E18',
              titleColor: '#C9A24E',
              bodyColor: '#F0ECF2',
              borderColor: 'rgba(201,162,78,0.2)',
              borderWidth: 1,
              padding: 8,
              cornerRadius: 8,
              callbacks: {
                label: function(item) { return 'Visits: ' + item.raw; },
              },
            },
          },
          scales: {
            x: {
              grid: { display: false },
              ticks: { font: { size: 11 }, color: '#5A4A62' },
            },
            y: {
              ticks: { font: { size: 11 }, color: '#5A4A62', stepSize: 1 },
              grid: { color: 'rgba(201,162,78,0.06)', drawTicks: false },
            },
          },
        },
      });
    }
  }

  /* ===== DOUGHNUT CHART ===== */
  if (doughnutCanvas) {
    if (window.dashboardDoughnutChart) { window.dashboardDoughnutChart.destroy(); window.dashboardDoughnutChart = null; }

    var visits = state.dashData.visits || [];
    var typeCounts = {};
    visits.forEach(function(v) {
      var complaint = (v.complaint || v.chiefComplaint || v.chief_complaint || v.diagnosis || '').toLowerCase();
      var type = 'General Illness';
      if (/injure|wound|fracture|cut|laceration|sprain|accident/i.test(complaint)) type = 'Injury/Accident';
      else if (/follow|check|monitor|routine/i.test(complaint)) type = 'Follow-up';
      else if (/dental|tooth|teeth|gum/i.test(complaint)) type = 'Dental';
      else if (/anxiety|depression|mental|stress|counsel/i.test(complaint)) type = 'Mental Health';
      typeCounts[type] = (typeCounts[type] || 0) + 1;
    });

    var consultationTypes = [
      { name: 'General Illness', value: 0, color: '#7B1028' },
      { name: 'Injury/Accident', value: 0, color: '#C9A24E' },
      { name: 'Follow-up', value: 0, color: '#2A6B9B' },
      { name: 'Dental', value: 0, color: '#2A8B4A' },
      { name: 'Mental Health', value: 0, color: '#8B4A2A' },
    ];
    var hasData = false;
    consultationTypes.forEach(function(t) {
      if (typeCounts[t.name]) { t.value = typeCounts[t.name]; hasData = true; }
    });
    if (!hasData) {
      consultationTypes = [
        { name: 'General Illness', value: 38, color: '#7B1028' },
        { name: 'Injury/Accident', value: 22, color: '#C9A24E' },
        { name: 'Follow-up', value: 18, color: '#2A6B9B' },
        { name: 'Dental', value: 12, color: '#2A8B4A' },
        { name: 'Mental Health', value: 10, color: '#8B4A2A' },
      ];
    }

    var ctx2 = doughnutCanvas.getContext('2d');
    if (ctx2) {
      window.dashboardDoughnutChart = new Chart(ctx2, {
        type: 'doughnut',
        data: {
          labels: consultationTypes.map(function(t) { return t.name; }),
          datasets: [{
            data: consultationTypes.map(function(t) { return t.value; }),
            backgroundColor: consultationTypes.map(function(t) { return t.color; }),
            borderWidth: 0,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '55%',
          plugins: {
            legend: { display: false },
            tooltip: {
              backgroundColor: '#150E18',
              titleColor: '#C9A24E',
              bodyColor: '#F0ECF2',
              borderColor: 'rgba(201,162,78,0.2)',
              borderWidth: 1,
              padding: 8,
              cornerRadius: 8,
              callbacks: {
                label: function(item) {
                  var total = item.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                  return item.label + ': ' + (total > 0 ? Math.round(item.raw / total * 100) : 0) + '%';
                },
              },
            },
          },
        },
      });
    }

    /* Legend */
    var legendEl = document.getElementById('consultation-legend');
    if (legendEl) {
      var total = consultationTypes.reduce(function(s, t) { return s + t.value; }, 0);
      legendEl.innerHTML = consultationTypes.map(function(t) {
        var pct = total > 0 ? Math.round(t.value / total * 100) : 0;
        return '<div class="flex items-center justify-between">' +
          '<div class="flex items-center gap-2">' +
          '<div class="w-2 h-2 rounded-full shrink-0" style="background:' + t.color + '"></div>' +
          '<span class="text-[10px]" style="color:#7A7A7A">' + t.name + '</span>' +
          '</div>' +
          '<span class="text-[10px] font-medium font-mono-data" style="color:#C9A24E">' + pct + '%</span>' +
          '</div>';
      }).join('');
    }
  }
}

/* ============================== EVENT HANDLING ============================== */
function setupEvents() {
  document.addEventListener('click', function(e) {
    var target = e.target.closest('[data-nav]');
    if (target) {
      e.preventDefault();
      navigateTo(target.getAttribute('data-nav'));
      return;
    }

    target = e.target.closest('[data-action="logout"]');
    if (target) {
      e.preventDefault();
      state.showLogoutConfirm = true;
      render();
      return;
    }

    target = e.target.closest('[data-action="close-modal"]');
    if (target) {
      e.preventDefault();
      closeModalForCurrentView();
      return;
    }

    target = e.target.closest('[data-action="close-medical-record-modal"]');
    if (target) {
      e.preventDefault();
      var mms = getModuleState('medicalRecords');
      mms.modalOpen = false;
      mms.editing = null;
      mms.error = '';
      mms.form = {};
      renderMainContent();
      return;
    }

    target = e.target.closest('[data-action="cancel-modal"]');
    if (target) {
      e.preventDefault();
      closeModalForCurrentView();
      return;
    }

    target = e.target.closest('[data-action="cancel-confirm"]');
    if (target) {
      state.showLogoutConfirm = false;
      var ms = getModuleState(state.currentView);
      ms.deleteTarget = null;
      render();
      return;
    }

    target = e.target.closest('[data-action="confirm-action"]');
    if (target) {
      if (state.showLogoutConfirm) {
        doLogout();
      } else {
        doDelete(state.currentView);
      }
      return;
    }

    target = e.target.closest('[data-action="add"]');
    if (target) {
      var mk = target.getAttribute('data-module');
      openAddForm(mk);
      return;
    }

    target = e.target.closest('[data-add]');
    if (target) {
      var mk = target.getAttribute('data-add');
      openAddForm(mk);
      return;
    }

    target = e.target.closest('[data-action="open-enrollment-modal"]');
    if (target) {
      openEnrollmentModal();
      return;
    }

    target = e.target.closest('[data-action="cancel-enrollment-modal"]');
    if (target) {
      closeEnrollmentModal();
      return;
    }

    target = e.target.closest('[data-action="submit-enrollment"]');
    if (target) {
      submitEnrollment();
      return;
    }

    target = e.target.closest('[data-view-medical-records]');
    if (target) {
      e.preventDefault();
      var studentId = target.getAttribute('data-view-medical-records');
      var studentName = target.getAttribute('data-student-name');
      (async function() {
        try {
          var sid = String(studentId || '').trim().toLowerCase();
          var sname = String(studentName || '').trim().toLowerCase();
          var records = await API.list('medicalRecords');
          var rec = records.find(function(r) {
            return String(r.studentId || '').trim().toLowerCase() === sid &&
                   String(r.studentName || '').trim().toLowerCase() === sname;
          });
          if (!rec) {
            showToast('No medical record found for this student.', 'warning');
            return;
          }
          var folder = rec.groupFolder || '';
          var groups = await API.listRecordFolders();
          var mms = getModuleState('medicalRecords');
          var folderIdx = groups.findIndex(function(g) { return g.name === folder; });
          var folderPage = 1;
          if (folderIdx >= 0) folderPage = Math.floor(folderIdx / 10) + 1;
          var groupRecs = records.filter(function(r) { return (r.groupFolder || '') === folder; });
          var groupIdx = groupRecs.findIndex(function(r) { return String(r.studentId || '').trim().toLowerCase() === sid; });
          var groupPage = 1;
          if (groupIdx >= 0) groupPage = Math.floor(groupIdx / 25) + 1;
          state.currentView = 'medicalRecords';
          mms.folderPage = folderPage;
          mms.groupPage = groupPage;
          mms.groupSelected = folder;
          mms.expandedFolders = {};
          mms.expandedFolders[folder] = true;
          mms.viewTarget = null;
          mms.groups = groups;
          mms.items = records;
          mms.loading = false;
          saveState();
          renderMainContent();
          setTimeout(function() {
            var sel = '[data-record-id="' + rec.id.replace(/"/g, '\\"') + '"]';
            var el = document.querySelector(sel);
            if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
          }, 100);
        } catch (e) {
          showToast('Could not load medical records.', 'error');
        }
      })();
      return;
    }

    target = e.target.closest('[data-create-medical-record]');
    if (target) {
      openMedicalRecordFromStudent(target.getAttribute('data-create-medical-record'));
      return;
    }

    

    target = e.target.closest('[data-action="import-students"]');
    if (target) {
      triggerImportStudents();
      return;
    }

    target = e.target.closest('[data-action="export-students"]');
    if (target) {
      exportStudents();
      return;
    }

    target = e.target.closest('[data-action="import-visits"]');
    if (target) {
      triggerImportVisits();
      return;
    }

    target = e.target.closest('[data-action="generate-filtered-visits"]');
    if (target) {
      generateFilteredVisits();
      return;
    }

    target = e.target.closest('[data-action="save"]');
    if (target) {
      var mk = target.getAttribute('data-module');
      doSave(mk);
      return;
    }

    target = e.target.closest('[data-edit]');
    if (target) {
      var id = target.getAttribute('data-edit');
      openEditForm(state.currentView, id);
      return;
    }

    target = e.target.closest('[data-delete]');
    if (target) {
      var id = target.getAttribute('data-delete');
      confirmDelete(state.currentView, id);
      return;
    }

    target = e.target.closest('[data-dispense]');
    if (target) {
      var id = target.getAttribute('data-dispense');
      doDispense(state.currentView, id);
      return;
    }

    /* Sidebar collapse toggle */
    target = e.target.closest('#sidebar-toggle');
    if (target) {
      e.preventDefault();
      state.sidebarCollapsed = !state.sidebarCollapsed;
      render();
      return;
    }

    /* Notifications */
    target = e.target.closest('#notif-btn');
    if (target) {
      e.preventDefault();
      toggleNotifications();
      return;
    }

    target = e.target.closest('#notif-close');
    if (target) {
      e.preventDefault();
      var dd = document.getElementById('notif-dropdown');
      if (dd) dd.classList.add('hidden');
      return;
    }

    /* User menu */
    target = e.target.closest('#user-menu-btn');
    if (target) {
      e.preventDefault();
      toggleUserMenu();
      return;
    }

    /* Forgot password */
    target = e.target.closest('#forgot-password');
    if (target) {
      e.preventDefault();
      var infoEl = document.getElementById('login-info');
      if (infoEl) {
        infoEl.textContent = 'Please contact your system administrator to reset your password.';
        infoEl.classList.remove('hidden');
      }
      return;
    }

    /* Google sign-in */
    target = e.target.closest('#google-signin');
    if (target) {
      e.preventDefault();
      var infoEl = document.getElementById('login-info');
      if (infoEl) {
        infoEl.textContent = 'Google sign-in isn\'t configured for this demo \u2014 please use the username and password form above.';
        infoEl.classList.remove('hidden');
      }
      return;
    }

    /* Login again */
    target = e.target.closest('#login-again-btn');
    if (target) {
      state.loggedOut = false;
      render();
      return;
    }

    /* Refresh reports */
    target = e.target.closest('#refresh-reports');
    if (target) {
      loadReportsData();
      return;
    }

    /* Export CSV */
    target = e.target.closest('[data-export]');
    if (target) {
      var key = target.getAttribute('data-export');
      var filename = target.getAttribute('data-filename');
      var data = state.reportsData.records[key] || [];
      exportCSV(filename, data);
      return;
    }

    /* View button */
    target = e.target.closest('[data-view]');
    if (target) {
      var viewId = target.getAttribute('data-view');
      var ms = getModuleState(state.currentView);
      var item = ms.items.find(function(i) { return i.id === viewId; });
      if (item) { ms.viewTarget = viewId; renderMainContent(); }
      return;
    }

    /* View close */
    target = e.target.closest('[data-view-close]');
    if (target) {
      var ms = getModuleState(state.currentView);
      ms.viewTarget = null;
      renderMainContent();
      return;
    }

    target = e.target.closest('[data-action="close-modal"]');
    if (target) {
      closeModalForCurrentView();
      return;
    }

    target = e.target.closest('[data-action="cancel-group-modal"]');
    if (target) {
      var ms = getModuleState('medicalRecords');
      ms.newGroupModalOpen = false;
      ms.groupRenameModalOpen = false;
      ms.groupError = '';
      ms.groupRenameError = '';
      ms.groupForm = {};
      ms.groupRenameForm = {};
      renderMainContent();
      return;
    }

    target = e.target.closest('[data-action="modal-add-record"]');
    if (target) {
      var ms = getModuleState('medicalRecords');
      if (ms.folderModalGroup) {
        ms.groupSelected = ms.folderModalGroup;
        openAddForm('medicalRecords');
      }
      return;
    }


    target = e.target.closest('[data-folder-page]');
    if (target) {
      e.preventDefault();
      var pg = parseInt(target.getAttribute('data-folder-page'), 10);
      if (isNaN(pg) || pg < 1) return;
      var ms = getModuleState('medicalRecords');
      ms.folderPage = pg;
      saveState();
      renderMainContent();
      return;
    }

    target = e.target.closest('[data-add-record-group]');
    if (target) {
      e.preventDefault();
      var name = target.getAttribute('data-add-record-group');
      var ms = getModuleState('medicalRecords');
      ms.groupSelected = name;
      openAddForm('medicalRecords');
      return;
    }

    target = e.target.closest('[data-action="toggle-folder"]');
    if (target) {
      e.preventDefault();
      var name = target.getAttribute('data-group-name');
      var ms = getModuleState('medicalRecords');
      ms.expandedFolders = ms.expandedFolders || {};
      var isOpen = !ms.expandedFolders[name];
      ms.expandedFolders[name] = isOpen;
      if (isOpen) {
        ms.groupSelected = name;
      }
      renderMainContent();
      saveState();
      return;
    }

    target = e.target.closest('[data-group-page]');
    if (target) {
      e.preventDefault();
      var p = parseInt(target.getAttribute('data-group-page'), 10);
      var ms = getModuleState('medicalRecords');
      if (!isNaN(p) && p > 0) {
        ms.groupPage = p;
        saveState();
        renderMainContent();
      }
      return;
    }

    /* Create new record group */
    target = e.target.closest('[data-action="create-record-group"]');
    if (target) {
      e.preventDefault();
      var ms = getModuleState('medicalRecords');
      ms.newGroupModalOpen = true;
      ms.groupForm = ms.groupForm || { name: '', description: '', yearLevel: '', department: '', type: 'Student' };
      ms.groupError = '';
      renderMainContent();
      return;
    }

    /* Rename record group */
    target = e.target.closest('[data-action="rename-record-group"]');
    if (target) {
      e.preventDefault();
      var name = target.getAttribute('data-group-name');
      var newName = prompt('Rename folder', name);
      if (!newName || !newName.trim() || newName.trim() === name) return;
      newName = newName.trim();
      var ms = getModuleState('medicalRecords');
      ms.groups = undefined;
      renderMainContent();
      (async function() {
        try {
          await API.renameRecordFolder({ currentName: name, newName: newName });
          if (ms.groupSelected === name) ms.groupSelected = newName;
          if (ms.expandedFolders) {
            ms.expandedFolders[newName] = ms.expandedFolders[name];
            delete ms.expandedFolders[name];
          }
          loadModuleData('medicalRecords');
        } catch (e) {
          ms.groups = [];
          renderMainContent();
        }
      })();
      return;
    }

    /* Delete record group */
    target = e.target.closest('[data-action="delete-record-group"]');
    if (target) {
      e.preventDefault();
      var name = target.getAttribute('data-group-name');
      if (!confirm('Delete folder "' + name + '"? Records inside will remain.')) return;
      var ms = getModuleState('medicalRecords');
      ms.groups = undefined;
      renderMainContent();
      (async function() {
        try {
          await API.deleteRecordFolder(name);
          if (ms.expandedFolders) delete ms.expandedFolders[name];
          if (ms.groupSelected === name) ms.groupSelected = '';
          loadModuleData('medicalRecords');
        } catch (e) {
          ms.groups = [];
          renderMainContent();
        }
      })();
      return;
    }


    /* Appointment status cards */
    target = e.target.closest('[data-appt-filter]');
    if (target) {
      var ms = getModuleState('appointments');
      ms.statusFilter = target.getAttribute('data-appt-filter');
      ms.page = 1;
      renderMainContent();
      return;
    }

    /* Appointment status update */
    target = e.target.closest('[data-status-update]');
    if (target) {
      var val = target.getAttribute('data-status-update') || '';
      var sepIdx = val.lastIndexOf('-');
      var aId = val.slice(0, sepIdx);
      var newStatus = val.slice(sepIdx + 1);
      (async function() {
        var ms = getModuleState('appointments');
        try { await API.update('appointments', aId, { status: newStatus }); } catch (e) {}
        await loadModuleData('appointments');
      })();
      return;
    }

    /* Pagination */
    target = e.target.closest('[data-page]');
    if (target) {
      var pg = parseInt(target.getAttribute('data-page'), 10);
      if (isNaN(pg) || pg < 1) return;
      var ms = getModuleState(state.currentView);
      ms.page = pg;
      renderMainContent();
      return;
    }

    /* Export appointments */
    target = e.target.closest('#export-appointments');
    if (target) {
      var ms = getModuleState('appointments');
      exportCSV('appointments.csv', ms.items);
      return;
    }

    /* Export visits */
    target = e.target.closest('#export-visits');
    if (target) {
      var ms = getModuleState('visits');
      exportCSV('clinic_visits.csv', ms.items);
      return;
    }
  });

  /* Form submission */
  document.addEventListener('submit', function(e) {
    var form = e.target;
    if (form.id === 'login-form') {
      e.preventDefault();
      handleLoginSubmit();
    }
    var saveKey = form.getAttribute('data-save-form');
    if (saveKey) {
      e.preventDefault();
      handleFormSubmit(saveKey);
    }
  });

  /* Search input */
  document.addEventListener('input', function(e) {
    var target = e.target;
    var searchKey = target.getAttribute('data-search');
    if (searchKey) {
      var ms = getModuleState(searchKey);
      ms.search = target.value;
      ms.page = 1;
      renderMainContent();
    }
    var groupsKey = target.getAttribute('data-groups-select');
    if (groupsKey) {
      var ms2 = getModuleState(groupsKey);
      ms2.groupSelected = target.value;
      renderMainContent();
      return;
    }
    if (target.name === 'type' && target.form && target.form.getAttribute('data-save-form') === 'medicalRecordGroup') {
      var val = target.value;
      var modalForm = target.form;
      var yl = modalForm.querySelector('[name="yearLevel"]');
      var sec = modalForm.querySelector('[name="section"]');
      try { if (yl) yl.closest('div').style.display = (val === 'Faculty' ? 'none' : 'block'); } catch (e) {}
      try { if (sec) sec.closest('div').style.display = (val === 'Faculty' ? 'none' : 'block'); } catch (e) {}
      return;
    }
  });

  /* Dispense quantity input */
  document.addEventListener('input', function(e) {
    var target = e.target;
    if (target.classList.contains('dispense-qty')) {
      var id = target.getAttribute('data-id');
      var mk = state.currentView;
      var ms = getModuleState(mk);
      ms.dispenseQty[id] = target.value;
    }
  });

  /* Filter change events */
  document.addEventListener('change', function(e) {
    var target = e.target;
    /* Appointments date filter */
    if (target.matches('[data-appt-date-filter]')) {
      var ms = getModuleState('appointments');
      ms.dateFilter = target.value;
      ms.page = 1;
      renderMainContent();
      return;
    }
    /* Appointments status filter */
    if (target.matches('[data-appt-status-filter]')) {
      var ms = getModuleState('appointments');
      ms.statusFilter = target.value;
      ms.page = 1;
      renderMainContent();
      return;
    }
    /* Visits date filter - removed auto-apply, now uses Generate button */
    /* Visits type filter - removed auto-apply, now uses Generate button */
    /* Visits disposition filter - removed auto-apply, now uses Generate button */
    /* Generic module filter dropdowns */
    if (target.matches('[data-filter]')) {
      var filterKey = target.getAttribute('data-filter') || '';
      var sep = filterKey.indexOf('-');
      var mk = filterKey.slice(0, sep);
      var fieldName = filterKey.slice(sep + 1);
      var ms = getModuleState(mk);
      ms[fieldName + 'Filter'] = target.value;
      ms.page = 1;
      renderMainContent();
      return;
    }
  });

  /* Toggle password visibility */
  document.addEventListener('click', function(e) {
    var target = e.target.closest('#toggle-password');
    if (target) {
      var pwInput = document.getElementById('login-password');
      if (pwInput) {
        if (pwInput.type === 'password') {
          pwInput.type = 'text';
          target.innerHTML = icon('eye-off', 15).trim();
        } else {
          pwInput.type = 'password';
          target.innerHTML = icon('eye', 15).trim();
        }
        lucide.createIcons();
      }
      return;
    }
  });
}

/* ============================== ACTIONS ============================== */

function navigateTo(view) {
  if (!canAccessView(view)) {
    view = 'dashboard';
  }
  if (state.currentView === view) return;
  state.currentView = view;
  state.showLogoutConfirm = false;

  /* Reset module state if switching away from a module */
  if (state.currentView === 'dashboard' || state.currentView === 'reports') {
    /* load data */
  }

  render();

  if (view === 'dashboard') {
    loadDashboardData();
  } else if (view === 'reports') {
    loadReportsData();
  } else if (MODULES[view]) {
    loadModuleData(view);
  }
  saveState();
}

function renderMainContent() {
  var mainEl = document.querySelector('#root main');
  if (!mainEl) return;
  var mainContent = '';
  if (state.currentView === 'dashboard') {
    mainContent = renderDashboard();
    mainEl.innerHTML = mainContent;
    if (state.currentView === 'dashboard' && !state.loadingData && state.dashData) {
      setTimeout(initDashboardChart, 50);
    }
  } else if (state.currentView === 'reports') {
    mainContent = renderReports();
    mainEl.innerHTML = mainContent;
  } else if (state.currentView === 'appointments') {
    mainContent = renderAppointmentsModule();
    mainEl.innerHTML = mainContent;
  } else if (state.currentView === 'visits') {
    mainContent = renderVisitsModule();
    mainEl.innerHTML = mainContent;
  } else if (state.currentView === 'medicalRecords') {
    mainContent = renderMedicalRecordsModule();
    mainEl.innerHTML = mainContent;
  } else if (MODULES[state.currentView]) {
    mainContent = renderCrudModule(state.currentView);
    mainEl.innerHTML = mainContent;
  }
  lucide.createIcons();

  // Add input event listeners for enrollment modal
  if (state.currentView === 'students') {
    var ms = getModuleState('students');
    if (ms.enrollmentModalOpen) {
      var enrollmentInputs = document.querySelectorAll('#modal-overlay input, #modal-overlay select, #modal-overlay textarea');
      enrollmentInputs.forEach(function(input) {
        input.addEventListener('input', function() {
          ms.enrollmentForm[input.name] = input.value;
        });
      });
    }

    // Add file input listener for import
    var fileInput = document.getElementById('import-file-input');
    if (fileInput) {
      fileInput.addEventListener('change', handleImportStudents);
    }
  }

  // Add file input listener for visits import
  if (state.currentView === 'visits') {
    var visitsFileInput = document.getElementById('import-visits-file-input');
    if (visitsFileInput) {
      visitsFileInput.addEventListener('change', handleImportVisits);
    }
  }
}

function closeModalForCurrentView() {
  if (MODULES[state.currentView]) {
    var ms = getModuleState(state.currentView);
    ms.modalOpen = false;
    ms.editing = null;
    ms.error = '';
    if (state.currentView === 'students') {
      ms.enrollmentModalOpen = false;
      ms.enrollmentForm = {};
      ms.enrollmentError = '';
    }
    if (state.currentView === 'medicalRecords') {
      ms.folderActionModalOpen = false;
      ms.folderModalGroup = '';
      ms.folderModalPage = 1;
      ms.newGroupModalOpen = false;
      ms.groupRenameModalOpen = false;
      ms.groupError = '';
      ms.groupRenameError = '';
      ms.groupForm = {};
      ms.groupRenameForm = {};
    }
    renderMainContent();
  }
}

function openEnrollmentModal() {
  var ms = getModuleState('students');
  ms.enrollmentModalOpen = true;
  ms.enrollmentForm = {};
  ms.enrollmentError = '';
  renderMainContent();
}

function closeEnrollmentModal() {
  var ms = getModuleState('students');
  ms.enrollmentModalOpen = false;
  ms.enrollmentForm = {};
  ms.enrollmentError = '';
  renderMainContent();
}

async function submitEnrollment() {
  var ms = getModuleState('students');
  var modal = document.querySelector('#modal-overlay');
  if (!modal) return;

  var inputs = modal.querySelectorAll('input, select, textarea');
  var data = {};
  inputs.forEach(function(input) {
    data[input.name] = input.value;
  });

  // Update form state with current values
  ms.enrollmentForm = data;

  // Validate required fields
  if (!data.name || !data.studentId) {
    ms.enrollmentError = 'Please fill in all required fields';
    renderMainContent();
    return;
  }

  // Add default values for empty fields
  data.course = data.course || '';
  data.yearLevel = data.yearLevel || '';
  data.status = data.status || 'Active';
  data.bloodType = data.bloodType || 'Unknown';
  data.allergies = data.allergies || 'None';
  data.conditions = data.conditions || 'None';
  data.contactNumber = data.contactNumber || '';
  data.emergencyContact = data.emergencyContact || '';

  if (!API.getToken()) {
    ms.enrollmentError = 'Your session expired. Please log in again.';
    showToast(ms.enrollmentError, 'error');
    renderMainContent();
    return;
  }

  try {
    await API.create('students', data);
    ms.enrollmentModalOpen = false;
    ms.enrollmentForm = {};
    ms.enrollmentError = '';
    await loadModuleData('students');
    showToast('Student enrolled successfully.', 'success');
    renderMainContent();
  } catch (error) {
    console.error('Error:', error);
    ms.enrollmentError = error.message || 'Failed to enroll student. Please try again.';
    showToast(ms.enrollmentError, 'error');
    renderMainContent();
  }
}

function showToast(message, type) {
  state.toast = { message: message || '', type: type || 'success' };
  if (state.toastTimeout) {
    clearTimeout(state.toastTimeout);
  }
  state.toastTimeout = setTimeout(function() {
    state.toast = null;
    state.toastTimeout = null;
    render();
  }, 3000);
  render();
}

function renderToast() {
  if (!state.toast || !state.toast.message) return '';
  var colors = state.toast.type === 'error'
    ? { bg: '#FEE2E2', border: '#F5C6CB', text: '#9B1B1B' }
    : { bg: '#ECFDF5', border: '#34D399', text: '#166534' };
  return '<div id="toast-message" class="fixed right-5 top-24 z-50 max-w-sm rounded-2xl border px-4 py-3 text-sm shadow-xl" ' +
    'style="background:' + colors.bg + ';border-color:' + colors.border + ';color:' + colors.text + ';">' +
    esc(state.toast.message) +
    '</div>';
}

function triggerImportStudents() {
  var fileInput = document.getElementById('import-file-input');
  if (fileInput) {
    fileInput.click();
  }
}

function handleImportStudents(event) {
  var file = event.target.files[0];
  if (!file) return;

  var reader = new FileReader();
  reader.onload = async function(e) {
    var data = e.target.result;
    var rows = data.split('\n');
    var headers = rows[0].split(',').map(function(h) { return h.trim(); });
    var students = [];

    for (var i = 1; i < rows.length; i++) {
      if (rows[i].trim() === '') continue;
      var values = rows[i].split(',');
      var student = {};
      headers.forEach(function(header, index) {
        student[header] = values[index] ? values[index].trim() : '';
      });
      
      // Map CSV headers to database fields
      var mappedStudent = {
        name: student['Full Name'] || student['name'] || '',
        studentId: student['Student ID'] || student['studentId'] || '',
        course: student['Course'] || student['course'] || '',
        yearLevel: student['Year Level'] || student['yearLevel'] || '',
        status: student['Status'] || student['status'] || 'Active',
        bloodType: student['Blood Type'] || student['bloodType'] || 'Unknown',
        allergies: student['Allergies'] || student['allergies'] || 'None',
        conditions: student['Medical Conditions'] || student['conditions'] || 'None',
        contactNumber: student['Contact Number'] || student['contactNumber'] || '',
        emergencyContact: student['Emergency Contact'] || student['emergencyContact'] || ''
      };

      if (mappedStudent.name && mappedStudent.studentId) {
        students.push(mappedStudent);
      }
    }

    // Import students
    for (var j = 0; j < students.length; j++) {
      try {
        await API.create('students', students[j]);
      } catch (error) {
        console.error('Error importing student:', error);
      }
    }

    // Refresh data
    await loadModuleData('students');
    renderMainContent();
    alert('Imported ' + students.length + ' students successfully!');
  };

  reader.readAsText(file);
}

function exportStudents() {
  var ms = getModuleState('students');
  var students = ms.items || [];

  if (students.length === 0) {
    alert('No students to export');
    return;
  }

  // Create CSV content
  var headers = ['Full Name', 'Student ID', 'Course', 'Year Level', 'Status', 'Blood Type', 'Allergies', 'Medical Conditions', 'Contact Number', 'Emergency Contact'];
  var csvContent = headers.join(',') + '\n';

  students.forEach(function(student) {
    var row = [
      '"' + (student.name || '') + '"',
      '"' + (student.studentId || '') + '"',
      '"' + (student.course || '') + '"',
      '"' + (student.yearLevel || '') + '"',
      '"' + (student.status || 'Active') + '"',
      '"' + (student.bloodType || '') + '"',
      '"' + (student.allergies || '') + '"',
      '"' + (student.conditions || '') + '"',
      '"' + (student.contactNumber || '') + '"',
      '"' + (student.emergencyContact || '') + '"'
    ];
    csvContent += row.join(',') + '\n';
  });

  // Create download link
  var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  var link = document.createElement('a');
  var url = URL.createObjectURL(blob);
  link.setAttribute('href', url);
  link.setAttribute('download', 'students_export_' + new Date().toISOString().split('T')[0] + '.csv');
  link.style.visibility = 'hidden';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

function triggerImportVisits() {
  var fileInput = document.getElementById('import-visits-file-input');
  if (fileInput) {
    fileInput.click();
  }
}

function handleImportVisits(event) {
  var file = event.target.files[0];
  if (!file) return;

  var reader = new FileReader();
  reader.onload = async function(e) {
    var data = e.target.result;
    var rows = data.split('\n');
    var headers = rows[0].split(',').map(function(h) { return h.trim(); });
    var visits = [];

    for (var i = 1; i < rows.length; i++) {
      if (rows[i].trim() === '') continue;
      var values = rows[i].split(',');
      var visit = {};
      headers.forEach(function(header, index) {
        visit[header] = values[index] ? values[index].trim() : '';
      });
      
      // Map CSV headers to database fields
      var mappedVisit = {
        patientName: visit['Patient Name'] || visit['patientName'] || '',
        patientType: visit['Patient Type'] || visit['patientType'] || 'Student',
        date: visit['Date'] || visit['date'] || new Date().toISOString().slice(0, 10),
        time: visit['Time'] || visit['time'] || new Date().toTimeString().slice(0, 5),
        complaint: visit['Chief Complaint'] || visit['complaint'] || '',
        diagnosis: visit['Diagnosis'] || visit['diagnosis'] || '',
        treatment: visit['Treatment Given'] || visit['treatment'] || '',
        temperature: visit['Temperature (°C)'] || visit['temperature'] || '',
        bloodPressure: visit['Blood Pressure (mmHg)'] || visit['bloodPressure'] || '',
        pulseRate: visit['Pulse Rate (bpm)'] || visit['pulseRate'] || '',
        assessment: visit['Assessment'] || visit['assessment'] || '',
        medicineDispensed: visit['Medicine Dispensed'] || visit['medicineDispensed'] || '',
        nurseOnDuty: visit['Nurse on Duty'] || visit['nurseOnDuty'] || '',
        disposition: visit['Disposition'] || visit['disposition'] || 'Treated & Discharged'
      };

      if (mappedVisit.patientName && mappedVisit.date) {
        visits.push(mappedVisit);
      }
    }

    // Import visits
    for (var j = 0; j < visits.length; j++) {
      try {
        await API.create('visits', visits[j]);
      } catch (error) {
        console.error('Error importing visit:', error);
      }
    }

    // Refresh data
    await loadModuleData('visits');
    renderMainContent();
    alert('Imported ' + visits.length + ' visits successfully!');
  };

  reader.readAsText(file);
}

function generateFilteredVisits() {
  var ms = getModuleState('visits');
  
  // Get filter values from DOM
  var searchInput = document.querySelector('[data-search="visits"]');
  var dateInput = document.querySelector('[data-visit-date-filter]');
  var typeSelect = document.querySelector('[data-visit-type-filter]');
  var dispSelect = document.querySelector('[data-visit-disp-filter]');
  
  if (searchInput) ms.search = searchInput.value;
  if (dateInput) ms.dateFilter = dateInput.value;
  if (typeSelect) ms.typeFilter = typeSelect.value;
  if (dispSelect) ms.dispFilter = dispSelect.value;
  
  // Re-render to apply filters
  renderMainContent();
}

async function handleLoginSubmit() {
  var usernameEl = document.getElementById('login-username');
  var passwordEl = document.getElementById('login-password');
  var errorEl = document.getElementById('login-error');
  var submitBtn = document.getElementById('login-submit');

  var username = (usernameEl ? usernameEl.value : '').trim();
  var password = passwordEl ? passwordEl.value : '';

  if (!username || !password) {
    if (errorEl) { errorEl.textContent = 'Please enter your username and password.'; errorEl.classList.remove('hidden'); }
    return;
  }

  if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Signing in\u2026'; }

  try {
    var result = await API.login(username, password);
    API.setToken(result.token);
    API.setSession({ id: result.user.id, username: result.user.username, fullName: result.user.fullName, role: result.user.role });
    state.currentUser = result.user;
    state.currentView = 'dashboard';
    render();
    loadDashboardData();
  } catch (e) {
    if (errorEl) { errorEl.textContent = e.message || 'Invalid username or password.'; errorEl.classList.remove('hidden'); }
    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Log In'; }
  }
}

async function doLogout() {
  state.showLogoutConfirm = false;
  state.currentUser = null;
  state.loggedOut = true;
  API.setToken(null);
  API.setSession(null);
  try { localStorage.removeItem('clinic_ui_state'); } catch (e) {}
  if (window.dashboardBarChart) { window.dashboardBarChart.destroy(); window.dashboardBarChart = null; }
  if (window.dashboardDoughnutChart) { window.dashboardDoughnutChart.destroy(); window.dashboardDoughnutChart = null; }
  render();
}

function openAddForm(key) {
  var ms = getModuleState(key);
  var config = MODULES[key];
  var blank = {};
  config.fields.forEach(function(f) { blank[f.name] = ''; });
  if (key === 'visits') {
    blank.date = new Date().toISOString().slice(0, 10);
    blank.time = new Date().toTimeString().slice(0, 5);
  }
  if (key === 'incidents') {
    blank.caseNo = generateIncidentId(ms.items);
  }
  if (key === 'medicalRecords') {
    blank.recordId = generateMedicalRecordId(ms.items);
    blank.status = 'Active';
    blank.groupFolder = ms.groupSelected || (ms.groups && ms.groups.length ? ms.groups[0].name : '');
  }
  ms.form = blank;
  ms.editing = null;
  ms.error = '';
  ms.modalOpen = true;
  renderMainContent();
}

async function openMedicalRecordFromStudent(studentId) {
  var studentState = getModuleState('students');
  var student = (studentState.items || []).find(function(item) { return item.id === studentId; });
  if (!student) return;

  var ms = getModuleState('medicalRecords');
  if (!ms.items || ms.items.length === 0) {
    try {
      ms.items = await API.list('medicalRecords');
    } catch (e) {
      ms.items = [];
    }
  }

  var config = MODULES['medicalRecords'];
  var blank = {};
  config.fields.forEach(function(f) { blank[f.name] = ''; });
  blank.status = 'Active';
  blank.groupFolder = ms.groupSelected || (ms.groups && ms.groups.length ? ms.groups[0].name : '');
  blank.studentId = student.studentId || '';
  blank.studentName = student.name || '';
  blank.department = student.course || '';
  blank.yearLevel = student.yearLevel || '';
  blank.section = '';
  blank.bloodType = student.bloodType || 'Unknown';
  blank.allergies = student.allergies || 'None';
  blank.medicalConditions = student.conditions || 'None';
  blank.recordId = generateMedicalRecordId(ms.items);

  ms.form = blank;
  ms.editing = null;
  ms.error = '';
  ms.modalOpen = true;

  loadMedicalRecordGroups(ms);
  renderMainContent();
}

function openEditForm(key, id) {
  var ms = getModuleState(key);
  var item = ms.items.find(function(i) { return i.id === id; });
  if (!item) return;
  var f = {};
  for (var k in item) {
    if (item.hasOwnProperty(k)) f[k] = item[k];
  }
  if (key === 'users') f.password = '';
  ms.form = f;
  ms.editing = item;
  ms.error = '';
  ms.modalOpen = true;
  renderMainContent();
}

function confirmDelete(key, id) {
  var ms = getModuleState(key);
  var item = ms.items.find(function(i) { return i.id === id; });
  if (item) {
    ms.deleteTarget = item;
    renderMainContent();
  }
}

async function doDelete(key) {
  var ms = getModuleState(key);
  if (!ms.deleteTarget) return;
  try {
    await API.delete(key, ms.deleteTarget.id);
    var config = MODULES[key] || { title: 'Record' };
    showToast(config.title + ' deleted successfully.', 'success');
  } catch (e) { /* continue */ }
  ms.deleteTarget = null;
  await loadModuleData(key);
}

function handleFormSubmit(key) {
  var ms = getModuleState(key);
  var modal = document.querySelector('#modal-overlay');
  if (!modal) return;

  var inputs = modal.querySelectorAll('input, select, textarea');
  var data = {};
  inputs.forEach(function(input) {
    var name = input.name || '';
    if (name.indexOf(key + '_') === 0) {
      name = name.slice(key.length + 1);
    }
    data[name] = input.value;
  });

  if (key === 'medicalRecords') {
    var mms = getModuleState('medicalRecords');
    if (!data.recordId) {
      data.recordId = generateMedicalRecordId(mms.items || []);
    }
    if (!data.groupFolder && mms.groupSelected) {
      data.groupFolder = mms.groupSelected;
    }
  }

  if (key === 'medicalRecordGroup') {
    var mms = getModuleState('medicalRecords');
    mms.groupForm = data;
    if (!data.name || !String(data.name).trim()) {
      mms.groupError = 'Group name is required';
      renderMainContent();
      return;
    }
    mms.groupError = '';
    API.createRecordFolder(data).then(function() {
      return API.listRecordFolders();
    }).then(function(list) {
      mms.groups = list || [];
      mms.groupSelected = data.name;
      mms.expandedFolders = mms.expandedFolders || {};
      mms.expandedFolders[data.name] = true;
      mms.newGroupModalOpen = false;
      mms.groupForm = {};
      showToast('Medical record group created successfully.', 'success');
      renderMainContent();
      setTimeout(function(){
        try {
          var q = (window.CSS && CSS.escape) ? CSS.escape(data.name) : data.name.replace(/"/g, '\\"');
          var el = document.querySelector('[data-group-name="' + q + '"]');
          if (el && el.scrollIntoView) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } catch (e) { /* ignore */ }
      }, 120);
    }).catch(function(e) {
      mms.groupError = e.message || 'Failed to create group';
      showToast(mms.groupError, 'error');
      renderMainContent();
    });
    return;
  }

  if (key === 'medicalRecordGroupRename') {
    var mms = getModuleState('medicalRecords');
    mms.groupRenameForm = data;
    if (!data.newName || !String(data.newName).trim()) {
      mms.groupRenameError = 'New group name is required';
      renderMainContent();
      return;
    }
    if (!data.currentName || !String(data.currentName).trim()) {
      mms.groupRenameError = 'Current group name is required';
      renderMainContent();
      return;
    }
    mms.groupRenameError = '';
    API.renameRecordFolder({ currentName: data.currentName, newName: data.newName }).then(function() {
      return API.listRecordFolders();
    }).then(function(list) {
      mms.groups = list || [];
      mms.groupSelected = data.newName;
      mms.groupRenameModalOpen = false;
      mms.groupRenameForm = {};
      showToast('Medical record group renamed successfully.', 'success');
      renderMainContent();
    }).catch(function(e) {
      mms.groupRenameError = e.message || 'Failed to rename group';
      showToast(mms.groupRenameError, 'error');
      renderMainContent();
    });
    return;
  }

  var config = MODULES[key];
  var requiredFields = config.fields.filter(function(f) { return f.required; });
  var missing = requiredFields.filter(function(f) { return !data[f.name] });
  if (missing.length > 0) {
    ms.error = 'Please fill in all required fields';
    renderMainContent();
    return;
  }

  ms.form = data;
  saveModuleData(key);
}

async function saveModuleData(key) {
  var ms = getModuleState(key);
  var data = ms.form;
  var config = MODULES[key];

  try {
    var isUpdate = Boolean(ms.editing);
    if (ms.editing) {
      if (key === 'users' && !data.password) delete data.password;
      var updated = await API.update(key, ms.editing.id, data);
    } else {
      if (key === 'visits') {
        data.id = generateVisitId(ms.items);
      } else if (key === 'appointments') {
        data.id = generateAppointmentId(ms.items);
      } else if (key === 'medicalRecords') {
        data.recordId = generateMedicalRecordId(ms.items);
        data.id = uid();
        data.status = data.status || 'Active';
        if (!data.groupFolder && ms.groupSelected) data.groupFolder = ms.groupSelected;
      } else if (key === 'incidents') {
        data.caseNo = generateIncidentId(ms.items);
        data.id = uid();
      } else {
        data.id = uid();
      }
      await API.create(key, data);
    }
    ms.modalOpen = false;
    ms.editing = null;
    ms.form = {};
    ms.error = '';
    showToast((isUpdate ? 'Updated ' : 'Created ') + config.title + ' successfully.', 'success');
    await loadModuleData(key);
  } catch (e) {
    ms.error = 'Failed to save: ' + (e.message || 'Unknown error');
    showToast(ms.error, 'error');
    renderMainContent();
  }
}

async function doSave(key) {
  var ms = getModuleState(key);
  var config = MODULES[key];

  /* Sync live DOM values into ms.form before any validation/re-render
     so that re-rendering (on error) preserves user input */
  config.fields.forEach(function(f) {
    var liveVal = getFieldValue(key, f.name);
    if (f.type === 'number') liveVal = Number(liveVal);
    ms.form[f.name] = liveVal;
  });

  /* Validate */
  for (var i = 0; i < config.fields.length; i++) {
    var f = config.fields[i];
    var fieldValue = ms.form[f.name];
    if (f.required && key === 'users' && f.name === 'password' && ms.editing) continue;
    if (f.required && !String(fieldValue || '').trim()) {
      ms.error = f.label + ' is required.';
      renderMainContent();
      return;
    }
  }

  /* Check duplicate username for users */
  if (key === 'users') {
    var uname = getFieldValue(key, 'username');
    var dup = ms.items.find(function(u) {
      return u.username.toLowerCase() === String(uname).toLowerCase() && (!ms.editing || u.id !== ms.editing.id);
    });
    if (dup) {
      ms.error = 'That username is already taken.';
      renderMainContent();
      return;
    }
  }

  /* Build data object */
  var data = {};
  config.fields.forEach(function(f) {
    var val = getFieldValue(key, f.name);
    if (f.type === 'number') val = Number(val);
    data[f.name] = val;
  });

  if (key === 'medicalRecords' && !data.recordId) {
    data.recordId = generateMedicalRecordId(ms.items);
  }

  /* Strip fields not yet in the database schema for each module */
  var EXTRA_FIELDS = {
    visits: ['grade', 'temperature', 'bloodPressure', 'pulseRate', 'assessment', 'medicineDispensed', 'disposition'],
  };
  var skip = EXTRA_FIELDS[key];
  if (skip) {
    skip.forEach(function(n) { delete data[n]; });
  }

  /* Auto-set date/time for visits to current system time */
  if (key === 'visits') {
    data.date = new Date().toISOString().slice(0, 10);
    data.time = new Date().toTimeString().slice(0, 5);
  }

  try {
    var isUpdate = Boolean(ms.editing);
    if (ms.editing) {
      if (key === 'users' && !data.password) delete data.password;
      var updated = await API.update(key, ms.editing.id, data);
    } else {
      if (key === 'visits') {
        data.id = generateVisitId(ms.items);
      } else if (key === 'appointments') {
        data.id = generateAppointmentId(ms.items);
      } else if (key === 'medicalRecords') {
        data.recordId = generateMedicalRecordId(ms.items);
        data.id = uid();
      } else if (key === 'incidents') {
        data.caseNo = generateIncidentId(ms.items);
        data.id = uid();
      } else {
        data.id = uid();
      }
      await API.create(key, data);
    }
    ms.modalOpen = false;
    ms.editing = null;
    ms.error = '';
    showToast((isUpdate ? 'Updated ' : 'Created ') + config.title + ' successfully.', 'success');
    await loadModuleData(key);
  } catch (e) {
    ms.error = e.message;
    showToast('Failed to save: ' + ms.error, 'error');
    renderMainContent();
  }
}

function generateVisitId(items) {
  var year = new Date().getFullYear();
  var prefix = 'CV-' + year + '-';
  var maxSeq = 0;
  items.forEach(function(item) {
    var id = item.id || '';
    if (id.indexOf(prefix) === 0) {
      var num = parseInt(id.slice(prefix.length), 10);
      if (!isNaN(num) && num > maxSeq) maxSeq = num;
    }
  });
  var next = maxSeq + 1;
  var seq = next < 10 ? '0' + next : String(next);
  return prefix + seq;
}

function generateAppointmentId(items) {
  var year = new Date().getFullYear();
  var prefix = 'APT-' + year + '-';
  var maxSeq = 0;
  items.forEach(function(item) {
    var id = item.id || '';
    if (id.indexOf(prefix) === 0) {
      var num = parseInt(id.slice(prefix.length), 10);
      if (!isNaN(num) && num > maxSeq) maxSeq = num;
    }
  });
  var next = maxSeq + 1;
  var seq = next < 10 ? '0' + next : String(next);
  return prefix + seq;
}

function generateMedicalRecordId(items) {
  var year = new Date().getFullYear();
  var prefix = 'MR-' + year + '-';
  var maxSeq = 0;
  items.forEach(function(item) {
    var id = item.recordId || item.id || '';
    if (id.indexOf(prefix) === 0) {
      var num = parseInt(id.slice(prefix.length), 10);
      if (!isNaN(num) && num > maxSeq) maxSeq = num;
    }
  });
  var next = maxSeq + 1;
  var seq = next < 10 ? '0' + next : String(next);
  return prefix + seq;
}

function generateIncidentId(items) {
  var year = new Date().getFullYear();
  var prefix = 'INC-' + year + '-';
  var maxSeq = 0;
  items.forEach(function(item) {
    var id = item.caseNo || item.id || '';
    if (id.indexOf(prefix) === 0) {
      var num = parseInt(id.slice(prefix.length), 10);
      if (!isNaN(num) && num > maxSeq) maxSeq = num;
    }
  });
  var next = maxSeq + 1;
  var seq = next < 100 ? (next < 10 ? '00' + next : '0' + next) : String(next);
  return prefix + seq;
}

function getFieldValue(key, fieldName) {
  var el = document.getElementById(key + '_' + fieldName);
  if (!el) {
    el = document.getElementById('field_' + fieldName);
  }
  if (!el) {
    el = document.querySelector('[name="' + key + '_' + fieldName + '"]');
  }
  if (!el) {
    el = document.querySelector('[name="' + fieldName + '"]');
  }
  if (!el) return '';
  if (el.type === 'checkbox') return el.checked;
  return el.value;
}

async function doDispense(key, id) {
  var ms = getModuleState(key);
  var qty = parseInt(ms.dispenseQty[id], 10);
  if (!qty || qty <= 0) return;
  var item = ms.items.find(function(i) { return i.id === id; });
  if (!item) return;
  var newStock = Math.max(0, parseInt(item.stock || 0, 10) - qty);
  try {
    await API.update(key, id, { stock: newStock });
    ms.dispenseQty[id] = '';
    await loadModuleData(key);
  } catch (e) { /* continue */ }
}

function toggleNotifications() {
  var dd = document.getElementById('notif-dropdown');
  var userDd = document.getElementById('user-dropdown');
  if (userDd) userDd.classList.add('hidden');
  if (dd) {
    var isOpen = !dd.classList.contains('hidden');
    dd.classList.toggle('hidden');
    if (!isOpen) loadNotifications();
  }
}

function toggleUserMenu() {
  var dd = document.getElementById('user-dropdown');
  var notifDd = document.getElementById('notif-dropdown');
  if (notifDd) notifDd.classList.add('hidden');
  if (dd) dd.classList.toggle('hidden');
}

async function loadNotifications() {
  var list = document.getElementById('notif-list');
  if (!list) return;
  list.innerHTML = '<p class="text-xs" style="color:#5A4A62">Loading&hellip;</p>';
  try {
    var data = await API.getDashboard();
    var notifs = [];
    data.medicine && data.medicine.forEach(function(m) {
      if (Number(m.stock) <= Number(m.reorderLevel || 0)) notifs.push('Low stock: ' + (m.name || ''));
    });
    data.appointments && data.appointments.forEach(function(a) {
      if (a.date === todayStr()) notifs.push('Appointment today: ' + (a.patientName || ''));
    });
    data.incidents && data.incidents.forEach(function(i) {
      if (i.status !== 'Resolved') notifs.push('Open incident: ' + (i.caseNo || ''));
    });
    notifs = notifs.slice(0, 8);
    if (notifs.length === 0) {
      list.innerHTML = '<p class="text-xs" style="color:#5A4A62">You\'re all caught up.</p>';
    } else {
      list.innerHTML = notifs.map(function(n) {
        return '<p class="rounded-lg px-2.5 py-2 text-xs" style="background:#F8F7FA;color:#2B2B2B">' + esc(n) + '</p>';
      }).join('');
    }
  } catch (e) {
    list.innerHTML = '<p class="text-xs" style="color:#5A4A62">Failed to load notifications.</p>';
  }
}

/* ============================== DATA LOADING ============================== */
async function loadDashboardData() {
  state.loadingData = true;
  renderMainContent();
  try {
    state.dashData = await API.getDashboard();
  } catch (e) {
    state.dashData = {
      stats: { todaysVisits: 0, pendingAppointments: 0, lowStock: 0, clearances: 0, openIncidents: 0 },
      chartData: [],
      recentActivity: [],
      upcomingAppointments: [],
      programUpdates: [],
      visits: [],
      students: [],
    };
  }
  state.loadingData = false;
  renderMainContent();
}

async function loadReportsData() {
  state.loadingData = true;
  renderMainContent();
  try {
    var data = await API.getReports();
    state.reportsData = data;
  } catch (e) {
    state.reportsData = { records: {}, summary: { lowStock: 0, openIncidents: 0, expiredClearance: 0, pendingAppointments: 0 } };
  }
  state.loadingData = false;
  renderMainContent();
}

function saveState() {
  try {
    var mms = getModuleState('medicalRecords');
    localStorage.setItem('clinic_ui_state', JSON.stringify({
      currentView: state.currentView,
      medicalRecords: {
        expandedFolders: mms.expandedFolders || {},
        groupSelected: mms.groupSelected || '',
        groupPage: mms.groupPage || 1,
        folderPage: mms.folderPage || 1
      }
    }));
  } catch (e) {}
}

function restoreState() {
  try {
    var saved = localStorage.getItem('clinic_ui_state');
    if (saved) {
      var parsed = JSON.parse(saved);
      if (parsed.currentView && canAccessView(parsed.currentView)) {
        state.currentView = parsed.currentView;
      }
      if (parsed.medicalRecords) {
        var mms = getModuleState('medicalRecords');
        if (parsed.medicalRecords.expandedFolders) mms.expandedFolders = parsed.medicalRecords.expandedFolders;
        if (parsed.medicalRecords.groupSelected) mms.groupSelected = parsed.medicalRecords.groupSelected;
        if (parsed.medicalRecords.groupPage) mms.groupPage = parsed.medicalRecords.groupPage;
        if (parsed.medicalRecords.folderPage) mms.folderPage = parsed.medicalRecords.folderPage;
      }
    }
  } catch (e) {}
}

/* ============================== INIT ============================== */
async function init() {
  setupEvents();
  render();

  /* Check for existing session */
  var token = API.getToken();
  var session = API.getSession();

  if (token && session) {
    try {
      var dashData = await API.getDashboard();
      state.currentUser = {
        id: session.id || '',
        fullName: session.fullName || 'User',
        username: session.username || '',
        role: session.role || 'Staff',
      };
      restoreState();
      state.authLoading = false;
      render();
      state.dashData = dashData;
      state.loadingData = false;
      renderMainContent();
      if (MODULES[state.currentView]) {
        loadModuleData(state.currentView);
      }
      return;
    } catch (e) {
      API.setToken(null);
      API.setSession(null);
    }
  }

  /* Try seeding default admin */
  try { await API.seed(); } catch (e) {}

  state.authLoading = false;
  render();
}

document.addEventListener('DOMContentLoaded', init);
