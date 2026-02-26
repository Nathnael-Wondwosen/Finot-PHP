# Marklist + Result API Contract

This document defines the enforced workflow and expected API behavior for:

- `portal/api/marklist.php`
- `portal/api/homeroom.php`
- `api/result_summary_mvp.php`

## Workflow State Machine

1. `Teacher (marklist)` enters/saves marks per `class_id + course_id + term_id`.
2. `Homeroom` reviews class term matrix and submits.
3. `Admin` finalizes term summary and applies statuses.
4. Optional yearly summary/finalization/promotion follows term completion.

## Locking Rules

1. Teacher mark save is blocked (`409`) when:
- homeroom matrix status is `submitted` for `class_id + term_id`, or
- admin-finalized term summary exists for `class_id + term_id`, or
- submitted rows contain already finalized mark rows.

2. Weight settings are frozen after first mark entry in a context:
- if mark rows already exist and submitted weights differ, save is blocked (`409`).

3. Homeroom actions are blocked (`409`) when admin finalized term summary exists:
- `finalize_history`
- `reopen_history`
- `save_attendance`
- `save_matrix_draft`
- `submit_matrix`

4. Homeroom submit preconditions (`422` if not met):
- class has active course assignments
- submitted teacher courses for term are complete
- class has active students

5. Admin term finalization preconditions:
- term summary rows exist
- homeroom status is `submitted`
- no newer teacher mark updates after homeroom submit/update
- submitted teacher courses cover expected active class courses

## Response Envelope

Success:

```json
{
  "success": true,
  "...": "payload"
}
```

Error:

```json
{
  "success": false,
  "message": "human-readable error"
}
```

## Important HTTP Status Codes

1. `200` successful read/write operation.
2. `401` unauthenticated.
3. `403` authenticated but unauthorized role/context.
4. `405` wrong method.
5. `409` workflow/concurrency conflict (locked/finalized/stale context/weight freeze).
6. `422` validation/readiness failure.
7. `500` server or migration/table missing.

## Endpoint Notes

### `portal/api/marklist.php?action=students`

Includes lock metadata:

- `is_locked`
- `lock_reason`
- `weight_frozen`
- `homeroom_status`
- `context_version`

### `portal/api/marklist.php` (`action=save_marks`)

Required POST:

- `csrf`
- `class_id`, `course_id`, `term_id`
- `rows` (JSON array)
- `weights` (JSON object)
- optional `context_version` (optimistic concurrency)

### `portal/api/homeroom.php`

Key write actions:

- `save_attendance`
- `save_matrix_draft`
- `submit_matrix`
- `finalize_history`
- `reopen_history`

All require POST + valid CSRF.

### `api/result_summary_mvp.php`

Term:

- `recalculate` (blocked if finalized unless `force_recalculate=1`)
- `finalize`
- `apply_status`

Yearly:

- `recalculate_yearly` (blocked if finalized unless `force_recalculate=1`)
- `finalize_yearly`
- `apply_yearly_status`
- promotion endpoints

## Deployment Verification

Run the smoke script after deploy:

- `php scripts/smoke_marklist_result_workflow.php ...`

It validates login/session, CSRF extraction, bootstrap/students/readiness calls, and workflow lock responses.
