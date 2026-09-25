<?php
session_start();
include 'connect.php';

// Check if student is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['user_id'];

// Get student data
$query = "SELECT * FROM students WHERE id = '$student_id'";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Database error: " . mysqli_error($conn));
}

$student = mysqli_fetch_assoc($result);

if (!$student) {
    die("Student not found in database.");
}

// ============================================
// CHECK IF STUDENT IS APPROVED
// ============================================
$is_approved = ($student['status'] == 'active' || $student['status'] == 'approved');

if (!$is_approved) {
    header('Location: student_dashboard.php?error=You are not admitted yet');
    exit();
}

// ============================================
// GENERATE REGISTRATION NUMBER
// ============================================
$reg_no = $student['reg_no'] ?? $student['student_id'];
if (empty($reg_no)) {
    $reg_no = 'DLCOE/NCE/' . date('y') . 'A' . sprintf('%03d', $student['id']) . '/' . substr(strtoupper($student['course'] ?? 'ENG'), 0, 3) . sprintf('%03d', $student['id']);
}

// ============================================
// APPLICATION NUMBER
// ============================================
$application_no = $student['application_no'] ?? ('002/' . str_pad($student['id'], 6, '0', STR_PAD_LEFT));

// ============================================
// PHOTO PATH
// ============================================
$photo_file = '';
if (!empty($student['photo'])) {
    if (file_exists('uploads/students/' . $student['photo'])) {
        $photo_file = 'uploads/students/' . $student['photo'];
    } elseif (file_exists('uploads/' . $student['photo'])) {
        $photo_file = 'uploads/' . $student['photo'];
    }
}

if (empty($photo_file) && !empty($student['reg_no'])) {
    $safe_reg = str_replace(['/', ' '], '_', $student['reg_no']);
    foreach (['jpg', 'jpeg', 'png'] as $ext) {
        if (file_exists('uploads/students/' . $student['reg_no'] . '.' . $ext)) {
            $photo_file = 'uploads/students/' . $student['reg_no'] . '.' . $ext;
            break;
        }
        if (file_exists('uploads/students/' . $safe_reg . '.' . $ext)) {
            $photo_file = 'uploads/students/' . $safe_reg . '.' . $ext;
            break;
        }
    }
}

// ============================================
// ACADEMIC SESSION
// ============================================
$academic_session = '2026/2027';

// ============================================
// PROGRAMME TYPE
// ============================================
$programme = strtoupper($student['programme'] ?? 'NCE');
$combination = strtoupper($student['course'] ?? $student['combination'] ?? 'N/A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Letter - Dala College</title>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Georgia', 'Times New Roman', serif;
            background: #e8f0e8;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }
        
        /* ============================================
           TOPBAR
           ============================================ */
        .topbar {
            max-width: 900px;
            width: 100%;
            background: #0d2818;
            padding: 12px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            border-radius: 12px;
            margin-bottom: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .topbar .logo-title { color: #ffd54f; font-size: 1.2rem; font-weight: 800; letter-spacing: 2px; }
        .topbar .logo-title span { color: #a5d6a7; }
        .topbar .logo-sub { display: block; font-size: 0.55rem; color: #c8e6c9; letter-spacing: 2px; }
        .topbar nav a { color: #c8e6c9; text-decoration: none; padding: 8px 18px; border-radius: 30px; font-size: 0.85rem; font-weight: 500; transition: all 0.3s ease; }
        .topbar nav a:hover { background: #2e7d32; color: white; }
        .topbar nav .active { background: #ffd54f; color: #0d2818 !important; font-weight: 700; }
        
        /* ============================================
           ACTION BUTTONS
           ============================================ */
        .action-buttons {
            max-width: 900px;
            width: 100%;
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        .btn-print {
            padding: 12px 40px;
            background: linear-gradient(135deg, #2e7d32, #1b5e20);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(46, 125, 50, 0.25);
            letter-spacing: 1px;
            font-family: 'Segoe UI', sans-serif;
        }
        .btn-print:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(46, 125, 50, 0.35);
        }
        .btn-back {
            padding: 12px 40px;
            border: 2px solid #2e7d32;
            color: #2e7d32;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
            letter-spacing: 1px;
            font-family: 'Segoe UI', sans-serif;
        }
        .btn-back:hover {
            background: #2e7d32;
            color: white;
            transform: translateY(-3px);
        }
        
        /* ============================================
           PAGE CONTAINER
           ============================================ */
        .page {
            max-width: 900px;
            width: 100%;
            background: #ffffff;
            padding: 30px 45px;
            border-radius: 8px;
            box-shadow: 0 8px 40px rgba(0, 30, 0, 0.12);
            border: 4px solid #0d2818;
            position: relative;
            overflow: hidden;
            margin-bottom: 20px;
            page-break-after: always;
            page-break-inside: avoid;
            break-after: page;
            break-inside: avoid;
        }
        .page:last-child {
            page-break-after: auto;
            break-after: auto;
        }
        
        .page::after {
            content: 'DALA COLLEGE OF EDUCATION, KANO DALA COLLEGE OF EDUCATION, KANO DALA COLLEGE OF EDUCATION, KANO';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            font-size: 9px;
            color: rgba(13, 40, 24, 0.03);
            letter-spacing: 6px;
            word-spacing: 12px;
            line-height: 3;
            text-align: justify;
            pointer-events: none;
            z-index: 0;
            padding: 15px;
            font-weight: 700;
            white-space: pre-wrap;
            overflow: hidden;
        }
        
        .page::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 55%;
            height: 55%;
            background: url('images/dala-logo.png') no-repeat center center;
            background-size: contain;
            opacity: 0.035;
            pointer-events: none;
            z-index: 0;
        }
        
        .page > * { position: relative; z-index: 1; }
        
        /* ============================================
           HEADER
           ============================================ */
        .letter-header {
            text-align: center;
            border-bottom: 2px solid #0d2818;
            padding-bottom: 10px;
            margin-bottom: 12px;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .letter-header .header-top {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 18px;
            margin-bottom: 5px;
        }
        .letter-header .header-top .logo-img {
            width: 100px;
            height: 100px;
            object-fit: contain;
            flex-shrink: 0;
        }
        .letter-header .header-top .title-group {
            text-align: center;
        }
        .letter-header .header-top .title-group .college-name {
            font-size: 1.6rem;
            font-weight: 800;
            color: #0d2818;
            letter-spacing: 2px;
            line-height: 1.2;
            text-transform: uppercase;
        }
        .letter-header .header-top .title-group .college-name span {
            color: #2e7d32;
        }
        .letter-header .header-top .title-group .college-sub {
            font-size: 0.85rem;
            color: #1a2e1a;
            font-weight: 600;
            letter-spacing: 2px;
            margin-top: 2px;
        }
        .letter-header .accreditation {
            font-size: 0.75rem;
            color: #2e7d32;
            font-weight: 600;
            margin-top: 4px;
        }
        .letter-header .contact-info {
            font-size: 0.75rem;
            color: #4a6a4a;
            margin-top: 4px;
            display: flex;
            justify-content: center;
            gap: 25px;
            flex-wrap: wrap;
        }
        .letter-header .contact-info a {
            color: #2e7d32;
            text-decoration: none;
            font-weight: 600;
        }
        .letter-header .office {
            font-size: 0.8rem;
            font-weight: 700;
            margin-top: 6px;
            text-transform: uppercase;
            letter-spacing: 3px;
            background: #0d2818;
            color: #ffd54f;
            padding: 3px 22px;
            border-radius: 20px;
            display: inline-block;
        }
        
        /* ============================================
           TITLE
           ============================================ */
        .letter-title {
            text-align: center;
            font-size: 1.15rem;
            font-weight: 700;
            color: #c62828;
            text-transform: uppercase;
            margin: 10px 0 8px 0;
            letter-spacing: 2px;
        }
        .letter-title .sub-title {
            font-size: 0.8rem;
            font-weight: 400;
            color: #0d2818;
            display: block;
            margin-top: 2px;
            text-transform: none;
            letter-spacing: 1px;
            font-style: italic;
        }
        
        /* ============================================
           STUDENT INFO + PHOTO
           ============================================ */
        .student-wrapper {
            display: grid;
            grid-template-columns: 1fr 90px;
            gap: 15px;
            background: #f8faf8;
            padding: 12px 18px;
            border-radius: 8px;
            border-left: 4px solid #2e7d32;
            margin-bottom: 12px;
            align-items: start;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .student-info {
            font-size: 0.95rem;
            line-height: 1.8;
        }
        .student-info .label {
            font-weight: 700;
            color: #0d2818;
            display: inline-block;
            min-width: 130px;
        }
        .student-info .value {
            color: #1a2e1a;
            font-weight: 500;
        }
        .student-photo {
            width: 90px;
            height: 110px;
            border: 3px solid #2e7d32;
            border-radius: 8px;
            overflow: hidden;
            background: #f5faf5;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.06);
        }
        .student-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .student-photo span {
            font-size: 2rem;
            color: #bbb;
        }
        
        /* ============================================
           CONTENT
           ============================================ */
        .content {
            font-size: 0.92rem;
            line-height: 1.75;
            color: #1a2e1a;
        }
        .content p {
            margin-bottom: 8px;
            text-align: justify;
        }
        .content p strong { color: #0d2818; }
        
        .section-title {
            font-weight: 700;
            color: #c62828;
            font-size: 0.95rem;
            margin-top: 12px;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 2px;
            border-bottom: 1px solid #ffcdd2;
            padding-bottom: 3px;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        
        .conditions {
            margin: 6px 0;
            padding-left: 22px;
        }
        .conditions li {
            margin-bottom: 4px;
            line-height: 1.65;
            color: #1a2e1a;
            font-size: 0.9rem;
            text-align: justify;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .conditions li strong { color: #0d2818; font-weight: 700; }
        
        /* ============================================
           FEES TABLE
           ============================================ */
        .fees-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 0.9rem;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .fees-table th {
            background: #0d2818;
            color: white;
            padding: 8px 12px;
            text-align: left;
            font-weight: 700;
            border: 1px solid #0d2818;
        }
        .fees-table td {
            padding: 8px 12px;
            border: 1px solid #ccc;
        }
        .fees-table tr:nth-child(even) td {
            background: #f8faf8;
        }
        .fees-table .total-row td {
            background: #e8f5e9;
            font-weight: 800;
            color: #0d2818;
            font-size: 1rem;
        }
        .fees-table .amount-col {
            text-align: right;
            font-weight: 700;
        }
        
        /* ============================================
           NOTES
           ============================================ */
        .notes-list {
            margin: 6px 0;
            padding-left: 22px;
        }
        .notes-list li {
            margin-bottom: 5px;
            line-height: 1.6;
            font-size: 0.88rem;
            text-align: justify;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        
        /* ============================================
           PORTAL STEPS
           ============================================ */
        .portal-steps {
            margin: 6px 0;
            padding-left: 22px;
            list-style: decimal;
        }
        .portal-steps li {
            margin-bottom: 5px;
            line-height: 1.6;
            font-size: 0.9rem;
            text-align: justify;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        
        /* ============================================
           UNDERTAKING
           ============================================ */
        .undertaking {
            margin-top: 15px;
        }
        .undertaking p {
            font-size: 0.9rem;
            line-height: 1.75;
            margin-bottom: 10px;
            text-align: justify;
        }
        .signature-area {
            margin-top: 25px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .signature-box {
            padding-top: 5px;
        }
        .signature-box .sign-line {
            border-top: 2px solid #0d2818;
            padding-top: 8px;
            margin-top: 30px;
            font-weight: 700;
            font-size: 0.85rem;
            color: #0d2818;
        }
        .signature-box .sign-title {
            font-size: 0.78rem;
            color: #4a6a4a;
            font-style: italic;
        }
        
        /* ============================================
           LETTER FOOTER
           ============================================ */
        .letter-footer {
            margin-top: 15px;
            padding-top: 12px;
            border-top: 2px dashed #dce8dc;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .letter-footer .signature {
            margin-top: 15px;
        }
        .letter-footer .signature .line {
            width: 200px;
            border-bottom: 2px solid #0d2818;
            margin: 22px 0 4px 0;
        }
        .letter-footer .signature .name {
            font-weight: 700;
            color: #0d2818;
            font-size: 0.95rem;
            letter-spacing: 1px;
        }
        .letter-footer .signature .title {
            color: #1a2e1a;
            font-size: 0.85rem;
            letter-spacing: 2px;
            font-weight: 600;
        }
        
        /* ============================================
           PAGE FOOTER
           ============================================ */
        .page-footer {
            text-align: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #dce8dc;
            font-size: 0.75rem;
            color: #6a8f6a;
            font-style: italic;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .page-number {
            font-weight: 700;
            color: #0d2818;
        }
        
        /* ============================================
           PRINT
           ============================================ */
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            html, body {
                width: 210mm;
                height: 297mm;
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
                font-size: 10.5pt;
                line-height: 1.4;
            }
            
            .topbar, .action-buttons, .no-print { 
                display: none !important; 
            }
            
            .page { 
                width: 210mm !important;
                height: 297mm !important;
                min-height: 297mm !important;
                max-height: 297mm !important;
                margin: 0 !important;
                padding: 12mm 15mm !important;
                box-sizing: border-box !important;
                border: 3px solid #0d2818 !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                position: relative !important;
                overflow: hidden !important;
                page-break-after: always !important;
                page-break-inside: avoid !important;
                break-after: page !important;
                break-inside: avoid !important;
                display: block !important;
                float: none !important;
            }
            
            .page:last-child {
                page-break-after: auto !important;
                break-after: auto !important;
            }
            
            .page::before { opacity: 0.05 !important; }
            .page::after { opacity: 0.15 !important; }
            
            .letter-header .header-top .title-group .college-name { font-size: 1.3rem !important; }
            .letter-header .header-top .title-group .college-sub { font-size: 0.75rem !important; }
            .letter-header .header-top .logo-img { width: 75px !important; height: 75px !important; }
            .letter-header .accreditation { font-size: 0.65rem !important; }
            .letter-header .contact-info { font-size: 0.65rem !important; }
            .letter-header .office { font-size: 0.7rem !important; padding: 2px 15px !important; }
            
            .letter-title { font-size: 1rem !important; margin: 6px 0 5px 0 !important; }
            .letter-title .sub-title { font-size: 0.7rem !important; }
            
            .student-wrapper { padding: 8px 12px !important; margin-bottom: 8px !important; }
            .student-info { font-size: 0.82rem !important; line-height: 1.5 !important; }
            .student-photo { width: 70px !important; height: 85px !important; }
            
            .content { font-size: 0.8rem !important; line-height: 1.45 !important; }
            .content p { margin-bottom: 4px !important; }
            
            .section-title { font-size: 0.8rem !important; margin-top: 8px !important; margin-bottom: 3px !important; }
            
            .conditions li,
            .notes-list li,
            .portal-steps li {
                font-size: 0.78rem !important;
                line-height: 1.45 !important;
                margin-bottom: 2px !important;
            }
            
            .fees-table { font-size: 0.8rem !important; margin: 6px 0 !important; }
            .fees-table th, .fees-table td { padding: 4px 8px !important; }
            
            .undertaking p { font-size: 0.8rem !important; line-height: 1.5 !important; margin-bottom: 6px !important; }
            
            .signature-area { margin-top: 15px !important; gap: 20px !important; }
            .signature-box .sign-line { margin-top: 20px !important; font-size: 0.75rem !important; }
            .signature-box .sign-title { font-size: 0.7rem !important; }
            
            .letter-footer { margin-top: 10px !important; padding-top: 8px !important; }
            .letter-footer .signature .line { margin: 15px 0 3px 0 !important; }
            .letter-footer .signature .name { font-size: 0.85rem !important; }
            .letter-footer .signature .title { font-size: 0.75rem !important; }
            .letter-footer .signature img { width: 120px !important; margin: 5px 0 2px 0 !important; }
            
            .page-footer { font-size: 0.68rem !important; margin-top: 10px !important; padding-top: 6px !important; }
            
            .letter-header, .student-wrapper, .content, .conditions li,
            .fees-table, .fees-table tr, .notes-list li, .portal-steps li,
            .undertaking, .signature-area, .letter-footer, .page-footer {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
            
            @page { 
                size: A4 portrait;
                margin: 0;
            }
        }
        
        @media (max-width: 768px) {
            .page { padding: 15px 18px; border-width: 3px; }
            .letter-header .header-top { flex-direction: column; gap: 8px; }
            .letter-header .header-top .logo-img { width: 80px; height: 80px; }
            .letter-header .header-top .title-group .college-name { font-size: 1.1rem; }
            .student-wrapper { grid-template-columns: 1fr; }
            .student-photo { margin: 0 auto; }
            .signature-area { grid-template-columns: 1fr; }
            .topbar { flex-direction: column; gap: 8px; }
        }
    </style>
</head>
<body>

    <!-- TOPBAR -->
    <div class="topbar no-print">
        <div>
            <span class="logo-title">DALA <span>COLLEGE</span></span>
            <span class="logo-sub">of Education, Kano</span>
        </div>
        <nav>
            <a href="student_dashboard.php">Dashboard</a>
            <a href="admission_letter.php" class="active">Admission Letter</a>
        </nav>
    </div>

    <!-- ACTION BUTTONS -->
    <div class="action-buttons no-print">
        <button onclick="trackAndPrint()" class="btn-print">🖨️ Print / Download PDF</button>
        <a href="student_dashboard.php" class="btn-back">← Back to Dashboard</a>
    </div>

    <!-- ============================================ -->
    <!-- PAGE 1 -->
    <!-- ============================================ -->
    <div class="page" id="page-1">
        
        <div class="letter-header">
            <div class="header-top">
                <img src="images/dala-logo.png" alt="Dala College Logo" class="logo-img" onerror="this.style.visibility='hidden'">
                <div class="title-group">
                    <div class="college-name">DALA <span>COLLEGE OF EDUCATION, KANO</span></div>
                    <div class="college-sub">KANO STATE - NIGERIA</div>
                </div>
            </div>
            
            <div class="accreditation">Accredited by National Commission for Colleges of Education (NCCE), Abuja</div>
            
            <div class="contact-info">
                <span>🌐 www.dalacoe.edu.ng</span>
                <span>✉️ info@dalacoe.edu.ng</span>
            </div>
            
            <div class="office">OFFICE OF THE REGISTRAR</div>
        </div>

        <div class="letter-title">
            PROVISIONAL OFFER OF ADMISSION
            <span class="sub-title">UNDER THE DUAL MANDATE (NCE &amp; DEGREE) INITIATIVE</span>
        </div>

        <div class="student-wrapper">
            <div class="student-info">
                <div><span class="label">Name:</span> <span class="value"><?php echo strtoupper($student['fullname']); ?></span></div>
                <div><span class="label">Admission No.:</span> <span class="value"><?php echo $reg_no; ?></span></div>
                <div><span class="label">Combination:</span> <span class="value"><?php echo $combination; ?></span></div>
                <div><span class="label">Programme:</span> <span class="value">Dual Mandate (NCE &amp; Degree)</span></div>
                <div><span class="label">Study Centre:</span> <span class="value"><?php echo $student['branch_code'] ?? 'SHINGE'; ?></span></div>
            </div>
            <div class="student-photo">
                <?php if (!empty($photo_file) && file_exists($photo_file)): ?>
                    <img src="<?php echo $photo_file; ?>" alt="Student Photo">
                <?php else: ?>
                    <span>📷</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="content">
            <p><strong>Dear <?php echo $student['fullname']; ?>,</strong></p>
            
            <p>
                I am pleased to inform you that you have been offered <strong>provisional admission</strong> into the College 
                for the <strong><?php echo $academic_session; ?> Academic Session</strong> under the Dual Mandate (NCE &amp; Degree) Initiative.
            </p>
            
            <p>
                This admission covers two qualifications: a <strong>three (3) year National Certificate in Education (NCE)</strong> 
                followed by a <strong>two (2) year Degree programme</strong>, making a total duration of <strong>five (5) years</strong> 
                of study, subject to the applicable academic and regulatory requirements.
            </p>

            <div class="section-title">CONDITIONS OF ADMISSION</div>
            <ol class="conditions" type="a">
                <li>The credentials submitted by you are correct, and you shall present original copies during registration.</li>
                <li>All admissions are based on <strong>NCCE requirements</strong>; any deficiency must be corrected before graduation.</li>
                <li>You will pay all required fees at the appropriate time without delay.</li>
                <li>You must abide by all rules and regulations of the College.</li>
                <li>You must not engage in any act of riot or unrest; violation will lead to dismissal.</li>
                <li>You must comply with the College dress code.</li>
                <li>You are required to complete and return the sponsorship form.</li>
                <li>You must regularize your admission with <strong>JAMB</strong> and obtain a JAMB registration number.</li>
                <li>You must submit a <strong>medical fitness certificate</strong> from a recognized hospital.</li>
                <li>You must regularly check the College portal/social media for updates.</li>
                <li>The College reserves the right to introduce new changes, whether academic, financial, administrative, or otherwise, whenever the need arises.</li>
                <li>You must comply with all directives, instructions, procedures, and official decisions issued by the College Authority from time to time.</li>
                <li>The College Authority reserves the right to suspend or dismiss any student who violates any of these rules and regulations.</li>
            </ol>

            <p style="margin-top: 10px;">Accept my congratulations.</p>
        </div>

        <div class="letter-footer">
            <p style="font-size: 0.9rem;">Sincerely,</p>
            <div class="signature">
                <img src="images/registrar-signature.png" 
                     alt="Registrar Signature" 
                     style="width: 150px; height: auto; margin: 8px 0 3px 0; display: block;"
                     onerror="this.style.display='none'">
                <div class="line"></div>
                <div class="name">HAJIYA LUBABATU ALIYU YOLA</div>
                <div class="title">REGISTRAR</div>
            </div>
        </div>
        
        <div class="page-footer">
            Page <span class="page-number">1</span> of 2 — Dala College of Education, Kano
        </div>
    </div>

    <!-- ============================================ -->
    <!-- PAGE 2 -->
    <!-- ============================================ -->
    <div class="page" id="page-2">
        
        <div class="letter-header">
            <div class="header-top">
                <img src="images/dala-logo.png" alt="Dala College Logo" class="logo-img" onerror="this.style.visibility='hidden'">
                <div class="title-group">
                    <div class="college-name">DALA <span>COLLEGE OF EDUCATION, KANO</span></div>
                    <div class="college-sub">KANO STATE - NIGERIA</div>
                </div>
            </div>
            <div class="office" style="margin-top: 8px;"><?php echo $academic_session; ?> ACADEMIC SESSION</div>
        </div>

        <div class="section-title">SECTION A — SCHEDULE OF FEES (<?php echo $academic_session; ?>)</div>
        
        <table class="fees-table">
            <thead>
                <tr>
                    <th style="width: 50px;">S/N</th>
                    <th>ITEM</th>
                    <th style="width: 150px; text-align: right;">AMOUNT (₦)</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>1.</td><td>Tuition</td><td class="amount-col">21,600.00</td></tr>
                <tr><td>2.</td><td>Examination</td><td class="amount-col">10,000.00</td></tr>
                <tr><td>3.</td><td>Administrative Charges</td><td class="amount-col">3,000.00</td></tr>
                <tr><td>4.</td><td>Maintenance</td><td class="amount-col">1,500.00</td></tr>
                <tr class="total-row">
                    <td colspan="2" style="text-align: right;">TOTAL</td>
                    <td class="amount-col">36,100.00</td>
                </tr>
            </tbody>
        </table>

        <div class="section-title" style="margin-top: 12px;">NOTES</div>
        <ul class="notes-list">
            <li>The above fees exclude other charges such as Faculty, Departmental, Orientation, Matriculation, Convocation, Acceptance Form, Students' Union, ID Card, Library, Handbook, JAMB, etc.</li>
            <li>All payments must be made through official College channels.</li>
            <li><strong>Scholarship</strong> covers ₦21,600 only; the student must pay the balance of <strong>₦14,500</strong>.</li>
            <li>All fees are <strong>non-refundable</strong>.</li>
            <li>Failure to register within <strong>two (2) weeks</strong> may lead to loss of admission or penalties.</li>
        </ul>

        <div class="section-title" style="margin-top: 12px;">SECTION B — STUDENT PORTAL ACCESS</div>
        <p style="font-size: 0.9rem; margin-bottom: 6px;">
            Whenever the need for any document arises, such as Admission Letter, Semester/Course Registration Form, 
            Exam Card, Semester Result, Transcript, ID Card, etc., the student shall obtain a <strong>Scratch Card (PIN Number)</strong> 
            in order to download the required document directly from the College website as follows:
        </p>
        
        <ol class="portal-steps">
            <li>Visit: <strong>www.dalacoe.edu.ng</strong></li>
            <li>Click on <strong>"Student Login"</strong></li>
            <li>Enter <strong>Username</strong></li>
            <li>Enter <strong>Password/PIN</strong></li>
            <li>Click <strong>Login</strong></li>
        </ol>

        <div class="section-title" style="margin-top: 15px;">SECTION C — UNDERTAKING</div>
        
        <div class="undertaking">
            <p>I hereby acknowledge that I have read, understood, and accepted all the rules and regulations of the College. I undertake to comply fully with all requirements throughout my period of study. I understand that failure to comply may result in disciplinary action, including suspension.</p>
            
            <div class="signature-area">
                <div class="signature-box">
                    <div class="sign-line">Student Signature</div>
                    <div class="sign-title">Date: _______________</div>
                </div>
                <div class="signature-box">
                    <div class="sign-line">Parent's Name</div>
                    <div class="sign-title">Signature: _______________</div>
                </div>
            </div>
            
            <div class="signature-area" style="margin-top: 20px;">
                <div class="signature-box">
                    <div class="sign-line">Parent's Signature</div>
                    <div class="sign-title">Date: _______________</div>
                </div>
                <div class="signature-box">
                    <div class="sign-line">Date</div>
                    <div class="sign-title">_______________________</div>
                </div>
            </div>
        </div>
        
        <div class="page-footer">
            Page <span class="page-number">2</span> of 2 — Dala College of Education, Kano
        </div>
    </div>

    <script>
    function trackAndPrint() {
        fetch('log_download.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'student_id=<?php echo $student_id; ?>&document_type=admission_letter&action_type=print'
        }).finally(function() {
            window.print();
        });
    }
    </script>

</body>
</html>