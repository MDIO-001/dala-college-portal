<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'connect.php';
include 'result_functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_role = $_SESSION['role'] ?? '';
$allowed_roles = ['admin', 'Exam Officer', 'Provost'];

if (!in_array($user_role, $allowed_roles)) {
    header('Location: login.php');
    exit();
}

$admin_name = $_SESSION['fullname'] ?? 'Admin';
$message = '';
$message_type = '';

$session_options = getSessionOptions();
$current_session = getSetting($conn, 'current_session') ?? '2024/2025';
$level_options = getLevelOptions();

// ============================================
// DOWNLOAD ALL COURSES SHEET
// ============================================
if (isset($_GET['download_sheet'])) {
    $combination = mysqli_real_escape_string($conn, $_GET['combination'] ?? '');
    $level = mysqli_real_escape_string($conn, $_GET['level'] ?? '');
    $semester = mysqli_real_escape_string($conn, $_GET['semester'] ?? '');
    $session = mysqli_real_escape_string($conn, $_GET['session'] ?? $current_session);
    
    if (empty($combination) || empty($level) || empty($semester)) {
        header('Location: admin_result_entry.php');
        exit();
    }
    
    // Samo courses
    $courses = [];
    $cq = mysqli_query($conn, "SELECT * FROM courses 
                               WHERE combination = '$combination' 
                               AND level = '$level' 
                               AND semester = '$semester'
                               AND status = 'Compulsory'
                               ORDER BY FIELD(category, 'EDU', 'GSE', 'PED', 'CSC', 'BIO', 'ISC', 'PHY', 'ENG', 'ECO', 'ARB', 'ISS', 'HAU', 'SOS'), 
                               course_code");
    while ($c = mysqli_fetch_assoc($cq)) $courses[] = $c;
    
    // Samo students
    $students_query = mysqli_query($conn, "SELECT * FROM students 
                                           WHERE (combination = '$combination' OR course = '$combination') 
                                           AND level = '$level' 
                                           AND status IN ('active', 'approved')
                                           ORDER BY fullname");
    
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
    
    $count = 0;
    while ($s = mysqli_fetch_assoc($students_query)) {
        $count++;
        $row = [$s['reg_no'] ?? $s['student_id'], $s['fullname']];
        foreach ($courses as $c) $row[] = '';
        fputcsv($output, $row);
    }
    
    if ($count == 0) {
        fputcsv($output, ['No students found for this combination and level']);
    }
    
    fclose($output);
    exit();
}

// ============================================
// DOWNLOAD SINGLE COURSE SHEET
// ============================================
if (isset($_GET['download_single'])) {
    $combination = mysqli_real_escape_string($conn, $_GET['combination'] ?? '');
    $level = mysqli_real_escape_string($conn, $_GET['level'] ?? '');
    $semester = mysqli_real_escape_string($conn, $_GET['semester'] ?? '');
    $session = mysqli_real_escape_string($conn, $_GET['session'] ?? $current_session);
    $course_code = strtoupper(mysqli_real_escape_string($conn, $_GET['course_code'] ?? ''));
    
    if (empty($combination) || empty($level) || empty($semester) || empty($course_code)) {
        header('Location: admin_result_entry.php');
        exit();
    }
    
    $course = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM courses 
                                                       WHERE course_code = '$course_code' 
                                                       AND combination = '$combination' 
                                                       AND level = '$level' 
                                                       AND semester = '$semester' 
                                                       LIMIT 1"));
    
    if (!$course) {
        header('Location: admin_result_entry.php');
        exit();
    }
    
    $students_query = mysqli_query($conn, "SELECT * FROM students 
                                           WHERE (combination = '$combination' OR course = '$combination') 
                                           AND level = '$level' 
                                           AND status IN ('active', 'approved')
                                           ORDER BY fullname");
    
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
    fputcsv($output, ['Units:', $course['credits']]);
    fputcsv($output, []);
    
    fputcsv($output, ['Reg No', 'Student Name', $course_code . ' (' . $course['credits'] . ')']);
    
    $count = 0;
    while ($s = mysqli_fetch_assoc($students_query)) {
        $count++;
        fputcsv($output, [$s['reg_no'] ?? $s['student_id'], $s['fullname'], '']);
    }
    
    if ($count == 0) {
        fputcsv($output, ['No students found']);
    }
    
    fclose($output);
    exit();
}

// ============================================
// SINGLE ENTRY - SAVE
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_single'])) {
    $student_id = intval($_POST['student_id']);
    $session = mysqli_real_escape_string($conn, $_POST['session']);
    $level = mysqli_real_escape_string($conn, $_POST['level']);
    $semester = mysqli_real_escape_string($conn, $_POST['semester']);
    $combination = mysqli_real_escape_string($conn, $_POST['combination']);
    
    $s = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM students WHERE id = $student_id"));
    
    $saved = 0;
    if (isset($_POST['scores']) && is_array($_POST['scores'])) {
        foreach ($_POST['scores'] as $course_code => $score) {
            if ($score === '' || $score === null) continue;
            $score = intval($score);
            if ($score < 0 || $score > 100) continue;
            
            $course_code = mysqli_real_escape_string($conn, $course_code);
            
            $cq = mysqli_query($conn, "SELECT * FROM courses WHERE course_code = '$course_code' AND combination = '$combination' AND level = '$level' LIMIT 1");
            $course = mysqli_fetch_assoc($cq);
            
            if (!$course) {
                $cq2 = mysqli_query($conn, "SELECT * FROM results WHERE course_code = '$course_code' AND student_id = $student_id LIMIT 1");
                $course = mysqli_fetch_assoc($cq2);
                if (!$course) continue;
                $course_title = $course['course_title'];
                $credits = $course['credit_units'];
            } else {
                $course_title = $course['course_title'];
                $credits = $course['credits'];
            }
            
            $g = getGrade($conn, $score);
            
            $check = mysqli_query($conn, "SELECT id FROM results 
                                          WHERE student_id = $student_id 
                                          AND course_code = '$course_code' 
                                          AND level = '$level' 
                                          AND semester = '$semester' 
                                          AND session = '$session'");
            
            if (mysqli_num_rows($check) > 0) {
                $update = "UPDATE results SET 
                    score = $score, grade = '{$g['grade']}', 
                    grade_point = {$g['grade_point']}, remark = '{$g['remark']}', 
                    credit_units = $credits
                    WHERE student_id = $student_id 
                    AND course_code = '$course_code' 
                    AND level = '$level' 
                    AND semester = '$semester' 
                    AND session = '$session'";
                if (mysqli_query($conn, $update)) $saved++;
            } else {
                $insert = "INSERT INTO results (student_id, reg_no, student_name, course_code, course_title, credit_units, score, grade, grade_point, remark, level, semester, session, combination, entered_by) 
                           VALUES ($student_id, '{$s['reg_no']}', '{$s['fullname']}', '$course_code', '$course_title', $credits, $score, '{$g['grade']}', {$g['grade_point']}, '{$g['remark']}', '$level', '$semester', '$session', '$combination', {$_SESSION['user_id']})";
                if (mysqli_query($conn, $insert)) $saved++;
            }
        }
    }
    
    $message = "✅ Saved <strong>$saved</strong> result(s) for $level — $session!";
    $message_type = 'success';
}

// ============================================
// BULK UPLOAD
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_sheet'])) {
    $combination = mysqli_real_escape_string($conn, $_POST['combination']);
    $level = mysqli_real_escape_string($conn, $_POST['level']);
    $semester = mysqli_real_escape_string($conn, $_POST['semester']);
    $session = mysqli_real_escape_string($conn, $_POST['session']);
    
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
                
                $sq = mysqli_query($conn, "SELECT * FROM students WHERE reg_no = '$reg_no' OR student_id = '$reg_no' LIMIT 1");
                $student = mysqli_fetch_assoc($sq);
                if (!$student) {
                    $errors[] = "Row $row_num: Student $reg_no not found";
                    continue;
                }
                
                for ($i = 0; $i < count($courses); $i++) {
                    $score = trim($row[$i + 2] ?? '');
                    if ($score === '') continue;
                    $score = intval($score);
                    if ($score < 0 || $score > 100) continue;
                    
                    $course_code = mysqli_real_escape_string($conn, $courses[$i]);
                    $cq = mysqli_query($conn, "SELECT * FROM courses WHERE course_code = '$course_code' AND combination = '$combination' AND level = '$level' LIMIT 1");
                    $course = mysqli_fetch_assoc($cq);
                    if (!$course) continue;
                    
                    $g = getGrade($conn, $score);
                    
                    $check = mysqli_query($conn, "SELECT id FROM results 
                                                  WHERE student_id = {$student['id']} 
                                                  AND course_code = '$course_code' 
                                                  AND level = '$level' 
                                                  AND semester = '$semester' 
                                                  AND session = '$session'");
                    
                    if (mysqli_num_rows($check) > 0) {
                        $update = "UPDATE results SET score = $score, grade = '{$g['grade']}', grade_point = {$g['grade_point']}, remark = '{$g['remark']}' 
                                   WHERE student_id = {$student['id']} AND course_code = '$course_code' AND level = '$level' AND semester = '$semester' AND session = '$session'";
                        if (mysqli_query($conn, $update)) $updated++;
                    } else {
                        $insert = "INSERT INTO results (student_id, reg_no, student_name, course_code, course_title, credit_units, score, grade, grade_point, remark, level, semester, session, combination, entered_by) 
                                   VALUES ({$student['id']}, '{$student['reg_no']}', '{$student['fullname']}', '$course_code', '{$course['course_title']}', {$course['credits']}, $score, '{$g['grade']}', {$g['grade_point']}, '{$g['remark']}', '$level', '$semester', '$session', '$combination', {$_SESSION['user_id']})";
                        if (mysqli_query($conn, $insert)) $saved++;
                    }
                }
            }
            fclose($file);
            
            $total = $saved + $updated;
            if ($total > 0) {
                $message = "✅ Results imported for $level — $session!<br>📊 New: <strong>$saved</strong> | 🔄 Updated: <strong>$updated</strong>";
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

$combinations_list = [];
$cq = mysqli_query($conn, "SELECT DISTINCT combination FROM courses WHERE combination IS NOT NULL AND combination != '' ORDER BY combination");
while ($c = mysqli_fetch_assoc($cq)) $combinations_list[] = $c['combination'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Result Entry - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Tahoma,sans-serif; background:#f0f4f8; padding:20px; }
        .container { max-width:1300px; margin:0 auto; }
        
        .topbar { background:#0d2818; padding:15px 25px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; border-radius:12px; margin-bottom:20px; }
        .topbar .logo-title { color:#ffd54f; font-size:1.3rem; font-weight:800; }
        .topbar .logo-title span { color:#a5d6a7; }
        .topbar .logo-sub { display:block; font-size:0.55rem; color:#c8e6c9; }
        .topbar nav a { color:#c8e6c9; text-decoration:none; padding:8px 16px; border-radius:25px; font-size:0.85rem; }
        .topbar nav a:hover { background:#2e7d32; }
        .topbar nav .logout { background:#c62828; color:white !important; }
        .topbar .admin-badge { background:#c62828; color:white; padding:6px 18px; border-radius:20px; font-size:0.8rem; font-weight:600; }
        
        .result-nav { background:white; padding:15px; border-radius:12px; margin-bottom:20px; display:flex; gap:8px; flex-wrap:wrap; align-items:center; box-shadow:0 2px 10px rgba(0,0,0,0.05); }
        .result-nav .label { font-weight:700; color:#0d2818; margin-right:10px; font-size:0.85rem; }
        .result-nav a { padding:8px 15px; border-radius:6px; text-decoration:none; font-weight:600; font-size:0.75rem; }
        .r-course { background:#2e7d32; color:white; }
        .r-grade { background:#f9a825; color:#0d2818; }
        .r-entry { background:#8d2c2c; color:white; box-shadow:0 0 0 3px #ffd54f; }
        .r-slip { background:#e53935; color:white; }
        .r-slip-pro { background:#a5d6a7; color:#0d2818; }
        .r-transcript { background:#5d4037; color:white; }
        .r-final { background:#558b2f; color:white; }
        .r-settings { background:#e65100; color:white; }
        .r-database { background:#37474f; color:white; }
        .r-statement { background:#455a64; color:white; }
        
        .card { background:white; padding:25px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); margin-bottom:20px; }
        .card h2 { color:#0d2818; margin-bottom:15px; font-size:1.2rem; }
        
        .message { padding:12px 20px; border-radius:8px; margin-bottom:15px; font-weight:600; }
        .message.success { background:#e8f5e9; color:#2e7d32; border-left:4px solid #2e7d32; }
        .message.error { background:#ffebee; color:#c62828; border-left:4px solid #c62828; }
        
        .tabs { display:flex; gap:5px; margin-bottom:20px; border-bottom:2px solid #e0ebe0; }
        .tab-btn { padding:12px 25px; background:transparent; border:none; font-weight:700; font-size:0.9rem; cursor:pointer; color:#6a8f6a; border-bottom:3px solid transparent; }
        .tab-btn.active { color:#2e7d32; border-bottom-color:#2e7d32; }
        .tab-content { display:none; }
        .tab-content.active { display:block; }
        
        .form-row { display:grid; grid-template-columns:repeat(4,1fr); gap:15px; margin-bottom:15px; }
        .form-row.col1 { grid-template-columns:1fr; }
        .form-group label { display:block; font-weight:600; color:#1a2e1a; margin-bottom:5px; font-size:0.85rem; }
        .form-group input, .form-group select { width:100%; padding:10px; border:2px solid #dce8dc; border-radius:8px; font-size:0.9rem; }
        .form-group input:focus, .form-group select:focus { border-color:#2e7d32; outline:none; }
        
        .btn { padding:10px 25px; border:none; border-radius:8px; font-weight:700; font-size:0.9rem; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-green { background:#2e7d32; color:white; }
        .btn-green:hover { background:#1b5e20; }
        .btn-blue { background:#1976d2; color:white; }
        .btn-blue:hover { background:#0d47a1; }
        .btn-orange { background:#f57c00; color:white; }
        .btn-orange:hover { background:#e65100; }
        
        .score-input { width:80px; padding:8px; border:2px solid #dce8dc; border-radius:6px; text-align:center; font-weight:700; font-size:0.95rem; }
        .score-input:focus { border-color:#2e7d32; outline:none; }
        
        .result-table { width:100%; border-collapse:collapse; margin-bottom:15px; }
        .result-table th { background:#0d2818; color:white; padding:10px; text-align:left; font-size:0.8rem; }
        .result-table td { padding:10px; border-bottom:1px solid #eee; font-size:0.9rem; }
        .result-table tr:hover td { background:#f8faf8; }
        .result-table .center { text-align:center; }
        
        .grade-badge { padding:4px 12px; border-radius:15px; font-weight:700; font-size:0.85rem; }
        .grade-A { background:#e8f5e9; color:#2e7d32; }
        .grade-B { background:#e3f2fd; color:#0d47a1; }
        .grade-C { background:#fff3e0; color:#e65100; }
        .grade-D { background:#f3e5f5; color:#6a1b9a; }
        .grade-E { background:#fce4ec; color:#c2185b; }
        .grade-F { background:#ffebee; color:#c62828; }
        
        .student-info-box { background:linear-gradient(135deg, #e8f5e9, #c8e6c9); padding:20px; border-radius:10px; margin-bottom:20px; border-left:5px solid #2e7d32; }
        .student-info-box h3 { color:#0d2818; margin-bottom:10px; }
        .student-info-box p { color:#1a2e1a; margin:5px 0; font-size:0.9rem; }
        
        .carryover-box { background:#ffebee; padding:20px; border-radius:10px; margin-bottom:20px; border-left:5px solid #c62828; }
        .carryover-box h3 { color:#c62828; margin-bottom:15px; }
        
        .total-summary { display:grid; grid-template-columns:repeat(3, 1fr); gap:15px; margin-top:20px; margin-bottom:15px; }
        .total-summary .summary-box { border:2px solid #0d2818; padding:15px; text-align:center; border-radius:8px; background:#f8faf8; }
        .total-summary .summary-box.gpa { border-color:#2e7d32; background:#e8f5e9; }
        .total-summary .summary-box .label { font-size:0.75rem; text-transform:uppercase; color:#6a8f6a; font-weight:700; }
        .total-summary .summary-box .value { font-size:1.8rem; font-weight:900; color:#0d2818; line-height:1; margin-top:5px; }
        .total-summary .summary-box.gpa .value { color:#2e7d32; }
        
        .import-area { background:#f8faf8; padding:30px; border-radius:12px; border:2px dashed #2e7d32; text-align:center; }
        .import-area input[type="file"] { padding:12px; border:2px solid #dce8dc; border-radius:8px; background:white; width:100%; max-width:400px; margin:15px auto; display:block; }
        
        .guide-box { background:#e3f2fd; padding:20px; border-radius:10px; margin-bottom:20px; border-left:4px solid #1976d2; }
        .guide-box h4 { color:#0d47a1; margin-bottom:10px; }
        .guide-box ol { padding-left:20px; }
        .guide-box li { margin:5px 0; font-size:0.9rem; }
        
        @media (max-width:768px) {
            .form-row { grid-template-columns:1fr; }
            .topbar { flex-direction:column; gap:10px; }
            .total-summary { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="topbar">
            <div>
                <span class="logo-title">DALA <span>COLLEGE</span></span>
                <span class="logo-sub">Admin Panel</span>
            </div>
            <nav>
                <a href="admin_dashboard.php">Dashboard</a>
                <a href="logout.php" class="logout">Logout</a>
            </nav>
            <div>
                <span class="admin-badge">👤 <?php echo htmlspecialchars($admin_name); ?></span>
            </div>
        </div>

        <div class="result-nav">
            <span class="label">📊 RESULT SYSTEM:</span>
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
        </div>

        <h2 style="color:#0d2818; margin-bottom:15px;">📝 Result Entry</h2>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab-btn active" onclick="showTab(event, 'single')">📝 Single Entry</button>
            <button class="tab-btn" onclick="showTab(event, 'bulk')">📤 Bulk Upload / Download</button>
        </div>

        <!-- SINGLE ENTRY -->
        <div class="tab-content active" id="tab-single">
            <div class="card">
                <h2>📝 Single Entry - Enter Scores for One Student</h2>
                
                <form method="GET">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Combination</label>
                            <select name="combination" required onchange="this.form.submit()">
                                <option value="">-- Select --</option>
                                <?php foreach ($combinations_list as $c): ?>
                                    <option value="<?php echo $c; ?>" <?php echo (isset($_GET['combination']) && $_GET['combination']==$c)?'selected':''; ?>><?php echo $c; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Level</label>
                            <select name="level" required onchange="this.form.submit()">
                                <option value="">-- Select --</option>
                                <?php foreach ($level_options as $lvl): ?>
                                    <option value="<?php echo $lvl; ?>" <?php echo (isset($_GET['level']) && $_GET['level']==$lvl)?'selected':''; ?>><?php echo $lvl; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Semester</label>
                            <select name="semester" required onchange="this.form.submit()">
                                <option value="">-- Select --</option>
                                <option value="First Semester" <?php echo (isset($_GET['semester']) && $_GET['semester']=='First Semester')?'selected':''; ?>>First Semester</option>
                                <option value="Second Semester" <?php echo (isset($_GET['semester']) && $_GET['semester']=='Second Semester')?'selected':''; ?>>Second Semester</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Session</label>
                            <select name="session" required onchange="this.form.submit()">
                                <?php foreach ($session_options as $sess): ?>
                                    <option value="<?php echo $sess; ?>" <?php echo ((isset($_GET['session']) ? $_GET['session'] : $current_session) == $sess)?'selected':''; ?>><?php echo $sess; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-blue">🔍 Load Students</button>
                </form>
                
                <?php if (isset($_GET['combination']) && isset($_GET['level']) && isset($_GET['semester'])): 
                    $comb = mysqli_real_escape_string($conn, $_GET['combination']);
                    $lvl = mysqli_real_escape_string($conn, $_GET['level']);
                    $sem = mysqli_real_escape_string($conn, $_GET['semester']);
                    $sess = mysqli_real_escape_string($conn, $_GET['session'] ?? $current_session);
                    
                    $students = mysqli_query($conn, "SELECT * FROM students 
                                                     WHERE (combination = '$comb' OR course = '$comb') 
                                                     AND level = '$lvl' 
                                                     ORDER BY fullname");
                    
                    if (mysqli_num_rows($students) == 0) {
                        $students = mysqli_query($conn, "SELECT * FROM students 
                                                         WHERE (combination = '$comb' OR course = '$comb') 
                                                         ORDER BY fullname");
                    }
                ?>
                <hr style="margin:20px 0;">
                
                <h3 style="margin-bottom:15px;">Select Student:</h3>
                <div class="form-row col1">
                    <div class="form-group">
                        <label>Student</label>
                        <select id="studentSelect" onchange="loadStudentCourses()">
                            <option value="">-- Select Student --</option>
                            <?php 
                            $count = 0;
                            while ($s = mysqli_fetch_assoc($students)): 
                                $count++;
                                $co_count = mysqli_num_rows(getCarryOverCourses($conn, $s['id'], $comb));
                            ?>
                                <option value="<?php echo $s['id']; ?>" 
                                        data-reg="<?php echo htmlspecialchars($s['reg_no'] ?? $s['student_id']); ?>"
                                        data-name="<?php echo htmlspecialchars($s['fullname']); ?>"
                                        data-combination="<?php echo htmlspecialchars($comb); ?>"
                                        data-level="<?php echo htmlspecialchars($lvl); ?>"
                                        data-semester="<?php echo htmlspecialchars($sem); ?>"
                                        data-session="<?php echo htmlspecialchars($sess); ?>">
                                    <?php echo htmlspecialchars($s['reg_no'] ?? $s['student_id']); ?> — 
                                    <?php echo htmlspecialchars($s['fullname']); ?>
                                    <?php if ($co_count > 0): ?>
                                        ⚠️ (<?php echo $co_count; ?> Carry Over)
                                    <?php endif; ?>
                                </option>
                            <?php endwhile; ?>
                            <?php if ($count == 0): ?>
                                <option value="" disabled>⚠️ No students found</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                
                <div id="coursesContainer"></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- BULK -->
        <div class="tab-content" id="tab-bulk">
            <div class="card">
                <div class="guide-box">
                    <h4>📖 How To Use:</h4>
                    <ol>
                        <li><strong>Step 1:</strong> Download Sheet — All Courses ko Single Course</li>
                        <li><strong>Step 2:</strong> Open CSV a Excel/Google Sheets</li>
                        <li><strong>Step 3:</strong> Shigar da maki (0-100)</li>
                        <li><strong>Step 4:</strong> Ajiye a matsayin CSV</li>
                        <li><strong>Step 5:</strong> Upload Sheet</li>
                        <li><strong>Step 6:</strong> System ɗin zai lissafta Grade, Point, GPA kai tsaye ✅</li>
                    </ol>
                </div>
            </div>

            <!-- DOWNLOAD ALL COURSES -->
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
                                    <option value="<?php echo $c; ?>"><?php echo $c; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Level *</label>
                            <select name="level" required>
                                <?php foreach ($level_options as $lvl): ?>
                                    <option value="<?php echo $lvl; ?>"><?php echo $lvl; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Semester *</label>
                            <select name="semester" required>
                                <option value="First Semester">First Semester</option>
                                <option value="Second Semester">Second Semester</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Session *</label>
                            <select name="session" required>
                                <?php foreach ($session_options as $sess): ?>
                                    <option value="<?php echo $sess; ?>" <?php echo ($current_session == $sess)?'selected':''; ?>><?php echo $sess; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-blue">
                        <i class="fas fa-download"></i> Download All Courses Sheet
                    </button>
                </form>
            </div>

            <!-- DOWNLOAD SINGLE COURSE -->
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
                                    <option value="<?php echo $c; ?>"><?php echo $c; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Level *</label>
                            <select name="level" required>
                                <?php foreach ($level_options as $lvl): ?>
                                    <option value="<?php echo $lvl; ?>"><?php echo $lvl; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Semester *</label>
                            <select name="semester" required>
                                <option value="First Semester">First Semester</option>
                                <option value="Second Semester">Second Semester</option>
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
                                    <option value="<?php echo $sess; ?>" <?php echo ($current_session == $sess)?'selected':''; ?>><?php echo $sess; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-orange">
                        <i class="fas fa-download"></i> Download Single Course Sheet
                    </button>
                </form>
            </div>

            <!-- UPLOAD -->
            <div class="card">
                <h2>📤 Step 2: Upload Filled Sheet</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Combination *</label>
                            <select name="combination" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($combinations_list as $c): ?>
                                    <option value="<?php echo $c; ?>"><?php echo $c; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Level *</label>
                            <select name="level" required>
                                <?php foreach ($level_options as $lvl): ?>
                                    <option value="<?php echo $lvl; ?>"><?php echo $lvl; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Semester *</label>
                            <select name="semester" required>
                                <option value="First Semester">First Semester</option>
                                <option value="Second Semester">Second Semester</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Session *</label>
                            <select name="session" required>
                                <?php foreach ($session_options as $sess): ?>
                                    <option value="<?php echo $sess; ?>" <?php echo ($current_session == $sess)?'selected':''; ?>><?php echo $sess; ?></option>
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
        
        document.getElementById('coursesContainer').innerHTML = '<p style="text-align:center; padding:20px; color:#6a8f6a;">⏳ Loading courses...</p>';
        
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
                document.getElementById('coursesContainer').innerHTML = '<p style="color:red;">Error: ' + err.message + '</p>';
            });
    }
    </script>
</body>
</html>