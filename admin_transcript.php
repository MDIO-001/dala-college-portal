<?php
session_start();
include 'connect.php';
include 'result_functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'exam_officer'])) {
    header('Location: login.php');
    exit();
}

$admin_name = $_SESSION['fullname'] ?? 'Admin';

// ============================================
// DOWNLOAD CSV
// ============================================
if (isset($_GET['download_csv']) && isset($_GET['student_id'])) {
    $sid = intval($_GET['student_id']);
    $student = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM students WHERE id = $sid"));
    if (!$student) exit();
    
    $rq = "SELECT * FROM results WHERE student_id = $sid ORDER BY session, FIELD(semester, 'First Semester', 'Second Semester'), course_code";
    $rr = mysqli_query($conn, $rq);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="transcript_' . str_replace('/', '_', $student['reg_no']) . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['DALA COLLEGE OF EDUCATION, KANO']);
    fputcsv($output, ['ACADEMIC TRANSCRIPT']);
    fputcsv($output, []);
    fputcsv($output, ['Student Name:', $student['fullname']]);
    fputcsv($output, ['Reg No:', $student['reg_no']]);
    fputcsv($output, ['Combination:', $student['combination']]);
    fputcsv($output, ['Programme:', $student['programme']]);
    fputcsv($output, []);
    fputcsv($output, ['Session', 'Semester', 'Level', 'Course Code', 'Course Title', 'Unit', 'Grade', 'Point', 'Remark']);
    
    while ($r = mysqli_fetch_assoc($rr)) {
        fputcsv($output, [$r['session'], $r['semester'], $r['level'], $r['course_code'], $r['course_title'], $r['credit_units'], $r['grade'], number_format($r['grade_point'], 1), $r['remark']]);
    }
    
    fputcsv($output, []);
    fputcsv($output, ['CGPA:', number_format(getStudentCGPA($conn, $sid), 2)]);
    fputcsv($output, ['Classification:', getGradeClassification(getStudentCGPA($conn, $sid))]);
    
    fclose($output);
    exit();
}

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

$student = null;
$semesters = [];
$cgpa = 0;
$classification = '';
$total_units_all = 0;
$total_points_all = 0;

if ($student_id > 0) {
    $student = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM students WHERE id = $student_id"));
    
    if ($student) {
        $rq = "SELECT * FROM results 
               WHERE student_id = $student_id 
               ORDER BY session, 
               FIELD(semester, 'First Semester', 'Second Semester'),
               FIELD(level, 'NCE I', 'NCE II', 'NCE III'),
               course_code";
        $rr = mysqli_query($conn, $rq);
        
        while ($r = mysqli_fetch_assoc($rr)) {
            $key = $r['session'] . '|' . $r['level'] . '|' . $r['semester'];
            $semesters[$key]['session'] = $r['session'];
            $semesters[$key]['level'] = $r['level'];
            $semesters[$key]['semester'] = $r['semester'];
            $semesters[$key]['courses'][] = $r;
            
            if ($r['grade'] != 'F') {
                $total_units_all += $r['credit_units'];
                $total_points_all += $r['grade_point'] * $r['credit_units'];
            }
        }
        
        $cgpa = $total_units_all > 0 ? round($total_points_all / $total_units_all, 2) : 0;
        $classification = getGradeClassification($cgpa);
    }
}

$students_list = mysqli_query($conn, "SELECT id, reg_no, fullname, combination, level FROM students ORDER BY fullname");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transcript - Admin</title>
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
        .r-slip-pro { background:#a5d6a7; color:#0d2818; }
        .r-transcript { background:#5d4037; color:white; box-shadow:0 0 0 3px #ffd54f; }
        .r-final { background:#558b2f; color:white; }
        .r-settings { background:#e65100; color:white; }
        .r-database { background:#37474f; color:white; }
        .r-statement { background:#455a64; color:white; }
        
        .card { background:white; padding:25px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); margin-bottom:20px; }
        .card h2 { color:#0d2818; margin-bottom:15px; font-size:1.2rem; }
        
        .form-row { display:grid; grid-template-columns:1fr auto; gap:15px; align-items:end; }
        .form-group label { display:block; font-weight:600; color:#1a2e1a; margin-bottom:5px; font-size:0.85rem; }
        .form-group select { width:100%; padding:10px; border:2px solid #dce8dc; border-radius:8px; font-size:0.9rem; }
        
        .btn { padding:10px 25px; border:none; border-radius:8px; font-weight:700; font-size:0.9rem; cursor:pointer; text-decoration:none; display:inline-block; margin-right:8px; }
        .btn-green { background:#2e7d32; color:white; }
        .btn-green:hover { background:#1b5e20; }
        .btn-blue { background:#1976d2; color:white; }
        .btn-orange { background:#f57c00; color:white; }
        .btn-red { background:#c62828; color:white; }
        
        /* TRANSCRIPT DESIGN */
        .transcript { 
            background: white; 
            padding: 30px; 
            border: 3px double #0d2818; 
            margin-top: 20px; 
            font-family: 'Times New Roman', serif; 
            position: relative; 
            overflow: hidden;
        }
        
        .transcript::before { 
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
        
        .transcript > * { position: relative; z-index: 1; }
        
        .trans-header { 
            display: grid; 
            grid-template-columns: 150px 1fr; 
            gap: 20px; 
            align-items: center; 
            padding-bottom: 15px; 
            border-bottom: 3px double #0d2818; 
            margin-bottom: 12px; 
        }
        .trans-header .logo-box { width: 150px; height: 150px; }
        .trans-header .logo-box img { width: 100%; height: 100%; object-fit: contain; }
        .trans-header .center-box { text-align: center; }
        .trans-header .school-name { font-size: 1.8rem; font-weight: 900; color: #0d2818; letter-spacing: 1.5px; }
        .trans-header .school-loc { font-size: 1rem; font-weight: 700; color: #0d2818; margin: 3px 0; }
        .trans-header .accreditation { font-size: 0.8rem; color: #c62828; font-weight: 700; border-top: 1px solid #c62828; border-bottom: 1px solid #c62828; padding: 3px 0; display: inline-block; margin-top: 5px; }
        
        .trans-title { text-align: center; font-size: 1.3rem; font-weight: 900; letter-spacing: 3px; color: white; background: #0d2818; padding: 10px; margin-bottom: 20px; text-transform: uppercase; }
        
        /* INFO WITH PHOTO ON LEFT */
        .trans-info-wrapper {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 25px;
            margin-bottom: 25px;
            align-items: start;
        }
        
        .trans-photo-left {
            width: 150px;
            height: 180px;
            border: 3px solid #0d2818;
            border-radius: 6px;
            overflow: hidden;
            background: #f0f0f0;
            box-shadow: 4px 4px 10px rgba(0,0,0,0.2);
        }
        
        .trans-photo-left img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        
        .trans-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0 30px;
            font-size: 0.95rem;
        }
        
        .trans-info .row { display: flex; padding: 6px 0; border-bottom: 1px dotted #999; }
        .trans-info .label { font-weight: 800; color: #0d2818; min-width: 140px; }
        .trans-info .value { font-weight: 700; color: #000; }
        
        /* SEMESTER BLOCKS */
        .sem-block {
            margin-bottom: 25px;
            border: 2px solid #0d2818;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .sem-header {
            background: #2e7d32;
            color: white;
            padding: 10px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 800;
            font-size: 0.95rem;
        }
        
        .sem-header .meta {
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .sem-table { width: 100%; border-collapse: collapse; }
        .sem-table th { 
            background: #e8f5e9; 
            color: #0d2818; 
            padding: 8px; 
            font-size: 0.75rem; 
            text-transform: uppercase; 
            border-bottom: 1px solid #0d2818;
            text-align: left;
        }
        .sem-table td { 
            padding: 7px 10px; 
            border-bottom: 1px solid #eee; 
            font-size: 0.85rem; 
        }
        .sem-table tr:last-child td { border-bottom: none; }
        .sem-table tr:hover td { background: #f8faf8; }
        .sem-table .center { text-align: center; }
        
        .grade-A { color: #2e7d32; font-weight: 700; }
        .grade-B { color: #0d47a1; font-weight: 700; }
        .grade-C { color: #e65100; font-weight: 700; }
        .grade-D { color: #6a1b9a; font-weight: 700; }
        .grade-E { color: #c2185b; font-weight: 700; }
        .grade-F { color: #c62828; font-weight: 700; }
        
        /* CGPA SUMMARY */
        .cgpa-box {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .cgpa-box .box {
            border: 2px solid #0d2818;
            padding: 15px;
            text-align: center;
            border-radius: 6px;
            background: #f8faf8;
        }
        .cgpa-box .box.cgpa { border-color: #c62828; background: #ffebee; }
        .cgpa-box .box.class { border-color: #f57c00; background: #fff3e0; }
        .cgpa-box .box .value { font-size: 1.8rem; font-weight: 900; color: #0d2818; line-height: 1; }
        .cgpa-box .box.cgpa .value { color: #c62828; }
        .cgpa-box .box.class .value { color: #f57c00; font-size: 1.1rem; }
        .cgpa-box .box .label { font-size: 0.75rem; text-transform: uppercase; color: #6a8f6a; font-weight: 700; margin-top: 5px; }
        
        /* BOTTOM */
        .trans-bottom { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 40px; align-items: end; }
        .trans-bottom .qr-box { text-align: center; }
        .trans-bottom .qr-box img { width: 100px; height: 100px; }
        .trans-bottom .qr-box p { font-size: 0.7rem; color: #0d2818; margin-top: 5px; font-weight: 700; }
        .trans-bottom .sign-box { text-align: center; }
        .trans-bottom .sign-line { border-top: 2px solid #0d2818; padding-top: 30px; margin-bottom: 5px; }
        .trans-bottom .sign-title { font-weight: 800; font-size: 0.85rem; color: #0d2818; text-transform: uppercase; }
        
        .trans-footer { text-align: center; margin-top: 20px; padding-top: 10px; border-top: 1px solid #ccc; font-size: 0.75rem; color: #666; font-style: italic; }
        
        .empty-state { text-align:center; padding:40px; color:#6a8f6a; }
        .empty-state i { font-size:3rem; color:#dce8dc; display:block; margin-bottom:15px; }
        
        @media print {
            .topbar, .result-nav, .card, .btn, .no-print { display: none !important; }
            body { background: white; padding: 0; }
            .container { max-width: 100%; }
            .transcript { border: 2px solid #000; margin: 0; padding: 20px; }
            .transcript::before { opacity: 0.08; }
            @page { size: A4; margin: 8mm; }
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

        <div class="card no-print">
            <h2>🎓 Select Student for Transcript</h2>
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
                    <button type="submit" class="btn btn-blue"><i class="fas fa-eye"></i> Show Transcript</button>
                </div>
            </form>
        </div>

        <?php if ($student): ?>
        
        <div class="no-print" style="margin-bottom:15px; display:flex; flex-wrap:wrap; gap:8px;">
            <button onclick="window.print()" class="btn btn-green">
                <i class="fas fa-print"></i> Print Transcript
            </button>
            <a href="?student_id=<?php echo $student_id; ?>&download_csv=1" class="btn btn-orange">
                <i class="fas fa-file-csv"></i> Download CSV
            </a>
        </div>
        
        <div class="transcript">
            <!-- HEADER -->
            <div class="trans-header">
                <div class="logo-box">
                    <img src="images/dala-logo.png" alt="Logo" onerror="this.style.display='none'">
                </div>
                <div class="center-box">
                    <div class="school-name">DALA COLLEGE OF EDUCATION, KANO</div>
                    <div class="school-loc">KANO STATE, NIGERIA</div>
                    <div class="accreditation">Accredited By National Commission for Colleges of Education (NCCE), Abuja</div>
                </div>
            </div>
            
            <div class="trans-title">ACADEMIC TRANSCRIPT</div>
            
            <!-- INFO WITH PHOTO ON LEFT -->
            <div class="trans-info-wrapper">
                <div class="trans-photo-left">
                    <img src="uploads/students/<?php echo htmlspecialchars($student['photo'] ?? 'default.png'); ?>" 
                         alt="Student Photo"
                         onerror="this.src='https://via.placeholder.com/150x180/cccccc/333333?text=PHOTO'">
                </div>
                <div class="trans-info">
                    <div class="row"><span class="label">Student Name:</span><span class="value"><?php echo strtoupper(htmlspecialchars($student['fullname'])); ?></span></div>
                    <div class="row"><span class="label">Admission No:</span><span class="value"><?php echo htmlspecialchars($student['reg_no'] ?? $student['student_id']); ?></span></div>
                    <div class="row"><span class="label">Combination:</span><span class="value"><?php echo htmlspecialchars($student['combination'] ?? $student['course']); ?></span></div>
                    <div class="row"><span class="label">Programme:</span><span class="value"><?php echo htmlspecialchars($student['programme'] ?? 'NCE'); ?></span></div>
                    <div class="row"><span class="label">Level:</span><span class="value"><?php echo htmlspecialchars($student['level']); ?></span></div>
                    <div class="row"><span class="label">Graduation Year:</span><span class="value"><?php echo htmlspecialchars(getGraduationYear($conn, $student_id)); ?></span></div>
                </div>
            </div>
            
            <!-- SEMESTER BLOCKS -->
            <?php if (!empty($semesters)): ?>
                <?php foreach ($semesters as $key => $block): 
                    $sem_units = 0; $sem_points = 0;
                    foreach ($block['courses'] as $c) {
                        if ($c['grade'] != 'F') {
                            $sem_units += $c['credit_units'];
                            $sem_points += $c['grade_point'] * $c['credit_units'];
                        }
                    }
                    $sem_gpa = $sem_units > 0 ? round($sem_points / $sem_units, 2) : 0;
                ?>
                <div class="sem-block">
                    <div class="sem-header">
                        <span>
                            <i class="fas fa-book"></i> 
                            <?php echo htmlspecialchars($block['level']); ?> — 
                            <?php echo htmlspecialchars($block['semester']); ?> — 
                            <?php echo htmlspecialchars($block['session']); ?>
                        </span>
                        <span class="meta">
                            Units: <strong><?php echo $sem_units; ?></strong> | 
                            GPA: <strong><?php echo number_format($sem_gpa, 2); ?></strong>
                        </span>
                    </div>
                    <table class="sem-table">
                        <thead>
                            <tr>
                                <th style="width:40px;">S/N</th>
                                <th style="width:100px;">Code</th>
                                <th>Course Title</th>
                                <th style="width:50px;">Unit</th>
                                <th style="width:60px;">Grade</th>
                                <th style="width:60px;">Point</th>
                                <th style="width:90px;">Remark</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($block['courses'] as $c): ?>
                            <tr>
                                <td class="center"><?php echo $i++; ?></td>
                                <td><strong><?php echo htmlspecialchars($c['course_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($c['course_title']); ?></td>
                                <td class="center"><?php echo $c['credit_units']; ?></td>
                                <td class="center grade-<?php echo $c['grade']; ?>"><?php echo $c['grade']; ?></td>
                                <td class="center"><?php echo number_format($c['grade_point'], 1); ?></td>
                                <td class="center"><?php echo htmlspecialchars($c['remark']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <h3>No results</h3>
                    <p>No results for this student.</p>
                </div>
            <?php endif; ?>
            
            <!-- CGPA SUMMARY -->
            <?php if (!empty($semesters)): ?>
            <div class="cgpa-box">
                <div class="box">
                    <div class="value"><?php echo $total_units_all; ?></div>
                    <div class="label">Total Units</div>
                </div>
                <div class="box cgpa">
                    <div class="value"><?php echo number_format($cgpa, 2); ?></div>
                    <div class="label">CGPA</div>
                </div>
                <div class="box class">
                    <div class="value"><?php echo $classification; ?></div>
                    <div class="label">Classification</div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- BOTTOM: QR + Registrar -->
            <?php if (!empty($semesters)): ?>
            <div class="trans-bottom">
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
            
            <div class="trans-footer">
                This is a computer-generated transcript. No signature is required.
                <br>Generated on <?php echo date('d-M-Y H:i A'); ?>
            </div>
        </div>
        
        <?php elseif ($student_id > 0): ?>
            <div class="card"><p style="text-align:center; padding:40px; color:#c62828;">❌ Student not found</p></div>
        <?php endif; ?>
    </div>
</body>
</html>