<?php
session_start();
include 'connect.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

$admin_name = $_SESSION['fullname'] ?? 'Admin';

// Get counts
$students_count = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM students"));
$staff_count = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM staff"));
$applications_count = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM applications"));
$pending_count = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM applications WHERE status = 'pending'"));
$approved_count = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM applications WHERE status = 'approved'"));
$rejected_count = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM applications WHERE status = 'rejected'"));

// Get recent applications
$recent_apps = mysqli_query($conn, "SELECT * FROM applications ORDER BY applied_at DESC LIMIT 5");
$total_apps = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM applications"));

// Get recent students
$recent_students = mysqli_query($conn, "SELECT * FROM students ORDER BY id DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Dala College</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f4f8;
            color: #1a2e1a;
            min-height: 100vh;
        }

        .topbar {
            background: #0d2818;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: 0 2px 15px rgba(0,0,0,0.2);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .topbar .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .topbar .logo-section img {
            height: 50px;
            width: 50px;
            border-radius: 50%;
            border: 2px solid #ffd54f;
            object-fit: contain;
            background: white;
            padding: 5px;
        }
        .topbar .logo-section .brand h1 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #ffd54f;
            letter-spacing: 1px;
        }
        .topbar .logo-section .brand h1 span { color: #a5d6a7; }
        .topbar .logo-section .brand p {
            font-size: 0.6rem;
            color: #c8e6c9;
            margin-top: -2px;
        }
        .topbar nav {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        .topbar nav a {
            color: #c8e6c9;
            text-decoration: none;
            padding: 8px 18px;
            border-radius: 25px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        .topbar nav a:hover { background: #2e7d32; color: white; }
        .topbar nav a.active {
            background: #ffd54f;
            color: #0d2818 !important;
            font-weight: 700;
        }
        .topbar nav a.logout {
            background: #c62828;
            color: white !important;
        }
        .topbar nav a.logout:hover { background: #b71c1c; }

        .dashboard-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .welcome-section {
            background: linear-gradient(135deg, #1b4d2e, #2e7d32);
            padding: 25px 30px;
            border-radius: 16px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 25px;
            box-shadow: 0 4px 20px rgba(46,125,50,0.25);
        }
        .welcome-section .greeting h2 {
            font-size: 1.5rem;
            font-weight: 700;
        }
        .welcome-section .greeting h2 span { color: #ffd54f; }
        .welcome-section .greeting p { color: #c8e6c9; font-size: 0.9rem; }
        .welcome-section .admin-badge {
            background: rgba(255,255,255,0.15);
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.85rem;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .stats-grid .stat-item {
            background: white;
            padding: 20px;
            border-radius: 14px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
            border-top: 4px solid #2e7d32;
        }
        .stats-grid .stat-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }
        .stats-grid .stat-item .icon { font-size: 1.5rem; display: block; margin-bottom: 5px; }
        .stats-grid .stat-item .number {
            font-size: 2rem;
            font-weight: 800;
            color: #0d2818;
        }
        .stats-grid .stat-item .label {
            font-size: 0.85rem;
            color: #6a8f6a;
            margin-top: 2px;
        }
        .stats-grid .stat-item.pending { border-top-color: #ffa000; }
        .stats-grid .stat-item.approved { border-top-color: #2e7d32; }
        .stats-grid .stat-item.rejected { border-top-color: #c62828; }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .quick-actions .quick-btn {
            background: white;
            padding: 20px;
            border-radius: 14px;
            text-align: center;
            text-decoration: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
            border: 2px solid transparent;
            display: block;
        }
        .quick-actions .quick-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border-color: #2e7d32;
        }
        .quick-actions .quick-btn .icon { font-size: 2rem; display: block; margin-bottom: 8px; }
        .quick-actions .quick-btn .title { font-size: 0.9rem; font-weight: 600; color: #0d2818; }
        .quick-actions .quick-btn .desc { font-size: 0.75rem; color: #6a8f6a; margin-top: 2px; }

        .table-section {
            background: white;
            padding: 25px 30px;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            margin-bottom: 25px;
            overflow-x: auto;
        }
        .table-section .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        .table-section .table-header h3 {
            font-size: 1.1rem;
            color: #0d2818;
        }
        .table-section .table-header h3 i { color: #2e7d32; margin-right: 8px; }
        .table-section .table-header .view-all {
            color: #2e7d32;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .table-section .table-header .view-all:hover { text-decoration: underline; }
        .table-section table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .table-section table th {
            background: #f8faf8;
            color: #0d2818;
            padding: 10px 14px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #e8f0e8;
        }
        .table-section table td {
            padding: 10px 14px;
            border-bottom: 1px solid #f0f4f8;
            vertical-align: middle;
        }
        .table-section table tr:hover td { background: #f8faf8; }
        .table-section .status-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-badge.approved { background: #e8f5e9; color: #2e7d32; }
        .status-badge.pending { background: #fff8e1; color: #ffa000; }
        .status-badge.rejected { background: #ffebee; color: #c62828; }

        footer {
            background: #0d2818;
            color: #a5d6a7;
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .topbar { padding: 12px 20px; flex-direction: column; gap: 10px; }
            .topbar nav { justify-content: center; }
            .welcome-section { flex-direction: column; text-align: center; gap: 10px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .quick-actions { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .quick-actions { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <div class="topbar">
        <div class="logo-section">
            <img src="images/dala-logo.png" alt="Dala College Logo" onerror="this.style.display='none'">
            <div class="brand">
                <h1>DALA <span>COLLEGE</span></h1>
                <p>of Education, Kano</p>
            </div>
        </div>
        <nav>
            <a href="index.html"><i class="fas fa-home"></i> Home</a>
            <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="students_list.php"><i class="fas fa-users"></i> Students</a>
            <a href="staff_dashboard.php"><i class="fas fa-user-tie"></i> Staff Panel</a>
            <a href="admin_features.php"><i class="fas fa-cog"></i> Admin Tools</a>
            <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="dashboard-container">

        <div class="welcome-section">
            <div class="greeting">
                <h2>👋 Welcome, <span><?php echo $admin_name; ?></span></h2>
                <p><i class="fas fa-calendar-alt"></i> <?php echo date('l, F j, Y'); ?></p>
            </div>
            <div class="admin-badge">
                <i class="fas fa-user-shield"></i> Admin Panel
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-item">
                <span class="icon">👨‍🎓</span>
                <div class="number"><?php echo $students_count; ?></div>
                <div class="label">Total Students</div>
            </div>
            <div class="stat-item">
                <span class="icon">👨‍🏫</span>
                <div class="number"><?php echo $staff_count; ?></div>
                <div class="label">Total Staff</div>
            </div>
            <div class="stat-item">
                <span class="icon">📋</span>
                <div class="number"><?php echo $total_apps; ?></div>
                <div class="label">Applications</div>
            </div>
            <div class="stat-item pending">
                <span class="icon">⏳</span>
                <div class="number" style="color:#ffa000;"><?php echo $pending_count; ?></div>
                <div class="label">Pending Review</div>
            </div>
            <div class="stat-item approved">
                <span class="icon">✅</span>
                <div class="number" style="color:#2e7d32;"><?php echo $approved_count; ?></div>
                <div class="label">Approved</div>
            </div>
            <div class="stat-item rejected">
                <span class="icon">❌</span>
                <div class="number" style="color:#c62828;"><?php echo $rejected_count; ?></div>
                <div class="label">Rejected</div>
            </div>
        </div>

        <div class="quick-actions">
            <a href="students_list.php" class="quick-btn">
                <span class="icon">👨‍🎓</span>
                <span class="title">All Students</span>
                <span class="desc">View all registered students</span>
            </a>
            <a href="staff_dashboard.php" class="quick-btn">
                <span class="icon">👨‍🏫</span>
                <span class="title">Staff Panel</span>
                <span class="desc">Manage applications</span>
            </a>
            <a href="admin_features.php" class="quick-btn">
                <span class="icon">🔧</span>
                <span class="title">Admin Tools</span>
                <span class="desc">Add, Edit, Delete students</span>
            </a>
            <a href="logout.php" class="quick-btn" style="border-color:#ffebee;">
                <span class="icon">🚪</span>
                <span class="title">Logout</span>
                <span class="desc">Sign out</span>
            </a>
        </div>

        <div class="table-section">
            <div class="table-header">
                <h3><i class="fas fa-clock"></i> Recent Applications</h3>
                <a href="staff_dashboard.php" class="view-all">View All →</a>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Programme</th>
                        <th>Course</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1;
                    while ($row = mysqli_fetch_assoc($recent_apps)): 
                        $status_class = $row['status'] == 'approved' ? 'approved' : ($row['status'] == 'pending' ? 'pending' : 'rejected');
                    ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><strong><?php echo $row['fullname']; ?></strong></td>
                        <td><?php echo $row['programme']; ?></td>
                        <td><?php echo $row['course']; ?></td>
                        <td><span class="status-badge <?php echo $status_class; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                        <td><?php echo date('d/m/Y', strtotime($row['applied_at'] ?? 'now')); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="table-section">
            <div class="table-header">
                <h3><i class="fas fa-user-graduate"></i> Recent Students</h3>
                <a href="students_list.php" class="view-all">View All →</a>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Programme</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1;
                    while ($row = mysqli_fetch_assoc($recent_students)): 
                        $status_class = $row['status'] == 'approved' ? 'approved' : ($row['status'] == 'pending' ? 'pending' : 'rejected');
                    ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><strong><?php echo $row['fullname']; ?></strong></td>
                        <td><?php echo $row['email']; ?></td>
                        <td><?php echo $row['phone']; ?></td>
                        <td><?php echo $row['programme']; ?></td>
                        <td><span class="status-badge <?php echo $status_class; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    </div>

    <footer>
        <p>© 2026 Dala College Kano. All Rights Reserved.</p>
    </footer>

</body>
</html>