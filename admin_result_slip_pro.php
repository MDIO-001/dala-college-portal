<?php
session_start();
include 'connect.php';
include 'result_functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Role ɗin da aka yarda su shiga Result Slip Pro
$user_role = $_SESSION['role'] ?? '';
$allowed_roles = ['admin', 'Exam Officer', 'Provost'];

if (!in_array($user_role, $allowed_roles)) {
    header('Location: login.php');
    exit();
}

$admin_name = $_SESSION['fullname'] ?? 'Admin';

// ============================================
// DOWNLOAD CSV
// ============================================
if (isset($_GET['download_csv']) && isset($_GET['student_id'])) {
    $sid = intval($_GET['student_id']);
    $session = mysqli_real_escape_string($conn, $_GET['session'] ?? '');
    $semester = mysqli_real_escape_string($conn, $_GET['semester'] ?? '');
    $level = mysqli_real_escape_string($conn, $_GET['level'] ?? '');
    
    $student = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM students WHERE id = $sid"));
    if (!$student) exit();
    
    $rq = "SELECT * FROM results WHERE student_id = $sid AND session = '$session' AND semester = '$semester' AND level = '$level' ORDER BY course_code";
    $rr = mysqli_query($conn, $rq);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="result_slip_' . str_replace('/', '_', $student['reg_no']) . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['DALA COLLEGE OF EDUCATION, KANO']);
    fputcsv($output, ['RESULT SLIP']);
    fputcsv($output, []);
    fputcsv($output, ['Student Name:', $student['fullname']]);
    fputcsv($output, ['Reg No:', $student['reg_no']]);
    fputcsv($output, ['Combination:', $student['combination']]);
    fputcsv($output, ['Level:', $level]);
    fputcsv($output, ['Semester:', $semester]);
    fputcsv($output, ['Session:', $session]);
    fputcsv($output, []);
    fputcsv($output, ['S/N', 'Course Code', 'Course Title', 'Unit', 'Grade', 'Point', 'Remark']);
    
    $i = 1;
    $total_units = 0;
    $total_points = 0;
    while ($r = mysqli_fetch_assoc($rr)) {
        $remark = ($r['grade'] == 'F') ? 'Fail' : 'Pass';
        fputcsv($output, [$i++, $r['course_code'], $r['course_title'], $r['credit_units'], $r['grade'], number_format($r['grade_point'], 1), $remark]);
        if ($r['grade'] != 'F') {
            $total_units += $r['credit_units'];
            $total_points += $r['grade_point'] * $r['credit_units'];
        }
    }
    fputcsv($output, []);
    fputcsv($output, ['Total Units:', $total_units]);
    fputcsv($output, ['GPA:', number_format($total_units > 0 ? $total_points / $total_units : 0, 2)]);
    fputcsv($output, ['CGPA:', number_format(getStudentCGPA($conn, $sid), 2)]);
    fputcsv($output, ['Classification:', getGradeClassification(getStudentCGPA($conn, $sid))]);
    
    fclose($output);
    exit();
}

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$session = isset($_GET['session']) ? mysqli_real_escape_string($conn, $_GET['session']) : getSetting($conn, 'current_session');
$semester = isset($_GET['semester']) ? mysqli_real_escape_string($conn, $_GET['semester']) : 'First Semester';
$level = isset($_GET['level']) ? mysqli_real_escape_string($conn, $_GET['level']) : '';

$student = null;
$results = [];
$total_units = 0;
$total_points = 0;
$gpa = 0;
$cgpa = 0;
$classification = '';

if ($student_id > 0) {
    $student = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM students WHERE id = $student_id"));
    
    if ($student) {
        if (empty($level)) $level = $student['level'];
        
        $rq = "SELECT * FROM results 
               WHERE student_id = $student_id 
               AND session = '$session' 
               AND semester = '$semester' 
               AND level = '$level'
               ORDER BY course_code";
        $rr = mysqli_query($conn, $rq);
        while ($r = mysqli_fetch_assoc($rr)) {
            $results[] = $r;
            if ($r['grade'] != 'F') {
                $total_units += $r['credit_units'];
                $total_points += $r['grade_point'] * $r['credit_units'];
            }
        }
        
        $gpa = $total_units > 0 ? round($total_points / $total_units, 2) : 0;
        $cgpa = getStudentCGPA($conn, $student_id);
        $classification = getGradeClassification($cgpa);
    }
}

$students_list = mysqli_query($conn, "SELECT id, reg_no, fullname, combination, level FROM students ORDER BY fullname");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Result Slip Pro - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Tahoma,sans-serif; background:#f0f4f8; padding:20px; }
        .container { max-width:1200px; margin:0 auto; }
        
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
        .r-entry { background:#8d2c2c; color:white; }
        .r-slip { background:#e53935; color:white; }
        .r-slip-pro { background:#a5d6a7; color:#0d2818; box-shadow:0 0 0 3px #ffd54f; }
        .r-transcript { background:#5d4037; color:white; }
        .r-final { background:#558b2f; color:white; }
        .r-settings { background:#e65100; color:white; }
        .r-database { background:#37474f; color:white; }
        .r-statement { background:#455a64; color:white; }
        
        .card { background:white; padding:25px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); margin-bottom:20px; }
        .card h2 { color:#0d2818; margin-bottom:15px; font-size:1.2rem; }
        
        .form-row { display:grid; grid-template-columns:repeat(4,1fr); gap:15px; margin-bottom:15px; }
        .form-group label { display:block; font-weight:600; color:#1a2e1a; margin-bottom:5px; font-size:0.85rem; }
        .form-group select, .form-group input { width:100%; padding:10px; border:2px solid #dce8dc; border-radius:8px; font-size:0.9rem; }
        
        .btn { padding:10px 25px; border:none; border-radius:8px; font-weight:700; font-size:0.9rem; cursor:pointer; text-decoration:none; display:inline-block; margin-right:8px; }
        .btn-green { background:#2e7d32; color:white; }
        .btn-green:hover { background:#1b5e20; }
        .btn-blue { background:#1976d2; color:white; }
        .btn-blue:hover { background:#0d47a1; }
        .btn-orange { background:#f57c00; color:white; }
        .btn-orange:hover { background:#e65100; }
        .btn-red { background:#c62828; color:white; }
        .btn-red:hover { background:#b71c1c; }
        
        /* ============================================ */
        /* SLIP PRO DESIGN */
        /* ============================================ */
        .slip-pro { 
            background: white; 
            padding: 30px; 
            border: 3px double #0d2818; 
            margin-top: 20px; 
            font-family: 'Times New Roman', serif; 
            position: relative; 
            overflow: hidden;
        }
        
        /* WATERMARK - LOGO */
        .slip-pro::before { 
            content: ''; 
            position: absolute; 
            top: 50%; 
            left: 50%; 
            transform: translate(-50%, -50%); 
            width: 500px; 
            height: 500px; 
            background-image: url('images/dala-logo.png'); 
            background-size: contain; 
            background-repeat: no-repeat; 
            background-position: center; 
            opacity: 0.06; 
            z-index: 0; 
            pointer-events: none; 
        }
        
        .slip-pro > * { position: relative; z-index: 1; }
        
        /* HEADER - LOGO + TEXT */
        .pro-header { 
            display: grid; 
            grid-template-columns: 150px 1fr; 
            gap: 20px; 
            align-items: center; 
            padding-bottom: 15px; 
            border-bottom: 3px double #0d2818; 
            margin-bottom: 12px; 
        }
        .pro-header .logo-box { width: 150px; height: 150px; }
        .pro-header .logo-box img { width: 100%; height: 100%; object-fit: contain; }
        .pro-header .center-box { text-align: center; }
        .pro-header .school-name { font-size: 1.8rem; font-weight: 900; color: #0d2818; letter-spacing: 1.5px; }
        .pro-header .school-loc { font-size: 1rem; font-weight: 700; color: #0d2818; margin: 3px 0; }
        .pro-header .accreditation { font-size: 0.8rem; color: #c62828; font-weight: 700; border-top: 1px solid #c62828; border-bottom: 1px solid #c62828; padding: 3px 0; display: inline-block; margin-top: 5px; }
        
        .pro-title { text-align: center; font-size: 1.3rem; font-weight: 900; letter-spacing: 3px; color: white; background: #0d2818; padding: 10px; margin-bottom: 20px; text-transform: uppercase; }
        
        /* INFO WITH PHOTO ON LEFT */
        .pro-info-wrapper {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 25px;
            margin-bottom: 25px;
            align-items: start;
        }
        
        .pro-photo-left {
            width: 150px;
            height: 180px;
            border: 3px solid #0d2818;
            border-radius: 6px;
            overflow: hidden;
            background: #f0f0f0;
            box-shadow: 4px 4px 10px rgba(0,0,0,0.2);
        }
        
        .pro-photo-left img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        
        .pro-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0 30px;
            font-size: 0.95rem;
        }
        
        .pro-info .row { 
            display: flex; 
            padding: 6px 0; 
            border-bottom: 1px dotted #999; 
        }
        
        .pro-info .label { 
            font-weight: 800; 
            color: #0d2818; 
            min-width: 140px; 
        }
        
        .pro-info .value { 
            font-weight: 700; 
            color: #000; 
        }
        
        /* TABLE */
        .pro-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; border: 2px solid #0d2818; }
        .pro-table th { background: #2e7d32; color: white; padding: 10px; font-size: 0.8rem; font-weight: 900; text-transform: uppercase; border: 1px solid #0d2818; text-align: center; }
        .pro-table td { padding: 8px 10px; border: 1px solid #0d2818; font-size: 0.9rem; }
        .pro-table .center { text-align: center; }
        .pro-table tbody tr:nth-child(even) td { background: #f8faf8; }
        .pro-table .grade-A { color: #2e7d32; }
        .pro-table .grade-B { color: #0d47a1; }
        .pro-table .grade-C { color: #e65100; }
        .pro-table .grade-D { color: #6a1b9a; }
        .pro-table .grade-E { color: #c2185b; }
        .pro-table .grade-F { color: #c62828; }
        
        /* REMARK PASS/FAIL */
        .remark-pass { color: #2e7d32; font-weight: 700; }
        .remark-fail { color: #c62828; font-weight: 700; }
        
        /* SUMMARY */
        .pro-summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
        .pro-summary .box { border: 2px solid #0d2818; padding: 12px; text-align: center; border-radius: 6px; background: #f8faf8; }
        .pro-summary .box.gpa { border-color: #2e7d32; background: #e8f5e9; }
        .pro-summary .box.cgpa { border-color: #c62828; background: #ffebee; }
        .pro-summary .box .value { font-size: 1.5rem; font-weight: 900; color: #0d2818; line-height: 1; }
        .pro-summary .box.gpa .value { color: #2e7d32; }
        .pro-summary .box.cgpa .value { color: #c62828; }
        .pro-summary .box .label { font-size: 0.7rem; text-transform: uppercase; color: #6a8f6a; font-weight: 700; margin-top: 5px; }
        
        /* BOTTOM */
        .pro-bottom { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 40px; align-items: end; }
        .pro-bottom .qr-box { text-align: center; }
        .pro-bottom .qr-box img { width: 100px; height: 100px; }
        .pro-bottom .qr-box p { font-size: 0.7rem; color: #0d2818; margin-top: 5px; font-weight: 700; }
        .pro-bottom .sign-box { text-align: center; }
        .pro-bottom .sign-line { border-top: 2px solid #0d2818; padding-top: 30px; margin-bottom: 5px; }
        .pro-bottom .sign-title { font-weight: 800; font-size: 0.85rem; color: #0d2818; text-transform: uppercase; }
        
        .pro-footer { text-align: center; margin-top: 20px; padding-top: 10px; border-top: 1px solid #ccc; font-size: 0.75rem; color: #666; font-style: italic; }
        
        .empty-state { text-align:center; padding:40px; color:#6a8f6a; }
        .empty-state i { font-size:3rem; color:#dce8dc; display:block; margin-bottom:15px; }
        
        /* ============================================ */
        /* PRINT - A4 PORTRAIT */
        /* ============================================ */
        @media print {
            .topbar, .result-nav, .card, .btn, .no-print { 
                display: none !important; 
            }
            
            body { 
                background: white; 
                padding: 0; 
                margin: 0;
                font-size: 11px;
            }
            
            .container { 
                max-width: 100%; 
                padding: 0;
                margin: 0;
            }
            
            .slip-pro { 
                border: 2px solid #000; 
                margin: 0; 
                padding: 12mm;
                width: 100%;
                min-height: 297mm;
                box-sizing: border-box;
            }
            
            .slip-pro::before { 
                opacity: 0.08; 
            }
            
            .pro-table { 
                page-break-inside: avoid;
            }
            
            .pro-table tr {
                page-break-inside: avoid;
            }
            
            .pro-header, .pro-title, .pro-info-wrapper, .pro-summary, .pro-bottom {
                page-break-inside: avoid;
            }
            
            @page { 
                size: A4 portrait; 
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="topbar no-print">
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

        <div class="result-nav no-print">
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

        <!-- SELECT -->
        <div class="card no-print">
            <h2>🎓 Select Student for Result Slip Pro</h2>
            <form method="GET">
                <div class="form-row">
                    <div class="form-group">
                        <label>Student</label>
                        <select name="student_id" required>
                            <option value="">-- Select --</option>
                            <?php while ($s = mysqli_fetch_assoc($students_list)): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo ($student_id == $s['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['reg_no']); ?> — <?php echo htmlspecialchars($s['fullname']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Session</label>
                        <input type="text" name="session" value="<?php echo htmlspecialchars($session); ?>">
                    </div>
                    <div class="form-group">
                        <label>Semester</label>
                        <select name="semester">
                            <option value="First Semester" <?php echo ($semester=='First Semester')?'selected':''; ?>>First Semester</option>
                            <option value="Second Semester" <?php echo ($semester=='Second Semester')?'selected':''; ?>>Second Semester</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Level</label>
                        <select name="level">
                            <option value="NCE I" <?php echo ($level=='NCE I')?'selected':''; ?>>NCE I</option>
                            <option value="NCE II" <?php echo ($level=='NCE II')?'selected':''; ?>>NCE II</option>
                            <option value="NCE III" <?php echo ($level=='NCE III')?'selected':''; ?>>NCE III</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-blue"><i class="fas fa-eye"></i> Show Result Slip Pro</button>
            </form>
        </div>

        <?php if ($student): ?>
        
        <!-- ACTION BUTTONS -->
        <div class="no-print" style="margin-bottom:15px; display:flex; flex-wrap:wrap; gap:8px;">
            <button onclick="window.print()" class="btn btn-green">
                <i class="fas fa-print"></i> Print Result Slip
            </button>
            <a href="?student_id=<?php echo $student_id; ?>&session=<?php echo urlencode($session); ?>&semester=<?php echo urlencode($semester); ?>&level=<?php echo urlencode($level); ?>&download_csv=1" 
               class="btn btn-orange">
                <i class="fas fa-file-csv"></i> Download CSV
            </a>
            <a href="?student_id=<?php echo $student_id; ?>&session=<?php echo urlencode($session); ?>&semester=<?php echo urlencode($semester); ?>&level=<?php echo urlencode($level); ?>&download_pdf=1" 
               class="btn btn-red" onclick="return confirm('Use Print dialog then Save as PDF')">
                <i class="fas fa-file-pdf"></i> Save as PDF
            </a>
        </div>
        
        <div class="slip-pro">
            <!-- HEADER -->
            <div class="pro-header">
                <div class="logo-box">
                    <img src="images/dala-logo.png" alt="Logo" onerror="this.style.display='none'">
                </div>
                <div class="center-box">
                    <div class="school-name">DALA COLLEGE OF EDUCATION, KANO</div>
                    <div class="school-loc">KANO STATE, NIGERIA</div>
                    <div class="accreditation">Accredited By National Commission for Colleges of Education (NCCE), Abuja</div>
                </div>
            </div>
            
            <!-- TITLE -->
            <div class="pro-title"><?php echo strtoupper($semester); ?> RESULT SLIP</div>
            
            <!-- INFO WITH PHOTO ON LEFT -->
            <div class="pro-info-wrapper">
                <div class="pro-photo-left">
                    <img src="uploads/students/<?php echo htmlspecialchars($student['photo'] ?? 'default.png'); ?>" 
                         alt="Student Photo"
                         onerror="this.src='https://via.placeholder.com/150x180/cccccc/333333?text=PHOTO'">
                </div>
                <div class="pro-info">
                    <div class="row"><span class="label">Academic Session:</span><span class="value"><?php echo htmlspecialchars($session); ?></span></div>
                    <div class="row"><span class="label">Student Name:</span><span class="value"><?php echo strtoupper(htmlspecialchars($student['fullname'])); ?></span></div>
                    <div class="row"><span class="label">Admission No:</span><span class="value"><?php echo htmlspecialchars($student['reg_no'] ?? $student['student_id']); ?></span></div>
                    <div class="row"><span class="label">Combination:</span><span class="value"><?php echo htmlspecialchars($student['combination'] ?? $student['course']); ?></span></div>
                    <div class="row"><span class="label">Programme:</span><span class="value"><?php echo htmlspecialchars($student['programme'] ?? 'NCE'); ?></span></div>
                    <div class="row"><span class="label">Level:</span><span class="value"><?php echo htmlspecialchars($level); ?></span></div>
                </div>
            </div>
            
            <!-- TABLE -->
            <?php if (count($results) > 0): ?>
            <table class="pro-table">
                <thead>
                    <tr>
                        <th style="width:45px;">S/N</th>
                        <th style="width:110px;">Course Code</th>
                        <th>Course Title</th>
                        <th style="width:60px;">Unit</th>
                        <th style="width:80px;">Grade</th>
                        <th style="width:80px;">Point</th>
                        <th style="width:100px;">Remark</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($results as $r): 
                        $is_fail = ($r['grade'] == 'F');
                    ?>
                    <tr>
                        <td class="center"><?php echo $i++; ?></td>
                        <td><strong><?php echo htmlspecialchars($r['course_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($r['course_title']); ?></td>
                        <td class="center"><?php echo $r['credit_units']; ?></td>
                        <td class="center grade-<?php echo $r['grade']; ?>"><strong><?php echo $r['grade']; ?></strong></td>
                        <td class="center"><?php echo number_format($r['grade_point'], 1); ?></td>
                        <td class="center <?php echo $is_fail ? 'remark-fail' : 'remark-pass'; ?>">
                            <?php echo $is_fail ? 'Fail' : 'Pass'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- SUMMARY -->
            <div class="pro-summary">
                <div class="box"><div class="value"><?php echo count($results); ?></div><div class="label">Courses</div></div>
                <div class="box"><div class="value"><?php echo $total_units; ?></div><div class="label">Total Units</div></div>
                <div class="box gpa"><div class="value"><?php echo number_format($gpa, 2); ?></div><div class="label">GPA (Semester)</div></div>
                <div class="box cgpa"><div class="value"><?php echo number_format($cgpa, 2); ?></div><div class="label">CGPA (Cumulative)</div></div>
            </div>
            
            <div style="text-align:center; margin-bottom:20px;">
                <div style="display:inline-block; padding:10px 30px; background:#f8faf8; border:2px solid #0d2818; border-radius:30px;">
                    <strong style="color:#0d2818;">Class of Result:</strong>
                    <strong style="color:#c62828; font-size:1.1rem; margin-left:10px;"><?php echo $classification; ?></strong>
                </div>
            </div>
            
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <h3>No results</h3>
                <p>No results for this student in <?php echo htmlspecialchars($semester); ?> — <?php echo htmlspecialchars($session); ?>.</p>
            </div>
            <?php endif; ?>
            
            <!-- BOTTOM: QR + Registrar ONLY -->
            <?php if (count($results) > 0): ?>
            <div class="pro-bottom">
                <div class="qr-box">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo urlencode('https://dala-portal.local/verify.php?reg_no=' . ($student['reg_no'] ?? $student['student_id'])); ?>" alt="QR">
                    <p>Scan To Verify</p>
                </div>
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <div class="sign-title">Registrar</div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="pro-footer">
                This is a computer-generated result slip. No signature is required.
                <br>Generated on <?php echo date('d-M-Y H:i A'); ?>
            </div>
        </div>
        
        <?php elseif ($student_id > 0): ?>
            <div class="card"><p style="text-align:center; padding:40px; color:#c62828;">❌ Student not found</p></div>
        <?php endif; ?>
    </div>
</body>
</html>