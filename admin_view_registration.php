<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['role'] != 'admin') {
    header('Location: admin_login.php');
    exit();
}

$admin_name = $_SESSION['admin_name'];
$admin_id = $_SESSION['admin_id'];
$registration_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$registration_id) {
    header('Location: admin_registrations.php');
    exit();
}

// Get registration details
$stmt = $conn->prepare("
    SELECT cr.*, s.fullname, s.admission_no, s.course, s.programme, s.level as student_level
    FROM course_registrations cr 
    JOIN students s ON cr.student_id = s.id 
    WHERE cr.id = ?
");
$stmt->bind_param("i", $registration_id);
$stmt->execute();
$result = $stmt->get_result();
$registration = $result->fetch_assoc();

if (!$registration) {
    header('Location: admin_registrations.php');
    exit();
}

// Get course details
$course_codes = explode(',', $registration['courses']);
$course_details = [];
$total_units = 0;
foreach ($course_codes as $code) {
    $course_details[] = [
        'code' => $code,
        'title' => $code,
        'unit' => 2,
        'status' => 'Compulsory'
    ];
    $total_units += 2;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Registration - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f4f8;
            color: #1a2e1a;
            padding: 20px;
        }
        .container { max-width: 900px; margin: 0 auto; }
        
        .topbar {
            background: #0d2818;
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .topbar .logo-title { color: #ffd54f; font-size: 1.3rem; font-weight: 800; }
        .topbar .logo-title span { color: #a5d6a7; }
        .topbar .logo-sub { display: block; font-size: 0.55rem; color: #c8e6c9; }
        .topbar nav a {
            color: #c8e6c9; text-decoration: none; padding: 8px 16px; border-radius: 25px; font-size: 0.85rem;
        }
        .topbar nav a:hover { background: #2e7d32; }
        .topbar nav .active { background: #ffd54f; color: #0d2818 !important; font-weight: 700; }
        .topbar nav .logout { background: #c62828; color: white !important; }
        .topbar nav .logout:hover { background: #b71c1c; }
        .topbar .admin-badge {
            background: #c62828;
            color: white;
            padding: 6px 18px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .card {
            background: white;
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .card-header h2 { color: #0d2818; }
        
        .status-badge {
            padding: 5px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .status-badge.pending { background: #ffa000; color: white; }
        .status-badge.approved { background: #2e7d32; color: white; }
        .status-badge.rejected { background: #c62828; color: white; }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        .info-grid .item .label { color: #6a8f6a; font-size: 0.7rem; text-transform: uppercase; font-weight: 600; }
        .info-grid .item .value { color: #0d2818; font-size: 1rem; font-weight: 600; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table th {
            background: #0d2818;
            color: white;
            padding: 10px 12px;
            text-align: left;
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        table td {
            padding: 8px 12px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 0.85rem;
        }
        table tr:nth-child(even) { background: #f8faf8; }
        table .total-row { background: #e8f5e9 !important; font-weight: 700; }
        table .total-row td { border-top: 2px solid #0d2818; }
        
        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        .btn {
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-approve { background: #2e7d32; color: white; }
        .btn-approve:hover { background: #1b5e20; }
        .btn-reject { background: #c62828; color: white; }
        .btn-reject:hover { background: #b71c1c; }
        .btn-back { background: #e0e0e0; color: #333; }
        .btn-back:hover { background: #bdbdbd; }
        .btn-print { background: #1976d2; color: white; }
        .btn-print:hover { background: #0d47a1; }
        
        footer {
            background: #0d2818;
            color: #a5d6a7;
            text-align: center;
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
        }
        
        .status-label {
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-label.compulsory { background: #ffcdd2; color: #c62828; }
        .status-label.elective { background: #bbdefb; color: #0d47a1; }
        
        @media (max-width: 768px) {
            .topbar { flex-direction: column; gap: 10px; text-align: center; }
            .topbar nav { flex-wrap: wrap; justify-content: center; }
            .info-grid { grid-template-columns: 1fr; }
            .card-header { flex-direction: column; gap: 10px; text-align: center; }
            .btn-group { justify-content: center; }
            .card { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        
        <!-- ========== TOPBAR ========== -->
        <div class="topbar">
            <div>
                <span class="logo-title">DALA <span>COLLEGE</span></span>
                <span class="logo-sub">Admin Panel</span>
            </div>
            <nav>
                <a href="admin_dashboard.php">Dashboard</a>
                <a href="admin_registrations.php" class="active">Registrations</a>
                <a href="admin_students.php">Students</a>
                <a href="admin_staff.php">Staff</a>
                <a href="admin_change_password.php">Change Password</a>
                <a href="logout.php" class="logout">Logout</a>
            </nav>
            <div>
                <span class="admin-badge">👤 <?php echo htmlspecialchars($admin_name); ?></span>
            </div>
        </div>

        <!-- ========== CARD ========== -->
        <div class="card">
            <div class="card-header">
                <h2>📋 Registration Details</h2>
                <span class="status-badge <?php echo $registration['status']; ?>">
                    <?php echo strtoupper($registration['status']); ?>
                </span>
            </div>

            <!-- ========== INFO GRID ========== -->
            <div class="info-grid">
                <div class="item">
                    <div class="label">Admission Number</div>
                    <div class="value"><?php echo htmlspecialchars($registration['admission_no']); ?></div>
                </div>
                <div class="item">
                    <div class="label">Student Name</div>
                    <div class="value"><?php echo htmlspecialchars($registration['fullname']); ?></div>
                </div>
                <div class="item">
                    <div class="label">Department</div>
                    <div class="value"><?php echo htmlspecialchars($registration['course']); ?></div>
                </div>
                <div class="item">
                    <div class="label">Programme</div>
                    <div class="value"><?php echo htmlspecialchars($registration['programme']); ?></div>
                </div>
                <div class="item">
                    <div class="label">Level</div>
                    <div class="value"><?php echo htmlspecialchars($registration['level']); ?></div>
                </div>
                <div class="item">
                    <div class="label">Semester</div>
                    <div class="value"><?php echo htmlspecialchars($registration['semester']); ?> Semester</div>
                </div>
                <div class="item">
                    <div class="label">Registration Date</div>
                    <div class="value"><?php echo date('F j, Y g:i A', strtotime($registration['registration_date'])); ?></div>
                </div>
                <div class="item">
                    <div class="label">Status</div>
                    <div class="value">
                        <span class="status-badge <?php echo $registration['status']; ?>">
                            <?php echo ucfirst($registration['status']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- ========== COURSES TABLE ========== -->
            <h3 style="color:#0d2818; margin:15px 0 10px;">📖 Registered Courses</h3>
            <table>
                <thead>
                    <tr>
                        <th style="text-align:center;">#</th>
                        <th>Course Code</th>
                        <th>Course Title</th>
                        <th style="text-align:center;">Unit</th>
                        <th style="text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($course_details as $course): ?>
                    <tr>
                        <td style="text-align:center;"><?php echo $i++; ?></td>
                        <td><strong><?php echo htmlspecialchars($course['code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($course['title']); ?></td>
                        <td style="text-align:center;"><?php echo $course['unit']; ?></td>
                        <td style="text-align:center;">
                            <span class="status-label <?php echo strtolower($course['status']); ?>">
                                <?php echo $course['status']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="3" style="text-align:right; font-size:1rem;">TOTAL UNITS:</td>
                        <td style="text-align:center; font-size:1.1rem;"><?php echo $total_units; ?></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>

            <!-- ========== ACTIONS ========== -->
            <?php if ($registration['status'] == 'pending'): ?>
            <div class="btn-group">
                <form method="POST" action="admin_registrations.php" style="display:inline;">
                    <input type="hidden" name="registration_id" value="<?php echo $registration['id']; ?>">
                    <button type="submit" name="action" value="approve" class="btn btn-approve" onclick="return confirm('Approve this registration?')">✅ Approve</button>
                    <button type="submit" name="action" value="reject" class="btn btn-reject" onclick="return confirm('Reject this registration?')">❌ Reject</button>
                </form>
                <button class="btn btn-print" onclick="window.print()">🖨️ Print</button>
            </div>
            <?php else: ?>
            <div class="btn-group">
                <button class="btn btn-print" onclick="window.print()">🖨️ Print</button>
            </div>
            <?php endif; ?>

            <div style="margin-top:15px;">
                <a href="admin_registrations.php" class="btn btn-back">⬅️ Back to Registrations</a>
            </div>
        </div>

        <!-- ========== FOOTER ========== -->
        <footer>
            <p>© <?php echo date('Y'); ?> Dala College Kano. All Rights Reserved.</p>
        </footer>
        
    </div>
</body>
</html>