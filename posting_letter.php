<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['user_id'];

$query = "SELECT * FROM students WHERE id = $student_id";
$result = mysqli_query($conn, $query);
$student = mysqli_fetch_assoc($result);

if (!$student) {
    echo "Student not found!";
    exit();
}

// Branch name
$branch_names = [
    'SHINGE' => 'Shinge',
    'SABUWA' => 'Sabuwar Kofa',
    'TUDUN' => 'Tudun Yola'
];
$branch_name = $branch_names[$student['branch_code']] ?? $student['branch_code'] ?? 'Kano';

// ============================================
// AUTO DATE: 14 WEEKS DAGA RANAR DA AKA BUƊE
// ============================================
$tp_start_date = date('Y-m-d'); // Yau
$tp_end_date = date('Y-m-d', strtotime($tp_start_date . ' + 98 days')); // + 14 weeks = 98 days

// ============================================
// HANDLE FORM SUBMISSION
// ============================================
$tp_school = '';
$form_submitted = false;
$form_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_letter'])) {
    $tp_school = mysqli_real_escape_string($conn, trim($_POST['tp_school']));
    
    if (empty($tp_school)) {
        $form_error = "❌ Please enter the school name.";
    } else {
        // Adana ranar buɗewa da ranar ƙarewa a session
        $_SESSION['tp_school'] = $tp_school;
        $_SESSION['tp_start_date'] = $tp_start_date;
        $_SESSION['tp_end_date'] = $tp_end_date;
        
        $form_submitted = true;
    }
}

// Idan an riga an adana a session
if (!$form_submitted && isset($_SESSION['tp_school'])) {
    $tp_school = $_SESSION['tp_school'];
    $tp_start_date = $_SESSION['tp_start_date'];
    $tp_end_date = $_SESSION['tp_end_date'];
    $form_submitted = true;
}

// Reset
if (isset($_GET['reset'])) {
    unset($_SESSION['tp_school']);
    unset($_SESSION['tp_start_date']);
    unset($_SESSION['tp_end_date']);
    header('Location: posting_letter.php');
    exit();
}

// Current date
date_default_timezone_set('Africa/Lagos');
$current_date = date('d F, Y');

// Letter number
$letter_number = 'DCE/TP/' . date('Y') . '/' . str_pad($student['id'], 4, '0', STR_PAD_LEFT);

// Department names
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

$photo_path = 'uploads/students/' . $student['photo'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TP Posting Letter - Dala College</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Times New Roman', Times, serif;
            background: #eef2f5;
            padding: 30px;
        }
        
        .letter-container {
            max-width: 850px;
            margin: 0 auto;
            background: #ffffff;
            padding: 45px 50px;
            box-shadow: 0 10px 35px rgba(0,0,0,0.12);
            border-radius: 12px;
            border: 1px solid #e0e0e0;
            position: relative;
        }
        
        .letter-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 8px;
            background: linear-gradient(90deg, #0d2818 0%, #2e7d32 50%, #0d2818 100%);
            border-radius: 12px 12px 0 0;
        }
        
        .letter-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 20px;
            margin-bottom: 25px;
            border-bottom: 3px double #0d2818;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 18px;
        }
        .header-left .logo {
            width: 85px;
            height: 85px;
            object-fit: contain;
            border-radius: 50%;
            border: 3px solid #0d2818;
            padding: 5px;
            background: #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .header-left .text h1 {
            font-size: 24px;
            color: #0d2818;
            margin: 0;
            font-weight: 900;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .header-left .text .motto {
            font-size: 13px;
            color: #2e7d32;
            font-style: italic;
            margin: 2px 0;
            font-weight: 600;
        }
        .header-left .text .accreditation {
            font-size: 12px;
            color: #6a8f6a;
            margin: 0;
        }
        .header-right {
            text-align: right;
            font-size: 13px;
            color: #555;
            line-height: 1.8;
        }
        .header-right .ref-no {
            font-weight: 700;
            color: #0d2818;
        }
        
        .title-section {
            text-align: center;
            margin-bottom: 25px;
            background: #0d2818;
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
        }
        .title-section h3 {
            margin: 0;
            font-size: 20px;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .title-section h4 {
            margin: 4px 0 0;
            font-size: 15px;
            font-weight: 400;
            color: #a5d6a7;
        }
        
        .student-section {
            display: flex;
            align-items: flex-start;
            gap: 25px;
            background: #f8faf8;
            border: 1px solid #c8e6c9;
            border-left: 6px solid #2e7d32;
            padding: 20px 25px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        .student-section .photo-box {
            width: 130px;
            height: 150px;
            border-radius: 10px;
            overflow: hidden;
            border: 3px solid #2e7d32;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
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
            font-size: 50px;
            color: #a5d6a7;
        }
        .student-section .student-details {
            flex-grow: 1;
            line-height: 2.2;
            font-size: 15px;
        }
        .student-section .student-details .detail-row {
            display: flex;
            border-bottom: 1px dotted #dce8dc;
            padding: 2px 0;
        }
        .student-section .student-details .detail-row:last-child {
            border-bottom: none;
        }
        .student-section .student-details .label {
            font-weight: 700;
            color: #0d2818;
            width: 170px;
            flex-shrink: 0;
        }
        .student-section .student-details .value {
            color: #1a2e1a;
            font-weight: 500;
        }
        
        .recipient {
            margin: 15px 0 20px;
            font-size: 15px;
            line-height: 2;
        }
        .recipient .school-name {
            font-weight: 700;
            color: #0d2818;
            font-size: 16px;
        }
        
        .body-text {
            line-height: 2;
            font-size: 15px;
            text-align: justify;
            margin-bottom: 25px;
            color: #1a2e1a;
        }
        .body-text p {
            margin-bottom: 12px;
        }
        .body-text .date-range {
            text-align: center;
            font-size: 16px;
            font-weight: 700;
            color: #0d2818;
            background: #e8f5e9;
            padding: 8px 15px;
            border-radius: 6px;
            display: inline-block;
            width: 100%;
        }
        
      .qr-section {
    text-align: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid #dce8dc;
}

.qr-section img {
    width: 130px;
    height: 130px;
    border: 2px solid #0d2818;
    border-radius: 8px;
    padding: 5px;
    background: white;
}

.qr-section p {
    font-size: 12px;
    color: #0d2818;
    margin-top: 8px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
}
        
        .letter-footer {
            margin-top: 30px;
            padding-top: 18px;
            border-top: 2px solid #dce8dc;
            text-align: center;
            font-size: 13px;
            color: #6a8f6a;
        }
        
        .form-container {
            max-width: 850px;
            margin: 20px auto 0;
            background: white;
            padding: 30px 35px;
            border-radius: 12px;
            box-shadow: 0 4px 30px rgba(0,0,0,0.1);
        }
        .form-container h3 {
            color: #0d2818;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #0d2818;
        }
        .form-group input {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #dce8dc;
            border-radius: 8px;
            font-size: 0.95rem;
        }
        .form-group input:focus {
            border-color: #2e7d32;
            outline: none;
        }
        .form-error {
            background: #ffebee;
            color: #c62828;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #c62828;
        }
        .form-success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #2e7d32;
        }
        
        .date-info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #1976d2;
            margin-bottom: 15px;
            font-size: 14px;
            color: #0d47a1;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        .btn-submit {
            padding: 12px 30px;
            background: #2e7d32;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-submit:hover { background: #1b5e20; transform: translateY(-2px); }
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
        .btn-reset {
            padding: 12px 30px;
            background: #ffa000;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .btn-reset:hover { background: #f57f17; transform: translateY(-2px); }
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
                padding: 30px 35px;
                border-radius: 0;
                max-width: 100%;
            }
            .letter-container::before { display: none; }
            .form-container { display: none !important; }
            .no-print { display: none !important; }
            .student-section { break-inside: avoid; }
            .signature-section { break-inside: avoid; }
        }
        
        @media (max-width: 768px) {
            .letter-container { padding: 25px; }
            .letter-header { flex-direction: column; text-align: center; gap: 10px; }
            .header-left { flex-direction: column; }
            .header-right { text-align: center; }
            .student-section { flex-direction: column; align-items: center; text-align: center; }
            .student-section .student-details .detail-row { flex-direction: column; align-items: center; }
            .student-section .student-details .label { width: auto; }
            .signature-section { grid-template-columns: 1fr; gap: 20px; }
            .form-container { padding: 20px; }
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
                <div class="logo" style="display:flex; align-items:center; justify-content:center; font-size:40px; border:3px solid #0d2818; border-radius:50%; width:85px; height:85px; background:#e8f5e9;">🎓</div>
            <?php endif; ?>
            <div class="text">
                <h1>DALA COLLEGE OF EDUCATION</h1>
                <div class="motto">Knowledge, Excellence &amp; Success</div>
                <div class="accreditation">📍 Accredited by NCCE, Abuja</div>
            </div>
        </div>
        <div class="header-right">
            <div><span class="ref-no">Ref:</span> <?php echo $letter_number; ?></div>
            <div><strong>Date:</strong> <?php echo $current_date; ?></div>
            <div>📞 07042251142, 08107666258</div>
            <div>✉️ dalacoekano@gmail.com</div>
        </div>
    </div>

    <div class="title-section">
        <h3>TEACHING PRACTICE POSTING LETTER</h3>
        <h4>Office of the Chairman, Teaching Practice Committee</h4>
    </div>

    <div class="student-section">
        <div class="photo-box">
            <?php if (!empty($student['photo']) && file_exists($photo_path)): ?>
                <img src="<?php echo $photo_path; ?>" alt="Student Photo">
            <?php else: ?>
                <div class="no-photo">👤</div>
            <?php endif; ?>
        </div>
        <div class="student-details">
            <div class="detail-row">
                <span class="label">Admission No.</span>
                <span class="value"><?php echo htmlspecialchars($student['student_id'] ?? 'N/A'); ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Full Name</span>
                <span class="value"><strong><?php echo strtoupper(htmlspecialchars($student['fullname'])); ?></strong></span>
            </div>
            <div class="detail-row">
                <span class="label">Phone Number</span>
                <span class="value"><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Department</span>
                <span class="value"><?php echo htmlspecialchars($department_name); ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Programme</span>
                <span class="value"><?php echo htmlspecialchars($student['programme'] ?? 'NCE'); ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Level</span>
                <span class="value"><?php echo htmlspecialchars($student['level'] ?? 'NCE I'); ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Study Centre</span>
                <span class="value"><?php echo htmlspecialchars($branch_name); ?></span>
            </div>
        </div>
    </div>

    <div class="recipient">
        <strong>The Principal/Head Teacher,</strong><br>
        <span class="school-name"><?php echo !empty($tp_school) ? htmlspecialchars($tp_school) : '..............................................'; ?></span><br>
        ..............................................<br>
        ..............................................
    </div>

    <div class="body-text">
        <p><strong>Dear Sir/Madam,</strong></p>
        
        <p><strong>RE: TEACHING PRACTICE POSTING</strong></p>
        
        <p>You are hereby informed that the student whose details appear above has been posted to your school for <strong>Teaching Practice (TP)</strong> of <strong>14 weeks</strong>, effective from:</p>
        
        <div class="date-range">
            <?php echo !empty($tp_start_date) ? date('d/m/Y', strtotime($tp_start_date)) : '..........................'; ?>
            &nbsp;&nbsp;<strong>TO</strong>&nbsp;&nbsp;
            <?php echo !empty($tp_end_date) ? date('d/m/Y', strtotime($tp_end_date)) : '..........................'; ?>
        </div>
        
        <p style="margin-top:15px;">During this period, you are authorized to assign duties and responsibilities to the student aimed at enhancing their proficiency, productivity, and effectiveness, as well as augmenting their knowledge and training.</p>
        
        <p>Any student found wanting or breaching regulations should be addressed appropriately, and such cases should be reported to the College for necessary disciplinary action.</p>
        
        <p>Your cooperation in this regard is highly appreciated.</p>
        
        <!-- ============================================ -->
<!-- QR CODE -->
<!-- ============================================ -->
<div class="qr-section">
    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode('https://dalacoe.edu.ng/verify.php?reg_no=' . ($student['reg_no'] ?? $student['student_id'])); ?>" alt="QR Code">
    <p>Scan to Verify</p>
    <p style="font-size: 11px; color: #666; margin-top: 5px;">
        <?php echo htmlspecialchars($student['reg_no'] ?? $student['student_id']); ?>
    </p>
</div>

    <div class="letter-footer">
        <strong>DALA COLLEGE OF EDUCATION, KANO</strong> — Knowledge, Excellence &amp; Success<br>
        📍 <?php echo htmlspecialchars($branch_name); ?> Study Centre, Kano State, Nigeria
    </div>
</div>

<div class="form-container no-print">
    <?php if ($form_error): ?>
        <div class="form-error">❌ <?php echo $form_error; ?></div>
    <?php endif; ?>
    <?php if ($form_submitted && !$form_error): ?>
        <div class="form-success">✅ Letter generated successfully! Click Print to download.</div>
    <?php endif; ?>
    
    <h3>📝 Enter Teaching Practice Posting Details</h3>
    
    <div class="date-info">
        <i class="fas fa-calendar-alt"></i> <strong>Note:</strong> Ranar fara aiki ita ce <strong>yau (<?php echo date('d/m/Y'); ?>)</strong>, 
        kuma zai ƙare a <strong><?php echo date('d/m/Y', strtotime('+98 days')); ?></strong> (14 weeks).
    </div>
    
    <form method="POST" action="">
        <div class="form-group">
            <label>School Name (Where student is posted)</label>
            <input type="text" name="tp_school" placeholder="Enter school name" required
                   value="<?php echo htmlspecialchars($tp_school); ?>">
        </div>
        
        <div class="action-buttons">
            <button type="submit" name="generate_letter" class="btn-submit">
                <i class="fas fa-file-alt"></i> Generate Letter
            </button>
        </div>
    </form>
    
    <div class="action-buttons" style="margin-top:15px;">
        <a href="student_dashboard.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <?php if ($form_submitted && !$form_error): ?>
            <button onclick="window.print()" class="btn-print">
                <i class="fas fa-print"></i> Print / PDF
            </button>
            <a href="posting_letter.php?reset=1" class="btn-reset">
                <i class="fas fa-edit"></i> Edit Details
            </a>
        <?php endif; ?>
    </div>
</div>

</body>
</html>