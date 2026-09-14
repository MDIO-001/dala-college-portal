<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header('Location: login.php');
    exit();
}

// Get all courses
$courses_query = "SELECT * FROM courses ORDER BY id DESC";
$courses_result = mysqli_query($conn, $courses_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses - Staff</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f4f8;
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
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 30px rgba(0,0,0,0.1);
        }
        h1 { color: #0d2818; margin-bottom: 5px; }
        .sub { color: #6a8f6a; margin-bottom: 20px; }
        
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 700px; }
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
        .badge-inactive { background: #ffebee; color: #c62828; }
        
        .btn {
            padding: 6px 15px;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary { background: #1976d2; color: white; }
        .btn-primary:hover { background: #0d47a1; }
        .btn-danger { background: #c62828; color: white; }
        .btn-danger:hover { background: #b71c1c; }
        .btn-success { background: #2e7d32; color: white; }
        .btn-success:hover { background: #1b5e20; }
        .btn-back { background: #6a8f6a; color: white; }
        .btn-back:hover { background: #4a6a4a; }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6a8f6a;
        }
        .no-data i { font-size: 3rem; display: block; margin-bottom: 10px; color: #dce8dc; }
        
        @media (max-width: 768px) {
            .container { padding: 15px; }
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
            <a href="manage_courses.php" class="active"><i class="fas fa-book"></i> Courses</a>
            <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="container">
        <h1><i class="fas fa-book" style="color:#2e7d32;"></i> Manage Courses</h1>
        <p class="sub">View and manage all courses</p>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Course Code</th>
                        <th>Course Title</th>
                        <th>Department</th>
                        <th>Level</th>
                        <th>Credits</th>
                        <th>Semester</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($courses_result && mysqli_num_rows($courses_result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($courses_result)): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['course_code']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['course_title']); ?></td>
                            <td><?php echo htmlspecialchars($row['department']); ?></td>
                            <td><?php echo htmlspecialchars($row['level']); ?></td>
                            <td><?php echo $row['credits']; ?></td>
                            <td><?php echo htmlspecialchars($row['semester']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $row['status'] == 'active' ? 'active' : 'inactive'; ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align:center; padding:40px; color:#6a8f6a;">
                            <i class="fas fa-book" style="font-size:2rem; display:block; margin-bottom:10px; color:#dce8dc;"></i>
                            No courses found.
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 20px;">
            <a href="staff_dashboard.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>

</body>
</html>