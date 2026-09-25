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
// COURSE CODES
// NCCE Standard: ENG/HAU (English / Hausa)
// ============================================
function getDepartmentCode($course) {
    $dept_codes = [
        // NCE Courses
        'ARB/ISS' => 'ARI',   // Arabic / Islamic Studies
        'ENG/ISS' => 'ENI',   // English / Islamic Studies
        'PED'     => 'PED',   // Primary Education
        'ENG/HAU' => 'ENH',   // English / Hausa (NCCE Standard)
        'CSC/ISC' => 'CSI',   // Computer Science / Islamic Studies
        'ENG/SOS' => 'ENS',   // English / Social Studies
        'CSC/BIO' => 'CSB',   // Computer Science / Biology
        'CSC/PHY' => 'CSP',   // Computer Science / Physics
        'ENG/ECO' => 'ENE',   // English / Economics
        
        // Degree Courses
        'BA_ARABIC'     => 'ARI',
        'BA_ISLAMIC'    => 'ISC',
        'BED_ENGLISH'   => 'ENG',
        'BED_HAUSA'     => 'HAU',
        'BED_SOCIAL'    => 'SOC',
        'BSC_ECONOMICS' => 'ECO',
        'BSC_CSC'       => 'CSC',
        'BSC_BIOLOGY'   => 'BIO',
        'BSC_PHYSICS'   => 'PHY',
        'BSC_ISC'       => 'ISC',
        
        // Entrepreneurship
        'TAILORING'      => 'TAI',
        'AI_TECH'        => 'AIT',
        'SALOON'         => 'SAL',
        'HENNA'          => 'HEN',
        'FISH_FARMING'   => 'FIS',
        'POULTRY'        => 'POU',
        'SOAP_MAKING'    => 'SOA',
        'CATERING'       => 'CAT',
        'BEAD_MAKING'    => 'BEA',
        'GRAPHIC_DESIGN' => 'GRA'
    ];
    return $dept_codes[strtoupper(trim($course))] ?? 'GEN';
}

function generateUsername($fullname) {
    $name_parts = explode(' ', trim($fullname));
    $first_name = strtolower(preg_replace('/[^a-zA-Z]/', '', $name_parts[0] ?? 'student'));
    return $first_name . '@123';
}

// ============================================
// YEAR INFO
// ============================================
function getYearInfo($level) {
    $level_upper = strtoupper(trim($level));
    $years = [
        'NCE III'   => ['year_code' => '24', 'entry' => '2024/2025', 'grad' => '2026/2027'],
        'NCE II'    => ['year_code' => '25', 'entry' => '2025/2026', 'grad' => '2027/2028'],
        'NCE I'     => ['year_code' => '26', 'entry' => '2026/2027', 'grad' => '2028/2029'],
        '400 LEVEL' => ['year_code' => '26', 'entry' => '2026/2027', 'grad' => '2029/2030'],
        '500 LEVEL' => ['year_code' => '27', 'entry' => '2027/2028', 'grad' => '2030/2031'],
        'PRE-NCE'   => ['year_code' => '26', 'entry' => '2026/2027', 'grad' => '2027/2028'],
    ];
    return $years[$level_upper] ?? ['year_code' => '26', 'entry' => '2026/2027', 'grad' => '2028/2029'];
}

function getLevelByProgramme($programme, $explicit_level = null) {
    if ($explicit_level && !empty($explicit_level)) return trim($explicit_level);
    $programme = strtoupper(trim($programme));
    switch ($programme) {
        case 'NCE': return 'NCE I';
        case 'DEGREE': case 'DEG': return '400 Level';
        case 'ENTREPRENEURSHIP': case 'ENT': return 'ENTREPRENEURSHIP';
        case 'PRE-NCE': return 'PRE-NCE';
        default: return 'NCE I';
    }
}

// ============================================
// GENERATE ADMISSION NUMBER
// ============================================
function generateAdmissionNumber($programme, $branch_code, $course, $level = 'NCE I') {
    global $conn;
    
    $level = trim($level);
    $level_upper = strtoupper($level);
    
    if (empty($level) || $level_upper == 'NCE' || $level_upper == 'DEGREE' || $level_upper == 'DEG') {
        $level = getLevelByProgramme($programme, $level);
        $level_upper = strtoupper($level);
    }
    
    $programme_upper = strtoupper(trim($programme));
    if ($programme_upper == 'NCE') $program_code = 'NCE';
    elseif ($programme_upper == 'DEGREE' || $programme_upper == 'DEG') $program_code = 'DEG';
    elseif ($programme_upper == 'ENTREPRENEURSHIP' || $programme_upper == 'ENT') $program_code = 'ENT';
    else $program_code = 'NCE';
    
    $year_info = getYearInfo($level);
    $year_code = $year_info['year_code'];
    
    $branch_letter = 'A';
    $bc = strtoupper(trim($branch_code));
    if ($bc == 'SHINGE' || $bc == 'A') $branch_letter = 'A';
    elseif ($bc == 'SABUWA' || $bc == 'SABUWAR KOFA' || $bc == 'B') $branch_letter = 'B';
    elseif ($bc == 'TUDUN' || $bc == 'TUDUN YOLA' || $bc == 'C') $branch_letter = 'C';
    
    $course_code = getDepartmentCode($course);
    
    $adm_query = "SELECT reg_no FROM students 
                  WHERE reg_no LIKE 'DLCOE/$program_code/$year_code%' 
                  AND reg_no IS NOT NULL AND reg_no != ''";
    $adm_result = mysqli_query($conn, $adm_query);
    $max_adm = 0;
    if ($adm_result) {
        while ($row = mysqli_fetch_assoc($adm_result)) {
            if (preg_match('/DLCOE\/' . $program_code . '\/\d{2}[A-Z](\d+)\//', $row['reg_no'], $m)) {
                $num = intval($m[1]);
                if ($num > $max_adm) $max_adm = $num;
            }
        }
    }
    $adm_num = str_pad($max_adm + 1, 3, '0', STR_PAD_LEFT);
    
    $dept_query = "SELECT reg_no FROM students 
                   WHERE reg_no LIKE 'DLCOE/$program_code/$year_code%/$course_code%' 
                   AND reg_no IS NOT NULL AND reg_no != ''";
    $dept_result = mysqli_query($conn, $dept_query);
    $max_dept = 0;
    if ($dept_result) {
        while ($row = mysqli_fetch_assoc($dept_result)) {
            if (preg_match('/' . preg_quote($course_code, '/') . '(\d+)$/', $row['reg_no'], $m)) {
                $num = intval($m[1]);
                if ($num > $max_dept) $max_dept = $num;
            }
        }
    }
    $dept_count = $max_dept + 1;
    $dept_num = str_pad($dept_count, 3, '0', STR_PAD_LEFT);
    
    return "DLCOE/$program_code/$year_code$branch_letter$adm_num/$course_code$dept_num";
}

// Fix NULL levels/status
mysqli_query($conn, "UPDATE students SET level = 'NCE I' WHERE level IS NULL OR level = '' OR level = 'NCE'");
mysqli_query($conn, "UPDATE students SET status = 'pending' WHERE status IS NULL OR status = ''");

// ============================================
// STATS (BASIC)
// ============================================
$total_students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students"))['c'];
$total_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE status = 'pending'"))['c'];
$total_accepted = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE status IN ('approved','graduated','active')"))['c'];
$total_rejected = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE status IN ('rejected','inactive')"))['c'];
$total_staff = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM staff"))['c'];
$nce1_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE level = 'NCE I'"))['c'];
$nce2_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE level = 'NCE II'"))['c'];
$nce3_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE level = 'NCE III'"))['c'];
$degree_400 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE level = '400 Level'"))['c'];
$degree_500 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE level = '500 Level'"))['c'];

$branch_a = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE branch_code = 'SHINGE'"))['c'];
$branch_b = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE branch_code = 'SABUWA'"))['c'];
$branch_c = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE branch_code = 'TUDUN'"))['c'];

// ============================================
// GENDER STATS
// ============================================
$male_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE gender = 'Male'"))['c'];
$female_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE gender = 'Female'"))['c'];
$male_branch_a = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE gender = 'Male' AND branch_code = 'SHINGE'"))['c'];
$female_branch_a = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE gender = 'Female' AND branch_code = 'SHINGE'"))['c'];
$male_branch_b = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE gender = 'Male' AND branch_code = 'SABUWA'"))['c'];
$female_branch_b = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE gender = 'Female' AND branch_code = 'SABUWA'"))['c'];
$male_branch_c = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE gender = 'Male' AND branch_code = 'TUDUN'"))['c'];
$female_branch_c = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE gender = 'Female' AND branch_code = 'TUDUN'"))['c'];

$academic_staff = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as c FROM staff 
    WHERE role = 'Academic Staff'
    OR LOWER(position) LIKE '%lecturer%' 
    OR LOWER(position) LIKE '%tutor%' 
    OR LOWER(position) LIKE '%instructor%'
    OR LOWER(position) LIKE '%professor%'
    OR LOWER(position) LIKE '%teacher%'
    OR LOWER(role) LIKE '%lecturer%'
    OR LOWER(role) LIKE '%academic%'
"))['c'];
$non_academic_staff = $total_staff - $academic_staff;

// ============================================
// GET STUDENTS LIST
// ============================================
$filter_level = isset($_GET['filter_level']) ? mysqli_real_escape_string($conn, $_GET['filter_level']) : 'all';
$sql = ($filter_level != 'all') 
    ? "SELECT * FROM students WHERE level = '$filter_level' ORDER BY id DESC"
    : "SELECT * FROM students ORDER BY id DESC";
$students_result = mysqli_query($conn, $sql);
$staff_result = mysqli_query($conn, "SELECT * FROM staff ORDER BY id DESC");
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
        $student_id = intval($row['student_id'] ?? 0);
        $phone = $row['phone'] ?? '';
        $programme = $row['programme'] ?? 'NCE';
        $course = $row['course_applied'] ?? '';
        $branch_code = $row['branch_code'] ?? 'SHINGE';
        
        $admission_no = '';
        $level = '';
        $entry_year = '';
        $grad_year = '';
        
        if ($action == 'approved') {
            $programme_upper = strtoupper(trim($programme));
            
            if ($programme_upper == 'NCE') {
                $level = 'NCE I';
                $entry_year = '2026/2027';
                $grad_year = '2028/2029';
            } elseif ($programme_upper == 'DEGREE' || $programme_upper == 'DEG') {
                $level = '400 Level';
                $entry_year = '2026/2027';
                $grad_year = '2029/2030';
            } elseif ($programme_upper == 'ENTREPRENEURSHIP' || $programme_upper == 'ENT') {
                $level = 'ENTREPRENEURSHIP';
                $entry_year = '2026/2027';
                $grad_year = '2027/2028';
            } else {
                $level = 'NCE I';
                $entry_year = '2026/2027';
                $grad_year = '2028/2029';
            }
            
            $admission_no = generateAdmissionNumber($programme, $branch_code, $course, $level);
        }
        
        mysqli_query($conn, "UPDATE applications 
                             SET status = '$action', notes = '$comment', 
                                 reviewed_by = '$admin_name (Admin)', reviewed_at = NOW() 
                             WHERE id = '$app_id'");
        
        if ($action == 'approved') {
            $updated = false;
            
            if ($student_id > 0) {
                $update_student = "UPDATE students 
                                   SET status = 'active', 
                                       reg_no = '$admission_no', 
                                       student_id = '$admission_no', 
                                       combination = '$course', 
                                       level = '$level', 
                                       entry_year = '$entry_year', 
                                       graduation_year = '$grad_year' 
                                   WHERE id = $student_id";
                if (mysqli_query($conn, $update_student)) {
                    $updated = mysqli_affected_rows($conn) > 0;
                }
            }
            
            if (!$updated && !empty($phone)) {
                $update_student = "UPDATE students 
                                   SET status = 'active', 
                                       reg_no = '$admission_no', 
                                       student_id = '$admission_no', 
                                       combination = '$course', 
                                       level = '$level', 
                                       entry_year = '$entry_year', 
                                       graduation_year = '$grad_year' 
                                   WHERE phone = '$phone'";
                if (mysqli_query($conn, $update_student)) {
                    $updated = mysqli_affected_rows($conn) > 0;
                }
            }
            
            if ($updated) {
                $success = "✅ Application approved!<br>🎓 Reg No: <strong>$admission_no</strong><br>📚 Level: <strong>$level</strong>";
            } else {
                $error = "❌ An sabunta application amma ba a sami ɗalibin ba. Phone: $phone";
            }
        } else {
            if ($student_id > 0) {
                mysqli_query($conn, "UPDATE students SET status = 'rejected' WHERE id = $student_id");
            } elseif (!empty($phone)) {
                mysqli_query($conn, "UPDATE students SET status = 'rejected' WHERE phone = '$phone'");
            }
            $success = "✅ Application rejected.";
        }
    }
}

// ============================================
// EDIT STUDENT
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_student'])) {
    $sid = intval($_POST['student_id']);
    $fullname = mysqli_real_escape_string($conn, trim($_POST['fullname']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $programme = mysqli_real_escape_string($conn, $_POST['programme']);
    $course = mysqli_real_escape_string($conn, $_POST['course']);
    $level = mysqli_real_escape_string($conn, $_POST['level']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $branch_code = mysqli_real_escape_string($conn, $_POST['branch_code']);
    
    $old_query = mysqli_query($conn, "SELECT fullname FROM students WHERE id = $sid");
    $old_row = mysqli_fetch_assoc($old_query);
    $old_name = $old_row['fullname'] ?? '';
    
    $year_info = getYearInfo($level);
    
    $update = "UPDATE students SET 
                fullname = '$fullname', email = '$email', phone = '$phone', 
                programme = '$programme', course = '$course', level = '$level', 
                entry_year = '{$year_info['entry']}', graduation_year = '{$year_info['grad']}',
                status = '$status', branch_code = '$branch_code' 
               WHERE id = $sid";
    
    if (mysqli_query($conn, $update)) {
        if ($old_name !== $fullname && !empty($old_name)) {
            $admin_id = intval($_SESSION['user_id']);
            $old_name_esc = mysqli_real_escape_string($conn, $old_name);
            $fullname_esc = mysqli_real_escape_string($conn, $fullname);
            mysqli_query($conn, "INSERT INTO name_change_logs (student_id, old_name, new_name, changed_by) 
                        VALUES ($sid, '$old_name_esc', '$fullname_esc', $admin_id)");
        }
        $edit_success = "✅ Student updated!";
    } else {
        $edit_error = "❌ Error: " . mysqli_error($conn);
    }
}

// ============================================
// DOWNLOAD TEMPLATE CSV
// ============================================
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="student_import_template.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['S/N', 'REG NO (Optional)', 'FULL NAME', 'EMAIL', 'PHONE', 'PROGRAMME', 'DEPARTMENT/COURSE', 'LEVEL', 'STATUS', 'STUDY CENTRE', 'PASSWORD']);
    fputcsv($output, ['1', '', 'Amina Ibrahim', 'amina.ibrahim@email.com', '08012345678', 'NCE', 'CSC/ISC', 'NCE I', 'active', 'SHINGE', 'student123']);
    fputcsv($output, ['2', '', 'Musa Ahmed', 'musa.ahmed@email.com', '08087654321', 'DEGREE', 'BSC_CSC', '400 Level', 'active', 'SABUWA', 'student123']);
    fputcsv($output, ['3', '', 'Fatima Sani', 'fatima.sani@email.com', '08055555555', 'NCE', 'ENG/SOS', 'NCE III', 'active', 'TUDUN', 'student123']);
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
    fputcsv($output, ['S/N', 'REG NO', 'FULL NAME', 'EMAIL', 'PHONE', 'GENDER', 'PROGRAMME', 'COURSE', 'LEVEL', 'ENTRY YEAR', 'GRAD YEAR', 'STATUS', 'STUDY CENTRE', 'PASSWORD']);
    $export_query = mysqli_query($conn, "SELECT * FROM students ORDER BY id ASC");
    if ($export_query) {
        $sn = 1;
        while ($row = mysqli_fetch_assoc($export_query)) {
            fputcsv($output, [$sn++, $row['reg_no'] ?? '', $row['fullname'] ?? '', $row['email'] ?? '', $row['phone'] ?? '', $row['gender'] ?? '', $row['programme'] ?? 'NCE', $row['course'] ?? '', $row['level'] ?? 'NCE I', $row['entry_year'] ?? '', $row['graduation_year'] ?? '', $row['status'] ?? 'pending', $row['branch_code'] ?? 'SHINGE', 'student123']);
        }
    }
    fclose($output);
    exit();
}

// ============================================
// EXPORT STUDENTS WITH USERNAME + PASSWORD
// ============================================
if (isset($_GET['export_students_with_password'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="students_with_password_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['S/N', 'REG NO', 'FULL NAME', 'EMAIL', 'PHONE', 'USERNAME', 'PASSWORD', 'PROGRAMME', 'COURSE', 'LEVEL', 'STATUS', 'STUDY CENTRE']);
    $export_query = mysqli_query($conn, "SELECT * FROM students ORDER BY id ASC");
    if ($export_query) {
        $sn = 1;
        while ($row = mysqli_fetch_assoc($export_query)) {
            fputcsv($output, [
                $sn++, $row['reg_no'] ?? '', $row['fullname'] ?? '', $row['email'] ?? '', $row['phone'] ?? '',
                $row['username'] ?? '', 'student123', $row['programme'] ?? 'NCE', $row['course'] ?? '',
                $row['level'] ?? 'NCE I', $row['status'] ?? 'pending', $row['branch_code'] ?? 'SHINGE'
            ]);
        }
    }
    fclose($output);
    exit();
}

// ============================================
// DOWNLOAD T.P INTRODUCTORY LETTER LIST (GYARA)
// Ɗaliban da suka riga sun download introductory letter
// ============================================
if (isset($_GET['download_intro_list'])) {
    $intro_level = mysqli_real_escape_string($conn, $_GET['intro_level'] ?? 'NCE III');
    
    // Nemo ɗaliban da suka riga sun download introductory letter
    $intro_query = mysqli_query($conn, "
        SELECT DISTINCT 
            s.id, s.reg_no, s.student_id, s.fullname, s.programme, 
            s.combination, s.course, s.level, s.branch_code,
            s.email, s.phone,
            dl.downloaded_at AS downloaded_date,
            dl.action_type
        FROM students s
        INNER JOIN download_logs dl ON s.id = dl.student_id
        WHERE s.level = '$intro_level' 
        AND dl.document_type = 'introductory_letter'
        AND s.status NOT IN ('rejected', 'inactive')
        GROUP BY s.id
        ORDER BY s.fullname ASC
    ");
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tp_introductory_list_' . $intro_level . '_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    
    // Header
    fputcsv($output, ['DALA COLLEGE OF EDUCATION, KANO']);
    fputcsv($output, ['T.P INTRODUCTORY LETTER LIST']);
    fputcsv($output, ['Level:', $intro_level]);
    fputcsv($output, ['Generated:', date('F j, Y g:i A')]);
    fputcsv($output, []);
    
    fputcsv($output, [
        'S/N', 
        'REG NO', 
        'FULL NAME', 
        'PROGRAMME', 
        'COMBINATION', 
        'LEVEL', 
        'STUDY CENTRE', 
        'EMAIL', 
        'PHONE',
        'DOWNLOADED DATE',
        'ACTION'
    ]);
    
    $sn = 1;
    if ($intro_query && mysqli_num_rows($intro_query) > 0) {
        while ($row = mysqli_fetch_assoc($intro_query)) {
            fputcsv($output, [
                $sn++,
                $row['reg_no'] ?? $row['student_id'] ?? '—',
                strtoupper($row['fullname'] ?? ''),
                $row['programme'] ?? 'NCE',
                $row['combination'] ?? $row['course'] ?? '—',
                $row['level'] ?? '—',
                $row['branch_code'] ?? '—',
                $row['email'] ?? '—',
                $row['phone'] ?? '—',
                $row['downloaded_date'] ? date('d-M-Y g:i A', strtotime($row['downloaded_date'])) : '—',
                ucfirst($row['action_type'] ?? '—')
            ]);
        }
    }
    fclose($output);
    exit();
}

// ============================================
// DOWNLOAD T.P POSTING LIST (GYARA)
// ============================================
if (isset($_GET['download_posting_list'])) {
    $post_level = mysqli_real_escape_string($conn, $_GET['post_level'] ?? 'NCE III');
    $post_session = mysqli_real_escape_string($conn, $_GET['post_session'] ?? date('Y') . '/' . (date('Y') + 1));
    
    $post_query = mysqli_query($conn, "SELECT 
                                        s.id, s.reg_no, s.student_id, s.fullname, s.programme, 
                                        s.combination, s.course, s.level, s.branch_code,
                                        s.phone, s.email,
                                        t.school_name, t.supervisor_name, 
                                        t.start_date, t.end_date,
                                        t.teaching_score, t.lesson_note_score, 
                                        t.punctuality_score, t.relationship_score,
                                        t.total_score, t.grade, t.remark,
                                        t.academic_year AS tp_session
                                        FROM students s 
                                        LEFT JOIN tp_results t ON s.id = t.student_id AND t.academic_year = '$post_session'
                                        WHERE s.level = '$post_level' 
                                        AND s.status NOT IN ('rejected', 'inactive')
                                        ORDER BY s.fullname ASC");
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tp_posting_list_' . $post_level . '_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    
    // Header
    fputcsv($output, ['DALA COLLEGE OF EDUCATION, KANO']);
    fputcsv($output, ['T.P POSTING LIST']);
    fputcsv($output, ['Level:', $post_level]);
    fputcsv($output, ['Session:', $post_session]);
    fputcsv($output, ['Generated:', date('F j, Y g:i A')]);
    fputcsv($output, []);
    
    fputcsv($output, [
        'S/N', 
        'REG NO', 
        'FULL NAME', 
        'PROGRAMME', 
        'COMBINATION', 
        'LEVEL', 
        'STUDY CENTRE',
        'PHONE',
        'T.P SCHOOL', 
        'SUPERVISOR', 
        'START DATE', 
        'END DATE',
        'TEACHING (40)', 
        'LESSON NOTE (20)', 
        'PUNCTUALITY (20)', 
        'RELATIONSHIP (20)', 
        'TOTAL (100)', 
        'GRADE', 
        'REMARK'
    ]);
    
    $sn = 1;
    if ($post_query && mysqli_num_rows($post_query) > 0) {
        while ($row = mysqli_fetch_assoc($post_query)) {
            fputcsv($output, [
                $sn++,
                $row['reg_no'] ?? $row['student_id'] ?? '—',
                strtoupper($row['fullname'] ?? ''),
                $row['programme'] ?? 'NCE',
                $row['combination'] ?? $row['course'] ?? '—',
                $row['level'] ?? '—',
                $row['branch_code'] ?? '—',
                $row['phone'] ?? '—',
                $row['school_name'] ?? '—',
                $row['supervisor_name'] ?? '—',
                $row['start_date'] ? date('d-M-Y', strtotime($row['start_date'])) : '—',
                $row['end_date'] ? date('d-M-Y', strtotime($row['end_date'])) : '—',
                $row['teaching_score'] ?? '—',
                $row['lesson_note_score'] ?? '—',
                $row['punctuality_score'] ?? '—',
                $row['relationship_score'] ?? '—',
                $row['total_score'] ?? '—',
                $row['grade'] ?? '—',
                $row['remark'] ?? '—'
            ]);
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
    $gender = mysqli_real_escape_string($conn, $_POST['gender'] ?? '');
    
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
        if (empty($reg_no)) $reg_no = generateAdmissionNumber($programme, $branch_code, $course, $level);
        
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
        $year_info = getYearInfo($level);
        
        // ============================================
        // ⚠️ MUHIMMANCI: student_id = reg_no (ba a ƙirƙira daban ba)
        // ============================================
        $insert = "INSERT INTO students (reg_no, student_id, username, password, fullname, email, phone, gender, programme, course, combination, level, entry_year, graduation_year, status, branch_code, created_at) 
                   VALUES ('$reg_no', '$reg_no', '$username', '$password', '$fullname', '$email', '$phone', '$gender', '$programme', '$course', '$course', '$level', '{$year_info['entry']}', '{$year_info['grad']}', '$status', '$branch_code', NOW())";
        
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
// ADD STAFF
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_staff'])) {
    $fullname = mysqli_real_escape_string($conn, trim($_POST['staff_fullname']));
    $email = mysqli_real_escape_string($conn, trim($_POST['staff_email']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['staff_phone']));
    $username = mysqli_real_escape_string($conn, trim($_POST['staff_username']));
    $password = $_POST['staff_password'] ?? 'staff123';
    $role = mysqli_real_escape_string($conn, $_POST['staff_role']);
    $position = mysqli_real_escape_string($conn, $_POST['staff_position']);
    $department = mysqli_real_escape_string($conn, trim($_POST['staff_department'] ?? ''));
    $gender = mysqli_real_escape_string($conn, $_POST['staff_gender'] ?? '');
    $branch_code = mysqli_real_escape_string($conn, $_POST['staff_branch'] ?? 'SHINGE');
    $can_accept = mysqli_real_escape_string($conn, $_POST['staff_can_accept'] ?? 'no');
    $date_joined = mysqli_real_escape_string($conn, $_POST['staff_date_joined'] ?? date('Y-m-d'));
    
    $errors = [];
    
    if (empty($fullname)) $errors[] = 'Full name is required.';
    if (empty($email)) $errors[] = 'Email is required.';
    if (empty($phone)) $errors[] = 'Phone is required.';
    if (empty($username)) $errors[] = 'Username is required.';
    if (empty($role)) $errors[] = 'Role is required.';
    if (empty($position)) $errors[] = 'Position is required.';
    
    if (!empty($username)) {
        $check = mysqli_query($conn, "SELECT id FROM staff WHERE username = '$username'");
        if ($check && mysqli_num_rows($check) > 0) $errors[] = 'Username already exists.';
    }
    if (!empty($email)) {
        $check = mysqli_query($conn, "SELECT id FROM staff WHERE email = '$email'");
        if ($check && mysqli_num_rows($check) > 0) $errors[] = 'Email already exists.';
    }
    
    if (empty($errors)) {
        $staff_id_query = mysqli_query($conn, "SELECT MAX(CAST(SUBSTRING(staff_id, 5) AS UNSIGNED)) as max_id FROM staff WHERE staff_id LIKE 'STF/%'");
        $next_id = 1;
        if ($staff_id_query) {
            $row = mysqli_fetch_assoc($staff_id_query);
            $next_id = intval($row['max_id']) + 1;
        }
        $staff_id = 'STF/' . str_pad($next_id, 3, '0', STR_PAD_LEFT);
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $insert = "INSERT INTO staff (
            staff_id, username, password, fullname, email, phone, 
            role, position, department, gender, branch_code, 
            can_accept, date_joined, status, created_at
        ) VALUES (
            '$staff_id', '$username', '$hashed_password', '$fullname', '$email', '$phone',
            '$role', '$position', '$department', '$gender', '$branch_code',
            '$can_accept', '$date_joined', 'active', NOW()
        )";
        
        if (mysqli_query($conn, $insert)) {
            $staff_success = "✅ Staff added successfully!<br>Staff ID: <strong>$staff_id</strong>";
        } else {
            $staff_error = "❌ Error: " . mysqli_error($conn);
        }
    } else {
        $staff_error = implode('<br>', $errors);
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
    $position = mysqli_real_escape_string($conn, trim($_POST['position'] ?? ''));
    if (mysqli_query($conn, "UPDATE staff SET fullname='$fullname', email='$email', phone='$phone', username='$username', role='$role', position='$position' WHERE id=$sid")) {
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
            
            $imported = 0; 
            $updated = 0; 
            $skipped = 0; 
            $errors = []; 
            $row_num = 1;
            
            while (($row = fgetcsv($file)) !== FALSE) {
                $row_num++;
                if (empty(array_filter($row))) { 
                    $skipped++; 
                    continue; 
                }
                
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
                
                if (empty($fullname)) { 
                    $errors[] = "Row $row_num: Missing name"; 
                    $skipped++; 
                    continue; 
                }
                
                if (empty($email)) {
                    $parts = explode(' ', strtolower(trim($fullname)));
                    $fn = preg_replace('/[^a-z]/', '', $parts[0] ?? 'student');
                    $ln = isset($parts[count($parts)-1]) ? preg_replace('/[^a-z]/', '', $parts[count($parts)-1]) : '';
                    $email = $fn . '.' . $ln . $row_num . '@student.dalacoe.edu.ng';
                }
                
                if (empty($reg_no)) {
                    $reg_no = generateAdmissionNumber($programme, $branch_code, $course, $level);
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
                
                $check = mysqli_query($conn, "SELECT id FROM students WHERE reg_no = '$reg_no' OR email = '$email' OR phone = '$phone'");
                $hashed = password_hash($password_plain, PASSWORD_DEFAULT);
                $year_info = getYearInfo($level);
                
                if ($check && mysqli_num_rows($check) > 0) {
                    $update_sql = "UPDATE students SET 
                                    fullname='$fullname', 
                                    email='$email', 
                                    phone='$phone', 
                                    programme='$programme', 
                                    course='$course', 
                                    combination='$course', 
                                    level='$level', 
                                    entry_year='{$year_info['entry']}', 
                                    graduation_year='{$year_info['grad']}', 
                                    status='$status', 
                                    branch_code='$branch_code', 
                                    password='$hashed', 
                                    username='$username' 
                                   WHERE reg_no='$reg_no' OR email='$email' OR phone='$phone'";
                    
                    if (mysqli_query($conn, $update_sql)) {
                        $updated++;
                    } else {
                        $errors[] = "Row $row_num: " . mysqli_error($conn);
                        $skipped++;
                    }
                } else {
                    $insert_sql = "INSERT INTO students 
                                    (reg_no, student_id, username, password, fullname, email, phone, programme, course, combination, level, entry_year, graduation_year, status, branch_code, created_at) 
                                   VALUES 
                                    ('$reg_no', '$reg_no', '$username', '$hashed', '$fullname', '$email', '$phone', '$programme', '$course', '$course', '$level', '{$year_info['entry']}', '{$year_info['grad']}', '$status', '$branch_code', NOW())";
                    
                    if (mysqli_query($conn, $insert_sql)) {
                        $imported++;
                    } else {
                        $error_msg = mysqli_error($conn);
                        $update_sql = "UPDATE students SET 
                                        fullname='$fullname', 
                                        email='$email', 
                                        phone='$phone', 
                                        programme='$programme', 
                                        course='$course', 
                                        combination='$course', 
                                        level='$level', 
                                        entry_year='{$year_info['entry']}', 
                                        graduation_year='{$year_info['grad']}', 
                                        status='$status', 
                                        branch_code='$branch_code', 
                                        password='$hashed', 
                                        username='$username' 
                                       WHERE phone='$phone' OR email='$email' OR reg_no='$reg_no'";
                        
                        if (mysqli_query($conn, $update_sql)) {
                            $updated++;
                        } else {
                            $errors[] = "Row $row_num: $error_msg";
                            $skipped++;
                        }
                    }
                }
            }
            fclose($file);
            
            $msg = [];
            if ($imported > 0) $msg[] = "✅ <strong>$imported</strong> new students imported";
            if ($updated > 0) $msg[] = "🔄 <strong>$updated</strong> students updated";
            if ($skipped > 0) $msg[] = "⚠️ <strong>$skipped</strong> rows skipped";
            
            if (!empty($errors)) {
                $msg[] = "<br><small style='color:#c62828;'>" . implode("<br>", array_slice($errors, 0, 5)) . "</small>";
            }
            
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
// DELETE STUDENT / STATUS
// ============================================
if (isset($_GET['delete_student']) && is_numeric($_GET['delete_student'])) {
    mysqli_query($conn, "DELETE FROM students WHERE id=" . intval($_GET['delete_student']));
    header('Location: admin_dashboard.php'); exit();
}
if (isset($_GET['change_student_status']) && is_numeric($_GET['change_student_status'])) {
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    $id = intval($_GET['change_student_status']);
    if ($status == 'approved') $status = 'active';
    mysqli_query($conn, "UPDATE students SET status='$status' WHERE id='$id'");
    header('Location: admin_dashboard.php'); exit();
}

// ============================================
// SEARCH STUDENT (KYAUTA)
// ============================================
$search_results = null;
$search_count = 0;
if (isset($_GET['search']) || isset($_GET['search_level']) || isset($_GET['search_status'])) {
    $search = mysqli_real_escape_string($conn, trim($_GET['search'] ?? ''));
    $s_level = mysqli_real_escape_string($conn, $_GET['search_level'] ?? '');
    $s_status = mysqli_real_escape_string($conn, $_GET['search_status'] ?? '');
    
    $where = [];
    
    if (!empty($search)) {
        $where[] = "(fullname LIKE '%$search%' 
                     OR reg_no LIKE '%$search%' 
                     OR student_id LIKE '%$search%' 
                     OR email LIKE '%$search%' 
                     OR phone LIKE '%$search%' 
                     OR username LIKE '%$search%')";
    }
    
    if (!empty($s_level)) {
        $level_variants = [
            'NCE I' => ['NCE I', 'NCEI', 'NCE 1'],
            'NCE II' => ['NCE II', 'NCEII', 'NCE 2'],
            'NCE III' => ['NCE III', 'NCEIII', 'NCE 3'],
            '400 Level' => ['400 Level', '400L', '400'],
            '500 Level' => ['500 Level', '500L', '500']
        ];
        $variants = $level_variants[$s_level] ?? [$s_level];
        $level_conditions = [];
        foreach ($variants as $v) {
            $v_esc = mysqli_real_escape_string($conn, $v);
            $level_conditions[] = "level = '$v_esc'";
        }
        $where[] = "(" . implode(' OR ', $level_conditions) . ")";
    }
    
    if (!empty($s_status)) {
        $where[] = "status = '$s_status'";
    }
    
    $where_sql = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";
    $search_results = mysqli_query($conn, "SELECT * FROM students $where_sql ORDER BY id DESC LIMIT 50");
    
    if (!$search_results) {
        $search_error = "❌ SQL Error: " . mysqli_error($conn);
        $search_count = 0;
    } else {
        $search_count = mysqli_num_rows($search_results);
    }
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
        .container { max-width: 1500px; margin: 0 auto; }
        .topbar { background: #0d2818; color: white; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; border-radius: 12px; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .topbar h1 { margin: 0; font-size: 1.5rem; }
        .topbar .logout { background: #c62828; color: white; padding: 8px 15px; border-radius: 6px; text-decoration: none; font-weight: bold; }
        .topbar .logout:hover { background: #b71c1c; }
        .welcome-banner { background: linear-gradient(135deg, #1b5e20, #2e7d32, #43a047); color: white; padding: 30px 35px; border-radius: 16px; margin-bottom: 25px; box-shadow: 0 8px 25px rgba(46, 125, 50, 0.3); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .welcome-banner h2 { font-size: 1.8rem; margin-bottom: 5px; }
        .welcome-banner h2 span { color: #ffd54f; }
        .welcome-banner p { font-size: 0.95rem; opacity: 0.9; }
        .welcome-banner .date { background: rgba(255,255,255,0.15); padding: 8px 20px; border-radius: 30px; font-size: 0.85rem; border: 1px solid rgba(255,255,255,0.2); }
        .welcome-banner .quick-cards { display: flex; gap: 10px; flex-wrap: wrap; }
        .welcome-banner .quick-cards a { background: white; color: #0d2818; padding: 12px 20px; border-radius: 10px; text-decoration: none; text-align: center; min-width: 140px; transition: all 0.3s ease; display: block; }
        .welcome-banner .quick-cards a:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.2); }
        .welcome-banner .quick-cards a i { font-size: 1.5rem; display: block; margin-bottom: 5px; }
        .welcome-banner .quick-cards a strong { display: block; font-size: 0.9rem; }
        .welcome-banner .quick-cards a small { color: #6a8f6a; font-size: 0.7rem; }
        .welcome-banner .quick-cards .c-courses i { color: #2e7d32; }
        .welcome-banner .quick-cards .c-results i { color: #7b1fa2; }
        
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; margin-bottom: 25px; }
        .stat-card { background: white; padding: 18px 15px; border-radius: 12px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 4px solid #2e7d32; transition: all 0.3s ease; text-decoration: none; color: inherit; display: block; }
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
        .stat-card.degree400 { border-left-color: #6a1b9a; }
        .stat-card.degree400 .number { color: #6a1b9a; }
        .stat-card.degree500 { border-left-color: #4527a0; }
        .stat-card.degree500 .number { color: #4527a0; }
        .stat-card.branch-a { border-left-color: #1b5e20; }
        .stat-card.branch-a .number { color: #1b5e20; }
        .stat-card.branch-b { border-left-color: #0d47a1; }
        .stat-card.branch-b .number { color: #0d47a1; }
        .stat-card.branch-c { border-left-color: #e65100; }
        .stat-card.branch-c .number { color: #e65100; }
        .stat-card.male { border-left-color: #1976d2; }
        .stat-card.male .number { color: #1976d2; }
        .stat-card.female { border-left-color: #c2185b; }
        .stat-card.female .number { color: #c2185b; }
        .stat-card.academic { border-left-color: #6a1b9a; }
        .stat-card.academic .number { color: #6a1b9a; }
        .stat-card.non-academic { border-left-color: #e65100; }
        .stat-card.non-academic .number { color: #e65100; }
        
        .alert { padding: 12px 15px; border-radius: 8px; font-weight: 600; margin-bottom: 15px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }
        .alert-error { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }
        
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .card h2 { color: #0d2818; margin-bottom: 20px; font-size: 1.2rem; }
        .card-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; }
        .card-header h2 { margin-bottom: 0; }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 700; margin-bottom: 5px; font-size: 14px; color: #0d2818; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 2px solid #dce8dc; border-radius: 8px; font-size: 14px; }
        .form-group input:focus, .form-group select:focus { border-color: #2e7d32; outline: none; }
        .form-group .hint { font-size: 0.75rem; color: #6a8f6a; margin-top: 3px; }
        
        .btn { padding: 10px 20px; border-radius: 8px; font-weight: 700; font-size: 14px; cursor: pointer; border: none; display: inline-block; text-decoration: none; transition: all 0.3s ease; }
        .btn-green { background: #2e7d32; color: white; }
        .btn-green:hover { background: #1b5e20; transform: translateY(-2px); }
        .btn-blue { background: #1976d2; color: white; }
        .btn-blue:hover { background: #0d47a1; transform: translateY(-2px); }
        .btn-red { background: #c62828; color: white; }
        .btn-orange { background: #ffa000; color: white; }
        .btn-purple { background: #7b1fa2; color: white; }
        
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
        .badge-400level { background: #f3e5f5; color: #6a1b9a; }
        .badge-500level { background: #ede7f6; color: #4527a0; }
        
        .status-badge { display:inline-block; padding:4px 14px; border-radius:20px; font-size:0.75rem; font-weight:600; }
        .status-badge.pending { background:#fff8e1; color:#ffa000; }
        .status-badge.approved { background:#e8f5e9; color:#2e7d32; }
        .status-badge.rejected { background:#ffebee; color:#c62828; }
        
        .admission-no { font-weight:700; color:#0d2818; font-size:0.75rem; background:#e8f5e9; padding:2px 10px; border-radius:12px; }
        .username-badge { font-weight:600; color:#7b1fa2; font-size:0.75rem; background:#f3e5f5; padding:2px 8px; border-radius:8px; font-family:monospace; }
        
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
        .stats-btn { background: #e65100; }
        
        .action-form { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
        .action-form input[type="text"] { padding:6px 10px; border:1px solid #dce8dc; border-radius:6px; font-size:0.78rem; min-width:100px; }
        .action-form .btn-accept { padding:6px 16px; background:#2e7d32; color:white; border:none; border-radius:6px; font-size:0.75rem; font-weight:600; cursor:pointer; }
        .action-form .btn-reject { padding:6px 16px; background:#c62828; color:white; border:none; border-radius:6px; font-size:0.75rem; font-weight:600; cursor:pointer; }
        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto; padding: 30px; }
        .modal-content { max-width: 600px; margin: 30px auto; background: white; padding: 35px; border-radius: 16px; position: relative; }
        .modal-close { position: absolute; top: 15px; right: 20px; font-size: 2rem; background: none; border: none; cursor: pointer; color: #c62828; }
        
        .import-area { background: #f8faf8; padding: 25px; border-radius: 10px; border: 2px dashed #2e7d32; text-align: center; }
        .import-area .icon { font-size: 2.5rem; margin-bottom: 10px; }
        .import-area input[type="file"] { padding: 10px; border: 1px solid #dce8dc; border-radius: 8px; background: white; width: 100%; max-width: 400px; margin: 10px auto; }
        .import-area .btn-import { padding: 10px 30px; background: #2e7d32; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        
        @media (max-width: 1200px) {
            .stats { grid-template-columns: repeat(4, 1fr); }
        }
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .form-row-3 { grid-template-columns: 1fr; }
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

    <!-- STATS CARDS -->
    <div class="stats">
        <a href="view_applications.php?status=pending" class="stat-card pending">
            <div class="number"><?php echo $total_pending; ?></div>
            <div class="label">📌 Pending</div>
        </a>
        <a href="view_applications.php?status=approved" class="stat-card">
            <div class="number"><?php echo $total_accepted; ?></div>
            <div class="label">✅ Accepted</div>
        </a>
        <a href="view_applications.php?status=rejected" class="stat-card rejected">
            <div class="number"><?php echo $total_rejected; ?></div>
            <div class="label">❌ Rejected</div>
        </a>
        <a href="view_students.php" class="stat-card">
            <div class="number"><?php echo $total_students; ?></div>
            <div class="label">🎓 Total Students</div>
        </a>
        <a href="view_students.php?level=NCE III" class="stat-card nce3">
            <div class="number"><?php echo $nce3_count; ?></div>
            <div class="label">🏆 NCE III</div>
        </a>
        <a href="view_students.php?level=NCE II" class="stat-card nce2">
            <div class="number"><?php echo $nce2_count; ?></div>
            <div class="label">📘 NCE II</div>
        </a>
        <a href="view_students.php?level=NCE I" class="stat-card nce1">
            <div class="number"><?php echo $nce1_count; ?></div>
            <div class="label">📗 NCE I</div>
        </a>
        <a href="view_students.php?level=400 Level" class="stat-card degree400">
            <div class="number"><?php echo $degree_400; ?></div>
            <div class="label">🎓 400 Level</div>
        </a>
        <a href="view_students.php?level=500 Level" class="stat-card degree500">
            <div class="number"><?php echo $degree_500; ?></div>
            <div class="label">🎓 500 Level</div>
        </a>
        <a href="view_staff.php" class="stat-card">
            <div class="number"><?php echo $total_staff; ?></div>
            <div class="label">👥 Total Staff</div>
        </a>
        <a href="view_students.php?branch=SHINGE" class="stat-card branch-a">
            <div class="number"><?php echo $branch_a; ?></div>
            <div class="label">⭐ A - Shinge</div>
        </a>
        <a href="view_students.php?branch=SABUWA" class="stat-card branch-b">
            <div class="number"><?php echo $branch_b; ?></div>
            <div class="label">⭐ B - Sabuwar Kofa</div>
        </a>
        <a href="view_students.php?branch=TUDUN" class="stat-card branch-c">
            <div class="number"><?php echo $branch_c; ?></div>
            <div class="label">⭐ C - Tudun Yola</div>
        </a>
    </div>

    <!-- GENDER + STAFF STATS CARDS -->
    <h3 style="color:#0d2818; margin-bottom:15px; font-size:1.1rem;">
        <i class="fas fa-venus-mars" style="color:#1976d2;"></i> Gender & Staff Statistics
    </h3>
    
    <div class="stats">
        <a href="view_students.php?gender=Male" class="stat-card male">
            <div class="number"><?php echo $male_total; ?></div>
            <div class="label">👨 Total Male</div>
        </a>
        <a href="view_students.php?gender=Female" class="stat-card female">
            <div class="number"><?php echo $female_total; ?></div>
            <div class="label">👩 Total Female</div>
        </a>
        <a href="view_staff.php?type=academic" class="stat-card academic">
            <div class="number"><?php echo $academic_staff; ?></div>
            <div class="label">📚 Academic Staff</div>
        </a>
        <a href="view_staff.php?type=non_academic" class="stat-card non-academic">
            <div class="number"><?php echo $non_academic_staff; ?></div>
            <div class="label">🛠️ Non-Academic Staff</div>
        </a>
        <a href="view_students.php?gender=Male&branch=SHINGE" class="stat-card male">
            <div class="number"><?php echo $male_branch_a; ?></div>
            <div class="label">👨 A-Shinge (M)</div>
        </a>
        <a href="view_students.php?gender=Female&branch=SHINGE" class="stat-card female">
            <div class="number"><?php echo $female_branch_a; ?></div>
            <div class="label">👩 A-Shinge (F)</div>
        </a>
        <a href="view_students.php?gender=Male&branch=SABUWA" class="stat-card male">
            <div class="number"><?php echo $male_branch_b; ?></div>
            <div class="label">👨 B-Sabuwa (M)</div>
        </a>
        <a href="view_students.php?gender=Female&branch=SABUWA" class="stat-card female">
            <div class="number"><?php echo $female_branch_b; ?></div>
            <div class="label">👩 B-Sabuwa (F)</div>
        </a>
        <a href="view_students.php?gender=Male&branch=TUDUN" class="stat-card male">
            <div class="number"><?php echo $male_branch_c; ?></div>
            <div class="label">👨 C-Tudun (M)</div>
        </a>
        <a href="view_students.php?gender=Female&branch=TUDUN" class="stat-card female">
            <div class="number"><?php echo $female_branch_c; ?></div>
            <div class="label">👩 C-Tudun (F)</div>
        </a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if (isset($edit_success)): ?>
        <div class="alert alert-success"><?php echo $edit_success; ?></div>
    <?php endif; ?>
    <?php if (isset($staff_success)): ?>
        <div class="alert alert-success"><?php echo $staff_success; ?></div>
    <?php endif; ?>
    <?php if (isset($staff_error)): ?>
        <div class="alert alert-error"><?php echo $staff_error; ?></div>
    <?php endif; ?>

    <!-- DOWNLOAD CENTER -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-file-download" style="color:#e65100;"></i> Download Center</h2>
            <span style="color:#6a8f6a; font-size:0.9rem;">Zaɓi abin da kake so ka sauke</span>
        </div>
        
        <h3 style="color:#0d2818; margin-bottom:12px; font-size:1rem;"><i class="fas fa-users" style="color:#2e7d32;"></i> Student Lists</h3>
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px; margin-bottom:25px;">
            <a href="?download_template=1" style="text-decoration:none;">
                <div style="background:#e3f2fd; border:2px solid #1976d2; border-radius:12px; padding:20px; text-align:center; transition:all 0.3s ease;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                    <i class="fas fa-file-csv" style="font-size:2.5rem; color:#1976d2; display:block; margin-bottom:10px;"></i>
                    <strong style="color:#0d2818; display:block; font-size:1rem;">Template (CSV)</strong>
                    <small style="color:#6a8f6a; font-size:0.75rem;">Samfurin shigar da ɗalibai</small>
                </div>
            </a>
            <a href="?export_students=1" style="text-decoration:none;">
                <div style="background:#e8f5e9; border:2px solid #2e7d32; border-radius:12px; padding:20px; text-align:center; transition:all 0.3s ease;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                    <i class="fas fa-users" style="font-size:2.5rem; color:#2e7d32; display:block; margin-bottom:10px;"></i>
                    <strong style="color:#0d2818; display:block; font-size:1rem;">All Students (CSV)</strong>
                    <small style="color:#6a8f6a; font-size:0.75rem;">Jerin duk ɗalibai</small>
                </div>
            </a>
            <a href="?export_students_with_password=1" style="text-decoration:none;">
                <div style="background:#fff3e0; border:2px solid #e65100; border-radius:12px; padding:20px; text-align:center; transition:all 0.3s ease;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                    <i class="fas fa-key" style="font-size:2.5rem; color:#e65100; display:block; margin-bottom:10px;"></i>
                    <strong style="color:#0d2818; display:block; font-size:1rem;">Students + Password</strong>
                    <small style="color:#6a8f6a; font-size:0.75rem;">Username da password</small>
                </div>
            </a>
        </div>
        
        <h3 style="color:#0d2818; margin-bottom:12px; font-size:1rem;"><i class="fas fa-chalkboard-teacher" style="color:#e65100;"></i> T.P Lists (Teaching Practice)</h3>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
            <a href="?download_intro_list=1&intro_level=NCE%20III" style="text-decoration:none;">
                <div style="background:#e3f2fd; border:2px solid #1976d2; border-radius:12px; padding:20px; text-align:center; transition:all 0.3s ease;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                    <i class="fas fa-envelope-open-text" style="font-size:2.5rem; color:#1976d2; display:block; margin-bottom:10px;"></i>
                    <strong style="color:#0d2818; display:block; font-size:1rem;">T.P Introductory Letter List</strong>
                    <small style="color:#6a8f6a; font-size:0.75rem;">Jerin ɗaliban da za a ba wa intro letter (NCE III)</small>
                </div>
            </a>
            <a href="?download_posting_list=1&post_level=NCE%20III" style="text-decoration:none;">
                <div style="background:#fff3e0; border:2px solid #e65100; border-radius:12px; padding:20px; text-align:center; transition:all 0.3s ease;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                    <i class="fas fa-map-marked-alt" style="font-size:2.5rem; color:#e65100; display:block; margin-bottom:10px;"></i>
                    <strong style="color:#0d2818; display:block; font-size:1rem;">T.P Posting List</strong>
                    <small style="color:#6a8f6a; font-size:0.75rem;">Jerin ɗaliban da aka tura wuraren T.P</small>
                </div>
            </a>
        </div>
    </div>

    <!-- SEARCH STUDENT (GYARA) -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-search" style="color:#1976d2;"></i> Search Student</h2>
            <span style="color:#6a8f6a; font-size:0.9rem;">Nemo ɗalibi da sauri</span>
        </div>
        
        <form method="GET" action="" style="background:#e3f2fd; padding:20px; border-radius:12px;">
            <div class="form-row" style="grid-template-columns: 2fr 1fr 1fr auto;">
                <div class="form-group">
                    <label>Search (Name, Reg No, Username, Email, Phone)</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" placeholder="Rubuta suna, reg no, username, email...">
                </div>
                <div class="form-group">
                    <label>Level</label>
                    <select name="search_level">
                        <option value="">-- All --</option>
                        <option value="NCE I" <?php echo (isset($_GET['search_level']) && $_GET['search_level'] == 'NCE I') ? 'selected' : ''; ?>>NCE I</option>
                        <option value="NCE II" <?php echo (isset($_GET['search_level']) && $_GET['search_level'] == 'NCE II') ? 'selected' : ''; ?>>NCE II</option>
                        <option value="NCE III" <?php echo (isset($_GET['search_level']) && $_GET['search_level'] == 'NCE III') ? 'selected' : ''; ?>>NCE III</option>
                        <option value="400 Level" <?php echo (isset($_GET['search_level']) && $_GET['search_level'] == '400 Level') ? 'selected' : ''; ?>>400 Level</option>
                        <option value="500 Level" <?php echo (isset($_GET['search_level']) && $_GET['search_level'] == '500 Level') ? 'selected' : ''; ?>>500 Level</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="search_status">
                        <option value="">-- All --</option>
                        <option value="active" <?php echo (isset($_GET['search_status']) && $_GET['search_status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="pending" <?php echo (isset($_GET['search_status']) && $_GET['search_status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo (isset($_GET['search_status']) && $_GET['search_status'] == 'approved') ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo (isset($_GET['search_status']) && $_GET['search_status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-blue" style="width:100%;">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </div>
        </form>
        
        <?php if (isset($search_results)): ?>
            <div style="margin-top:20px;">
                <h3 style="margin-bottom:10px; color:#0d2818;">Search Results (<?php echo $search_count; ?>)</h3>
                
                <?php if ($search_count > 0): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Reg No</th>
                                <th>Full Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Course</th>
                                <th>Level</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($search_results)): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['reg_no'] ?? '-'); ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($row['fullname']); ?></strong></td>
                                <td><span class="username-badge"><?php echo htmlspecialchars($row['username'] ?? '-'); ?></span></td>
                                <td><?php echo htmlspecialchars($row['email'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['phone'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['course'] ?? '-'); ?></td>
                                <td><span class="badge badge-<?php echo strtolower(str_replace(' ', '', $row['level'] ?? 'ncei')); ?>"><?php echo $row['level'] ?? 'NCE I'; ?></span></td>
                                <td><span class="badge badge-<?php echo $row['status'] ?? 'pending'; ?>"><?php echo ucfirst($row['status'] ?? 'Pending'); ?></span></td>
                                <td class="action-btns">
                                    <a href="#" class="view-btn" onclick="viewFullRecord(<?php echo $row['id']; ?>); return false;" title="View Full Record"><i class="fas fa-id-card"></i></a>
                                    <a href="#" class="stats-btn" onclick="viewDownloadStats(<?php echo $row['id']; ?>); return false;" title="Download Stats"><i class="fas fa-chart-bar"></i></a>
                                    <a href="#" class="edit-btn" onclick="editStudent(<?php echo $row['id']; ?>); return false;" title="Edit"><i class="fas fa-edit"></i></a>
                                    <a href="#" class="pass-btn" onclick="changeStudentPassword(<?php echo $row['id']; ?>); return false;" title="Password"><i class="fas fa-key"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div style="text-align:center; padding:30px; background:#fff3e0; border-radius:10px;">
                        <i class="fas fa-search" style="font-size:2rem; display:block; margin-bottom:10px; color:#e65100;"></i>
                        <p style="color:#e65100; font-weight:600;">Babu ɗalibin da ya dace da bincikenka.</p>
                        <p style="font-size:0.85rem; color:#6a8f6a;">Gwada da wani abu dabam ko ka share filters ɗin.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- IMPORT STUDENTS -->
    <div class="card">
        <h2>📂 Import Students (CSV)</h2>
        <?php if (isset($import_success)): ?><div class="alert alert-success"><?php echo $import_success; ?></div><?php endif; ?>
        <?php if (isset($import_error)): ?><div class="alert alert-error"><?php echo $import_error; ?></div><?php endif; ?>
        <div class="import-area">
            <div class="icon">📄</div>
            <p style="font-weight:600; margin-bottom:10px;">Upload CSV file with students</p>
            <p style="color:#6a8f6a; font-size:0.8rem; margin-bottom:10px;">Format: S/N, REG NO (Optional), FULL NAME, EMAIL, PHONE, PROGRAMME, DEPARTMENT/COURSE, LEVEL, STATUS, STUDY CENTRE, PASSWORD</p>
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
                <div class="form-group"><label>Gender</label>
                    <select name="gender">
                        <option value="">-- Select --</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Reg No (Optional)</label><input type="text" name="reg_no" placeholder="Auto generate"></div>
                <div class="form-group"><label>Programme *</label>
                    <select name="programme" id="programme" required onchange="updateCourses()">
                        <option value="">-- Select --</option>
                        <option value="NCE">NCE</option>
                        <option value="DEGREE">Degree</option>
                        <option value="ENTREPRENEURSHIP">Entrepreneurship</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Course *</label>
                    <select name="course" id="course" required>
                        <option value="">-- Select Programme First --</option>
                    </select>
                </div>
                <div class="form-group"><label>Level</label>
                    <select name="level" id="levelSelect">
                        <option value="NCE I">NCE I</option>
                        <option value="NCE II">NCE II</option>
                        <option value="NCE III">NCE III</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Status</label>
                    <select name="status">
                        <option value="pending">Pending</option>
                        <option value="active">Active</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="form-group"><label>Study Centre *</label>
                    <select name="branch_code" required>
                        <option value="">-- Select --</option>
                        <option value="SHINGE">Shinge (A)</option>
                        <option value="SABUWA">Sabuwa (B)</option>
                        <option value="TUDUN">Tudun (C)</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="add_student" class="btn btn-green">✅ Add Student</button>
        </form>
    </div>

    <!-- ADD STAFF -->
    <div class="card">
        <div class="card-header">
            <h2>➕ Add New Staff</h2>
            <span style="color:#6a8f6a; font-size:0.9rem;">Ƙara sabon ma'aikaci</span>
        </div>
        
        <?php if (isset($staff_success)): ?><div class="alert alert-success"><?php echo $staff_success; ?></div><?php endif; ?>
        <?php if (isset($staff_error)): ?><div class="alert alert-error"><?php echo $staff_error; ?></div><?php endif; ?>
        
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="staff_fullname" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="staff_email" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Phone *</label>
                    <input type="tel" name="staff_phone" required>
                </div>
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="staff_username" required placeholder="Don login">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Password *</label>
                    <input type="text" name="staff_password" value="staff123" required>
                    <div class="hint">Default: staff123</div>
                </div>
                <div class="form-group">
                    <label>Gender</label>
                    <select name="staff_gender">
                        <option value="">-- Select --</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Role *</label>
                    <select name="staff_role" required>
                        <option value="">-- Select Role --</option>
                        <option value="Provost">Provost</option>
                        <option value="Admission Officer">Admission Officer</option>
                        <option value="Exam Officer">Exam Officer</option>
                        <option value="Bursary">Bursary</option>
                        <option value="Accountant">Accountant</option>
                        <option value="Academic Staff">📚 Academic Staff</option>
                        <option value="Non-Academic Staff">🛠️ Non-Academic Staff</option>
                        <option value="Staff">Staff</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Position *</label>
                    <input type="text" name="staff_position" required placeholder="Misali: Lecturer, Tutor, Admin Officer...">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" name="staff_department" placeholder="Misali: Computer Science">
                </div>
                <div class="form-group">
                    <label>Branch</label>
                    <select name="staff_branch">
                        <option value="SHINGE">A - Shinge</option>
                        <option value="SABUWA">B - Sabuwar Kofa</option>
                        <option value="TUDUN">C - Tudun Yola</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Can Accept Applications?</label>
                    <select name="staff_can_accept">
                        <option value="no">No</option>
                        <option value="yes">Yes</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date Joined</label>
                    <input type="date" name="staff_date_joined" value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            
            <button type="submit" name="add_staff" class="btn btn-green">
                <i class="fas fa-user-plus"></i> Add Staff
            </button>
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
            <a href="?filter_level=400%20Level" class="btn btn-purple <?php echo ($filter_level == '400 Level') ? 'active' : ''; ?>">🎓 400 Level</a>
            <a href="?filter_level=500%20Level" class="btn btn-purple <?php echo ($filter_level == '500 Level') ? 'active' : ''; ?>" style="background:#4527a0;">🎓 500 Level</a>
        </div>
        
        <?php if (isset($pass_success)): ?><div class="alert alert-success"><?php echo $pass_success; ?></div><?php endif; ?>
        
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Reg No</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Gender</th>
                        <th>Course</th>
                        <th>Level</th>
                        <th>Entry Year</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($students_result && mysqli_num_rows($students_result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($students_result)): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['reg_no'] ?? '-'); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['fullname'] ?? ''); ?></td>
                            <td><span class="username-badge"><?php echo htmlspecialchars($row['username'] ?? '-'); ?></span></td>
                            <td><?php echo htmlspecialchars($row['gender'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($row['course'] ?? ''); ?></td>
                            <td>
                                <span class="badge badge-<?php echo strtolower(str_replace(' ', '', $row['level'] ?? 'ncei')); ?>">
                                    <?php echo $row['level'] ?? 'NCE I'; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($row['entry_year'] ?? '—'); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $row['status'] ?? 'pending'; ?>">
                                    <?php echo ucfirst($row['status'] ?? 'Pending'); ?>
                                </span>
                            </td>
                            <td class="action-btns">
                                <a href="#" class="view-btn" onclick="viewFullRecord(<?php echo $row['id']; ?>); return false;" title="View Full Record"><i class="fas fa-id-card"></i></a>
                                <a href="#" class="stats-btn" onclick="viewDownloadStats(<?php echo $row['id']; ?>); return false;" title="Download Stats"><i class="fas fa-chart-bar"></i></a>
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
                        <tr><td colspan="10" style="text-align:center; padding:30px; color:#6a8f6a;">No students found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
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
                        <th>Position</th>
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
                            <td><?php echo htmlspecialchars($row['position'] ?? ''); ?></td>
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
                        <th>#</th><th>Student</th><th>Programme</th><th>Course</th>
                        <th>Status</th><th>Admission No.</th><th>Comment</th><th>Action</th>
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

<div class="modal-overlay" id="fullRecordModal" style="display:none;">
    <div class="modal-content" style="max-width:900px;">
        <button class="modal-close" onclick="closeModal('fullRecordModal')">&times;</button>
        <h2 style="margin-bottom:20px; color:#0d2818;">
            <i class="fas fa-id-card" style="color:#7b1fa2;"></i> Full Student Record
        </h2>
        <div id="fullRecordContent">
            <p style="text-align:center; padding:40px; color:#6a8f6a;">
                <i class="fas fa-spinner fa-spin" style="font-size:2rem;"></i><br>Loading...
            </p>
        </div>
    </div>
</div>

<!-- MODAL: DOWNLOAD STATS -->
<div class="modal-overlay" id="downloadStatsModal" style="display:none;">
    <div class="modal-content" style="max-width:800px;">
        <button class="modal-close" onclick="closeModal('downloadStatsModal')">&times;</button>
        <h2 style="margin-bottom:20px; color:#0d2818;">
            <i class="fas fa-chart-bar" style="color:#e65100;"></i> Student Download Statistics
        </h2>
        <div id="downloadStatsContent">
            <p style="text-align:center; padding:40px; color:#6a8f6a;">
                <i class="fas fa-spinner fa-spin" style="font-size:2rem;"></i><br>Loading...
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
    var levelSelect = document.getElementById('levelSelect');
    courseSelect.innerHTML = '<option value="">-- Select Course --</option>';
    var courses = [];
    var levels = ['NCE I', 'NCE II', 'NCE III'];
    
    if (programme === 'NCE') {
        courses = [
            { value: 'ARB/ISS', text: 'ARB/ISS - Arabic / Islamic Studies' },
            { value: 'ENG/ISS', text: 'ENG/ISS - English / Islamic Studies' },
            { value: 'PED', text: 'PED - Primary Education' },
            { value: 'ENG/HAU', text: 'ENG/HAU - English / Hausa' },  // ✅ DAAIDAI
            { value: 'CSC/ISC', text: 'CSC/ISC - Computer Science / Islamic Studies' },
            { value: 'ENG/SOS', text: 'ENG/SOS - English / Social Studies' },
            { value: 'CSC/BIO', text: 'CSC/BIO - Computer Science / Biology' },
            { value: 'CSC/PHY', text: 'CSC/PHY - Computer Science / Physics' },
            { value: 'ENG/ECO', text: 'ENG/ECO - English / Economics' }
        ];
        levels = ['NCE I', 'NCE II', 'NCE III'];
    } else if (programme === 'DEGREE') {
        courses = [
            { value: 'BA_ARABIC', text: 'B.A. Arabic' },
            { value: 'BA_ISLAMIC', text: 'B.A. Islamic Studies' },
            { value: 'BED_ENGLISH', text: 'B.Ed. English' },
            { value: 'BED_HAUSA', text: 'B.Ed. Hausa' },
            { value: 'BED_SOCIAL', text: 'B.Ed. Social Studies' },
            { value: 'BSC_ECONOMICS', text: 'B.Sc. Economics' },
            { value: 'BSC_CSC', text: 'B.Sc. Computer Science Education' },
            { value: 'BSC_BIOLOGY', text: 'B.Sc. Biology Education' },
            { value: 'BSC_PHYSICS', text: 'B.Sc. Physics Education' },
            { value: 'BSC_ISC', text: 'B.Sc. Islamic Studies' }
        ];
        levels = ['400 Level', '500 Level'];
    } else if (programme === 'ENTREPRENEURSHIP') {
        courses = [
            { value: 'TAILORING', text: 'Tailoring' }, { value: 'AI_TECH', text: 'AI & Technology' },
            { value: 'SALOON', text: 'Salon' }, { value: 'HENNA', text: 'Henna' },
            { value: 'FISH_FARMING', text: 'Fish Farming' }, { value: 'POULTRY', text: 'Poultry' },
            { value: 'SOAP_MAKING', text: 'Soap Making' }, { value: 'CATERING', text: 'Catering' },
            { value: 'BEAD_MAKING', text: 'Bead Making' }, { value: 'GRAPHIC_DESIGN', text: 'Graphic Design' }
        ];
        levels = ['ENTREPRENEURSHIP'];
    }
    
    courses.forEach(function(c) {
        var option = document.createElement('option');
        option.value = c.value; option.textContent = c.text;
        courseSelect.appendChild(option);
    });
    
    if (levelSelect) {
        levelSelect.innerHTML = '';
        levels.forEach(function(lvl) {
            var option = document.createElement('option');
            option.value = lvl; option.textContent = lvl;
            levelSelect.appendChild(option);
        });
    }
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
                            <option value="400 Level" ${data.level == '400 Level' ? 'selected' : ''}>400 Level</option>
                            <option value="500 Level" ${data.level == '500 Level' ? 'selected' : ''}>500 Level</option>
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
                <div class="form-row">
                    <div class="form-group"><label>Role</label>
                        <select name="role">
                            <option value="Provost" ${data.role == 'Provost' ? 'selected' : ''}>Provost</option>
                            <option value="Admission Officer" ${data.role == 'Admission Officer' ? 'selected' : ''}>Admission Officer</option>
                            <option value="Bursary" ${data.role == 'Bursary' ? 'selected' : ''}>Bursary</option>
                            <option value="Accountant" ${data.role == 'Accountant' ? 'selected' : ''}>Accountant</option>
                            <option value="Exam Officer" ${data.role == 'Exam Officer' ? 'selected' : ''}>Exam Officer</option>
                            <option value="Academic Staff" ${data.role == 'Academic Staff' ? 'selected' : ''}>📚 Academic Staff</option>
                            <option value="Non-Academic Staff" ${data.role == 'Non-Academic Staff' ? 'selected' : ''}>🛠️ Non-Academic Staff</option>
                            <option value="Staff" ${data.role == 'Staff' ? 'selected' : ''}>Staff</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Position</label><input type="text" name="position" value="${data.position || ''}"></div>
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
            var html = '';
            html += '<div style="background:#0d2818; color:white; padding:20px; border-radius:12px; margin-bottom:20px;">';
            html += '<h2 style="color:#ffd54f; margin-bottom:10px;">' + s.fullname + '</h2>';
            html += '<p style="font-size:0.9rem; color:#c8e6c9;">';
            html += 'Reg No: <strong>' + s.reg_no + '</strong> | Username: <strong>' + (s.username || '—') + '</strong><br>';
            html += 'Course: <strong>' + s.course + '</strong> | Level: <strong>' + s.level + '</strong><br>';
            html += 'Entry: <strong>' + (s.entry_year || '—') + '</strong> | Grad: <strong>' + (s.graduation_year || '—') + '</strong><br>';
            html += 'Email: <strong>' + s.email + '</strong> | Phone: <strong>' + s.phone + '</strong> | Status: <strong>' + s.status.toUpperCase() + '</strong>';
            html += '</p></div>';
            content.innerHTML = html;
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-error">❌ Error: ' + error.message + '</div>';
        });
}

function viewDownloadStats(studentId) {
    var modal = document.getElementById('downloadStatsModal');
    var content = document.getElementById('downloadStatsContent');
    
    modal.style.display = 'block';
    content.innerHTML = '<p style="text-align:center; padding:40px; color:#6a8f6a;"><i class="fas fa-spinner fa-spin" style="font-size:2rem;"></i><br>Loading...</p>';
    
    fetch('get_download_stats.php?student_id=' + studentId)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                content.innerHTML = '<div class="alert alert-error">❌ ' + (data.message || 'Error') + '</div>';
                return;
            }
            
            var html = '';
            html += '<div style="background:#0d2818; color:white; padding:15px 20px; border-radius:10px; margin-bottom:20px;">';
            html += '<h3 style="color:#ffd54f; margin-bottom:5px;">' + data.student.fullname + '</h3>';
            html += '<p style="font-size:0.85rem; color:#c8e6c9;">Reg No: ' + (data.student.reg_no || '—') + ' | Level: ' + (data.student.level || '—') + '</p>';
            html += '</div>';
            
            if (data.stats && data.stats.length > 0) {
                html += '<table style="width:100%; border-collapse:collapse; font-size:0.85rem;">';
                html += '<thead><tr style="background:#0d2818; color:white;">';
                html += '<th style="padding:10px; text-align:left;">Document</th>';
                html += '<th style="padding:10px; text-align:center;">Downloads</th>';
                html += '<th style="padding:10px; text-align:center;">Prints</th>';
                html += '<th style="padding:10px; text-align:center;">Last Access</th>';
                html += '</tr></thead><tbody>';
                
                data.stats.forEach(function(item) {
                    html += '<tr>';
                    html += '<td style="padding:10px; border-bottom:1px solid #eee;">';
                    html += '<i class="fas fa-file-alt" style="color:#2e7d32; margin-right:8px;"></i>';
                    html += item.document_label;
                    html += '</td>';
                    html += '<td style="padding:10px; text-align:center; border-bottom:1px solid #eee;">';
                    html += '<span style="background:#e8f5e9; color:#2e7d32; padding:3px 10px; border-radius:12px; font-weight:700;">' + item.download_count + '</span>';
                    html += '</td>';
                    html += '<td style="padding:10px; text-align:center; border-bottom:1px solid #eee;">';
                    html += '<span style="background:#fff3e0; color:#e65100; padding:3px 10px; border-radius:12px; font-weight:700;">' + item.print_count + '</span>';
                    html += '</td>';
                    html += '<td style="padding:10px; text-align:center; border-bottom:1px solid #eee; font-size:0.8rem; color:#6a8f6a;">';
                    html += item.last_access || '—';
                    html += '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
            } else {
                html += '<div style="text-align:center; padding:40px; background:#f8faf8; border-radius:10px;">';
                html += '<i class="fas fa-chart-bar" style="font-size:3rem; color:#dce8dc; display:block; margin-bottom:10px;"></i>';
                html += '<p style="color:#6a8f6a;">This student has not downloaded any document yet.</p>';
                html += '</div>';
            }
            
            content.innerHTML = html;
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-error">❌ Error: ' + error.message + '</div>';
        });
}
</script>

</body>
</html>