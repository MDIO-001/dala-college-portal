<?php
ob_start();
session_start();
include 'connect.php';
include 'check_role.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

$admin_name = $_SESSION['fullname'] ?? 'Admin';
$success = '';
$error = '';

// ============================================
// GET DEPARTMENT CODE
// ============================================
function getDepartmentCode($course) {
    $dept_codes = [
        'ARB/ISS' => 'ARI', 'ENG/ISS' => 'ENG', 'PED' => 'PED',
        'HAU/ENG' => 'HAU', 'CSC/ISC' => 'CSC', 'ENG/SOS' => 'SOC',
        'CSC/BIO' => 'BIO', 'CSC/PHY' => 'PHY', 'ENG/ECO' => 'ECO',
        'BA_ARABIC' => 'ARI', 'BA_ISLAMIC' => 'ISC',
        'BED_ENGLISH' => 'ENG', 'BED_HAUSA' => 'HAU',
        'BED_SOCIAL' => 'SOC', 'BSC_ECONOMICS' => 'ECO',
        'BSC_CSC' => 'CSC', 'BSC_BIOLOGY' => 'BIO',
        'BSC_PHYSICS' => 'PHY', 'BSC_ISC' => 'ISC',
        'TAILORING' => 'TAI', 'AI_TECH' => 'AIT',
        'SALOON' => 'SAL', 'HENNA' => 'HEN',
        'FISH_FARMING' => 'FIS', 'POULTRY' => 'POU',
        'SOAP_MAKING' => 'SOA', 'CATERING' => 'CAT',
        'BEAD_MAKING' => 'BEA', 'GRAPHIC_DESIGN' => 'GRA'
    ];
    return $dept_codes[$course] ?? 'GEN';
}

function generateUsername($fullname) {
    $name_parts = explode(' ', trim($fullname));
    $first_name = strtolower(preg_replace('/[^a-zA-Z]/', '', $name_parts[0] ?? 'student'));
    return $first_name . '@123';
}

// ============================================
// GET LEVEL BY PROGRAMME
// ============================================
function getLevelByProgramme($programme) {
    $programme = strtoupper(trim($programme));
    switch ($programme) {
        case 'NCE': return 'NCE I';
        case 'DEGREE':
        case 'DEG': return '100 LEVEL';
        case 'ENTREPRENEURSHIP':
        case 'ENT': return 'ENTREPRENEURSHIP';
        case 'PRE-NCE': return 'PRE-NCE';
        default: return 'NCE I';
    }
}

// ============================================
// generateAdmissionNumber()
// TSARI: DLCOE/NCE/26A083/ARI004
// ============================================
function generateAdmissionNumber($programme, $branch_code, $course) {
    global $conn;
    $year = date('y');
    
    $branch_letter = 'A';
    if ($branch_code == 'SHINGE') $branch_letter = 'A';
    elseif ($branch_code == 'SABUWA') $branch_letter = 'B';
    elseif ($branch_code == 'TUDUN') $branch_letter = 'C';
    
    $dept_code = getDepartmentCode($course);
    
    $total_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM students");
    $total_count = 1;
    if ($total_result) {
        $row = mysqli_fetch_assoc($total_result);
        $total_count = $row['total'] + 1;
    }
    $total_number = str_pad($total_count, 3, '0', STR_PAD_LEFT);
    
    $dept_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM students WHERE course = '$course'");
    $dept_count = 1;
    if ($dept_result) {
        $row = mysqli_fetch_assoc($dept_result);
        $dept_count = $row['total'] + 1;
    }
    $dept_number = str_pad($dept_count, 3, '0', STR_PAD_LEFT);
    
    $prefix = 'DLCOE';
    if ($programme == 'NCE') $prefix = 'DLCOE/NCE';
    elseif ($programme == 'DEGREE') $prefix = 'DLCOE/DEG';
    else $prefix = 'DLCOE/ENT';
    
    return $prefix . "/{$year}{$branch_letter}{$total_number}/{$dept_code}{$dept_number}";
}

// Fix NULL levels/status
mysqli_query($conn, "UPDATE students SET level = 'NCE I' WHERE level IS NULL OR level = '' OR level = 'NCE'");
mysqli_query($conn, "UPDATE students SET status = 'pending' WHERE status IS NULL OR status = ''");

// ============================================
// STATS
// ============================================
$total_students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students"))['c'];
$total_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE status IN ('pending','active')"))['c'];
$total_accepted = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE status IN ('approved','graduated')"))['c'];
$total_rejected = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE status IN ('rejected','inactive')"))['c'];
$total_staff = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM staff"))['c'];
$nce1_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE level = 'NCE I'"))['c'];
$nce2_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE level = 'NCE II'"))['c'];
$nce3_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE level = 'NCE III'"))['c'];

// ============================================
// GET STUDENTS LIST
// ============================================
$filter_level = isset($_GET['filter_level']) ? mysqli_real_escape_string($conn, $_GET['filter_level']) : 'all';

$sql = ($filter_level != 'all') 
    ? "SELECT * FROM students WHERE level = '$filter_level' ORDER BY id DESC"
    : "SELECT * FROM students ORDER BY id DESC";
$students_result = mysqli_query($conn, $sql);

$staff_result = mysqli_query($conn, "SELECT * FROM staff ORDER BY id DESC");

// ============================================
// APPLICATIONS
// ============================================
$applications = mysqli_query($conn, "SELECT * FROM applications ORDER BY id DESC");

// ============================================
// HANDLE ACCEPT/REJECT APPLICATION
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && isset($_POST['application_id'])) {
    $app_id = mysqli_real_escape_string($conn, $_POST['application_id']);
    $action = mysqli_real_escape_string($conn, $_POST['action']);
    $comment = mysqli_real_escape_string($conn, trim($_POST['comment']));
    
    $get = "SELECT * FROM applications WHERE id = '$app_id'";
    $res = mysqli_query($conn, $get);
    
    if ($res && $row = mysqli_fetch_assoc($res)) {
        $email = $row['email'];
        $programme = $row['programme'] ?? 'NCE';
        $course = $row['course_applied'];
        $branch_code = $row['branch_code'] ?? 'SHINGE';
        
        $admission_no = '';
        if ($action == 'approved') {
            $admission_no = generateAdmissionNumber($programme, $branch_code, $course);
        }
        
        mysqli_query($conn, "UPDATE applications SET status = '$action', notes = '$comment', reviewed_by = '$admin_name (Admin)', reviewed_at = NOW() WHERE id = '$app_id'");
        
        if ($action == 'approved') {
            $level = getLevelByProgramme($programme);
            mysqli_query($conn, "UPDATE students SET status = 'active', reg_no = '$admission_no', student_id = '$admission_no', combination = '$course', level = '$level' WHERE email = '$email'");
        } else {
            mysqli_query($conn, "UPDATE students SET status = '$action' WHERE email = '$email'");
        }
        
        $success = "✅ Application $action successfully!";
        if ($action == 'approved') $success .= "<br>🎓 Admission Number: <strong>$admission_no</strong>";
    }
}

// ============================================
// SAVE SCORES (Score Entry)
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_scores'])) {
    $score_student_id = intval($_POST['student_id']);
    $score_level = mysqli_real_escape_string($conn, $_POST['level']);
    $score_semester = mysqli_real_escape_string($conn, $_POST['semester']);
    $score_academic_year = mysqli_real_escape_string($conn, $_POST['academic_year']);
    $ca_scores = $_POST['ca_score'] ?? [];
    $exam_scores = $_POST['exam_score'] ?? [];
    
    $saved = 0;
    $failed = 0;
    
    foreach ($ca_scores as $course_code => $ca) {
        $course_code = mysqli_real_escape_string($conn, $course_code);
        $ca = intval($ca);
        $exam = intval($exam_scores[$course_code] ?? 0);
        $total = $ca + $exam;
        
        if ($total >= 70) { $grade = 'A'; $gp = 5.00; }
        elseif ($total >= 60) { $grade = 'B'; $gp = 4.00; }
        elseif ($total >= 50) { $grade = 'C'; $gp = 3.00; }
        elseif ($total >= 45) { $grade = 'D'; $gp = 2.00; }
        elseif ($total >= 40) { $grade = 'E'; $gp = 1.00; }
        else { $grade = 'F'; $gp = 0.00; }
        
        $ct_query = "SELECT course_title FROM courses WHERE course_code = '$course_code' LIMIT 1";
        $ct_result = mysqli_query($conn, $ct_query);
        $ct_row = mysqli_fetch_assoc($ct_result);
        $course_title = $ct_row['course_title'] ?? '';
        
        $sql = "INSERT INTO results 
                (student_id, course_code, course_title, level, semester, academic_year, 
                 ca_score, exam_score, total_score, grade, grade_point, status)
                VALUES 
                ($score_student_id, '$course_code', '$course_title', '$score_level', '$score_semester', '$score_academic_year',
                 $ca, $exam, $total, '$grade', $gp, 'approved')
                ON DUPLICATE KEY UPDATE
                ca_score = $ca, exam_score = $exam, total_score = $total,
                grade = '$grade', grade_point = $gp, status = 'approved'";
        
        if (mysqli_query($conn, $sql)) { $saved++; } else { $failed++; }
    }
    
    if ($saved > 0) $score_success = "✅ $saved course(s) scores saved successfully!";
    if ($failed > 0) $score_error = "❌ $failed course(s) failed to save.";
}

// ============================================
// SAVE T.P SCORES
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_tp_scores'])) {
    $tp_student_id = intval($_POST['tp_student_id']);
    $tp_session = mysqli_real_escape_string($conn, $_POST['tp_session']);
    $tp_level = mysqli_real_escape_string($conn, $_POST['tp_level']);
    $tp_school = mysqli_real_escape_string($conn, trim($_POST['school_name']));
    $tp_supervisor = mysqli_real_escape_string($conn, trim($_POST['supervisor_name']));
    $teaching = intval($_POST['teaching_score']);
    $lesson = intval($_POST['lesson_note_score']);
    $punctual = intval($_POST['punctuality_score']);
    $relationship = intval($_POST['relationship_score']);
    
    $total = $teaching + $lesson + $punctual + $relationship;
    
    if ($total >= 70) { $grade = 'A'; $remark = 'Distinction'; }
    elseif ($total >= 60) { $grade = 'B'; $remark = 'Credit'; }
    elseif ($total >= 50) { $grade = 'C'; $remark = 'Merit'; }
    elseif ($total >= 45) { $grade = 'D'; $remark = 'Pass'; }
    else { $grade = 'F'; $remark = 'Fail'; }
    
    $sql = "INSERT INTO tp_results 
            (student_id, academic_year, level, school_name, supervisor_name,
             teaching_score, lesson_note_score, punctuality_score, relationship_score,
             total_score, grade, remark, status)
            VALUES 
            ($tp_student_id, '$tp_session', '$tp_level', '$tp_school', '$tp_supervisor',
             $teaching, $lesson, $punctual, $relationship,
             $total, '$grade', '$remark', 'approved')
            ON DUPLICATE KEY UPDATE
            school_name = '$tp_school', supervisor_name = '$tp_supervisor',
            teaching_score = $teaching, lesson_note_score = $lesson,
            punctuality_score = $punctual, relationship_score = $relationship,
            total_score = $total, grade = '$grade', remark = '$remark',
            status = 'approved'";
    
    if (mysqli_query($conn, $sql)) {
        $tp_success = "✅ T.P score saved! Grade: <strong>$grade ($remark)</strong> — Total: <strong>$total/100</strong>";
    } else {
        $tp_error = "❌ Error: " . mysqli_error($conn);
    }
}

// ============================================
// DOWNLOAD TEMPLATE CSV
// ============================================
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="student_import_template.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['S/N', 'REG NO', 'FULL NAME', 'EMAIL', 'PHONE', 'PROGRAMME', 'DEPARTMENT/COURSE', 'LEVEL', 'STATUS', 'STUDY CENTRE', 'PASSWORD']);
    fputcsv($output, ['1', 'DLCOE/NCE/24A001/CSC001', 'Amina Ibrahim', 'amina.ibrahim@email.com', '08012345678', 'NCE', 'CSC/ISC', 'NCE I', 'pending', 'SHINGE', 'student123']);
    fclose($output);
    exit();
}

// ============================================
// EXPORT ALL STUDENTS
// ============================================
if (isset($_GET['export_students'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="students_list_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['S/N', 'REG NO', 'FULL NAME', 'EMAIL', 'PHONE', 'PROGRAMME', 'DEPARTMENT/COURSE', 'LEVEL', 'STATUS', 'STUDY CENTRE', 'PASSWORD']);
    $export_query = mysqli_query($conn, "SELECT * FROM students ORDER BY id ASC");
    if ($export_query) {
        $sn = 1;
        while ($row = mysqli_fetch_assoc($export_query)) {
            fputcsv($output, [$sn++, $row['reg_no'] ?? $row['student_id'] ?? '', $row['fullname'] ?? '', $row['email'] ?? '', $row['phone'] ?? '', $row['programme'] ?? 'NCE', $row['course'] ?? $row['department'] ?? '', $row['level'] ?? 'NCE I', $row['status'] ?? 'pending', $row['branch_code'] ?? 'SHINGE', 'student123']);
        }
    }
    fclose($output);
    exit();
}

// ============================================
// ADD STUDENT
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_student'])) {
    $fullname = mysqli_real_escape_string($conn, trim($_POST['fullname']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $reg_no = mysqli_real_escape_string($conn, trim($_POST['reg_no'] ?? ''));
    $programme = mysqli_real_escape_string($conn, $_POST['programme']);
    $course = mysqli_real_escape_string($conn, $_POST['course']);
    $level = mysqli_real_escape_string($conn, $_POST['level']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $branch_code = mysqli_real_escape_string($conn, $_POST['branch_code'] ?? 'SHINGE');
    
    $errors = [];
    if (empty($fullname)) $errors[] = 'Full name is required.';
    if (empty($email)) $errors[] = 'Email is required.';
    if (empty($phone)) $errors[] = 'Phone is required.';
    
    if (!empty($email)) {
        $check = mysqli_query($conn, "SELECT id FROM students WHERE email = '$email'");
        if ($check && mysqli_num_rows($check) > 0) $errors[] = 'Email already exists.';
    }
    if (!empty($phone)) {
        $check = mysqli_query($conn, "SELECT id FROM students WHERE phone = '$phone'");
        if ($check && mysqli_num_rows($check) > 0) $errors[] = 'Phone number already exists.';
    }
    
    if (empty($errors)) {
        if (empty($reg_no)) $reg_no = generateAdmissionNumber($programme, $branch_code, $course);
        
        $username = generateUsername($fullname);
        $counter = 1;
        $check_username = mysqli_query($conn, "SELECT id FROM students WHERE username = '$username'");
        while ($check_username && mysqli_num_rows($check_username) > 0) {
            $name_parts = explode(' ', trim($fullname));
            $first_name = strtolower(preg_replace('/[^a-zA-Z]/', '', $name_parts[0] ?? 'student'));
            $username = $first_name . $counter . '@123';
            $counter++;
            $check_username = mysqli_query($conn, "SELECT id FROM students WHERE username = '$username'");
        }
        
        $password = password_hash('student123', PASSWORD_DEFAULT);
        
        $insert = "INSERT INTO students (reg_no, student_id, username, password, fullname, email, phone, programme, course, level, status, branch_code, created_at) 
                   VALUES ('$reg_no', '$reg_no', '$username', '$password', '$fullname', '$email', '$phone', '$programme', '$course', '$level', '$status', '$branch_code', NOW())";
        
        if (mysqli_query($conn, $insert)) {
            header("Location: admin_dashboard.php?success=1");
            exit();
        } else {
            $add_error = "❌ Error: " . mysqli_error($conn);
        }
    } else {
        $add_error = implode('<br>', $errors);
    }
}

// ============================================
// CHANGE STUDENT PASSWORD
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_student_password'])) {
    $sid = intval($_POST['student_id']);
    $new_password = $_POST['new_password'] ?? '';
    if (strlen($new_password) < 6) {
        $pass_error = "❌ Min 6 characters.";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        if (mysqli_query($conn, "UPDATE students SET password='$hashed' WHERE id=$sid")) {
            $pass_success = "✅ Password changed!";
        } else {
            $pass_error = "❌ Error: " . mysqli_error($conn);
        }
    }
}

// ============================================
// EDIT STAFF
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_staff'])) {
    $sid = intval($_POST['staff_id']);
    $fullname = mysqli_real_escape_string($conn, trim($_POST['fullname']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    if (mysqli_query($conn, "UPDATE staff SET fullname='$fullname', email='$email', phone='$phone', username='$username', role='$role' WHERE id=$sid")) {
        $staff_edit_success = "✅ Staff updated!";
    } else {
        $staff_edit_error = "❌ Error: " . mysqli_error($conn);
    }
}

// ============================================
// CHANGE STAFF PASSWORD
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_staff_password'])) {
    $sid = intval($_POST['staff_id']);
    $new_password = $_POST['new_password'] ?? '';
    if (strlen($new_password) < 6) {
        $staff_pass_error = "❌ Min 6 characters.";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        if (mysqli_query($conn, "UPDATE staff SET password='$hashed' WHERE id=$sid")) {
            $staff_pass_success = "✅ Password changed!";
        } else {
            $staff_pass_error = "❌ Error: " . mysqli_error($conn);
        }
    }
}

// ============================================
// CSV IMPORT
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import_students'])) {
    if (isset($_FILES['student_file']) && $_FILES['student_file']['error'] == 0) {
        $file_tmp = $_FILES['student_file']['tmp_name'];
        $file_ext = strtolower(pathinfo($_FILES['student_file']['name'], PATHINFO_EXTENSION));
        
        if ($file_ext != 'csv') {
            $import_error = "❌ Please upload a CSV file.";
        } else {
            $file = fopen($file_tmp, 'r');
            fgetcsv($file);
            
            $imported = 0; $updated = 0; $skipped = 0; $errors = []; $row_num = 1;
            
            while (($row = fgetcsv($file)) !== FALSE) {
                $row_num++;
                if (empty(array_filter($row))) { $skipped++; continue; }
                
                $reg_no = mysqli_real_escape_string($conn, trim($row[1] ?? ''));
                $fullname = mysqli_real_escape_string($conn, trim($row[2] ?? ''));
                $email = mysqli_real_escape_string($conn, trim($row[3] ?? ''));
                $phone = mysqli_real_escape_string($conn, trim($row[4] ?? ''));
                $programme = mysqli_real_escape_string($conn, trim($row[5] ?? 'NCE'));
                $course = mysqli_real_escape_string($conn, trim($row[6] ?? ''));
                $level = mysqli_real_escape_string($conn, trim($row[7] ?? 'NCE I'));
                $status = mysqli_real_escape_string($conn, trim($row[8] ?? 'pending'));
                $branch_code = mysqli_real_escape_string($conn, trim($row[9] ?? 'SHINGE'));
                $password_plain = trim($row[10] ?? 'student123');
                
                if (empty($fullname)) { $errors[] = "Row $row_num: Missing name"; $skipped++; continue; }
                
                if (empty($email)) {
                    $parts = explode(' ', strtolower(trim($fullname)));
                    $fn = preg_replace('/[^a-z]/', '', $parts[0] ?? 'student');
                    $ln = isset($parts[count($parts)-1]) ? preg_replace('/[^a-z]/', '', $parts[count($parts)-1]) : '';
                    $email = $fn . '.' . $ln . '@student.dalacoe.edu.ng';
                }
                if (empty($reg_no)) {
                    $reg_no = generateAdmissionNumber($programme, $branch_code, $course);
                }
                
                $username = generateUsername($fullname);
                $counter = 1;
                $check_u = mysqli_query($conn, "SELECT id FROM students WHERE username = '$username'");
                while ($check_u && mysqli_num_rows($check_u) > 0) {
                    $parts = explode(' ', trim($fullname));
                    $fn = strtolower(preg_replace('/[^a-zA-Z]/', '', $parts[0] ?? 'student'));
                    $username = $fn . $counter . '@123';
                    $counter++;
                    $check_u = mysqli_query($conn, "SELECT id FROM students WHERE username = '$username'");
                }
                
                $check = mysqli_query($conn, "SELECT id FROM students WHERE reg_no = '$reg_no' OR email = '$email'");
                $hashed = password_hash($password_plain, PASSWORD_DEFAULT);
                
                if ($check && mysqli_num_rows($check) > 0) {
                    mysqli_query($conn, "UPDATE students SET fullname='$fullname', email='$email', phone='$phone', programme='$programme', course='$course', level='$level', status='$status', branch_code='$branch_code', password='$hashed', username='$username' WHERE reg_no='$reg_no' OR email='$email'");
                    $updated++;
                } else {
                    mysqli_query($conn, "INSERT INTO students (reg_no, student_id, username, password, fullname, email, phone, programme, course, level, status, branch_code, created_at) VALUES ('$reg_no', '$reg_no', '$username', '$hashed', '$fullname', '$email', '$phone', '$programme', '$course', '$level', '$status', '$branch_code', NOW())");
                    $imported++;
                }
            }
            fclose($file);
            
            $msg = [];
            if ($imported > 0) $msg[] = "✅ <strong>$imported</strong> new students imported";
            if ($updated > 0) $msg[] = "🔄 <strong>$updated</strong> students updated";
            if ($skipped > 0) $msg[] = "⚠️ <strong>$skipped</strong> rows skipped";
            
            if (!empty($msg)) {
                $import_success = implode("<br>", $msg);
            } else {
                $import_error = "❌ No students imported.";
            }
        }
    } else {
        $import_error = "❌ Please select a file.";
    }
}

// ============================================
// DELETE / STATUS
// ============================================
if (isset($_GET['delete_student']) && is_numeric($_GET['delete_student'])) {
    mysqli_query($conn, "DELETE FROM students WHERE id=" . intval($_GET['delete_student']));
    header('Location: admin_dashboard.php'); exit();
}
if (isset($_GET['delete_staff']) && is_numeric($_GET['delete_staff'])) {
    mysqli_query($conn, "DELETE FROM staff WHERE id=" . intval($_GET['delete_staff']));
    header('Location: admin_dashboard.php'); exit();
}
if (isset($_GET['change_student_status']) && is_numeric($_GET['change_student_status'])) {
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    $id = intval($_GET['change_student_status']);
    if ($status == 'approved') $status = 'active';
    mysqli_query($conn, "UPDATE students SET status='$status' WHERE id='$id'");
    header('Location: admin_dashboard.php'); exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Dala College</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f0f4f8; color: #1a2e1a; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        
        .topbar { background: #0d2818; color: white; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; border-radius: 12px; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .topbar h1 { margin: 0; font-size: 1.5rem; }
        .topbar .logout { background: #c62828; color: white; padding: 8px 15px; border-radius: 6px; text-decoration: none; font-weight: bold; }
        .topbar .logout:hover { background: #b71c1c; }
        
        .welcome-banner {
            background: linear-gradient(135deg, #1b5e20, #2e7d32, #43a047);
            color: white; padding: 30px 35px; border-radius: 16px; margin-bottom: 25px;
            box-shadow: 0 8px 25px rgba(46, 125, 50, 0.3);
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 15px;
        }
        .welcome-banner h2 { font-size: 1.8rem; margin-bottom: 5px; }
        .welcome-banner h2 span { color: #ffd54f; }
        .welcome-banner p { font-size: 0.95rem; opacity: 0.9; }
        .welcome-banner .date { background: rgba(255,255,255,0.15); padding: 8px 20px; border-radius: 30px; font-size: 0.85rem; border: 1px solid rgba(255,255,255,0.2); }
        .welcome-banner .quick-cards { display: flex; gap: 10px; flex-wrap: wrap; }
        .welcome-banner .quick-cards a {
            background: white; color: #0d2818; padding: 12px 20px; border-radius: 10px;
            text-decoration: none; text-align: center; min-width: 140px;
            transition: all 0.3s ease; display: block;
        }
        .welcome-banner .quick-cards a:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.2); }
        .welcome-banner .quick-cards a i { font-size: 1.5rem; display: block; margin-bottom: 5px; }
        .welcome-banner .quick-cards a strong { display: block; font-size: 0.9rem; }
        .welcome-banner .quick-cards a small { color: #6a8f6a; font-size: 0.7rem; }
        .welcome-banner .quick-cards .c-courses i { color: #2e7d32; }
        .welcome-banner .quick-cards .c-results i { color: #7b1fa2; }
        
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; margin-bottom: 25px; }
        .stat-card { background: white; padding: 18px 15px; border-radius: 12px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 4px solid #2e7d32; transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .stat-card .number { font-size: 2rem; font-weight: 800; color: #2e7d32; line-height: 1; }
        .stat-card .label { color: #6a8f6a; font-size: 0.75rem; font-weight: 700; margin-top: 5px; text-transform: uppercase; }
        .stat-card.pending { border-left-color: #ffa000; }
        .stat-card.pending .number { color: #ffa000; }
        .stat-card.rejected { border-left-color: #c62828; }
        .stat-card.rejected .number { color: #c62828; }
        .stat-card.nce3 { border-left-color: #1b5e20; }
        .stat-card.nce3 .number { color: #1b5e20; }
        .stat-card.nce2 { border-left-color: #0d47a1; }
        .stat-card.nce2 .number { color: #0d47a1; }
        .stat-card.nce1 { border-left-color: #e65100; }
        .stat-card.nce1 .number { color: #e65100; }
        
        .alert { padding: 12px 15px; border-radius: 8px; font-weight: 600; margin-bottom: 15px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }
        .alert-error { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }
        
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .card h2 { color: #0d2818; margin-bottom: 20px; font-size: 1.2rem; }
        .card-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; }
        .card-header h2 { margin-bottom: 0; }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 700; margin-bottom: 5px; font-size: 14px; color: #0d2818; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 2px solid #dce8dc; border-radius: 8px; font-size: 14px; }
        .form-group input:focus, .form-group select:focus { border-color: #2e7d32; outline: none; }
        
        .btn { padding: 10px 20px; border-radius: 8px; font-weight: 700; font-size: 14px; cursor: pointer; border: none; display: inline-block; text-decoration: none; transition: all 0.3s ease; }
        .btn-green { background: #2e7d32; color: white; }
        .btn-green:hover { background: #1b5e20; transform: translateY(-2px); }
        .btn-blue { background: #1976d2; color: white; }
        .btn-blue:hover { background: #0d47a1; transform: translateY(-2px); }
        .btn-red { background: #c62828; color: white; }
        .btn-orange { background: #ffa000; color: white; }
        .btn-purple { background: #7b1fa2; color: white; }
        
        .export-section { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; background: #f8faf8; padding: 20px 25px; border-radius: 12px; border: 2px solid #e0ebe0; }
        .export-section h3 { color: #0d2818; margin-bottom: 5px; }
        .export-section p { color: #6a8f6a; font-size: 0.9rem; }
        .export-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
        
        .import-area { background: #f8faf8; padding: 25px; border-radius: 10px; border: 2px dashed #2e7d32; text-align: center; }
        .import-area .icon { font-size: 2.5rem; margin-bottom: 10px; }
        .import-area input[type="file"] { padding: 10px; border: 1px solid #dce8dc; border-radius: 8px; background: white; width: 100%; max-width: 400px; margin: 10px auto; }
        .import-area .btn-import { padding: 10px 30px; background: #2e7d32; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        
        .level-filter { display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; }
        .level-filter .btn { padding: 8px 20px; font-size: 0.85rem; }
        .level-filter .btn.active { box-shadow: 0 0 0 3px #ffd54f; }
        
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        table th { background: #0d2818; color: white; padding: 12px; text-align: left; font-size: 0.8rem; text-transform: uppercase; white-space: nowrap; }
        table td { padding: 10px; border-bottom: 1px solid #eee; font-size: 0.85rem; }
        table tr:hover { background: #f8faf8; }
        
        .badge { display: inline-block; padding: 3px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; }
        .badge-pending { background: #fff3e0; color: #e65100; }
        .badge-approved { background: #e8f5e9; color: #2e7d32; }
        .badge-active { background: #e8f5e9; color: #1b5e20; }
        .badge-rejected { background: #ffebee; color: #c62828; }
        .badge-ncei { background: #fff3e0; color: #e65100; }
        .badge-nceii { background: #e3f2fd; color: #0d47a1; }
        .badge-nceiii { background: #e8f5e9; color: #1b5e20; }
        
        .status-badge { display:inline-block; padding:4px 14px; border-radius:20px; font-size:0.75rem; font-weight:600; }
        .status-badge.pending { background:#fff8e1; color:#ffa000; }
        .status-badge.approved { background:#e8f5e9; color:#2e7d32; }
        .status-badge.rejected { background:#ffebee; color:#c62828; }
        
        .admission-no { font-weight:700; color:#0d2818; font-size:0.75rem; background:#e8f5e9; padding:2px 10px; border-radius:12px; }
        
        .action-btns { display: flex; gap: 4px; flex-wrap: wrap; }
        .action-btns a { padding: 5px 9px; border-radius: 4px; text-decoration: none; color: white; font-size: 0.75rem; transition: all 0.3s ease; }
        .action-btns a:hover { transform: scale(1.1); }
        .accept-btn { background: #2e7d32; }
        .reject-btn { background: #c62828; }
        .edit-btn { background: #1976d2; }
        .pass-btn { background: #ffa000; }
        .delete-btn { background: #c62828; }
        .view-btn { background: #7b1fa2; }
        .idcard-btn { background: #1976d2; }
        .tp-btn { background: #e65100; }
        
        .action-form { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
        .action-form input[type="text"] { padding:6px 10px; border:1px solid #dce8dc; border-radius:6px; font-size:0.78rem; min-width:100px; }
        .action-form .btn-accept { padding:6px 16px; background:#2e7d32; color:white; border:none; border-radius:6px; font-size:0.75rem; font-weight:600; cursor:pointer; }
        .action-form .btn-reject { padding:6px 16px; background:#c62828; color:white; border:none; border-radius:6px; font-size:0.75rem; font-weight:600; cursor:pointer; }
        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto; padding: 30px; }
        .modal-content { max-width: 600px; margin: 30px auto; background: white; padding: 35px; border-radius: 16px; position: relative; }
        .modal-close { position: absolute; top: 15px; right: 20px; font-size: 2rem; background: none; border: none; cursor: pointer; color: #c62828; }
        
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .topbar { flex-direction: column; text-align: center; }
            .welcome-banner { flex-direction: column; text-align: center; }
            .stats { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="topbar">
        <h1>👨‍💼 Admin Dashboard</h1>
        <div>
            <a href="admin_payments.php" style="color: #ffd54f; margin-right: 15px; text-decoration: none; font-weight: 600;">
                <i class="fas fa-money-bill-wave"></i> Payments
            </a>
            <a href="admin_scratch_cards.php" style="color: #ffd54f; margin-right: 15px; text-decoration: none; font-weight: 600;">
                <i class="fas fa-ticket-alt"></i> Scratch Cards
            </a>
            <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="welcome-banner">
        <div>
            <h2>👋 Welcome, <span><?php echo htmlspecialchars($admin_name); ?></span></h2>
            <p>Manage all student and staff records from this central dashboard.</p>
        </div>
        
        <div class="quick-cards">
            <a href="admin_manage_courses.php" class="c-courses">
                <i class="fas fa-book"></i>
                <strong>Manage Courses</strong>
                <small>Add, delete courses</small>
            </a>
            <a href="admin_result_entry.php" class="c-results">
                <i class="fas fa-chart-line"></i>
                <strong>Results</strong>
                <small>Enter scores & GPA</small>
            </a>
        </div>
        
        <div class="date">
            <i class="fas fa-calendar-alt"></i> <?php echo date('l, F j, Y'); ?>
        </div>
    </div>

    <div class="stats">
        <div class="stat-card pending">
            <div class="number"><?php echo $total_pending; ?></div>
            <div class="label">📌 Pending</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $total_accepted; ?></div>
            <div class="label">✅ Accepted</div>
        </div>
        <div class="stat-card rejected">
            <div class="number"><?php echo $total_rejected; ?></div>
            <div class="label">❌ Rejected</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $total_students; ?></div>
            <div class="label">🎓 Total Students</div>
        </div>
        <div class="stat-card nce3">
            <div class="number"><?php echo $nce3_count; ?></div>
            <div class="label">🏆 NCE III</div>
        </div>
        <div class="stat-card nce2">
            <div class="number"><?php echo $nce2_count; ?></div>
            <div class="label">📘 NCE II</div>
        </div>
        <div class="stat-card nce1">
            <div class="number"><?php echo $nce1_count; ?></div>
            <div class="label">📗 NCE I</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $total_staff; ?></div>
            <div class="label">👥 Total Staff</div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
    <?php endif; ?>

    <!-- APPLICATIONS -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-list-ul" style="color:#2e7d32;"></i> All Applications</h2>
            <span style="color:#6a8f6a; font-size:0.9rem;">Accept or Reject student applications</span>
        </div>
        
        <?php if ($applications && mysqli_num_rows($applications) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Programme</th>
                        <th>Course</th>
                        <th>Status</th>
                        <th>Admission No.</th>
                        <th>Comment</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; while ($row = mysqli_fetch_assoc($applications)): 
                        $status_class = $row['status'] == 'approved' ? 'approved' : ($row['status'] == 'pending' ? 'pending' : 'rejected');
                    ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['fullname']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['programme']); ?></td>
                        <td><?php echo htmlspecialchars($row['course_applied']); ?></td>
                        <td><span class="status-badge <?php echo $status_class; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                        <td>
                            <?php if (!empty($row['reg_no']) || !empty($row['student_id'])): ?>
                                <span class="admission-no"><?php echo $row['reg_no'] ?? $row['student_id']; ?></span>
                            <?php else: ?>
                                <span style="color:#aaa;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="max-width:150px; font-size:0.8rem;"><?php echo htmlspecialchars($row['notes'] ?? '-'); ?></td>
                        <td>
                            <?php if ($row['status'] == 'pending'): ?>
                            <form method="POST" class="action-form">
                                <input type="hidden" name="application_id" value="<?php echo $row['id']; ?>">
                                <input type="text" name="comment" placeholder="Comment..." required>
                                <button type="submit" name="action" value="approved" class="btn-accept"><i class="fas fa-check"></i> Accept</button>
                                <button type="submit" name="action" value="rejected" class="btn-reject"><i class="fas fa-times"></i> Reject</button>
                            </form>
                            <?php else: ?>
                            <span style="color:#6a8f6a; font-size:0.8rem;"><i class="fas fa-check-circle" style="color:#2e7d32;"></i> Done</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="text-align:center; padding:40px; color:#6a8f6a;">
            <i class="fas fa-inbox" style="font-size:3rem; display:block; margin-bottom:10px; color:#dce8dc;"></i>
            <p>No applications found.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- EXPORT -->
    <div class="card">
        <div class="export-section">
            <div>
                <h3><i class="fas fa-file-export" style="color:#2e7d32;"></i> Export Students Data</h3>
                <p>Download all student records in CSV format.</p>
            </div>
            <div class="export-buttons">
                <a href="?download_template=1" class="btn btn-blue"><i class="fas fa-download"></i> Template</a>
                <a href="?export_students=1" class="btn btn-green"><i class="fas fa-download"></i> All Students</a>
            </div>
        </div>
    </div>

    <!-- IMPORT -->
    <div class="card">
        <h2>📂 Import Students (CSV)</h2>
        <?php if (isset($import_success)): ?><div class="alert alert-success"><?php echo $import_success; ?></div><?php endif; ?>
        <?php if (isset($import_error)): ?><div class="alert alert-error"><?php echo $import_error; ?></div><?php endif; ?>
        <div class="import-area">
            <div class="icon">📄</div>
            <p style="font-weight:600; margin-bottom:10px;">Upload CSV file with students</p>
            <p style="color:#6a8f6a; font-size:0.8rem; margin-bottom:10px;">Format: S/N, REG NO, FULL NAME, EMAIL, PHONE, PROGRAMME, DEPARTMENT/COURSE, LEVEL, STATUS, STUDY CENTRE, PASSWORD</p>
            <form method="POST" enctype="multipart/form-data">
                <input type="file" name="student_file" accept=".csv" required>
                <br><br>
                <button type="submit" name="import_students" class="btn-import"><i class="fas fa-upload"></i> Import Students</button>
            </form>
        </div>
    </div>

    <!-- ADD STUDENT -->
    <div class="card">
        <h2>➕ Add New Student</h2>
        <?php if (isset($_GET['success'])): ?><div class="alert alert-success">✅ Student added!</div><?php endif; ?>
        <?php if (isset($add_error)): ?><div class="alert alert-error"><?php echo $add_error; ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-row">
                <div class="form-group"><label>Full Name *</label><input type="text" name="fullname" required></div>
                <div class="form-group"><label>Email *</label><input type="email" name="email" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Phone *</label><input type="tel" name="phone" required></div>
                <div class="form-group"><label>Reg No (Optional)</label><input type="text" name="reg_no" placeholder="Auto-generate"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Programme *</label>
                    <select name="programme" id="programme" required onchange="updateCourses()">
                        <option value="">-- Select --</option>
                        <option value="NCE">NCE</option>
                        <option value="DEGREE">Degree</option>
                        <option value="ENTREPRENEURSHIP">Entrepreneurship</option>
                    </select>
                </div>
                <div class="form-group"><label>Course *</label>
                    <select name="course" id="course" required>
                        <option value="">-- Select Programme First --</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Level</label>
                    <select name="level">
                        <option value="NCE I">NCE I</option>
                        <option value="NCE II">NCE II</option>
                        <option value="NCE III">NCE III</option>
                    </select>
                </div>
                <div class="form-group"><label>Status</label>
                    <select name="status">
                        <option value="pending">Pending</option>
                        <option value="active">Active</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Study Centre *</label>
                    <select name="branch_code" required>
                        <option value="">-- Select Study Centre --</option>
                        <option value="SHINGE">Shinge (A)</option>
                        <option value="SABUWA">Sabuwa (B)</option>
                        <option value="TUDUN">Tudun (C)</option>
                    </select>
                </div>
                <div class="form-group"></div>
            </div>
            <button type="submit" name="add_student" class="btn btn-green">✅ Add Student</button>
        </form>
    </div>

    <!-- STUDENTS LIST -->
    <div class="card">
        <div class="card-header">
            <h2>📋 List of Students</h2>
            <span style="color:#6a8f6a; font-size:0.9rem;">Total: <?php echo $total_students; ?> students</span>
        </div>
        
        <div class="level-filter">
            <a href="?filter_level=all" class="btn btn-green <?php echo ($filter_level == 'all') ? 'active' : ''; ?>">📋 All</a>
            <a href="?filter_level=NCE%20III" class="btn btn-green <?php echo ($filter_level == 'NCE III') ? 'active' : ''; ?>" style="background:#1b5e20;">🏆 NCE III</a>
            <a href="?filter_level=NCE%20II" class="btn btn-blue <?php echo ($filter_level == 'NCE II') ? 'active' : ''; ?>">📘 NCE II</a>
            <a href="?filter_level=NCE%20I" class="btn btn-orange <?php echo ($filter_level == 'NCE I') ? 'active' : ''; ?>">📗 NCE I</a>
        </div>
        
        <?php if (isset($pass_success)): ?><div class="alert alert-success"><?php echo $pass_success; ?></div><?php endif; ?>
        
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Reg No</th>
                        <th>Full Name</th>
                        <th>Course</th>
                        <th>Level</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($students_result && mysqli_num_rows($students_result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($students_result)): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['reg_no'] ?? $row['student_id'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['fullname'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['course'] ?? ''); ?></td>
                            <td>
                                <span class="badge badge-<?php echo strtolower(str_replace(' ', '', $row['level'] ?? 'ncei')); ?>">
                                    <?php echo $row['level'] ?? 'NCE I'; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $row['status'] ?? 'pending'; ?>">
                                    <?php echo ucfirst($row['status'] ?? 'Pending'); ?>
                                </span>
                            </td>
                            <td class="action-btns">
                                <a href="#" class="view-btn" onclick="viewFullRecord(<?php echo $row['id']; ?>); return false;" title="View Full Record"><i class="fas fa-id-card"></i></a>
                                <a href="student_id_card.php?student_id=<?php echo $row['id']; ?>" class="idcard-btn" title="ID Card" target="_blank"><i class="fas fa-user-circle"></i></a>
                                <a href="student_tp_result.php?student_id=<?php echo $row['id']; ?>" class="tp-btn" title="T.P Result" target="_blank"><i class="fas fa-chalkboard-teacher"></i></a>
                                <a href="?change_student_status=<?php echo $row['id']; ?>&status=active" class="accept-btn" title="Activate"><i class="fas fa-check"></i></a>
                                <a href="?change_student_status=<?php echo $row['id']; ?>&status=rejected" class="reject-btn" title="Reject"><i class="fas fa-times"></i></a>
                                <a href="#" class="edit-btn" onclick="editStudent(<?php echo $row['id']; ?>); return false;" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="#" class="pass-btn" onclick="changeStudentPassword(<?php echo $row['id']; ?>); return false;" title="Password"><i class="fas fa-key"></i></a>
                                <a href="?delete_student=<?php echo $row['id']; ?>" class="delete-btn" onclick="return confirm('Delete?')" title="Delete"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center; padding:30px; color:#6a8f6a;">No students found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- SCORE ENTRY -->
    <div class="card">
        <div class="card-header">
            <h2>📝 Score Entry (Enter Student Scores)</h2>
            <span style="color:#6a8f6a; font-size:0.9rem;">Select a student to enter CA and Exam scores</span>
        </div>
        
        <?php if (isset($score_success)): ?><div class="alert alert-success"><?php echo $score_success; ?></div><?php endif; ?>
        <?php if (isset($score_error)): ?><div class="alert alert-error"><?php echo $score_error; ?></div><?php endif; ?>
        
        <form method="GET" action="" style="background:#e8f5e9; padding:20px; border-radius:12px; margin-bottom:20px;">
            <input type="hidden" name="score_entry" value="1">
            <div class="form-row" style="grid-template-columns: 1fr 1fr 1fr 1fr auto;">
                <div class="form-group">
                    <label>Combination</label>
                    <select name="combination" required>
                        <option value="">-- Select --</option>
                        <option value="CSC/BIO" <?php echo (isset($_GET['combination']) && $_GET['combination'] == 'CSC/BIO') ? 'selected' : ''; ?>>CSC/BIO</option>
                        <option value="CSC/ISC" <?php echo (isset($_GET['combination']) && $_GET['combination'] == 'CSC/ISC') ? 'selected' : ''; ?>>CSC/ISC</option>
                        <option value="CSC/PHY" <?php echo (isset($_GET['combination']) && $_GET['combination'] == 'CSC/PHY') ? 'selected' : ''; ?>>CSC/PHY</option>
                        <option value="ENG/ISS" <?php echo (isset($_GET['combination']) && $_GET['combination'] == 'ENG/ISS') ? 'selected' : ''; ?>>ENG/ISS</option>
                        <option value="ENG/SOS" <?php echo (isset($_GET['combination']) && $_GET['combination'] == 'ENG/SOS') ? 'selected' : ''; ?>>ENG/SOS</option>
                        <option value="ENG/ECO" <?php echo (isset($_GET['combination']) && $_GET['combination'] == 'ENG/ECO') ? 'selected' : ''; ?>>ENG/ECO</option>
                        <option value="HAU/ENG" <?php echo (isset($_GET['combination']) && $_GET['combination'] == 'HAU/ENG') ? 'selected' : ''; ?>>HAU/ENG</option>
                        <option value="ARB/ISS" <?php echo (isset($_GET['combination']) && $_GET['combination'] == 'ARB/ISS') ? 'selected' : ''; ?>>ARB/ISS</option>
                        <option value="PED" <?php echo (isset($_GET['combination']) && $_GET['combination'] == 'PED') ? 'selected' : ''; ?>>PED</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Level</label>
                    <select name="level" required>
                        <option value="">-- Select --</option>
                        <option value="NCE I" <?php echo (isset($_GET['level']) && $_GET['level'] == 'NCE I') ? 'selected' : ''; ?>>NCE I</option>
                        <option value="NCE II" <?php echo (isset($_GET['level']) && $_GET['level'] == 'NCE II') ? 'selected' : ''; ?>>NCE II</option>
                        <option value="NCE III" <?php echo (isset($_GET['level']) && $_GET['level'] == 'NCE III') ? 'selected' : ''; ?>>NCE III</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Semester</label>
                    <select name="semester" required>
                        <option value="">-- Select --</option>
                        <option value="First Semester" <?php echo (isset($_GET['semester']) && $_GET['semester'] == 'First Semester') ? 'selected' : ''; ?>>First Semester</option>
                        <option value="Second Semester" <?php echo (isset($_GET['semester']) && $_GET['semester'] == 'Second Semester') ? 'selected' : ''; ?>>Second Semester</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Session</label>
                    <select name="session" required>
                        <option value="2024/2025" <?php echo (isset($_GET['session']) && $_GET['session'] == '2024/2025') ? 'selected' : ''; ?>>2024/2025</option>
                        <option value="2023/2024" <?php echo (isset($_GET['session']) && $_GET['session'] == '2023/2024') ? 'selected' : ''; ?>>2023/2024</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-green" style="width:100%;">
                        <i class="fas fa-search"></i> Load Students
                    </button>
                </div>
            </div>
        </form>
        
        <?php
        if (isset($_GET['score_entry']) && !empty($_GET['combination']) && !empty($_GET['level'])) {
            $score_combination = mysqli_real_escape_string($conn, $_GET['combination']);
            $score_level = mysqli_real_escape_string($conn, $_GET['level']);
            $score_semester = mysqli_real_escape_string($conn, $_GET['semester'] ?? 'First Semester');
            $score_session = mysqli_real_escape_string($conn, $_GET['session'] ?? '2024/2025');
            
            $score_student_query = "SELECT id, reg_no, fullname, combination, level 
                                    FROM students 
                                    WHERE combination = '$score_combination' 
                                    AND REPLACE(level, ' ', '') = REPLACE('$score_level', ' ', '')
                                    AND status IN ('active', 'approved')
                                    ORDER BY fullname ASC";
            $score_student_result = mysqli_query($conn, $score_student_query);
            $score_students = [];
            if ($score_student_result) {
                while ($row = mysqli_fetch_assoc($score_student_result)) {
                    $score_students[] = $row;
                }
            }
            
            if (!empty($score_students)):
        ?>
            <div style="background:#f8faf8; padding:20px; border-radius:12px; border-left:4px solid #2e7d32;">
                <form method="GET" action="">
                    <input type="hidden" name="score_entry" value="1">
                    <input type="hidden" name="combination" value="<?php echo htmlspecialchars($score_combination); ?>">
                    <input type="hidden" name="level" value="<?php echo htmlspecialchars($score_level); ?>">
                    <input type="hidden" name="semester" value="<?php echo htmlspecialchars($score_semester); ?>">
                    <input type="hidden" name="session" value="<?php echo htmlspecialchars($score_session); ?>">
                    
                    <div class="form-group">
                        <label>Select Student:</label>
                        <select name="student_id" required style="padding:12px; font-size:1rem;">
                            <option value="">-- Select Student --</option>
                            <?php foreach ($score_students as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo (isset($_GET['student_id']) && $_GET['student_id'] == $s['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['reg_no']); ?> — <?php echo htmlspecialchars($s['fullname']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-blue" style="margin-top:10px;">
                        <i class="fas fa-eye"></i> View Courses
                    </button>
                </form>
            </div>
            <?php else: ?>
                <div class="alert alert-error">⚠️ No students found for this combination and level.</div>
            <?php endif; ?>
        <?php } ?>
        
        <?php
        if (isset($_GET['student_id']) && intval($_GET['student_id']) > 0) {
            $score_student_id = intval($_GET['student_id']);
            $score_level = mysqli_real_escape_string($conn, $_GET['level'] ?? '');
            $score_semester = mysqli_real_escape_string($conn, $_GET['semester'] ?? '');
            $score_session = mysqli_real_escape_string($conn, $_GET['session'] ?? '2024/2025');
            
            $s_info_query = "SELECT * FROM students WHERE id = $score_student_id";
            $s_info_result = mysqli_query($conn, $s_info_query);
            $s_info = mysqli_fetch_assoc($s_info_result);
            
            $courses_query = "SELECT * FROM course_registrations 
                              WHERE student_id = $score_student_id 
                              AND level = '$score_level' 
                              AND semester = '$score_semester' 
                              AND academic_year = '$score_session'
                              AND status != 'dropped'
                              ORDER BY course_code";
            $courses_result = mysqli_query($conn, $courses_query);
            $courses = [];
            if ($courses_result) {
                while ($row = mysqli_fetch_assoc($courses_result)) {
                    $courses[] = $row;
                }
            }
            
            if ($s_info):
        ?>
            <div style="background:#e8f5e9; padding:15px; border-radius:10px; margin-bottom:20px; margin-top:20px;">
                <h3><?php echo htmlspecialchars($s_info['fullname']); ?></h3>
                <p>
                    Reg No: <strong><?php echo htmlspecialchars($s_info['reg_no']); ?></strong> | 
                    Combination: <strong><?php echo htmlspecialchars($s_info['combination']); ?></strong><br>
                    Level: <strong><?php echo htmlspecialchars($score_level); ?></strong> | 
                    Semester: <strong><?php echo htmlspecialchars($score_semester); ?></strong> | 
                    Session: <strong><?php echo htmlspecialchars($score_session); ?></strong>
                </p>
            </div>
            
            <?php if (!empty($courses)): ?>
            <form method="POST" action="">
                <input type="hidden" name="student_id" value="<?php echo $score_student_id; ?>">
                <input type="hidden" name="level" value="<?php echo htmlspecialchars($score_level); ?>">
                <input type="hidden" name="semester" value="<?php echo htmlspecialchars($score_semester); ?>">
                <input type="hidden" name="academic_year" value="<?php echo htmlspecialchars($score_session); ?>">
                
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Title</th>
                                <th>Unit</th>
                                <th>CA Score (Max 30)</th>
                                <th>Exam Score (Max 70)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($course['course_title']); ?></td>
                                <td><?php echo $course['credits']; ?></td>
                                <td>
                                    <input type="number" name="ca_score[<?php echo $course['course_code']; ?>]" 
                                           min="0" max="30" value="0" required 
                                           style="width:100px; padding:8px; border:2px solid #dce8dc; border-radius:6px;">
                                </td>
                                <td>
                                    <input type="number" name="exam_score[<?php echo $course['course_code']; ?>]" 
                                           min="0" max="70" value="0" required 
                                           style="width:100px; padding:8px; border:2px solid #dce8dc; border-radius:6px;">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="submit" name="save_scores" class="btn btn-green" style="margin-top:15px;">
                    <i class="fas fa-save"></i> Save Scores
                </button>
            </form>
            <?php else: ?>
                <div class="alert alert-error">
                    ⚠️ <strong>No Courses Found</strong><br>
                    No courses registered for this student in this Level, Semester, and Session.
                </div>
            <?php endif; ?>
        <?php endif; } ?>
    </div>

    <!-- T.P SCORE ENTRY -->
    <div class="card">
        <div class="card-header">
            <h2>🎓 Teaching Practice Score Entry</h2>
            <span style="color:#6a8f6a; font-size:0.9rem;">Enter T.P scores for students</span>
        </div>
        
        <?php if (isset($tp_success)): ?><div class="alert alert-success"><?php echo $tp_success; ?></div><?php endif; ?>
        <?php if (isset($tp_error)): ?><div class="alert alert-error"><?php echo $tp_error; ?></div><?php endif; ?>
        
        <form method="GET" action="" style="background:#fff3e0; padding:20px; border-radius:12px; margin-bottom:20px;">
            <input type="hidden" name="tp_entry" value="1">
            <div class="form-row" style="grid-template-columns: 1fr 1fr 1fr auto;">
                <div class="form-group">
                    <label>Combination</label>
                    <select name="combination" required>
                        <option value="">-- Select --</option>
                        <option value="CSC/BIO" <?php echo (isset($_GET['combination']) && $_GET['combination'] == 'CSC/BIO') ? 'selected' : ''; ?>>CSC/BIO</option>
                        <option value="CSC/ISC" <?php echo (isset($_GET['combination']) && $_GET['combination'] == 'CSC/ISC') ? 'selected' : ''; ?>>CSC/ISC</option>
                        <option value="CSC/PHY" <?php echo (isset($_GET['combination']) && $_GET['combination'] == 'CSC/PHY') ? 'selected' : ''; ?>>CSC/PHY</option>
                        <option value="ENG/ISS" <?php echo (isset($_GET['combination']) && $_GET['combination'] == 'ENG/ISS') ? 'selected' : ''; ?>>ENG/ISS</option>
                        <option value="ENG/SOS" <?php echo (isset($_GET['combination']) && $_GET['combination'] == 'ENG/SOS') ? 'selected' : ''; ?>>ENG/SOS</option>
                        <option value="ENG/ECO" <?php echo (isset($_GET['combination']) && $_GET['combination'] == 'ENG/ECO') ? 'selected' : ''; ?>>ENG/ECO</option>
                        <option value="HAU/ENG" <?php echo (isset($_GET['combination']) && $_GET['combination'] == 'HAU/ENG') ? 'selected' : ''; ?>>HAU/ENG</option>
                        <option value="ARB/ISS" <?php echo (isset($_GET['combination']) && $_GET['combination'] == 'ARB/ISS') ? 'selected' : ''; ?>>ARB/ISS</option>
                        <option value="PED" <?php echo (isset($_GET['combination']) && $_GET['combination'] == 'PED') ? 'selected' : ''; ?>>PED</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Level</label>
                    <select name="level" required>
                        <option value="">-- Select --</option>
                        <option value="NCE I" <?php echo (isset($_GET['level']) && $_GET['level'] == 'NCE I') ? 'selected' : ''; ?>>NCE I</option>
                        <option value="NCE II" <?php echo (isset($_GET['level']) && $_GET['level'] == 'NCE II') ? 'selected' : ''; ?>>NCE II</option>
                        <option value="NCE III" <?php echo (isset($_GET['level']) && $_GET['level'] == 'NCE III') ? 'selected' : ''; ?>>NCE III</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Session</label>
                    <select name="session" required>
                        <option value="2024/2025" <?php echo (isset($_GET['session']) && $_GET['session'] == '2024/2025') ? 'selected' : ''; ?>>2024/2025</option>
                        <option value="2023/2024" <?php echo (isset($_GET['session']) && $_GET['session'] == '2023/2024') ? 'selected' : ''; ?>>2023/2024</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-orange" style="width:100%;">
                        <i class="fas fa-search"></i> Load Students
                    </button>
                </div>
            </div>
        </form>
        
        <?php
        if (isset($_GET['tp_entry']) && !empty($_GET['combination']) && !empty($_GET['level'])) {
            $tp_combination = mysqli_real_escape_string($conn, $_GET['combination']);
            $tp_level = mysqli_real_escape_string($conn, $_GET['level']);
            $tp_session = mysqli_real_escape_string($conn, $_GET['session'] ?? '2024/2025');
            
            $tp_students_query = "SELECT id, reg_no, fullname FROM students 
                                  WHERE combination = '$tp_combination' 
                                  AND REPLACE(level, ' ', '') = REPLACE('$tp_level', ' ', '')
                                  AND status IN ('active', 'approved')
                                  ORDER BY fullname ASC";
            $tp_students_result = mysqli_query($conn, $tp_students_query);
            $tp_students = [];
            if ($tp_students_result) {
                while ($row = mysqli_fetch_assoc($tp_students_result)) {
                    $tp_students[] = $row;
                }
            }
            
            if (!empty($tp_students)):
        ?>
            <div style="background:#f8faf8; padding:20px; border-radius:12px; border-left:4px solid #e65100;">
                <form method="GET" action="">
                    <input type="hidden" name="tp_entry" value="1">
                    <input type="hidden" name="combination" value="<?php echo htmlspecialchars($tp_combination); ?>">
                    <input type="hidden" name="level" value="<?php echo htmlspecialchars($tp_level); ?>">
                    <input type="hidden" name="session" value="<?php echo htmlspecialchars($tp_session); ?>">
                    
                    <div class="form-group">
                        <label>Select Student:</label>
                        <select name="tp_student_id" required style="padding:12px; font-size:1rem;">
                            <option value="">-- Select Student --</option>
                            <?php foreach ($tp_students as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo (isset($_GET['tp_student_id']) && $_GET['tp_student_id'] == $s['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['reg_no']); ?> — <?php echo htmlspecialchars($s['fullname']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-orange" style="margin-top:10px;">
                        <i class="fas fa-eye"></i> Load T.P Form
                    </button>
                </form>
            </div>
            <?php else: ?>
                <div class="alert alert-error">⚠️ No students found for this combination and level.</div>
            <?php endif; ?>
        <?php } ?>
        
        <?php
        if (isset($_GET['tp_student_id']) && intval($_GET['tp_student_id']) > 0) {
            $tp_student_id = intval($_GET['tp_student_id']);
            $tp_level = mysqli_real_escape_string($conn, $_GET['level'] ?? '');
            $tp_session = mysqli_real_escape_string($conn, $_GET['session'] ?? '2024/2025');
            
            $s_info_query = "SELECT * FROM students WHERE id = $tp_student_id";
            $s_info_result = mysqli_query($conn, $s_info_query);
            $s_info = mysqli_fetch_assoc($s_info_result);
            
            $existing_query = "SELECT * FROM tp_results WHERE student_id = $tp_student_id AND academic_year = '$tp_session' LIMIT 1";
            $existing_result = mysqli_query($conn, $existing_query);
            $existing = mysqli_fetch_assoc($existing_result);
            
            if ($s_info):
        ?>
            <div style="background:#e8f5e9; padding:15px; border-radius:10px; margin-bottom:20px; margin-top:20px;">
                <h3><?php echo htmlspecialchars($s_info['fullname']); ?></h3>
                <p>
                    Reg No: <strong><?php echo htmlspecialchars($s_info['reg_no']); ?></strong> | 
                    Combination: <strong><?php echo htmlspecialchars($s_info['combination']); ?></strong> | 
                    Level: <strong><?php echo htmlspecialchars($tp_level); ?></strong> | 
                    Session: <strong><?php echo htmlspecialchars($tp_session); ?></strong>
                </p>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="tp_student_id" value="<?php echo $tp_student_id; ?>">
                <input type="hidden" name="tp_level" value="<?php echo htmlspecialchars($tp_level); ?>">
                <input type="hidden" name="tp_session" value="<?php echo htmlspecialchars($tp_session); ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Teaching Practice School</label>
                        <input type="text" name="school_name" value="<?php echo htmlspecialchars($existing['school_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Supervisor Name</label>
                        <input type="text" name="supervisor_name" value="<?php echo htmlspecialchars($existing['supervisor_name'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Teaching Practice (Max 40)</label>
                        <input type="number" name="teaching_score" min="0" max="40" value="<?php echo $existing['teaching_score'] ?? 0; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Lesson Note Preparation (Max 20)</label>
                        <input type="number" name="lesson_note_score" min="0" max="20" value="<?php echo $existing['lesson_note_score'] ?? 0; ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Punctuality &amp; Regularity (Max 20)</label>
                        <input type="number" name="punctuality_score" min="0" max="20" value="<?php echo $existing['punctuality_score'] ?? 0; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Relationship with Staff &amp; Students (Max 20)</label>
                        <input type="number" name="relationship_score" min="0" max="20" value="<?php echo $existing['relationship_score'] ?? 0; ?>" required>
                    </div>
                </div>
                
                <button type="submit" name="save_tp_scores" class="btn btn-orange" style="margin-top:15px;">
                    <i class="fas fa-save"></i> Save T.P Scores
                </button>
            </form>
        <?php endif; } ?>
    </div>

    <!-- STAFF LIST -->
    <div class="card">
        <div class="card-header">
            <h2>👥 List of Staff</h2>
            <span style="color:#6a8f6a; font-size:0.9rem;">Total: <?php echo $total_staff; ?> staff</span>
        </div>
        <?php if (isset($staff_edit_success)): ?><div class="alert alert-success"><?php echo $staff_edit_success; ?></div><?php endif; ?>
        <?php if (isset($staff_pass_success)): ?><div class="alert alert-success"><?php echo $staff_pass_success; ?></div><?php endif; ?>
        
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Staff ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($staff_result && mysqli_num_rows($staff_result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($staff_result)): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['staff_id'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                            <td><?php echo htmlspecialchars($row['email'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['phone'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo htmlspecialchars($row['role']); ?></td>
                            <td class="action-btns">
                                <a href="#" class="edit-btn" onclick="editStaff(<?php echo $row['id']; ?>); return false;"><i class="fas fa-edit"></i></a>
                                <a href="#" class="pass-btn" onclick="changeStaffPassword(<?php echo $row['id']; ?>); return false;"><i class="fas fa-key"></i></a>
                                <a href="?delete_staff=<?php echo $row['id']; ?>" class="delete-btn" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align:center; padding:30px; color:#6a8f6a;">No staff found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODALS -->
<div class="modal-overlay" id="editStudentModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('editStudentModal')">&times;</button>
        <h3 style="margin-bottom:20px;">✏️ Edit Student</h3>
        <div id="editStudentFormContainer"></div>
    </div>
</div>

<div class="modal-overlay" id="changeStudentPassModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('changeStudentPassModal')">&times;</button>
        <h3 style="margin-bottom:20px;">🔑 Change Student Password</h3>
        <div id="changeStudentPassFormContainer"></div>
    </div>
</div>

<div class="modal-overlay" id="editStaffModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('editStaffModal')">&times;</button>
        <h3 style="margin-bottom:20px;">✏️ Edit Staff</h3>
        <div id="editStaffFormContainer"></div>
    </div>
</div>

<div class="modal-overlay" id="changeStaffPassModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('changeStaffPassModal')">&times;</button>
        <h3 style="margin-bottom:20px;">🔑 Change Staff Password</h3>
        <div id="changeStaffPassFormContainer"></div>
    </div>
</div>

<!-- MODAL: FULL STUDENT RECORD -->
<div class="modal-overlay" id="fullRecordModal" style="display:none;">
    <div class="modal-content" style="max-width:900px;">
        <button class="modal-close" onclick="closeModal('fullRecordModal')">&times;</button>
        <h2 style="margin-bottom:20px; color:#0d2818;">
            <i class="fas fa-id-card" style="color:#7b1fa2;"></i> Full Student Record
        </h2>
        <div id="fullRecordContent">
            <p style="text-align:center; padding:40px; color:#6a8f6a;">
                <i class="fas fa-spinner fa-spin" style="font-size:2rem;"></i><br>
                Loading...
            </p>
        </div>
    </div>
</div>

<script>
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
window.onclick = function(event) { if (event.target.className === 'modal-overlay') event.target.style.display = 'none'; }

function updateCourses() {
    var programme = document.getElementById('programme').value;
    var courseSelect = document.getElementById('course');
    courseSelect.innerHTML = '<option value="">-- Select Course --</option>';
    
    var courses = [];
    if (programme === 'NCE') {
        courses = [
            { value: 'ARB/ISS', text: 'ARB/ISS - Arabic / Islamic Studies' },
            { value: 'ENG/ISS', text: 'ENG/ISS - English / Islamic Studies' },
            { value: 'PED', text: 'PED - Primary Education' },
            { value: 'HAU/ENG', text: 'HAU/ENG - Hausa / English' },
            { value: 'CSC/ISC', text: 'CSC/ISC - Computer Science / Islamic Studies' },
            { value: 'ENG/SOS', text: 'ENG/SOS - English / Social Studies' },
            { value: 'CSC/BIO', text: 'CSC/BIO - Computer Science / Biology' },
            { value: 'CSC/PHY', text: 'CSC/PHY - Computer Science / Physics' },
            { value: 'ENG/ECO', text: 'ENG/ECO - English / Economics' }
        ];
    } else if (programme === 'DEGREE') {
        courses = [
            { value: 'BA_ARABIC', text: 'B.A. Arabic' },
            { value: 'BSC_CSC', text: 'B.Sc. Computer Science Education' },
            { value: 'BSC_PHYSICS', text: 'B.Sc. Physics Education' },
            { value: 'BSC_BIOLOGY', text: 'B.Sc. Biology Education' }
        ];
    }
    courses.forEach(function(c) {
        var option = document.createElement('option');
        option.value = c.value;
        option.textContent = c.text;
        courseSelect.appendChild(option);
    });
}

function editStudent(id) {
    fetch('get_student.php?id=' + id).then(r => r.json()).then(data => {
        if (data.success) {
            var html = `
            <form method="POST">
                <input type="hidden" name="student_id" value="${data.id}">
                <div class="form-row">
                    <div class="form-group"><label>Full Name</label><input type="text" name="fullname" value="${data.fullname || ''}" required></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" value="${data.email || ''}" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Phone</label><input type="tel" name="phone" value="${data.phone || ''}"></div>
                    <div class="form-group"><label>Programme</label>
                        <select name="programme">
                            <option value="NCE" ${data.programme == 'NCE' ? 'selected' : ''}>NCE</option>
                            <option value="DEGREE" ${data.programme == 'DEGREE' ? 'selected' : ''}>Degree</option>
                            <option value="ENTREPRENEURSHIP" ${data.programme == 'ENTREPRENEURSHIP' ? 'selected' : ''}>Entrepreneurship</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Course</label><input type="text" name="course" value="${data.course || ''}"></div>
                    <div class="form-group"><label>Level</label>
                        <select name="level">
                            <option value="NCE I" ${data.level == 'NCE I' ? 'selected' : ''}>NCE I</option>
                            <option value="NCE II" ${data.level == 'NCE II' ? 'selected' : ''}>NCE II</option>
                            <option value="NCE III" ${data.level == 'NCE III' ? 'selected' : ''}>NCE III</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Status</label>
                        <select name="status">
                            <option value="pending" ${data.status == 'pending' ? 'selected' : ''}>Pending</option>
                            <option value="active" ${data.status == 'active' ? 'selected' : ''}>Active</option>
                            <option value="approved" ${data.status == 'approved' ? 'selected' : ''}>Approved</option>
                            <option value="rejected" ${data.status == 'rejected' ? 'selected' : ''}>Rejected</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Study Centre</label>
                        <select name="branch_code">
                            <option value="SHINGE" ${data.branch_code == 'SHINGE' ? 'selected' : ''}>Shinge (A)</option>
                            <option value="SABUWA" ${data.branch_code == 'SABUWA' ? 'selected' : ''}>Sabuwa (B)</option>
                            <option value="TUDUN" ${data.branch_code == 'TUDUN' ? 'selected' : ''}>Tudun (C)</option>
                        </select>
                    </div>
                </div>
                <input type="hidden" name="edit_student" value="1">
                <button type="submit" class="btn btn-blue" style="margin-top:15px;">Update Student</button>
            </form>`;
            document.getElementById('editStudentFormContainer').innerHTML = html;
            document.getElementById('editStudentModal').style.display = 'block';
        }
    });
}

function changeStudentPassword(id) {
    fetch('get_student.php?id=' + id).then(r => r.json()).then(data => {
        if (data.success) {
            var html = `
            <form method="POST">
                <input type="hidden" name="student_id" value="${data.id}">
                <div class="form-group"><label>New Password *</label><input type="password" name="new_password" required minlength="6"></div>
                <input type="hidden" name="change_student_password" value="1">
                <button type="submit" class="btn btn-orange" style="margin-top:15px;">Change Password</button>
            </form>`;
            document.getElementById('changeStudentPassFormContainer').innerHTML = html;
            document.getElementById('changeStudentPassModal').style.display = 'block';
        }
    });
}

function editStaff(id) {
    fetch('get_staff.php?id=' + id).then(r => r.json()).then(data => {
        if (data.success) {
            var html = `
            <form method="POST">
                <input type="hidden" name="staff_id" value="${data.id}">
                <div class="form-row">
                    <div class="form-group"><label>Full Name</label><input type="text" name="fullname" value="${data.fullname}" required></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" value="${data.email || ''}"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Phone</label><input type="tel" name="phone" value="${data.phone || ''}"></div>
                    <div class="form-group"><label>Username</label><input type="text" name="username" value="${data.username}" required></div>
                </div>
                <div class="form-group"><label>Role</label>
                    <select name="role">
                        <option value="Provost" ${data.role == 'Provost' ? 'selected' : ''}>Provost</option>
                        <option value="Admission Officer" ${data.role == 'Admission Officer' ? 'selected' : ''}>Admission Officer</option>
                        <option value="Bursary Officer" ${data.role == 'Bursary Officer' ? 'selected' : ''}>Bursary Officer</option>
                        <option value="Accountant" ${data.role == 'Accountant' ? 'selected' : ''}>Accountant</option>
                        <option value="Exam Officer" ${data.role == 'Exam Officer' ? 'selected' : ''}>Exam Officer</option>
                        <option value="Staff" ${data.role == 'Staff' ? 'selected' : ''}>Staff</option>
                    </select>
                </div>
                <input type="hidden" name="edit_staff" value="1">
                <button type="submit" class="btn btn-blue" style="margin-top:15px;">Update Staff</button>
            </form>`;
            document.getElementById('editStaffFormContainer').innerHTML = html;
            document.getElementById('editStaffModal').style.display = 'block';
        }
    });
}

function changeStaffPassword(id) {
    fetch('get_staff.php?id=' + id).then(r => r.json()).then(data => {
        if (data.success) {
            var html = `
            <form method="POST">
                <input type="hidden" name="staff_id" value="${data.id}">
                <div class="form-group"><label>New Password *</label><input type="password" name="new_password" required minlength="6"></div>
                <input type="hidden" name="change_staff_password" value="1">
                <button type="submit" class="btn btn-orange" style="margin-top:15px;">Change Password</button>
            </form>`;
            document.getElementById('changeStaffPassFormContainer').innerHTML = html;
            document.getElementById('changeStaffPassModal').style.display = 'block';
        }
    });
}

// ============================================
// VIEW FULL RECORD
// ============================================
function viewFullRecord(id) {
    var modal = document.getElementById('fullRecordModal');
    var content = document.getElementById('fullRecordContent');
    
    modal.style.display = 'block';
    content.innerHTML = '<p style="text-align:center; padding:40px; color:#6a8f6a;"><i class="fas fa-spinner fa-spin" style="font-size:2rem;"></i><br>Loading...</p>';
    
    fetch('get_student_full_record.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                content.innerHTML = '<div class="alert alert-error">❌ ' + (data.message || 'Error loading record') + '</div>';
                return;
            }
            
            var s = data.student;
            var docs = data.documents;
            var exam = data.exam_card;
            var results = data.results;
            var tp_result = data.tp_result;
            var scratch = data.scratch_cards;
            var sessions = data.sessions;
            var regs = data.registrations;
            var total_printed = data.total_printed || {};
            
            function statusIcon(status) {
                if (status === 'approved') return '<span style="color:#2e7d32; font-weight:700;">✅ Approved</span>';
                if (status === 'pending') return '<span style="color:#ffa000; font-weight:700;">⏳ Pending</span>';
                if (status === 'rejected') return '<span style="color:#c62828; font-weight:700;">❌ Rejected</span>';
                return '<span style="color:#999;">— Not Uploaded</span>';
            }
            
            var html = '';
            
            // STUDENT HEADER
            html += '<div style="background:#0d2818; color:white; padding:20px; border-radius:12px; margin-bottom:20px;">';
            html += '<h2 style="color:#ffd54f; margin-bottom:10px;">' + s.fullname + '</h2>';
            html += '<p style="font-size:0.9rem; color:#c8e6c9;">';
            html += 'Reg No: <strong>' + s.reg_no + '</strong> | Combination: <strong>' + s.combination + '</strong><br>';
            html += 'Level: <strong>' + s.level + '</strong> | Programme: <strong>' + s.programme + '</strong> | Centre: <strong>' + s.branch_code + '</strong><br>';
            html += 'Email: <strong>' + s.email + '</strong> | Phone: <strong>' + s.phone + '</strong> | Status: <strong>' + s.status.toUpperCase() + '</strong>';
            html += '</p></div>';
            
            // DOCUMENTS
            html += '<h3 style="color:#0d2818; margin-bottom:15px;"><i class="fas fa-file-alt" style="color:#1976d2;"></i> Documents Status</h3>';
            html += '<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:25px;">';
            var docList = [
                { key: 'admission_letter', label: '📄 Admission Letter', data: docs.admission_letter },
                { key: 'acceptance_letter', label: '✍️ Acceptance Letter', data: docs.acceptance_letter },
                { key: 'introductory_letter', label: '📝 Introductory Letter', data: docs.introductory_letter },
                { key: 'posting_letter', label: '📍 Posting Letter', data: docs.posting_letter }
            ];
            docList.forEach(function(doc) {
                html += '<div style="background:#f8faf8; padding:12px 15px; border-radius:8px; border-left:4px solid ' + (doc.data ? '#2e7d32' : '#ddd') + ';">';
                html += '<strong>' + doc.label + '</strong><br>';
                html += statusIcon(doc.data ? doc.data.status : null);
                html += '</div>';
            });
            html += '</div>';
            
            // ACADEMIC STATUS
            html += '<h3 style="color:#0d2818; margin-bottom:15px;"><i class="fas fa-chart-bar" style="color:#7b1fa2;"></i> Academic Status</h3>';
            html += '<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:25px;">';
            html += '<div style="background:#f8faf8; padding:12px 15px; border-radius:8px; border-left:4px solid ' + (exam.has_exam_card ? '#2e7d32' : '#ddd') + ';">';
            html += '<strong>🎫 Exam Card</strong><br>';
            html += exam.has_exam_card ? '<span style="color:#2e7d32; font-weight:700;">✅ Generated (' + exam.total_registered_courses + ' courses)</span>' : '<span style="color:#c62828;">❌ No registered courses</span>';
            html += '</div>';
            html += '<div style="background:#f8faf8; padding:12px 15px; border-radius:8px; border-left:4px solid ' + (results.has_results ? '#2e7d32' : '#ddd') + ';">';
            html += '<strong>📊 Results</strong><br>';
            html += results.has_results ? '<span style="color:#2e7d32; font-weight:700;">✅ ' + results.total_results + ' results entered</span>' : '<span style="color:#c62828;">❌ No results entered</span>';
            html += '</div>';
            html += '</div>';
            
            // T.P RESULT
            if (tp_result) {
                html += '<h3 style="color:#0d2818; margin-bottom:15px;"><i class="fas fa-chalkboard-teacher" style="color:#e65100;"></i> Teaching Practice Result</h3>';
                html += '<div style="background:#fff3e0; padding:15px; border-radius:10px; border-left:4px solid #e65100; margin-bottom:25px;">';
                html += '<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">';
                html += '<div><strong>Session:</strong> ' + tp_result.academic_year + '</div>';
                html += '<div><strong>Level:</strong> ' + tp_result.level + '</div>';
                html += '<div><strong>School:</strong> ' + (tp_result.school_name || '—') + '</div>';
                html += '<div><strong>Supervisor:</strong> ' + (tp_result.supervisor_name || '—') + '</div>';
                html += '<div><strong>Total Score:</strong> ' + tp_result.total_score + '/100</div>';
                html += '<div><strong>Grade:</strong> ' + tp_result.grade + ' (' + tp_result.remark + ')</div>';
                html += '</div></div>';
            }
            
            // SCRATCH CARDS
            html += '<h3 style="color:#0d2818; margin-bottom:15px;"><i class="fas fa-ticket-alt" style="color:#f57c00;"></i> Scratch Cards (' + scratch.length + ')</h3>';
            if (scratch.length > 0) {
                html += '<table style="width:100%; border-collapse:collapse; font-size:0.8rem; margin-bottom:25px;">';
                html += '<thead><tr style="background:#0d2818; color:white;"><th style="padding:8px;">Serial</th><th style="padding:8px;">PIN</th><th style="padding:8px;">Session</th><th style="padding:8px;">Purpose</th><th style="padding:8px;">Used?</th></tr></thead><tbody>';
                scratch.forEach(function(sc) {
                    html += '<tr><td style="padding:6px; border-bottom:1px solid #eee;"><strong>' + sc.serial_number + '</strong></td>';
                    html += '<td style="padding:6px; border-bottom:1px solid #eee;">' + sc.pin + '</td>';
                    html += '<td style="padding:6px; border-bottom:1px solid #eee;">' + sc.session + '</td>';
                    html += '<td style="padding:6px; border-bottom:1px solid #eee;">' + sc.purpose + '</td>';
                    html += '<td style="padding:6px; border-bottom:1px solid #eee;">' + (sc.is_used == 1 ? '<span style="color:#c62828;">Yes</span>' : '<span style="color:#2e7d32;">No</span>') + '</td></tr>';
                });
                html += '</tbody></table>';
            } else {
                html += '<p style="color:#999; margin-bottom:25px;">No scratch cards found.</p>';
            }
            
            // SESSIONS
            html += '<h3 style="color:#0d2818; margin-bottom:15px;"><i class="fas fa-calendar-alt" style="color:#1976d2;"></i> Session & Level History</h3>';
            if (sessions.length > 0) {
                html += '<table style="width:100%; border-collapse:collapse; font-size:0.8rem; margin-bottom:25px;">';
                html += '<thead><tr style="background:#0d2818; color:white;"><th style="padding:8px;">Session</th><th style="padding:8px;">Level</th><th style="padding:8px;">Semester</th><th style="padding:8px;">Current?</th></tr></thead><tbody>';
                sessions.forEach(function(sess) {
                    html += '<tr><td style="padding:6px; border-bottom:1px solid #eee;">' + sess.academic_year + '</td>';
                    html += '<td style="padding:6px; border-bottom:1px solid #eee;">' + sess.level + '</td>';
                    html += '<td style="padding:6px; border-bottom:1px solid #eee;">' + sess.semester + '</td>';
                    html += '<td style="padding:6px; border-bottom:1px solid #eee;">' + (sess.is_current == 1 ? '<span style="color:#2e7d32; font-weight:700;">✅ Current</span>' : '—') + '</td></tr>';
                });
                html += '</tbody></table>';
            } else {
                html += '<p style="color:#999; margin-bottom:25px;">No session history found.</p>';
            }
            
            // REGISTRATIONS
            html += '<h3 style="color:#0d2818; margin-bottom:15px;"><i class="fas fa-book" style="color:#2e7d32;"></i> Course Registrations</h3>';
            if (regs.length > 0) {
                html += '<table style="width:100%; border-collapse:collapse; font-size:0.8rem; margin-bottom:25px;">';
                html += '<thead><tr style="background:#0d2818; color:white;"><th style="padding:8px;">Session</th><th style="padding:8px;">Level</th><th style="padding:8px;">Semester</th><th style="padding:8px;">Courses</th><th style="padding:8px;">Units</th></tr></thead><tbody>';
                regs.forEach(function(r) {
                    html += '<tr><td style="padding:6px; border-bottom:1px solid #eee;">' + r.academic_year + '</td>';
                    html += '<td style="padding:6px; border-bottom:1px solid #eee;">' + r.level + '</td>';
                    html += '<td style="padding:6px; border-bottom:1px solid #eee;">' + r.semester + '</td>';
                    html += '<td style="padding:6px; border-bottom:1px solid #eee;">' + r.total_courses + '</td>';
                    html += '<td style="padding:6px; border-bottom:1px solid #eee;">' + r.total_units + '</td></tr>';
                });
                html += '</tbody></table>';
            } else {
                html += '<p style="color:#999; margin-bottom:25px;">No course registrations found.</p>';
            }
            
            // TOTAL PRINTED
            html += '<h3 style="color:#0d2818; margin-bottom:15px;"><i class="fas fa-print" style="color:#455a64;"></i> Total Printed Records</h3>';
            html += '<div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:8px; margin-bottom:25px;">';
            var printItems = [
                { label: 'ID Card', value: total_printed.id_card || 0 },
                { label: 'Exam Card', value: total_printed.exam_card || 0 },
                { label: 'T.P Result', value: total_printed.tp_result || 0 },
                { label: 'Admission Letter', value: total_printed.admission_letter || 0 },
                { label: 'Acceptance Letter', value: total_printed.acceptance_letter || 0 },
                { label: 'Introductory Letter', value: total_printed.introductory_letter || 0 },
                { label: 'Posting Letter', value: total_printed.posting_letter || 0 }
            ];
            printItems.forEach(function(item) {
                html += '<div style="background:#f8faf8; padding:10px; border-radius:8px; text-align:center; border-left:4px solid #455a64;">';
                html += '<div style="font-size:1.5rem; font-weight:900; color:#455a64;">' + item.value + '</div>';
                html += '<div style="font-size:0.7rem; color:#6a8f6a; font-weight:700;">' + item.label + '</div>';
                html += '</div>';
            });
            html += '</div>';
            
            content.innerHTML = html;
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-error">❌ Error: ' + error.message + '</div>';
        });
}
</script>

</body>
</html>