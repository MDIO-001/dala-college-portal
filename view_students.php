<?php
session_start();
include 'connect.php';
include 'check_role.php';

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
$position = strtolower($_SESSION['position'] ?? '');

// ============================================
// ROLE CHECKS
// ============================================
$is_admin = ($user_role == 'admin');
$is_provost = ($user_role == 'Provost') || ($position == 'provost');
$is_exam_officer = ($user_role == 'Exam Officer') || ($position == 'exam officer');
$is_accountant = ($user_role == 'Accountant');
$is_bursary = ($user_role == 'Bursary');
$is_admission_officer = ($user_role == 'Admission Officer');

// ============================================
// FILTERS
// ============================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$filter_level = isset($_GET['level']) ? mysqli_real_escape_string($conn, $_GET['level']) : '';
$filter_branch = isset($_GET['branch']) ? mysqli_real_escape_string($conn, $_GET['branch']) : '';
$filter_gender = isset($_GET['gender']) ? mysqli_real_escape_string($conn, $_GET['gender']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$filter_programme = isset($_GET['programme']) ? mysqli_real_escape_string($conn, $_GET['programme']) : '';

// ============================================
// BUILD WHERE CLAUSE
// ============================================
$where = [];
if (!empty($search)) {
    $where[] = "(fullname LIKE '%$search%' OR reg_no LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%' OR username LIKE '%$search%' OR student_id LIKE '%$search%')";
}
if (!empty($filter_level)) $where[] = "level = '$filter_level'";
if (!empty($filter_branch)) $where[] = "branch_code = '$filter_branch'";
if (!empty($filter_gender)) $where[] = "gender = '$filter_gender'";
if (!empty($filter_status)) $where[] = "status = '$filter_status'";
if (!empty($filter_programme)) $where[] = "programme = '$filter_programme'";

$where_sql = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";

// ============================================
// GET STUDENTS
// ============================================
$students_query = "SELECT * FROM students $where_sql ORDER BY id DESC";
$students_result = mysqli_query($conn, $students_query);

$total_students = 0;
if ($students_result) {
    $total_students = mysqli_num_rows($students_result);
}

// ============================================
// STATS (BASIC - FOR ALL ROLES)
// ============================================
$total_all = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students"))['c'];
$nce1 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE level = 'NCE I'"))['c'];
$nce2 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE level = 'NCE II'"))['c'];
$nce3 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE level = 'NCE III'"))['c'];
$degree_400 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE level = '400 Level'"))['c'];
$degree_500 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE level = '500 Level'"))['c'];

// ============================================
// STATS (GENDER + BRANCH - ADMIN DA PROVOST KAWAI)
// ============================================
$male_total = 0;
$female_total = 0;
$male_branch_a = 0;
$female_branch_a = 0;
$male_branch_b = 0;
$female_branch_b = 0;
$male_branch_c = 0;
$female_branch_c = 0;

if ($is_admin || $is_provost) {
    $male_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE gender = 'Male'"))['c'];
    $female_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE gender = 'Female'"))['c'];
    
    $male_branch_a = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE gender = 'Male' AND branch_code = 'SHINGE'"))['c'];
    $female_branch_a = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE gender = 'Female' AND branch_code = 'SHINGE'"))['c'];
    
    $male_branch_b = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE gender = 'Male' AND branch_code = 'SABUWA'"))['c'];
    $female_branch_b = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE gender = 'Female' AND branch_code = 'SABUWA'"))['c'];
    
    $male_branch_c = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE gender = 'Male' AND branch_code = 'TUDUN'"))['c'];
    $female_branch_c = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE gender = 'Female' AND branch_code = 'TUDUN'"))['c'];
}

// ============================================
// EXPORT CSV
// ============================================
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="students_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['S/N', 'REG NO', 'FULL NAME', 'EMAIL', 'PHONE', 'GENDER', 'PROGRAMME', 'COURSE', 'LEVEL', 'BRANCH', 'STATUS']);
    
    $sn = 1;
    $export_q = mysqli_query($conn, "SELECT * FROM students $where_sql ORDER BY id ASC");
    if ($export_q) {
        while ($row = mysqli_fetch_assoc($export_q)) {
            fputcsv($output, [
                $sn++,
                $row['reg_no'] ?? '',
                $row['fullname'] ?? '',
                $row['email'] ?? '',
                $row['phone'] ?? '',
                $row['gender'] ?? '',
                $row['programme'] ?? 'NCE',
                $row['course'] ?? '',
                $row['level'] ?? '',
                $row['branch_code'] ?? '',
                $row['status'] ?? ''
            ]);
        }
    }
    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Students - Dala College</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            padding: 20px;
        }
        .topbar {
            background: #0d2818;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .topbar .logo-title { color: #ffd54f; font-size: 1.3rem; font-weight: 800; }
        .topbar .logo-title span { color: #a5d6a7; }
        .topbar .logo-sub { display: block; font-size: 0.6rem; color: #c8e6c9; }
        .topbar nav a { 
            color: #c8e6c9; 
            text-decoration: none; 
            padding: 8px 18px; 
            border-radius: 25px;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }
        .topbar nav a:hover { background: #2e7d32; }
        .topbar nav a.active { background: #ffd54f; color: #0d2818 !important; font-weight: 700; }
        .topbar nav a.logout { background: #c62828; color: white !important; }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 30px rgba(0,0,0,0.1);
        }
        h1 { color: #0d2818; margin-bottom: 5px; }
        .sub { color: #6a8f6a; margin-bottom: 20px; }
        
        /* STATS GRID */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: #f8faf8;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            border-top: 4px solid #2e7d32;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }
        .stat-card .number { font-size: 1.8rem; font-weight: 800; color: #2e7d32; line-height: 1; }
        .stat-card .label { font-size: 0.7rem; color: #6a8f6a; margin-top: 5px; font-weight: 700; text-transform: uppercase; }
        .stat-card.blue .number { color: #1976d2; }
        .stat-card.blue { border-top-color: #1976d2; }
        .stat-card.orange .number { color: #e65100; }
        .stat-card.orange { border-top-color: #e65100; }
        .stat-card.purple .number { color: #7b1fa2; }
        .stat-card.purple { border-top-color: #7b1fa2; }
        .stat-card.pink .number { color: #c2185b; }
        .stat-card.pink { border-top-color: #c2185b; }
        .stat-card.navy .number { color: #0d47a1; }
        .stat-card.navy { border-top-color: #0d47a1; }
        .stat-card.dark-purple .number { color: #4527a0; }
        .stat-card.dark-purple { border-top-color: #4527a0; }
        
        /* FILTERS */
        .filter-box {
            background: #e8f5e9;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr auto;
            gap: 12px;
            align-items: end;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #0d2818;
            margin-bottom: 5px;
            font-size: 0.8rem;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #dce8dc;
            border-radius: 8px;
            font-size: 0.85rem;
            background: white;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #2e7d32;
            outline: none;
        }
        .btn-filter {
            padding: 9px 20px;
            background: #2e7d32;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            white-space: nowrap;
        }
        .btn-filter:hover { background: #1b5e20; }
        .btn-reset {
            padding: 9px 15px;
            background: #6a8f6a;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-reset:hover { background: #4a6a4a; }
        
        /* ACTIONS */
        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .actions-bar h3 {
            color: #0d2818;
            font-size: 1rem;
        }
        .actions-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn-action {
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            border: none;
        }
        .btn-export { background: #f57c00; color: white; }
        .btn-export:hover { background: #e65100; }
        .btn-print { background: #1976d2; color: white; }
        .btn-print:hover { background: #0d47a1; }
        .btn-back { background: #6a8f6a; color: white; }
        .btn-back:hover { background: #4a6a4a; }
        
        /* TABLE */
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        table th {
            background: #0d2818;
            color: white;
            padding: 12px;
            text-align: left;
            font-size: 0.8rem;
            text-transform: uppercase;
            white-space: nowrap;
        }
        table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            font-size: 0.85rem;
        }
        table tr:hover td { background: #f8faf8; }
        
        .badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .badge-active { background: #e8f5e9; color: #2e7d32; }
        .badge-pending { background: #fff3e0; color: #e65100; }
        .badge-inactive { background: #ffebee; color: #c62828; }
        .badge-rejected { background: #ffebee; color: #c62828; }
        .badge-approved { background: #e8f5e9; color: #2e7d32; }
        .badge-graduated { background: #e3f2fd; color: #0d47a1; }
        
        .badge-level {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .badge-ncei { background: #fff3e0; color: #e65100; }
        .badge-nceii { background: #e3f2fd; color: #0d47a1; }
        .badge-nceiii { background: #e8f5e9; color: #1b5e20; }
        .badge-400level { background: #f3e5f5; color: #6a1b9a; }
        .badge-500level { background: #ede7f6; color: #4527a0; }
        .badge-degree { background: #f3e5f5; color: #7b1fa2; }
        
        .badge-gender-male { background: #e3f2fd; color: #0d47a1; }
        .badge-gender-female { background: #fce4ec; color: #c2185b; }
        
        .badge-branch-a { background: #e8f5e9; color: #1b5e20; }
        .badge-branch-b { background: #e3f2fd; color: #0d47a1; }
        .badge-branch-c { background: #fff3e0; color: #e65100; }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6a8f6a;
        }
        .no-data i { font-size: 3rem; display: block; margin-bottom: 10px; color: #dce8dc; }
        
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(4, 1fr); }
            .filter-grid { grid-template-columns: 1fr 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .container { padding: 15px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filter-grid { grid-template-columns: 1fr; }
            .topbar { flex-direction: column; gap: 10px; text-align: center; }
            .topbar nav a { font-size: 0.75rem; padding: 6px 10px; }
        }
        
        @media print {
            .topbar, .filter-box, .actions-bar, .no-print { display: none !important; }
            body { background: white; padding: 0; }
            .container { box-shadow: none; padding: 10px; }
            table { font-size: 10px; }
            table th { background: #0d2818 !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
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
            <?php if ($is_admin): ?>
                <a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php else: ?>
                <a href="staff_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <?php endif; ?>
            <a href="view_students.php" class="active"><i class="fas fa-users"></i> Students</a>
            <?php if ($is_admin || $is_provost || $is_admission_officer): ?>
                <a href="view_applications.php"><i class="fas fa-file-alt"></i> Applications</a>
            <?php endif; ?>
            <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="container">
        <h1><i class="fas fa-users" style="color:#2e7d32;"></i> All Students</h1>
        <p class="sub">
            <?php if (!empty($search) || !empty($filter_level) || !empty($filter_branch) || !empty($filter_gender) || !empty($filter_status) || !empty($filter_programme)): ?>
                Filtered Results: <strong><?php echo $total_students; ?></strong> students
            <?php else: ?>
                Total: <strong><?php echo $total_all; ?></strong> students
            <?php endif; ?>
        </p>

        <!-- ============================================ -->
        <!-- STATS CARDS -->
        <!-- ============================================ -->
        <div class="stats-grid">
            <a href="view_students.php" class="stat-card">
                <div class="number"><?php echo $total_all; ?></div>
                <div class="label">🎓 Total</div>
            </a>
            <a href="view_students.php?level=NCE I" class="stat-card orange">
                <div class="number"><?php echo $nce1; ?></div>
                <div class="label">📗 NCE I</div>
            </a>
            <a href="view_students.php?level=NCE II" class="stat-card blue">
                <div class="number"><?php echo $nce2; ?></div>
                <div class="label">📘 NCE II</div>
            </a>
            <a href="view_students.php?level=NCE III" class="stat-card">
                <div class="number"><?php echo $nce3; ?></div>
                <div class="label">🏆 NCE III</div>
            </a>
            <a href="view_students.php?level=400 Level" class="stat-card purple">
                <div class="number"><?php echo $degree_400; ?></div>
                <div class="label">🎓 400 Level</div>
            </a>
            <a href="view_students.php?level=500 Level" class="stat-card dark-purple">
                <div class="number"><?php echo $degree_500; ?></div>
                <div class="label">🎓 500 Level</div>
            </a>
        </div>

        <!-- ============================================ -->
        <!-- GENDER + BRANCH CARDS (ADMIN DA PROVOST KAWAI) -->
        <!-- ============================================ -->
        <?php if ($is_admin || $is_provost): ?>
        <div class="stats-grid">
            <a href="view_students.php?gender=Male" class="stat-card navy">
                <div class="number"><?php echo $male_total; ?></div>
                <div class="label">👨 Total Male</div>
            </a>
            <a href="view_students.php?gender=Female" class="stat-card pink">
                <div class="number"><?php echo $female_total; ?></div>
                <div class="label">👩 Total Female</div>
            </a>
            <a href="view_students.php?gender=Male&branch=SHINGE" class="stat-card navy">
                <div class="number"><?php echo $male_branch_a; ?></div>
                <div class="label">👨 A-Shinge (M)</div>
            </a>
            <a href="view_students.php?gender=Female&branch=SHINGE" class="stat-card pink">
                <div class="number"><?php echo $female_branch_a; ?></div>
                <div class="label">👩 A-Shinge (F)</div>
            </a>
            <a href="view_students.php?gender=Male&branch=SABUWA" class="stat-card navy">
                <div class="number"><?php echo $male_branch_b; ?></div>
                <div class="label">👨 B-Sabuwa (M)</div>
            </a>
            <a href="view_students.php?gender=Female&branch=SABUWA" class="stat-card pink">
                <div class="number"><?php echo $female_branch_b; ?></div>
                <div class="label">👩 B-Sabuwa (F)</div>
            </a>
            <a href="view_students.php?gender=Male&branch=TUDUN" class="stat-card navy">
                <div class="number"><?php echo $male_branch_c; ?></div>
                <div class="label">👨 C-Tudun (M)</div>
            </a>
            <a href="view_students.php?gender=Female&branch=TUDUN" class="stat-card pink">
                <div class="number"><?php echo $female_branch_c; ?></div>
                <div class="label">👩 C-Tudun (F)</div>
            </a>
        </div>
        <?php endif; ?>

        <!-- ============================================ -->
        <!-- FILTERS -->
        <!-- ============================================ -->
        <form method="GET" class="filter-box no-print">
            <div class="filter-grid">
                <div class="form-group">
                    <label><i class="fas fa-search"></i> Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, Reg No, Email, Phone...">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-layer-group"></i> Level</label>
                    <select name="level">
                        <option value="">-- All --</option>
                        <option value="NCE I" <?php echo ($filter_level == 'NCE I') ? 'selected' : ''; ?>>NCE I</option>
                        <option value="NCE II" <?php echo ($filter_level == 'NCE II') ? 'selected' : ''; ?>>NCE II</option>
                        <option value="NCE III" <?php echo ($filter_level == 'NCE III') ? 'selected' : ''; ?>>NCE III</option>
                        <option value="400 Level" <?php echo ($filter_level == '400 Level') ? 'selected' : ''; ?>>400 Level</option>
                        <option value="500 Level" <?php echo ($filter_level == '500 Level') ? 'selected' : ''; ?>>500 Level</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-building"></i> Branch</label>
                    <select name="branch">
                        <option value="">-- All --</option>
                        <option value="SHINGE" <?php echo ($filter_branch == 'SHINGE') ? 'selected' : ''; ?>>A - Shinge</option>
                        <option value="SABUWA" <?php echo ($filter_branch == 'SABUWA') ? 'selected' : ''; ?>>B - Sabuwar Kofa</option>
                        <option value="TUDUN" <?php echo ($filter_branch == 'TUDUN') ? 'selected' : ''; ?>>C - Tudun Yola</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-venus-mars"></i> Gender</label>
                    <select name="gender">
                        <option value="">-- All --</option>
                        <option value="Male" <?php echo ($filter_gender == 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($filter_gender == 'Female') ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-graduation-cap"></i> Programme</label>
                    <select name="programme">
                        <option value="">-- All --</option>
                        <option value="NCE" <?php echo ($filter_programme == 'NCE') ? 'selected' : ''; ?>>NCE</option>
                        <option value="DEGREE" <?php echo ($filter_programme == 'DEGREE') ? 'selected' : ''; ?>>Degree</option>
                        <option value="ENTREPRENEURSHIP" <?php echo ($filter_programme == 'ENTREPRENEURSHIP') ? 'selected' : ''; ?>>Entrepreneurship</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-toggle-on"></i> Status</label>
                    <select name="status">
                        <option value="">-- All --</option>
                        <option value="active" <?php echo ($filter_status == 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="pending" <?php echo ($filter_status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo ($filter_status == 'approved') ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo ($filter_status == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                        <option value="graduated" <?php echo ($filter_status == 'graduated') ? 'selected' : ''; ?>>Graduated</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                    <a href="view_students.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
                </div>
            </div>
        </form>

        <!-- ============================================ -->
        <!-- ACTIONS -->
        <!-- ============================================ -->
        <div class="actions-bar no-print">
            <h3>
                <i class="fas fa-list"></i> 
                <?php echo $total_students; ?> Student(s) Found
            </h3>
            <div class="actions-buttons">
                <a href="?export_csv=1&search=<?php echo urlencode($search); ?>&level=<?php echo urlencode($filter_level); ?>&branch=<?php echo urlencode($filter_branch); ?>&gender=<?php echo urlencode($filter_gender); ?>&status=<?php echo urlencode($filter_status); ?>&programme=<?php echo urlencode($filter_programme); ?>" 
                   class="btn-action btn-export">
                    <i class="fas fa-file-csv"></i> Export CSV
                </a>
                <button onclick="window.print()" class="btn-action btn-print">
                    <i class="fas fa-print"></i> Print
                </button>
                <?php if ($is_admin): ?>
                    <a href="admin_dashboard.php" class="btn-action btn-back"><i class="fas fa-arrow-left"></i> Back</a>
                <?php else: ?>
                    <a href="staff_dashboard.php" class="btn-action btn-back"><i class="fas fa-arrow-left"></i> Back</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- STUDENTS TABLE -->
        <!-- ============================================ -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Reg No</th>
                        <th>Full Name</th>
                        <th>Gender</th>
                        <th>Phone</th>
                        <th>Programme</th>
                        <th>Course</th>
                        <th>Level</th>
                        <th>Branch</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($total_students > 0 && $students_result && mysqli_num_rows($students_result) > 0): ?>
                        <?php $sn = 1; while ($row = mysqli_fetch_assoc($students_result)): 
                            // Level class
                            $level_class = 'ncei';
                            if ($row['level'] == 'NCE I') $level_class = 'ncei';
                            elseif ($row['level'] == 'NCE II') $level_class = 'nceii';
                            elseif ($row['level'] == 'NCE III') $level_class = 'nceiii';
                            elseif ($row['level'] == '400 Level') $level_class = '400level';
                            elseif ($row['level'] == '500 Level') $level_class = '500level';
                            
                            // Gender class
                            $gender_class = ($row['gender'] == 'Female') ? 'gender-female' : 'gender-male';
                            
                            // Branch class
                            $branch_class = 'branch-a';
                            $branch_name = 'A - Shinge';
                            if ($row['branch_code'] == 'SABUWA') { $branch_class = 'branch-b'; $branch_name = 'B - Sabuwa'; }
                            elseif ($row['branch_code'] == 'TUDUN') { $branch_class = 'branch-c'; $branch_name = 'C - Tudun'; }
                        ?>
                        <tr>
                            <td><?php echo $sn++; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['reg_no'] ?? $row['student_id'] ?? '-'); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $gender_class; ?>">
                                    <?php echo ($row['gender'] == 'Female') ? '👩 Female' : '👨 Male'; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($row['phone'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['programme'] ?? 'NCE'); ?></td>
                            <td><?php echo htmlspecialchars($row['course'] ?? '-'); ?></td>
                            <td>
                                <span class="badge-level badge-<?php echo $level_class; ?>">
                                    <?php echo htmlspecialchars($row['level'] ?? 'NCE I'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $branch_class; ?>">
                                    <?php echo $branch_name; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $row['status'] ?? 'pending'; ?>">
                                    <?php echo ucfirst($row['status'] ?? 'Pending'); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="text-align:center; padding:40px; color:#6a8f6a;">
                                <i class="fas fa-users" style="font-size:2rem; display:block; margin-bottom:10px; color:#dce8dc;"></i>
                                No students found matching your filters.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>