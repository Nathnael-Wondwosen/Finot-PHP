-- Marklist MVP Phase 7: performance indexes for Phase 4/6 flows

START TRANSACTION;

-- Faster homeroom status and term-wide submission listing
CREATE INDEX idx_homeroom_term_class_status
ON homeroom_term_matrix_submissions_mvp (term_id, class_id, status);

CREATE INDEX idx_homeroom_updated_at
ON homeroom_term_matrix_submissions_mvp (updated_at);

-- Faster yearly student bulk operations and promotion filters
CREATE INDEX idx_year_class_year_decision_finalized
ON student_year_result_summary_mvp (class_id, academic_year, decision, is_finalized);

CREATE INDEX idx_year_class_year_rank
ON student_year_result_summary_mvp (class_id, academic_year, rank_in_class);

COMMIT;

