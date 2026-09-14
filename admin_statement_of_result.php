<?php
session_start();
include 'connect.php';
include 'result_functions.php';
include 'statement_functions.php';

include 'check_role.php';

if (!isset($_SESSION['user_id']) || !canAccessResultSystem()) {
    header('Location: login.php');
    exit();
}

$admin_name = $_SESSION['fullname'] ?? 'Admin';

// ============================================
// GET STUDENT
// ============================================
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

$student = null;
$cgpa = 0;
$category_gpas = [];
$grad_year = '';
$academic_year = getSetting($conn, 'current_session');

if ($student_id > 0) {
    $student = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM students WHERE id = $student_id"));
    
    if ($student) {
        $cgpa = getStudentCGPA($conn, $student_id);
        $category_gpas = getCategoryGPAs($conn, $student_id);
        $grad_year = getGraduationYear($conn, $student_id);
    }
}

// Samo students list
$students_list = mysqli_query($conn, "SELECT id, reg_no, fullname, combination, level FROM students ORDER BY fullname");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Statement of Result - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Tahoma,sans-serif; background:#f0f4f8; padding:20px; }
        .container { max-width:1200px; margin:0 auto; }
        
        .topbar { background:#0d2818; padding:15px 25px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; border-radius:12px; margin-bottom:20px; }
        .topbar .logo-title { color:#ffd54f; font-size:1.3rem; font-weight:800; }
        .topbar .logo-title span { color:#a5d6a7; }
        .topbar nav a { color:#c8e6c9; text-decoration:none; padding:8px 16px; border-radius:25px; font-size:0.85rem; }
        .topbar nav a:hover { background:#2e7d32; }
        .topbar nav .logout { background:#c62828; color:white !important; }
        .topbar .admin-badge { background:#c62828; color:white; padding:6px 18px; border-radius:20px; font-size:0.8rem; font-weight:600; }
        
        .result-nav { background:white; padding:15px; border-radius:12px; margin-bottom:20px; display:flex; gap:8px; flex-wrap:wrap; align-items:center; box-shadow:0 2px 10px rgba(0,0,0,0.05); }
        .result-nav .label { font-weight:700; color:#0d2818; margin-right:10px; font-size:0.85rem; }
        .result-nav a { padding:8px 15px; border-radius:6px; text-decoration:none; font-weight:600; font-size:0.75rem; }
        .result-nav a:hover { transform:translateY(-2px); }
        .r-course { background:#2e7d32; color:white; }
        .r-grade { background:#f9a825; color:#0d2818; }
        .r-entry { background:#8d2c2c; color:white; }
        .r-slip { background:#e53935; color:white; }
        .r-slip-pro { background:#a5d6a7; color:#0d2818; }
        .r-transcript { background:#5d4037; color:white; }
        .r-final { background:#558b2f; color:white; }
        .r-settings { background:#e65100; color:white; }
        .r-database { background:#37474f; color:white; }
        .r-statement { background:#455a64; color:white; box-shadow:0 0 0 3px #ffd54f; }
        
        .card { background:white; padding:25px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); margin-bottom:20px; }
        .card h2 { color:#0d2818; margin-bottom:15px; font-size:1.2rem; }
        
        .form-row { display:grid; grid-template-columns:1fr auto; gap:15px; align-items:end; }
        .form-group label { display:block; font-weight:600; color:#1a2e1a; margin-bottom:5px; font-size:0.85rem; }
        .form-group select { width:100%; padding:10px; border:2px solid #dce8dc; border-radius:8px; font-size:0.9rem; }
        
        .btn { padding:10px 25px; border:none; border-radius:8px; font-weight:700; font-size:0.9rem; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-green { background:#2e7d32; color:white; }
        .btn-blue { background:#1976d2; color:white; }
        
        /* ============================================ */
        /* STATEMENT OF RESULT DESIGN */
        /* ============================================ */
        .statement-container {
            background: white;
            padding: 25px;
            border: 2px solid #0d2818;
            margin-top: 20px;
            font-family: 'Times New Roman', serif;
        }
        
        /* Header */
        .stmt-header {
            display: grid;
            grid-template-columns: 90px 1fr 90px;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }
        .stmt-header .logo-box {
            width: 90px;
            height: 90px;
        }
        .stmt-header .logo-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .stmt-header .center-box {
            text-align: center;
        }
        .stmt-header .school-name {
            font-size: 1.5rem;
            font-weight: 900;
            color: #0d2818;
            letter-spacing: 1px;
        }
        .stmt-header .school-loc {
            font-size: 0.85rem;
            font-weight: 700;
            color: #0d2818;
            margin-top: 2px;
        }
        .stmt-header .accreditation {
            font-size: 0.75rem;
            color: #c62828;
            font-weight: 700;
            margin-top: 5px;
            border-top: 1px solid #c62828;
            border-bottom: 1px solid #c62828;
            padding: 3px 0;
            display: inline-block;
        }
        
        /* Contact Line */
        .stmt-contact {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            color: #0d2818;
            padding: 5px 0;
            border-bottom: 2px solid #0d2818;
            margin-bottom: 8px;
        }
        
        /* Date / No. Row */
        .stmt-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            font-weight: 700;
            color: #0d2818;
            padding: 5px 0;
            margin-bottom: 5px;
        }
        
        /* Emblem */
        .stmt-emblem {
            text-align: center;
            margin: 10px 0;
        }
        .stmt-emblem img {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }
        
        /* Title */
        .stmt-title {
            text-align: center;
            font-size: 1.3rem;
            font-weight: 900;
            color: #c62828;
            letter-spacing: 2px;
            margin: 10px 0;
            text-transform: uppercase;
        }
        
        /* Student Info with Photo */
        .stmt-info-box {
            display: grid;
            grid-template-columns: 1fr 120px;
            gap: 15px;
            border: 2px solid #2e7d32;
            padding: 10px;
            margin-bottom: 10px;
            background: #e8f5e9;
        }
        .stmt-info-box .info-left {
            display: grid;
            grid-template-columns: 100px 1fr;
            gap: 5px 15px;
            align-items: center;
            font-size: 0.95rem;
        }
        .stmt-info-box .info-label {
            font-weight: 800;
            color: #0d2818;
        }
        .stmt-info-box .info-value {
            font-weight: 700;
            color: #000;
            border-bottom: 1px solid #0d2818;
            padding: 2px 5px;
        }
        .stmt-info-box .photo-box {
            width: 120px;
            height: 140px;
            border: 2px solid #0d2818;
            background: #fff;
            overflow: hidden;
        }
        .stmt-info-box .photo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* Certification text */
        .stmt-cert {
            text-align: center;
            font-size: 0.9rem;
            font-style: italic;
            color: #0d2818;
            padding: 10px;
            margin-bottom: 10px;
            line-height: 1.4;
        }
        
        /* Result Table */
        .stmt-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            border: 2px solid #0d2818;
        }
        .stmt-table th {
            background: #2e7d32;
            color: white;
            padding: 8px;
            text-align: center;
            font-size: 0.9rem;
            font-weight: 900;
            border: 1px solid #0d2818;
            text-transform: uppercase;
        }
        .stmt-table th.sub {
            background: #1b5e20;
            font-size: 0.75rem;
        }
        .stmt-table td {
            padding: 8px 12px;
            border: 1px solid #0d2818;
            font-size: 0.9rem;
        }
        .stmt-table td.center { text-align: center; }
        .stmt-table td.bold { font-weight: 700; }
        .stmt-table tbody tr:nth-child(even) td { background: #f8faf8; }
        
        /* Overall Result */
        .stmt-overall {
            border: 2px solid #0d2818;
            margin-bottom: 15px;
        }
        .stmt-overall .row {
            display: grid;
            grid-template-columns: 200px 1fr;
            border-bottom: 1px solid #0d2818;
        }
        .stmt-overall .row:last-child { border-bottom: none; }
        .stmt-overall .label {
            background: #e8f5e9;
            padding: 8px 15px;
            font-weight: 800;
            color: #0d2818;
            border-right: 1px solid #0d2818;
            font-size: 0.9rem;
        }
        .stmt-overall .value {
            padding: 8px 15px;
            font-weight: 800;
            color: #c62828;
            font-size: 1rem;
        }
        
        /* Bottom: QR + Registrar */
        .stmt-bottom {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 40px;
            align-items: end;
        }
        .stmt-bottom .qr-box {
            text-align: center;
        }
        .stmt-bottom .qr-box img {
            width: 100px;
            height: 100px;
        }
        .stmt-bottom .qr-box p {
            font-size: 0.75rem;
            color: #0d2818;
            margin-top: 5px;
        }
        .stmt-bottom .sign-box {
            text-align: center;
        }
        .stmt-bottom .sign-box .sign-line {
            border-top: 1px solid #0d2818;
            margin-bottom: 5px;
            padding-top: 30px;
        }
        .stmt-bottom .sign-box .sign-title {
            font-weight: 700;
            font-size: 0.85rem;
            color: #0d2818;
        }
        
        /* Print */
        @media print {
            .topbar, .result-nav, .card, .btn, .no-print { display: none !important; }
            body { background: white; padding: 0; }
            .container { max-width: 100%; }
            .statement-container { border: 2px solid #000; margin: 0; padding: 15px; }
            @page { size: A4; margin: 8mm; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="topbar no-print">
            <div>
                <span class="logo-title">DALA <span>COLLEGE</span></span>
                <span style="display:block; font-size:0.55rem; color:#c8e6c9;">Admin Panel</span>
            </div>
            <nav>
                <a href="admin_dashboard.php">Dashboard</a>
                <a href="logout.php" class="logout">Logout</a>
            </nav>
            <div>
                <span class="admin-badge">👤 <?php echo htmlspecialchars($admin_name); ?></span>
            </div>
        </div>

        <div class="result-nav no-print">
            <span class="label">📊 RESULT SYSTEM:</span>
            <a href="admin_course_structure.php" class="r-course">COURSE_STRUCTURE</a>
            <a href="admin_grade_setup.php" class="r-grade">GRADE_SETUP</a>
            <a href="admin_result_entry.php" class="r-entry">RESULT_ENTRY</a>
            <a href="admin_result_slip.php" class="r-slip">RESULT_SLIP</a>
            <a href="admin_result_slip_pro.php" class="r-slip-pro">RESULT_SLIP_PRO</a>
            <a href="admin_transcript.php" class="r-transcript">TRANSCRIPT</a>
            <a href="admin_final_result.php" class="r-final">FINAL_RESULT</a>
            <a href="admin_settings.php" class="r-settings">SETTINGS</a>
            <a href="admin_result_database.php" class="r-database">RESULT_DATABASE</a>
            <a href="admin_statement_of_result.php" class="r-statement">STATEMENT_OF_RESULT</a>
        </div>

        <!-- SELECT STUDENT -->
        <div class="card no-print">
            <h2>🎓 Zaɓi Dalibi don Statement of Result</h2>
            <form method="GET">
                <div class="form-row">
                    <div class="form-group">
                        <label>Student</label>
                        <select name="student_id" required>
                            <option value="">-- Select Student --</option>
                            <?php while ($s = mysqli_fetch_assoc($students_list)): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo ($student_id == $s['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['reg_no']); ?> — <?php echo htmlspecialchars($s['fullname']); ?> (<?php echo htmlspecialchars($s['level']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-blue"><i class="fas fa-eye"></i> Nuna Statement</button>
                </div>
            </form>
        </div>

        <?php if ($student): ?>
        
        <!-- PRINT BUTTON -->
        <div class="no-print" style="margin-bottom:15px;">
            <button onclick="window.print()" class="btn btn-green">
                <i class="fas fa-print"></i> Print Statement of Result
            </button>
        </div>
        
        <!-- STATEMENT OF RESULT -->
        <div class="statement-container" id="statement">
            
            <!-- HEADER -->
            <div class="stmt-header">
                <div class="logo-box">
                    <img src="images/dala-logo.png" alt="Logo" onerror="this.style.display='none'">
                </div>
                <div class="center-box">
                    <div class="school-name">DALA COLLEGE OF EDUCATION, KANO</div>
                    <div class="school-loc">KANO STATE</div>
                    <div class="accreditation">Accredited By National Commission for Colleges of Education (NCCE), Abuja</div>
                </div>
                <div class="logo-box">
                    <!-- NCCE Logo space -->
                </div>
            </div>
            
            <!-- CONTACT -->
            <div class="stmt-contact">
                <span>🌐 www.dcoe.org</span>
                <span>📧 dalacollegekano@gmail.com</span>
            </div>
            
            <!-- DATE + NO -->
            <div class="stmt-meta">
                <span>Date: <?php echo date('d-M-Y'); ?></span>
                <span>No.: <?php echo htmlspecialchars($student['reg_no'] ?? 'DLC/STF/' . date('Y') . '/' . str_pad($student['id'], 3, '0', STR_PAD_LEFT)); ?></span>
            </div>
            
            <!-- EMBLEM -->
            <div class="stmt-emblem">
                <img src="images/dala-logo.png" alt="Emblem" onerror="this.style.display='none'">
            </div>
            
            <!-- TITLE -->
            <div class="stmt-title">NCE STATEMENT OF RESULT</div>
            
            <!-- STUDENT INFO -->
            <div class="stmt-info-box">
                <div class="info-left">
                    <span class="info-label">NAME:</span>
                    <span class="info-value"><?php echo strtoupper(htmlspecialchars($student['fullname'])); ?></span>
                    
                    <span class="info-label">ADM NO:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['reg_no'] ?? $student['student_id']); ?></span>
                    
                    <span class="info-label">GRAD YEAR:</span>
                    <span class="info-value"><?php echo htmlspecialchars($grad_year); ?></span>
                </div>
                <div class="photo-box">
                    <img src="uploads/students/<?php echo htmlspecialchars($student['photo'] ?? 'default.png'); ?>" 
                         alt="Student Photo"
                         onerror="this.src='https://via.placeholder.com/120x140/cccccc/333333?text=PHOTO'">
                </div>
            </div>
            
            <!-- CERTIFICATION -->
            <div class="stmt-cert">
                This is to certify that above named candidate has completed Examinations and is issued<br>
                with this Statement of Result by The Academic Board.
            </div>
            
            <!-- RESULT TABLE -->
            <table class="stmt-table">
                <thead>
                    <tr>
                        <th rowspan="2" style="width:50px;">S/N</th>
                        <th rowspan="2">SUBJECT</th>
                        <th colspan="2">GRADE SCORED</th>
                    </tr>
                    <tr>
                        <th class="sub" style="width:100px;">GPA</th>
                        <th class="sub" style="width:150px;">CLASSIFICATION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1;
                    $subject_order = ['EDU', 'CSC', 'ISC', 'GSE', 'PED', 'BIO', 'PHY', 'ENG', 'ECO', 'ARB', 'ISS', 'HAU', 'SOS'];
                    foreach ($subject_order as $cat):
                        if (!isset($category_gpas[$cat])) continue;
                        $data = $category_gpas[$cat];
                    ?>
                    <tr>
                        <td class="center bold"><?php echo $i++; ?></td>
                        <td class="bold"><?php echo getCategoryTitle($cat); ?></td>
                        <td class="center bold"><?php echo number_format($data['gpa'], 2); ?></td>
                        <td class="center bold"><?php echo getGradeClassification($data['gpa']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($category_gpas)): ?>
                    <tr>
                        <td colspan="4" class="center" style="padding:20px; color:#999;">
                            Babu sakamako da aka shigar
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- OVERALL RESULT -->
            <div class="stmt-overall">
                <div class="row">
                    <div class="label">OVERALL RESULT:</div>
                    <div class="value"><?php echo getGradeClassification($cgpa); ?></div>
                </div>
                <div class="row">
                    <div class="label">GRADE POINT:</div>
                    <div class="value"><?php echo number_format($cgpa, 2); ?></div>
                </div>
            </div>
            
            <!-- BOTTOM: QR + REGISTRAR -->
            <div class="stmt-bottom">
                <div class="qr-box">
                    <!-- QR Code - zai iya amfani da Google Chart API -->
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo urlencode('https://dala-portal.local/verify.php?reg_no=' . ($student['reg_no'] ?? $student['student_id'])); ?>" 
                         alt="QR Code">
                    <p>Scan To Verify</p>
                </div>
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <div class="sign-title">Registrar</div>
                </div>
            </div>
        </div>
        
        <?php elseif ($student_id > 0): ?>
            <div class="card">
                <p style="text-align:center; padding:40px; color:#c62828;">❌ Ba a sami dalibi ba</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>