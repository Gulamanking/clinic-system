-- Migration to update medicalhistory table structure
-- Run this to update existing database schemas

-- Step 1: Add new columns (if they don't exist)
-- SQLite version
ALTER TABLE medicalhistory ADD COLUMN studentname TEXT NOT NULL DEFAULT '';
ALTER TABLE medicalhistory ADD COLUMN department TEXT NOT NULL DEFAULT '';
ALTER TABLE medicalhistory ADD COLUMN yearlevel TEXT NOT NULL DEFAULT '';
ALTER TABLE medicalhistory ADD COLUMN section TEXT NOT NULL DEFAULT '';
ALTER TABLE medicalhistory ADD COLUMN bloodtype TEXT NOT NULL DEFAULT '';
ALTER TABLE medicalhistory ADD COLUMN course TEXT NOT NULL DEFAULT '';

-- MySQL version (uncomment if using MySQL)
-- ALTER TABLE medicalhistory ADD COLUMN studentname TEXT NOT NULL DEFAULT '';
-- ALTER TABLE medicalhistory ADD COLUMN department TEXT NOT NULL DEFAULT '';
-- ALTER TABLE medicalhistory ADD COLUMN yearlevel TEXT NOT NULL DEFAULT '';
-- ALTER TABLE medicalhistory ADD COLUMN section TEXT NOT NULL DEFAULT '';
-- ALTER TABLE medicalhistory ADD COLUMN bloodtype TEXT NOT NULL DEFAULT '';
-- ALTER TABLE medicalhistory ADD COLUMN course TEXT NOT NULL DEFAULT '';

-- Step 2: Populate data from student records if possible
-- This is optional but helps with data migration
-- SQLite version
UPDATE medicalhistory SET studentname = (SELECT name FROM students WHERE students.studentid = medicalhistory.studentid) WHERE studentname = '';
UPDATE medicalhistory SET department = (SELECT course FROM students WHERE students.studentid = medicalhistory.studentid) WHERE department = '';
UPDATE medicalhistory SET yearlevel = (SELECT yearlevel FROM students WHERE students.studentid = medicalhistory.studentid) WHERE yearlevel = '';
UPDATE medicalhistory SET bloodtype = (SELECT bloodtype FROM students WHERE students.studentid = medicalhistory.studentid) WHERE bloodtype = '';
UPDATE medicalhistory SET course = (SELECT course FROM students WHERE students.studentid = medicalhistory.studentid) WHERE course = '';

-- MySQL version (uncomment if using MySQL)
-- UPDATE medicalhistory mh
-- JOIN students s ON mh.studentid = s.studentid
-- SET mh.studentname = s.name
-- WHERE mh.studentname = '';
-- UPDATE medicalhistory mh
-- JOIN students s ON mh.studentid = s.studentid
-- SET mh.department = s.course
-- WHERE mh.department = '';
-- UPDATE medicalhistory mh
-- JOIN students s ON mh.studentid = s.studentid
-- SET mh.yearlevel = s.yearlevel
-- WHERE mh.yearlevel = '';
-- UPDATE medicalhistory mh
-- JOIN students s ON mh.studentid = s.studentid
-- SET mh.bloodtype = s.bloodtype
-- WHERE mh.bloodtype = '';
-- UPDATE medicalhistory mh
-- JOIN students s ON mh.studentid = s.studentid
-- SET mh.course = s.course
-- WHERE mh.course = '';

-- Step 3: Remove medicalrecordid column if it exists (optional cleanup)
-- SQLite version
-- ALTER TABLE medicalhistory DROP COLUMN medicalrecordid;

-- MySQL version (uncomment if using MySQL)
-- ALTER TABLE medicalhistory DROP COLUMN medicalrecordid;
