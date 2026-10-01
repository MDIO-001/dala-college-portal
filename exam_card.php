<?php
session_start();
include 'connect.php';
include 'payment_gate.php';

// Tabbatar ɗalibi ya shiga
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header('Location: login.php');
    exit();
}

// ============================================
// PAYMENT GATE — Duba Tuition Fee
// ============================================
if (!hasPaidItem($conn, $_SESSION['user_id'], 'TUI')) {
    showPaymentRequired('Tuition Fee', 'student_dashboard.php');
}

// ============================================
// SAURAN KOD
// ============================================
$student_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    die("Student not found!");
}

$student_level_raw = $student['level'] ?? 'NCE I';
$student_level = str_replace(' ', '', $student_level_raw); // NCEI

$student_combination = !empty($student['combination']) 
    ? trim($student['combination']) 
    : trim($student['course']);

// ============================================
// GET SELECTED LEVEL & SEMESTER
// ============================================
$selected_level_raw = isset($_GET['level']) ? mysqli_real_escape_string($conn, $_GET['level']) : $student_level;
$selected_semester = isset($_GET['semester']) ? mysqli_real_escape_string($conn, $_GET['semester']) : 'First Semester';

// ============================================
// GYARA: NCEI → NCE I, NCEII → NCE II, NCEIII → NCE III
// ============================================
$level_map = [
    'NCEI' => 'NCE I',
    'NCEII' => 'NCE II',
    'NCEIII' => 'NCE III',
    'NCE I' => 'NCE I',
    'NCE II' => 'NCE II',
    'NCE III' => 'NCE III',
    '400Level' => '400 Level',
    '400 Level' => '400 Level',
    '500Level' => '500 Level',
    '500 Level' => '500 Level',
];

$selected_level = $level_map[$selected_level_raw] ?? $selected_level_raw;

// ============================================
// SESSION YANA BIN LEVEL
// ============================================
$selected_level_clean = str_replace(' ', '', $selected_level);

$level_session_map = [
    'NCEI'      => '2026/2027',
    'NCEII'     => '2025/2026',
    'NCEIII'    => '2024/2025',
    '400Level'  => '2026/2027',
    '500Level'  => '2027/2028',
];

$academic_year = $level_session_map[$selected_level_clean] ?? '2026/2027';

// ============================================
// GET REGISTERED COURSES
// ============================================
$reg_query = "SELECT * FROM course_registrations 
              WHERE student_id = $student_id 
              AND level = '$selected_level' 
              AND semester = '$selected_semester' 
              AND status != 'dropped'
              ORDER BY FIELD(SUBSTRING(course_code, 1, 3), 'EDU', 'GSE', 'PED', 'CSC', 'BIO', 'ISC', 'PHY', 'ENG', 'ECO', 'ARB', 'ISS', 'HAU', 'SOS'), course_code";
$reg_result = mysqli_query($conn, $reg_query);

$registered_courses = [];
$total_units = 0;
if ($reg_result) {
    while ($row = mysqli_fetch_assoc($reg_result)) {
        $registered_courses[] = $row;
        $total_units += $row['credits'];
    }
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

// ============================================
// LEVELS — NCE da DEGREE
// ============================================
$levels = [
    'NCEI'      => 'NCE I',
    'NCEII'     => 'NCE II',
    'NCEIII'    => 'NCE III',
    '400Level'  => '400 Level',
    '500Level'  => '500 Level',
];

$semesters = ['First Semester', 'Second Semester'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Examination Card - Dala College</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Times New Roman', serif;
            background: #f0f4f8;
            padding: 15px;
        }
        
        /* ============ SELECTION FORM ============ */
        .selection-form {
            max-width: 850px;
            margin: 0 auto 20px auto;
            background: #e8f5e9;
            padding: 20px;
            border-radius: 12px;
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 15px;
            align-items: end;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .selection-form .form-group label {
            display: block;
            font-weight: 700;
            color: #0d2818;
            margin-bottom: 5px;
            font-size: 0.9rem;
            font-family: 'Segoe UI', sans-serif;
        }
        .selection-form select {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #2e7d32;
            border-radius: 8px;
            font-size: 0.95rem;
            background: white;
            font-family: 'Segoe UI', sans-serif;
        }
        .selection-form select:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(46,125,50,0.2);
        }
        .selection-form .btn-view {
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
            font-family: 'Segoe UI', sans-serif;
        }
        .selection-form .btn-view:hover {
            background: #1b5e20;
            transform: translateY(-2px);
        }
        
        /* ============ SESSION INFO ============ */
        .session-info {
            max-width: 850px;
            margin: 0 auto 15px auto;
            background: #fff9c4;
            padding: 10px 20px;
            border-radius: 8px;
            border-left: 4px solid #f9a825;
            font-family: 'Segoe UI', sans-serif;
            font-size: 0.9rem;
            color: #0d2818;
            font-weight: 600;
        }
        .session-info i {
            color: #f57c00;
            margin-right: 5px;
        }
        
        /* ============ CARD CONTAINER ============ */
        .card-container {
            max-width: 850px;
            margin: 0 auto;
            background: white;
            padding: 20px 25px;
            box-shadow: 0 4px 30px rgba(0,0,0,0.15);
            border-radius: 8px;
            border: 2px solid #0d2818;
            position: relative;
        }
        
        /* ============================================
           HEADER - TITLE LAYI ƊAYA + LOGO BABU RAGE
           ============================================ */
        .exam-header {
            position: relative;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 3px double #0d2818;
            min-height: 130px;
        }
        .exam-header .exam-logo-left {
            position: absolute;
            left: 0;
            top: 0;
            width: 120px;
            height: 120px;
        }
        .exam-header .exam-logo-left img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: drop-shadow(0 3px 6px rgba(0,0,0,0.15));
        }
        .exam-header .exam-header-text {
            text-align: center;
            padding: 0 130px;
            margin-top: 0;
        }
        .exam-header .exam-college-name {
            font-size: 20px;
            font-weight: 900;
            color: #2e7d32;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
            text-transform: uppercase;
            line-height: 1;
            text-shadow: 0 1px 2px rgba(46, 125, 50, 0.15);
            white-space: nowrap;
        }
        .exam-header .exam-motto {
            font-size: 12px;
            color: #c62828;
            font-weight: 700;
            font-style: italic;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }
        .exam-header .exam-accreditation {
            font-size: 11px;
            color: #c62828;
            font-weight: 700;
            margin-bottom: 5px;
            letter-spacing: 0.3px;
        }
        .exam-header .exam-contact {
            font-size: 11px;
            color: #1976d2;
            font-weight: 600;
            margin-bottom: 12px;
            letter-spacing: 0.3px;
        }
        .exam-header .exam-form-title {
            font-size: 13px;
            font-weight: 700;
            color: #ffffff;
            text-transform: uppercase;
            margin: 10px 0 0 0;
            border-top: 1.0px solid #1b5e20;
            border-bottom: 1.0px solid #1b5e20;
            padding: 8px 0;
            letter-spacing: 1px;
            background: linear-gradient(90deg, #2e7d32, #1b5e20);
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            border-radius: 4px;
        }
        
        /* ============ STUDENT INFO + PHOTO (SIZE 13) ============ */
        .exam-body-info {
            margin-top: 12px;
            margin-bottom: 15px;
        }
        .exam-body-info .info-wrapper {
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }
        .exam-body-info .student-details {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3px 20px;
            font-size: 13px;
        }
        .exam-body-info .student-details .row {
            display: flex;
            padding: 3px 0;
            border-bottom: 1px dotted #ccc;
        }
        .exam-body-info .student-details .label {
            font-weight: 700;
            min-width: 110px;
            color: #0d2818;
            font-size: 13px;
        }
        .exam-body-info .student-details .value {
            color: #000;
            font-size: 13px;
        }
        .exam-body-info .student-photo {
            width: 85px;
            height: 100px;
            border: 2px solid #0d2818;
            border-radius: 4px;
            overflow: hidden;
            background: #f0f0f0;
            flex-shrink: 0;
            box-shadow: 2px 2px 6px rgba(0,0,0,0.1);
        }
        .exam-body-info .student-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* ============ EXAM TABLE ============ */
        .exam-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-top: 12px;
        }
        .exam-table th {
            background: #0d2818 !important;
            color: white !important;
            padding: 6px 6px;
            border: 1px solid #000;
            text-align: left;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            font-size: 12px;
            font-weight: 700;
        }
        .exam-table td {
            padding: 5px 6px;
            border: 1px solid #000;
            vertical-align: middle;
            font-size: 12px;
        }
        .exam-table tr:nth-child(even) td {
            background: #f8faf8;
        }
        .exam-table .sign-col {
            height: 28px;
            background: #fffef5 !important;
        }
        .exam-table .date-col {
            height: 28px;
            background: #fffef5 !important;
        }
        .exam-table tr.total-row td {
            background: #0d2818 !important;
            color: white !important;
            font-weight: 800;
            padding: 6px;
            font-size: 12px;
        }
        
        /* ============ STUDENT'S ATTESTATION ============ */
        .attestation-box {
            margin-top: 15px;
            padding: 10px 15px;
            border: 1px solid #0d2818;
            background: #f8faf8;
            border-radius: 4px;
            page-break-inside: avoid;
        }
        .attestation-box .attestation-title {
            font-weight: 800;
            font-size: 13px;
            color: #0d2818;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #dce8dc;
            padding-bottom: 4px;
        }
        .attestation-box .attestation-list {
            margin: 0;
            padding-left: 22px;
            font-size: 12px;
            line-height: 1.7;
            color: #1a2e1a;
        }
        .attestation-box .attestation-list li {
            margin-bottom: 4px;
        }
        
        /* ============ EXAM SIGNATURE AREA ============ */
        .exam-signature-area {
            margin-top: 20px;
            padding-top: 12px;
            border-top: 1px solid #0d2818;
            page-break-inside: avoid;
        }
        .exam-signature-area .sign-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            text-align: center;
        }
        .exam-signature-area .sign-box {
            padding-top: 5px;
        }
        .exam-signature-area .sign-line {
            border-top: 1px solid #0d2818;
            padding-top: 4px;
            margin-top: 35px;
            margin-bottom: 5px;
        }
        .exam-signature-area .sign-title {
            font-weight: 800;
            font-size: 12px;
            color: #0d2818;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        /* ============ FOOTER ============ */
        .exam-footer {
            margin-top: 15px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 8px;
        }
        
        /* ============ BUTTONS ============ */
        .actions {
            text-align: center;
            margin-top: 20px;
            max-width: 850px;
            margin-left: auto;
            margin-right: auto;
        }
        .btn-print {
            display: inline-block;
            padding: 12px 30px;
            background: #2e7d32;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            margin: 5px;
            transition: all 0.3s ease;
            font-family: 'Segoe UI', sans-serif;
        }
        .btn-print:hover {
            background: #1b5e20;
            transform: translateY(-2px);
        }
        .btn-back {
            display: inline-block;
            padding: 12px 25px;
            background: #6a8f6a;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            margin: 5px;
            transition: all 0.3s ease;
            font-family: 'Segoe UI', sans-serif;
        }
        .btn-back:hover {
            background: #4a6a4a;
            transform: translateY(-2px);
        }
        
        /* ============ NO COURSES MESSAGE ============ */
        .no-courses {
            text-align: center;
            padding: 40px 20px;
            color: #6a8f6a;
            font-family: 'Segoe UI', sans-serif;
        }
        .no-courses i {
            font-size: 3rem;
            display: block;
            margin-bottom: 10px;
            color: #dce8dc;
        }
        
        /* ============================================
           PRINT - A4 DAIDAI (GYARAN NAN)
           ============================================ */
        @media print {
            .no-print { display: none !important; }
            
            html, body { 
                background: white !important; 
                padding: 0 !important; 
                margin: 0 !important;
                width: 100%;
                height: auto;
                font-size: 11pt;
                display: block !important;
            }
            
            .card-container { 
                box-shadow: none !important; 
                padding: 6mm 8mm !important; 
                border: 1.5px solid #000 !important;
                width: 100% !important;
                min-height: auto !important;
                max-height: none !important;
                max-width: 100% !important;
                border-radius: 0 !important;
                page-break-inside: avoid !important;
                page-break-after: avoid !important;
                page-break-before: avoid !important;
                margin: 0 auto !important;
                box-sizing: border-box !important;
                overflow: visible !important;
                display: block !important;
            }
            
            .exam-header {
                min-height: 110px !important;
                margin-bottom: 10px !important;
                padding-bottom: 8px !important;
            }
            .exam-header .exam-logo-left {
                width: 100px !important;
                height: 100px !important;
            }
            .exam-header .exam-header-text {
                padding: 0 110px !important;
            }
            .exam-header .exam-college-name {
                font-size: 17px !important;
                color: #2e7d32 !important;
                letter-spacing: 0.3px !important;
                white-space: nowrap !important;
                line-height: 1 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .exam-header .exam-motto {
                font-size: 10px !important;
            }
            .exam-header .exam-accreditation {
                font-size: 9px !important;
            }
            .exam-header .exam-contact {
                font-size: 9px !important;
                margin-bottom: 6px !important;
            }
            .exam-header .exam-form-title {
                font-size: 11px !important;
                padding: 6px 0 !important;
                margin-top: 6px !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .exam-body-info {
                margin-top: 8px !important;
                margin-bottom: 10px !important;
            }
            .exam-body-info .student-details {
                font-size: 11px !important;
                gap: 2px 15px !important;
            }
            .exam-body-info .student-details .label {
                font-size: 11px !important;
                min-width: 95px !important;
            }
            .exam-body-info .student-details .value {
                font-size: 11px !important;
            }
            .exam-body-info .student-photo {
                width: 70px !important;
                height: 85px !important;
            }
            
            .exam-table {
                page-break-inside: avoid !important;
                font-size: 10px !important;
                margin-top: 8px !important;
            }
            .exam-table th {
                padding: 4px 4px !important;
                font-size: 10px !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .exam-table td {
                padding: 3px 4px !important;
                font-size: 10px !important;
            }
            .exam-table .sign-col,
            .exam-table .date-col {
                height: 22px !important;
            }
            .exam-table tr.total-row td {
                padding: 4px !important;
                font-size: 10px !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .attestation-box {
                margin-top: 10px !important;
                padding: 7px 12px !important;
                page-break-inside: avoid !important;
            }
            .attestation-box .attestation-title {
                font-size: 11px !important;
                margin-bottom: 4px !important;
            }
            .attestation-box .attestation-list {
                font-size: 10px !important;
                line-height: 1.5 !important;
                padding-left: 18px !important;
            }
            .attestation-box .attestation-list li {
                margin-bottom: 2px !important;
            }
            
            .exam-signature-area {
                margin-top: 15px !important;
                padding-top: 8px !important;
                page-break-inside: avoid !important;
            }
            .exam-signature-area .sign-line {
                margin-top: 30px !important;
            }
            .exam-signature-area .sign-title {
                font-size: 10px !important;
            }
            
            .exam-footer {
                margin-top: 10px !important;
                font-size: 8px !important;
                padding-top: 5px !important;
            }
            
            .exam-header .exam-logo-left img,
            .exam-body-info .student-photo img {
                display: block !important;
                visibility: visible !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            @page {
                size: A4 portrait;
                margin: 5mm;
            }
        }
        
        @media (max-width: 500px) {
            .selection-form { grid-template-columns: 1fr; }
            .card-container { padding: 11px; }
            .exam-body-info .info-wrapper { flex-direction: column; align-items: center; }
            .exam-signature-area .sign-row { grid-template-columns: 1fr; gap: 30px; }
        }
    </style>
</head>
<body>

<!-- SELECTION FORM -->
<div class="selection-form no-print">
    <div class="form-group">
    <label><i class="fas fa-layer-group"></i> Select Level</label>
    <select id="levelSelect" onchange="updateSession()">
        <?php foreach ($levels as $key => $label): ?>
            <option value="<?php echo $key; ?>" <?php echo ($selected_level_raw == $key) ? 'selected' : ''; ?>>
                <?php echo $label; ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
    <div class="form-group">
        <label><i class="fas fa-calendar-alt"></i> Select Semester</label>
        <select id="semesterSelect" onchange="updateSession()">
            <?php foreach ($semesters as $sem): ?>
                <option value="<?php echo $sem; ?>" <?php echo ($selected_semester == $sem) ? 'selected' : ''; ?>>
                    <?php echo $sem; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button onclick="window.location.href='?level=' + document.getElementById('levelSelect').value + '&semester=' + encodeURIComponent(document.getElementById('semesterSelect').value)" class="btn-view">
        <i class="fas fa-eye"></i> View Courses
    </button>
</div>

<!-- SESSION INFO -->
<div class="session-info no-print">
    <i class="fas fa-calendar-check"></i> 
    <strong>Academic Session:</strong> <?php echo htmlspecialchars($academic_year); ?> 
    &nbsp;|&nbsp; 
    <strong>Level:</strong> <?php echo htmlspecialchars($selected_level); ?> 
    &nbsp;|&nbsp; 
    <strong>Semester:</strong> <?php echo htmlspecialchars($selected_semester); ?>
</div>

<script>
function updateSession() {
    var level = document.getElementById('levelSelect').value;
    var semester = document.getElementById('semesterSelect').value;
    window.location.href = '?level=' + level + '&semester=' + encodeURIComponent(semester);
}
</script>

<?php if (empty($registered_courses)): ?>
    <div class="card-container">
        <div class="no-courses">
            <i class="fas fa-id-card"></i>
            <h2 style="color:#c62828; font-family:'Segoe UI',sans-serif; margin-bottom:10px;">❌ No Courses Registered</h2>
            <p>No courses have been registered for this Level and Semester.</p>
            <p style="font-size:13px; color:#666; margin-top:10px;">
                Level: <strong><?php echo htmlspecialchars($selected_level); ?></strong> | 
                Semester: <strong><?php echo htmlspecialchars($selected_semester); ?></strong> | 
                Academic Year: <strong><?php echo $academic_year; ?></strong>
            </p>
            <a href="course_registration.php" style="display:inline-block; margin-top:20px; padding:12px 25px; background:#2e7d32; color:white; text-decoration:none; border-radius:8px; font-weight:bold; font-family:'Segoe UI',sans-serif;">
                Register for Courses
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="card-container" id="printArea">
        
        <!-- EXAM HEADER -->
        <div class="exam-header">
            <div class="exam-logo-left">
                <img src="images/dala-logo.png" alt="Dala College Logo">
            </div>
            <div class="exam-header-text">
                <div class="exam-college-name">DALA COLLEGE OF EDUCATION, KANO</div>
                <div class="exam-motto">Knowledge, Excellence &amp; Success</div>
                <div class="exam-accreditation">✅ Accredited by National Commission for Colleges of Education (NCCE), Abuja</div>
                <div class="exam-contact">🌐 www.dalacoe.edu.ng | 📧 info@dalacoe.edu.ng</div>
                <div class="exam-form-title">Examination Card — <?php echo $academic_year; ?> Academic Session</div>
            </div>
        </div>

        <!-- STUDENT INFO + PHOTO -->
        <div class="exam-body-info">
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
                        <span class="label">Total Units:</span>
                        <span class="value"><strong><?php echo $total_units; ?> Units</strong></span>
                    </div>
                </div>
                <div class="student-photo">
                    <img src="<?php echo $photo_path; ?>" alt="Student Photo" 
                         onerror="this.src='https://via.placeholder.com/85x100/cccccc/333333?text=PHOTO'">
                </div>
            </div>
        </div>

        <!-- EXAM TABLE -->
        <table class="exam-table">
            <thead>
                <tr>
                    <th style="width:30px;">#</th>
                    <th style="width:90px;">Course Code</th>
                    <th>Course Title</th>
                    <th style="width:50px;">Unit</th>
                    <th style="width:100px;">Date</th>
                    <th style="width:130px;">Invigilator's Signature</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $i = 1;
                foreach ($registered_courses as $course): 
                ?>
                <tr>
                    <td style="text-align:center;"><?php echo $i++; ?></td>
                    <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($course['course_title']); ?></td>
                    <td style="text-align:center;"><?php echo $course['credits']; ?></td>
                    <td class="date-col"></td>
                    <td class="sign-col"></td>
                </tr>
                <?php endforeach; ?>
                
                <tr class="total-row">
                    <td colspan="3" style="text-align:right; padding:8px;">TOTAL UNITS:</td>
                    <td style="text-align:center; padding:8px;"><?php echo $total_units; ?></td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>

        <!-- ATTESTATION -->
        <div class="attestation-box">
            <div class="attestation-title">Student's Attestation</div>
            <ol class="attestation-list">
                <li>Make sure all the information on this card is correct.</li>
                <li>If there is any mistake on your card, please contact the Department Exam Officer before the exam.</li>
                <li>You will only be allowed to enter the exam venue with this card <strong>AND</strong> a valid ID card.</li>
                <li>Be at the examination hall at least <strong>thirty (30) minutes</strong> before the examination.</li>
                <li>Writing on any text or drawing is <strong>prohibited</strong> on this card.</li>
                <li>Personal belongings such as bags, coats, purses, etc. shall be deposited at a place designated by the invigilator.</li>
                <li><strong>Phones are NOT allowed</strong> into the examination venue.</li>
            </ol>
        </div>

        <!-- SIGNATURES -->
        <div class="exam-signature-area">
            <div class="sign-row">
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <div class="sign-title">Exam Officer's Signature and Stamp</div>
                </div>
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <div class="sign-title">Student's Signature</div>
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="exam-footer">
            <p>This is a computer-generated document. No signature is required.</p>
            <p>Dala College of Education, Kano — <?php echo date('Y'); ?></p>
        </div>
        
    </div>

    <!-- ACTIONS -->
    <div class="actions no-print">
        <a href="student_dashboard.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <button onclick="trackAndPrint('exam_card')" class="btn-print">
            <i class="fas fa-print"></i> Print Exam Card
        </button>
    </div>
<?php endif; ?>

<script>
function trackAndPrint(docType) {
    fetch('log_download.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'student_id=<?php echo $student_id; ?>&document_type=' + docType + '&action_type=print'
    }).finally(function() {
        window.print();
    });
}
</script>

</body>
</html>