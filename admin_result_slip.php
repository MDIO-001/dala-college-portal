<?php
session_start();
include 'connect.php';
include 'result_functions.php';

// ============================================
// SESSION CHECK
// ============================================
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// ============================================
// ROLE CHECK - Yana karɓar duk staff roles
// ============================================
$user_role = $_SESSION['role'] ?? '';

// Idan role ɗin 'staff' ne kawai, sai a ɗauko ainihin role daga staff table
if ($user_role == 'staff' || empty($user_role)) {
    $staff_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM staff WHERE id = {$_SESSION['user_id']}"));
    if ($staff_check) {
        $user_role = $staff_check['role']; // 'Exam Officer', 'Provost', da sauransu
        $_SESSION['role'] = $user_role; // Sabunta session
        $_SESSION['position'] = $staff_check['position'] ?? $staff_check['role'];
    }
}

// Roles ɗin da aka yarda su shiga Result Slip
$allowed_roles = ['admin', 'Exam Officer', 'Provost', 'Accountant', 'Bursary', 'Admission Officer'];

if (!in_array($user_role, $allowed_roles)) {
    // Nuna kuskure maimakon mayar da kai login
    echo "<div style='background:#ffebee; padding:30px; font-family:Arial; text-align:center; margin:50px auto; max-width:600px; border-radius:12px;'>";
    echo "<h2 style='color:#c62828;'>❌ Ba ka da izinin shiga wannan shafin</h2>";
    echo "<p style='margin:15px 0;'>Role ɗinka: <strong>" . htmlspecialchars($user_role) . "</strong></p>";
    echo "<p style='margin:15px 0;'>Allowed roles: " . implode(', ', $allowed_roles) . "</p>";
    echo "<a href='staff_dashboard.php' style='display:inline-block; padding:10px 25px; background:#2e7d32; color:white; text-decoration:none; border-radius:8px; font-weight:bold;'>Koma Dashboard</a>";
    echo "</div>";
    exit();
}

$admin_name = $_SESSION['fullname'] ?? 'Staff';

// ============================================
// GET STUDENT
// ============================================
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$session = isset($_GET['session']) ? mysqli_real_escape_string($conn, $_GET['session']) : getSetting($conn, 'current_session');
$semester = isset($_GET['semester']) ? mysqli_real_escape_string($conn, $_GET['semester']) : 'First Semester';
$level = isset($_GET['level']) ? mysqli_real_escape_string($conn, $_GET['level']) : '';

$student = null;
$results = [];
$total_units = 0;
$total_points = 0;
$gpa = 0;

if ($student_id > 0) {
    $student = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM students WHERE id = $student_id"));
    
    if ($student) {
        if (empty($level)) $level = $student['level'];
        
        // Samo results
        $rq = "SELECT * FROM results 
               WHERE student_id = $student_id 
               AND session = '$session' 
               AND semester = '$semester' 
               AND level = '$level'
               ORDER BY course_code";
        $rr = mysqli_query($conn, $rq);
        while ($r = mysqli_fetch_assoc($rr)) {
            $results[] = $r;
            $total_units += $r['credit_units'];
            $total_points += $r['grade_point'] * $r['credit_units'];
        }
        
        $gpa = $total_units > 0 ? round($total_points / $total_units, 2) : 0;
    }
}

// Samo students don dropdown
$students_list = mysqli_query($conn, "SELECT id, reg_no, fullname, combination, level FROM students ORDER BY fullname");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Result Slip - Dala College</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Tahoma,sans-serif; background:#f0f4f8; padding:20px; }
        .container { max-width:1200px; margin:0 auto; }
        
        .topbar { background:#0d2818; padding:15px 25px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; border-radius:12px; margin-bottom:20px; }
        .topbar .logo-title { color:#ffd54f; font-size:1.3rem; font-weight:800; }
        .topbar .logo-title span { color:#a5d6a7; }
        .topbar nav a { color:#c8e6c9; text-decoration:none; padding:8px 16px; border-radius:25px; font-size:0.85rem; }
        .topbar nav a:hover { background:#2e7d32; }
        .topbar nav .logout { background:#c62828; color:white !important; }
        
        .result-nav { background:white; padding:15px; border-radius:12px; margin-bottom:20px; display:flex; gap:8px; flex-wrap:wrap; align-items:center; box-shadow:0 2px 10px rgba(0,0,0,0.05); }
        .result-nav .label { font-weight:700; color:#0d2818; margin-right:10px; font-size:0.85rem; }
        .result-nav a { padding:8px 15px; border-radius:6px; text-decoration:none; font-weight:600; font-size:0.75rem; }
        .r-course { background:#2e7d32; color:white; }
        .r-grade { background:#f9a825; color:#0d2818; }
        .r-entry { background:#8d2c2c; color:white; }
        .r-slip { background:#e53935; color:white; box-shadow:0 0 0 3px #ffd54f; }
        .r-slip-pro { background:#a5d6a7; color:#0d2818; }
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
        
        .btn { padding:10px 25px; border:none; border-radius:8px; font-weight:700; font-size:0.9rem; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-green { background:#2e7d32; color:white; }
        .btn-blue { background:#1976d2; color:white; }
        .btn-red { background:#c62828; color:white; }
        
        /* RESULT SLIP DESIGN */
        .slip-container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-top: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border: 2px solid #0d2818;
        }
        
        .slip-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px double #0d2818;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .slip-header .logo-left {
            width: 90px;
            height: 90px;
            flex-shrink: 0;
        }
        .slip-header .logo-left img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .slip-header .header-center {
            flex: 1;
            text-align: center;
            padding: 0 20px;
        }
        .slip-header .school-name {
            font-size: 1.6rem;
            font-weight: 900;
            color: #0d2818;
            letter-spacing: 1px;
            margin-bottom: 3px;
        }
        .slip-header .school-address {
            font-size: 0.8rem;
            color: #2e7d32;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .slip-header .school-motto {
            font-size: 0.75rem;
            color: #6a8f6a;
            font-style: italic;
        }
        
        .slip-header .photo-right {
            width: 90px;
            height: 110px;
            border: 2px solid #0d2818;
            border-radius: 4px;
            overflow: hidden;
            flex-shrink: 0;
            background: #f0f0f0;
        }
        .slip-header .photo-right img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .slip-title {
            text-align: center;
            background: #0d2818;
            color: white;
            padding: 8px;
            font-size: 1rem;
            font-weight: 800;
            letter-spacing: 2px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        
        /* Student Info Grid */
        .student-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0 30px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .student-details .row {
            display: flex;
            padding: 6px 0;
            border-bottom: 1px dotted #ccc;
        }
        .student-details .label {
            font-weight: 700;
            color: #0d2818;
            min-width: 150px;
        }
        .student-details .value {
            color: #000;
            font-weight: 600;
        }
        
        /* Result Table */
        .result-slip-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .result-slip-table th {
            background: #0d2818;
            color: white;
            padding: 10px;
            text-align: left;
            font-size: 0.8rem;
            text-transform: uppercase;
            border: 1px solid #0d2818;
        }
        .result-slip-table td {
            padding: 8px 10px;
            border: 1px solid #ccc;
            font-size: 0.9rem;
        }
        .result-slip-table tr:nth-child(even) td {
            background: #f8faf8;
        }
        .result-slip-table .center { text-align: center; }
        
        /* Summary */
        .result-summary {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .summary-box {
            background: #f8faf8;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #2e7d32;
        }
        .summary-box .row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 0.9rem;
        }
        .summary-box .row .label {
            font-weight: 600;
            color: #0d2818;
        }
        .summary-box .row .value {
            font-weight: 800;
            color: #2e7d32;
            font-size: 1rem;
        }
        
        /* Signature */
        .signature-area {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            margin-top: 40px;
            padding-top: 20px;
        }
        .signature-area .sign-box {
            text-align: center;
        }
        .signature-area .sign-line {
            border-top: 2px solid #0d2818;
            padding-top: 8px;
            margin-bottom: 5px;
        }
        .signature-area .sign-title {
            font-weight: 800;
            font-size: 0.85rem;
            color: #0d2818;
            text-transform: uppercase;
        }
        
        .empty-state { text-align:center; padding:40px; color:#6a8f6a; }
        .empty-state i { font-size:3rem; color:#dce8dc; display:block; margin-bottom:15px; }
        
        @media print {
            .topbar, .result-nav, .card, .no-print, .btn { display: none !important; }
            body { background: white; padding: 0; }
            .container { max-width: 100%; }
            .slip-container { box-shadow: none; border: 1px solid #000; margin: 0; }
            @page { size: A4; margin: 10mm; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="topbar no-print">
            <div>
                <span class="logo-title">DALA <span>COLLEGE</span></span>
                <span style="display:block; font-size:0.55rem; color:#c8e6c9;">Result System</span>
            </div>
            <nav>
                <a href="staff_dashboard.php">Dashboard</a>
                <a href="logout.php" class="logout">Logout</a>
            </nav>
            <div>
                <span style="background:#c62828; color:white; padding:6px 18px; border-radius:20px; font-size:0.8rem; font-weight:600;">
                    👤 <?php echo htmlspecialchars($admin_name); ?> (<?php echo htmlspecialchars($user_role); ?>)
                </span>
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

        <!-- SELECT STUDENT -->
        <div class="card no-print">
            <h2>🔍 Zaɓi Dalibi</h2>
            <form method="GET">
                <div class="form-row">
                    <div class="form-group">
                        <label>Student</label>
                        <select name="student_id" required>
                            <option value="">-- Select Student --</option>
                            <?php while ($s = mysqli_fetch_assoc($students_list)): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo ($student_id == $s['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['reg_no'] ?? ''); ?> — <?php echo htmlspecialchars($s['fullname']); ?> (<?php echo htmlspecialchars($s['level']); ?>)
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
                            <option value="First Semester" <?php echo ($semester == 'First Semester') ? 'selected' : ''; ?>>First Semester</option>
                            <option value="Second Semester" <?php echo ($semester == 'Second Semester') ? 'selected' : ''; ?>>Second Semester</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Level</label>
                        <select name="level">
                            <option value="NCE I" <?php echo ($level == 'NCE I') ? 'selected' : ''; ?>>NCE I</option>
                            <option value="NCE II" <?php echo ($level == 'NCE II') ? 'selected' : ''; ?>>NCE II</option>
                            <option value="NCE III" <?php echo ($level == 'NCE III') ? 'selected' : ''; ?>>NCE III</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-blue"><i class="fas fa-search"></i> Nuna Result Slip</button>
            </form>
        </div>

        <?php if ($student): ?>
        
        <!-- PRINT BUTTON -->
        <div class="no-print" style="margin-bottom:15px;">
            <button onclick="window.print()" class="btn btn-green">
                <i class="fas fa-print"></i> Print Result Slip
            </button>
            <a href="admin_result_entry.php?combination=<?php echo urlencode($student['combination']); ?>&level=<?php echo urlencode($level); ?>&semester=<?php echo urlencode($semester); ?>" class="btn btn-blue">
                <i class="fas fa-edit"></i> Edit Results
            </a>
        </div>
        
        <!-- RESULT SLIP -->
        <div class="slip-container" id="resultSlip">
            
            <!-- HEADER -->
            <div class="slip-header">
                <div class="logo-left">
                    <img src="images/dala-logo.png" alt="Dala College Logo" onerror="this.style.display='none'">
                </div>
                <div class="header-center">
                    <div class="school-name">DALA COLLEGE OF EDUCATION, KANO</div>
                    <div class="school-address">KANO STATE, NIGERIA</div>
                    <div class="school-motto">Approved by National Commission for Colleges of Education (NCCE)</div>
                </div>
                <div class="photo-right">
                    <img src="uploads/students/<?php echo htmlspecialchars($student['photo'] ?? 'default.png'); ?>" 
                         alt="Student Photo"
                         onerror="this.src='https://via.placeholder.com/90x110/cccccc/333333?text=PHOTO'">
                </div>
            </div>
            
            <!-- TITLE -->
            <div class="slip-title">
                <?php echo strtoupper($semester); ?> RESULT SLIP
            </div>
            
            <!-- STUDENT DETAILS -->
            <div class="student-details">
                <div class="row">
                    <span class="label">Academic Session:</span>
                    <span class="value"><?php echo htmlspecialchars($session); ?></span>
                </div>
                <div class="row">
                    <span class="label">Student Name:</span>
                    <span class="value"><?php echo strtoupper(htmlspecialchars($student['fullname'])); ?></span>
                </div>
                <div class="row">
                    <span class="label">Semester:</span>
                    <span class="value"><?php echo htmlspecialchars($semester); ?></span>
                </div>
                <div class="row">
                    <span class="label">Combination:</span>
                    <span class="value"><?php echo htmlspecialchars($student['combination'] ?? $student['course']); ?></span>
                </div>
                <div class="row">
                    <span class="label">Admission No:</span>
                    <span class="value"><?php echo htmlspecialchars($student['reg_no'] ?? $student['student_id']); ?></span>
                </div>
                <div class="row">
                    <span class="label">Programme:</span>
                    <span class="value"><?php echo htmlspecialchars($student['programme'] ?? 'NCE'); ?></span>
                </div>
                <div class="row">
                    <span class="label">Reg No:</span>
                    <span class="value"><?php echo htmlspecialchars($student['reg_no'] ?? $student['student_id']); ?></span>
                </div>
                <div class="row">
                    <span class="label">Level:</span>
                    <span class="value"><?php echo htmlspecialchars($level); ?></span>
                </div>
            </div>
            
            <!-- RESULT TABLE -->
            <?php if (count($results) > 0): ?>
            <table class="result-slip-table">
                <thead>
                    <tr>
                        <th style="width:50px;">S/N</th>
                        <th style="width:100px;">Course Code</th>
                        <th>Course Title</th>
                        <th style="width:60px;" class="center">Unit</th>
                        <th style="width:70px;" class="center">Grade</th>
                        <th style="width:100px;" class="center">Remark</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($results as $r): ?>
                    <tr>
                        <td class="center"><?php echo $i++; ?></td>
                        <td><strong><?php echo htmlspecialchars($r['course_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($r['course_title']); ?></td>
                        <td class="center"><?php echo $r['credit_units']; ?></td>
                        <td class="center"><strong><?php echo htmlspecialchars($r['grade']); ?></strong></td>
                        <td class="center"><?php echo strtoupper(htmlspecialchars($r['remark'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <h3>Babu sakamako</h3>
                <p>Babu results na wannan dalibi a <?php echo htmlspecialchars($semester); ?> — <?php echo htmlspecialchars($session); ?>.</p>
            </div>
            <?php endif; ?>
            
            <!-- SUMMARY -->
            <?php if (count($results) > 0): ?>
            <div class="result-summary">
                <div class="summary-box">
                    <div class="row">
                        <span class="label">Total Units:</span>
                        <span class="value"><?php echo $total_units; ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Total Points:</span>
                        <span class="value"><?php echo $total_points; ?></span>
                    </div>
                </div>
                <div class="summary-box">
                    <div class="row">
                        <span class="label">GPA:</span>
                        <span class="value"><?php echo number_format($gpa, 2); ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Class:</span>
                        <span class="value"><?php echo getClassOfDegree($gpa); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- SIGNATURE -->
            <div class="signature-area">
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <div class="sign-title">Academic Secretary</div>
                </div>
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <div class="sign-title">H.O.D</div>
                </div>
            </div>
            
            <div style="text-align:center; margin-top:20px; font-size:0.75rem; color:#666;">
                This is a computer-generated result slip. No signature is required.
            </div>
        </div>
        
        <?php elseif ($student_id > 0): ?>
            <div class="card">
                <div class="empty-state">
                    <i class="fas fa-user-slash"></i>
                    <h3>Ba a sami dalibi ba</h3>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>