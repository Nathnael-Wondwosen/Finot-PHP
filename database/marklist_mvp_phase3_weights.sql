-- Marklist MVP Phase 3: configurable assessment components and weights
-- Non-destructive migration.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `mark_weight_settings_mvp` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `term_id` int(11) NOT NULL,
  `book_weight` decimal(6,2) NOT NULL DEFAULT 10.00,
  `assignment_weight` decimal(6,2) NOT NULL DEFAULT 10.00,
  `quiz_weight` decimal(6,2) NOT NULL DEFAULT 10.00,
  `mid_exam_weight` decimal(6,2) NOT NULL DEFAULT 20.00,
  `final_exam_weight` decimal(6,2) NOT NULL DEFAULT 40.00,
  `attendance_weight` decimal(6,2) NOT NULL DEFAULT 10.00,
  `entered_by_admin_id` int(11) DEFAULT NULL,
  `entered_by_portal_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_weight_context` (`class_id`,`course_id`,`term_id`),
  CONSTRAINT `fk_weight_mvp_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_weight_mvp_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_weight_mvp_term` FOREIGN KEY (`term_id`) REFERENCES `academic_terms_mvp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_weight_mvp_admin` FOREIGN KEY (`entered_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_weight_mvp_portal` FOREIGN KEY (`entered_by_portal_user_id`) REFERENCES `portal_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `student_course_marks_mvp`
  ADD COLUMN IF NOT EXISTS `book_mark` decimal(6,2) NOT NULL DEFAULT 0.00 AFTER `student_id`,
  ADD COLUMN IF NOT EXISTS `assignment_mark` decimal(6,2) NOT NULL DEFAULT 0.00 AFTER `book_mark`,
  ADD COLUMN IF NOT EXISTS `quiz_mark` decimal(6,2) NOT NULL DEFAULT 0.00 AFTER `assignment_mark`,
  ADD COLUMN IF NOT EXISTS `mid_exam_mark` decimal(6,2) NOT NULL DEFAULT 0.00 AFTER `quiz_mark`,
  ADD COLUMN IF NOT EXISTS `final_exam_mark` decimal(6,2) NOT NULL DEFAULT 0.00 AFTER `mid_exam_mark`;

COMMIT;
