<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header('Location: login.php');
    exit();
}

$staff_id = $_SESSION['user_id'];

// ============================================
// GET STAFF DATA
// ============================================
$stmt = $conn->prepare("SELECT * FROM staff WHERE id = ?");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$result = $stmt->get_result();
$staff = $result->fetch_assoc();

if (!$staff) {
    die("Staff not found!");
}

// ============================================
// GET FILTER VALUES
// ============================================
$selected_combination = isset($_GET['combination']) ? mysqli_real_escape_string($conn, $_GET['combination']) : '';
$selected_level = isset($_GET['level']) ? mysqli_real_escape_string($conn, $_GET['level']) : '';
$selected_semester = isset($_GET['semester']) ? mysqli_real_escape_string($conn, $_GET['semester']) : '';
$selected_session = isset($_GET['session']) ? mysqli_real_escape_string($conn, $_GET['session']) : '2024/2025';
$selected_student = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

$levels = ['NCEI', 'NCEII', 'NCEIII'];
$semesters = ['First Semester', 'Second Semester'];
$sessions = ['2024/2025', '2023/2024', '2022/2023'];

// ============================================
// GET ALL COMBINATIONS
// ============================================
$comb_query = "SELECT DISTINCT combination FROM students WHERE combination IS NOT NULL AND combination != '' ORDER BY combination";
$comb_result = mysqli_query($conn, $comb_query);
$combinations = [];
if ($comb_result) {
    while ($row = mysqli_fetch_assoc($comb_result)) {
        $combinations[] = $row['combination'];
    }
}

// ============================================
// LOAD STUDENTS BASED ON FILTER
// ============================================
$students = [];
if (!empty($selected_combination) && !empty($selected_level)) {
    $student_query = "SELECT id, reg_no, fullname, combination, level, programme 
                      FROM students 
                      WHERE combination = '$selected_combination' 
                      AND REPLACE(level, ' ', '') = '$selected_level'
                      ORDER BY fullname ASC";
    $student_result = mysqli_query($conn, $student_query);
    if ($student_result) {
        while ($row = mysqli_fetch_assoc($student_result)) {
            $students[] = $row;
        }
    }
}

// ============================================
// GET SELECTED STUDENT'S RESULTS
// ============================================
$student_info = null;
$results = [];
$total_units = 0;
$total_points = 0;
$total_courses = 0;
$gpa = 0.00;

if ($selected_student > 0 && !empty($selected_level) && !empty($selected_semester)) {
    // Get student info
    $s_query = "SELECT * FROM students WHERE id = $selected_student";
    $s_result = mysqli_query($conn, $s_query);
    $student_info = mysqli_fetch_assoc($s_result);
    
    if ($student_info) {
        // Get results
        $r_query = "SELECT * FROM results 
                    WHERE student_id = $selected_student 
                    AND level = '$selected_level' 
                    AND semester = '$selected_semester' 
                    AND academic_year = '$selected_session'
                    AND status IN ('approved', 'published')
                    ORDER BY course_code";
        $r_result = mysqli_query($conn, $r_query);
        
        if ($r_result) {
            while ($row = mysqli_fetch_assoc($r_result)) {
                $results[] = $row;
                // Idan babu credits a results, yi amfani da 3
                $credit = isset($row['credits']) ? $row['credits'] : 3;
                $total_units += $credit;
                $total_points += ($row['grade_point'] * $credit);
                $total_courses++;
            }
        }
        
        $gpa = ($total_units > 0) ? round($total_points / $total_units, 2) : 0.00;
    }
}

// ============================================
// STUDENT PHOTO (idan an zaɓi ɗalibi)
// ============================================
$photo_path = "uploads/students/default.png";
if ($student_info) {
    if (!empty($student_info['photo']) && file_exists("uploads/students/" . $student_info['photo'])) {
        $photo_path = "uploads/students/" . $student_info['photo'];
    } elseif (!empty($student_info['reg_no']) && file_exists("uploads/students/" . $student_info['reg_no'] . ".jpg")) {
        $photo_path = "uploads/students/" . $student_info['reg_no'] . ".jpg";
    } elseif (!empty($student_info['reg_no']) && file_exists("uploads/students/" . $student_info['reg_no'] . ".png")) {
        $photo_path = "uploads/students/" . $student_info['reg_no'] . ".png";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Results - Staff</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f4f8;
            padding: 20px;
        }
        .topbar {
            background: #0d2818;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            border-radius: 12px;
            margin-bottom: 20px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        .topbar .logo-title { color: #ffd54f; font-size: 1.3rem; font-weight: 800; }
        .topbar .logo-title span { color: #a5d6a7; }
        .topbar .logo-sub { display: block; font-size: 0.6rem; color: #c8e6c9; }
        .topbar nav a {
            color: #c8e6c9;
            text-decoration: none;
            padding: 8px 18px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        .topbar nav a:hover { background: #2e7d32; }
        .topbar nav a.active { background: #ffd54f; color: #0d2818 !important; font-weight: 700; }
        .topbar nav a.logout { background: #c62828; color: white !important; }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 30px rgba(0,0,0,0.1);
        }
        h1 { color: #0d2818; margin-bottom: 5px; }
        .sub { color: #6a8f6a; margin-bottom: 20px; }
        
        .btn-back { background: #6a8f6a; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-back:hover { background: #4a6a4a; }
        
        /* ============ FILTER BOX ============ */
        .filter-box {
            background: #e8f5e9;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr auto;
            gap: 12px;
            align-items: end;
        }
        .filter-box .form-group label {
            display: block;
            font-weight: 600;
            color: #0d2818;
            margin-bottom: 5px;
            font-size: 0.85rem;
        }
        .filter-box select {
            width: 100%;
            padding: 9px 12px;
            border: 2px solid #2e7d32;
            border-radius: 8px;
            font-size: 0.9rem;
            background: white;
        }
        .filter-box select:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(46,125,50,0.2);
        }
        .filter-box .btn-filter {
            background: #2e7d32;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.3s ease;
        }
        .filter-box .btn-filter:hover {
            background: #1b5e20;
        }
        
        /* ============ STUDENT SELECTOR ============ */
        .student-selector {
            background: #f8faf8;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-left: 4px solid #2e7d32;
        }
        .student-selector label {
            display: block;
            font-weight: 700;
            color: #0d2818;
            margin-bottom: 8px;
        }
        .student-selector select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #2e7d32;
            border-radius: 8px;
            font-size: 1rem;
            background: white;
        }
        .student-selector .btn-load {
            margin-top: 12px;
            padding: 10px 30px;
            background: #1976d2;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
        }
        .student-selector .btn-load:hover {
            background: #0d47a1;
        }
        
        /* ============ RESULT HEADER ============ */
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px 20px;
            background: #0d2818;
            border-radius: 12px;
            color: white;
        }
        .result-header .student-info h2 { color: #ffd54f; font-size: 1.3rem; }
        .result-header .student-info p { color: #c8e6c9; font-size: 0.9rem; margin-top: 5px; }
        
        .gpa-box {
            background: #ffd54f;
            color: #0d2818;
            padding: 12px 25px;
            border-radius: 10px;
            text-align: center;
        }
        .gpa-box .gpa-number { font-size: 2rem; font-weight: 900; }
        .gpa-box .gpa-label { font-size: 0.75rem; font-weight: 700; }
        
        /* ============ RESULT TABLE ============ */
        .result-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            margin-top: 20px;
        }
        .result-table th {
            background: #0d2818;
            color: white;
            padding: 12px;
            text-align: left;
        }
        .result-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #eee;
        }
        .result-table tr:hover td { background: #f8faf8; }
        
        .grade-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.8rem;
        }
        .grade-A { background: #e8f5e9; color: #2e7d32; }
        .grade-B { background: #e3f2fd; color: #1565c0; }
        .grade-C { background: #fff3e0; color: #e65100; }
        .grade-D { background: #fce4ec; color: #c62828; }
        .grade-E { background: #f3e5f5; color: #6a1b9a; }
        .grade-F { background: #ffebee; color: #b71c1c; }
        
        .summary-box {
            margin-top: 20px;
            padding: 15px 20px;
            background: #f8faf8;
            border-radius: 10px;
            border-left: 4px solid #1976d2;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        .summary-box .item {
            text-align: center;
        }
        .summary-box .item .num {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0d2818;
        }
        .summary-box .item .lbl {
            font-size: 0.8rem;
            color: #6a8f6a;
        }
        
        .no-result {
            text-align: center;
            padding: 40px;
            color: #6a8f6a;
        }
        .no-result i {
            font-size: 3rem;
            display: block;
            margin-bottom: 10px;
            color: #dce8dc;
        }
        
        .btn-print {
            padding: 12px 30px;
            background: #1976d2;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: 20px;
        }
        .btn-print:hover { background: #0d47a1; }
        
        @media print {
            .topbar, .filter-box, .student-selector, .btn-print, .btn-back, .no-print { display: none !important; }
            body { background: white; padding: 0; }
            .container { box-shadow: none; padding: 10px; max-width: 100%; }
            .result-header { background: white !important; color: #000 !important; border: 2px solid #000; }
            .result-header .student-info h2 { color: #000 !important; }
            .result-header .student-info p { color: #000 !important; }
            .gpa-box { background: white !important; color: #000 !important; border: 2px solid #000; }
            .result-table th {
                background: #0d2818 !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        
        @media (max-width: 768px) {
            .filter-box { grid-template-columns: 1fr; }
            .result-header { flex-direction: column; gap: 15px; text-align: center; }
            .summary-box { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <div class="topbar no-print">
        <div>
            <span class="logo-title">DALA <span>COLLEGE</span></span>
            <span class="logo-sub">of Education, Kano</span>
        </div>
        <nav>
            <a href="staff_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="view_results.php" class="active"><i class="fas fa-chart-bar"></i> Results</a>
            <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="container">
        <h1><i class="fas fa-chart-bar" style="color:#2e7d32;"></i> View Results</h1>
        <p class="sub">Select combination, level, and semester to load students</p>

        <!-- ============================================ -->
        <!-- FILTER FORM -->
        <!-- ============================================ -->
        <form method="GET" action="" class="filter-box no-print">
            <div class="form-group">
                <label><i class="fas fa-users"></i> Combination</label>
                <select name="combination" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($combinations as $comb): ?>
                        <option value="<?php echo htmlspecialchars($comb); ?>" 
                            <?php echo ($selected_combination == $comb) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($comb); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-layer-group"></i> Level</label>
                <select name="level" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($levels as $lvl): ?>
                        <option value="<?php echo $lvl; ?>" 
                            <?php echo ($selected_level == $lvl) ? 'selected' : ''; ?>>
                            <?php echo $lvl; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-calendar-alt"></i> Semester</label>
                <select name="semester" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($semesters as $sem): ?>
                        <option value="<?php echo $sem; ?>" 
                            <?php echo ($selected_semester == $sem) ? 'selected' : ''; ?>>
                            <?php echo $sem; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-clock"></i> Session</label>
                <select name="session" required>
                    <?php foreach ($sessions as $sess): ?>
                        <option value="<?php echo $sess; ?>" 
                            <?php echo ($selected_session == $sess) ? 'selected' : ''; ?>>
                            <?php echo $sess; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-filter">
                <i class="fas fa-search"></i> Load Students
            </button>
        </form>

        <!-- ============================================ -->
        <!-- STUDENT SELECTOR -->
        <!-- ============================================ -->
        <?php if (!empty($students)): ?>
            <div class="student-selector no-print">
                <form method="GET" action="">
                    <input type="hidden" name="combination" value="<?php echo htmlspecialchars($selected_combination); ?>">
                    <input type="hidden" name="level" value="<?php echo htmlspecialchars($selected_level); ?>">
                    <input type="hidden" name="semester" value="<?php echo htmlspecialchars($selected_semester); ?>">
                    <input type="hidden" name="session" value="<?php echo htmlspecialchars($selected_session); ?>">
                    
                    <label><i class="fas fa-user-graduate"></i> Select Student:</label>
                    <select name="student_id" required>
                        <option value="">-- Select Student --</option>
                        <?php foreach ($students as $std): ?>
                            <option value="<?php echo $std['id']; ?>" 
                                <?php echo ($selected_student == $std['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($std['reg_no']); ?> — <?php echo htmlspecialchars($std['fullname']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-load">
                        <i class="fas fa-eye"></i> View Results
                    </button>
                </form>
            </div>
        <?php elseif (!empty($selected_combination) && !empty($selected_level)): ?>
            <div style="padding:15px; background:#fff3e0; border-left:4px solid #ff9800; border-radius:8px; margin-bottom:20px;">
                <strong>⚠️ No students found</strong> for Combination <strong><?php echo htmlspecialchars($selected_combination); ?></strong> 
                and Level <strong><?php echo htmlspecialchars($selected_level); ?></strong>.
            </div>
        <?php endif; ?>

        <!-- ============================================ -->
        <!-- RESULTS DISPLAY -->
        <!-- ============================================ -->
        <?php if ($student_info): ?>
            
            <!-- RESULT HEADER -->
            <div class="result-header">
                <div class="student-info">
                    <h2><?php echo htmlspecialchars($student_info['fullname']); ?></h2>
                    <p>
                        Reg No: <strong><?php echo htmlspecialchars($student_info['reg_no']); ?></strong> | 
                        Combination: <strong><?php echo htmlspecialchars($student_info['combination']); ?></strong><br>
                        Level: <strong><?php echo htmlspecialchars($selected_level); ?></strong> | 
                        Semester: <strong><?php echo htmlspecialchars($selected_semester); ?></strong> | 
                        Session: <strong><?php echo htmlspecialchars($selected_session); ?></strong>
                    </p>
                </div>
                <div class="gpa-box">
                    <div class="gpa-number"><?php echo number_format($gpa, 2); ?></div>
                    <div class="gpa-label">GPA</div>
                </div>
            </div>
            
            <!-- RESULT TABLE -->
            <?php if (!empty($results)): ?>
                <table class="result-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Course Code</th>
                            <th>Course Title</th>
                            <th>CA</th>
                            <th>Exam</th>
                            <th>Total</th>
                            <th>Grade</th>
                            <th>Point</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $i = 1;
                        foreach ($results as $res): 
                            $grade_class = 'grade-' . $res['grade'];
                        ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><strong><?php echo htmlspecialchars($res['course_code']); ?></strong></td>
                            <td><?php echo htmlspecialchars($res['course_title']); ?></td>
                            <td><?php echo $res['ca_score']; ?></td>
                            <td><?php echo $res['exam_score']; ?></td>
                            <td><strong><?php echo $res['total_score']; ?></strong></td>
                            <td><span class="grade-badge <?php echo $grade_class; ?>"><?php echo $res['grade']; ?></span></td>
                            <td><?php echo number_format($res['grade_point'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- SUMMARY -->
                <div class="summary-box">
                    <div class="item">
                        <div class="num"><?php echo $total_courses; ?></div>
                        <div class="lbl">Total Courses</div>
                    </div>
                    <div class="item">
                        <div class="num"><?php echo $total_units; ?></div>
                        <div class="lbl">Total Units</div>
                    </div>
                    <div class="item">
                        <div class="num"><?php echo number_format($gpa, 2); ?></div>
                        <div class="lbl">GPA</div>
                    </div>
                </div>
                
                <button class="btn-print no-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Result
                </button>
                
            <?php else: ?>
                <div class="no-result">
                    <i class="fas fa-chart-bar"></i>
                    <h3>No Results Found</h3>
                    <p>No results found for this student in the selected Level, Semester, and Session.</p>
                    <p style="font-size:0.85rem; color:#999; margin-top:8px;">
                        Level: <strong><?php echo htmlspecialchars($selected_level); ?></strong> | 
                        Semester: <strong><?php echo htmlspecialchars($selected_semester); ?></strong> | 
                        Session: <strong><?php echo htmlspecialchars($selected_session); ?></strong>
                    </p>
                </div>
            <?php endif; ?>
            
        <?php endif; ?>
        
        <div style="margin-top: 20px;" class="no-print">
            <a href="staff_dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>

</body>
</html>