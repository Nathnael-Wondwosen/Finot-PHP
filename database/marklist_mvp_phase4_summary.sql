-- Marklist MVP Phase 4: class-term result summary and finalization
-- Non-destructive migration.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `student_term_result_summary_mvp` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `term_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_count` int(11) NOT NULL DEFAULT 0,
  `total_score` decimal(8,2) NOT NULL DEFAULT 0.00,
  `average_score` decimal(8,2) NOT NULL DEFAULT 0.00,
  `attendance_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `rank_in_class` int(11) DEFAULT NULL,
  `decision` enum('pass','fail','pending') NOT NULL DEFAULT 'pending',
  `is_finalized` tinyint(1) NOT NULL DEFAULT 0,
  `finalized_by_admin_id` int(11) DEFAULT NULL,
  `finalized_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_summary_context` (`class_id`,`term_id`,`student_id`),
  KEY `idx_summary_class_term` (`class_id`,`term_id`),
  KEY `idx_summary_student_term` (`student_id`,`term_id`),
  CONSTRAINT `fk_summary_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_summary_term` FOREIGN KEY (`term_id`) REFERENCES `academic_terms_mvp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_summary_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_summary_admin` FOREIGN KEY (`finalized_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
