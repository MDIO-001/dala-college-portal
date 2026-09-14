<?php
session_start();
include 'connect.php';
include 'result_functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'exam_officer'])) {
    header('Location: login.php');
    exit();
}

$admin_name = $_SESSION['fullname'] ?? 'Admin';
$message = '';

// ============================================
// DOWNLOAD CSV
// ============================================
if (isset($_GET['download_csv'])) {
    $combination = mysqli_real_escape_string($conn, $_GET['combination'] ?? '');
    $session = mysqli_real_escape_string($conn, $_GET['session'] ?? '');
    $level = mysqli_real_escape_string($conn, $_GET['level'] ?? '');
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="final_result_' . str_replace('/', '_', $combination) . '_' . str_replace('/', '_', $session) . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['DALA COLLEGE OF EDUCATION, KANO']);
    fputcsv($output, ['LIST OF GRADUATES']);
    fputcsv($output, ['Combination:', $combination]);
    fputcsv($output, ['Level:', $level]);
    fputcsv($output, ['Session:', $session]);
    fputcsv($output, []);
    fputcsv($output, ['S/N', 'MATRIC NO', 'NAMES IN FULL', 'TP GRADE', 'EDU', 'ARB', 'ISS', 'GSE', 'SESSION']);
    
    $students = mysqli_query($conn, "SELECT * FROM students 
                                     WHERE (combination = '$combination' OR course = '$combination') 
                                     AND level = '$level' 
                                     AND status IN ('active', 'approved', 'graduated')
                                     ORDER BY fullname");
    
    $sn = 1;
    while ($s = mysqli_fetch_assoc($students)) {
        $cgpa = getStudentCGPA($conn, $s['id']);
        $class = getGradeClassification($cgpa);
        
        fputcsv($output, [
            $sn++,
            $s['reg_no'] ?? $s['student_id'],
            strtoupper($s['fullname']),
            $class,
            number_format($cgpa, 2),
            '-', '-', '-',
            $session
        ]);
    }
    
    fclose($output);
    exit();
}

// ============================================
// GET STUDENTS
// ============================================
$combination = isset($_GET['combination']) ? mysqli_real_escape_string($conn, $_GET['combination']) : '';
$session = isset($_GET['session']) ? mysqli_real_escape_string($conn, $_GET['session']) : getSetting($conn, 'current_session');
$level = isset($_GET['level']) ? mysqli_real_escape_string($conn, $_GET['level']) : 'NCE III';

$students = [];
if (!empty($combination)) {
    $sq = mysqli_query($conn, "SELECT * FROM students 
                               WHERE (combination = '$combination' OR course = '$combination') 
                               AND level = '$level' 
                               AND status IN ('active', 'approved', 'graduated')
                               ORDER BY fullname");
    while ($s = mysqli_fetch_assoc($sq)) {
        $s['cgpa'] = getStudentCGPA($conn, $s['id']);
        $s['class'] = getGradeClassification($s['cgpa']);
        $s['category_gpas'] = getCategoryGPAs($conn, $s['id']);
        $students[] = $s;
    }
}

// Get combinations
$combinations_list = [];
$cq = mysqli_query($conn, "SELECT DISTINCT combination FROM courses WHERE combination IS NOT NULL AND combination != '' ORDER BY combination");
while ($c = mysqli_fetch_assoc($cq)) $combinations_list[] = $c['combination'];

$session_options = getSessionOptions();
$level_options = getLevelOptions();

// Categories to show
$show_categories = ['EDU', 'ARB', 'ISS', 'GSE'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Final Result - List of Graduates</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Tahoma,sans-serif; background:#f0f4f8; padding:20px; }
        .container { max-width:1400px; margin:0 auto; }
        
        .topbar { background:#0d2818; padding:15px 25px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; border-radius:12px; margin-bottom:20px; }
        .topbar .logo-title { color:#ffd54f; font-size:1.3rem; font-weight:800; }
        .topbar .logo-title span { color:#a5d6a7; }
        .topbar .logo-sub { display:block; font-size:0.55rem; color:#c8e6c9; }
        .topbar nav a { color:#c8e6c9; text-decoration:none; padding:8px 16px; border-radius:25px; font-size:0.85rem; }
        .topbar nav a:hover { background:#2e7d32; }
        .topbar nav .logout { background:#c62828; color:white !important; }
        .topbar .admin-badge { background:#c62828; color:white; padding:6px 18px; border-radius:20px; font-size:0.8rem; font-weight:600; }
        
        .result-nav { background:white; padding:15px; border-radius:12px; margin-bottom:20px; display:flex; gap:8px; flex-wrap:wrap; align-items:center; box-shadow:0 2px 10px rgba(0,0,0,0.05); }
        .result-nav .label { font-weight:700; color:#0d2818; margin-right:10px; font-size:0.85rem; }
        .result-nav a { padding:8px 15px; border-radius:6px; text-decoration:none; font-weight:600; font-size:0.75rem; }
        .r-course { background:#2e7d32; color:white; }
        .r-grade { background:#f9a825; color:#0d2818; }
        .r-entry { background:#8d2c2c; color:white; }
        .r-slip { background:#e53935; color:white; }
        .r-slip-pro { background:#a5d6a7; color:#0d2818; }
        .r-transcript { background:#5d4037; color:white; }
        .r-final { background:#558b2f; color:white; box-shadow:0 0 0 3px #ffd54f; }
        .r-settings { background:#e65100; color:white; }
        .r-database { background:#37474f; color:white; }
        .r-statement { background:#455a64; color:white; }
        
        .card { background:white; padding:25px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); margin-bottom:20px; }
        .card h2 { color:#0d2818; margin-bottom:15px; font-size:1.2rem; }
        
        .form-row { display:grid; grid-template-columns:repeat(4,1fr); gap:15px; margin-bottom:15px; }
        .form-group label { display:block; font-weight:600; color:#1a2e1a; margin-bottom:5px; font-size:0.85rem; }
        .form-group select, .form-group input { width:100%; padding:10px; border:2px solid #dce8dc; border-radius:8px; font-size:0.9rem; }
        
        .btn { padding:10px 25px; border:none; border-radius:8px; font-weight:700; font-size:0.9rem; cursor:pointer; text-decoration:none; display:inline-block; margin-right:8px; }
        .btn-green { background:#2e7d32; color:white; }
        .btn-green:hover { background:#1b5e20; }
        .btn-blue { background:#1976d2; color:white; }
        .btn-blue:hover { background:#0d47a1; }
        .btn-orange { background:#f57c00; color:white; }
        .btn-orange:hover { background:#e65100; }
        
        /* ============================================ */
        /* FINAL RESULT DESIGN */
        /* ============================================ */
        .final-container {
            background: white;
            padding: 25px;
            border: 2px solid #0d2818;
            font-family: 'Times New Roman', serif;
            position: relative;
            overflow: hidden;
        }
        
        .final-container::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 600px;
            height: 600px;
            background-image: url('images/dala-logo.png');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            opacity: 0.04;
            z-index: 0;
            pointer-events: none;
        }
        
        .final-container > * { position: relative; z-index: 1; }
        
        .final-header {
            display: grid;
            grid-template-columns: 100px 1fr 100px;
            gap: 15px;
            align-items: center;
            padding-bottom: 12px;
            border-bottom: 3px double #0d2818;
            margin-bottom: 10px;
        }
        .final-header .logo-box { width: 100px; height: 100px; }
        .final-header .logo-box img { width: 100%; height: 100%; object-fit: contain; }
        .final-header .center-box { text-align: center; }
        .final-header .school-name { font-size: 1.5rem; font-weight: 900; color: #0d2818; letter-spacing: 1px; }
        .final-header .school-loc { font-size: 0.85rem; font-weight: 700; color: #0d2818; margin: 3px 0; }
        .final-header .accreditation { font-size: 0.75rem; color: #c62828; font-weight: 700; padding: 3px 0; }
        .final-header .contact { font-size: 0.7rem; color: #0d2818; font-weight: 600; }
        
        .final-title {
            text-align: center;
            font-size: 1.1rem;
            font-weight: 900;
            color: #0d2818;
            letter-spacing: 2px;
            margin: 10px 0;
            text-transform: uppercase;
            padding: 8px;
            background: #f8faf8;
            border-top: 2px solid #0d2818;
            border-bottom: 2px solid #0d2818;
        }
        
        .final-subtitle {
            text-align: center;
            font-size: 0.9rem;
            font-weight: 700;
            color: #0d2818;
            margin-bottom: 15px;
        }
        
        /* TABLE */
        .final-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
            margin-bottom: 20px;
        }
        .final-table th {
            background: #0d2818;
            color: white;
            padding: 6px 4px;
            text-align: center;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            border: 1px solid #0d2818;
        }
        .final-table th.main {
            background: #1b5e20;
            font-size: 0.7rem;
        }
        .final-table td {
            padding: 5px 4px;
            border: 1px solid #ccc;
            text-align: center;
            font-size: 0.75rem;
        }
        .final-table td.name-col {
            text-align: left;
            font-weight: 700;
            text-transform: uppercase;
        }
        .final-table tbody tr:nth-child(even) td {
            background: #f8faf8;
        }
        .final-table tbody tr:hover td {
            background: #e8f5e9;
        }
        
        .tp-distinction { color: #2e7d32; font-weight: 900; }
        .tp-credit { color: #0d47a1; font-weight: 900; }
        .tp-merit { color: #e65100; font-weight: 900; }
        .tp-pass { color: #6a1b9a; font-weight: 900; }
        .tp-fail { color: #c62828; font-weight: 900; }
        
        /* ATTESTATION */
        .attestation {
            margin-top: 25px;
            padding-top: 15px;
            border-top: 2px solid #0d2818;
        }
        .attestation h4 {
            text-align: center;
            font-size: 0.95rem;
            font-weight: 900;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .attestation p {
            font-size: 0.8rem;
            text-align: justify;
            margin-bottom: 15px;
            line-height: 1.4;
        }
        .attestation .sign-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        .attestation .sign-box {
            text-align: center;
        }
        .attestation .sign-line {
            border-top: 1px solid #0d2818;
            margin-top: 40px;
            padding-top: 5px;
            font-weight: 700;
            font-size: 0.8rem;
        }
        .attestation .sign-title {
            font-size: 0.75rem;
            font-weight: 700;
            color: #0d2818;
        }
        
        .page-info {
            text-align: center;
            font-size: 0.75rem;
            font-weight: 700;
            color: #0d2818;
            margin-top: 15px;
        }
        
        .empty-state { text-align:center; padding:40px; color:#6a8f6a; }
        .empty-state i { font-size:3rem; color:#dce8dc; display:block; margin-bottom:15px; }
        
        /* PRINT - A4 LANDSCAPE */
        @media print {
            .topbar, .result-nav, .card, .btn, .no-print { display: none !important; }
            body { background: white; padding: 0; margin: 0; font-size: 10px; }
            .container { max-width: 100%; padding: 0; margin: 0; }
            .final-container { border: 2px solid #000; margin: 0; padding: 10mm; width: 100%; }
            .final-container::before { opacity: 0.06; }
            
            .final-table { page-break-inside: auto; }
            .final-table tr { page-break-inside: avoid; }
            
            @page { 
                size: A4 landscape; 
                margin: 5mm;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="topbar no-print">
            <div>
                <span class="logo-title">DALA <span>COLLEGE</span></span>
                <span class="logo-sub">Admin Panel</span>
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

        <h2 class="no-print" style="color:#0d2818; margin-bottom:15px;">🎓 Final Result — List of Graduates</h2>

        <?php if ($message): ?>
            <div class="message no-print" style="padding:12px 20px; border-radius:8px; background:#e8f5e9; color:#2e7d32; margin-bottom:15px;"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- SELECT -->
        <div class="card no-print">
            <h2>🎓 Select Combination, Level, and Session</h2>
            <form method="GET">
                <div class="form-row">
                    <div class="form-group">
                        <label>Combination *</label>
                        <select name="combination" required>
                            <option value="">-- Select --</option>
                            <?php foreach ($combinations_list as $c): ?>
                                <option value="<?php echo $c; ?>" <?php echo ($combination == $c) ? 'selected' : ''; ?>><?php echo $c; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Level *</label>
                        <select name="level" required>
                            <?php foreach ($level_options as $lvl): ?>
                                <option value="<?php echo $lvl; ?>" <?php echo ($level == $lvl) ? 'selected' : ''; ?>><?php echo $lvl; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Session *</label>
                        <select name="session" required>
                            <?php foreach ($session_options as $sess): ?>
                                <option value="<?php echo $sess; ?>" <?php echo ($session == $sess) ? 'selected' : ''; ?>><?php echo $sess; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex; align-items:flex-end;">
                        <button type="submit" class="btn btn-blue"><i class="fas fa-search"></i> Show Final Result</button>
                    </div>
                </div>
            </form>
        </div>

        <?php if (!empty($combination) && !empty($students)): ?>
        
        <!-- ACTION BUTTONS -->
        <div class="no-print" style="margin-bottom:15px; display:flex; flex-wrap:wrap; gap:8px;">
            <button onclick="window.print()" class="btn btn-green">
                <i class="fas fa-print"></i> Print List
            </button>
            <a href="?combination=<?php echo urlencode($combination); ?>&level=<?php echo urlencode($level); ?>&session=<?php echo urlencode($session); ?>&download_csv=1" 
               class="btn btn-orange">
                <i class="fas fa-download"></i> Download CSV
            </a>
        </div>
        
        <!-- FINAL RESULT CONTAINER -->
        <div class="final-container">
            
            <!-- HEADER -->
            <div class="final-header">
                <div class="logo-box">
                    <img src="images/dala-logo.png" alt="Logo" onerror="this.style.display='none'">
                </div>
                <div class="center-box">
                    <div class="school-name">DALA COLLEGE OF EDUCATION KANO</div>
                    <div class="school-loc">ACCREDITED BY NATIONAL COMMISSION FOR COLLEGES OF EDUCATION (NCCE), ABUJA</div>
                    <div class="contact">WEBSITE: www.dalacollege.edu.ng | E-Mail: dalacollegekano@gmail.com | G.S.M. NO: 08037409185</div>
                    <div class="accreditation">(ACADEMIC AFFAIRS DIVISION)</div>
                </div>
                <div class="logo-box"></div>
            </div>
            
            <!-- TITLE -->
            <div class="final-title">LIST OF GRADUATES FOR <?php echo strtoupper($session); ?> ACADEMIC SESSION</div>
            <div class="final-subtitle">
                <?php echo htmlspecialchars($combination); ?> — <?php echo htmlspecialchars($level); ?>
            </div>
            
            <!-- TABLE -->
            <table class="final-table">
                <thead>
                    <tr>
                        <th rowspan="2" style="width:35px;">S/N</th>
                        <th rowspan="2" style="width:90px;">MATRIC NO.</th>
                        <th rowspan="2" style="width:200px;">NAMES IN FULL</th>
                        <th rowspan="2" style="width:80px;">TP GRADE</th>
                        <?php foreach ($show_categories as $cat): ?>
                            <th colspan="3" class="main"><?php echo $cat; ?></th>
                        <?php endforeach; ?>
                        <th rowspan="2" style="width:70px;">SESSION</th>
                    </tr>
                    <tr>
                        <?php foreach ($show_categories as $cat): ?>
                            <th style="width:45px;">TCUP</th>
                            <th style="width:45px;">CGPA</th>
                            <th style="width:60px;">GRADE</th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $sn = 1; foreach ($students as $s): 
                        $class_class = 'tp-pass';
                        if ($s['class'] == 'Distinction') $class_class = 'tp-distinction';
                        elseif ($s['class'] == 'Upper Credit' || $s['class'] == 'Credit') $class_class = 'tp-credit';
                        elseif ($s['class'] == 'Lower Credit' || $s['class'] == 'Merit') $class_class = 'tp-merit';
                    ?>
                    <tr>
                        <td><?php echo $sn++; ?></td>
                        <td><?php echo htmlspecialchars($s['reg_no'] ?? $s['student_id']); ?></td>
                        <td class="name-col"><?php echo strtoupper(htmlspecialchars($s['fullname'])); ?></td>
                        <td class="<?php echo $class_class; ?>"><?php echo strtoupper($s['class']); ?></td>
                        
                        <?php foreach ($show_categories as $cat): 
                            $cat_data = $s['category_gpas'][$cat] ?? null;
                            if ($cat_data) {
                                $cat_gpa = $cat_data['gpa'];
                                $cat_units = $cat_data['units'];
                                $cat_class = getGradeClassification($cat_gpa);
                            } else {
                                $cat_gpa = 0;
                                $cat_units = 0;
                                $cat_class = '—';
                            }
                        ?>
                            <td><?php echo $cat_units > 0 ? $cat_units : '—'; ?></td>
                            <td><?php echo $cat_units > 0 ? number_format($cat_gpa, 2) : '—'; ?></td>
                            <td><?php echo $cat_units > 0 ? strtoupper($cat_class) : '—'; ?></td>
                        <?php endforeach; ?>
                        
                        <td><?php echo htmlspecialchars($session); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- ATTESTATION -->
            <div class="attestation">
                <h4>ATTESTATION</h4>
                <p>
                    We the undersigned attest to it that results reflected in this page(s) are the authentic final results 
                    of the Candidates for the award of NCE Certificate for the Year <?php echo htmlspecialchars($session); ?>
                </p>
                
                <div class="sign-grid">
                    <div class="sign-box">
                        <div class="sign-line">ABDULMUMIN MUSA</div>
                        <div class="sign-title">(a) Dean of School</div>
                    </div>
                    <div class="sign-box">
                        <div class="sign-line">MUSA SULEIMAN</div>
                        <div class="sign-title">(b) Academic Secretary</div>
                    </div>
                    <div class="sign-box">
                        <div class="sign-line">DR. UMAR ISAH</div>
                        <div class="sign-title">(c) Provost</div>
                    </div>
                </div>
            </div>
            
            <!-- PAGE INFO -->
            <div class="page-info">1 | Page</div>
        </div>
        
        <?php elseif (!empty($combination)): ?>
            <div class="card">
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No students found</h3>
                    <p>No students found for <?php echo htmlspecialchars($combination); ?> — <?php echo htmlspecialchars($level); ?>.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>