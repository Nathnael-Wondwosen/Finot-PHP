-- Database Optimization Indexes for Finot-PHP Production
-- Run this SQL to add performance indexes

-- Students table indexes
ALTER TABLE students ADD INDEX idx_full_name (full_name(100));
ALTER TABLE students ADD INDEX idx_current_grade (current_grade);
ALTER TABLE students ADD INDEX idx_gender (gender);
ALTER TABLE students ADD INDEX idx_birth_date (birth_date);
ALTER TABLE students ADD INDEX idx_created_at (created_at);
ALTER TABLE students ADD INDEX idx_phone_number (phone_number(20));
ALTER TABLE students ADD INDEX idx_grade_created (current_grade, created_at);
ALTER TABLE students ADD INDEX idx_name_phone (full_name(50), phone_number(20));

-- Parents table indexes
ALTER TABLE parents ADD INDEX idx_student_id (student_id);
ALTER TABLE parents ADD INDEX idx_parent_type (parent_type);
ALTER TABLE parents ADD INDEX idx_student_type (student_id, parent_type);

-- Classes table indexes
ALTER TABLE classes ADD INDEX idx_grade (grade);
ALTER TABLE classes ADD INDEX idx_academic_year (academic_year);
ALTER TABLE classes ADD INDEX idx_grade_section (grade, section);

-- Class enrollments indexes
ALTER TABLE class_enrollments ADD INDEX idx_student_id (student_id);
ALTER TABLE class_enrollments ADD INDEX idx_class_id (class_id);
ALTER TABLE class_enrollments ADD INDEX idx_status (status);
ALTER TABLE class_enrollments ADD INDEX idx_class_status (class_id, status);
ALTER TABLE class_enrollments ADD INDEX idx_student_status (student_id, status);

-- Teachers table indexes
ALTER TABLE teachers ADD INDEX idx_is_active (is_active);
ALTER TABLE teachers ADD INDEX idx_full_name (full_name(100));

-- Class teachers indexes
ALTER TABLE class_teachers ADD INDEX idx_class_id (class_id);
ALTER TABLE class_teachers ADD INDEX idx_teacher_id (teacher_id);
ALTER TABLE class_teachers ADD INDEX idx_is_active (is_active);
ALTER TABLE class_teachers ADD INDEX idx_class_role_active (class_id, role, is_active);

-- Courses table indexes
ALTER TABLE courses ADD INDEX idx_is_active (is_active);

-- Course teachers indexes
ALTER TABLE course_teachers ADD INDEX idx_course_id (course_id);
ALTER TABLE course_teachers ADD INDEX idx_teacher_id (teacher_id);
ALTER TABLE course_teachers ADD INDEX idx_class_id (class_id);
ALTER TABLE course_teachers ADD INDEX idx_is_active (is_active);
ALTER TABLE course_teachers ADD INDEX idx_class_active (class_id, is_active);

-- Instrument registrations indexes
ALTER TABLE instrument_registrations ADD INDEX idx_full_name (full_name(100));
ALTER TABLE instrument_registrations ADD INDEX idx_instrument (instrument(50));
ALTER TABLE instrument_registrations ADD INDEX idx_created_at (created_at);
ALTER TABLE instrument_registrations ADD INDEX idx_flagged (flagged);

-- Attendance records indexes
ALTER TABLE attendance_records ADD INDEX idx_student_id (student_id);
ALTER TABLE attendance_records ADD INDEX idx_class_id (class_id);
ALTER TABLE attendance_records ADD INDEX idx_date (date);
ALTER TABLE attendance_records ADD INDEX idx_student_date (student_id, date);
ALTER TABLE attendance_records ADD INDEX idx_class_date (class_id, date);

-- Admin preferences indexes
ALTER TABLE admin_preferences ADD INDEX idx_admin_id (admin_id);
ALTER TABLE admin_preferences ADD INDEX idx_table_name (table_name(50));
ALTER TABLE admin_preferences ADD INDEX idx_admin_table (admin_id, table_name(50));

-- Allocation ranges indexes
ALTER TABLE allocation_ranges ADD INDEX idx_grade (grade(32));

-- Youth categories indexes
ALTER TABLE youth_categories ADD INDEX idx_student_id (student_id);
ALTER TABLE youth_categories ADD INDEX idx_category (category(50));

-- Admin categories indexes
ALTER TABLE admin_categories ADD INDEX idx_type (type(20));

-- Optimize tables
OPTIMIZE TABLE students;
OPTIMIZE TABLE parents;
OPTIMIZE TABLE classes;
OPTIMIZE TABLE class_enrollments;
OPTIMIZE TABLE teachers;
OPTIMIZE TABLE class_teachers;
OPTIMIZE TABLE courses;
OPTIMIZE TABLE course_teachers;
OPTIMIZE TABLE instrument_registrations;
OPTIMIZE TABLE attendance_records;
OPTIMIZE TABLE admins;
OPTIMIZE TABLE admin_preferences;
