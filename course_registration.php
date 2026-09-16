<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$success = '';
$error = '';

// ============================================
// FETCH STUDENT DATA
// ============================================
$query = "SELECT * FROM students WHERE id = $student_id";
$result = mysqli_query($conn, $query);
$student = mysqli_fetch_assoc($result);

if (!$student) {
    echo "Student not found!";
    exit();
}

$student_branch = $student['branch_code'] ?? 'SHINGE';

// MUHIMMI: Yi amfani da 'combination' idan akwai, in ba haka ba 'course'
$student_combination = !empty($student['combination']) 
    ? trim($student['combination']) 
    : trim($student['course']);

// ============================================
// GET SELECTED LEVEL & SEMESTER
// ============================================
$selected_level = isset($_POST['level']) ? mysqli_real_escape_string($conn, $_POST['level']) : 'NCEI';
$selected_semester = isset($_POST['semester']) ? mysqli_real_escape_string($conn, $_POST['semester']) : 'First Semester';
$academic_year = '2024/2025';

// ============================================
// HANDLE COURSE REGISTRATION
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_courses'])) {
    $selected_courses = isset($_POST['courses']) ? $_POST['courses'] : [];
    
    if (empty($selected_courses)) {
        $error = '❌ Please select at least one course.';
    } else {
        $registered = 0;
        $already = 0;
        $failed = 0;
        
        foreach ($selected_courses as $course_code) {
            $course_code = mysqli_real_escape_string($conn, $course_code);
            
            // Duba daidai da level, semester, da academic_year
            $check = "SELECT id FROM course_registrations 
                      WHERE student_id = $student_id 
                      AND course_code = '$course_code' 
                      AND semester = '$selected_semester' 
                      AND level = '$selected_level' 
                      AND academic_year = '$academic_year'
                      AND status != 'dropped'";
            $check_result = mysqli_query($conn, $check);
            
            if ($check_result && mysqli_num_rows($check_result) > 0) {
                $already++;
                continue;
            }
            
            // Nemo bayanan course
            $course_query = "SELECT * FROM courses WHERE course_code = '$course_code' LIMIT 1";
            $course_result = mysqli_query($conn, $course_query);
            $course = mysqli_fetch_assoc($course_result);
            
            if ($course) {
                $credits = intval($course['credits']);
                $course_title = mysqli_real_escape_string($conn, $course['course_title']);
                
                $insert = "INSERT INTO course_registrations (
                    student_id, course_code, course_title, semester, level, 
                    academic_year, credits, branch_code, status, registration_date
                ) VALUES (
                    $student_id, '$course_code', '$course_title', 
                    '$selected_semester', '$selected_level', '$academic_year', 
                    $credits, '$student_branch', 'registered', CURDATE()
                )";
                
                // Yin amfani da try/catch domin kama kuskuren Duplicate entry
                try {
                    if (mysqli_query($conn, $insert)) {
                        $registered++;
                    }
                } catch (mysqli_sql_exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        $already++;
                    } else {
                        $failed++;
                    }
                }
            }
        }
        
        if ($registered > 0) {
            $success = "✅ $registered course(s) registered successfully!";
            if ($already > 0) {
                $success .= "<br>⚠️ $already course(s) already registered a wannan level/semester.";
            }
            if ($failed > 0) {
                $success .= "<br>❌ $failed course(s) failed to register.";
            }
        } else {
            if ($already > 0) {
                $error = "⚠️ Duk courses ɗin da ka zaɓa an riga an yi rijista su a wannan level da semester.";
            } else {
                $error = '❌ No new courses were registered. Please try again.';
            }
        }
    }
}
// ============================================
// HANDLE DROP COURSE
// ============================================
if (isset($_GET['drop']) && is_numeric($_GET['drop'])) {
    $reg_id = intval($_GET['drop']);
    $update = "UPDATE course_registrations SET status = 'dropped' 
               WHERE id = $reg_id AND student_id = $student_id";
    if (mysqli_query($conn, $update)) {
        $success = "✅ Course dropped successfully!";
    }
}

// ============================================
// GET REGISTERED COURSES
// ============================================
$registered_query = "SELECT * FROM course_registrations 
                     WHERE student_id = $student_id 
                     AND level = '$selected_level' 
                     AND semester = '$selected_semester' 
                     AND academic_year = '$academic_year'
                     AND status != 'dropped'
                     ORDER BY course_code";
$registered_result = mysqli_query($conn, $registered_query);
$registered_courses = [];
if ($registered_result) {
    while ($row = mysqli_fetch_assoc($registered_result)) {
        $registered_courses[] = $row['course_code'];
    }
}

// ============================================
// GET AVAILABLE COURSES
// ============================================
$courses_query = "SELECT * FROM courses 
                  WHERE combination = '$student_combination'
                  AND level = '$selected_level' 
                  AND semester = '$selected_semester' 
                  ORDER BY FIELD(category, 'EDU', 'GSE', 'PED', 'CSC', 'BIO', 'ISC', 'PHY', 'ENG', 'ECO', 'ARB', 'ISS', 'HAU', 'SOS'), course_code";
$courses_result = mysqli_query($conn, $courses_query);

if (!$courses_result) {
    die("SQL Error: " . mysqli_error($conn));
}

$total_available = 0;
if ($courses_result) {
    $total_available = mysqli_num_rows($courses_result);
}

// ============================================
// STUDENT PHOTO PATH
// ============================================
$photo_path = "uploads/students/default.png";
if (!empty($student['photo']) && file_exists("uploads/students/" . $student['photo'])) {
    $photo_path = "uploads/students/" . $student['photo'];
} elseif (!empty($student['reg_no']) && file_exists("uploads/students/" . $student['reg_no'] . ".jpg")) {
    $photo_path = "uploads/students/" . $student['reg_no'] . ".jpg";
} elseif (!empty($student['reg_no']) && file_exists("uploads/students/" . $student['reg_no'] . ".png")) {
    $photo_path = "uploads/students/" . $student['reg_no'] . ".png";
}

$levels = ['NCEI', 'NCEII', 'NCEIII'];
$semesters = ['First Semester', 'Second Semester'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Registration - Dala College</title>
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
            max-width: 1000px;
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
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 30px rgba(0,0,0,0.1);
        }
        h1 { color: #0d2818; margin-bottom: 5px; }
        .sub { color: #6a8f6a; margin-bottom: 20px; }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #2e7d32;
        }
        .alert-error {
            background: #ffebee;
            color: #c62828;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #c62828;
        }
        
        .filter-box {
            background: #e8f5e9;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }
        .filter-box .form-group label {
            display: block;
            font-weight: 600;
            color: #0d2818;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        .filter-box select {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #2e7d32;
            border-radius: 8px;
            font-size: 0.95rem;
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
            padding: 10px 30px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        .filter-box .btn-filter:hover {
            background: #1b5e20;
            transform: translateY(-2px);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: #f8faf8;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-card .number { font-size: 1.8rem; font-weight: 800; color: #2e7d32; }
        .stat-card .label { font-size: 0.8rem; color: #6a8f6a; }
        .stat-card.blue .number { color: #1976d2; }
        .stat-card.purple .number { color: #7b1fa2; font-size: 1.2rem; }
        
        .course-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .course-item {
            background: #f8faf8;
            padding: 15px;
            border-radius: 10px;
            border: 2px solid #e8f5e9;
            transition: all 0.3s ease;
        }
        .course-item:hover {
            border-color: #2e7d32;
            background: white;
            box-shadow: 0 4px 15px rgba(46,125,50,0.1);
        }
        .course-item.registered {
            border-color: #2e7d32;
            background: #e8f5e9;
        }
        .course-item .course-code {
            font-weight: 700;
            color: #0d2818;
            font-size: 1rem;
        }
        .course-item .course-title {
            color: #4a6a4a;
            font-size: 0.85rem;
            margin: 5px 0;
        }
        .course-item .course-credits {
            display: inline-block;
            background: #fff3e0;
            color: #e65100;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .course-item .course-status {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 5px;
        }
        .course-item .course-status.registered {
            background: #2e7d32;
            color: white;
        }
        .course-item .course-status.available {
            background: #e3f2fd;
            color: #0d47a1;
        }
        .course-item label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }
        .course-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #2e7d32;
        }
        
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #2e7d32, #1b5e20);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(46,125,50,0.3);
        }
        .btn-print {
            width: 100%;
            padding: 14px;
            background: #1976d2;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        .btn-print:hover {
            background: #0d47a1;
            transform: translateY(-2px);
        }
        .btn-back {
            display: inline-block;
            margin-top: 15px;
            color: #2e7d32;
            text-decoration: none;
            font-weight: 600;
        }
        
        .registered-list {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e8f5e9;
        }
        .registered-list h3 {
            color: #0d2818;
            margin-bottom: 15px;
        }
        .registered-list table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .registered-list table th {
            background: #0d2818;
            color: white;
            padding: 10px;
            text-align: left;
        }
        .registered-list table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .registered-list table tr:hover td { background: #f8faf8; }
        .registered-list .btn-drop {
            background: #c62828;
            color: white;
            padding: 4px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .registered-list .btn-drop:hover { background: #b71c1c; }
        
        /* ============ PRINT HEADER ============ */
        .print-header {
            display: none;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 3px double #0d2818;
            position: relative;
        }
        .print-header .logo-left {
            position: absolute;
            left: 0;
            top: 0;
            width: 85px;
            height: 85px;
        }
        .print-header .logo-left img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .print-header .header-text {
            text-align: center;
            padding: 0 95px;
            margin-top: 5px;
        }
        .print-header .college-name {
            font-size: 22px;
            font-weight: 900;
            color: #0d2818;
            letter-spacing: 1px;
            margin-bottom: 2px;
            font-family: 'Times New Roman', serif;
            text-transform: uppercase;
        }
        .print-header .college-motto {
            font-size: 12px;
            color: #c62828;
            font-weight: 700;
            font-style: italic;
            margin-bottom: 6px;
            letter-spacing: 1px;
        }
        .print-header .accreditation {
            font-size: 10px;
            color: #c62828;
            font-weight: 600;
            margin-bottom: 3px;
        }
        .print-header .accreditation .check {
            color: #2e7d32;
            font-weight: 900;
        }
        .print-header .contact-info {
            font-size: 10px;
            color: #1976d2;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .print-header .contact-info span {
            color: #666;
            margin: 0 5px;
        }
        .print-header .form-title {
            font-size: 14px;
            font-weight: 800;
            color: #0d2818;
            text-transform: uppercase;
            margin: 10px 0 0 0;
            border-top: 2px solid #0d2818;
            border-bottom: 2px solid #0d2818;
            padding: 6px 0;
            letter-spacing: 1px;
        }
        
        /* ============ PRINT BODY INFO ============ */
        .print-body-info {
            display: none;
            margin-top: 15px;
            margin-bottom: 20px;
        }
        .print-body-info .info-wrapper {
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }
        .print-body-info .student-details {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 5px 25px;
            font-size: 12px;
        }
        .print-body-info .student-details .row {
            display: flex;
            padding: 4px 0;
            border-bottom: 1px dotted #ccc;
        }
        .print-body-info .student-details .label {
            font-weight: 700;
            min-width: 130px;
            color: #0d2818;
        }
        .print-body-info .student-details .value {
            color: #000;
        }
        .print-body-info .student-photo {
            width: 100px;
            height: 120px;
            border: 2px solid #0d2818;
            border-radius: 4px;
            overflow: hidden;
            background: #f0f0f0;
            flex-shrink: 0;
        }
        .print-body-info .student-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .signature-area {
            display: none;
            margin-top: 50px;
            padding-top: 20px;
        }
        .signature-area .sign-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            text-align: center;
        }
        .signature-area .sign-box {
            padding-top: 10px;
        }
        .signature-area .sign-line {
            border-top: 2px solid #0d2818;
            padding-top: 8px;
            margin-bottom: 5px;
        }
        .signature-area .sign-title {
            font-weight: 800;
            font-size: 13px;
            color: #0d2818;
            text-transform: uppercase;
        }
        .signature-area .sign-date {
            font-size: 11px;
            color: #555;
            margin-top: 15px;
        }
        .signature-area .sign-date span {
            border-bottom: 1px dotted #000;
            padding: 0 30px;
        }
        
        .print-footer {
            display: none;
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
        
        @media (max-width: 768px) {
            .container { padding: 15px; }
            .filter-box { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr; }
            .course-list { grid-template-columns: 1fr; }
        }
        
        @media print {
            .topbar, .filter-box, .stats-grid, .btn-submit, .btn-print, 
            .btn-back, .no-print, .alert-success, .alert-error, 
            .btn-drop, .course-list, h1, .sub {
                display: none !important;
            }
            
            body { 
                background: white; 
                padding: 0; 
                margin: 0;
                font-size: 12px;
            }
            
            .container { 
                box-shadow: none; 
                padding: 10px; 
                max-width: 100%;
                border-radius: 0;
            }
            
            .print-header { display: block !important; }
            .print-body-info { display: block !important; }
            .print-footer { display: block !important; }
            .signature-area { display: block !important; }
            
            .registered-list { 
                margin-top: 0; 
                padding-top: 0; 
                border-top: none;
            }
            
            .registered-list h3 { display: none; }
            
            .registered-list table { 
                font-size: 11px; 
                border: 1px solid #000;
                border-collapse: collapse;
                width: 100%;
            }
            
            .registered-list table th {
                background: #0d2818 !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                padding: 6px;
                border: 1px solid #000;
            }
            
            .registered-list table td {
                padding: 5px;
                border: 1px solid #000;
            }
            
            .print-header .logo-left img,
            .print-body-info .student-photo img {
                display: block !important;
                visibility: visible !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            @page {
                size: A4;
                margin: 15mm;
            }
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
            <a href="student_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="course_registration.php" class="active"><i class="fas fa-book"></i> Course Registration</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="container">
        
        <!-- ============================================ -->
        <!-- PRINT HEADER -->
        <!-- ============================================ -->
        <div class="print-header">
            <div class="logo-left">
                <img src="images/dala-logo.png" alt="Dala College Logo">
            </div>
            
            <div class="header-text">
                <div class="college-name">DALA COLLEGE OF EDUCATION, KANO</div>
                <div class="college-motto">Knowledge, Excellence &amp; Success</div>
                <div class="accreditation">
                    <span class="check">✅</span> Accredited by National Commission for Colleges of Education (NCCE), Abuja
                </div>
                <div class="contact-info">
                    🌐 www.dalacollege.edu.ng <span>|</span> 📧 dalacollegekano@gmail.com
                </div>
                <div class="form-title">Course Registration Form (CRF) — <?php echo $academic_year; ?> Academic Session</div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- PRINT BODY INFO -->
        <!-- ============================================ -->
        <div class="print-body-info">
            <div class="info-wrapper">
                <div class="student-details">
                    <div class="row">
                        <span class="label">Registration No:</span>
                        <span class="value"><?php echo htmlspecialchars($student['reg_no'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Student Name:</span>
                        <span class="value"><?php echo htmlspecialchars($student['fullname'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Combination:</span>
                        <span class="value"><?php echo htmlspecialchars($student_combination); ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Programme:</span>
                        <span class="value"><?php echo htmlspecialchars($student['programme'] ?? 'NCE'); ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Level:</span>
                        <span class="value"><?php echo htmlspecialchars($selected_level); ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Semester:</span>
                        <span class="value"><?php echo htmlspecialchars($selected_semester); ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Registration Date:</span>
                        <span class="value"><?php echo date('F j, Y g:i A'); ?></span>
                    </div>
                </div>
                
                <div class="student-photo">
                    <img src="<?php echo $photo_path; ?>" alt="Student Photo" 
                         onerror="this.src='https://via.placeholder.com/100x120/cccccc/333333?text=PHOTO'">
                </div>
            </div>
        </div>

        <h1><i class="fas fa-book" style="color:#2e7d32;"></i> Course Registration</h1>
        <p class="sub">
            Combination: <strong><?php echo htmlspecialchars($student_combination); ?></strong> | 
            Zaɓi Level da Semester domin ganin courses ɗin da suka dace
        </p>

        <?php if ($success): ?>
            <div class="alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="" class="filter-box no-print">
            <div class="form-group">
                <label><i class="fas fa-layer-group"></i> Zaɓi Level</label>
                <select name="level">
                    <?php foreach ($levels as $lvl): ?>
                        <option value="<?php echo $lvl; ?>" <?php echo ($selected_level == $lvl) ? 'selected' : ''; ?>>
                            <?php echo $lvl; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-calendar-alt"></i> Zaɓi Semester</label>
                <select name="semester">
                    <?php foreach ($semesters as $sem): ?>
                        <option value="<?php echo $sem; ?>" <?php echo ($selected_semester == $sem) ? 'selected' : ''; ?>>
                            <?php echo $sem; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-filter">
                <i class="fas fa-filter"></i> Nuna Courses
            </button>
        </form>

        <div class="stats-grid no-print">
            <div class="stat-card">
                <div class="number"><?php echo count($registered_courses); ?></div>
                <div class="label">📚 Registered (<?php echo $selected_level; ?>)</div>
            </div>
            <div class="stat-card blue">
                <div class="number"><?php echo $total_available; ?></div>
                <div class="label">📖 Available (<?php echo $selected_semester; ?>)</div>
            </div>
            <div class="stat-card purple">
                <div class="number"><?php echo htmlspecialchars($student_combination); ?></div>
                <div class="label">📌 Your Combination</div>
            </div>
        </div>

        <form method="POST" action="" class="no-print" id="courseForm">
            <input type="hidden" name="level" value="<?php echo $selected_level; ?>">
            <input type="hidden" name="semester" value="<?php echo $selected_semester; ?>">
            
            <div class="course-list">
                <?php if ($courses_result && mysqli_num_rows($courses_result) > 0): ?>
                    <?php 
                    // Muna buƙatar sake gudanar da query ɗin domin mu iya amfani da shi sau biyu
                    mysqli_data_seek($courses_result, 0);
                    while ($course = mysqli_fetch_assoc($courses_result)): 
                        $is_registered = in_array($course['course_code'], $registered_courses);
                    ?>
                    <div class="course-item <?php echo $is_registered ? 'registered' : ''; ?>">
                        <label>
                            <input type="checkbox" name="courses[]" value="<?php echo $course['course_code']; ?>"
                                   <?php echo $is_registered ? 'checked disabled' : ''; ?>>
                            <div>
                                <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                                <div class="course-title"><?php echo htmlspecialchars($course['course_title']); ?></div>
                                <span class="course-credits"><?php echo $course['credits']; ?> Units</span>
                                <span class="course-status <?php echo $is_registered ? 'registered' : 'available'; ?>">
                                    <?php echo $is_registered ? '✅ Registered' : 'Available'; ?>
                                </span>
                            </div>
                        </label>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; text-align:center; padding:40px; color:#6a8f6a;">
                        <i class="fas fa-book" style="font-size:3rem; display:block; margin-bottom:10px; color:#dce8dc;"></i>
                        <p>Babu courses da suka dace da <strong><?php echo htmlspecialchars($student_combination); ?></strong> - <strong><?php echo $selected_level; ?></strong> - <strong><?php echo $selected_semester; ?></strong></p>
                        <p style="font-size:0.85rem;">Zaɓi wani level ko semester.</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($total_available > 0): ?>
                <button type="submit" name="register_courses" class="btn-submit">
                    <i class="fas fa-save"></i> Register Selected Courses
                </button>
            <?php endif; ?>
        </form>

        <?php if ($registered_result && mysqli_num_rows($registered_result) > 0): ?>
        <div class="registered-list">
            <h3 class="no-print"><i class="fas fa-check-circle" style="color:#2e7d32;"></i> Registered Courses (<?php echo $selected_level; ?> - <?php echo $selected_semester; ?>)</h3>
            
            <table>
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th style="width:100px;">Course Code</th>
                        <th>Course Title</th>
                        <th style="width:70px;">Unit</th>
                        <th style="width:100px;">Status</th>
                        <th class="no-print" style="width:80px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1;
                    $total_units = 0;
                    // Muna buƙatar sake gudanar da query ɗin registered
                    $reg_result = mysqli_query($conn, $registered_query);
                    while ($reg = mysqli_fetch_assoc($reg_result)): 
                        $total_units += $reg['credits'];
                    ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><strong><?php echo htmlspecialchars($reg['course_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($reg['course_title']); ?></td>
                        <td><?php echo $reg['credits']; ?></td>
                        <td><span class="badge badge-approved"><?php echo ucfirst($reg['status']); ?></span></td>
                        <td class="no-print">
                            <a href="?drop=<?php echo $reg['id']; ?>" class="btn-drop" onclick="return confirm('Drop this course?')">
                                <i class="fas fa-times"></i> Drop
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    
                    <tr style="background:#0d2818; color:white; font-weight:800;">
                        <td colspan="3" style="text-align:right; padding:10px;">TOTAL UNITS:</td>
                        <td style="padding:10px;"><?php echo $total_units; ?></td>
                        <td colspan="2" class="no-print"></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="signature-area">
            <div class="sign-row">
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <div class="sign-title">Academic Secretary</div>
                    <div class="sign-date">Date: <span></span></div>
                </div>
                
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <div class="sign-title">H.O.D</div>
                    <div class="sign-date">Date: <span></span></div>
                </div>
            </div>
        </div>

        <button type="button" class="btn-print no-print" onclick="window.print()">
            <i class="fas fa-print"></i> Print Registration Form
        </button>

        <a href="student_dashboard.php" class="btn-back no-print">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="print-footer">
        <p>This is a computer-generated document. No signature is required.</p>
        <p>Dala College of Education, Kano — <?php echo date('Y'); ?></p>
    </div>

</body>
</html>