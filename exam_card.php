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
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    die("Student not found!");
}

// ============================================
// STUDENT DATA
// ============================================
$student_level_raw = $student['level'] ?? 'NCEI';
$student_level = str_replace(' ', '', $student_level_raw);

$student_combination = !empty($student['combination']) 
    ? trim($student['combination']) 
    : trim($student['course']);

// ============================================
// GET SELECTED LEVEL & SEMESTER
// ============================================
$selected_level = isset($_GET['level']) ? mysqli_real_escape_string($conn, $_GET['level']) : $student_level;
$selected_semester = isset($_GET['semester']) ? mysqli_real_escape_string($conn, $_GET['semester']) : 'First Semester';
$academic_year = '2024/2025';

// ============================================
// GET REGISTERED COURSES FOR SELECTED LEVEL & SEMESTER
// ============================================
$reg_query = "SELECT * FROM course_registrations 
              WHERE student_id = $student_id 
              AND level = '$selected_level' 
              AND semester = '$selected_semester' 
              AND academic_year = '$academic_year'
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

$levels = ['NCEI', 'NCEII', 'NCEIII'];
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
        
        /* ============ CARD CONTAINER ============ */
        .card-container {
            max-width: 850px;
            margin: 0 auto;
            background: white;
            padding: 15px 20px;
            box-shadow: 0 4px 30px rgba(0,0,0,0.15);
            border-radius: 8px;
            border: 2px solid #0d2818;
        }
        
        /* ============ HEADER ============ */
        .exam-header {
            position: relative;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px double #0d2818;
        }
        .exam-header .exam-logo-left {
            position: absolute;
            left: 0;
            top: 0;
            width: 65px;
            height: 65px;
        }
        .exam-header .exam-logo-left img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .exam-header .exam-header-text {
            text-align: center;
            padding: 0 75px;
            margin-top: 2px;
        }
        .exam-header .exam-college-name {
            font-size: 16px;
            font-weight: 900;
            color: #0d2818;
            letter-spacing: 0.5px;
            margin-bottom: 1px;
            text-transform: uppercase;
        }
        .exam-header .exam-motto {
            font-size: 9px;
            color: #c62828;
            font-weight: 700;
            font-style: italic;
            margin-bottom: 3px;
        }
        .exam-header .exam-accreditation {
            font-size: 8px;
            color: #c62828;
            font-weight: 600;
            margin-bottom: 2px;
        }
        .exam-header .exam-contact {
            font-size: 8px;
            color: #1976d2;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .exam-header .exam-form-title {
            font-size: 11px;
            font-weight: 800;
            color: #0d2818;
            text-transform: uppercase;
            margin: 6px 0 0 0;
            border-top: 1px solid #0d2818;
            border-bottom: 1px solid #0d2818;
            padding: 3px 0;
            letter-spacing: 0.5px;
        }
        
        /* ============ STUDENT INFO + PHOTO ============ */
        .exam-body-info {
            margin-top: 8px;
            margin-bottom: 10px;
        }
        .exam-body-info .info-wrapper {
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }
        .exam-body-info .student-details {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2px 15px;
            font-size: 9px;
        }
        .exam-body-info .student-details .row {
            display: flex;
            padding: 2px 0;
            border-bottom: 1px dotted #ccc;
        }
        .exam-body-info .student-details .label {
            font-weight: 700;
            min-width: 90px;
            color: #0d2818;
        }
        .exam-body-info .student-details .value {
            color: #000;
        }
        .exam-body-info .student-photo {
            width: 70px;
            height: 85px;
            border: 1px solid #0d2818;
            border-radius: 3px;
            overflow: hidden;
            background: #f0f0f0;
            flex-shrink: 0;
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
            font-size: 9px;
            margin-top: 8px;
        }
        .exam-table th {
            background: #0d2818 !important;
            color: white !important;
            padding: 4px 4px;
            border: 1px solid #000;
            text-align: left;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            font-size: 9px;
        }
        .exam-table td {
            padding: 3px 4px;
            border: 1px solid #000;
            vertical-align: middle;
            font-size: 9px;
        }
        .exam-table tr:nth-child(even) td {
            background: #f8faf8;
        }
        .exam-table .sign-col {
            height: 25px;
            background: #fffef5 !important;
        }
        .exam-table .date-col {
            height: 25px;
            background: #fffef5 !important;
        }
        .exam-table tr.total-row td {
            background: #0d2818 !important;
            color: white !important;
            font-weight: 800;
            padding: 5px;
            font-size: 9px;
        }
        
        /* ============ WARNING ============ */
        .warnings {
            background: #fff3e0;
            color: #e65100;
            padding: 5px 10px;
            border-radius: 5px;
            border-left: 3px solid #ff9800;
            margin: 6px 0;
            font-size: 9px;
        }
        
        /* ============ DECLARATION ============ */
        .exam-declaration {
            margin-top: 6px;
            padding: 6px 8px;
            border: 1px solid #0d2818;
            background: #f8faf8;
            font-size: 8px;
            border-radius: 3px;
        }
        
        /* ============ SIGNATURES (Accountant & Exam Officer) ============ */
        .exam-signature-area {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #0d2818;
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
            margin-bottom: 3px;
        }
        .exam-signature-area .sign-title {
            font-weight: 800;
            font-size: 10px;
            color: #0d2818;
            text-transform: uppercase;
        }
        .exam-signature-area .sign-date {
            font-size: 8px;
            color: #555;
            margin-top: 8px;
        }
        .exam-signature-area .sign-date span {
            border-bottom: 1px dotted #000;
            padding: 0 25px;
        }
        
        /* ============ FOOTER ============ */
        .exam-footer {
            margin-top: 12px;
            text-align: center;
            font-size: 8px;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 5px;
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
        
        /* ============ PRINT - A4 PORTRAIT ============ */
        @media print {
            .no-print { display: none !important; }
            html, body { 
                background: white; 
                padding: 0; 
                margin: 0;
                width: 210mm;
                height: 297mm;
            }
            .card-container { 
                box-shadow: none; 
                padding: 8mm 10mm; 
                border: 1px solid #000;
                width: 210mm;
                min-height: 280mm;
                max-width: 210mm;
                border-radius: 0;
                page-break-inside: avoid;
                page-break-after: avoid;
                margin: 0;
                box-sizing: border-box;
            }
            .exam-table {
                page-break-inside: avoid;
            }
            .exam-signature-area {
                page-break-inside: avoid;
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
                margin: 0;
            }
        }
        
        @media (max-width: 600px) {
            .selection-form { grid-template-columns: 1fr; }
            .card-container { padding: 12px; }
            .exam-body-info .info-wrapper { flex-direction: column; align-items: center; }
            .exam-signature-area .sign-row { grid-template-columns: 1fr; gap: 30px; }
            .exam-table { font-size: 8px; }
        }
    </style>
</head>
<body>

<!-- ============================================ -->
<!-- SELECTION FORM - Select Level & Semester -->
<!-- ============================================ -->
<div class="selection-form no-print">
    <div class="form-group">
        <label><i class="fas fa-layer-group"></i> Select Level</label>
        <select id="levelSelect">
            <?php foreach ($levels as $lvl): ?>
                <option value="<?php echo $lvl; ?>" <?php echo ($selected_level == $lvl) ? 'selected' : ''; ?>>
                    <?php echo $lvl; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label><i class="fas fa-calendar-alt"></i> Select Semester</label>
        <select id="semesterSelect">
            <?php foreach ($semesters as $sem): ?>
                <option value="<?php echo $sem; ?>" <?php echo ($selected_semester == $sem) ? 'selected' : ''; ?>>
                    <?php echo $sem; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="button" class="btn-view" onclick="viewExamCard()">
        <i class="fas fa-eye"></i> View Exam Card
    </button>
</div>

<script>
function viewExamCard() {
    var level = document.getElementById('levelSelect').value;
    var semester = document.getElementById('semesterSelect').value;
    window.location.href = 'exam_card.php?level=' + encodeURIComponent(level) + '&semester=' + encodeURIComponent(semester);
}
</script>

<?php if (empty($registered_courses)): ?>
    <!-- ============================================ -->
    <!-- NO COURSES MESSAGE -->
    <!-- ============================================ -->
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
    <!-- ============================================ -->
    <!-- EXAM CARD -->
    <!-- ============================================ -->
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
                <div class="exam-contact">🌐 www.dalacollege.edu.ng | 📧 dalacollegekano@gmail.com</div>
                <div class="exam-form-title">Examination Attendance Card — <?php echo $academic_year; ?> Academic Session</div>
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
                         onerror="this.src='https://via.placeholder.com/70x85/cccccc/333333?text=PHOTO'">
                </div>
            </div>
        </div>

        <!-- EXAM TABLE -->
        <table class="exam-table">
            <thead>
                <tr>
                    <th style="width:25px;">#</th>
                    <th style="width:75px;">Course Code</th>
                    <th>Course Title</th>
                    <th style="width:40px;">Unit</th>
                    <th style="width:85px;">Date</th>
                    <th style="width:110px;">Invigilator's Signature</th>
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
                    <td colspan="3" style="text-align:right; padding:5px;">TOTAL UNITS:</td>
                    <td style="text-align:center; padding:5px;"><?php echo $total_units; ?></td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>

        <!-- WARNING -->
        <div class="warnings">
            ⚠️ <strong>Important:</strong> This exam card must be presented at the exam hall with a valid ID card.
            No candidate will be allowed without this card. All courses must be signed by the invigilator.
        </div>

        <!-- DECLARATION -->
        <div class="exam-declaration">
            <strong>DECLARATION:</strong> I hereby declare that I have registered for the above courses and I am qualified to sit for the examinations. I understand that any examination malpractice will lead to disqualification.
        </div>

        <!-- SIGNATURES (Accountant & Exam Officer) -->
        <div class="exam-signature-area">
            <div class="sign-row">
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <div class="sign-title">Accountant</div>
                    <div class="sign-date">Date: <span></span></div>
                </div>
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <div class="sign-title">Exam Officer</div>
                    <div class="sign-date">Date: <span></span></div>
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="exam-footer">
            <p>This is a computer-generated document. No signature is required.</p>
            <p>Dala College of Education, Kano — <?php echo date('Y'); ?></p>
        </div>
        
    </div>

    <!-- ============================================ -->
    <!-- ACTIONS -->
    <!-- ============================================ -->
    <div class="actions no-print">
        <a href="student_dashboard.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <button onclick="window.print()" class="btn-print">
            <i class="fas fa-print"></i> Print Exam Card
        </button>
    </div>
<?php endif; ?>

</body>
</html>