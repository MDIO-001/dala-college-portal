<?php
session_start();
include 'connect.php';

// Check if staff is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header('Location: login.php');
    exit();
}

$staff_id = $_SESSION['user_id'];
$staff_name = $_SESSION['fullname'] ?? 'Staff';

// Get all students - SIMPLE QUERY
$students_query = "SELECT * FROM students ORDER BY id DESC";
$students_result = mysqli_query($conn, $students_query);

$total_students = 0;
if ($students_result) {
    $total_students = mysqli_num_rows($students_result);
}

// Count by level
$nce1 = 0;
$nce2 = 0;
$nce3 = 0;
$degree = 0;

$nce1_q = mysqli_query($conn, "SELECT COUNT(*) as count FROM students WHERE level = 'NCE I'");
if ($nce1_q) { $row = mysqli_fetch_assoc($nce1_q); $nce1 = $row['count']; }

$nce2_q = mysqli_query($conn, "SELECT COUNT(*) as count FROM students WHERE level = 'NCE II'");
if ($nce2_q) { $row = mysqli_fetch_assoc($nce2_q); $nce2 = $row['count']; }

$nce3_q = mysqli_query($conn, "SELECT COUNT(*) as count FROM students WHERE level = 'NCE III'");
if ($nce3_q) { $row = mysqli_fetch_assoc($nce3_q); $nce3 = $row['count']; }

$degree_q = mysqli_query($conn, "SELECT COUNT(*) as count FROM students WHERE programme = 'DEGREE'");
if ($degree_q) { $row = mysqli_fetch_assoc($degree_q); $degree = $row['count']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Students - Staff</title>
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: #f8faf8;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-card .number { font-size: 1.8rem; font-weight: 800; color: #2e7d32; }
        .stat-card .label { font-size: 0.8rem; color: #6a8f6a; }
        .stat-card.blue .number { color: #1976d2; }
        .stat-card.orange .number { color: #e65100; }
        .stat-card.purple .number { color: #7b1fa2; }
        .stat-card.green .number { color: #2e7d32; }
        
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        table th {
            background: #0d2818;
            color: white;
            padding: 12px;
            text-align: left;
            font-size: 0.85rem;
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
        .badge-degree { background: #f3e5f5; color: #7b1fa2; }
        
        .btn-back { background: #6a8f6a; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-back:hover { background: #4a6a4a; }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6a8f6a;
        }
        .no-data i { font-size: 3rem; display: block; margin-bottom: 10px; color: #dce8dc; }
        
        @media (max-width: 768px) {
            .container { padding: 15px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

    <div class="topbar">
        <div>
            <span class="logo-title">DALA <span>COLLEGE</span></span>
            <span class="logo-sub">of Education, Kano</span>
        </div>
        <nav>
            <a href="staff_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="view_applications.php"><i class="fas fa-file-alt"></i> Applications</a>
            <a href="view_students.php" class="active"><i class="fas fa-users"></i> Students</a>
            <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="container">
        <h1><i class="fas fa-users" style="color:#2e7d32;"></i> All Students</h1>
        <p class="sub">View all registered students</p>

        <div class="stats-grid">
            <div class="stat-card green">
                <div class="number"><?php echo $total_students; ?></div>
                <div class="label">🎓 Total Students</div>
            </div>
            <div class="stat-card orange">
                <div class="number"><?php echo $nce1; ?></div>
                <div class="label">📗 NCE I</div>
            </div>
            <div class="stat-card blue">
                <div class="number"><?php echo $nce2; ?></div>
                <div class="label">📘 NCE II</div>
            </div>
            <div class="stat-card green">
                <div class="number"><?php echo $nce3; ?></div>
                <div class="label">🏆 NCE III</div>
            </div>
            <div class="stat-card purple">
                <div class="number"><?php echo $degree; ?></div>
                <div class="label">🎓 Degree</div>
            </div>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Reg No</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Phone</th>
                        <th>Programme</th>
                        <th>Course</th>
                        <th>Level</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($total_students > 0 && is_object($students_result) && mysqli_num_rows($students_result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($students_result)): 
                            $level_class = '';
                            if ($row['level'] == 'NCE I') $level_class = 'ncei';
                            elseif ($row['level'] == 'NCE II') $level_class = 'nceii';
                            elseif ($row['level'] == 'NCE III') $level_class = 'nceiii';
                            else $level_class = 'degree';
                        ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['reg_no'] ?? $row['student_id'] ?? '-'); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo htmlspecialchars($row['phone'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['programme'] ?? 'NCE'); ?></td>
                            <td><?php echo htmlspecialchars($row['course'] ?? '-'); ?></td>
                            <td>
                                <span class="badge-level badge-<?php echo $level_class; ?>">
                                    <?php echo htmlspecialchars($row['level'] ?? 'NCE I'); ?>
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
                            <td colspan="9" style="text-align:center; padding:40px; color:#6a8f6a;">
                                <i class="fas fa-users" style="font-size:2rem; display:block; margin-bottom:10px; color:#dce8dc;"></i>
                                No students found. Please add students first.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 20px;">
            <a href="staff_dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>

</body>
</html>