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
// GET OR CREATE ID CARD
// ============================================
$session = '2024/2025';

$card_query = "SELECT * FROM id_cards WHERE student_id = $student_id AND session = '$session' LIMIT 1";
$card_result = mysqli_query($conn, $card_query);
$card = mysqli_fetch_assoc($card_result);

if (!$card) {
    $card_number = 'DLC/ID/' . date('y') . '/' . str_pad($student_id, 4, '0', STR_PAD_LEFT);
    $issue_date = date('Y-m-d');
    $expiry_date = date('Y-m-d', strtotime('+3 years'));
    
    $insert = "INSERT INTO id_cards (student_id, card_number, issue_date, expiry_date, session) 
               VALUES ($student_id, '$card_number', '$issue_date', '$expiry_date', '$session')";
    mysqli_query($conn, $insert);
    
    $card_query = "SELECT * FROM id_cards WHERE student_id = $student_id AND session = '$session' LIMIT 1";
    $card_result = mysqli_query($conn, $card_query);
    $card = mysqli_fetch_assoc($card_result);
}

// ============================================
// STUDENT PHOTO
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
// QR CODE DATA
// ============================================
$qr_data = urlencode(
    "DALA COLLEGE OF EDUCATION, KANO\n" .
    "Name: " . $student['fullname'] . "\n" .
    "Reg No: " . $student['reg_no'] . "\n" .
    "Combination: " . $student_combination . "\n" .
    "Level: " . ($student['level'] ?? 'NCE I') . "\n" .
    "Programme: " . ($student['programme'] ?? 'NCE') . "\n" .
    "Card No: " . ($card['card_number'] ?? 'N/A') . "\n" .
    "Session: " . $session . "\n" .
    "Verify: www.dalacollege.edu.ng/verify"
);
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . $qr_data;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student ID Card - Dala College</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #eef2ee;
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            color: #0d2818;
        }

        /* ============ ACTIONS ============ */
        .actions {
            margin-bottom: 25px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-print { background: #c9a227; color: #0d2818; }
        .btn-print:hover { background: #a8871c; transform: translateY(-2px); }
        .btn-back { background: #2e7d32; color: white; }
        .btn-back:hover { background: #1b5e20; transform: translateY(-2px); }

        /* ============ WRAPPER ============ */
        .id-card-wrapper {
            display: flex;
            flex-direction: column;
            gap: 30px;
            align-items: center;
        }

        /* ============ CARD BASE ============ */
        .id-card {
            width: 380px;
            height: 600px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.18);
            position: relative;
            overflow: hidden;
            border: 2px solid #c9a227;
        }

        /* ============================================
           LOGO WATERMARK
           ============================================ */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 280px;
            height: 280px;
            opacity: 0.06;
            pointer-events: none;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .watermark img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: grayscale(100%);
        }

        /* ============================================
           FRONT HEADER
           ============================================ */
        .card-header {
            background: linear-gradient(135deg, #0d2818 0%, #1b5e20 60%, #2e7d32 100%);
            padding: 12px 14px 8px;
            border-bottom: 3px solid #c9a227;
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .header-top {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 6px;
        }

        .card-header .logo {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
            border: 2px solid #c9a227;
        }
        .card-header .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .college-info { text-align: center; flex: 1; }
        .college-name {
            font-size: 11.5px;
            font-weight: 900;
            color: #ffffff;
            text-transform: uppercase;
            line-height: 1.2;
            letter-spacing: 0.4px;
        }
        .college-state {
            font-size: 9.5px;
            color: #ffffff;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 2px;
        }
        .college-accredited {
            font-size: 7.5px;
            color: #c9a227;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: 3px;
        }
        .college-office {
            font-size: 7.5px;
            color: #c8e6c9;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            margin-top: 3px;
            padding-top: 3px;
            border-top: 1px solid rgba(201,162,39,0.35);
        }

        /* Contact strip a header */
        .header-contact {
            margin-top: 6px;
            padding-top: 5px;
            border-top: 1px solid rgba(201,162,39,0.35);
            display: flex;
            justify-content: center;
            gap: 14px;
            font-size: 7.5px;
            color: #c8e6c9;
            letter-spacing: 0.3px;
        }
        .header-contact span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .header-contact i {
            color: #c9a227;
            font-size: 7px;
        }

        .red-bar {
            height: 4px;
            background: #c62828;
            width: 100%;
        }

        /* ============ FRONT BODY ============ */
        .card-body {
            padding: 16px 22px 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        .student-photo {
            width: 125px;
            height: 145px;
            border: 3px solid #c9a227;
            border-radius: 10px;
            overflow: hidden;
            background: #f0f0f0;
            box-shadow: 0 6px 15px rgba(0,0,0,0.15);
            margin-bottom: 10px;
        }
        .student-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .student-name {
            text-align: center;
            margin: 4px 0 10px;
        }
        .student-name h2 {
            font-size: 15px;
            font-weight: 900;
            color: #0d2818;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            line-height: 1.2;
        }
        .student-name .role {
            font-size: 9px;
            color: #c62828;
            text-transform: uppercase;
            letter-spacing: 3px;
            font-weight: 800;
            margin-top: 4px;
        }

        /* ============================================
           DETAILS — LEFT LABEL / RIGHT VALUE
           ============================================ */
        .student-details {
            width: 100%;
            padding: 6px 0 5px;
            display: flex;
            flex-direction: column;
            gap: 0;
            border-top: 1px dashed #c9a227;
            border-bottom: 1px dashed #c9a227;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            padding: 7px 0;
            border-bottom: 1px dashed #e5e5e5;
        }
        .detail-row:last-child { border-bottom: none; }

        .detail-row .label {
            color: #6b7a6b;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 8.5px;
            letter-spacing: 1px;
            text-align: left;
            flex-shrink: 0;
        }
        .detail-row .value {
            color: #0d2818;
            font-weight: 800;
            font-size: 10.5px;
            letter-spacing: 0.3px;
            text-align: right;
            word-break: break-word;
            padding-left: 10px;
        }

        /* ============ FRONT FOOTER ============ */
        .card-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: #ffffff;
            border-top: 2px solid #c9a227;
            padding: 8px 16px;
            z-index: 2;
        }
        .footer-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 8.5px;
        }
        .card-footer .card-number {
            font-weight: 800;
            color: #c9a227;
            letter-spacing: 0.5px;
        }
        .card-footer .validity {
            text-align: right;
            color: #6b7a6b;
            font-weight: 600;
            line-height: 1.3;
        }
        .card-footer .validity strong { color: #0d2818; }

        .footer-signature {
            margin-top: 6px;
            padding-top: 5px;
            border-top: 1px dashed #c9a227;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 8px;
        }
        .footer-signature .sig-label {
            color: #6b7a6b;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .footer-signature .sig-name {
            color: #c62828;
            font-weight: 800;
            font-size: 8.5px;
        }

        /* ============================================
           BACK SIDE
           ============================================ */
        .id-card.back { background: #ffffff; }

        .back-header {
            background: linear-gradient(135deg, #0d2818 0%, #1b5e20 60%, #2e7d32 100%);
            padding: 12px 14px 8px;
            border-bottom: 3px solid #c9a227;
            position: relative;
            z-index: 2;
            text-align: center;
        }
        .back-header .header-top {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 5px;
        }
        .back-header .logo {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
            border: 2px solid #c9a227;
        }
        .back-header .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .back-header .college-info { text-align: center; flex: 1; }
        .back-header .college-name {
            font-size: 11px;
            font-weight: 900;
            color: #ffffff;
            text-transform: uppercase;
            line-height: 1.2;
            letter-spacing: 0.4px;
        }
        .back-header .college-state {
            font-size: 9px;
            color: #ffffff;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 2px;
        }
        .back-header .college-accredited {
            font-size: 7.5px;
            color: #c9a227;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: 3px;
        }
        .back-header .college-office {
            font-size: 7.5px;
            color: #c8e6c9;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            margin-top: 3px;
            padding-top: 3px;
            border-top: 1px solid rgba(201,162,39,0.35);
        }

        .back-red-bar {
            height: 4px;
            background: #c62828;
            width: 100%;
        }

        .back-body {
            padding: 14px 18px 100px;
            position: relative;
            z-index: 2;
        }

        .qr-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 2px dashed #c9a227;
        }
        .qr-box {
            width: 130px;
            height: 130px;
            border: 3px solid #c9a227;
            border-radius: 10px;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 5px;
            box-shadow: 0 6px 15px rgba(201,162,39,0.25);
            position: relative;
        }
        .qr-box::before {
            content: '';
            position: absolute;
            top: -3px; left: -3px; right: -3px; bottom: -3px;
            border-radius: 10px;
            border: 1px solid #c62828;
            pointer-events: none;
        }
        .qr-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .qr-label {
            margin-top: 8px;
            font-size: 8.5px;
            color: #0d2818;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }
        .qr-label i { color: #c62828; margin-right: 4px; }

        .back-title {
            text-align: center;
            font-size: 10.5px;
            font-weight: 900;
            color: #c9a227;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 2px solid #c62828;
        }

        .back-text {
            font-size: 9.5px;
            line-height: 1.55;
            color: #0d2818;
            margin-bottom: 10px;
            text-align: center;
        }

        .back-rules {
            list-style: none;
            margin-bottom: 10px;
        }
        .back-rules li {
            font-size: 9px;
            color: #0d2818;
            padding: 4px 0 4px 16px;
            position: relative;
            line-height: 1.5;
            text-align: left;
        }
        .back-rules li:before {
            content: "\f00c";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            left: 0;
            color: #c9a227;
            font-size: 7px;
            top: 6px;
        }

        .back-contact {
            background: linear-gradient(135deg, #f8faf8, #ffffff);
            border-left: 3px solid #c62828;
            padding: 8px 10px;
            font-size: 9px;
            color: #0d2818;
            line-height: 1.5;
            margin-bottom: 10px;
            text-align: center;
            border-radius: 0 6px 6px 0;
        }
        .back-contact strong { color: #2e7d32; }

        .back-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: #0d2818;
            color: #ffffff;
            padding: 10px 16px;
            text-align: center;
            border-top: 3px solid #c9a227;
        }
        .back-footer .signature {
            font-size: 8.5px;
            color: #c9a227;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .back-footer .sig-phone {
            font-size: 9px;
            color: #ffffff;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .back-footer .motto {
            font-size: 8px;
            color: #a5d6a7;
            font-style: italic;
        }

        /* ============ PRINT ============ */
        @media print {
            body { background: white; padding: 0; display: block; }
            .actions { display: none !important; }
            .id-card-wrapper { gap: 15px; align-items: center; }
            .id-card { 
                box-shadow: none;
                page-break-inside: avoid;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                margin: 0 auto;
            }
            @page { size: A4 portrait; margin: 10mm; }
        }

        @media (max-width: 600px) {
            .id-card { width: 350px; height: 570px; }
        }
    </style>
</head>
<body>

    <!-- ACTIONS -->
    <div class="actions no-print">
        <?php if ($role == 'admin'): ?>
            <a href="admin_dashboard.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        <?php else: ?>
            <a href="student_dashboard.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        <?php endif; ?>
        <button onclick="trackAndPrint('id_card')" class="btn-print">
    <i class="fas fa-print"></i> Print ID Card
</button>
    </div>

    <!-- ID CARD WRAPPER -->
    <div class="id-card-wrapper">

        <!-- ============================================
             FRONT SIDE
             ============================================ -->
        <div class="id-card front">

            <!-- LOGO WATERMARK -->
            <div class="watermark">
                <img src="images/dala-logo.png" alt="Watermark"
                     onerror="this.style.display='none'">
            </div>

            <!-- HEADER -->
            <div class="card-header">
                <div class="header-top">
                    <div class="logo">
                        <img src="images/dala-logo.png" alt="Logo" 
                             onerror="this.src='https://via.placeholder.com/46/ffffff/0d2818?text=DC'">
                    </div>
                    <div class="college-info">
                        <div class="college-name">Dala College of Education, Kano</div>
                        <div class="college-state">Kano State</div>
                        <div class="college-accredited">Accredited by NCCE, Abuja</div>
                        <div class="college-office">Office of the Student's Affairs</div>
                    </div>
                </div>

                <!-- WEBSITE DA EMAIL -->
                <div class="header-contact">
                    <span><i class="fas fa-envelope"></i> info@dalacollege.edu.ng</span>
                    <span><i class="fas fa-globe"></i> www.dalacollege.edu.ng</span>
                </div>
            </div>

            <div class="red-bar"></div>

            <!-- BODY -->
            <div class="card-body">

                <div class="student-photo">
                    <img src="<?php echo $photo_path; ?>" alt="Student Photo" 
                         onerror="this.src='https://via.placeholder.com/125x145/cccccc/333333?text=PHOTO'">
                </div>

                <div class="student-name">
                    <h2><?php echo htmlspecialchars($student['fullname']); ?></h2>
                    <div class="role">Student</div>
                </div>

                <!-- DETAILS -->
                <div class="student-details">
                    <div class="detail-row">
                        <span class="label">Reg No:</span>
                        <span class="value"><?php echo htmlspecialchars($student['reg_no'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Combination:</span>
                        <span class="value"><?php echo htmlspecialchars($student_combination); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Level:</span>
                        <span class="value"><?php echo htmlspecialchars($student['level'] ?? 'NCE I'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Programme:</span>
                        <span class="value"><?php echo htmlspecialchars($student['programme'] ?? 'NCE'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Centre:</span>
                        <span class="value"><?php echo htmlspecialchars($student['branch_code'] ?? 'SHINGE'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Phone:</span>
                        <span class="value"><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            </div>

            <!-- FOOTER -->
            <div class="card-footer">
                <div class="footer-row">
                    <div>
                        <div class="card-number"><?php echo htmlspecialchars($card['card_number'] ?? 'N/A'); ?></div>
                        <div>Card No.</div>
                    </div>
                    <div class="validity">
                        <div><strong>Valid Until:</strong> <?php echo date('M Y', strtotime($card['expiry_date'] ?? '+3 years')); ?></div>
                        <div>Session: <?php echo $session; ?></div>
                    </div>
                </div>

                <div class="footer-signature">
                    <span class="sig-label">Student Affairs:</span>
                    <span class="sig-name">08054367334</span>
                </div>
            </div>

        </div>

        <!-- ============================================
             BACK SIDE
             ============================================ -->
        <div class="id-card back">

            <!-- LOGO WATERMARK -->
            <div class="watermark">
                <img src="images/dala-logo.png" alt="Watermark"
                     onerror="this.style.display='none'">
            </div>

            <!-- BACK HEADER -->
            <div class="back-header">
                <div class="header-top">
                    <div class="logo">
                        <img src="images/dala-logo.png" alt="Logo" 
                             onerror="this.src='https://via.placeholder.com/42/ffffff/0d2818?text=DC'">
                    </div>
                    <div class="college-info">
                        <div class="college-name">Dala College of Education, Kano</div>
                        <div class="college-state">Kano State</div>
                        <div class="college-accredited">Accredited by NCCE, Abuja</div>
                        <div class="college-office">Office of the Student's Affairs</div>
                    </div>
                </div>
            </div>

            <div class="back-red-bar"></div>

            <!-- BACK BODY -->
            <div class="back-body">

                <!-- QR CODE -->
                <div class="qr-section">
                    <div class="qr-box">
                        <img src="<?php echo $qr_url; ?>" alt="QR Code">
                    </div>
                    <div class="qr-label">
                        <i class="fas fa-qrcode"></i> Scan to Verify
                    </div>
                </div>

                <!-- TERMS -->
                <div class="back-title">Terms &amp; Conditions</div>

                <p class="back-text">
                    This identity card remains the property of Dala College of Education, Kano.
                    It must be carried at all times within the campus and presented upon request
                    by any authorized college official.
                </p>

                <ul class="back-rules">
                    <li>This card is not transferable.</li>
                    <li>Report loss immediately to Student Affairs.</li>
                    <li>Damaged or defaced cards must be replaced.</li>
                    <li>Misuse may lead to disciplinary action.</li>
                    <li>Return the card upon leaving the college.</li>
                </ul>

                <div class="back-contact">
                    <strong>If found, please return to:</strong><br>
                    Dala College of Education, Kano<br>
                    P.M.B. 1234, Kano State, Nigeria<br>
                    Tel: 08054367334
                </div>
            </div>

            <!-- BACK FOOTER -->
            <div class="back-footer">
                <div class="signature">Student Affairs</div>
                <div class="sig-phone">08054367334</div>
                <div class="motto">"Knowledge, Excellence &amp; Success"</div>
            </div>

        </div>

    </div>

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