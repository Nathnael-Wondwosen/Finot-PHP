-- Marklist MVP Phase 5: audit trail for mark entry changes
-- Non-destructive migration.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `mark_entry_audit_mvp` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `request_id` varchar(64) NOT NULL,
  `class_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `term_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `action_type` enum('insert','update') NOT NULL,
  `changed_fields` longtext DEFAULT NULL,
  `before_payload` longtext DEFAULT NULL,
  `after_payload` longtext DEFAULT NULL,
  `changed_by_admin_id` int(11) DEFAULT NULL,
  `changed_by_portal_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_context` (`class_id`,`course_id`,`term_id`),
  KEY `idx_audit_student_term` (`student_id`,`term_id`),
  KEY `idx_audit_request` (`request_id`),
  KEY `idx_audit_portal_user` (`changed_by_portal_user_id`),
  CONSTRAINT `fk_audit_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_audit_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_audit_term` FOREIGN KEY (`term_id`) REFERENCES `academic_terms_mvp` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_audit_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_audit_admin` FOREIGN KEY (`changed_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_audit_portal` FOREIGN KEY (`changed_by_portal_user_id`) REFERENCES `portal_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
