-- Marklist MVP migration (non-breaking)
-- Safe to run without changing existing attendance tables.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `academic_terms_mvp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `term_order` tinyint(4) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_term_order` (`term_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `academic_terms_mvp` (`name`, `term_order`, `is_active`)
SELECT 'Term 1', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM `academic_terms_mvp` WHERE `term_order` = 1);

INSERT INTO `academic_terms_mvp` (`name`, `term_order`, `is_active`)
SELECT 'Term 2', 2, 1
WHERE NOT EXISTS (SELECT 1 FROM `academic_terms_mvp` WHERE `term_order` = 2);

INSERT INTO `academic_terms_mvp` (`name`, `term_order`, `is_active`)
SELECT 'Term 3', 3, 1
WHERE NOT EXISTS (SELECT 1 FROM `academic_terms_mvp` WHERE `term_order` = 3);

CREATE TABLE IF NOT EXISTS `student_course_marks_mvp` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `term_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `continuous_mark` decimal(6,2) NOT NULL DEFAULT 0.00,
  `exam_mark` decimal(6,2) NOT NULL DEFAULT 0.00,
  `total_mark` decimal(6,2) NOT NULL DEFAULT 0.00,
  `attendance_percent` decimal(5,2) DEFAULT NULL,
  `grade_letter` varchar(8) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `is_finalized` tinyint(1) NOT NULL DEFAULT 0,
  `entered_by_admin_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mark_context` (`class_id`,`course_id`,`term_id`,`student_id`),
  KEY `idx_mvp_student_term` (`student_id`,`term_id`),
  KEY `idx_mvp_class_term` (`class_id`,`term_id`),
  KEY `idx_mvp_course_term` (`course_id`,`term_id`),
  KEY `idx_mvp_finalized` (`is_finalized`),
  CONSTRAINT `fk_mvp_mark_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mvp_mark_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mvp_mark_term` FOREIGN KEY (`term_id`) REFERENCES `academic_terms_mvp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_mvp_mark_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mvp_mark_admin` FOREIGN KEY (`entered_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
