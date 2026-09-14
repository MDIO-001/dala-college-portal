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
// CHECK IF STUDENT IS APPROVED (Active OR Approved)
// ============================================
$is_approved = ($student['status'] == 'active' || $student['status'] == 'approved');

if (!$is_approved) {
    header('Location: student_dashboard.php?error=You are not admitted yet');
    exit();
}

// Generate Registration Number
$reg_no = $student['reg_no'] ?? $student['student_id'];
if (empty($reg_no)) {
    $reg_no = 'DLCOE/NCE/' . date('y') . 'A' . sprintf('%03d', $student['id']) . '/' . substr(strtoupper($student['course'] ?? 'ENG'), 0, 3) . sprintf('%03d', $student['id']);
}

// Check photo path
$photo_file = '';
if (!empty($student['photo'])) {
    if (file_exists('uploads/students/' . $student['photo'])) {
        $photo_file = 'uploads/students/' . $student['photo'];
    } elseif (file_exists('uploads/' . $student['photo'])) {
        $photo_file = 'uploads/' . $student['photo'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Letter - Dala College</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Georgia', 'Times New Roman', serif;
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            padding: 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .letter-container {
            max-width: 950px;
            width: 100%;
            background: #ffffff;
            padding: 35px 50px 40px 50px;
            border-radius: 16px;
            box-shadow: 0 10px 50px rgba(0, 30, 0, 0.15);
            border: 6px solid #0d2818;
            position: relative;
            overflow: hidden;
        }
        
        /* ============================================
           BACKGROUND PATTERN
           ============================================ */
        .letter-container::after {
            content: 'DALA COLLEGE OF EDUCATION, KANO DALA COLLEGE OF EDUCATION, KANO DALA COLLEGE OF EDUCATION, KANO DALA COLLEGE OF EDUCATION, KANO DALA COLLEGE OF EDUCATION, KANO DALA COLLEGE OF EDUCATION, KANO DALA COLLEGE OF EDUCATION, KANO DALA COLLEGE OF EDUCATION, KANO DALA COLLEGE OF EDUCATION, KANO DALA COLLEGE OF EDUCATION, KANO DALA COLLEGE OF EDUCATION, KANO DALA COLLEGE OF EDUCATION, KANO DALA COLLEGE OF EDUCATION, KANO DALA COLLEGE OF EDUCATION, KANO DALA COLLEGE OF EDUCATION, KANO DALA COLLEGE OF EDUCATION, KANO DALA COLLEGE OF EDUCATION, KANO DALA COLLEGE OF EDUCATION, KANO DALA COLLEGE OF EDUCATION, KANO DALA COLLEGE OF EDUCATION, KANO DALA COLLEGE OF EDUCATION, KANO DALA COLLEGE OF EDUCATION, KANO DALA COLLEGE OF EDUCATION, KANO DALA COLLEGE OF EDUCATION, KANO';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            font-size: 10px;
            color: rgba(13, 40, 24, 0.025);
            letter-spacing: 8px;
            word-spacing: 15px;
            line-height: 3;
            text-align: justify;
            pointer-events: none;
            z-index: 0;
            padding: 20px;
            font-weight: 700;
            white-space: pre-wrap;
            overflow: hidden;
        }
        
        /* WATERMARK LOGO */
        .letter-container::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 60%;
            height: 60%;
            background: url('images/dala-logo.png') no-repeat center center;
            background-size: contain;
            opacity: 0.03;
            pointer-events: none;
            z-index: 0;
        }
        
        /* ============================================
           HEADER
           ============================================ */
        .letter-header {
            text-align: center;
            border-bottom: 3px double #2e7d32;
            padding-bottom: 12px;
            margin-bottom: 12px;
            position: relative;
            z-index: 1;
        }
        .letter-header .header-top {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-bottom: 6px;
        }
        .letter-header .header-top .logo-img {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            border: 4px solid #ffd54f;
            object-fit: contain;
            background: white;
            padding: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            flex-shrink: 0;
        }
        .letter-header .header-top .title-group {
            text-align: left;
        }
        .letter-header .header-top .title-group .college-name {
            font-size: 1.8rem;
            font-weight: 800;
            color: #0d2818;
            letter-spacing: 3px;
            line-height: 1.2;
            text-transform: uppercase;
        }
        .letter-header .header-top .title-group .college-name span {
            color: #2e7d32;
        }
        .letter-header .header-top .title-group .college-address {
            font-size: 0.95rem;
            color: #1a2e1a;
            font-weight: 600;
            letter-spacing: 3px;
            text-align: center;
            background: #f5faf5;
            padding: 2px 15px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 2px;
        }
        .letter-header .accreditation {
            font-size: 0.8rem;
            color: #2e7d32;
            font-weight: 600;
            margin-top: 5px;
            background: #e8f5e9;
            padding: 4px 20px;
            border-radius: 20px;
            display: inline-block;
        }
        .letter-header .contact-info {
            font-size: 0.75rem;
            color: #4a6a4a;
            margin-top: 4px;
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .letter-header .contact-info a {
            color: #2e7d32;
            text-decoration: none;
            font-weight: 500;
        }
        .letter-header .contact-info a:hover {
            text-decoration: underline;
        }
        .letter-header .initiative {
            font-size: 0.8rem;
            color: #0d2818;
            font-weight: 600;
            margin-top: 4px;
            font-style: italic;
            letter-spacing: 1px;
        }
        .letter-header .office {
            font-size: 0.85rem;
            font-weight: 700;
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 3px;
            background: #0d2818;
            color: #ffd54f;
            padding: 4px 25px;
            border-radius: 30px;
            display: inline-block;
        }
        
        /* ============================================
           TITLE
           ============================================ */
        .letter-title {
            text-align: center;
            font-size: 1.3rem;
            font-weight: 700;
            color: #c62828;
            text-transform: uppercase;
            margin: 10px 0 6px 0;
            letter-spacing: 3px;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 10px rgba(198, 40, 40, 0.08);
        }
        .letter-title .sub-title {
            font-size: 0.85rem;
            font-weight: 400;
            color: #0d2818;
            display: block;
            margin-top: 3px;
            text-transform: none;
            letter-spacing: 2px;
            font-style: italic;
        }
        .letter-title .underline {
            width: 40%;
            height: 3px;
            background: linear-gradient(to right, transparent, #c62828, transparent);
            margin: 6px auto 0;
            border-radius: 3px;
        }
        
        /* ============================================
           STUDENT PHOTO
           ============================================ */
        .student-photo-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }
        .student-photo-section .photo-box {
            width: 85px;
            height: 105px;
            border: 4px solid #2e7d32;
            border-radius: 12px;
            overflow: hidden;
            background: #f5faf5;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        }
        .student-photo-section .photo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .student-photo-section .photo-box span {
            font-size: 2.5rem;
            color: #bbb;
        }
        
        /* ============================================
           STUDENT INFO
           ============================================ */
        .student-info {
            margin: 6px 0 12px 0;
            font-size: 1rem;
            line-height: 1.8;
            position: relative;
            z-index: 1;
            background: linear-gradient(135deg, #f8faf8, #f0f5f0);
            padding: 12px 20px;
            border-radius: 12px;
            border-left: 5px solid #2e7d32;
        }
        .student-info .label {
            font-weight: 700;
            color: #0d2818;
            letter-spacing: 1px;
        }
        .student-info .value {
            color: #1a2e1a;
            font-weight: 500;
        }
        
        /* ============================================
           GREETING
           ============================================ */
        .greeting {
            font-size: 1.05rem;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
            padding: 5px 0;
        }
        .greeting strong {
            color: #0d2818;
            font-weight: 700;
        }
        
        /* ============================================
           CONTENT
           ============================================ */
        .content {
            font-size: 0.98rem;
            line-height: 1.8;
            color: #1a2e1a;
            position: relative;
            z-index: 1;
        }
        .content p {
            margin-bottom: 10px;
            text-align: justify;
        }
        .content p strong {
            color: #0d2818;
        }
        
        .content .section-title {
            font-weight: 700;
            color: #c62828;
            font-size: 1.05rem;
            margin-top: 15px;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 2px;
            border-bottom: 2px solid #ffcdd2;
            padding-bottom: 5px;
        }
        
        .conditions {
            margin: 8px 0;
            padding-left: 20px;
            position: relative;
            z-index: 1;
        }
        .conditions li {
            margin-bottom: 5px;
            line-height: 1.7;
            color: #1a2e1a;
            font-size: 0.95rem;
        }
        .conditions li strong {
            color: #0d2818;
            font-weight: 700;
        }
        
        /* ============================================
           PATHWAY
           ============================================ */
        .pathway {
            background: linear-gradient(135deg, #f5faf5, #e8f5e9);
            padding: 18px 25px;
            border-radius: 12px;
            margin: 12px 0;
            border-left: 5px solid #2e7d32;
            text-align: center;
            position: relative;
            z-index: 1;
        }
        .pathway .pathway-title {
            font-weight: 700;
            color: #0d2818;
            font-size: 1rem;
            margin-bottom: 8px;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .pathway .step {
            font-size: 0.92rem;
            padding: 4px 0;
            color: #1a2e1a;
        }
        .pathway .arrow {
            color: #2e7d32;
            font-size: 1.4rem;
            display: block;
            margin: 2px 0;
        }
        .pathway .total {
            font-weight: 700;
            color: #2e7d32;
            font-size: 0.98rem;
            margin-top: 6px;
            background: white;
            padding: 5px 20px;
            border-radius: 30px;
            display: inline-block;
            box-shadow: 0 2px 10px rgba(46, 125, 50, 0.08);
        }
        .pathway .note {
            font-size: 0.82rem;
            color: #4a6a4a;
            margin-top: 6px;
            font-style: italic;
        }
        
        /* ============================================
           FOOTER
           ============================================ */
        .letter-footer {
            margin-top: 18px;
            padding-top: 15px;
            border-top: 2px dashed #dce8dc;
            position: relative;
            z-index: 1;
        }
        .letter-footer .signature {
            margin-top: 20px;
        }
        .letter-footer .signature .line {
            width: 220px;
            border-bottom: 2px solid #0d2818;
            margin: 25px 0 5px 0;
        }
        .letter-footer .signature .name {
            font-weight: 700;
            color: #0d2818;
            font-size: 1rem;
            letter-spacing: 1px;
        }
        .letter-footer .signature .title {
            color: #1a2e1a;
            font-size: 0.9rem;
            letter-spacing: 2px;
            font-weight: 600;
        }
        
        /* ============================================
           ACTION BUTTONS
           ============================================ */
        .action-buttons {
            text-align: center;
            margin-bottom: 15px;
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
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
            background: transparent;
            letter-spacing: 1px;
        }
        .btn-back:hover {
            background: #2e7d32;
            color: white;
            transform: translateY(-3px);
        }
        
        .topbar {
            background: #0d2818;
            padding: 12px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            border-radius: 12px;
            margin-bottom: 18px;
            position: relative;
            z-index: 1;
        }
        .topbar .logo-title { color: #ffd54f; font-size: 1.2rem; font-weight: 800; letter-spacing: 2px; }
        .topbar .logo-title span { color: #a5d6a7; }
        .topbar .logo-sub { display: block; font-size: 0.55rem; color: #c8e6c9; letter-spacing: 2px; }
        .topbar nav a { color: #c8e6c9; text-decoration: none; padding: 6px 18px; border-radius: 30px; font-size: 0.85rem; font-weight: 500; transition: all 0.3s ease; }
        .topbar nav a:hover { background: #2e7d32; color: white; }
        .topbar nav .active { background: #ffd54f; color: #0d2818 !important; font-weight: 700; }

        @media print {
            body { background: white; padding: 0; margin: 0; }
            .letter-container { 
                box-shadow: none; 
                padding: 25px 35px; 
                border-radius: 0;
                border: 6px solid #0d2818;
                max-width: 100%;
            }
            .action-buttons, .no-print, .topbar { display: none !important; }
            .letter-container::after { opacity: 0.2; }
            .letter-container::before { opacity: 0.03; }
        }
        
        @media (max-width: 768px) {
            .letter-container { padding: 20px 20px; border-width: 4px; }
            .letter-header .header-top { flex-direction: column; text-align: center; }
            .letter-header .header-top .title-group { text-align: center; }
            .letter-header .header-top .logo-img { width: 100px; height: 100px; }
            .letter-header .college-name { font-size: 1.3rem; }
            .letter-title { font-size: 1.1rem; }
            .content { font-size: 0.9rem; }
            .topbar { flex-direction: column; gap: 8px; }
            .student-photo-section { justify-content: center; }
        }
    </style>
</head>
<body>

    <!-- TOP BAR -->
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

    <!-- ADMISSION LETTER -->
    <div class="letter-container" id="admissionLetter">
        
        <!-- ACTION BUTTONS -->
        <div class="action-buttons no-print">
            <button onclick="window.print()" class="btn-print">🖨️ Print / Download PDF</button>
            <a href="student_dashboard.php" class="btn-back">← Back to Dashboard</a>
        </div>
        
        <!-- HEADER -->
        <div class="letter-header">
            <div class="header-top">
                <img src="images/dala-logo.png" alt="Dala College Logo" class="logo-img" onerror="this.style.display='none'">
                <div class="title-group">
                    <div class="college-name">DALA <span>COLLEGE OF EDUCATION, KANO</span></div>
                    <div class="college-address">KANO STATE - NIGERIA</div>
                </div>
            </div>
            
            <div class="accreditation">✅ Accredited by National Commission for Colleges of Education (NCCE), Abuja</div>
            
            <div class="contact-info">
                <span>✉️ <a href="mailto:dalacoekano@gmail.com">dalacoekano@gmail.com</a></span>
                <span>🌐 <a href="https://dalacollege.edu.ng" target="_blank">www.dalacollege.edu.ng</a></span>
            </div>
            
            <div class="initiative">Under the Federal Government Dual Mandate Initiative</div>
            <div class="office">OFFICE OF THE REGISTRAR</div>
        </div>

        <!-- TITLE -->
        <div class="letter-title">
            PROVISIONAL OFFER OF ADMISSION
            <span class="sub-title">UNDER THE DUAL MANDATE (NCE &amp; DEGREE) INITIATIVE</span>
            <div class="underline"></div>
        </div>

        <!-- STUDENT PHOTO -->
        <div class="student-photo-section">
            <div class="photo-box">
                <?php if (!empty($photo_file) && file_exists($photo_file)): ?>
                    <img src="<?php echo $photo_file; ?>" alt="Student Photo">
                <?php else: ?>
                    <span>📷</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- STUDENT INFO -->
        <div class="student-info">
            <div><span class="label">Name:</span> <span class="value"><?php echo strtoupper($student['fullname']); ?></span></div>
            <div><span class="label">Reg. No.:</span> <span class="value"><?php echo $reg_no; ?></span></div>
            <div><span class="label">Department:</span> <span class="value"><?php echo strtoupper($student['course'] ?? $student['combination'] ?? 'N/A'); ?></span></div>
            <div><span class="label">Study Centre:</span> <span class="value"><?php echo $student['branch_code'] ?? 'SHINGE'; ?></span></div>
        </div>

        <!-- GREETING -->
        <div class="greeting">
            <strong>Dear <?php echo $student['fullname']; ?>,</strong>
        </div>

        <!-- CONTENT -->
        <div class="content">
            <p>
                I am pleased to inform you that you have been offered <strong>Provisional Admission</strong> into 
                <strong>Dala College of Education, Kano</strong>, for the <strong>2025/2026 Academic Session</strong> 
                to undergo the approved three (3)-year NCE Programme followed by a two (2)-year Degree Programme 
                under the Federal Government <strong>Dual Mandate (NCE &amp; Degree) Initiative</strong>.
            </p>

            <div class="section-title">📌 CONDITIONS OF ADMISSION</div>
            <p>This offer of admission is subject to the following conditions:</p>
            <ol class="conditions">
                <li>The credentials submitted by you must be correct and genuine, and original copies must be presented during registration.</li>
                <li>All admissions shall be in accordance with the requirements and standards of the <strong>National Commission for Colleges of Education (NCCE)</strong>.</li>
                <li>You must pay all required fees before registration.</li>
                <li>You must abide by all rules and regulations of the College.</li>
                <li>Any involvement in misconduct, examination malpractice, or activities capable of disrupting the peace and order of the College may lead to disciplinary action, including dismissal.</li>
                <li>You must comply with the approved dress code of the College.</li>
                <li>The Sponsorship Form must be duly completed and submitted where applicable.</li>
                <li>All students shall comply with any new or amended academic, financial, administrative, or other regulations.</li>
                <li>All fees and other payments made by a student to the College shall be <strong>non-refundable</strong>.</li>
                <li>A medical fitness certificate is required.</li>
                <li>Students must regularly check the official communication channels of the College.</li>
            </ol>

            <!-- DUAL MANDATE PATHWAY -->
            <div class="pathway">
                <div class="pathway-title">🎓 DUAL MANDATE (NCE &amp; DEGREE) PATHWAY</div>
                <div class="step">You are admitted into the <strong>three (3)-year NCE Programme</strong> as the first stage of the Federal Government Dual Mandate (NCE &amp; Degree) Initiative, followed by the <strong>two (2)-year Degree Programme</strong> upon successful completion of the NCE.</div>
                <div style="margin: 8px 0;">
                    <div class="step">📘 THREE (3)-YEAR NCE PROGRAMME</div>
                    <div class="arrow">⬇️</div>
                    <div class="step">✅ SUCCESSFUL COMPLETION OF NCE</div>
                    <div class="arrow">⬇️</div>
                    <div class="step">🎓 TWO (2)-YEAR DEGREE PROGRAMME</div>
                </div>
                <div class="total">⏳ TOTAL DUAL MANDATE PATHWAY: FIVE (5) YEARS</div>
                <div class="note">
                    The Degree stage shall be subject to the applicable academic requirements, admission procedures, 
                    institutional regulations, and other requirements governing the Dual Mandate Initiative.
                </div>
            </div>

            <p style="margin-top: 10px;">
                We congratulate you on your admission and welcome you to <strong>Dala College of Education, Kano</strong>. 
                We wish you a successful and rewarding academic journey.
            </p>
        </div>

        <!-- FOOTER -->
        <div class="letter-footer">
            <p style="font-size: 0.95rem; margin-bottom: 3px;">Yours faithfully,</p>
            <div class="signature">
                <div class="line"></div>
                <div class="name">DR. AUWAL GAMBO PALI</div>
                <div class="title">REGISTRAR</div>
            </div>
        </div>
    </div>

</body>
</html>