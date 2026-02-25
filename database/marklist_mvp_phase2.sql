-- Marklist MVP Phase 2 (auth + homeroom attendance)
-- Non-destructive migration.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `portal_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(80) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('teacher','homeroom') NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_portal_username` (`username`),
  UNIQUE KEY `uniq_portal_teacher_role` (`teacher_id`,`role`),
  KEY `idx_portal_role_active` (`role`,`is_active`),
  CONSTRAINT `fk_portal_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `student_course_marks_mvp`
  ADD COLUMN IF NOT EXISTS `entered_by_portal_user_id` int(11) DEFAULT NULL AFTER `entered_by_admin_id`,
  ADD KEY `idx_mvp_portal_user` (`entered_by_portal_user_id`);

ALTER TABLE `student_course_marks_mvp`
  ADD CONSTRAINT `fk_mvp_mark_portal_user`
  FOREIGN KEY (`entered_by_portal_user_id`) REFERENCES `portal_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE IF NOT EXISTS `student_term_attendance_mvp` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `term_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `attendance_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `remark` varchar(255) DEFAULT NULL,
  `entered_by_admin_id` int(11) DEFAULT NULL,
  `entered_by_portal_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_attendance_context` (`class_id`,`term_id`,`student_id`),
  KEY `idx_attendance_student_term` (`student_id`,`term_id`),
  CONSTRAINT `fk_att_mvp_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_att_mvp_term` FOREIGN KEY (`term_id`) REFERENCES `academic_terms_mvp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_att_mvp_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_att_mvp_admin` FOREIGN KEY (`entered_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_att_mvp_portal` FOREIGN KEY (`entered_by_portal_user_id`) REFERENCES `portal_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
