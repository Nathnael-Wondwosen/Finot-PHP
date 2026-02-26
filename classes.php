<?php
// Note: config.php already handles session initialization, so we don't need to call session_start() directly
require 'config.php';
require 'includes/admin_layout.php';
require 'includes/security_helpers.php';
require 'includes/students_helpers.php';

// Require admin authentication
requireAdminLogin();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'get_classes':
                $stmt = $pdo->query("
                    SELECT c.*, t.full_name as teacher_name,
                           COUNT(ce.id) as student_count
                    FROM classes c
                    LEFT JOIN class_teachers ct ON c.id = ct.class_id AND ct.role = 'primary' AND ct.is_active = 1
                    LEFT JOIN teachers t ON ct.teacher_id = t.id
                    LEFT JOIN class_enrollments ce ON c.id = ce.class_id AND ce.status = 'active'
                    GROUP BY c.id
                    ORDER BY c.grade, c.section
                ");
                $classes = $stmt->fetchAll();
                echo json_encode(['success' => true, 'classes' => $classes]);
                break;
                
            case 'get_students':
                $grade = $_POST['grade'] ?? '';
                $search = $_POST['search'] ?? '';
                
                $sql = "SELECT s.id, s.full_name, s.current_grade, s.phone_number, s.current_result_status, s.is_new_registration, s.is_flagged,
                        c.id as current_class_id, c.name as current_class_name
                        FROM students s
                        LEFT JOIN class_enrollments ce ON s.id = ce.student_id AND ce.status = 'active'
                        LEFT JOIN classes c ON ce.class_id = c.id
                        WHERE 1=1";
                $params = [];
                
                if ($grade) {
                    $sql .= " AND s.current_grade = ?";
                    $params[] = $grade;
                }
                
                if ($search) {
                    $sql .= " AND (s.full_name LIKE ? OR s.phone_number LIKE ?)";
                    $params[] = "%$search%";
                    $params[] = "%$search%";
                }
                
                $sql .= " ORDER BY s.full_name";

                try {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $students = $stmt->fetchAll();
                } catch (PDOException $e) {
                    // Fallback: older schemas may not have is_new_registration / is_flagged yet
                    $fallbackSql = "SELECT s.id, s.full_name, s.current_grade, s.phone_number,
                                            c.id as current_class_id, c.name as current_class_name
                                     FROM students s
                                     LEFT JOIN class_enrollments ce ON s.id = ce.student_id AND ce.status = 'active'
                                     LEFT JOIN classes c ON ce.class_id = c.id
                                     WHERE 1=1";
                    if ($grade) { $fallbackSql .= " AND s.current_grade = ?"; }
                    if ($search) { $fallbackSql .= " AND (s.full_name LIKE ? OR s.phone_number LIKE ?)"; }
                    $fallbackSql .= " ORDER BY s.full_name";
                    $stmt = $pdo->prepare($fallbackSql);
                    $stmt->execute($params);
                    $students = $stmt->fetchAll();
                    // Supply default fields so UI logic can work without errors
                    foreach ($students as &$row) {
                        if (!isset($row['is_new_registration'])) { $row['is_new_registration'] = 0; }
                        if (!isset($row['is_flagged'])) { $row['is_flagged'] = 0; }
                        if (!array_key_exists('current_result_status', $row)) { $row['current_result_status'] = null; }
                    }
                    unset($row);
                }

                echo json_encode(['success' => true, 'students' => $students]);
                break;
                
            case 'create_class':
                $name = $_POST['name'] ?? '';
                $grade = $_POST['grade'] ?? '';
                $section = $_POST['section'] ?? '';
                $academic_year = $_POST['academic_year'] ?? date('Y');
                $capacity = $_POST['capacity'] ?? null;
                $description = $_POST['description'] ?? '';
                
                if (empty($name) || empty($grade)) {
                    echo json_encode(['success' => false, 'message' => 'Class name and grade are required']);
                    break;
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO classes (name, grade, section, academic_year, capacity, description) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $grade, $section, $academic_year, $capacity, $description]);
                
                $class_id = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Class created successfully', 'class_id' => $class_id]);
                break;
                
            case 'update_class':
                $class_id = (int)$_POST['class_id'];
                $name = $_POST['name'] ?? '';
                $grade = $_POST['grade'] ?? '';
                $section = $_POST['section'] ?? '';
                $academic_year = $_POST['academic_year'] ?? date('Y');
                $capacity = $_POST['capacity'] ?? null;
                $description = $_POST['description'] ?? '';
                
                if (empty($name) || empty($grade)) {
                    echo json_encode(['success' => false, 'message' => 'Class name and grade are required']);
                    break;
                }
                
                $stmt = $pdo->prepare("
                    UPDATE classes 
                    SET name = ?, grade = ?, section = ?, academic_year = ?, capacity = ?, description = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $grade, $section, $academic_year, $capacity, $description, $class_id]);
                
                echo json_encode(['success' => true, 'message' => 'Class updated successfully']);
                break;
                
            case 'delete_class':
                $class_id = (int)$_POST['class_id'];
                
                // Check if class has students
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM class_enrollments WHERE class_id = ? AND status = 'active'");
                $stmt->execute([$class_id]);
                $student_count = $stmt->fetchColumn();
                
                if ($student_count > 0) {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete class with active students']);
                    break;
                }
                
                $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
                $stmt->execute([$class_id]);
                
                echo json_encode(['success' => true, 'message' => 'Class deleted successfully']);
                break;
                
            case 'get_class_details':
                $class_id = (int)$_POST['class_id'];
                
                // Get class details
                $stmt = $pdo->prepare("
                    SELECT c.*, t.full_name as teacher_name
                    FROM classes c
                    LEFT JOIN class_teachers ct ON c.id = ct.class_id AND ct.role = 'primary' AND ct.is_active = 1
                    LEFT JOIN teachers t ON ct.teacher_id = t.id
                    WHERE c.id = ?
                ");
                $stmt->execute([$class_id]);
                $class = $stmt->fetch();
                
                if (!$class) {
                    echo json_encode(['success' => false, 'message' => 'Class not found']);
                    break;
                }
                
                // Get enrolled students (include optional flags when available)
                try {
                    $stmt = $pdo->prepare("
                        SELECT s.id, s.full_name, s.current_grade, s.phone_number, s.is_new_registration, s.is_flagged, ce.enrollment_date
                        FROM class_enrollments ce
                        JOIN students s ON ce.student_id = s.id
                        WHERE ce.class_id = ? AND ce.status = 'active'
                        ORDER BY s.full_name
                    ");
                    $stmt->execute([$class_id]);
                    $students = $stmt->fetchAll();
                } catch (PDOException $e) {
                    // Fallback for schemas without is_new_registration / is_flagged
                    $stmt = $pdo->prepare("
                        SELECT s.id, s.full_name, s.current_grade, s.phone_number, ce.enrollment_date
                        FROM class_enrollments ce
                        JOIN students s ON ce.student_id = s.id
                        WHERE ce.class_id = ? AND ce.status = 'active'
                        ORDER BY s.full_name
                    ");
                    $stmt->execute([$class_id]);
                    $students = $stmt->fetchAll();
                    foreach ($students as &$row) {
                        if (!isset($row['is_new_registration'])) { $row['is_new_registration'] = 0; }
                        if (!isset($row['is_flagged'])) { $row['is_flagged'] = 0; }
                    }
                    unset($row);
                }
                
                // Get assigned courses
                $stmt = $pdo->prepare("
                    SELECT ct.*, c.name as course_name, c.code as course_code, t.full_name as teacher_name
                    FROM course_teachers ct
                    JOIN courses c ON ct.course_id = c.id
                    JOIN teachers t ON ct.teacher_id = t.id
                    WHERE ct.class_id = ? AND ct.is_active = 1
                    ORDER BY ct.semester, c.name
                ");
                $stmt->execute([$class_id]);
                $courses = $stmt->fetchAll();
                
                // Get assigned teachers
                $stmt = $pdo->prepare("
                    SELECT ct.*, t.full_name as teacher_name, t.phone as teacher_phone
                    FROM class_teachers ct
                    JOIN teachers t ON ct.teacher_id = t.id
                    WHERE ct.class_id = ? AND ct.is_active = 1
                    ORDER BY ct.role, t.full_name
                ");
                $stmt->execute([$class_id]);
                $teachers = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'class' => $class, 'students' => $students, 'courses' => $courses, 'teachers' => $teachers]);
                break;
                
            case 'assign_students':
                $class_id = (int)$_POST['class_id'];
                $student_ids = json_decode($_POST['student_ids'], true) ?? [];
                
                if (empty($student_ids)) {
                    echo json_encode(['success' => false, 'message' => 'No students selected']);
                    break;
                }
                
                // Check class capacity
                $stmt = $pdo->prepare("SELECT capacity FROM classes WHERE id = ?");
                $stmt->execute([$class_id]);
                $capacity = $stmt->fetchColumn();
                
                if ($capacity) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM class_enrollments WHERE class_id = ? AND status = 'active'");
                    $stmt->execute([$class_id]);
                    $current_count = $stmt->fetchColumn();
                    
                    if ($current_count + count($student_ids) > $capacity) {
                        echo json_encode(['success' => false, 'message' => 'Adding these students would exceed class capacity']);
                        break;
                    }
                }
                
                // Assign students
                $pdo->beginTransaction();
                try {
                    $assigned_count = 0;
                    // Prepare statements once
                    $checkActiveStmt = $pdo->prepare("SELECT status FROM class_enrollments WHERE student_id = ? AND class_id = ? LIMIT 1");
                    $upsertStmt = $pdo->prepare(
                        "INSERT INTO class_enrollments (class_id, student_id, enrollment_date, status)
                         VALUES (?, ?, CURDATE(), 'active')
                         ON DUPLICATE KEY UPDATE status = 'active', enrollment_date = VALUES(enrollment_date)"
                    );
                    foreach ($student_ids as $student_id) {
                        // If already active, skip
                        $checkActiveStmt->execute([$student_id, $class_id]);
                        $row = $checkActiveStmt->fetch(PDO::FETCH_ASSOC);
                        if ($row && strtolower((string)$row['status']) === 'active') {
                            continue;
                        }
                        // Insert or reactivate existing enrollment
                        $upsertStmt->execute([$class_id, $student_id]);
                        $assigned_count++;
                    }
                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => $assigned_count . ' students assigned successfully']);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Assignment failed: ' . $e->getMessage()]);
                }
                break;

            case 'quick_add_students':
                $class_id = (int)($_POST['class_id'] ?? 0);
                $raw_names = trim((string)($_POST['full_names'] ?? ''));

                if ($class_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Class ID is required']);
                    break;
                }

                if ($raw_names === '') {
                    echo json_encode(['success' => false, 'message' => 'Please enter at least one full name']);
                    break;
                }

                // Support one name per line, comma-separated, or semicolon-separated.
                $parts = preg_split('/[\r\n,;]+/', $raw_names) ?: [];
                $name_list = [];
                foreach ($parts as $name) {
                    $clean = trim(preg_replace('/\s+/', ' ', (string)$name));
                    if ($clean !== '') {
                        $name_list[] = $clean;
                    }
                }
                $name_list = array_values(array_unique($name_list));

                if (empty($name_list)) {
                    echo json_encode(['success' => false, 'message' => 'No valid names found']);
                    break;
                }

                // Get class details for defaults and capacity checks.
                $stmt = $pdo->prepare("SELECT grade, capacity FROM classes WHERE id = ?");
                $stmt->execute([$class_id]);
                $class_row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$class_row) {
                    echo json_encode(['success' => false, 'message' => 'Class not found']);
                    break;
                }

                $default_grade = trim((string)($class_row['grade'] ?? 'new'));
                if ($default_grade === '') {
                    $default_grade = 'new';
                }
                $capacity = isset($class_row['capacity']) ? (int)$class_row['capacity'] : 0;

                $pdo->beginTransaction();
                try {
                    $currentCountStmt = $pdo->prepare("SELECT COUNT(*) FROM class_enrollments WHERE class_id = ? AND status = 'active'");
                    $currentCountStmt->execute([$class_id]);
                    $current_active_count = (int)$currentCountStmt->fetchColumn();

                    $findByNameStmt = $pdo->prepare("
                        SELECT id
                        FROM students
                        WHERE LOWER(TRIM(full_name)) = LOWER(TRIM(?))
                        ORDER BY id DESC
                        LIMIT 1
                    ");
                    $findEnrollmentStmt = $pdo->prepare("
                        SELECT status
                        FROM class_enrollments
                        WHERE class_id = ? AND student_id = ?
                        LIMIT 1
                    ");
                    $upsertEnrollmentStmt = $pdo->prepare("
                        INSERT INTO class_enrollments (class_id, student_id, enrollment_date, status)
                        VALUES (?, ?, CURDATE(), 'active')
                        ON DUPLICATE KEY UPDATE status = 'active', enrollment_date = VALUES(enrollment_date)
                    ");

                    // Prefer insert with is_new_registration; fallback for older schemas.
                    $insertStudentSqlA = "
                        INSERT INTO students (
                            full_name, christian_name, gender, birth_date, current_grade,
                            has_spiritual_father, living_with, is_new_registration
                        ) VALUES (?, ?, 'male', '0000-00-00', ?, 'none', 'both_parents', 1)
                    ";
                    $insertStudentSqlB = "
                        INSERT INTO students (
                            full_name, christian_name, gender, birth_date, current_grade,
                            has_spiritual_father, living_with
                        ) VALUES (?, ?, 'male', '0000-00-00', ?, 'none', 'both_parents')
                    ";
                    $insertStudentStmt = $pdo->prepare($insertStudentSqlA);
                    $insertSupportsNewFlag = true;

                    $created_count = 0;
                    $existing_count = 0;
                    $enrolled_count = 0;
                    $already_enrolled_count = 0;
                    $skipped_capacity_count = 0;
                    $added_student_ids = [];

                    foreach ($name_list as $full_name) {
                        $findByNameStmt->execute([$full_name]);
                        $student_id = (int)($findByNameStmt->fetchColumn() ?: 0);

                        if ($student_id <= 0) {
                            try {
                                $insertStudentStmt->execute([$full_name, $full_name, $default_grade]);
                            } catch (PDOException $e) {
                                if ($insertSupportsNewFlag) {
                                    $insertSupportsNewFlag = false;
                                    $insertStudentStmt = $pdo->prepare($insertStudentSqlB);
                                    $insertStudentStmt->execute([$full_name, $full_name, $default_grade]);
                                } else {
                                    throw $e;
                                }
                            }
                            $student_id = (int)$pdo->lastInsertId();
                            $created_count++;
                        } else {
                            $existing_count++;
                        }

                        if ($student_id <= 0) {
                            continue;
                        }

                        $findEnrollmentStmt->execute([$class_id, $student_id]);
                        $enroll_row = $findEnrollmentStmt->fetch(PDO::FETCH_ASSOC);
                        $already_active = $enroll_row && strtolower((string)($enroll_row['status'] ?? '')) === 'active';
                        if ($already_active) {
                            $already_enrolled_count++;
                            continue;
                        }

                        if ($capacity > 0 && $current_active_count >= $capacity) {
                            $skipped_capacity_count++;
                            continue;
                        }

                        $upsertEnrollmentStmt->execute([$class_id, $student_id]);
                        $enrolled_count++;
                        $current_active_count++;
                        $added_student_ids[] = $student_id;
                    }

                    $pdo->commit();

                    $message = "Quick add complete: {$enrolled_count} enrolled";
                    if ($created_count > 0) {
                        $message .= ", {$created_count} new";
                    }
                    if ($existing_count > 0) {
                        $message .= ", {$existing_count} existing";
                    }
                    if ($already_enrolled_count > 0) {
                        $message .= ", {$already_enrolled_count} already in class";
                    }
                    if ($skipped_capacity_count > 0) {
                        $message .= ", {$skipped_capacity_count} skipped (capacity)";
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => $message,
                        'created_count' => $created_count,
                        'existing_count' => $existing_count,
                        'enrolled_count' => $enrolled_count,
                        'already_enrolled_count' => $already_enrolled_count,
                        'skipped_capacity_count' => $skipped_capacity_count,
                        'student_ids' => $added_student_ids
                    ]);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Quick add failed: ' . $e->getMessage()]);
                }
                break;
                
            case 'remove_student':
                $class_id = (int)$_POST['class_id'];
                $student_id = (int)$_POST['student_id'];
                
                $stmt = $pdo->prepare("UPDATE class_enrollments SET status = 'dropped' WHERE class_id = ? AND student_id = ?");
                $stmt->execute([$class_id, $student_id]);
                
                echo json_encode(['success' => true, 'message' => 'Student removed from class']);
                break;

            case 'move_student':
                $class_id = (int)($_POST['class_id'] ?? 0);
                $student_id = (int)($_POST['student_id'] ?? 0);

                if ($class_id <= 0 || $student_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid class or student']);
                    break;
                }

                $pdo->beginTransaction();
                try {
                    // Validate target class and capacity.
                    $stmt = $pdo->prepare("SELECT id, name, capacity FROM classes WHERE id = ?");
                    $stmt->execute([$class_id]);
                    $targetClass = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$targetClass) {
                        throw new Exception('Target class not found');
                    }

                    $stmt = $pdo->prepare("SELECT id FROM class_enrollments WHERE class_id = ? AND student_id = ? AND status = 'active' LIMIT 1");
                    $stmt->execute([$class_id, $student_id]);
                    $alreadyInTarget = (bool)$stmt->fetchColumn();

                    if (!$alreadyInTarget && !empty($targetClass['capacity'])) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM class_enrollments WHERE class_id = ? AND status = 'active'");
                        $stmt->execute([$class_id]);
                        $targetCount = (int)$stmt->fetchColumn();
                        if ($targetCount >= (int)$targetClass['capacity']) {
                            throw new Exception('Target class is already full');
                        }
                    }

                    // Mark other active enrollments as transferred to keep one active class.
                    $stmt = $pdo->prepare("UPDATE class_enrollments SET status = 'transferred' WHERE student_id = ? AND status = 'active' AND class_id <> ?");
                    $stmt->execute([$student_id, $class_id]);

                    // Activate enrollment in target class.
                    $stmt = $pdo->prepare("
                        INSERT INTO class_enrollments (class_id, student_id, enrollment_date, status)
                        VALUES (?, ?, CURDATE(), 'active')
                        ON DUPLICATE KEY UPDATE status = 'active', enrollment_date = VALUES(enrollment_date)
                    ");
                    $stmt->execute([$class_id, $student_id]);

                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Student moved successfully']);
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                break;

            case 'move_students_bulk':
                $class_id = (int)($_POST['class_id'] ?? 0);
                $student_ids = json_decode($_POST['student_ids'] ?? '[]', true) ?: [];
                $student_ids = array_values(array_unique(array_map('intval', $student_ids)));
                $student_ids = array_values(array_filter($student_ids, function($v){ return $v > 0; }));

                if ($class_id <= 0 || empty($student_ids)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid class or students']);
                    break;
                }

                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("SELECT id, capacity FROM classes WHERE id = ?");
                    $stmt->execute([$class_id]);
                    $targetClass = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$targetClass) {
                        throw new Exception('Target class not found');
                    }

                    // Capacity check: count only students not already active in target.
                    $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
                    $alreadySql = "SELECT student_id FROM class_enrollments WHERE class_id = ? AND status = 'active' AND student_id IN ($placeholders)";
                    $stmt = $pdo->prepare($alreadySql);
                    $stmt->execute(array_merge([$class_id], $student_ids));
                    $alreadyActiveInTarget = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
                    $toActivate = array_diff($student_ids, $alreadyActiveInTarget);

                    if (!empty($targetClass['capacity'])) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM class_enrollments WHERE class_id = ? AND status = 'active'");
                        $stmt->execute([$class_id]);
                        $currentCount = (int)$stmt->fetchColumn();
                        if ($currentCount + count($toActivate) > (int)$targetClass['capacity']) {
                            throw new Exception('Target class capacity would be exceeded');
                        }
                    }

                    $deactivateStmt = $pdo->prepare("UPDATE class_enrollments SET status = 'transferred' WHERE student_id = ? AND status = 'active' AND class_id <> ?");
                    $upsertStmt = $pdo->prepare("
                        INSERT INTO class_enrollments (class_id, student_id, enrollment_date, status)
                        VALUES (?, ?, CURDATE(), 'active')
                        ON DUPLICATE KEY UPDATE status = 'active', enrollment_date = VALUES(enrollment_date)
                    ");

                    $movedCount = 0;
                    foreach ($student_ids as $sid) {
                        $deactivateStmt->execute([$sid, $class_id]);
                        $upsertStmt->execute([$class_id, $sid]);
                        $movedCount++;
                    }

                    $pdo->commit();
                    echo json_encode([
                        'success' => true,
                        'message' => $movedCount . ' students moved successfully',
                        'moved_count' => $movedCount
                    ]);
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                break;
                
            case 'get_teachers':
                $stmt = $pdo->query("SELECT id, full_name, phone FROM teachers WHERE is_active = 1 ORDER BY full_name");
                $teachers = $stmt->fetchAll();
                echo json_encode(['success' => true, 'teachers' => $teachers]);
                break;
                
            case 'assign_teacher':
                $class_id = (int)$_POST['class_id'];
                $teacher_id = (int)$_POST['teacher_id'];
                $role = $_POST['role'] ?? 'primary';
                
                // Deactivate existing teacher for this role
                $stmt = $pdo->prepare("UPDATE class_teachers SET is_active = 0 WHERE class_id = ? AND role = ?");
                $stmt->execute([$class_id, $role]);
                
                // Assign new teacher
                $stmt = $pdo->prepare("
                    INSERT INTO class_teachers (class_id, teacher_id, role, assigned_date, is_active) 
                    VALUES (?, ?, ?, CURDATE(), 1)
                ");
                $stmt->execute([$class_id, $teacher_id, $role]);
                
                echo json_encode(['success' => true, 'message' => 'Teacher assigned successfully']);
                break;
                
            case 'get_grade_student_count':
                $grade = $_POST['grade'] ?? '';
                
                if (empty($grade)) {
                    echo json_encode(['success' => false, 'message' => 'Grade is required']);
                    break;
                }
                
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE current_grade = ?");
                $stmt->execute([$grade]);
                $count = $stmt->fetchColumn();
                
                echo json_encode(['success' => true, 'count' => $count]);
                break;
                
            case 'create_section_classes':
                $grade = $_POST['grade'] ?? '';
                $sections = $_POST['sections'] ?? [];
                $capacity = $_POST['capacity'] ?? null;
                $academic_year = $_POST['academic_year'] ?? date('Y');
                
                if (empty($grade) || empty($sections)) {
                    echo json_encode(['success' => false, 'message' => 'Grade and sections are required']);
                    break;
                }
                
                $pdo->beginTransaction();
                try {
                    $created_classes = [];
                    foreach ($sections as $section) {
                        $name = "Grade $grade Section $section";
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO classes (name, grade, section, academic_year, capacity) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$name, $grade, $section, $academic_year, $capacity]);
                        
                        $class_id = $pdo->lastInsertId();
                        $created_classes[] = [
                            'id' => $class_id,
                            'name' => $name,
                            'grade' => $grade,
                            'section' => $section
                        ];
                    }
                    
                    $pdo->commit();
                    echo json_encode([
                        'success' => true, 
                        'message' => count($sections) . ' section classes created successfully',
                        'classes' => $created_classes
                    ]);
                } catch (Exception $e) {
                    $pdo->rollback();
                    echo json_encode(['success' => false, 'message' => 'Error creating section classes: ' . $e->getMessage()]);
                }
                break;
                
            case 'auto_allocate_students':
                $grade = $_POST['grade'] ?? '';
                $class_ids = $_POST['class_ids'] ?? [];
                $max_capacity = $_POST['max_capacity'] ?? 50;
                $dry_run = isset($_POST['dry_run']) ? (int)$_POST['dry_run'] : 0; // 1 = preview only
                $update_current_grade = isset($_POST['update_current_grade']) ? (int)$_POST['update_current_grade'] : 0;
                
                if (empty($grade) || empty($class_ids)) {
                    echo json_encode(['success' => false, 'message' => 'Grade and class IDs are required']);
                    break;
                }
                
                $pdo->beginTransaction();
                try {
                    // Fetch target classes metadata
                    $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
                    $stmt = $pdo->prepare("SELECT id, name FROM classes WHERE id IN ($placeholders)");
                    $stmt->execute($class_ids);
                    $targetClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $youthClassIds = [];
                    $adultClassIds = [];
                    foreach ($targetClasses as $tc) {
                        $n = strtolower($tc['name'] ?? '');
                        if (strpos($n, 'youth') !== false) $youthClassIds[] = (int)$tc['id'];
                        if (strpos($n, 'adult') !== false) $adultClassIds[] = (int)$tc['id'];
                    }
                    
                    // Get all students for this grade who are not yet assigned to any class in same grade scope
                    // For 'new' grade, include birth_date to compute age for stream (youth/adult)
                    if ($grade === 'new') {
                        $stmt = $pdo->prepare("\n                            SELECT s.id, s.birth_date\n                            FROM students s\n                            WHERE s.current_grade = ?\n                            AND s.id NOT IN (\n                                SELECT DISTINCT ce.student_id \n                                FROM class_enrollments ce \n                                WHERE ce.status = 'active'\n                            )\n                            ORDER BY s.full_name\n                        ");
                        $stmt->execute([$grade]);
                        $studentsRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } else {
                        $stmt = $pdo->prepare("\n                            SELECT s.id, s.birth_date\n                            FROM students s\n                            WHERE s.current_grade = ?\n                            AND s.id NOT IN (\n                                SELECT DISTINCT ce.student_id \n                                FROM class_enrollments ce \n                                JOIN classes c ON ce.class_id = c.id \n                                WHERE c.grade = ? AND ce.status = 'active'\n                            )\n                            ORDER BY s.full_name\n                        ");
                        $stmt->execute([$grade, $grade]);
                        $studentsRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                    
                    if (empty($studentsRows)) {
                        echo json_encode(['success' => false, 'message' => 'No unassigned students found for this selection']);
                        break;
                    }
                    
                    // Split students by stream using Ethiopian age (youth >=18)
                    $studentsYouth = [];
                    $studentsAdult = [];
                    foreach ($studentsRows as $row) {
                        $age = ethiopian_age_from_string($row['birth_date'] ?? '');
                        if ($age !== null && $age >= 18) {
                            $studentsYouth[] = (int)$row['id'];
                        } else {
                            $studentsAdult[] = (int)$row['id'];
                        }
                    }
                    
                    // Fallbacks if streams not explicitly present in class names
                    if (empty($youthClassIds)) $youthClassIds = array_map('intval', $class_ids);
                    if (empty($adultClassIds)) $adultClassIds = array_map('intval', $class_ids);
                    
                    $allocationsMap = [];
                    foreach ($class_ids as $cid) { $allocationsMap[(int)$cid] = 0; }
                    $proposals = [];
                    foreach ($class_ids as $cid) { $proposals[(int)$cid] = []; }
                    
                    // Helper to assign a list of student IDs to a list of class IDs round-robin
                    $assignRoundRobin = function(array $stuIds, array $clsIds) use ($pdo, &$allocationsMap, &$proposals, $dry_run, $update_current_grade) {
                        if (empty($stuIds) || empty($clsIds)) return;
                        $idx = 0; $n = count($clsIds);
                        foreach ($stuIds as $sid) {
                            $classId = (int)$clsIds[$idx % $n];
                            // Skip if already enrolled
                            $stmt = $pdo->prepare("SELECT id FROM class_enrollments WHERE student_id = ? AND class_id = ? AND status = 'active'");
                            $stmt->execute([$sid, $classId]);
                            if (!$stmt->fetch()) {
                                if ($dry_run) {
                                    $allocationsMap[$classId] = ($allocationsMap[$classId] ?? 0) + 1;
                                    $proposals[$classId][] = (int)$sid;
                                } else {
                                    $stmt = $pdo->prepare("INSERT INTO class_enrollments (class_id, student_id, enrollment_date) VALUES (?, ?, CURDATE())");
                                    $stmt->execute([$classId, $sid]);
                                    $allocationsMap[$classId] = ($allocationsMap[$classId] ?? 0) + 1;
                                    $proposals[$classId][] = (int)$sid;
                                    if ($update_current_grade) {
                                        // sync student's current_grade to assigned class grade
                                        $u = $pdo->prepare("UPDATE students s JOIN classes c ON c.id = ? SET s.current_grade = c.grade WHERE s.id = ?");
                                        $u->execute([$classId, $sid]);
                                    }
                                }
                            }
                            $idx++;
                        }
                    };
                    
                    // Allocate by stream when grade is 'new', else default spread
                    if ($grade === 'new') {
                        $assignRoundRobin($studentsYouth, $youthClassIds);
                        $assignRoundRobin($studentsAdult, $adultClassIds);
                        $totalAssigned = count($studentsYouth) + count($studentsAdult);
                    } else {
                        // Default previous behavior
                        $students = array_map(fn($r) => (int)$r['id'], $studentsRows);
                        $assignRoundRobin($students, array_map('intval', $class_ids));
                        $totalAssigned = count($students);
                    }
                    
                    $pdo->commit();
                    $allocations = [];
                    foreach ($allocationsMap as $cid => $cnt) { $allocations[] = ['class_id' => $cid, 'student_count' => $cnt, 'student_ids' => $proposals[$cid]]; }
                    $resp = [
                        'success' => true,
                        'message' => ($dry_run ? 'Preview: ' : '') . $totalAssigned . ' students ' . ($dry_run ? 'would be ' : '') . 'allocated across ' . count($class_ids) . ' classes',
                        'allocations' => $allocations,
                        'dry_run' => $dry_run,
                        'update_current_grade' => $update_current_grade
                    ];
                    echo json_encode($resp);
                } catch (Exception $e) {
                    $pdo->rollback();
                    echo json_encode(['success' => false, 'message' => 'Error allocating students: ' . $e->getMessage()]);
                }
                break;
                
            case 'get_courses':
                // Get all active courses
                $stmt = $pdo->query("
                    SELECT id, name, code 
                    FROM courses 
                    WHERE is_active = 1 
                    ORDER BY name
                ");
                $courses = $stmt->fetchAll();
                echo json_encode(['success' => true, 'courses' => $courses]);
                break;
                
            case 'assign_course':
                $class_id = (int)$_POST['class_id'];
                $course_id = (int)$_POST['course_id'];
                $teacher_id = (int)$_POST['teacher_id'];
                $semester = $_POST['semester'] ?? '1st';
                $academic_year = $_POST['academic_year'] ?? date('Y');
                $hours_per_week = $_POST['hours_per_week'] ?? 3;
                
                if (empty($class_id) || empty($course_id) || empty($teacher_id)) {
                    echo json_encode(['success' => false, 'message' => 'Class, course, and teacher are required']);
                    break;
                }
                
                try {
                    // Check if this assignment already exists
                    $stmt = $pdo->prepare("
                        SELECT id FROM course_teachers 
                        WHERE course_id = ? AND teacher_id = ? AND class_id = ? AND semester = ? AND academic_year = ? AND is_active = 1
                    ");
                    $stmt->execute([$course_id, $teacher_id, $class_id, $semester, $academic_year]);
                    
                    if ($stmt->fetch()) {
                        echo json_encode(['success' => false, 'message' => 'This course is already assigned to this class with the same teacher, semester, and academic year']);
                        break;
                    }
                    
                    // Assign course to class
                    $stmt = $pdo->prepare("
                        INSERT INTO course_teachers (course_id, teacher_id, class_id, semester, academic_year, hours_per_week) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$course_id, $teacher_id, $class_id, $semester, $academic_year, $hours_per_week]);
                    
                    echo json_encode(['success' => true, 'message' => 'Course assigned to class successfully']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Error assigning course: ' . $e->getMessage()]);
                }
                break;
                
            case 'remove_course_assignment':
                $assignment_id = (int)$_POST['assignment_id'];
                
                if (empty($assignment_id)) {
                    echo json_encode(['success' => false, 'message' => 'Assignment ID is required']);
                    break;
                }
                
                try {
                    $stmt = $pdo->prepare("UPDATE course_teachers SET is_active = 0 WHERE id = ?");
                    $stmt->execute([$assignment_id]);
                    
                    echo json_encode(['success' => true, 'message' => 'Course assignment removed successfully']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Error removing course assignment: ' . $e->getMessage()]);
                }
                break;
                
            case 'remove_teacher_assignment':
                $assignment_id = (int)$_POST['assignment_id'];
                
                if (empty($assignment_id)) {
                    echo json_encode(['success' => false, 'message' => 'Assignment ID is required']);
                    break;
                }
                
                try {
                    $stmt = $pdo->prepare("UPDATE class_teachers SET is_active = 0 WHERE id = ?");
                    $stmt->execute([$assignment_id]);
                    
                    echo json_encode(['success' => true, 'message' => 'Teacher assignment removed successfully']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Error removing teacher assignment: ' . $e->getMessage()]);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

$title = 'Class Management';
ob_start();
?>

<style>
/* Advanced compact styling for class management */
.class-card {
    transition: all 0.2s ease-in-out;
    border-radius: 0.375rem;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

.class-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

/* Drag and Drop Styles */
.draggable {
    cursor: move;
    user-select: none;
}

.draggable.dragging {
    opacity: 0.5;
    background-color: #dbeafe;
}

.drop-zone {
    transition: background-color 0.2s ease;
}

.drop-zone.drag-over {
    background-color: #dbeafe;
    border: 2px dashed #3b82f6;
}

.compact-table {
    font-size: 0.75rem; /* text-xs equivalent */
    line-height: 1rem; /* leading-4 equivalent */
}

.compact-table th,
.compact-table td {
    padding: 0.375rem 0.5rem; /* py-1.5 px-2 equivalent */
}

.compact-btn {
    padding: 0.375rem 0.625rem; /* py-1.5 px-2.5 equivalent */
    font-size: 0.75rem; /* text-xs equivalent */
    border-radius: 0.25rem; /* rounded equivalent */
}

.compact-input {
    padding: 0.375rem 0.625rem; /* py-1.5 px-2.5 equivalent */
    font-size: 0.75rem; /* text-xs equivalent */
    border-radius: 0.25rem; /* rounded equivalent */
}

.compact-modal {
    max-width: 90%;
    margin: 1.5rem auto;
}

@media (min-width: 768px) {
    .compact-modal {
        max-width: 900px;
    }
}

@media (min-width: 1280px) {
    .compact-modal {
        max-width: 1100px;
    }
}

.compact-form-group {
    margin-bottom: 0.75rem; /* mb-3 equivalent */
}

.compact-form-label {
    font-size: 0.75rem; /* text-xs equivalent */
    font-weight: 500; /* font-medium equivalent */
    margin-bottom: 0.25rem; /* mb-1 equivalent */
}

.compact-stat-card {
    padding: 0.75rem; /* p-3 equivalent */
    border-radius: 0.375rem; /* rounded-lg equivalent */
}

.compact-stat-value {
    font-size: 1rem; /* text-lg equivalent */
    font-weight: 600; /* font-semibold equivalent */
}

.compact-stat-label {
    font-size: 0.75rem; /* text-xs equivalent */
    font-weight: 500; /* font-medium equivalent */
}

.compact-tab {
    padding: 0.5rem 0.25rem; /* py-2 px-1 equivalent */
    font-size: 0.75rem; /* text-sm equivalent */
    font-weight: 500; /* font-medium equivalent */
}

.compact-action-bar {
    gap: 0.375rem; /* gap-1.5 equivalent */
}

.compact-action-btn {
    padding: 0.375rem; /* p-1.5 equivalent */
    font-size: 0.75rem; /* text-xs equivalent */
    border-radius: 0.25rem; /* rounded-md equivalent */
}

.compact-modal-header {
    padding-bottom: 0.75rem; /* pb-3 equivalent */
    font-size: 0.875rem; /* text-lg equivalent */
    font-weight: 600; /* font-semibold equivalent */
}

.compact-modal-content {
    padding: 0.75rem; /* py-3 equivalent */
}

/* Enhanced dark mode support */
.dark .class-card {
    background-color: #1f2937; /* gray-800 equivalent */
    border-color: #374151; /* gray-700 equivalent */
}

.dark .compact-table th {
    background-color: #374151; /* gray-700 equivalent */
    color: #d1d5db; /* gray-300 equivalent */
}

.dark .compact-table td {
    background-color: #1f2937; /* gray-800 equivalent */
    color: #f9fafb; /* gray-50 equivalent */
}

/* Advanced hover states */
.hover\:compact-bg:hover {
    background-color: #f9fafb; /* gray-50 equivalent */
}

.dark .hover\:compact-bg:hover {
    background-color: #374151; /* gray-700 equivalent */
}

/* Compact form elements */
.compact-select {
    padding: 0.375rem 0.625rem; /* py-1.5 px-2.5 equivalent */
    font-size: 0.75rem; /* text-xs equivalent */
    border-radius: 0.25rem; /* rounded equivalent */
    background-position: right 0.375rem center; /* bg-position adjusted */
    background-size: 1rem 1rem; /* bg-size adjusted */
}

/* Compact modal styles */
.compact-modal-overlay {
    background-color: rgba(107, 114, 128, 0.5); /* gray-500 with 50% opacity */
}

.compact-modal-container {
    border-radius: 0.5rem; /* rounded-lg equivalent */
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

/* Compact card styles */
.compact-card {
    border-radius: 0.375rem; /* rounded-md equivalent */
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
}

/* Advanced grid layouts */
.compact-grid {
    display: grid;
    gap: 0.75rem; /* gap-3 equivalent */
}

.compact-grid-cols-2 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.compact-grid-cols-4 {
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

@media (min-width: 768px) {
    .md\:compact-grid-cols-2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    
    .md\:compact-grid-cols-4 {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

/* Compact typography */
.compact-text-xs {
    font-size: 0.75rem; /* text-xs equivalent */
    line-height: 1rem; /* leading-4 equivalent */
}

.compact-text-sm {
    font-size: 0.8125rem; /* slightly larger than text-xs but smaller than text-sm */
    line-height: 1.25rem; /* leading-5 equivalent */
}

.compact-text-base {
    font-size: 0.875rem; /* text-sm equivalent */
    line-height: 1.25rem; /* leading-5 equivalent */
}

/* Compact spacing utilities */
.compact-p-1 {
    padding: 0.25rem; /* p-1 equivalent */
}

.compact-p-2 {
    padding: 0.5rem; /* p-2 equivalent */
}

.compact-p-3 {
    padding: 0.75rem; /* p-3 equivalent */
}

.compact-p-4 {
    padding: 1rem; /* p-4 equivalent */
}

.compact-px-1 {
    padding-left: 0.25rem; /* px-1 equivalent */
    padding-right: 0.25rem;
}

.compact-px-2 {
    padding-left: 0.5rem; /* px-2 equivalent */
    padding-right: 0.5rem;
}

.compact-px-3 {
    padding-left: 0.75rem; /* px-3 equivalent */
    padding-right: 0.75rem;
}

.compact-py-1 {
    padding-top: 0.25rem; /* py-1 equivalent */
    padding-bottom: 0.25rem;
}

.compact-py-1\.5 {
    padding-top: 0.375rem; /* py-1.5 equivalent */
    padding-bottom: 0.375rem;
}

.compact-py-2 {
    padding-top: 0.5rem; /* py-2 equivalent */
    padding-bottom: 0.5rem;
}

.compact-m-1 {
    margin: 0.25rem; /* m-1 equivalent */
}

.compact-m-2 {
    margin: 0.5rem; /* m-2 equivalent */
}

.compact-mb-1 {
    margin-bottom: 0.25rem; /* mb-1 equivalent */
}

.compact-mb-2 {
    margin-bottom: 0.5rem; /* mb-2 equivalent */
}

.compact-mb-3 {
    margin-bottom: 0.75rem; /* mb-3 equivalent */
}

.compact-mb-4 {
    margin-bottom: 1rem; /* mb-4 equivalent */
}

/* Compact border utilities */
.compact-border {
    border-width: 1px;
}

.compact-border-t {
    border-top-width: 1px;
}

.compact-border-b {
    border-bottom-width: 1px;
}

.compact-border-l {
    border-left-width: 1px;
}

.compact-border-r {
    border-right-width: 1px;
}

/* Compact rounded utilities */
.compact-rounded {
    border-radius: 0.25rem; /* rounded equivalent */
}

.compact-rounded-md {
    border-radius: 0.375rem; /* rounded-md equivalent */
}

.compact-rounded-lg {
    border-radius: 0.5rem; /* rounded-lg equivalent */
}

/* Compact shadow utilities */
.compact-shadow {
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
}

.compact-shadow-sm {
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

.compact-shadow-md {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

/* Compact flex utilities */
.compact-flex {
    display: flex;
}

.compact-flex-col {
    flex-direction: column;
}

.compact-flex-row {
    flex-direction: row;
}

.compact-items-center {
    align-items: center;
}

.compact-justify-between {
    justify-content: space-between;
}

.compact-gap-1 {
    gap: 0.25rem;
}

.compact-gap-2 {
    gap: 0.5rem;
}

.compact-gap-3 {
    gap: 0.75rem;
}

/* Compact width utilities */
.compact-w-full {
    width: 100%;
}

.compact-w-1\/2 {
    width: 50%;
}

.compact-w-1\/3 {
    width: 33.333333%;
}

.compact-w-2\/3 {
    width: 66.666667%;
}

.compact-w-1\/4 {
    width: 25%;
}

.compact-w-3\/4 {
    width: 75%;
}

/* Compact height utilities */
.compact-h-4 {
    height: 1rem;
}

.compact-h-5 {
    height: 1.25rem;
}

.compact-h-6 {
    height: 1.5rem;
}

.compact-h-8 {
    height: 2rem;
}

.compact-h-10 {
    height: 2.5rem;
}

/* Compact text utilities */
.compact-font-medium {
    font-weight: 500;
}

.compact-font-semibold {
    font-weight: 600;
}

.compact-text-left {
    text-align: left;
}

.compact-text-center {
    text-align: center;
}

.compact-text-right {
    text-align: right;
}

/* Compact color utilities */
.compact-text-gray-500 {
    color: #6b7280; /* gray-500 equivalent */
}

.compact-text-gray-600 {
    color: #4b5563; /* gray-600 equivalent */
}

.compact-text-gray-700 {
    color: #374151; /* gray-700 equivalent */
}

.compact-text-gray-900 {
    color: #111827; /* gray-900 equivalent */
}

.dark .compact-text-gray-300 {
    color: #d1d5db; /* gray-300 equivalent */
}

.dark .compact-text-gray-400 {
    color: #9ca3af; /* gray-400 equivalent */
}

.dark .compact-text-gray-500 {
    color: #6b7280; /* gray-500 equivalent */
}

.dark .compact-text-white {
    color: #ffffff; /* white equivalent */
}

/* Compact background utilities */
.compact-bg-white {
    background-color: #ffffff; /* white equivalent */
}

.compact-bg-gray-50 {
    background-color: #f9fafb; /* gray-50 equivalent */
}

.compact-bg-gray-100 {
    background-color: #f3f4f6; /* gray-100 equivalent */
}

.dark .compact-bg-gray-700 {
    background-color: #374151; /* gray-700 equivalent */
}

.dark .compact-bg-gray-800 {
    background-color: #1f2937; /* gray-800 equivalent */
}

/* Compact border color utilities */
.compact-border-gray-200 {
    border-color: #e5e7eb; /* gray-200 equivalent */
}

.compact-border-gray-300 {
    border-color: #d1d5db; /* gray-300 equivalent */
}

.dark .compact-border-gray-600 {
    border-color: #4b5563; /* gray-600 equivalent */
}

.dark .compact-border-gray-700 {
    border-color: #374151; /* gray-700 equivalent */
}

/* Compact focus utilities */
.compact-focus\:ring-1:focus {
    --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
    --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(1px + var(--tw-ring-offset-width)) var(--tw-ring-color);
    box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
}

.compact-focus\:ring-primary-500:focus {
    --tw-ring-opacity: 1;
    --tw-ring-color: rgba(59, 130, 246, var(--tw-ring-opacity)); /* primary-500 equivalent */
}

.compact-focus\:border-primary-500:focus {
    --tw-border-opacity: 1;
    border-color: rgba(59, 130, 246, var(--tw-border-opacity)); /* primary-500 equivalent */
}

/* Compact hover utilities */
.compact-hover\:bg-gray-50:hover {
    background-color: #f9fafb; /* gray-50 equivalent */
}

.compact-hover\:bg-gray-100:hover {
    background-color: #f3f4f6; /* gray-100 equivalent */
}

.compact-hover\:text-gray-700:hover {
    color: #374151; /* gray-700 equivalent */
}

.compact-hover\:text-gray-900:hover {
    color: #111827; /* gray-900 equivalent */
}

.dark .compact-hover\:bg-gray-700:hover {
    background-color: #374151; /* gray-700 equivalent */
}

.dark .compact-hover\:bg-gray-600:hover {
    background-color: #4b5563; /* gray-600 equivalent */
}

.dark .compact-hover\:text-white:hover {
    color: #ffffff; /* white equivalent */
}

.dark .compact-hover\:text-gray-300:hover {
    color: #d1d5db; /* gray-300 equivalent */
}

/* Compact responsive utilities */
@media (min-width: 768px) {
    .md\:compact-flex-row {
        flex-direction: row;
    }
    
    .md\:compact-items-center {
        align-items: center;
    }
    
    .md\:compact-justify-between {
        justify-content: space-between;
    }
    
    .md\:compact-w-56 {
        width: 14rem; /* w-56 equivalent */
    }
}

/* Compact overrides for Class Details modal */
#class-details-modal .compact-panel {
    font-size: 0.8125rem; /* ~text-sm */
    line-height: 1.25rem; /* leading-5 */
}

#class-details-modal .compact-panel h3 {
    font-size: 1rem; /* text-base */
    line-height: 1.25rem;
}

/* Tables inside modal */
#class-details-modal .compact-panel table { font-size: 0.75rem; }
#class-details-modal .compact-panel th,
#class-details-modal .compact-panel td { padding: 0.375rem 0.5rem; }

/* Utility overrides for tighter spacing */
#class-details-modal .compact-panel .py-6 { padding-top: 0.75rem; padding-bottom: 0.75rem; }
#class-details-modal .compact-panel .py-4 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
#class-details-modal .compact-panel .px-4 { padding-left: 0.5rem; padding-right: 0.5rem; }
#class-details-modal .compact-panel .p-4 { padding: 0.75rem; }
#class-details-modal .compact-panel .gap-4 { gap: 0.5rem; }
#class-details-modal .compact-panel .mb-6 { margin-bottom: 0.75rem; }
#class-details-modal .compact-panel .text-lg { font-size: 0.875rem; line-height: 1.25rem; }
#class-details-modal .compact-panel .text-xl { font-size: 1rem; line-height: 1.25rem; }
#class-details-modal .compact-panel .rounded-lg { border-radius: 0.375rem; }

/* Buttons inside modal */
#class-details-modal .compact-panel button { padding: 0.375rem 0.625rem; font-size: 0.75rem; border-radius: 0.25rem; }
#class-details-modal .compact-panel .p-2 { padding: 0.375rem; }
#class-details-modal .compact-panel .px-3 { padding-left: 0.5rem; padding-right: 0.5rem; }
#class-details-modal .compact-panel .py-2 { padding-top: 0.375rem; padding-bottom: 0.375rem; }
</style>

<div class="space-y-3">
    <!-- Header with enhanced compact design -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
        <div>
            <h1 class="text-base font-bold text-gray-900 dark:text-white flex items-center">
                <i class="fas fa-chalkboard mr-2 text-primary-600 dark:text-primary-400 text-sm"></i>
                Class Management
            </h1>
            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Manage classes, assign students, and track enrollments</p>
        </div>
        <div class="flex flex-wrap gap-1">
            <button onclick="openCreateClassModal()" class="compact-btn bg-primary-600 hover:bg-primary-700 text-white flex items-center">
                <i class="fas fa-plus mr-1"></i> Create
            </button>
            <button onclick="openAutoAllocateModal()" class="compact-btn bg-green-600 hover:bg-green-700 text-white flex items-center">
                <i class="fas fa-magic mr-1"></i> Auto Allocate
            </button>
            <a href="drag_drop_courses.php" class="compact-btn bg-blue-600 hover:bg-blue-700 text-white flex items-center">
                <i class="fas fa-exchange-alt mr-1"></i> Drag & Drop Courses
            </a>
        </div>
    </div>

    <!-- Classes Table with compact design -->
    <div class="class-card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
        <div class="compact-p-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 compact-mb-3">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Classes</h2>
                <div class="relative compact-w-full md:compact-w-56">
                    <input type="text" id="class-search" placeholder="Search classes..." class="compact-input compact-w-full border border-gray-300 dark:border-gray-600 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    <div class="absolute inset-y-0 left-0 compact-pl-2 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400 text-xs"></i>
                    </div>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="compact-table min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Students</th>
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700" id="classes-table-body">
                        <tr>
                            <td colspan="6" class="text-center compact-py-3">
                                <div class="flex justify-center">
                                    <div class="animate-spin rounded-full compact-h-4 compact-w-4 border-b-2 border-primary-600"></div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Class Modal with compact design -->
<div id="class-modal" class="hidden fixed inset-0 compact-modal-overlay overflow-y-auto compact-h-full compact-w-full z-50">
    <div class="compact-modal compact-modal-container bg-white dark:bg-gray-800 border compact-w-11/12 md:compact-w-2/5">
        <div class="flex items-center justify-between compact-modal-header border-b border-gray-200 dark:border-gray-700">
            <h3 class="compact-modal-header text-gray-900 dark:text-white" id="class-modal-title">Create Class</h3>
            <button onclick="closeClassModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>
        <div class="compact-modal-content">
            <form id="class-form">
                <input type="hidden" id="class-id" value="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 compact-mb-2">
                    <div>
                        <label class="compact-form-label text-gray-700 dark:text-gray-300">Class Name *</label>
                        <input type="text" id="class-name" class="compact-input compact-w-full border border-gray-300 dark:border-gray-600 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" required>
                    </div>
                    <div>
                        <label class="compact-form-label text-gray-700 dark:text-gray-300">Grade *</label>
                        <select id="class-grade" class="compact-select compact-w-full border border-gray-300 dark:border-gray-600 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" required>
                            <option value="">Select Grade</option>
                            <option value="new">New Students</option>
                            <option value="1st">Grade 1</option>
                            <option value="2nd">Grade 2</option>
                            <option value="3rd">Grade 3</option>
                            <option value="4th">Grade 4</option>
                            <option value="5th">Grade 5</option>
                            <option value="6th">Grade 6</option>
                            <option value="7th">Grade 7</option>
                            <option value="8th">Grade 8</option>
                            <option value="9th">Grade 9</option>
                            <option value="10th">Grade 10</option>
                            <option value="11th">Grade 11</option>
                            <option value="12th">Grade 12</option>
                        </select>
                    </div>
                    <div>
                        <label class="compact-form-label text-gray-700 dark:text-gray-300">Section</label>
                        <input type="text" id="class-section" class="compact-input compact-w-full border border-gray-300 dark:border-gray-600 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" placeholder="A, B, C, etc.">
                    </div>
                    <div>
                        <label class="compact-form-label text-gray-700 dark:text-gray-300">Academic Year</label>
                        <input type="number" id="class-year" class="compact-input compact-w-full border border-gray-300 dark:border-gray-600 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" value="<?= date('Y') ?>">
                    </div>
                    <div>
                        <label class="compact-form-label text-gray-700 dark:text-gray-300">Capacity</label>
                        <input type="number" id="class-capacity" class="compact-input compact-w-full border border-gray-300 dark:border-gray-600 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" placeholder="Unlimited">
                    </div>
                </div>
                <div class="compact-mb-3">
                    <label class="compact-form-label text-gray-700 dark:text-gray-300">Description</label>
                    <textarea id="class-description" rows="2" class="compact-input compact-w-full border border-gray-300 dark:border-gray-600 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"></textarea>
                </div>
                <div class="flex justify-end compact-gap-2">
                    <button type="button" onclick="closeClassModal()" class="compact-btn bg-gray-600 hover:bg-gray-700 text-white">Cancel</button>
                    <button type="submit" class="compact-btn bg-primary-600 hover:bg-primary-700 text-white">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Auto Allocate Students Modal with compact design -->
<div id="auto-allocate-modal" class="hidden fixed inset-0 compact-modal-overlay overflow-y-auto compact-h-full compact-w-full z-50">
    <div class="compact-modal compact-modal-container bg-white dark:bg-gray-800 border compact-w-11/12 md:compact-w-2/5 max-h-[85vh] overflow-y-auto">
        <div class="flex items-center justify-between compact-modal-header border-b border-gray-200 dark:border-gray-700">
            <h3 class="compact-modal-header text-gray-900 dark:text-white">Auto Allocate Students</h3>
            <button onclick="closeAutoAllocateModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>
        <div class="compact-modal-content">
            <div class="compact-mb-3">
                <label class="compact-form-label text-gray-700 dark:text-gray-300">Select Grade *</label>
                <select id="allocate-grade" class="compact-select compact-w-full border border-gray-300 dark:border-gray-600 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    <option value="">Select Grade</option>
                    <option value="new">New Students</option>
                    <option value="1st">Grade 1</option>
                    <option value="2nd">Grade 2</option>
                    <option value="3rd">Grade 3</option>
                    <option value="4th">Grade 4</option>
                    <option value="5th">Grade 5</option>
                    <option value="6th">Grade 6</option>
                    <option value="7th">Grade 7</option>
                    <option value="8th">Grade 8</option>
                    <option value="9th">Grade 9</option>
                    <option value="10th">Grade 10</option>
                    <option value="11th">Grade 11</option>
                    <option value="12th">Grade 12</option>
                </select>
            </div>
            
            <div class="compact-mb-3">
                <label class="compact-form-label text-gray-700 dark:text-gray-300">Max Students Per Class</label>
                <input type="number" id="max-capacity" class="compact-input compact-w-full border border-gray-300 dark:border-gray-600 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" value="50">
            </div>
            
            <div class="compact-mb-3">
                <label class="compact-form-label text-gray-700 dark:text-gray-300">Sections (comma separated)</label>
                <input type="text" id="sections" class="compact-input compact-w-full border border-gray-300 dark:border-gray-600 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" placeholder="A,B,C or 1,2,3">
                <p class="text-xs text-gray-500 dark:text-gray-400 compact-mt-1">Enter section names separated by commas</p>
            </div>
            
            <div class="compact-mb-3" id="student-count-info" style="display: none;">
                <div class="compact-card bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 compact-p-3">
                    <p class="text-xs text-blue-800 dark:text-blue-200">
                        Found <span id="student-count" class="compact-font-semibold">0</span> students for this grade.
                        Based on your settings, we recommend creating <span id="recommended-classes" class="compact-font-semibold">0</span> classes.
                    </p>
                </div>
            </div>
            
            <div class="flex justify-end compact-gap-2">
                <button type="button" onclick="closeAutoAllocateModal()" class="compact-btn bg-gray-600 hover:bg-gray-700 text-white">Cancel</button>
                <button type="button" onclick="createSectionClasses()" class="compact-btn bg-green-600 hover:bg-green-700 text-white">Create & Allocate</button>
            </div>
        </div>
    </div>
</div>

<!-- Class Details Modal -->
<div id="class-details-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-6 mx-auto p-3 border w-11/12 md:w-11/12 lg:w-5/6 xl:w-4/5 max-h-[95vh] overflow-y-auto shadow-lg rounded-md bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 compact-panel">
        <div class="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Class Details</h3>
            <button onclick="closeClassDetailsModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="py-4" id="class-details-content">
            <div class="flex justify-center py-6">
                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary-600"></div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Include external JS with cache-busting so latest changes appear immediately
$classesJsPath = __DIR__ . '/js/classes.js';
$classesJsVer = is_file($classesJsPath) ? filemtime($classesJsPath) : time();
$page_script = '<script src="js/classes.js?v=' . $classesJsVer . '"></script>';

// Now render the page using the admin layout
echo renderAdminLayout($title, $content, $page_script);
?>
