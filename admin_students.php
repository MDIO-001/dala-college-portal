<?php
session_start();
include 'connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] != 'admin') {
    header('Location: admin_login.php');
    exit();
}

$admin_name = $_SESSION['admin_name'];
$admin_id = $_SESSION['admin_id'];
$message = '';
$message_type = '';

// ============================================
// ACTIVATE / REJECT / PENDING STUDENT
// ============================================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];
    
    if ($action == 'activate') {
        $stmt = $conn->prepare("UPDATE students SET status = 'active' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = '✅ Student activated successfully!';
            $message_type = 'success';
        }
    } 
    elseif ($action == 'reject') {
        $stmt = $conn->prepare("UPDATE students SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = '⚠️ Student rejected.';
            $message_type = 'warning';
        }
    } 
    elseif ($action == 'pending') {
        $stmt = $conn->prepare("UPDATE students SET status = 'pending' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = '🕒 Student moved back to pending.';
            $message_type = 'warning';
        }
    }
}

// ============================================
// DELETE STUDENT
// ============================================
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = '🗑️ Student deleted successfully!';
        $message_type = 'success';
    } else {
        $message = '❌ Error: ' . $conn->error;
        $message_type = 'error';
    }
}

// ============================================
// IMPORT STUDENTS FROM CSV
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import_students'])) {
    
    if (isset($_FILES['student_file']) && $_FILES['student_file']['error'] == 0) {
        
        $file_name = $_FILES['student_file']['name'];
        $file_tmp = $_FILES['student_file']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_exts = ['csv', 'xlsx', 'xls'];
        
        if (!in_array($file_ext, $allowed_exts)) {
            $message = '❌ Please upload a CSV or Excel file.';
            $message_type = 'error';
        } else {
            
            $imported_count = 0;
            $error_count = 0;
            $errors = [];
            
            if ($file_ext == 'csv') {
                $file = fopen($file_tmp, 'r');
                $header = fgetcsv($file);
                
                while (($row = fgetcsv($file)) !== FALSE) {
                    if (empty(array_filter($row))) continue;
                    
                    $admission_no = trim($row[0] ?? '');
                    $fullname = trim($row[1] ?? '');
                    $email = trim($row[2] ?? '');
                    $phone = trim($row[3] ?? '');
                    $course = trim($row[4] ?? 'CSC/PHY');
                    $programme = trim($row[5] ?? 'NCE');
                    $level = trim($row[6] ?? 'NCE I');
                    $password = trim($row[7] ?? 'password123');
                    
                    if (empty($admission_no) || empty($fullname)) {
                        $error_count++;
                        $errors[] = "Missing admission number or name for row " . ($imported_count + $error_count + 1);
                        continue;
                    }
                    
                    $check = mysqli_query($conn, "SELECT id FROM students WHERE student_id = '$admission_no'");
                    if (mysqli_num_rows($check) > 0) {
                        $error_count++;
                        $errors[] = "Student with admission number '$admission_no' already exists.";
                        continue;
                    }
                    
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    $insert = mysqli_query($conn, "INSERT INTO students (student_id, reg_no, fullname, email, phone, course, programme, level, password, status, created_at) 
                                                   VALUES ('$admission_no', '$admission_no', '$fullname', '$email', '$phone', '$course', '$programme', '$level', '$hashed_password', 'active', NOW())");
                    
                    if ($insert) {
                        $imported_count++;
                    } else {
                        $error_count++;
                        $errors[] = "Error importing $admission_no: " . mysqli_error($conn);
                    }
                }
                fclose($file);
            } else {
                $message = '⚠️ For Excel files, please convert to CSV first.';
                $message_type = 'warning';
            }
            
            if ($imported_count > 0) {
                $message = "✅ Import successful! <br>
                            📊 Students imported: <strong>$imported_count</strong><br>
                            ❌ Errors: <strong>$error_count</strong>";
                if (!empty($errors)) {
                    $message .= "<br><br><strong>Errors:</strong><br>" . implode('<br>', array_slice($errors, 0, 5));
                }
                $message_type = 'success';
            } else {
                $message = "❌ No students were imported.";
                if (!empty($errors)) {
                    $message .= "<br><strong>Errors:</strong><br>" . implode('<br>', $errors);
                }
                $message_type = 'error';
            }
        }
    } else {
        $message = '❌ Please select a file to upload.';
        $message_type = 'error';
    }
}

// ============================================
// DOWNLOAD SAMPLE CSV
// ============================================
if (isset($_GET['download_sample'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="student_import_sample.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Admission No', 'Full Name', 'Email', 'Phone', 'Course', 'Programme', 'Level', 'Password']);
    fputcsv($output, ['DLCOE/NCE/26A0001/EIS001', 'Aliyu Musa', 'aliyu@example.com', '08012345678', 'CSC/PHY', 'NCE', 'NCE I', 'password123']);
    fputcsv($output, ['DLCOE/NCE/26A0002/EIS002', 'Aisha Bello', 'aisha@example.com', '08087654321', 'ENG/ECO', 'NCE', 'NCE I', 'password123']);
    fclose($output);
    exit();
}

// ============================================
// FILTER & SEARCH
// ============================================
$filter = isset($_GET['filter']) ? mysqli_real_escape_string($conn, $_GET['filter']) : 'all';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$where = "WHERE 1=1";
if ($filter == 'pending') {
    $where .= " AND status = 'pending'";
} elseif ($filter == 'active') {
    $where .= " AND status = 'active'";
} elseif ($filter == 'rejected') {
    $where .= " AND status = 'rejected'";
}

if (!empty($search)) {
    $where .= " AND (fullname LIKE '%$search%' OR phone LIKE '%$search%' OR student_id LIKE '%$search%')";
}

// ============================================
// GET STUDENTS
// ============================================
$students_query = "SELECT * FROM students $where ORDER BY id DESC";
$students_result = mysqli_query($conn, $students_query);
$students_list = [];
while ($row = mysqli_fetch_assoc($students_result)) {
    $students_list[] = $row;
}

// Counts
$count_all = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students"))['c'];
$count_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE status='pending'"))['c'];
$count_active = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE status='active'"))['c'];
$count_rejected = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE status='rejected'"))['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f4f8;
            color: #1a2e1a;
            padding: 20px;
        }
        .container { max-width: 1300px; margin: 0 auto; }
        
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
            transition: all 0.3s ease;
        }
        .topbar nav a:hover { background: #2e7d32; }
        .topbar nav .active { background: #ffd54f; color: #0d2818 !important; font-weight: 700; }
        .topbar nav .logout { background: #c62828; color: white !important; }
        .topbar .admin-badge {
            background: #c62828;
            color: white;
            padding: 6px 18px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .card h3 { color: #0d2818; margin-bottom: 15px; }
        
        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #2e7d32;
            transition: all 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card .number { font-size: 2rem; font-weight: 800; color: #2e7d32; }
        .stat-card .label { font-size: 0.8rem; color: #6a8f6a; margin-top: 5px; text-transform: uppercase; font-weight: 600; }
        .stat-card.pending { border-left-color: #f57c00; }
        .stat-card.pending .number { color: #f57c00; }
        .stat-card.rejected { border-left-color: #c62828; }
        .stat-card.rejected .number { color: #c62828; }
        
        /* Import */
        .import-area {
            background: #f8faf8;
            padding: 20px;
            border-radius: 8px;
            border: 2px dashed #2e7d32;
            text-align: center;
        }
        .import-area .icon { font-size: 2.5rem; margin-bottom: 10px; }
        .import-area input[type="file"] {
            padding: 10px;
            border: 1px solid #dce8dc;
            border-radius: 8px;
            background: white;
            width: 100%;
            max-width: 400px;
            margin: 10px auto;
        }
        .import-area .btn-import {
            padding: 10px 30px;
            background: #2e7d32;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
        }
        .import-area .btn-import:hover { background: #1b5e20; }
        .import-area .btn-sample {
            padding: 10px 25px;
            background: #1976d2;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .import-area .btn-sample:hover { background: #0d47a1; }
        
        .message {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .message.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }
        .message.error { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }
        .message.warning { background: #fff8e1; color: #e65100; border-left: 4px solid #ffa000; }
        
        /* Filter Bar */
        .filter-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-bar a {
            padding: 8px 18px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            background: #f0f4f8;
            color: #0d2818;
            transition: all 0.3s ease;
        }
        .filter-bar a:hover { background: #dce8dc; }
        .filter-bar a.active { background: #0d2818; color: #ffd54f; }
        .filter-bar form { display: flex; gap: 8px; margin-left: auto; }
        .filter-bar input[type="text"] {
            padding: 8px 15px;
            border: 2px solid #dce8dc;
            border-radius: 25px;
            font-size: 0.85rem;
            width: 250px;
        }
        .filter-bar input[type="text"]:focus { border-color: #2e7d32; outline: none; }
        .filter-bar button {
            padding: 8px 20px;
            background: #2e7d32;
            color: white;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .table-container { overflow: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
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
            padding: 10px 12px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 0.85rem;
        }
        table tr:hover { background: #f8faf8; }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-pending { background: #fff3e0; color: #e65100; }
        .badge-active { background: #e8f5e9; color: #2e7d32; }
        .badge-rejected { background: #ffebee; color: #c62828; }
        
        .btn {
            padding: 5px 12px;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            font-size: 0.7rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
            transition: all 0.2s ease;
        }
        .btn-activate { background: #2e7d32; color: white; }
        .btn-activate:hover { background: #1b5e20; }
        .btn-reject { background: #f57c00; color: white; }
        .btn-reject:hover { background: #e65100; }
        .btn-pending { background: #1976d2; color: white; }
        .btn-pending:hover { background: #0d47a1; }
        .btn-view { background: #616161; color: white; }
        .btn-view:hover { background: #424242; }
        .btn-danger { background: #c62828; color: white; }
        .btn-danger:hover { background: #b71c1c; }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        .empty-state .icon { font-size: 3rem; margin-bottom: 10px; }
        
        footer {
            background: #0d2818;
            color: #a5d6a7;
            text-align: center;
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .topbar { flex-direction: column; gap: 10px; text-align: center; }
            .topbar nav { flex-wrap: wrap; justify-content: center; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filter-bar form { margin-left: 0; width: 100%; }
            .filter-bar input[type="text"] { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="container">
        
        <div class="topbar">
            <div>
                <span class="logo-title">DALA <span>COLLEGE</span></span>
                <span class="logo-sub">Admin Panel</span>
            </div>
            <nav>
                <a href="admin_dashboard.php">Dashboard</a>
                <a href="admin_registrations.php">Registrations</a>
                <a href="admin_students.php" class="active">Students</a>
                <a href="admin_staff.php">Staff</a>
                <a href="admin_change_password.php">Change Password</a>
                <a href="logout.php" class="logout">Logout</a>
            </nav>
            <div>
                <span class="admin-badge">👤 <?php echo htmlspecialchars($admin_name); ?></span>
            </div>
        </div>

        <h2 style="color:#0d2818; margin-bottom:15px;">👨‍🎓 Manage Students</h2>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- ========== STATS ========== -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $count_all; ?></div>
                <div class="label">Total Students</div>
            </div>
            <div class="stat-card pending">
                <div class="number"><?php echo $count_pending; ?></div>
                <div class="label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $count_active; ?></div>
                <div class="label">Active</div>
            </div>
            <div class="stat-card rejected">
                <div class="number"><?php echo $count_rejected; ?></div>
                <div class="label">Rejected</div>
            </div>
        </div>

        <!-- ========== IMPORT ========== -->
        <div class="card">
            <h3>📤 Import Students from File</h3>
            <div class="import-area">
                <div class="icon">📄</div>
                <p style="color:#0d2818; font-weight:600; margin-bottom:5px;">
                    Upload CSV file with student data
                </p>
                <p style="color:#6a8f6a; font-size:0.85rem; margin-bottom:10px;">
                    Supported format: <strong>CSV</strong>
                </p>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="file" name="student_file" accept=".csv,.xlsx,.xls" required>
                    <br><br>
                    <button type="submit" name="import_students" class="btn-import">📤 Import Students</button>
                    <a href="admin_students.php?download_sample=1" class="btn-sample">📥 Download Sample CSV</a>
                </form>
            </div>
        </div>

        <!-- ========== FILTER BAR ========== -->
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; margin-bottom:15px;">
                <h3 style="margin-bottom:0;">👨‍🎓 Students List</h3>
            </div>
            
            <div class="filter-bar">
                <a href="?filter=all" class="<?php echo $filter == 'all' ? 'active' : ''; ?>">
                    All (<?php echo $count_all; ?>)
                </a>
                <a href="?filter=pending" class="<?php echo $filter == 'pending' ? 'active' : ''; ?>">
                    Pending (<?php echo $count_pending; ?>)
                </a>
                <a href="?filter=active" class="<?php echo $filter == 'active' ? 'active' : ''; ?>">
                    Active (<?php echo $count_active; ?>)
                </a>
                <a href="?filter=rejected" class="<?php echo $filter == 'rejected' ? 'active' : ''; ?>">
                    Rejected (<?php echo $count_rejected; ?>)
                </a>
                
                <form method="GET">
                    <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                    <input type="text" name="search" placeholder="Search name, phone, ID..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit">🔍</button>
                </form>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Admission No.</th>
                            <th>Full Name</th>
                            <th>Phone</th>
                            <th>Department</th>
                            <th>Level</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students_list)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <div class="icon">📭</div>
                                        <p>No students found.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $i = 1; foreach ($students_list as $student): 
                                $status = $student['status'] ?? 'pending';
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><strong><?php echo htmlspecialchars($student['student_id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($student['fullname']); ?></td>
                                <td><?php echo htmlspecialchars($student['phone'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($student['course'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($student['level'] ?? 'NCE I'); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $status; ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($status != 'active'): ?>
                                        <a href="?action=activate&id=<?php echo $student['id']; ?>" 
                                           class="btn btn-activate"
                                           onclick="return confirm('Activate this student?')">
                                            ✅ Activate
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($status != 'rejected'): ?>
                                        <a href="?action=reject&id=<?php echo $student['id']; ?>" 
                                           class="btn btn-reject"
                                           onclick="return confirm('Reject this student?')">
                                            ❌ Reject
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($status != 'pending'): ?>
                                        <a href="?action=pending&id=<?php echo $student['id']; ?>" 
                                           class="btn btn-pending"
                                           onclick="return confirm('Move back to pending?')">
                                            🕒 Pending
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="admin_view_student.php?id=<?php echo $student['id']; ?>" class="btn btn-view">👁️ View</a>
                                    <a href="?delete=<?php echo $student['id']; ?>" class="btn btn-danger" 
                                       onclick="return confirm('DELETE this student permanently?')">🗑️ Delete</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <footer>
            <p>© <?php echo date('Y'); ?> Dala College Kano. All Rights Reserved.</p>
        </footer>
        
    </div>
</body>
</html>