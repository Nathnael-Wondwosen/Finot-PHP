-- Marklist MVP Phase 6: yearly summary storage for promotion workflow

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `student_year_result_summary_mvp` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `student_id` int(11) NOT NULL,
  `terms_count` int(11) NOT NULL DEFAULT 0,
  `year_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `year_average` decimal(8,2) NOT NULL DEFAULT 0.00,
  `attendance_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `rank_in_class` int(11) DEFAULT NULL,
  `decision` enum('pass','fail','pending') NOT NULL DEFAULT 'pending',
  `is_finalized` tinyint(1) NOT NULL DEFAULT 0,
  `finalized_by_admin_id` int(11) DEFAULT NULL,
  `finalized_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_year_summary_context` (`class_id`,`academic_year`,`student_id`),
  KEY `idx_year_summary_class_year` (`class_id`,`academic_year`),
  KEY `idx_year_summary_student_year` (`student_id`,`academic_year`),
  CONSTRAINT `fk_year_summary_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_year_summary_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_year_summary_admin` FOREIGN KEY (`finalized_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
