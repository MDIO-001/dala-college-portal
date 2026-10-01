<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['user_id'];

$query = "SELECT * FROM students WHERE id = '$student_id'";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Database error: " . mysqli_error($conn));
}

$student = mysqli_fetch_assoc($result);

if (!$student) {
    die("Student not found in database.");
}

$is_approved = ($student['status'] == 'active' || $student['status'] == 'approved');
if (!$is_approved) {
    header('Location: student_dashboard.php?error=You are not admitted yet');
    exit();
}

$programme = strtoupper($student['programme'] ?? 'NCE');
$is_degree = (strpos($programme, 'DEGREE') !== false || strpos($programme, 'DEG') !== false);

if (!$is_degree) {
    header('Location: admission_letter.php');
    exit();
}

$reg_no = $student['reg_no'] ?? $student['student_id'];
if (empty($reg_no)) {
    $reg_no = 'DLCOE/DEG/' . date('y') . 'A' . sprintf('%03d', $student['id']) . '/' . substr(strtoupper($student['course'] ?? 'ENG'), 0, 3) . sprintf('%03d', $student['id']);
}

$application_no = $student['application_no'] ?? ('002/' . str_pad($student['id'], 6, '0', STR_PAD_LEFT));

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

$academic_session = '2026/2027';
$combination = strtoupper($student['course'] ?? $student['combination'] ?? 'N/A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Degree Admission Letter - Dala College</title>
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
           PAGE SETUP
           ============================================ */
        .page {
            max-width: 900px;
            width: 100%;
            background: #ffffff;
            padding: 25px 40px;
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
            border-bottom: 3px double #0d2818;
            padding-bottom: 12px;
            margin-bottom: 15px;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .letter-header .header-top {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 25px;
            margin-bottom: 8px;
        }
        .letter-header .header-top .logo-img {
            width: 150px;
            height: 150px;
            object-fit: contain;
            flex-shrink: 0;
            filter: drop-shadow(0 4px 10px rgba(0,0,0,0.15));
        }
        .letter-header .header-top .title-group {
            text-align: center;
        }
        .letter-header .header-top .title-group .college-name {
            font-size: 2rem;
            font-weight: 900;
            color: #0d2818;
            letter-spacing: 3px;
            line-height: 1.15;
            text-transform: uppercase;
            text-shadow: 0 1px 3px rgba(13, 40, 24, 0.15);
        }
        .letter-header .header-top .title-group .college-name span {
            color: #2e7d32;
        }
        .letter-header .header-top .title-group .college-sub {
            font-size: 1.05rem;
            color: #1a2e1a;
            font-weight: 700;
            letter-spacing: 3px;
            margin-top: 5px;
        }
        .letter-header .accreditation {
            font-size: 0.92rem;
            color: #2e7d32;
            font-weight: 700;
            margin-top: 6px;
            letter-spacing: 0.5px;
        }
        .letter-header .contact-info {
            font-size: 0.9rem;
            color: #4a6a4a;
            margin-top: 5px;
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
            font-weight: 600;
        }
        .letter-header .contact-info a {
            color: #2e7d32;
            text-decoration: none;
            font-weight: 700;
        }
        .letter-header .office {
            font-size: 1rem;
            font-weight: 800;
            margin-top: 10px;
            text-transform: uppercase;
            letter-spacing: 4px;
            background: #0d2818;
            color: #ffd54f;
            padding: 6px 32px;
            border-radius: 22px;
            display: inline-block;
        }
        
        .letter-title {
            text-align: center;
            font-size: 1.45rem;
            font-weight: 800;
            color: #c62828;
            text-transform: uppercase;
            margin: 14px 0 10px 0;
            letter-spacing: 2.5px;
        }
        .letter-title .sub-title {
            font-size: 0.98rem;
            font-weight: 500;
            color: #0d2818;
            display: block;
            margin-top: 4px;
            text-transform: none;
            letter-spacing: 1px;
            font-style: italic;
        }
        .letter-title .session-badge {
            display: inline-block;
            background: #fff9c4;
            color: #0d2818;
            padding: 5px 22px;
            border-radius: 14px;
            font-weight: 800;
            font-size: 1rem;
            border: 2px solid #f9a825;
            margin-top: 8px;
        }
        
        .student-wrapper {
            display: grid;
            grid-template-columns: 1fr 125px;
            gap: 20px;
            background: #f8faf8;
            padding: 15px 22px;
            border-radius: 8px;
            border-left: 5px solid #2e7d32;
            margin-bottom: 15px;
            align-items: start;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .student-info {
            font-size: 1.08rem;
            line-height: 1.9;
        }
        .student-info .label {
            font-weight: 700;
            color: #0d2818;
            display: inline-block;
            min-width: 150px;
        }
        .student-info .value {
            color: #1a2e1a;
            font-weight: 500;
        }
        .student-photo {
            width: 125px;
            height: 150px;
            border: 3px solid #2e7d32;
            border-radius: 8px;
            overflow: hidden;
            background: #f5faf5;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .student-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .student-photo span {
            font-size: 2.8rem;
            color: #bbb;
        }
        
        .content {
            font-size: 1.05rem;
            line-height: 1.85;
            color: #1a2e1a;
        }
        .content p {
            margin-bottom: 10px;
            text-align: justify;
        }
        .content p strong { color: #0d2818; }
        
        .section-title {
            font-weight: 800;
            color: #c62828;
            font-size: 1.1rem;
            margin-top: 15px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 2px;
            border-bottom: 2px solid #ffcdd2;
            padding-bottom: 5px;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        
        .conditions {
            margin: 8px 0;
            padding-left: 26px;
        }
        .conditions li {
            margin-bottom: 6px;
            line-height: 1.7;
            color: #1a2e1a;
            font-size: 1rem;
            text-align: justify;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .conditions li strong { color: #0d2818; font-weight: 700; }
        
        .fees-table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
            font-size: 1.02rem;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .fees-table th {
            background: #0d2818;
            color: white;
            padding: 10px 14px;
            text-align: left;
            font-weight: 700;
            border: 1px solid #0d2818;
        }
        .fees-table td {
            padding: 10px 14px;
            border: 1px solid #ccc;
        }
        .fees-table tr:nth-child(even) td {
            background: #f8faf8;
        }
        .fees-table .total-row td {
            background: #e8f5e9;
            font-weight: 800;
            color: #0d2818;
            font-size: 1.08rem;
        }
        .fees-table .amount-col {
            text-align: right;
            font-weight: 700;
        }
        
        .notes-list {
            margin: 8px 0;
            padding-left: 26px;
        }
        .notes-list li {
            margin-bottom: 6px;
            line-height: 1.7;
            font-size: 0.98rem;
            text-align: justify;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        
        .portal-steps {
            margin: 8px 0;
            padding-left: 26px;
            list-style: decimal;
        }
        .portal-steps li {
            margin-bottom: 6px;
            line-height: 1.7;
            font-size: 1rem;
            text-align: justify;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        
        .undertaking {
            margin-top: 15px;
        }
        .undertaking p {
            font-size: 1rem;
            line-height: 1.85;
            margin-bottom: 12px;
            text-align: justify;
        }
        .signature-area {
            margin-top: 25px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 35px;
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
            font-size: 0.95rem;
            color: #0d2818;
        }
        .signature-box .sign-title {
            font-size: 0.88rem;
            color: #4a6a4a;
            font-style: italic;
        }
        
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
            width: 220px;
            border-bottom: 2px solid #0d2818;
            margin: 22px 0 5px 0;
        }
        .letter-footer .signature .name {
            font-weight: 800;
            color: #0d2818;
            font-size: 1.08rem;
            letter-spacing: 1px;
        }
        .letter-footer .signature .title {
            color: #1a2e1a;
            font-size: 0.98rem;
            letter-spacing: 2px;
            font-weight: 700;
        }
        
        .page-footer {
            text-align: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #dce8dc;
            font-size: 0.85rem;
            color: #6a8f6a;
            font-style: italic;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .page-number {
            font-weight: 800;
            color: #0d2818;
        }
        
        /* ============================================
           PRINT - A4 2 PAGES
           ============================================ */
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            html, body {
                width: 210mm !important;
                height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
                font-size: 11pt !important;
                line-height: 1.5 !important;
                display: block !important;
                overflow: visible !important;
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
                padding: 8mm 10mm !important;
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
            
            .page::before { opacity: 0.045 !important; }
            .page::after { opacity: 0.08 !important; }
            
            /* HEADER A PRINT */
            .letter-header { 
                padding-bottom: 8px !important; 
                margin-bottom: 10px !important;
                border-bottom: 3px double #0d2818 !important;
            }
            .letter-header .header-top { gap: 15px !important; }
            .letter-header .header-top .title-group .college-name { 
                font-size: 1.3rem !important; 
                letter-spacing: 1.5px !important; 
                line-height: 1.1 !important;
            }
            .letter-header .header-top .title-group .college-sub { 
                font-size: 0.78rem !important; 
                letter-spacing: 2px !important;
            }
            .letter-header .header-top .logo-img { 
                width: 95px !important; 
                height: 95px !important; 
            }
            .letter-header .accreditation { 
                font-size: 0.68rem !important; 
                margin-top: 3px !important;
            }
            .letter-header .contact-info { 
                font-size: 0.65rem !important; 
                gap: 12px !important; 
                margin-top: 3px !important;
            }
            .letter-header .office { 
                font-size: 0.72rem !important; 
                padding: 3px 18px !important; 
                letter-spacing: 3px !important;
                margin-top: 6px !important;
            }
            
            /* TITLE A PRINT */
            .letter-title { 
                font-size: 1.05rem !important; 
                margin: 7px 0 5px 0 !important; 
                letter-spacing: 1.5px !important; 
            }
            .letter-title .sub-title { 
                font-size: 0.72rem !important; 
                margin-top: 2px !important;
            }
            .letter-title .session-badge { 
                font-size: 0.75rem !important; 
                padding: 3px 14px !important; 
                margin-top: 4px !important;
            }
            
            /* STUDENT INFO A PRINT */
            .student-wrapper { 
                padding: 8px 12px !important; 
                margin-bottom: 8px !important; 
                gap: 12px !important;
                grid-template-columns: 1fr 85px !important;
            }
            .student-info { 
                font-size: 0.8rem !important; 
                line-height: 1.55 !important; 
            }
            .student-info .label { min-width: 105px !important; }
            .student-photo { 
                width: 85px !important; 
                height: 105px !important; 
                border-width: 2px !important; 
            }
            
            /* CONTENT A PRINT */
            .content { 
                font-size: 0.8rem !important; 
                line-height: 1.5 !important; 
            }
            .content p { margin-bottom: 5px !important; }
            
            .section-title { 
                font-size: 0.82rem !important; 
                margin-top: 8px !important; 
                margin-bottom: 4px !important; 
                letter-spacing: 1.5px !important; 
            }
            
            .conditions li,
            .notes-list li,
            .portal-steps li {
                font-size: 0.75rem !important;
                line-height: 1.45 !important;
                margin-bottom: 3px !important;
            }
            
            /* FEES TABLE A PRINT */
            .fees-table { 
                font-size: 0.78rem !important; 
                margin: 6px 0 !important; 
            }
            .fees-table th, .fees-table td { 
                padding: 5px 8px !important; 
            }
            .fees-table .total-row td { 
                font-size: 0.82rem !important; 
            }
            
            /* UNDERTAKING A PRINT */
            .undertaking { margin-top: 8px !important; }
            .undertaking p { 
                font-size: 0.78rem !important; 
                line-height: 1.5 !important; 
                margin-bottom: 6px !important; 
            }
            
            .signature-area { 
                margin-top: 15px !important; 
                gap: 20px !important; 
            }
            .signature-box .sign-line { 
                margin-top: 20px !important; 
                font-size: 0.75rem !important; 
                padding-top: 4px !important; 
            }
            .signature-box .sign-title { font-size: 0.68rem !important; }
            
            /* LETTER FOOTER A PRINT */
            .letter-footer { 
                margin-top: 8px !important; 
                padding-top: 6px !important; 
            }
            .letter-footer .signature .line { 
                margin: 12px 0 3px 0 !important; 
                width: 150px !important; 
            }
            .letter-footer .signature .name { font-size: 0.82rem !important; }
            .letter-footer .signature .title { font-size: 0.75rem !important; }
            .letter-footer .signature img { 
                width: 110px !important; 
                margin: 4px 0 2px 0 !important; 
            }
            
            .page-footer { 
                font-size: 0.65rem !important; 
                margin-top: 8px !important; 
                padding-top: 4px !important; 
            }
            
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
            .letter-header .header-top { flex-direction: column; gap: 10px; }
            .letter-header .header-top .logo-img { width: 110px; height: 110px; }
            .letter-header .header-top .title-group .college-name { font-size: 1.3rem; }
            .student-wrapper { grid-template-columns: 1fr; }
            .student-photo { margin: 0 auto; }
            .signature-area { grid-template-columns: 1fr; }
            .topbar { flex-direction: column; gap: 8px; }
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
            <a href="student_dashboard.php">Dashboard</a>
            <a href="admission_letter_degree.php" class="active">Degree Admission Letter</a>
        </nav>
    </div>

    <div class="action-buttons no-print">
        <button onclick="trackAndPrint()" class="btn-print">🖨️ Print / Download PDF</button>
        <a href="student_dashboard.php" class="btn-back">← Back to Dashboard</a>
    </div>

    <!-- ============================================ -->
    <!-- PAGE 1: ADMISSION LETTER + CONDITIONS + REGISTRAR SIGNATURE -->
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
            <span class="sub-title">UNDER THE DUAL MANDATE (DEGREE) PROGRAMME</span>
            <div class="session-badge"><?php echo $academic_session; ?> ACADEMIC SESSION</div>
        </div>

        <div class="student-wrapper">
            <div class="student-info">
                <div><span class="label">Name:</span> <span class="value"><?php echo strtoupper($student['fullname']); ?></span></div>
                <div><span class="label">Admission No.:</span> <span class="value"><?php echo $reg_no; ?></span></div>
                <div><span class="label">Combination:</span> <span class="value"><?php echo $combination; ?></span></div>
                <div><span class="label">Programme:</span> <span class="value">DEGREE PROGRAMME</span></div>
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
                for the <strong><?php echo $academic_session; ?> Academic Session</strong> to pursue a <strong>Degree Programme</strong> 
                under the College's Dual Mandate.
            </p>
            
            <p>
                This admission is for the <strong>Degree component</strong> of the College's Dual Mandate Programme 
                and is offered to candidates who have successfully completed the <strong>National Certificate in Education (NCE)</strong>. 
                The Degree Programme has a duration of <strong>two (2) years only</strong>, subject to the applicable academic and regulatory requirements.
            </p>

            <div class="section-title">CONDITIONS OF ADMISSION</div>
            <ol class="conditions" type="a">
                <li>The credentials submitted by you are correct, and you shall present the original copies during registration.</li>
                <li>You must provide <strong>evidence of successful completion of the National Certificate in Education (NCE)</strong> and meet all requirements prescribed for admission into the Degree Programme.</li>
                <li>All admissions are subject to the applicable academic and regulatory requirements of the Degree Programme; any deficiency must be corrected before graduation.</li>
                <li>You will pay all required fees at the appropriate time without delay.</li>
                <li>You must abide by all rules and regulations of the College.</li>
                <li>You must not engage in any act of riot or unrest; violation will lead to dismissal.</li>
                <li>You must comply with the College dress code.</li>
                <li>You are required to complete and return the sponsorship form.</li>
                <li>You must regularize your Degree admission with <strong>JAMB</strong>, where applicable, and obtain the required JAMB registration number.</li>
                <li>You must submit a <strong>medical fitness certificate</strong> from a recognized hospital.</li>
                <li>You must regularly check the College portal/social media for updates.</li>
                <li>The College reserves the right to introduce new changes, whether academic, financial, administrative, or otherwise, whenever the need arises.</li>
                <li>You must comply with all directives, instructions, procedures, and official decisions issued by the College Authority from time to time.</li>
                <li>The College Authority reserves the right to suspend or dismiss any student who violates any of these rules and regulations.</li>
            </ol>

            <p style="margin-top: 10px;">Accept my congratulations.</p>
        </div>

        <!-- REGISTRAR SIGNATURE - KARSHE NA PAGE 1 -->
        <div class="letter-footer">
            <p style="font-size: 1rem;">Sincerely,</p>
            <div class="signature">
                <img src="images/registrar-signature.png" 
                     alt="Registrar Signature" 
                     style="width: 170px; height: auto; margin: 8px 0 4px 0; display: block;"
                     onerror="this.style.display='none'">
                <div class="line"></div>
                <div class="name">HAJIYA LUBABATU ALIYU YOLA</div>
                <div class="title">REGISTRAR</div>
            </div>
        </div>
        
        <div class="page-footer">
            Page <span class="page-number">1</span> of 2 — Degree Admission Letter
        </div>
    </div>

    <!-- ============================================ -->
    <!-- PAGE 2: FEES + PORTAL ACCESS + UNDERTAKING -->
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
            <div class="office" style="margin-top: 6px;"><?php echo $academic_session; ?> ACADEMIC SESSION</div>
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
                <tr>
                    <td>1.</td>
                    <td>Tuition</td>
                    <td class="amount-col">60,000.00</td>
                </tr>
                <tr class="total-row">
                    <td colspan="2" style="text-align: right;">TOTAL</td>
                    <td class="amount-col">60,000.00</td>
                </tr>
            </tbody>
        </table>

        <div class="section-title" style="margin-top: 12px;">NOTE</div>
        <ul class="notes-list">
            <li>The above fees exclude other charges such as Acceptance Fee, Faculty, Departmental, Orientation, Matriculation, Convocation, Students' Union, ID Card, Library, Handbook, JAMB, Administrative Charges, Maintenance, etc.</li>
            <li>All payments must be made through official College channels.</li>
            <li>All fees are <strong>non-refundable</strong>.</li>
            <li>Failure to register within <strong>two (2) weeks</strong> may lead to loss of admission or penalties.</li>
        </ul>

        <div class="section-title" style="margin-top: 12px;">SECTION B — STUDENT PORTAL ACCESS</div>
        <p style="font-size: 1rem; margin-bottom: 6px;">
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
            Page <span class="page-number">2</span> of 2 — Degree Admission Letter
        </div>
    </div>

    <script>
    function trackAndPrint() {
        fetch('log_download.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'student_id=<?php echo $student_id; ?>&document_type=degree_admission_letter&action_type=print'
        }).finally(function() {
            window.print();
        });
    }
    </script>

</body>
</html>