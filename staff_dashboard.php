<?php
session_start();
include 'connect.php';
include 'result_functions.php';

// ============================================
// SESSION CHECK
// ============================================
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_role = $_SESSION['role'] ?? '';
$allowed_roles = ['staff', 'Provost', 'Exam Officer', 'Accountant', 'Bursary', 'Admission Officer', 'admin'];

if (!in_array($user_role, $allowed_roles)) {
    header('Location: login.php');
    exit();
}

$staff_id = $_SESSION['user_id'];
$staff_name = $_SESSION['fullname'] ?? 'Staff';
$staff_position = $_SESSION['position'] ?? 'Staff';

// ============================================
// FETCH STAFF DATA
// ============================================
$staff_query = "SELECT * FROM staff WHERE id = '$staff_id'";
$staff_result = mysqli_query($conn, $staff_query);
$staff = mysqli_fetch_assoc($staff_result);

if (!$staff) {
    echo "Staff not found!";
    exit();
}

$position = $staff['position'] ?? $staff['role'] ?? '';

// ============================================
// ROLE CHECKS
// ============================================
$is_exam_officer = ($user_role == 'Exam Officer') || (strtolower($position) == 'exam officer');
$is_provost = ($user_role == 'Provost') || (strtolower($position) == 'provost');
$is_admin = ($user_role == 'admin');
$is_accountant = ($user_role == 'Accountant');
$is_bursary = ($user_role == 'Bursary');
$is_admission_officer = ($user_role == 'Admission Officer');

// ============================================
// RESULT SYSTEM PERMISSIONS
// ============================================
$has_result_access = ($is_exam_officer || $is_provost || $is_admin);

// ============================================
// PAYMENT ACCESS PERMISSIONS
// ============================================
$has_payment_access = ($is_admin || $is_provost || $is_accountant);

// Accept/Reject Permission
$can_accept = (
    ($staff['can_accept'] ?? '') == 'yes' 
    || $is_provost 
    || $is_admission_officer
    || $is_admin
);

// ============================================
// HANDLE ACCEPT/REJECT
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $can_accept) {
    $app_id = mysqli_real_escape_string($conn, $_POST['application_id']);
    $action = mysqli_real_escape_string($conn, $_POST['action']);
    $comment = mysqli_real_escape_string($conn, trim($_POST['comment']));
    
    $get = "SELECT * FROM applications WHERE id = '$app_id'";
    $res = mysqli_query($conn, $get);
    
    if ($res && $row = mysqli_fetch_assoc($res)) {
        $email = $row['email'];
        $programme = $row['programme'] ?? 'NCE';
        $course = $row['course_applied'];
        $branch_code = $row['branch_code'] ?? 'SHINGE';
        
        $admission_no = '';
        if ($action == 'approved') {
            $centre_codes = ['SHINGE' => 'A', 'SABUWA' => 'B', 'TUDUN' => 'C'];
            $centre_code = $centre_codes[$branch_code] ?? 'A';
            
            $dept_codes = [
                'ARB/ISS' => 'ARI', 'ENG/ISS' => 'ENG', 'PED' => 'PED',
                'HAU/ENG' => 'HAU', 'CSC/ISC' => 'CSC', 'ENG/SOS' => 'SOC',
                'CSC/BIO' => 'BIO', 'CSC/PHY' => 'PHY', 'ENG/ECO' => 'ECO'
            ];
            $dept_code = $dept_codes[$course] ?? 'GEN';
            
            if ($programme == 'DEGREE') $prog_code = 'DEG';
            elseif ($programme == 'ENTREPRENEURSHIP') $prog_code = 'ENT';
            else $prog_code = 'NCE';
            
            $year = date('y');
            $count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM students"))['total'] + 1;
            $next_num = str_pad($count, 3, '0', STR_PAD_LEFT);
            
            $admission_no = "DLCOE/{$prog_code}/{$year}{$centre_code}{$next_num}/{$dept_code}{$next_num}";
        }
        
        mysqli_query($conn, "UPDATE applications SET status = '$action', notes = '$comment', reviewed_by = '$staff_name ($position)', reviewed_at = NOW() WHERE id = '$app_id'");
        
        if ($action == 'approved') {
            $level = getLevelByProgramme($programme);
            mysqli_query($conn, "UPDATE students SET status = 'active', reg_no = '$admission_no', student_id = '$admission_no', combination = '$course', level = '$level' WHERE email = '$email'");
        } else {
            mysqli_query($conn, "UPDATE students SET status = '$action' WHERE email = '$email'");
        }
        
        $success = "✅ Application $action successfully!";
        if ($action == 'approved') $success .= "<br>🎓 Admission Number: <strong>$admission_no</strong>";
    }
}

// ============================================
// STATS
// ============================================
$total_students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students"))['c'];
$total_staff = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM staff"))['c'];
$total_courses = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM courses"))['c'];
$total_results = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM results"))['c'];
$total_applications = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM applications"))['c'];
$pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM applications WHERE status = 'pending'"))['c'];
$approved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM applications WHERE status = 'approved'"))['c'];
$rejected = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM applications WHERE status = 'rejected'"))['c'];

// Payment stats
$total_payments = 0;
$total_payment_amount = 0;
$payment_check = mysqli_query($conn, "SHOW TABLES LIKE 'payments'");
if (mysqli_num_rows($payment_check) > 0) {
    $total_payments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM payments"))['c'];
    $total_payment_amount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as s FROM payments"))['s'] ?? 0;
}

// ============================================
// FETCH APPLICATIONS
// ============================================
$applications = null;
if (!$has_result_access || $is_admin || $is_provost) {
    $applications = mysqli_query($conn, "SELECT * FROM applications ORDER BY id DESC");
}

date_default_timezone_set('Africa/Lagos');
$current_date = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Dala College</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Tahoma,sans-serif; background:#f0f4f8; color:#1a2e1a; min-height:100vh; }
        
        .topbar { background:#0d2818; padding:15px 30px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; box-shadow:0 2px 15px rgba(0,0,0,0.2); position:sticky; top:0; z-index:100; }
        .topbar .logo-title { color:#ffd54f; font-size:1.3rem; font-weight:800; }
        .topbar .logo-title span { color:#a5d6a7; }
        .topbar .logo-sub { display:block; font-size:0.6rem; color:#c8e6c9; }
        .topbar nav { display:flex; gap:5px; flex-wrap:wrap; }
        .topbar nav a { color:#c8e6c9; text-decoration:none; padding:8px 14px; border-radius:25px; transition:all 0.3s ease; font-weight:500; font-size:0.82rem; white-space:nowrap; }
        .topbar nav a:hover { background:#2e7d32; color:white; }
        .topbar nav a.active { background:#ffd54f; color:#0d2818 !important; font-weight:700; }
        .topbar nav a.logout { background:#c62828; color:white !important; }
        .topbar nav a.logout:hover { background:#b71c1c; }
        
        .container { max-width:1400px; margin:20px auto; padding:0 20px; }
        
        .welcome-section { background:linear-gradient(135deg, #1b4d2e, #2e7d32); padding:25px 30px; border-radius:16px; color:white; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; margin-bottom:25px; box-shadow:0 8px 25px rgba(46,125,50,0.3); }
        .welcome-section .greeting h2 { font-size:1.5rem; font-weight:700; }
        .welcome-section .greeting h2 span { color:#ffd54f; }
        .welcome-section .greeting p { color:#c8e6c9; font-size:0.9rem; }
        .welcome-section .staff-badge { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
        .welcome-section .staff-badge .role-tag { background:rgba(255,255,255,0.15); padding:6px 18px; border-radius:50px; font-size:0.8rem; border:1px solid rgba(255,255,255,0.2); }
        .welcome-section .staff-badge .exam-tag { background:#ffd54f; color:#0d2818; padding:6px 18px; border-radius:50px; font-size:0.8rem; font-weight:800; }
        .welcome-section .staff-badge .accept-tag { padding:6px 18px; border-radius:50px; font-size:0.8rem; font-weight:600; }
        .welcome-section .staff-badge .accept-tag.yes { background:#2e7d32; color:white; }
        .welcome-section .staff-badge .accept-tag.no { background:#c62828; color:white; }
        .welcome-section .staff-badge .pending-badge { background:#ffa000; color:white; padding:6px 18px; border-radius:50px; font-size:0.8rem; font-weight:600; }
        
        .stats-grid { display:grid; grid-template-columns:repeat(6, 1fr); gap:15px; margin-bottom:25px; }
        .stat-card { background:white; padding:18px; border-radius:12px; text-align:center; box-shadow:0 2px 10px rgba(0,0,0,0.05); cursor:pointer; transition:all 0.3s ease; border-top:4px solid #2e7d32; text-decoration:none; color:inherit; display:block; }
        .stat-card:hover { transform:translateY(-4px); box-shadow:0 8px 25px rgba(0,0,0,0.1); }
        .stat-card .number { font-size:2rem; font-weight:800; color:#0d2818; }
        .stat-card .label { font-size:0.75rem; color:#6a8f6a; margin-top:2px; }
        .stat-card .icon { font-size:1.5rem; display:block; margin-bottom:5px; }
        .stat-card.pending { border-top-color:#ffa000; }
        .stat-card.pending .number { color:#ffa000; }
        .stat-card.approved { border-top-color:#2e7d32; }
        .stat-card.approved .number { color:#2e7d32; }
        .stat-card.rejected { border-top-color:#c62828; }
        .stat-card.rejected .number { color:#c62828; }
        .stat-card.blue { border-top-color:#1976d2; }
        .stat-card.blue .number { color:#1976d2; }
        .stat-card.purple { border-top-color:#7b1fa2; }
        .stat-card.purple .number { color:#7b1fa2; }
        .stat-card.orange { border-top-color:#f57c00; }
        .stat-card.orange .number { color:#f57c00; }
        
        .result-nav { background:white; padding:12px 15px; border-radius:12px; margin-bottom:20px; display:flex; gap:6px; flex-wrap:wrap; align-items:center; box-shadow:0 2px 10px rgba(0,0,0,0.05); }
        .result-nav .label { font-weight:700; color:#0d2818; margin-right:10px; font-size:0.8rem; flex-shrink:0; }
        .result-nav a { padding:7px 12px; border-radius:6px; text-decoration:none; font-weight:600; font-size:0.68rem; white-space:nowrap; flex-shrink:0; transition:all 0.3s ease; }
        .result-nav a:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,0.15); }
        .r-course { background:#2e7d32; color:white; }
        .r-grade { background:#f9a825; color:#0d2818; }
        .r-entry { background:#8d2c2c; color:white; }
        .r-slip { background:#e53935; color:white; }
        .r-slip-pro { background:#a5d6a7; color:#0d2818; }
        .r-transcript { background:#5d4037; color:white; }
        .r-database { background:#37474f; color:white; }
        .r-final { background:#00695c; color:white; }
        .r-settings { background:#e65100; color:white; }
        .r-statement { background:#4a148c; color:white; }
        .r-payments { background:#f57c00; color:white; }
        
        .card { background:white; padding:25px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); margin-bottom:20px; }
        .card h3 { color:#0d2818; margin-bottom:15px; font-size:1.1rem; }
        
        .quick-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:15px; }
        .quick-card { background:#f8faf8; padding:20px; border-radius:12px; text-align:center; text-decoration:none; color:#0d2818; border:2px solid #e8f5e9; transition:all 0.3s ease; display:block; }
        .quick-card:hover { transform:translateY(-5px); border-color:#2e7d32; box-shadow:0 8px 20px rgba(46,125,50,0.15); }
        .quick-card i { font-size:2.2rem; margin-bottom:10px; display:block; }
        .quick-card strong { display:block; font-size:0.9rem; margin-bottom:5px; }
        .quick-card small { color:#6a8f6a; font-size:0.75rem; }
        
        .quick-card.entry i { color:#8d2c2c; }
        .quick-card.slip-pro i { color:#2e7d32; }
        .quick-card.slip i { color:#e53935; }
        .quick-card.courses i { color:#2e7d32; }
        .quick-card.database i { color:#37474f; }
        .quick-card.payments i { color:#f57c00; }
        
        .alert-success { background:#e8f5e9; color:#2e7d32; padding:15px 20px; border-radius:12px; margin-bottom:20px; border-left:4px solid #2e7d32; }
        
        .table-section { background:white; padding:25px 30px; border-radius:16px; box-shadow:0 2px 12px rgba(0,0,0,0.06); overflow-x:auto; margin-bottom:25px; }
        .table-section .table-header { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; margin-bottom:18px; }
        .table-section .table-header h3 { font-size:1.2rem; color:#0d2818; }
        .table-section .table-header h3 i { color:#2e7d32; margin-right:8px; }
        .table-section table { width:100%; border-collapse:collapse; font-size:0.85rem; min-width:900px; }
        .table-section table th { background:#f8faf8; color:#0d2818; padding:10px 12px; text-align:left; font-weight:600; border-bottom:2px solid #e8f0e8; white-space:nowrap; }
        .table-section table td { padding:10px 12px; border-bottom:1px solid #f0f4f8; vertical-align:middle; }
        .table-section table tr:hover td { background:#f8faf8; }
        
        .status-badge { display:inline-block; padding:4px 14px; border-radius:20px; font-size:0.75rem; font-weight:600; }
        .status-badge.pending { background:#fff8e1; color:#ffa000; }
        .status-badge.approved { background:#e8f5e9; color:#2e7d32; }
        .status-badge.rejected { background:#ffebee; color:#c62828; }
        
        .admission-no { font-weight:700; color:#0d2818; font-size:0.75rem; background:#e8f5e9; padding:2px 10px; border-radius:12px; }
        
        .action-form { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
        .action-form input[type="text"] { padding:6px 10px; border:1px solid #dce8dc; border-radius:6px; font-size:0.78rem; min-width:100px; }
        .action-form .btn-accept { padding:6px 16px; background:#2e7d32; color:white; border:none; border-radius:6px; font-size:0.75rem; font-weight:600; cursor:pointer; }
        .action-form .btn-reject { padding:6px 16px; background:#c62828; color:white; border:none; border-radius:6px; font-size:0.75rem; font-weight:600; cursor:pointer; }
        
        .no-applications { text-align:center; padding:40px; color:#6a8f6a; }
        .no-applications i { font-size:3rem; display:block; margin-bottom:10px; color:#dce8dc; }
        
        @media (max-width:1024px) { .stats-grid { grid-template-columns:repeat(3, 1fr); } }
        @media (max-width:768px) {
            .topbar { flex-direction:column; gap:10px; text-align:center; }
            .topbar nav a { font-size:0.75rem; padding:6px 10px; }
            .welcome-section { flex-direction:column; text-align:center; gap:12px; padding:20px; }
            .stats-grid { grid-template-columns:1fr 1fr; }
            .table-section { padding:15px; }
        }
    </style>
</head>
<body>

    <!-- ============================================ -->
    <!-- TOPBAR -->
    <!-- ============================================ -->
    <div class="topbar">
        <div>
            <span class="logo-title">DALA <span>COLLEGE</span></span>
            <span class="logo-sub">of Education, Kano</span>
        </div>
        <nav>
            <a href="staff_dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
            <?php if ($is_admin): ?>
                <a href="admin_result_entry.php"><i class="fas fa-edit"></i> Result Entry</a>
                <a href="admin_result_slip.php"><i class="fas fa-file-invoice"></i> Result Slip</a>
                <a href="admin_result_slip_pro.php"><i class="fas fa-file-alt"></i> Result Slip Pro</a>
                <a href="admin_payments.php"><i class="fas fa-money-bill-wave"></i> Payments</a>
            <?php elseif ($is_exam_officer): ?>
                <a href="admin_result_entry.php"><i class="fas fa-edit"></i> Result Entry</a>
                <a href="admin_result_slip.php"><i class="fas fa-file-invoice"></i> Result Slip</a>
                <a href="admin_result_slip_pro.php"><i class="fas fa-file-alt"></i> Result Slip Pro</a>
            <?php elseif ($is_provost): ?>
                <a href="admin_result_slip.php"><i class="fas fa-file-invoice"></i> Result Slip</a>
                <a href="admin_result_slip_pro.php"><i class="fas fa-file-alt"></i> Result Slip Pro</a>
                <a href="admin_payments.php"><i class="fas fa-money-bill-wave"></i> Payments</a>
            <?php elseif ($is_accountant): ?>
                <a href="admin_payments.php"><i class="fas fa-money-bill-wave"></i> Payments</a>
            <?php else: ?>
                <a href="view_students.php"><i class="fas fa-users"></i> Students</a>
                <a href="manage_courses.php"><i class="fas fa-book"></i> Courses</a>
                <a href="view_results.php"><i class="fas fa-chart-bar"></i> Results</a>
            <?php endif; ?>
            <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="container">
        
        <!-- ============================================ -->
        <!-- WELCOME SECTION -->
        <!-- ============================================ -->
        <div class="welcome-section">
            <div class="greeting">
                <h2>👋 Welcome, <span><?php echo htmlspecialchars($staff_name); ?></span></h2>
                <p><i class="fas fa-calendar-alt"></i> <?php echo $current_date; ?></p>
            </div>
            <div class="staff-badge">
                <span class="role-tag"><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($position); ?></span>
                <?php if ($is_exam_officer): ?>
                    <span class="exam-tag"><i class="fas fa-graduation-cap"></i> EXAM OFFICER</span>
                <?php elseif ($is_provost): ?>
                    <span class="exam-tag" style="background:#ffd54f;"><i class="fas fa-crown"></i> PROVOST</span>
                <?php elseif ($is_admin): ?>
                    <span class="exam-tag" style="background:#1976d2; color:white;"><i class="fas fa-shield-alt"></i> ADMIN</span>
                <?php elseif ($is_accountant): ?>
                    <span class="exam-tag" style="background:#f57c00; color:white;"><i class="fas fa-calculator"></i> ACCOUNTANT</span>
                <?php elseif ($can_accept): ?>
                    <span class="accept-tag yes"><i class="fas fa-check-circle"></i> Can Accept</span>
                <?php else: ?>
                    <span class="accept-tag no"><i class="fas fa-eye"></i> View Only</span>
                <?php endif; ?>
                <?php if ($pending > 0 && !$has_result_access && !$has_payment_access): ?>
                    <span class="pending-badge"><i class="fas fa-clock"></i> <?php echo $pending; ?> Pending</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>

        <!-- ============================================ -->
        <!-- RESULT ACCESS SECTION -->
        <!-- ============================================ -->
        <?php if ($has_result_access): ?>
        
        <!-- Result System Navigation -->
        <div class="result-nav">
            <span class="label">📊 RESULT SYSTEM:</span>
            
            <?php if ($is_admin): ?>
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
            <?php elseif ($is_exam_officer): ?>
                <a href="admin_result_entry.php" class="r-entry">RESULT_ENTRY</a>
                <a href="admin_result_slip.php" class="r-slip">RESULT_SLIP</a>
                <a href="admin_result_slip_pro.php" class="r-slip-pro">RESULT_SLIP_PRO</a>
            <?php elseif ($is_provost): ?>
                <a href="admin_result_slip.php" class="r-slip">RESULT_SLIP</a>
                <a href="admin_result_slip_pro.php" class="r-slip-pro">RESULT_SLIP_PRO</a>
            <?php endif; ?>
            
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <a href="view_students.php" class="stat-card">
                <span class="icon">🎓</span>
                <div class="number"><?php echo $total_students; ?></div>
                <div class="label">Total Students</div>
            </a>
            <a href="view_staff.php" class="stat-card blue">
                <span class="icon">👥</span>
                <div class="number"><?php echo $total_staff; ?></div>
                <div class="label">Total Staff</div>
            </a>
            <a href="admin_course_structure.php" class="stat-card purple">
                <span class="icon">📚</span>
                <div class="number"><?php echo $total_courses; ?></div>
                <div class="label">Total Courses</div>
            </a>
            <a href="admin_result_database.php" class="stat-card blue">
                <span class="icon">📊</span>
                <div class="number"><?php echo $total_results; ?></div>
                <div class="label">Total Results</div>
            </a>
            <?php if ($has_payment_access): ?>
            <a href="admin_payments.php" class="stat-card orange">
                <span class="icon">💰</span>
                <div class="number"><?php echo $total_payments; ?></div>
                <div class="label">Total Payments</div>
            </a>
            <?php else: ?>
            <a href="view_applications.php" class="stat-card pending">
                <span class="icon">⏳</span>
                <div class="number"><?php echo $pending; ?></div>
                <div class="label">Pending Applications</div>
            </a>
            <?php endif; ?>
            <a href="view_applications.php" class="stat-card approved">
                <span class="icon">✅</span>
                <div class="number"><?php echo $approved; ?></div>
                <div class="label">Approved</div>
            </a>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <h3><i class="fas fa-bolt" style="color:#f9a825;"></i> Quick Actions</h3>
            <div class="quick-grid">
                
                <?php if ($is_admin): ?>
                    <a href="admin_result_entry.php" class="quick-card entry">
                        <i class="fas fa-edit"></i>
                        <strong>Result Entry</strong>
                        <small>Enter scores</small>
                    </a>
                    <a href="admin_result_slip_pro.php" class="quick-card slip-pro">
                        <i class="fas fa-file-alt"></i>
                        <strong>Result Slip Pro</strong>
                        <small>Print result slip</small>
                    </a>
                    <a href="admin_result_slip.php" class="quick-card slip">
                        <i class="fas fa-file-invoice"></i>
                        <strong>Result Slip</strong>
                        <small>Simple slip</small>
                    </a>
                    <a href="admin_course_structure.php" class="quick-card courses">
                        <i class="fas fa-book"></i>
                        <strong>Course Structure</strong>
                        <small>Manage courses</small>
                    </a>
                    <a href="admin_result_database.php" class="quick-card database">
                        <i class="fas fa-database"></i>
                        <strong>Result Database</strong>
                        <small>All results</small>
                    </a>
                <?php elseif ($is_exam_officer): ?>
                    <a href="admin_result_entry.php" class="quick-card entry">
                        <i class="fas fa-edit"></i>
                        <strong>Result Entry</strong>
                        <small>Enter scores</small>
                    </a>
                    <a href="admin_result_slip_pro.php" class="quick-card slip-pro">
                        <i class="fas fa-file-alt"></i>
                        <strong>Result Slip Pro</strong>
                        <small>Print result slip</small>
                    </a>
                    <a href="admin_result_slip.php" class="quick-card slip">
                        <i class="fas fa-file-invoice"></i>
                        <strong>Result Slip</strong>
                        <small>Simple slip</small>
                    </a>
                <?php elseif ($is_provost): ?>
                    <a href="admin_result_slip_pro.php" class="quick-card slip-pro">
                        <i class="fas fa-file-alt"></i>
                        <strong>Result Slip Pro</strong>
                        <small>Print result slip</small>
                    </a>
                    <a href="admin_result_slip.php" class="quick-card slip">
                        <i class="fas fa-file-invoice"></i>
                        <strong>Result Slip</strong>
                        <small>Simple slip</small>
                    </a>
                <?php endif; ?>
                
                <?php if ($has_payment_access): ?>
                    <a href="admin_payments.php" class="quick-card payments" style="border-color:#f57c00;">
                        <i class="fas fa-money-bill-wave"></i>
                        <strong>Payment List</strong>
                        <small>View payments</small>
                    </a>
                <?php endif; ?>
                
            </div>
        </div>
        
        <!-- ============================================ -->
        <!-- APPLICATIONS TABLE (Admin & Provost) -->
        <!-- ============================================ -->
        <?php if (($is_admin || $is_provost) && $applications && mysqli_num_rows($applications) > 0): ?>
        <div class="table-section">
            <div class="table-header">
                <h3><i class="fas fa-list-ul"></i> All Applications</h3>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Programme</th>
                        <th>Course</th>
                        <th>Status</th>
                        <th>Admission No.</th>
                        <th>Comment</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; while ($row = mysqli_fetch_assoc($applications)): 
                        $status_class = $row['status'] == 'approved' ? 'approved' : ($row['status'] == 'pending' ? 'pending' : 'rejected');
                    ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['fullname']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['programme']); ?></td>
                        <td><?php echo htmlspecialchars($row['course_applied']); ?></td>
                        <td><span class="status-badge <?php echo $status_class; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                        <td>
                            <?php if (!empty($row['reg_no']) || !empty($row['student_id'])): ?>
                                <span class="admission-no"><?php echo $row['reg_no'] ?? $row['student_id']; ?></span>
                            <?php else: ?>
                                <span style="color:#aaa;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="max-width:120px; font-size:0.8rem;"><?php echo htmlspecialchars($row['notes'] ?? '-'); ?></td>
                        <td>
                            <?php if ($row['status'] == 'pending'): ?>
                            <form method="POST" class="action-form">
                                <input type="hidden" name="application_id" value="<?php echo $row['id']; ?>">
                                <input type="text" name="comment" placeholder="Comment..." required>
                                <button type="submit" name="action" value="approved" class="btn-accept"><i class="fas fa-check"></i> Accept</button>
                                <button type="submit" name="action" value="rejected" class="btn-reject"><i class="fas fa-times"></i> Reject</button>
                            </form>
                            <?php else: ?>
                            <span style="color:#6a8f6a; font-size:0.8rem;"><i class="fas fa-check-circle" style="color:#2e7d32;"></i> Done</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php elseif ($has_payment_access): ?>
        <!-- ============================================ -->
        <!-- PAYMENT ACCESS SECTION (Accountant) -->
        <!-- ============================================ -->
        
        <div class="stats-grid">
            <a href="view_students.php" class="stat-card">
                <span class="icon">🎓</span>
                <div class="number"><?php echo $total_students; ?></div>
                <div class="label">Total Students</div>
            </a>
            <a href="admin_payments.php" class="stat-card orange">
                <span class="icon">💰</span>
                <div class="number"><?php echo $total_payments; ?></div>
                <div class="label">Total Payments</div>
            </a>
            <a href="admin_payments.php" class="stat-card blue">
                <span class="icon">💵</span>
                <div class="number">₦<?php echo number_format($total_payment_amount, 0); ?></div>
                <div class="label">Total Amount</div>
            </a>
        </div>
        
        <div class="card">
            <h3><i class="fas fa-bolt" style="color:#f9a825;"></i> Quick Actions</h3>
            <div class="quick-grid">
                <a href="admin_payments.php" class="quick-card payments" style="border-color:#f57c00;">
                    <i class="fas fa-money-bill-wave"></i>
                    <strong>Payment List</strong>
                    <small>View all payments</small>
                </a>
                <a href="view_students.php" class="quick-card" style="border-color:#1976d2;">
                    <i class="fas fa-users" style="color:#1976d2;"></i>
                    <strong>Students</strong>
                    <small>View students</small>
                </a>
            </div>
        </div>
        
        <?php else: ?>
        <!-- ============================================ -->
        <!-- OTHER STAFF SECTION (Bursary, Admission Officer, Staff) -->
        <!-- ============================================ -->
        
        <div class="stats-grid">
            <a href="view_students.php" class="stat-card">
                <span class="icon">🎓</span>
                <div class="number"><?php echo $total_students; ?></div>
                <div class="label">Total Students</div>
            </a>
            <a href="view_staff.php" class="stat-card blue">
                <span class="icon">👥</span>
                <div class="number"><?php echo $total_staff; ?></div>
                <div class="label">Total Staff</div>
            </a>
            <a href="manage_courses.php" class="stat-card purple">
                <span class="icon">📚</span>
                <div class="number"><?php echo $total_courses; ?></div>
                <div class="label">Total Courses</div>
            </a>
            <a href="view_applications.php" class="stat-card pending">
                <span class="icon">⏳</span>
                <div class="number"><?php echo $pending; ?></div>
                <div class="label">Pending</div>
            </a>
            <a href="view_applications.php" class="stat-card approved">
                <span class="icon">✅</span>
                <div class="number"><?php echo $approved; ?></div>
                <div class="label">Approved</div>
            </a>
            <a href="view_applications.php" class="stat-card rejected">
                <span class="icon">❌</span>
                <div class="number"><?php echo $rejected; ?></div>
                <div class="label">Rejected</div>
            </a>
        </div>
        
        <div class="table-section">
            <div class="table-header">
                <h3><i class="fas fa-list-ul"></i> All Applications</h3>
            </div>

            <?php if ($applications && mysqli_num_rows($applications) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Programme</th>
                        <th>Course</th>
                        <th>Status</th>
                        <th>Admission No.</th>
                        <th>Comment</th>
                        <?php if ($can_accept): ?><th>Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; while ($row = mysqli_fetch_assoc($applications)): 
                        $status_class = $row['status'] == 'approved' ? 'approved' : ($row['status'] == 'pending' ? 'pending' : 'rejected');
                    ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['fullname']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['programme']); ?></td>
                        <td><?php echo htmlspecialchars($row['course_applied']); ?></td>
                        <td><span class="status-badge <?php echo $status_class; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                        <td>
                            <?php if (!empty($row['reg_no']) || !empty($row['student_id'])): ?>
                                <span class="admission-no"><?php echo $row['reg_no'] ?? $row['student_id']; ?></span>
                            <?php else: ?>
                                <span style="color:#aaa;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="max-width:120px; font-size:0.8rem;"><?php echo htmlspecialchars($row['notes'] ?? '-'); ?></td>
                        <?php if ($can_accept): ?>
                            <td>
                                <?php if ($row['status'] == 'pending'): ?>
                                <form method="POST" class="action-form">
                                    <input type="hidden" name="application_id" value="<?php echo $row['id']; ?>">
                                    <input type="text" name="comment" placeholder="Comment..." required>
                                    <button type="submit" name="action" value="approved" class="btn-accept"><i class="fas fa-check"></i> Accept</button>
                                    <button type="submit" name="action" value="rejected" class="btn-reject"><i class="fas fa-times"></i> Reject</button>
                                </form>
                                <?php else: ?>
                                <span style="color:#6a8f6a; font-size:0.8rem;"><i class="fas fa-check-circle" style="color:#2e7d32;"></i> Done</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-applications">
                <i class="fas fa-inbox"></i>
                <p>No applications found.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <?php endif; ?>
        
    </div>
</body>
</html>