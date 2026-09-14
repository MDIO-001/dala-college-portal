<?php
session_start();
include 'connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'staff')) {
    header('Location: login.php');
    exit();
}

// Get all students
$query = "SELECT s.*, a.applied_at 
          FROM students s 
          LEFT JOIN applications a ON s.email = a.email 
          ORDER BY s.id DESC";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Error in query: " . mysqli_error($conn));
}

$total_students = mysqli_num_rows($result);

// Handle Excel Export
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="students_list_' . date('Y-m-d') . '.xls"');
    
    echo '<table border="1">';
    echo '<tr>
            <th>S/N</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Programme</th>
            <th>Course</th>
            <th>Status</th>
            <th>Admission No.</th>
            <th>Date of Birth</th>
            <th>Gender</th>
            <th>Guardian Name</th>
            <th>State of Origin</th>
            <th>LGA</th>
            <th>Religion</th>
            <th>Exam Type</th>
            <th>Applied Date</th>
          </tr>';
    
    $i = 1;
    while ($row = mysqli_fetch_assoc($result)) {
        echo '<tr>';
        echo '<td>' . $i++ . '</td>';
        echo '<td>' . ($row['fullname'] ?? '') . '</td>';
        echo '<td>' . ($row['email'] ?? '') . '</td>';
        echo '<td>' . ($row['phone'] ?? '') . '</td>';
        echo '<td>' . ($row['programme'] ?? '') . '</td>';
        echo '<td>' . ($row['course'] ?? '') . '</td>';
        echo '<td>' . ucfirst($row['status'] ?? '') . '</td>';
        echo '<td>' . ($row['admission_no'] ?? '') . '</td>';
        echo '<td>' . (!empty($row['dob']) ? date('d/m/Y', strtotime($row['dob'])) : '') . '</td>';
        echo '<td>' . ($row['gender'] ?? '') . '</td>';
        echo '<td>' . ($row['guardian_name'] ?? '') . '</td>';
        echo '<td>' . ($row['state_of_origin'] ?? '') . '</td>';
        echo '<td>' . ($row['lga'] ?? '') . '</td>';
        echo '<td>' . ($row['religion'] ?? '') . '</td>';
        echo '<td>' . ($row['exam_type'] ?? '') . '</td>';
        echo '<td>' . (!empty($row['applied_at']) ? date('d/m/Y', strtotime($row['applied_at'])) : '') . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    exit();
}

// Reset pointer for display
mysqli_data_seek($result, 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students List - Dala College</title>
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

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 25px;
            background: white;
            padding: 20px 30px;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .header-section h1 {
            color: #0d2818;
            font-size: 1.8rem;
        }
        .header-section h1 i {
            color: #2e7d32;
            margin-right: 10px;
        }
        .header-section .count {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 6px 18px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .header-section .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .btn-export {
            padding: 10px 25px;
            background: #2e7d32;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        .btn-export:hover {
            background: #1b5e20;
            transform: translateY(-2px);
        }
        .btn-export i { font-size: 1.1rem; }
        .btn-back {
            padding: 10px 25px;
            border: 2px solid #2e7d32;
            color: #2e7d32;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            background: transparent;
        }
        .btn-back:hover {
            background: #2e7d32;
            color: white;
        }

        .table-wrapper {
            background: white;
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            overflow-x: auto;
        }
        .table-wrapper table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        .table-wrapper table th {
            background: #0d2818;
            color: white;
            padding: 10px 12px;
            text-align: left;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .table-wrapper table td {
            padding: 8px 12px;
            border-bottom: 1px solid #f0f4f8;
            vertical-align: middle;
        }
        .table-wrapper table tr:hover td {
            background: #f8faf8;
        }
        .table-wrapper .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .status-badge.approved { background: #e8f5e9; color: #2e7d32; }
        .status-badge.pending { background: #fff8e1; color: #ffa000; }
        .status-badge.rejected { background: #ffebee; color: #c62828; }
        .admission-badge {
            font-weight: 700;
            color: #0d2818;
            background: #e8f5e9;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            display: inline-block;
        }
        .no-data {
            text-align: center;
            padding: 60px;
            color: #6a8f6a;
        }
        .no-data i {
            font-size: 4rem;
            display: block;
            margin-bottom: 15px;
            color: #dce8dc;
        }

        @media (max-width: 768px) {
            .topbar { padding: 12px 20px; flex-direction: column; gap: 10px; }
            .topbar nav { justify-content: center; }
            .header-section { flex-direction: column; gap: 15px; text-align: center; }
            .header-section h1 { font-size: 1.4rem; }
            .table-wrapper table { font-size: 0.7rem; }
            .table-wrapper table th, .table-wrapper table td { padding: 5px 8px; }
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
            <a href="students_list.php" class="active"><i class="fas fa-users"></i> Students</a>
            <?php if ($_SESSION['role'] == 'admin'): ?>
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <?php else: ?>
                <a href="staff_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <?php endif; ?>
            <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="container">

        <div class="header-section">
            <div>
                <h1><i class="fas fa-users"></i> Students List</h1>
                <span class="count"><i class="fas fa-user-graduate"></i> Total: <?php echo $total_students; ?></span>
            </div>
            <div class="actions">
                <a href="?export=excel" class="btn-export">
                    <i class="fas fa-file-excel"></i> Export to Excel
                </a>
                <?php if ($_SESSION['role'] == 'admin'): ?>
                    <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                <?php else: ?>
                    <a href="staff_dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-wrapper">
            <?php if ($total_students > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Programme</th>
                        <th>Course</th>
                        <th>Status</th>
                        <th>Admission No.</th>
                        <th>DOB</th>
                        <th>Gender</th>
                        <th>Guardian</th>
                        <th>State</th>
                        <th>LGA</th>
                        <th>Religion</th>
                        <th>Exam</th>
                        <th>Applied</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1;
                    while ($row = mysqli_fetch_assoc($result)): 
                        $status_class = $row['status'] == 'approved' ? 'approved' : ($row['status'] == 'pending' ? 'pending' : 'rejected');
                    ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><strong><?php echo $row['fullname'] ?? ''; ?></strong></td>
                        <td><?php echo $row['email'] ?? ''; ?></td>
                        <td><?php echo $row['phone'] ?? ''; ?></td>
                        <td><?php echo $row['programme'] ?? ''; ?></td>
                        <td><?php echo $row['course'] ?? ''; ?></td>
                        <td><span class="status-badge <?php echo $status_class; ?>"><?php echo ucfirst($row['status'] ?? ''); ?></span></td>
                        <td>
                            <?php if (!empty($row['admission_no'])): ?>
                                <span class="admission-badge"><?php echo $row['admission_no']; ?></span>
                            <?php else: ?>
                                <span style="color:#aaa;">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo !empty($row['dob']) ? date('d/m/Y', strtotime($row['dob'])) : '-'; ?></td>
                        <td><?php echo $row['gender'] ?? '-'; ?></td>
                        <td><?php echo $row['guardian_name'] ?? '-'; ?></td>
                        <td><?php echo $row['state_of_origin'] ?? '-'; ?></td>
                        <td><?php echo $row['lga'] ?? '-'; ?></td>
                        <td><?php echo $row['religion'] ?? '-'; ?></td>
                        <td><?php echo $row['exam_type'] ?? '-'; ?></td>
                        <td><?php echo !empty($row['applied_at']) ? date('d/m/Y', strtotime($row['applied_at'])) : '-'; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">
                <i class="fas fa-inbox"></i>
                <p>No students found.</p>
                <p style="font-size:0.9rem;">Students will appear here once they apply.</p>
            </div>
            <?php endif; ?>
        </div>

    </div>

</body>
</html>