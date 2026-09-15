<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$student = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM students WHERE id = $student_id"));

if (!$student) {
    die("Student not found!");
}

// ============================================
// CHECK IF ADMISSION NUMBER IS AVAILABLE
// ============================================
if (empty($student['reg_no'])) {
    echo "<!DOCTYPE html><html><head><title>Not Available</title>";
    echo "<style>body{font-family:Arial;padding:40px;text-align:center;background:#f0f4f8;}";
    echo ".box{max-width:600px;margin:0 auto;background:white;padding:40px;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.1);}";
    echo "h2{color:#c62828;margin-bottom:15px;}";
    echo "p{color:#555;margin-bottom:20px;}";
    echo "a{display:inline-block;padding:12px 25px;background:#2e7d32;color:white;text-decoration:none;border-radius:8px;font-weight:bold;}";
    echo "</style></head><body>";
    echo "<div class='box'>";
    echo "<h2>❌ Admission Not Yet Approved</h2>";
    echo "<p>Your admission has not been approved yet. Please wait for admin approval before downloading your Acceptance Letter.</p>";
    echo "<a href='student_dashboard.php'>← Back to Dashboard</a>";
    echo "</div></body></html>";
    exit();
}

$academic_session = '2026/2027';
date_default_timezone_set('Africa/Lagos');
$current_date = date('d F, Y');
$letter_number = 'DCE/ACC/' . date('Y') . '/' . str_pad($student['id'], 4, '0', STR_PAD_LEFT);

$branch_names = [
    'SHINGE' => 'Shinge',
    'SABUWA' => 'Sabuwar Kofa',
    'TUDUN' => 'Tudun Yola'
];
$branch_name = $branch_names[$student['branch_code']] ?? $student['branch_code'] ?? 'Kano';

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
    <title>Acceptance Letter - Dala College</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Times New Roman', serif; background: #eef2f5; padding: 20px; }

.letter {
    max-width: 850px;
    margin: 0 auto;
    background: white;
    padding: 25px 35px;
    box-shadow: 0 10px 35px rgba(0,0,0,0.12);
    border-radius: 12px;
    border: 1px solid #e0e0e0;
    position: relative;
}
.letter::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 6px;
    background: linear-gradient(90deg, #0d2818 0%, #2e7d32 50%, #0d2818 100%);
    border-radius: 12px 12px 0 0;
}

/* HEADER */
.header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 12px;
    margin-bottom: 15px;
    border-bottom: 2px double #0d2818;
}
.header-left { display: flex; align-items: center; gap: 12px; }
.header-left .logo {
    width: 60px; height: 60px;
    object-fit: contain;
    border-radius: 50%;
    border: 2px solid #0d2818;
    padding: 3px;
    background: #fff;
}
.header-left .text h1 {
    font-size: 18px; color: #0d2818;
    font-weight: 900; letter-spacing: 0.5px;
    text-transform: uppercase;
}
.header-left .text .motto {
    font-size: 10px; color: #2e7d32;
    font-style: italic; font-weight: 600;
}
.header-left .text .accreditation {
    font-size: 9px; color: #6a8f6a;
}
.header-right {
    text-align: right;
    font-size: 10px;
    color: #555;
    line-height: 1.5;
}
.header-right .ref-no { font-weight: 700; color: #0d2818; }

/* TITLE */
.title-section {
    text-align: center;
    margin-bottom: 15px;
    background: #0d2818;
    color: white;
    padding: 10px 15px;
    border-radius: 6px;
}
.title-section h3 {
    margin: 0; font-size: 17px;
    letter-spacing: 2px;
    text-transform: uppercase;
    font-weight: 900;
}
.title-section h4 {
    margin: 3px 0 0; font-size: 11px;
    font-weight: 400; color: #a5d6a7;
    letter-spacing: 1px;
}

/* STUDENT SECTION */
.student-section {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    background: #f8faf8;
    border: 1px solid #c8e6c9;
    border-left: 5px solid #2e7d32;
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 15px;
}
.student-section .photo-box {
    width: 90px; height: 105px;
    border-radius: 8px;
    overflow: hidden;
    border: 2px solid #2e7d32;
    flex-shrink: 0;
    background: #e8f5e9;
}
.student-section .photo-box img {
    width: 100%; height: 100%;
    object-fit: cover;
}
.student-section .student-details {
    flex-grow: 1;
    line-height: 1.8;
    font-size: 12px;
}
.student-section .student-details .detail-row {
    display: flex;
    border-bottom: 1px dotted #dce8dc;
    padding: 1px 0;
}
.student-section .student-details .label {
    font-weight: 700;
    color: #0d2818;
    width: 130px;
    flex-shrink: 0;
    font-size: 11px;
}
.student-section .student-details .value {
    color: #1a2e1a;
    font-weight: 500;
}

/* BODY TEXT */
.body-text {
    line-height: 1.6;
    font-size: 12px;
    text-align: justify;
    margin-bottom: 15px;
    color: #1a2e1a;
}
.body-text p { margin-bottom: 8px; }
.body-text strong { color: #0d2818; }

/* OFFICIAL BOX */
.official-box {
    background: #f0f7f0;
    border: 1px solid #c8e6c9;
    border-radius: 6px;
    padding: 10px 15px;
    margin: 12px 0;
    font-size: 11px;
    color: #1a2e1a;
}
.official-box .official-title {
    font-weight: 900;
    color: #0d2818;
    font-size: 12px;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 1px;
    border-bottom: 1px solid #c8e6c9;
    padding-bottom: 4px;
}
.official-box ul {
    padding-left: 18px;
    line-height: 1.6;
}
.official-box ul li {
    margin-bottom: 2px;
}

/* SIGNATURE */
.signature-section {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-top: 20px;
    padding-top: 12px;
    gap: 30px;
}
.signature-box {
    text-align: center;
    flex: 1;
}
.signature-box .sign-line {
    border-bottom: 2px solid #0d2818;
    width: 180px;
    margin: 0 auto 3px;
}
.signature-box .sign-name {
    font-weight: 900;
    font-size: 12px;
    color: #0d2818;
}
.signature-box .sign-title {
    font-size: 10px;
    color: #6a8f6a;
    font-style: italic;
}
.signature-box .sign-date {
    font-size: 9px;
    color: #555;
    margin-top: 3px;
}

/* QR CODE */
.qr-section {
    text-align: center;
    margin-top: 15px;
    padding-top: 12px;
    border-top: 1px solid #dce8dc;
}
.qr-section img {
    width: 90px; height: 90px;
    border: 2px solid #0d2818;
    border-radius: 6px;
    padding: 3px;
    background: white;
}
.qr-section p {
    font-size: 10px;
    color: #0d2818;
    margin-top: 5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* FOOTER */
.letter-footer {
    margin-top: 15px;
    padding-top: 10px;
    border-top: 1px solid #dce8dc;
    text-align: center;
    font-size: 10px;
    color: #6a8f6a;
}

/* PRINT */
@media print {
    body { background: white; padding: 0; margin: 0; }
    .letter {
        box-shadow: none;
        border: none;
        padding: 15px 25px;
        border-radius: 0;
        max-width: 100%;
        page-break-inside: avoid;
        page-break-after: avoid;
    }
    .letter::before { display: none; }
    .btn-print, .btn-back { display: none !important; }
    .header, .title-section, .student-section, .body-text,
    .official-box, .signature-section, .qr-section {
        page-break-inside: avoid;
    }
    @page { size: A4; margin: 8mm; }
}

@media (max-width: 768px) {
    .letter { padding: 15px; }
    .header { flex-direction: column; text-align: center; gap: 8px; }
    .header-left { flex-direction: column; }
    .header-right { text-align: center; }
    .student-section { flex-direction: column; align-items: center; text-align: center; }
    .student-section .student-details .detail-row { flex-direction: column; align-items: center; }
    .signature-section { flex-direction: column; gap: 15px; }
}
    </style>
</head>
<body>

<div class="letter">
    <!-- HEADER -->
    <div class="header">
        <div class="header-left">
            <?php if (file_exists('images/dala-logo.png')): ?>
                <img src="images/dala-logo.png" class="logo" alt="Logo">
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

    <!-- TITLE -->
    <div class="title-section">
        <h3>ACCEPTANCE LETTER</h3>
        <h4><?php echo $academic_session; ?> Academic Session</h4>
    </div>

    <!-- STUDENT DETAILS -->
    <div class="student-section">
        <div class="photo-box">
            <?php if (!empty($student['photo']) && file_exists($photo_path)): ?>
                <img src="<?php echo $photo_path; ?>" alt="Student Photo">
            <?php else: ?>
                <div style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;font-size:50px;color:#a5d6a7;">👤</div>
            <?php endif; ?>
        </div>
        <div class="student-details">
            <div class="detail-row">
                <span class="label">Admission No.</span>
                <span class="value"><?php echo htmlspecialchars($student['reg_no']); ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Full Name</span>
                <span class="value"><strong><?php echo strtoupper(htmlspecialchars($student['fullname'])); ?></strong></span>
            </div>
            <div class="detail-row">
                <span class="label">Programme</span>
                <span class="value"><?php echo htmlspecialchars($student['programme']); ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Department</span>
                <span class="value"><?php echo htmlspecialchars($department_name); ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Level</span>
                <span class="value"><?php echo htmlspecialchars($student['level']); ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Study Centre</span>
                <span class="value"><?php echo htmlspecialchars($branch_name); ?></span>
            </div>
        </div>
    </div>

    <!-- BODY -->
    <div class="body-text">
        <p><strong>Dear <?php echo strtoupper(htmlspecialchars($student['fullname'])); ?>,</strong></p>
        
        <p><strong>RE: ACCEPTANCE OF ADMISSION</strong></p>
        
        <p>I am pleased to acknowledge that you have accepted the offer of admission into <strong>Dala College of Education, Kano</strong> for the <strong><?php echo $academic_session; ?> Academic Session</strong>.</p>
        
        <p>Your admission number is <strong><?php echo htmlspecialchars($student['reg_no']); ?></strong>. Please keep this number safe as it will be required for all academic activities.</p>
    </div>

    <!-- OFFICIAL INFORMATION BOX -->
    <div class="official-box">
        <div class="official-title">📋 Important Information for New Students</div>
        <ul>
            <li><strong>Registration:</strong> You are required to complete your course registration before the deadline.</li>
            <li><strong>Documents:</strong> Submit original and photocopies of your O'Level results, Birth Certificate, and Local Government Indigene Certificate.</li>
            <li><strong>Acceptance Fee:</strong> Pay the required acceptance fee at the Bursary Department or through the online portal.</li>
            <li><strong>Matriculation:</strong> You will be officially matriculated after completing your registration.</li>
            <li><strong>Student ID:</strong> Collect your Student ID Card from the ICT Office after registration.</li>
            <li><strong>Portal:</strong> Login to <strong>www.dalacoe.edu.ng</strong> with your Username and Password for all academic activities.</li>
            <li><strong>Deadline:</strong> Failure to complete your registration before the deadline may lead to withdrawal of admission.</li>
        </ul>
    </div>

    <div class="body-text">
        <p>You are required to complete your registration and other necessary documentation before the deadline. Failure to do so may lead to the withdrawal of your admission.</p>
        
        <p>We wish you a successful academic session.</p>
        
        <p style="margin-top:15px;">Yours faithfully,</p>
    </div>

    <!-- SIGNATURE SECTION -->
    <div class="signature-section">
        <div class="signature-box">
            <div class="sign-line"></div>
            <div class="sign-name">Mrs. LUBABATU ALIYU YOLA</div>
            <div class="sign-title">Registrar</div>
            <div class="sign-date">Dala College of Education, Kano</div>
        </div>
        <div class="signature-box">
            <div class="sign-line"></div>
            <div class="sign-name"><?php echo strtoupper(htmlspecialchars($student['fullname'])); ?></div>
            <div class="sign-title">Student's Signature</div>
            <div class="sign-date">Date: <?php echo $current_date; ?></div>
        </div>
    </div>

    <!-- QR CODE -->
    <div class="qr-section">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode('https://dalacoe.edu.ng/verify.php?reg_no=' . $student['reg_no']); ?>" alt="QR Code">
        <p>Scan to Verify</p>
        <p style="font-size: 11px; color: #666; margin-top: 5px;"><?php echo htmlspecialchars($student['reg_no']); ?></p>
    </div>

    <!-- FOOTER -->
    <div class="letter-footer">
        <strong>DALA COLLEGE OF EDUCATION, KANO</strong> — Knowledge, Excellence &amp; Success<br>
        📍 <?php echo htmlspecialchars($branch_name); ?> Study Centre, Kano State, Nigeria
    </div>
</div>

<!-- BUTTONS -->
<div style="text-align:center; margin-top:20px;">
    <button onclick="window.print()" class="btn-print" style="padding:12px 30px; background:#2e7d32; color:white; border:none; border-radius:8px; font-size:16px; font-weight:bold; cursor:pointer;">
        🖨️ Print Acceptance Letter
    </button>
    <a href="student_dashboard.php" class="btn-back" style="display:inline-block; padding:12px 30px; background:#6a8f6a; color:white; text-decoration:none; border-radius:8px; font-size:16px; font-weight:bold; margin-left:10px;">
        ← Back to Dashboard
    </a>
</div>

</body>
</html>