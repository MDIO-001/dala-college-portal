<?php
session_start();
include 'connect.php';

// Check if student is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['user_id'];

// Fetch student data
$query = "SELECT * FROM students WHERE id = $student_id";
$result = mysqli_query($conn, $query);
$student = mysqli_fetch_assoc($result);

if (!$student) {
    echo "Student not found!";
    exit();
}

// Get branch name
$branch_names = [
    'SHINGE' => 'Shinge',
    'SABUWA' => 'Sabuwar Kofa',
    'TUDUN' => 'Tudun Yola'
];
$branch_name = $branch_names[$student['branch_code']] ?? $student['branch_code'] ?? 'Kano';

// Current date
date_default_timezone_set('Africa/Lagos');
$current_date = date('Y-m-d');

// Department name mapping
$dept_names = [
    'ARB/ISS' => 'Arabic & Islamic Studies',
    'ENG/ISS' => 'English & Islamic Studies',
    'PED' => 'Primary Education',
    'HAU/ENG' => 'Hausa & English',
    'CSC/ISC' => 'Computer Science & Islamic Studies',
    'ENG/SOS' => 'English & Social Studies',
    'CSC/BIO' => 'Computer Science & Biology',
    'CSC/PHY' => 'Computer Science & Physics',
    'ENG/ECO' => 'English & Economics'
];
$department_name = $dept_names[$student['course']] ?? $student['course'] ?? 'Not Specified';

// Photo path
$photo_path = 'uploads/students/' . $student['photo'];

// QR Code
$qr_data = "Admission: " . ($student['student_id'] ?? 'N/A') . " | Name: " . $student['fullname'] . " | Phone: " . ($student['phone'] ?? 'N/A') . " | Department: " . $department_name . " | Level: " . ($student['level'] ?? 'NCE I');
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode($qr_data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Introductory Letter - Dala College</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Times New Roman', Times, serif;
            background: #eef2f5;
            padding: 30px;
        }
        
        .letter-container {
            max-width: 750px;
            margin: 0 auto;
            background: #ffffff;
            padding: 40px 45px;
            box-shadow: 0 10px 35px rgba(0,0,0,0.12);
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        
        .letter-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #0d2818;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .header-left .logo {
            width: 70px;
            height: 70px;
            object-fit: contain;
            border-radius: 50%;
            border: 3px solid #0d2818;
            padding: 3px;
            background: #fff;
        }
        .header-left .text h1 {
            font-size: 18px;
            color: #0d2818;
            text-transform: uppercase;
            margin: 0;
            letter-spacing: 1px;
        }
        .header-left .text .accreditation {
            font-size: 10px;
            color: #6a8f6a;
            font-style: italic;
        }
        .header-right {
            text-align: right;
            font-size: 11px;
            color: #555;
            line-height: 1.6;
        }
        .header-right .office {
            font-size: 12px;
            color: #2e7d32;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .top-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #333;
            margin-bottom: 12px;
        }
        
        .form-title {
            text-align: center;
            margin-bottom: 18px;
        }
        .form-title .tp-form {
            font-size: 13px;
            font-weight: 700;
            color: #2e7d32;
            letter-spacing: 2px;
        }
        .form-title h3 {
            font-size: 18px;
            color: #0d2818;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 2px 0;
        }
        .form-title .sub-title {
            font-size: 14px;
            color: #555;
            font-weight: 600;
        }
        
        .student-section {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            background: #f8faf8;
            border: 1px solid #c8e6c9;
            border-left: 4px solid #2e7d32;
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 18px;
        }
        .student-section .photo-box {
            width: 100px;
            height: 120px;
            border-radius: 6px;
            overflow: hidden;
            border: 3px solid #2e7d32;
            flex-shrink: 0;
            background: #e8f5e9;
        }
        .student-section .photo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .student-section .photo-box .no-photo {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: #a5d6a7;
        }
        .student-section .details {
            flex-grow: 1;
        }
        .student-section .details .detail-row {
            display: flex;
            padding: 2px 0;
            font-size: 14px;
            line-height: 1.8;
        }
        .student-section .details .label {
            font-weight: 700;
            color: #0d2818;
            width: 140px;
            flex-shrink: 0;
        }
        .student-section .details .value {
            color: #1a2e1a;
        }
        
        .recipient {
            margin-bottom: 12px;
            font-size: 14px;
            line-height: 2;
        }
        
        .body-text {
            line-height: 2;
            font-size: 14px;
            text-align: justify;
            margin-bottom: 18px;
            color: #1a2e1a;
        }
        .body-text p {
            margin-bottom: 10px;
        }
        .body-text .sign-stamp {
            margin-top: 12px;
            line-height: 2.2;
        }
        .body-text .sign-stamp .line {
            display: inline-block;
            width: 200px;
            border-bottom: 1px solid #0d2818;
            margin-left: 5px;
        }
        
        .qr-section {
            text-align: center;
            margin-top: 12px;
            padding: 10px;
            border: 2px dashed #2e7d32;
            border-radius: 10px;
            background: #f8faf8;
            max-width: 180px;
            margin-left: auto;
            margin-right: auto;
        }
        .qr-section img {
            width: 90px;
            height: 90px;
        }
        .qr-section p {
            font-size: 10px;
            color: #6a8f6a;
            margin-top: 3px;
        }
        
        .chairman-signature {
            margin-top: 18px;
            padding-top: 12px;
            border-top: 1px solid #dce8dc;
            font-size: 14px;
            font-weight: 700;
            color: #0d2818;
        }
        .chairman-signature .line {
            border-bottom: 2px solid #0d2818;
            width: 200px;
            margin-top: 25px;
        }
        
        .letter-footer {
            margin-top: 20px;
            padding-top: 12px;
            border-top: 2px solid #dce8dc;
            text-align: center;
            font-size: 11px;
            color: #6a8f6a;
        }
        
        .action-buttons {
            max-width: 750px;
            margin: 20px auto 0;
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn-print {
            padding: 12px 30px;
            background: #b30000;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-print:hover { background: #8b0000; transform: translateY(-2px); }
        .btn-back {
            padding: 12px 30px;
            background: #6a8f6a;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .btn-back:hover { background: #4a6a4a; transform: translateY(-2px); }
        
        .no-print { display: block; }
        
        @media print {
            body { background: white; padding: 0; margin: 0; }
            .letter-container {
                box-shadow: none;
                border: none;
                padding: 25px 30px;
                border-radius: 0;
                max-width: 100%;
            }
            .no-print { display: none !important; }
            .student-section { break-inside: avoid; }
            .qr-section { break-inside: avoid; }
        }
        
        @media (max-width: 768px) {
            .letter-container { padding: 20px; }
            .letter-header { flex-direction: column; text-align: center; gap: 10px; }
            .header-left { flex-direction: column; }
            .header-right { text-align: center; }
            .student-section { flex-direction: column; align-items: center; }
            .student-section .details .detail-row { flex-direction: column; align-items: center; }
            .student-section .details .label { width: auto; }
            .top-row { flex-direction: column; text-align: center; gap: 5px; }
            .qr-section { max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="letter-container" id="printArea">
    
    <div class="letter-header">
        <div class="header-left">
            <?php if (file_exists('images/dala-logo.png')): ?>
                <img src="images/dala-logo.png" class="logo" alt="Dala College Logo">
            <?php else: ?>
                <div class="logo" style="display:flex; align-items:center; justify-content:center; font-size:30px; border:3px solid #0d2818; border-radius:50%; width:70px; height:70px; background:#e8f5e9;">🎓</div>
            <?php endif; ?>
            <div class="text">
                <h1>DALA COLLEGE OF EDUCATION</h1>
                <div class="accreditation">Accredited by NCCE, Abuja</div>
            </div>
        </div>
        <div class="header-right">
            <div class="office">OFFICE OF THE CHAIRMAN<br>TEACHING PRACTICE COMMITTEE</div>
            <div>📞 07042251142, 08107666258</div>
            <div>📧 dalacoekano@gmail.com</div>
        </div>
    </div>

    <div class="top-row">
        <span><strong>Date:</strong> <?php echo $current_date; ?></span>
        <span><strong>Ref:</strong> DCE/TP/<?php echo date('Y'); ?>/<?php echo str_pad($student['id'], 4, '0', STR_PAD_LEFT); ?></span>
    </div>

    <div class="form-title">
        <div class="tp-form">TP FORM 01</div>
        <h3>INTRODUCTION LETTER FOR TEACHING PRACTICE</h3>
        <div class="sub-title">SCHOOL OF GENERAL EDUCATION</div>
    </div>

    <div class="student-section">
        <div class="photo-box">
            <?php if (!empty($student['photo']) && file_exists($photo_path)): ?>
                <img src="<?php echo $photo_path; ?>" alt="Student Photo">
            <?php else: ?>
                <div class="no-photo">👤</div>
            <?php endif; ?>
        </div>
        <div class="details">
            <div class="detail-row">
                <span class="label">Admission No.:</span>
                <span class="value"><strong><?php echo htmlspecialchars($student['student_id'] ?? 'N/A'); ?></strong></span>
            </div>
            <div class="detail-row">
                <span class="label">Name:</span>
                <span class="value"><strong><?php echo htmlspecialchars($student['fullname']); ?></strong></span>
            </div>
            <div class="detail-row">
                <span class="label">Phone No.:</span>
                <span class="value"><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Department:</span>
                <span class="value"><?php echo htmlspecialchars($department_name); ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Acad Session:</span>
                <span class="value">2024/2025</span>
            </div>
            <div class="detail-row">
                <span class="label">Level:</span>
                <span class="value"><?php echo htmlspecialchars($student['level'] ?? 'NCE I'); ?></span>
            </div>
        </div>
    </div>

    <div class="recipient">
        <strong>Principal/Head Teacher,</strong><br>
        ..............................................<br>
        ..............................................
    </div>

    <div class="body-text">
        <p><strong>Sir,</strong></p>
        
        <p>We are pleased to introduce the student whose details appear above. He/She has chosen to undertake his/her Teaching Practice at your esteemed institution for a period of <strong>14 weeks</strong>.</p>
        
        <p>We kindly request that you provide the necessary support and guidance to enable our student to meet the requirements of the minimum standards set by the National Commission for Colleges of Education (NCCE), Abuja.</p>
        
        <p><strong>Please append your signature and stamp if the student is accepted by your school.</strong></p>
        
        <div class="sign-stamp">
            <p><strong>Sign &amp; Stamp:</strong> <span class="line"></span></p>
            <p><strong>Date:</strong> <span class="line"></span></p>
        </div>
        
        <p style="margin-top:15px;">Thank you.</p>
    </div>

    <div class="qr-section">
        <img src="<?php echo $qr_url; ?>" alt="QR Code">
        <p>Scan to verify student details</p>
    </div>

    <div class="chairman-signature">
        <div class="line"></div>
        <p>TP Coordinator's Signature</p>
    </div>

    <div class="letter-footer">
        <strong>DALA COLLEGE OF EDUCATION, KANO</strong> — Knowledge, Excellence &amp; Success
    </div>
</div>

<div class="action-buttons no-print">
    <button type="button" class="btn-print" onclick="window.print()">
        <i class="fas fa-print"></i> Print Letter
    </button>
    <a href="student_dashboard.php" class="btn-back">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</div>

</body>
</html>