INSERT IGNORE INTO students (id, name, studentid, course, yearlevel, bloodtype, allergies, conditions, contactnumber, emergencycontact, status, created_at)
SELECT
  CONCAT('STU_', MD5(CONCAT('student:', LOWER(TRIM(m.studentid))))) AS id,
  MAX(m.studentname) AS name,
  LOWER(TRIM(m.studentid)) AS studentid,
  'Other' AS course,
  MAX(m.yearlevel) AS yearlevel,
  MAX(m.bloodtype) AS bloodtype,
  MAX(m.allergies) AS allergies,
  MAX(m.medicalconditions) AS conditions,
  '' AS contactnumber,
  '' AS emergencycontact,
  'Active' AS status,
  UNIX_TIMESTAMP() * 1000 AS created_at
FROM medicalrecords m
WHERE m.groupfolder = 'Group 9'
GROUP BY LOWER(TRIM(m.studentid));
