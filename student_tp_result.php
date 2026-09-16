<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

if ($role == 'admin' && isset($_GET['student_id'])) {
    $student_id = intval($_GET['student_id']);
} elseif ($role == 'student') {
    $student_id = $user_id;
} else {
    die("Unauthorized access.");
}

// ============================================
// GET STUDENT DATA
// ============================================
$s_query = "SELECT * FROM students WHERE id = $student_id";
$s_result = mysqli_query($conn, $s_query);
$student = mysqli_fetch_assoc($s_result);

if (!$student) {
    die("Student not found!");
}

$student_combination = !empty($student['combination']) 
    ? trim($student['combination']) 
    : trim($student['course']);

// ============================================
// GET SELECTED SESSION
// ============================================
$selected_session = isset($_GET['session']) ? mysqli_real_escape_string($conn, $_GET['session']) : '2024/2025';
$sessions = ['2024/2025', '2023/2024', '2022/2023'];

// ============================================
// GET T.P RESULT
// ============================================
$tp_query = "SELECT * FROM tp_results 
             WHERE student_id = $student_id 
             AND academic_year = '$selected_session'
             LIMIT 1";
$tp_result = mysqli_query($conn, $tp_query);
$tp = mysqli_fetch_assoc($tp_result);

// ============================================
// T.P REFERENCE NUMBER
// ============================================
$tp_ref = 'REF: DCOE/K/REG/T.P/' . str_replace('/', '', $selected_session) . '/' . str_pad($student_id, 3, '0', STR_PAD_LEFT);

// ============================================
// STUDENT PHOTO
// ============================================
$photo_path = "uploads/students/default.png";
if (!empty($student['photo']) && file_exists("uploads/students/" . $student['photo'])) {
    $photo_path = "uploads/students/" . $student['photo'];
} elseif (!empty($student['reg_no']) && file_exists("uploads/students/" . $student['reg_no'] . ".jpg")) {
    $photo_path = "uploads/students/" . $student['reg_no'] . ".jpg";
}

// ============================================
// GRADE SCALE
// ============================================
$grade_scale = [
    ['grade' => 'A (Distinction)', 'range' => '70 – 100'],
    ['grade' => 'B (Credit)', 'range' => '60 – 69'],
    ['grade' => 'C (Merit)', 'range' => '50 – 59'],
    ['grade' => 'D (Pass)', 'range' => '45 – 49'],
    ['grade' => 'F (Fail)', 'range' => '0 – 44']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teaching Practice Result - Dala College</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Times New Roman', serif;
            background: #f0f4f8;
            padding: 20px;
        }
        
        .container {
            max-width: 850px;
            margin: 0 auto;
            background: white;
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 4px 30px rgba(0,0,0,0.15);
            border: 2px solid #0d2818;
            position: relative;
            overflow: hidden;
        }
        
        /* WATERMARK LOGO A TSAKIYA */
        .watermark-bg {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 320px;
            height: 320px;
            opacity: 0.08;
            z-index: 0;
            pointer-events: none;
        }
        .watermark-bg img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .content {
            position: relative;
            z-index: 2;
        }
        
        /* ============ HEADER ============ */
        .tp-header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
        }
        .tp-header .logo-top {
            width: 70px;
            height: 70px;
            margin: 0 auto 8px;
        }
        .tp-header .logo-top img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .tp-header .college-name {
            font-size: 22px;
            font-weight: 900;
            color: #2e7d32;
            text-transform: uppercase;
            letter-spacing: 1px;
            line-height: 1.1;
        }
        .tp-header .state-name {
            font-size: 12px;
            font-weight: 700;
            color: #0d2818;
            text-transform: uppercase;
            margin: 2px 0;
        }
        .tp-header .accreditation {
            font-size: 10px;
            color: #c62828;
            font-weight: 700;
            text-transform: uppercase;
            margin: 3px 0;
        }
        .tp-header .contact {
            font-size: 9px;
            color: #1976d2;
            font-weight: 600;
        }
        .tp-header .contact a {
            color: #1976d2;
            text-decoration: none;
        }
        
        /* ============ RESULT SLIP TITLE ============ */
        .result-title {
            text-align: center;
            margin: 18px 0 10px;
            padding: 8px 0;
            border-top: 2px solid #0d2818;
            border-bottom: 2px solid #0d2818;
        }
        .result-title h2 {
            font-size: 18px;
            font-weight: 900;
            color: #c62828;
            font-style: italic;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .ref-number {
            text-align: center;
            font-size: 12px;
            font-weight: 800;
            color: #c62828;
            margin-bottom: 18px;
            font-style: italic;
            letter-spacing: 0.5px;
        }
        
        /* ============ STUDENT INFO ============ */
        .student-info {
            margin-bottom: 15px;
            font-size: 13px;
        }
        .student-info .row {
            display: flex;
            padding: 4px 0;
            border-bottom: 1px dotted #999;
        }
        .student-info .row:last-child { border-bottom: none; }
        .student-info .label {
            font-weight: 700;
            color: #0d2818;
            min-width: 160px;
        }
        .student-info .value {
            font-weight: 700;
            color: #000;
            text-transform: uppercase;
        }
        .student-info .value-underline {
            font-weight: 700;
            color: #000;
            border-bottom: 1px solid #0d2818;
            padding: 0 15px;
            text-transform: uppercase;
        }
        
        /* ============ GRADE SCALE BOX ============ */
        .grade-scale-box {
            background: #f8faf8;
            border: 2px solid #0d2818;
            border-radius: 8px;
            padding: 15px 20px;
            margin: 20px 0;
        }
        .grade-scale-box h3 {
            font-size: 13px;
            color: #0d2818;
            text-transform: uppercase;
            font-weight: 900;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #0d2818;
            padding-bottom: 5px;
        }
        .grade-scale-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 30px;
        }
        .grade-scale-item {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            padding: 3px 0;
            border-bottom: 1px dotted #ccc;
        }
        .grade-scale-item:last-child { border-bottom: none; }
        .grade-scale-item .grade-name {
            font-weight: 700;
            color: #0d2818;
        }
        .grade-scale-item .grade-range {
            font-weight: 700;
            color: #c62828;
        }
        
        /* ============ FINAL RESULT BOX ============ */
        .final-result-box {
            background: linear-gradient(135deg, #0d2818, #2e7d32);
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin: 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .final-result-box .item {
            text-align: center;
            flex: 1;
        }
        .final-result-box .item .label {
            font-size: 10px;
            color: #c8e6c9;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .final-result-box .item .value {
            font-size: 22px;
            font-weight: 900;
            color: #ffd54f;
            margin-top: 3px;
        }
        
        /* ============ SIGNATURE & QR ============ */
        .signature-area {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 20px;
        }
        .signature-area .qr-box {
            text-align: center;
        }
        .signature-area .qr-box img {
            width: 90px;
            height: 90px;
        }
        .signature-area .qr-box p {
            font-size: 9px;
            color: #666;
            margin-top: 3px;
        }
        .signature-area .sign-box {
            text-align: center;
            min-width: 220px;
        }
        .signature-area .sign-line {
            border-top: 2px solid #0d2818;
            padding-top: 5px;
            margin-bottom: 3px;
            min-width: 180px;
        }
        .signature-area .sign-title {
            font-size: 11px;
            font-weight: 800;
            color: #0d2818;
            text-transform: uppercase;
        }
        
        /* ============ FOOTER ============ */
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 9px;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 8px;
        }
        
        /* ============ ACTIONS ============ */
        .actions {
            text-align: center;
            margin-top: 20px;
        }
        .btn {
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 5px;
            font-family: 'Segoe UI', sans-serif;
        }
        .btn-print { background: #2e7d32; color: white; }
        .btn-print:hover { background: #1b5e20; }
        .btn-back { background: #6a8f6a; color: white; }
        .btn-back:hover { background: #4a6a4a; }
        
        .session-selector {
            text-align: center;
            margin-bottom: 20px;
        }
        .session-selector select {
            padding: 10px 20px;
            border: 2px solid #2e7d32;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .no-result {
            text-align: center;
            padding: 50px;
            color: #6a8f6a;
        }
        .no-result i {
            font-size: 3rem;
            display: block;
            margin-bottom: 10px;
            color: #dce8dc;
        }
        
        /* ============ PRINT ============ */
        @media print {
            .no-print { display: none !important; }
            body { background: white; padding: 0; margin: 0; }
            .container { 
                box-shadow: none; 
                padding: 10mm; 
                border: none;
                max-width: 100%;
                border-radius: 0;
            }
            .watermark-bg {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .final-result-box {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            @page {
                size: A4 portrait;
                margin: 8mm;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        
        <!-- WATERMARK LOGO -->
        <div class="watermark-bg">
            <img src="images/dala-logo.png" alt="Watermark">
        </div>
        
        <div class="content">
            
            <!-- ============ HEADER ============ -->
            <div class="tp-header">
                <div class="logo-top">
                    <img src="images/dala-logo.png" alt="Logo">
                </div>
                <div class="college-name">Dala College of Education Kano,</div>
                <div class="state-name">Kano State</div>
                <div class="accreditation">Accredited by National Commission for Colleges of Education (NCCE) Abuja</div>
                <div class="contact">
                    Website: www.dalacoe.ng &nbsp;|&nbsp; Email: info@dalacoe.ng
                </div>
            </div>
            
            <!-- ============ RESULT SLIP TITLE ============ -->
            <div class="result-title">
                <h2>Teaching Practice (T.P) — Result Slip</h2>
            </div>
            
            <!-- ============ REF NUMBER ============ -->
            <div class="ref-number"><?php echo $tp_ref; ?></div>
            
            <!-- ============ STUDENT INFO ============ -->
            <div class="student-info">
                <div class="row">
                    <span class="label">Name:</span>
                    <span class="value"><?php echo htmlspecialchars($student['fullname']); ?></span>
                </div>
                <div class="row">
                    <span class="label">Reg No:</span>
                    <span class="value"><?php echo htmlspecialchars($student['reg_no'] ?? 'N/A'); ?></span>
                </div>
                <div class="row">
                    <span class="label">Department:</span>
                    <span class="value"><?php echo htmlspecialchars($student_combination); ?></span>
                </div>
                <?php if ($tp): ?>
                <div class="row">
                    <span class="label">Total Score:</span>
                    <span class="value-underline"><?php echo $tp['total_score']; ?></span>
                    <span style="margin-left:5px; font-weight:700;">/100</span>
                </div>
                <div class="row">
                    <span class="label">Overall Average:</span>
                    <span class="value"><?php echo number_format($tp['total_score'], 1); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- ============ GRADE SCALE ============ -->
            <div class="grade-scale-box">
                <h3><i class="fas fa-list-ol"></i> Final Grade Scale</h3>
                <div class="grade-scale-grid">
                    <?php foreach ($grade_scale as $gs): ?>
                    <div class="grade-scale-item">
                        <span class="grade-name"><?php echo $gs['grade']; ?>:</span>
                        <span class="grade-range"><?php echo $gs['range']; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- ============ FINAL RESULT ============ -->
            <?php if ($tp): ?>
            <div class="final-result-box">
                <div class="item">
                    <div class="label">Total Score</div>
                    <div class="value"><?php echo $tp['total_score']; ?>/100</div>
                </div>
                <div class="item">
                    <div class="label">Final Grade</div>
                    <div class="value"><?php echo $tp['grade']; ?></div>
                </div>
                <div class="item">
                    <div class="label">Remark</div>
                    <div class="value"><?php echo strtoupper($tp['remark'] ?? 'N/A'); ?></div>
                </div>
            </div>
            <?php else: ?>
            <div class="no-result">
                <i class="fas fa-chalkboard-teacher"></i>
                <h3>No T.P Result Found</h3>
                <p>No T.P result has been recorded for this student in the <?php echo $selected_session; ?> session.</p>
            </div>
            <?php endif; ?>
            
            <!-- ============ SIGNATURE & QR ============ -->
            <div class="signature-area">
                <div class="qr-box">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=90x90&data=<?php echo urlencode($tp_ref); ?>" alt="QR Code">
                    <p>Scan to verify</p>
                </div>
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <div class="sign-title">T.P Co-ordinator</div>
                </div>
            </div>
            
            <!-- ============ FOOTER ============ -->
            <div class="footer">
                <p>This is a computer-generated document. No signature is required.</p>
                <p>Dala College of Education, Kano — <?php echo date('Y'); ?></p>
            </div>
            
        </div>
    </div>
    
    <!-- ============ ACTIONS ============ -->
    <div class="actions no-print">
        <div class="session-selector">
            <form method="GET" action="">
                <?php if ($role == 'admin'): ?>
                    <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                <?php endif; ?>
                <label style="font-weight:700; margin-right:10px;">Select Session:</label>
                <select name="session" onchange="this.form.submit()">
                    <?php foreach ($sessions as $sess): ?>
                        <option value="<?php echo $sess; ?>" <?php echo ($selected_session == $sess) ? 'selected' : ''; ?>>
                            <?php echo $sess; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php if ($role == 'admin'): ?>
            <a href="admin_dashboard.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        <?php else: ?>
            <a href="student_dashboard.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        <?php endif; ?>
        <button onclick="window.print()" class="btn btn-print">
            <i class="fas fa-print"></i> Print T.P Result
        </button>
    </div>

</body>
</html>