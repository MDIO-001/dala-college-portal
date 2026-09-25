<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'connect.php';
require_once 'result_functions.php';
include 'check_role.php';

// ============================================
// ACCESS CONTROL - ADMIN, PROVOST, EXAM OFFICER
// ============================================
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (!canAccessResultEntry()) {
    header('Location: staff_dashboard.php?error=access_denied');
    exit();
}

// ============================================
// NEMO ROLE DIN MAI AMFANI
// ============================================
$user_role = $_SESSION['role'] ?? '';
$position = strtolower(trim($_SESSION['position'] ?? ''));
$is_admin = ($user_role == 'admin');
$is_provost = ($user_role == 'Provost') || ($position == 'provost');
$is_exam_officer = ($user_role == 'Exam Officer') || ($position == 'exam officer');

$admin_name = $_SESSION['fullname'] ?? 'Admin';
$message = '';
$message_type = '';

$session_options = getSessionOptions();
// ⚠️ GYARA: Yi amfani da $conn maimakon $pdo
$current_session = getSetting($conn, 'current_session') ?? '2026/2027';
$level_options = getLevelOptions();
$semester_options = ['First Semester', 'Second Semester'];

// ============================================
// FUNCTIONS
// ============================================
if (!function_exists('getLevelVariants')) {
    function getLevelVariants($level) {
        $map = [
            'NCE I'     => ['NCE I', 'NCEI', 'NCE 1', 'NCE1', '100'],
            'NCE II'    => ['NCE II', 'NCEII', 'NCE 2', 'NCE2', '200'],
            'NCE III'   => ['NCE III', 'NCEIII', 'NCE 3', 'NCE3', '300'],
            '400 Level' => ['400 Level', '400L', '400'],
            '500 Level' => ['500 Level', '500L', '500'],
        ];
        return $map[$level] ?? [$level];
    }
}

if (!function_exists('buildLevelCondition')) {
    function buildLevelCondition($level, $column = 'cr.level') {
        $variants = getLevelVariants($level);
        $placeholders = implode(',', array_fill(0, count($variants), '?'));
        return "$column IN ($placeholders)";
    }
}

// ============================================
// SAMUN DALIBAI (PDO)
// ============================================
function getRegisteredStudents($pdo, $combination, $level, $semester, $session) {
    $level_sql = buildLevelCondition($level, 'cr.level');
    $variants = getLevelVariants($level);

    $sql = "SELECT DISTINCT s.*, 
                   s.admission_year, 
                   s.programme_type,
                   s.current_level,
                   s.graduation_year
            FROM students s
            INNER JOIN course_registrations cr ON cr.student_id = s.id
            WHERE (s.combination = ? OR s.course = ?)
            AND $level_sql
            AND cr.semester = ?
            AND cr.academic_year = ?
            AND cr.status IN ('registered', 'completed')
            ORDER BY s.fullname";

    $params = array_merge([$combination, $combination], $variants, [$semester, $session]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRegisteredCourses($pdo, $student_id, $level, $semester, $session) {
    $level_sql = buildLevelCondition($level, 'cr.level');
    $variants = getLevelVariants($level);

    $sql = "SELECT cr.*, 
                   COALESCE(c.credits, cr.credits, 0) AS course_credits,
                   c.category,
                   c.course_title AS course_title_from_courses
            FROM course_registrations cr
            LEFT JOIN courses c 
                ON c.course_code = cr.course_code 
                AND c.level = ?
                AND c.semester = ?
            WHERE cr.student_id = ?
            AND $level_sql
            AND cr.semester = ?
            AND cr.academic_year = ?
            AND cr.status IN ('registered', 'completed')
            ORDER BY cr.course_code";

    $params = array_merge([$level, $semester, $student_id], $variants, [$semester, $session]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllRegisteredCourses($pdo, $combination, $level, $semester, $session) {
    $level_sql = buildLevelCondition($level, 'cr.level');
    $variants = getLevelVariants($level);

    $sql = "SELECT DISTINCT cr.course_code, cr.course_title, 
                   COALESCE(c.credits, cr.credits, 0) AS credits
            FROM course_registrations cr
            INNER JOIN students s ON s.id = cr.student_id
            LEFT JOIN courses c ON c.course_code = cr.course_code 
                AND c.level = ? 
                AND c.semester = ?
            WHERE (s.combination = ? OR s.course = ?)
            AND $level_sql
            AND cr.semester = ?
            AND cr.academic_year = ?
            AND cr.status IN ('registered', 'completed')
            ORDER BY cr.course_code";

    $params = array_merge([$level, $semester, $combination, $combination], $variants, [$semester, $session]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================
// DOWNLOAD ALL COURSES SHEET
// ============================================
if (isset($_GET['download_sheet'])) {
    $combination = $_GET['combination'] ?? '';
    $level = $_GET['level'] ?? '';
    $semester = $_GET['semester'] ?? '';
    $session = $_GET['session'] ?? $current_session;

    if (empty($combination) || empty($level) || empty($semester)) {
        header('Location: admin_result_entry.php');
        exit();
    }

    $students = getRegisteredStudents($pdo, $combination, $level, $semester, $session);
    $courses = getAllRegisteredCourses($pdo, $combination, $level, $semester, $session);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="result_sheet_' . str_replace('/', '_', $combination) . '_' . str_replace(' ', '_', $level) . '_' . str_replace('/', '_', $session) . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['DALA COLLEGE OF EDUCATION, KANO']);
    fputcsv($output, ['RESULT ENTRY SHEET']);
    fputcsv($output, ['Combination:', $combination]);
    fputcsv($output, ['Level:', $level]);
    fputcsv($output, ['Semester:', $semester]);
    fputcsv($output, ['Session:', $session]);
    fputcsv($output, []);

    $header = ['Reg No', 'Student Name'];
    foreach ($courses as $c) $header[] = $c['course_code'] . ' (' . $c['credits'] . ')';
    fputcsv($output, $header);

    foreach ($students as $s) {
        $row = [$s['reg_no'] ?? $s['student_id'], $s['fullname']];
        foreach ($courses as $c) $row[] = '';
        fputcsv($output, $row);
    }
    fclose($output);
    exit();
}

// ============================================
// DOWNLOAD SINGLE COURSE SHEET
// ============================================
if (isset($_GET['download_single'])) {
    $combination = $_GET['combination'] ?? '';
    $level = $_GET['level'] ?? '';
    $semester = $_GET['semester'] ?? '';
    $session = $_GET['session'] ?? $current_session;
    $course_code = strtoupper($_GET['course_code'] ?? '');

    if (empty($combination) || empty($level) || empty($semester) || empty($course_code)) {
        header('Location: admin_result_entry.php');
        exit();
    }

    $level_sql = buildLevelCondition($level, 'level');
    $variants = getLevelVariants($level);

    $sql = "SELECT * FROM course_registrations 
            WHERE course_code = ? 
            AND $level_sql
            AND semester = ? 
            LIMIT 1";
    $params = array_merge([$course_code], $variants, [$semester]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$course) {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE course_code = ? AND level = ? AND semester = ? LIMIT 1");
        $stmt->execute([$course_code, $level, $semester]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$course) {
        header('Location: admin_result_entry.php');
        exit();
    }

    $students = getRegisteredStudents($pdo, $combination, $level, $semester, $session);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="single_' . $course_code . '_' . str_replace('/', '_', $session) . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['DALA COLLEGE OF EDUCATION, KANO']);
    fputcsv($output, ['SINGLE COURSE RESULT SHEET']);
    fputcsv($output, ['Combination:', $combination]);
    fputcsv($output, ['Level:', $level]);
    fputcsv($output, ['Semester:', $semester]);
    fputcsv($output, ['Session:', $session]);
    fputcsv($output, ['Course:', $course_code . ' - ' . $course['course_title']]);
    fputcsv($output, ['Units:', $course['credits'] ?? $course['credit_units']]);
    fputcsv($output, []);
    fputcsv($output, ['Reg No', 'Student Name', $course_code . ' (' . ($course['credits'] ?? $course['credit_units']) . ')']);

    foreach ($students as $s) {
        fputcsv($output, [$s['reg_no'] ?? $s['student_id'], $s['fullname'], '']);
    }
    fclose($output);
    exit();
}

// ============================================
// SAVE: SINGLE STUDENT
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_single'])) {
    $student_id = intval($_POST['student_id']);
    $session = $_POST['session'];
    $level = $_POST['level'];
    $semester = $_POST['semester'];
    $combination = $_POST['combination'];

    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $s = $stmt->fetch(PDO::FETCH_ASSOC);

    $saved = 0;

    if (isset($_POST['scores']) && is_array($_POST['scores'])) {
        foreach ($_POST['scores'] as $course_code => $score) {
            if ($score === '' || $score === null) continue;
            $score = intval($score);
            if ($score < 0 || $score > 100) continue;

            $level_sql = buildLevelCondition($level, 'level');
            $variants = getLevelVariants($level);

            $sql = "SELECT * FROM course_registrations 
                    WHERE course_code = ? 
                    AND student_id = ? 
                    AND $level_sql
                    AND semester = ? 
                    AND academic_year = ?
                    LIMIT 1";
            $params = array_merge([$course_code, $student_id], $variants, [$semester, $session]);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $course = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$course) {
                $stmt = $pdo->prepare("SELECT * FROM courses WHERE course_code = ? AND level = ? LIMIT 1");
                $stmt->execute([$course_code, $level]);
                $course = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$course) continue;

            $course_title = $course['course_title'];
            $credits = $course['credits'] ?? $course['credit_units'] ?? 0;
            $g = getGrade($conn, $score);  // ⚠️ Yi amfani da $conn

            $check = $pdo->prepare("SELECT id FROM results 
                                    WHERE student_id = ? 
                                    AND course_code = ? 
                                    AND level = ? 
                                    AND semester = ? 
                                    AND session = ?");
            $check->execute([$student_id, $course_code, $level, $semester, $session]);

            if ($check->rowCount() > 0) {
                $update = $pdo->prepare("UPDATE results SET 
                    score = ?, grade = ?, grade_point = ?, remark = ?, credit_units = ?
                    WHERE student_id = ? AND course_code = ? AND level = ? AND semester = ? AND session = ?");
                $update->execute([$score, $g['grade'], $g['grade_point'], $g['remark'], $credits, $student_id, $course_code, $level, $semester, $session]);
                $saved++;
            } else {
                $insert = $pdo->prepare("INSERT INTO results 
                    (student_id, reg_no, student_name, course_code, course_title, credit_units, score, grade, grade_point, remark, level, semester, session, combination, entered_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insert->execute([
                    $student_id, $s['reg_no'], $s['fullname'], $course_code, $course_title, 
                    $credits, $score, $g['grade'], $g['grade_point'], $g['remark'], 
                    $level, $semester, $session, $combination, $_SESSION['user_id']
                ]);
                $saved++;
            }
        }
    }

    $message = "✅ Saved <strong>$saved</strong> result(s) for <strong>$level — $semester — $session</strong>!";
    $message_type = 'success';
}

// ============================================
// SAVE: SINGLE COURSE
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_single_course'])) {
    $course_code = strtoupper($_POST['course_code'] ?? '');
    $session = $_POST['session'];
    $level = $_POST['level'];
    $semester = $_POST['semester'];
    $combination = $_POST['combination'];

    $saved = 0;
    $updated = 0;

    if ($course_code && isset($_POST['student_scores']) && is_array($_POST['student_scores'])) {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE course_code = ? AND level = ? AND semester = ? LIMIT 1");
        $stmt->execute([$course_code, $level, $semester]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        $course_title = $course['course_title'] ?? $course_code;
        $credits = $course['credits'] ?? $course['credit_units'] ?? 0;

        foreach ($_POST['student_scores'] as $student_id => $score) {
            if ($score === '' || $score === null) continue;
            $score = intval($score);
            if ($score < 0 || $score > 100) continue;
            $student_id = intval($student_id);

            $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
            $stmt->execute([$student_id]);
            $s = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$s) continue;

            $g = getGrade($conn, $score);  // ⚠️ Yi amfani da $conn

            $check = $pdo->prepare("SELECT id FROM results 
                                    WHERE student_id = ? 
                                    AND course_code = ? 
                                    AND level = ? 
                                    AND semester = ? 
                                    AND session = ?");
            $check->execute([$student_id, $course_code, $level, $semester, $session]);

            if ($check->rowCount() > 0) {
                $update = $pdo->prepare("UPDATE results SET 
                    score = ?, grade = ?, grade_point = ?, remark = ?, credit_units = ?
                    WHERE student_id = ? AND course_code = ? AND level = ? AND semester = ? AND session = ?");
                $update->execute([$score, $g['grade'], $g['grade_point'], $g['remark'], $credits, $student_id, $course_code, $level, $semester, $session]);
                $updated++;
            } else {
                $insert = $pdo->prepare("INSERT INTO results 
                    (student_id, reg_no, student_name, course_code, course_title, credit_units, score, grade, grade_point, remark, level, semester, session, combination, entered_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insert->execute([
                    $student_id, $s['reg_no'], $s['fullname'], $course_code, $course_title, 
                    $credits, $score, $g['grade'], $g['grade_point'], $g['remark'], 
                    $level, $semester, $session, $combination, $_SESSION['user_id']
                ]);
                $saved++;
            }
        }
    }

    $message = "✅ Saved <strong>$saved</strong> new, updated <strong>$updated</strong> for <strong>$course_code</strong> ($level — $semester — $session)!";
    $message_type = 'success';
}

// ============================================
// SAVE: COMPLETE COMBINATION
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_complete'])) {
    $session = $_POST['session'];
    $level = $_POST['level'];
    $semester = $_POST['semester'];
    $combination = $_POST['combination'];

    $saved = 0;
    $updated = 0;

    if (isset($_POST['combination_scores']) && is_array($_POST['combination_scores'])) {
        $courses_data = getAllRegisteredCourses($pdo, $combination, $level, $semester, $session);
        $course_map = [];
        foreach ($courses_data as $c) {
            $course_map[$c['course_code']] = [
                'title' => $c['course_title'],
                'credits' => $c['credits']
            ];
        }

        foreach ($_POST['combination_scores'] as $student_id => $scores) {
            $student_id = intval($student_id);
            if (!is_array($scores)) continue;

            $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
            $stmt->execute([$student_id]);
            $s = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$s) continue;

            foreach ($scores as $course_code => $score) {
                if ($score === '' || $score === null) continue;
                $score = intval($score);
                if ($score < 0 || $score > 100) continue;

                $course_code = strtoupper($course_code);
                $course_title = $course_map[$course_code]['title'] ?? $course_code;
                $credits = $course_map[$course_code]['credits'] ?? 0;

                $g = getGrade($conn, $score);  // ⚠️ Yi amfani da $conn

                $check = $pdo->prepare("SELECT id FROM results 
                                        WHERE student_id = ? 
                                        AND course_code = ? 
                                        AND level = ? 
                                        AND semester = ? 
                                        AND session = ?");
                $check->execute([$student_id, $course_code, $level, $semester, $session]);

                if ($check->rowCount() > 0) {
                    $update = $pdo->prepare("UPDATE results SET 
                        score = ?, grade = ?, grade_point = ?, remark = ?, credit_units = ?
                        WHERE student_id = ? AND course_code = ? AND level = ? AND semester = ? AND session = ?");
                    $update->execute([$score, $g['grade'], $g['grade_point'], $g['remark'], $credits, $student_id, $course_code, $level, $semester, $session]);
                    $updated++;
                } else {
                    $insert = $pdo->prepare("INSERT INTO results 
                        (student_id, reg_no, student_name, course_code, course_title, credit_units, score, grade, grade_point, remark, level, semester, session, combination, entered_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $insert->execute([
                        $student_id, $s['reg_no'], $s['fullname'], $course_code, $course_title, 
                        $credits, $score, $g['grade'], $g['grade_point'], $g['remark'], 
                        $level, $semester, $session, $combination, $_SESSION['user_id']
                    ]);
                    $saved++;
                }
            }
        }
    }

    $message = "✅ Complete Combination: Saved <strong>$saved</strong> new, updated <strong>$updated</strong> ($level — $semester — $session)!";
    $message_type = 'success';
}

// ============================================
// BULK UPLOAD (CSV)
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_sheet'])) {
    $combination = $_POST['combination'];
    $level = $_POST['level'];
    $semester = $_POST['semester'];
    $session = $_POST['session'];

    if (isset($_FILES['sheet_file']) && $_FILES['sheet_file']['error'] == 0) {
        $file_tmp = $_FILES['sheet_file']['tmp_name'];
        $file_ext = strtolower(pathinfo($_FILES['sheet_file']['name'], PATHINFO_EXTENSION));

        if ($file_ext != 'csv') {
            $message = "❌ Please upload a CSV file only.";
            $message_type = 'error';
        } else {
            $file = fopen($file_tmp, 'r');
            $first_row = fgetcsv($file);
            $is_single = (strpos($first_row[0], 'SINGLE') !== false);

            if ($is_single) {
                for ($i = 0; $i < 7; $i++) fgetcsv($file);
            } else {
                for ($i = 0; $i < 6; $i++) fgetcsv($file);
            }

            $header = fgetcsv($file);
            $courses = [];
            for ($i = 2; $i < count($header); $i++) {
                $parts = explode(' ', $header[$i]);
                if (!empty($parts[0])) $courses[] = $parts[0];
            }

            $saved = 0; $updated = 0; $errors = []; $row_num = 7;

            while (($row = fgetcsv($file)) !== FALSE) {
                $row_num++;
                if (empty(array_filter($row))) continue;

                $reg_no = trim($row[0] ?? '');
                if (empty($reg_no)) continue;

                $stmt = $pdo->prepare("SELECT * FROM students WHERE reg_no = ? OR student_id = ? LIMIT 1");
                $stmt->execute([$reg_no, $reg_no]);
                $student = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$student) {
                    $errors[] = "Row $row_num: Student $reg_no not found";
                    continue;
                }

                for ($i = 0; $i < count($courses); $i++) {
                    $score = trim($row[$i + 2] ?? '');
                    if ($score === '') continue;
                    $score = intval($score);
                    if ($score < 0 || $score > 100) continue;

                    $course_code = $courses[$i];
                    $level_sql = buildLevelCondition($level, 'level');
                    $variants = getLevelVariants($level);

                    $sql = "SELECT * FROM course_registrations 
                            WHERE course_code = ? 
                            AND student_id = ? 
                            AND $level_sql
                            AND semester = ? 
                            AND academic_year = ?
                            LIMIT 1";
                    $params = array_merge([$course_code, $student['id']], $variants, [$semester, $session]);
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $course = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$course) {
                        $stmt = $pdo->prepare("SELECT * FROM courses WHERE course_code = ? AND level = ? LIMIT 1");
                        $stmt->execute([$course_code, $level]);
                        $course = $stmt->fetch(PDO::FETCH_ASSOC);
                    }

                    if (!$course) continue;

                    $credits = $course['credits'] ?? $course['credit_units'] ?? 0;
                    $g = getGrade($conn, $score);  // ⚠️ Yi amfani da $conn

                    $check = $pdo->prepare("SELECT id FROM results 
                                            WHERE student_id = ? 
                                            AND course_code = ? 
                                            AND level = ? 
                                            AND semester = ? 
                                            AND session = ?");
                    $check->execute([$student['id'], $course_code, $level, $semester, $session]);

                    if ($check->rowCount() > 0) {
                        $update = $pdo->prepare("UPDATE results SET score = ?, grade = ?, grade_point = ?, remark = ? 
                                   WHERE student_id = ? AND course_code = ? AND level = ? AND semester = ? AND session = ?");
                        $update->execute([$score, $g['grade'], $g['grade_point'], $g['remark'], $student['id'], $course_code, $level, $semester, $session]);
                        $updated++;
                    } else {
                        $insert = $pdo->prepare("INSERT INTO results 
                            (student_id, reg_no, student_name, course_code, course_title, credit_units, score, grade, grade_point, remark, level, semester, session, combination, entered_by) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $insert->execute([
                            $student['id'], $student['reg_no'], $student['fullname'], $course_code, $course['course_title'], 
                            $credits, $score, $g['grade'], $g['grade_point'], $g['remark'], 
                            $level, $semester, $session, $combination, $_SESSION['user_id']
                        ]);
                        $saved++;
                    }
                }
            }
            fclose($file);

            $total = $saved + $updated;
            if ($total > 0) {
                $message = "✅ Imported $level — $session!<br>📊 New: <strong>$saved</strong> | 🔄 Updated: <strong>$updated</strong>";
                if (!empty($errors)) $message .= "<br>⚠️ Errors: " . count($errors);
                $message_type = 'success';
            } else {
                $message = "❌ No results imported.";
                if (!empty($errors)) $message .= "<br>" . implode('<br>', array_slice($errors, 0, 5));
                $message_type = 'error';
            }
        }
    }
}

// ============================================
// SAMO COMBINATIONS
// ============================================
$combinations_list = [];
$stmt = $pdo->query("SELECT DISTINCT combination FROM students WHERE combination IS NOT NULL AND combination != '' ORDER BY combination");
while ($c = $stmt->fetch(PDO::FETCH_ASSOC)) $combinations_list[] = $c['combination'];

if (empty($combinations_list)) {
    $stmt = $pdo->query("SELECT DISTINCT combination FROM courses WHERE combination IS NOT NULL AND combination != '' ORDER BY combination");
    while ($c = $stmt->fetch(PDO::FETCH_ASSOC)) $combinations_list[] = $c['combination'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Result Entry</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0d2818;
            --secondary: #2e7d32;
            --accent: #ffd54f;
            --danger: #c62828;
            --bg: #f0f4f8;
            --card: #ffffff;
            --text: #1a2e1a;
            --text-muted: #6a8f6a;
            --border: #dce8dc;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Tahoma,sans-serif; background:var(--bg); padding:20px; color:var(--text); }
        .container { max-width:1500px; margin:0 auto; }

        .topbar {
            background:var(--primary); padding:15px 25px; display:flex;
            justify-content:space-between; align-items:center; flex-wrap:wrap;
            border-radius:12px; margin-bottom:20px;
        }
        .topbar .logo-title { color:var(--accent); font-size:1.3rem; font-weight:800; }
        .topbar .logo-title span { color:#a5d6a7; }
        .topbar .logo-sub { display:block; font-size:0.55rem; color:#c8e6c9; }
        .topbar nav a { color:#c8e6c9; text-decoration:none; padding:8px 16px; border-radius:25px; font-size:0.85rem; }
        .topbar nav a:hover { background:var(--secondary); }
        .topbar nav .logout { background:var(--danger); color:white !important; }
        .topbar .admin-badge { background:var(--danger); color:white; padding:6px 18px; border-radius:20px; font-size:0.8rem; font-weight:600; }
        .topbar .provost-badge { background:var(--accent); color:var(--primary); padding:6px 18px; border-radius:20px; font-size:0.8rem; font-weight:800; }
        .topbar .exam-badge { background:var(--secondary); color:white; padding:6px 18px; border-radius:20px; font-size:0.8rem; font-weight:600; }

        .result-nav {
            background:white; padding:15px; border-radius:12px; margin-bottom:20px;
            display:flex; gap:8px; flex-wrap:wrap; align-items:center;
            box-shadow:0 2px 10px rgba(0,0,0,0.05);
        }
        .result-nav .label { font-weight:700; color:var(--primary); margin-right:10px; font-size:0.85rem; }
        .result-nav a { padding:8px 15px; border-radius:6px; text-decoration:none; font-weight:600; font-size:0.75rem; }
        .r-course { background:var(--secondary); color:white; }
        .r-grade { background:#f9a825; color:var(--primary); }
        .r-entry { background:#8d2c2c; color:white; box-shadow:0 0 0 3px var(--accent); }
        .r-slip { background:#e53935; color:white; }
        .r-slip-pro { background:#a5d6a7; color:var(--primary); }
        .r-transcript { background:#5d4037; color:white; }
        .r-final { background:#558b2f; color:white; }
        .r-settings { background:#e65100; color:white; }
        .r-database { background:#37474f; color:white; }
        .r-statement { background:#455a64; color:white; }

        .card { background:var(--card); padding:25px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); margin-bottom:20px; }
        .card h2 { color:var(--primary); margin-bottom:15px; font-size:1.2rem; }

        .message { padding:12px 20px; border-radius:8px; margin-bottom:15px; font-weight:600; }
        .message.success { background:#e8f5e9; color:var(--secondary); border-left:4px solid var(--secondary); }
        .message.error { background:#ffebee; color:var(--danger); border-left:4px solid var(--danger); }

        .tabs { display:flex; gap:5px; margin-bottom:20px; border-bottom:2px solid #e0ebe0; flex-wrap:wrap; }
        .tab-btn {
            padding:12px 25px; background:transparent; border:none; font-weight:700;
            font-size:0.85rem; cursor:pointer; color:var(--text-muted);
            border-bottom:3px solid transparent;
        }
        .tab-btn.active { color:var(--secondary); border-bottom-color:var(--secondary); }
        .tab-content { display:none; }
        .tab-content.active { display:block; }

        .form-row { display:grid; grid-template-columns:repeat(4,1fr); gap:15px; margin-bottom:15px; }
        .form-row.col1 { grid-template-columns:1fr; }
        .form-group label { display:block; font-weight:600; color:var(--text); margin-bottom:5px; font-size:0.85rem; }
        .form-group input, .form-group select {
            width:100%; padding:10px; border:2px solid var(--border);
            border-radius:8px; font-size:0.9rem;
        }
        .form-group input:focus, .form-group select:focus { border-color:var(--secondary); outline:none; }

        .btn {
            padding:10px 25px; border:none; border-radius:8px; font-weight:700;
            font-size:0.9rem; cursor:pointer; text-decoration:none; display:inline-block;
        }
        .btn-green { background:var(--secondary); color:white; }
        .btn-green:hover { background:#1b5e20; }
        .btn-blue { background:#1976d2; color:white; }
        .btn-blue:hover { background:#0d47a1; }
        .btn-orange { background:#f57c00; color:white; }
        .btn-orange:hover { background:#e65100; }
        .btn-red { background:var(--danger); color:white; }

        .score-input {
            width:75px; padding:8px; border:2px solid var(--border);
            border-radius:6px; text-align:center; font-weight:700; font-size:0.9rem;
        }
        .score-input:focus { border-color:var(--secondary); outline:none; }

        .result-table { width:100%; border-collapse:collapse; margin-bottom:15px; }
        .result-table th {
            background:var(--primary); color:white; padding:10px;
            text-align:left; font-size:0.75rem;
        }
        .result-table td { padding:8px; border-bottom:1px solid #eee; font-size:0.85rem; }
        .result-table tr:hover td { background:#f8faf8; }
        .result-table .center { text-align:center; }

        .grade-badge { padding:3px 10px; border-radius:12px; font-weight:700; font-size:0.8rem; }
        .grade-A { background:#e8f5e9; color:var(--secondary); }
        .grade-B { background:#e3f2fd; color:#0d47a1; }
        .grade-C { background:#fff3e0; color:#e65100; }
        .grade-D { background:#f3e5f5; color:#6a1b9a; }
        .grade-E { background:#fce4ec; color:#c2185b; }
        .grade-F { background:#ffebee; color:var(--danger); }

        .student-info-box {
            background:linear-gradient(135deg, #e8f5e9, #c8e6c9);
            padding:20px; border-radius:10px; margin-bottom:20px;
            border-left:5px solid var(--secondary);
        }
        .student-info-box h3 { color:var(--primary); margin-bottom:10px; }
        .student-info-box p { color:var(--text); margin:5px 0; font-size:0.9rem; }

        .import-area { background:#f8faf8; padding:30px; border-radius:12px; border:2px dashed var(--secondary); text-align:center; }
        .import-area input[type="file"] { padding:12px; border:2px solid var(--border); border-radius:8px; background:white; width:100%; max-width:400px; margin:15px auto; display:block; }

        .guide-box { background:#e3f2fd; padding:20px; border-radius:10px; margin-bottom:20px; border-left:4px solid #1976d2; }
        .guide-box h4 { color:#0d47a1; margin-bottom:10px; }
        .guide-box ol { padding-left:20px; }
        .guide-box li { margin:5px 0; font-size:0.9rem; }

        .combination-table-wrapper {
            max-height: 600px; overflow-y: auto;
            border: 2px solid var(--border); border-radius: 8px;
        }
        .combination-table-wrapper table { margin-bottom: 0; }
        .combination-table-wrapper thead th {
            position: sticky; top: 0; z-index: 10;
            background: var(--primary); color: white;
        }
        .combination-table-wrapper tbody tr:nth-child(even) { background: #fafafa; }

        @media (max-width:900px) {
            .form-row { grid-template-columns:1fr; }
            .topbar { flex-direction:column; gap:10px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="topbar">
            <div>
                <span class="logo-title">DALA <span>COLLEGE</span></span>
                <span class="logo-sub">Result System</span>
            </div>
            <nav>
                <?php if ($is_admin): ?>
                    <a href="admin_dashboard.php">Dashboard</a>
                <?php else: ?>
                    <a href="staff_dashboard.php">Dashboard</a>
                <?php endif; ?>
                <a href="logout.php" class="logout">Logout</a>
            </nav>
            <div>
                <?php if ($is_admin): ?>
                    <span class="admin-badge">👤 <?php echo htmlspecialchars($admin_name); ?> (ADMIN)</span>
                <?php elseif ($is_provost): ?>
                    <span class="provost-badge">👑 <?php echo htmlspecialchars($admin_name); ?> (PROVOST)</span>
                <?php elseif ($is_exam_officer): ?>
                    <span class="exam-badge">📚 <?php echo htmlspecialchars($admin_name); ?> (EXAM OFFICER)</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="result-nav">
            <span class="label">📊 RESULT SYSTEM:</span>
            
            <?php if ($is_admin): ?>
                <a href="admin_course_structure.php" class="r-course">COURSE_STRUCTURE</a>
                <a href="admin_grade_setup.php" class="r-grade">GRADE_SETUP</a>
                <a href="admin_result_entry.php" class="r-entry">RESULT_ENTRY</a>
                <a href="admin_result_slip.php" class="r-slip">RESULT_SLIP</a>
                <a href="admin_result_slip_pro.php" class="r-slip-pro">RESULT_SLIP_PRO</a>
                <a href="admin_transcript.php" class="r-transcript">TRANSCRIPT</a>
                <a href="admin_final_result.php" class="r-final">FINAL_RESULT</a>
                <a href="admin_settings.php" class="r-settings">SETTINGS</a>
                <a href="admin_result_database.php" class="r-database">RESULT_DATABASE</a>
                <a href="admin_statement_of_result.php" class="r-statement">STATEMENT_OF_RESULT</a>
            <?php elseif ($is_provost): ?>
                <a href="admin_course_structure.php" class="r-course">COURSE_STRUCTURE</a>
                <a href="admin_grade_setup.php" class="r-grade">GRADE_SETUP</a>
                <a href="admin_result_entry.php" class="r-entry">RESULT_ENTRY</a>
                <a href="admin_result_slip.php" class="r-slip">RESULT_SLIP</a>
                <a href="admin_result_slip_pro.php" class="r-slip-pro">RESULT_SLIP_PRO</a>
                <a href="admin_transcript.php" class="r-transcript">TRANSCRIPT</a>
                <a href="admin_final_result.php" class="r-final">FINAL_RESULT</a>
                <a href="admin_result_database.php" class="r-database">RESULT_DATABASE</a>
                <a href="admin_statement_of_result.php" class="r-statement">STATEMENT_OF_RESULT</a>
            <?php elseif ($is_exam_officer): ?>
                <a href="admin_course_structure.php" class="r-course">COURSE_STRUCTURE</a>
                <a href="admin_grade_setup.php" class="r-grade">GRADE_SETUP</a>
                <a href="admin_result_entry.php" class="r-entry">RESULT_ENTRY</a>
                <a href="admin_result_slip.php" class="r-slip">RESULT_SLIP</a>
                <a href="admin_result_slip_pro.php" class="r-slip-pro">RESULT_SLIP_PRO</a>
                <a href="admin_transcript.php" class="r-transcript">TRANSCRIPT</a>
                <a href="admin_result_database.php" class="r-database">RESULT_DATABASE</a>
            <?php endif; ?>
        </div>

        <h2 style="color:var(--primary); margin-bottom:15px;">📝 Result Entry</h2>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab-btn active" onclick="showTab(event, 'single')">📝 Single Student</button>
            <button class="tab-btn" onclick="showTab(event, 'single-course')">📚 Single Course</button>
            <button class="tab-btn" onclick="showTab(event, 'complete')">🎯 Complete Combination</button>
            <button class="tab-btn" onclick="showTab(event, 'bulk')">📤 Bulk CSV</button>
        </div>

        <!-- TAB 1: SINGLE STUDENT -->
        <div class="tab-content active" id="tab-single">
            <div class="card">
                <h2>📝 Single Student — Enter Scores for One Student</h2>

                <form method="GET" id="filterForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Combination</label>
                            <select name="combination" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($combinations_list as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c); ?>" 
                                        <?php echo (isset($_GET['combination']) && $_GET['combination']==$c)?'selected':''; ?>>
                                        <?php echo htmlspecialchars($c); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Level</label>
                            <select name="level" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($level_options as $lvl): ?>
                                    <option value="<?php echo htmlspecialchars($lvl); ?>" 
                                        <?php echo (isset($_GET['level']) && $_GET['level']==$lvl)?'selected':''; ?>>
                                        <?php echo htmlspecialchars($lvl); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Semester</label>
                            <select name="semester" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($semester_options as $sem): ?>
                                    <option value="<?php echo htmlspecialchars($sem); ?>" 
                                        <?php echo (isset($_GET['semester']) && $_GET['semester']==$sem)?'selected':''; ?>>
                                        <?php echo htmlspecialchars($sem); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Session</label>
                            <select name="session" required>
                                <?php foreach ($session_options as $sess): ?>
                                    <option value="<?php echo htmlspecialchars($sess); ?>" 
                                        <?php echo ((isset($_GET['session']) ? $_GET['session'] : $current_session) == $sess)?'selected':''; ?>>
                                        <?php echo htmlspecialchars($sess); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-blue">🔍 Load Students</button>
                </form>

                <?php if (isset($_GET['combination']) && isset($_GET['level']) && isset($_GET['semester'])): 
                    $comb = $_GET['combination'];
                    $lvl = $_GET['level'];
                    $sem = $_GET['semester'];
                    $sess = $_GET['session'] ?? $current_session;

                    $students = getRegisteredStudents($pdo, $comb, $lvl, $sem, $sess);
                ?>
                <hr style="margin:20px 0; border:1px solid var(--border);">

                <h3 style="margin-bottom:15px;">Select Student:</h3>
                <div class="form-row col1">
                    <div class="form-group">
                        <label>Student (<?php echo count($students); ?> eligible)</label>
                        <select id="studentSelect" onchange="loadStudentCourses()">
                            <option value="">-- Select Student --</option>
                            <?php foreach ($students as $s): ?>
                                <option value="<?php echo $s['id']; ?>" 
                                        data-reg="<?php echo htmlspecialchars($s['reg_no'] ?? $s['student_id']); ?>"
                                        data-name="<?php echo htmlspecialchars($s['fullname']); ?>"
                                        data-combination="<?php echo htmlspecialchars($comb); ?>"
                                        data-level="<?php echo htmlspecialchars($lvl); ?>"
                                        data-semester="<?php echo htmlspecialchars($sem); ?>"
                                        data-session="<?php echo htmlspecialchars($sess); ?>">
                                    <?php echo htmlspecialchars($s['reg_no'] ?? $s['student_id']); ?> — 
                                    <?php echo htmlspecialchars($s['fullname']); ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if (count($students) == 0): ?>
                                <option value="" disabled>⚠️ No students registered</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div id="coursesContainer"></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB 2: SINGLE COURSE -->
        <div class="tab-content" id="tab-single-course">
            <div class="card">
                <h2>📚 Single Course — Enter Scores for All Students</h2>

                <form method="GET" id="singleCourseForm">
                    <input type="hidden" name="sc_load" value="1">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Combination</label>
                            <select name="combination" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($combinations_list as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c); ?>" 
                                        <?php echo (isset($_GET['combination']) && $_GET['combination']==$c && isset($_GET['sc_load']))?'selected':''; ?>>
                                        <?php echo htmlspecialchars($c); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Level</label>
                            <select name="level" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($level_options as $lvl): ?>
                                    <option value="<?php echo htmlspecialchars($lvl); ?>" 
                                        <?php echo (isset($_GET['level']) && $_GET['level']==$lvl && isset($_GET['sc_load']))?'selected':''; ?>>
                                        <?php echo htmlspecialchars($lvl); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Semester</label>
                            <select name="semester" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($semester_options as $sem): ?>
                                    <option value="<?php echo htmlspecialchars($sem); ?>" 
                                        <?php echo (isset($_GET['semester']) && $_GET['semester']==$sem && isset($_GET['sc_load']))?'selected':''; ?>>
                                        <?php echo htmlspecialchars($sem); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Session</label>
                            <select name="session" required>
                                <?php foreach ($session_options as $sess): ?>
                                    <option value="<?php echo htmlspecialchars($sess); ?>" 
                                        <?php echo ((isset($_GET['session']) ? $_GET['session'] : $current_session) == $sess && isset($_GET['sc_load']))?'selected':''; ?>>
                                        <?php echo htmlspecialchars($sess); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Course Code *</label>
                            <input type="text" name="course_code" placeholder="e.g. EDU111" required 
                                   value="<?php echo htmlspecialchars($_GET['course_code'] ?? ''); ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-orange">🔍 Load Course</button>
                </form>

                <?php 
                if (isset($_GET['sc_load']) && isset($_GET['course_code']) && !empty($_GET['course_code'])): 
                    $sc_comb = $_GET['combination'] ?? '';
                    $sc_lvl = $_GET['level'] ?? '';
                    $sc_sem = $_GET['semester'] ?? '';
                    $sc_sess = $_GET['session'] ?? $current_session;
                    $sc_course = strtoupper($_GET['course_code']);

                    $sc_students = getRegisteredStudents($pdo, $sc_comb, $sc_lvl, $sc_sem, $sc_sess);

                    $stmt = $pdo->prepare("SELECT * FROM courses WHERE course_code = ? AND level = ? AND semester = ? LIMIT 1");
                    $stmt->execute([$sc_course, $sc_lvl, $sc_sem]);
                    $sc_course_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    $sc_title = $sc_course_data['course_title'] ?? $sc_course;
                    $sc_credits = $sc_course_data['credits'] ?? $sc_course_data['credit_units'] ?? 0;

                    $sc_existing = [];
                    $stmt = $pdo->prepare("SELECT student_id, score, grade FROM results 
                                           WHERE course_code = ? AND level = ? AND semester = ? AND session = ?");
                    $stmt->execute([$sc_course, $sc_lvl, $sc_sem, $sc_sess]);
                    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $sc_existing[$r['student_id']] = $r;
                    }
                ?>
                <hr style="margin:20px 0; border:1px solid var(--border);">

                <?php if (count($sc_students) == 0): ?>
                    <div style="text-align:center; padding:30px; background:#fff3e0; border-radius:10px;">
                        <p style="color:#e65100; font-weight:700;">⚠️ No students registered for this selection.</p>
                    </div>
                <?php else: ?>
                    <div class="student-info-box">
                        <h3>📚 <?php echo htmlspecialchars($sc_course); ?> — <?php echo htmlspecialchars($sc_title); ?></h3>
                        <p><strong>Units:</strong> <?php echo $sc_credits; ?> | 
                           <strong>Level:</strong> <?php echo htmlspecialchars($sc_lvl); ?> | 
                           <strong>Semester:</strong> <?php echo htmlspecialchars($sc_sem); ?> | 
                           <strong>Session:</strong> <?php echo htmlspecialchars($sc_sess); ?></p>
                        <p><strong>Students:</strong> <?php echo count($sc_students); ?></p>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="combination" value="<?php echo htmlspecialchars($sc_comb); ?>">
                        <input type="hidden" name="level" value="<?php echo htmlspecialchars($sc_lvl); ?>">
                        <input type="hidden" name="semester" value="<?php echo htmlspecialchars($sc_sem); ?>">
                        <input type="hidden" name="session" value="<?php echo htmlspecialchars($sc_sess); ?>">
                        <input type="hidden" name="course_code" value="<?php echo htmlspecialchars($sc_course); ?>">

                        <table class="result-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Reg No</th>
                                    <th>Student Name</th>
                                    <th>Score (0-100)</th>
                                    <th>Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 0; foreach ($sc_students as $s): $i++;
                                    $existing = $sc_existing[$s['id']] ?? null;
                                ?>
                                <tr>
                                    <td><?php echo $i; ?></td>
                                    <td><strong><?php echo htmlspecialchars($s['reg_no'] ?? $s['student_id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($s['fullname']); ?></td>
                                    <td>
                                        <input type="number" 
                                               name="student_scores[<?php echo $s['id']; ?>]" 
                                               class="score-input" min="0" max="100"
                                               value="<?php echo $existing['score'] ?? ''; ?>"
                                               placeholder="0-100">
                                    </td>
                                    <td>
                                        <?php if ($existing && $existing['grade']): ?>
                                            <span class="grade-badge grade-<?php echo $existing['grade']; ?>">
                                                <?php echo $existing['grade']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:#ccc;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <button type="submit" name="save_single_course" class="btn btn-green" style="margin-top:15px;">
                            <i class="fas fa-save"></i> Save All Scores
                        </button>
                    </form>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB 3: COMPLETE COMBINATION -->
        <div class="tab-content" id="tab-complete">
            <div class="card">
                <h2>🎯 Complete Combination — All Students × All Courses</h2>

                <div class="guide-box">
                    <h4>📖 How to Use:</h4>
                    <ol>
                        <li>Select <strong>Combination, Level, Semester, and Session</strong></li>
                        <li>Click <strong>"Load Complete Grid"</strong></li>
                        <li>A <strong>large grid</strong> will appear — students in rows, courses in columns</li>
                        <li>Enter scores in each cell</li>
                        <li>Click <strong>"Save Complete Combination"</strong> — all scores will be saved at once</li>
                    </ol>
                </div>

                <form method="GET" id="completeForm">
                    <input type="hidden" name="cc_load" value="1">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Combination</label>
                            <select name="combination" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($combinations_list as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c); ?>" 
                                        <?php echo (isset($_GET['combination']) && $_GET['combination']==$c && isset($_GET['cc_load']))?'selected':''; ?>>
                                        <?php echo htmlspecialchars($c); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Level</label>
                            <select name="level" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($level_options as $lvl): ?>
                                    <option value="<?php echo htmlspecialchars($lvl); ?>" 
                                        <?php echo (isset($_GET['level']) && $_GET['level']==$lvl && isset($_GET['cc_load']))?'selected':''; ?>>
                                        <?php echo htmlspecialchars($lvl); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Semester</label>
                            <select name="semester" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($semester_options as $sem): ?>
                                    <option value="<?php echo htmlspecialchars($sem); ?>" 
                                        <?php echo (isset($_GET['semester']) && $_GET['semester']==$sem && isset($_GET['cc_load']))?'selected':''; ?>>
                                        <?php echo htmlspecialchars($sem); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Session</label>
                            <select name="session" required>
                                <?php foreach ($session_options as $sess): ?>
                                    <option value="<?php echo htmlspecialchars($sess); ?>" 
                                        <?php echo ((isset($_GET['session']) ? $_GET['session'] : $current_session) == $sess && isset($_GET['cc_load']))?'selected':''; ?>>
                                        <?php echo htmlspecialchars($sess); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-red">🎯 Load Complete Grid</button>
                </form>

                <?php 
                if (isset($_GET['cc_load']) && isset($_GET['combination']) && isset($_GET['level']) && isset($_GET['semester'])): 
                    $cc_comb = $_GET['combination'];
                    $cc_lvl = $_GET['level'];
                    $cc_sem = $_GET['semester'];
                    $cc_sess = $_GET['session'] ?? $current_session;

                    $cc_students = getRegisteredStudents($pdo, $cc_comb, $cc_lvl, $cc_sem, $cc_sess);
                    $cc_courses = getAllRegisteredCourses($pdo, $cc_comb, $cc_lvl, $cc_sem, $cc_sess);

                    $cc_existing = [];
                    if (count($cc_students) > 0) {
                        $student_ids = array_column($cc_students, 'id');
                        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
                        $sql = "SELECT student_id, course_code, score, grade 
                                FROM results 
                                WHERE student_id IN ($placeholders) 
                                AND level = ? 
                                AND semester = ? 
                                AND session = ?";
                        $params = array_merge($student_ids, [$cc_lvl, $cc_sem, $cc_sess]);
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $cc_existing[$r['student_id']][$r['course_code']] = $r;
                        }
                    }
                ?>
                <hr style="margin:20px 0; border:1px solid var(--border);">

                <?php if (count($cc_students) == 0): ?>
                    <div style="text-align:center; padding:30px; background:#fff3e0; border-radius:10px;">
                        <p style="color:#e65100; font-weight:700;">⚠️ No students registered for this selection.</p>
                    </div>
                <?php elseif (count($cc_courses) == 0): ?>
                    <div style="text-align:center; padding:30px; background:#fff3e0; border-radius:10px;">
                        <p style="color:#e65100; font-weight:700;">⚠️ No courses registered.</p>
                    </div>
                <?php else: ?>
                    <div class="student-info-box">
                        <h3>🎯 Complete Combination Grid</h3>
                        <p><strong>Combination:</strong> <?php echo htmlspecialchars($cc_comb); ?> | 
                           <strong>Level:</strong> <?php echo htmlspecialchars($cc_lvl); ?> | 
                           <strong>Semester:</strong> <?php echo htmlspecialchars($cc_sem); ?> | 
                           <strong>Session:</strong> <?php echo htmlspecialchars($cc_sess); ?></p>
                        <p><strong>Students:</strong> <?php echo count($cc_students); ?> | 
                           <strong>Courses:</strong> <?php echo count($cc_courses); ?></p>
                    </div>

                    <form method="POST" id="completeSaveForm">
                        <input type="hidden" name="combination" value="<?php echo htmlspecialchars($cc_comb); ?>">
                        <input type="hidden" name="level" value="<?php echo htmlspecialchars($cc_lvl); ?>">
                        <input type="hidden" name="semester" value="<?php echo htmlspecialchars($cc_sem); ?>">
                        <input type="hidden" name="session" value="<?php echo htmlspecialchars($cc_sess); ?>">

                        <div class="combination-table-wrapper">
                            <table class="result-table">
                                <thead>
                                    <tr>
                                        <th style="min-width:60px;">#</th>
                                        <th style="min-width:120px;">Reg No</th>
                                        <th style="min-width:180px;">Student Name</th>
                                        <?php foreach ($cc_courses as $c): ?>
                                            <th style="min-width:90px; text-align:center;">
                                                <?php echo htmlspecialchars($c['course_code']); ?><br>
                                                <small style="font-weight:400; opacity:0.8;">(<?php echo $c['credits']; ?>)</small>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 0; foreach ($cc_students as $s): $i++; ?>
                                    <tr>
                                        <td><?php echo $i; ?></td>
                                        <td><strong><?php echo htmlspecialchars($s['reg_no'] ?? $s['student_id']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($s['fullname']); ?></td>
                                        <?php foreach ($cc_courses as $c): 
                                            $existing_score = $cc_existing[$s['id']][$c['course_code']]['score'] ?? '';
                                        ?>
                                            <td style="text-align:center;">
                                                <input type="number" 
                                                       name="combination_scores[<?php echo $s['id']; ?>][<?php echo htmlspecialchars($c['course_code']); ?>]" 
                                                       class="score-input" min="0" max="100"
                                                       value="<?php echo htmlspecialchars($existing_score); ?>"
                                                       placeholder="—"
                                                       style="width:60px; padding:5px; font-size:0.8rem;">
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div style="margin-top:20px; display:flex; gap:10px; flex-wrap:wrap;">
                            <button type="submit" name="save_complete" class="btn btn-green" style="font-size:1rem; padding:14px 40px;">
                                <i class="fas fa-save"></i> Save Complete Combination
                            </button>
                            <button type="button" class="btn btn-orange" onclick="fillEmptyWithZero()">
                                <i class="fas fa-fill"></i> Fill Empty with 0
                            </button>
                            <button type="button" class="btn btn-red" onclick="clearAllScores()">
                                <i class="fas fa-eraser"></i> Clear All Scores
                            </button>
                        </div>
                    </form>

                    <script>
                    function fillEmptyWithZero() {
                        if (!confirm('Are you sure? All empty cells will be filled with 0.')) return;
                        document.querySelectorAll('#completeSaveForm .score-input').forEach(function(input) {
                            if (input.value === '') input.value = 0;
                        });
                    }
                    function clearAllScores() {
                        if (!confirm('Are you sure? All scores will be cleared.')) return;
                        document.querySelectorAll('#completeSaveForm .score-input').forEach(function(input) {
                            input.value = '';
                        });
                    }
                    </script>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB 4: BULK CSV -->
        <div class="tab-content" id="tab-bulk">
            <div class="card">
                <div class="guide-box">
                    <h4>📖 How to Use CSV:</h4>
                    <ol>
                        <li><strong>Step 1:</strong> Download Sheet (All Courses or Single Course)</li>
                        <li><strong>Step 2:</strong> Open CSV in Excel/Google Sheets</li>
                        <li><strong>Step 3:</strong> Enter scores (0-100)</li>
                        <li><strong>Step 4:</strong> Save as CSV</li>
                        <li><strong>Step 5:</strong> Upload Sheet</li>
                        <li><strong>Step 6:</strong> System will calculate Grade, Point, GPA automatically ✅</li>
                    </ol>
                </div>
            </div>

            <div class="card">
                <h2>📥 Step 1a: Download Sheet (All Courses)</h2>
                <form method="GET">
                    <input type="hidden" name="download_sheet" value="1">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Combination *</label>
                            <select name="combination" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($combinations_list as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Level *</label>
                            <select name="level" required>
                                <?php foreach ($level_options as $lvl): ?>
                                    <option value="<?php echo htmlspecialchars($lvl); ?>"><?php echo htmlspecialchars($lvl); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Semester *</label>
                            <select name="semester" required>
                                <?php foreach ($semester_options as $sem): ?>
                                    <option value="<?php echo htmlspecialchars($sem); ?>"><?php echo htmlspecialchars($sem); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Session *</label>
                            <select name="session" required>
                                <?php foreach ($session_options as $sess): ?>
                                    <option value="<?php echo htmlspecialchars($sess); ?>" <?php echo ($current_session == $sess)?'selected':''; ?>><?php echo htmlspecialchars($sess); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-blue">
                        <i class="fas fa-download"></i> Download All Courses Sheet
                    </button>
                </form>
            </div>

            <div class="card">
                <h2>📥 Step 1b: Download Sheet (Single Course)</h2>
                <form method="GET">
                    <input type="hidden" name="download_single" value="1">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Combination *</label>
                            <select name="combination" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($combinations_list as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Level *</label>
                            <select name="level" required>
                                <?php foreach ($level_options as $lvl): ?>
                                    <option value="<?php echo htmlspecialchars($lvl); ?>"><?php echo htmlspecialchars($lvl); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Semester *</label>
                            <select name="semester" required>
                                <?php foreach ($semester_options as $sem): ?>
                                    <option value="<?php echo htmlspecialchars($sem); ?>"><?php echo htmlspecialchars($sem); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Course Code *</label>
                            <input type="text" name="course_code" placeholder="e.g. EDU111" required>
                        </div>
                        <div class="form-group">
                            <label>Session *</label>
                            <select name="session" required>
                                <?php foreach ($session_options as $sess): ?>
                                    <option value="<?php echo htmlspecialchars($sess); ?>" <?php echo ($current_session == $sess)?'selected':''; ?>><?php echo htmlspecialchars($sess); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-orange">
                        <i class="fas fa-download"></i> Download Single Course Sheet
                    </button>
                </form>
            </div>

            <div class="card">
                <h2>📤 Step 2: Upload Filled Sheet</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Combination *</label>
                            <select name="combination" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($combinations_list as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Level *</label>
                            <select name="level" required>
                                <?php foreach ($level_options as $lvl): ?>
                                    <option value="<?php echo htmlspecialchars($lvl); ?>"><?php echo htmlspecialchars($lvl); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Semester *</label>
                            <select name="semester" required>
                                <?php foreach ($semester_options as $sem): ?>
                                    <option value="<?php echo htmlspecialchars($sem); ?>"><?php echo htmlspecialchars($sem); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Session *</label>
                            <select name="session" required>
                                <?php foreach ($session_options as $sess): ?>
                                    <option value="<?php echo htmlspecialchars($sess); ?>" <?php echo ($current_session == $sess)?'selected':''; ?>><?php echo htmlspecialchars($sess); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="import-area">
                        <div style="font-size:3rem; margin-bottom:15px;">📄</div>
                        <p style="font-weight:700; margin-bottom:10px;">Upload your filled CSV file</p>
                        <input type="file" name="sheet_file" accept=".csv" required>
                        <button type="submit" name="upload_sheet" class="btn btn-green" style="margin-top:15px;">
                            <i class="fas fa-upload"></i> Upload & Process
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <script>
    function showTab(event, tab) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
        event.target.classList.add('active');
    }

    function loadStudentCourses() {
        var select = document.getElementById('studentSelect');
        var opt = select.options[select.selectedIndex];

        if (!opt.value) {
            document.getElementById('coursesContainer').innerHTML = '';
            return;
        }

        var studentId = opt.value;
        var combination = opt.getAttribute('data-combination');
        var level = opt.getAttribute('data-level');
        var semester = opt.getAttribute('data-semester');
        var session = opt.getAttribute('data-session');

        document.getElementById('coursesContainer').innerHTML = 
            '<p style="text-align:center; padding:20px; color:var(--text-muted);">⏳ Loading courses...</p>';

        fetch('get_student_courses.php?student_id=' + studentId + 
              '&combination=' + encodeURIComponent(combination) + 
              '&level=' + encodeURIComponent(level) + 
              '&semester=' + encodeURIComponent(semester) +
              '&session=' + encodeURIComponent(session))
            .then(r => r.text())
            .then(html => {
                document.getElementById('coursesContainer').innerHTML = html;
            })
            .catch(err => {
                document.getElementById('coursesContainer').innerHTML = 
                    '<p style="color:red; padding:20px;">Error: ' + err.message + '</p>';
            });
    }
    </script>
</body>
</html>