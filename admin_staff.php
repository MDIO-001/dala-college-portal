<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['role'] != 'admin') {
    header('Location: admin_login.php');
    exit();
}

$admin_name = $_SESSION['admin_name'];
$message = '';
$message_type = '';

// ============================================
// ADD STAFF
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_staff'])) {
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    
    if (empty($fullname) || empty($username) || empty($password)) {
        $message = '❌ Please fill all required fields.';
        $message_type = 'error';
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO admin (fullname, username, email, password, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $fullname, $username, $email, $hashed_password, $role);
        
        if ($stmt->execute()) {
            $message = '✅ Staff added successfully!';
            $message_type = 'success';
        } else {
            $message = '❌ Error: ' . $conn->error;
            $message_type = 'error';
        }
    }
}

// ============================================
// DELETE STAFF
// ============================================
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($id != $_SESSION['admin_id']) {
        $stmt = $conn->prepare("DELETE FROM admin WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = '✅ Staff deleted successfully!';
            $message_type = 'success';
        } else {
            $message = '❌ Error: ' . $conn->error;
            $message_type = 'error';
        }
    } else {
        $message = '❌ You cannot delete your own account.';
        $message_type = 'error';
    }
}

// ============================================
// GET ALL STAFF
// ============================================
$staff_result = mysqli_query($conn, "SELECT * FROM admin ORDER BY id");
$staff_list = [];
while ($row = mysqli_fetch_assoc($staff_result)) {
    $staff_list[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Staff - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f4f8;
            color: #1a2e1a;
            padding: 20px;
        }
        .container { max-width: 1100px; margin: 0 auto; }
        
        .topbar {
            background: #0d2818;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .topbar .logo-title { color: #ffd54f; font-size: 1.3rem; font-weight: 800; }
        .topbar .logo-title span { color: #a5d6a7; }
        .topbar nav a {
            color: #c8e6c9; text-decoration: none; padding: 8px 18px; border-radius: 25px; font-size: 0.9rem;
        }
        .topbar nav a:hover { background: #2e7d32; }
        .topbar nav .active { background: #ffd54f; color: #0d2818 !important; font-weight: 700; }
        .topbar nav .logout { background: #c62828; color: white !important; }
        .topbar nav .logout:hover { background: #b71c1c; }
        .topbar .admin-badge {
            background: #c62828;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .card h3 { color: #0d2818; margin-bottom: 15px; }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #1a2e1a;
            margin-bottom: 5px;
            font-size: 0.85rem;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #dce8dc;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.3s;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #2e7d32;
            outline: none;
        }
        .form-group .full-width { grid-column: 1 / -1; }
        
        .btn {
            padding: 10px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary { background: #2e7d32; color: white; }
        .btn-primary:hover { background: #1b5e20; }
        .btn-danger { background: #c62828; color: white; }
        .btn-danger:hover { background: #b71c1c; }
        .btn-edit { background: #ffa000; color: white; }
        .btn-edit:hover { background: #f57c00; }
        
        .message {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .message.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }
        .message.error { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }
        
        .table-container {
            overflow: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th {
            background: #0d2818;
            color: white;
            padding: 10px 12px;
            text-align: left;
            font-size: 0.8rem;
            text-transform: uppercase;
        }
        table td {
            padding: 8px 12px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 0.85rem;
        }
        table tr:hover { background: #f8faf8; }
        .role-badge {
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .role-badge.admin { background: #c62828; color: white; }
        .role-badge.staff { background: #1976d2; color: white; }
        
        footer {
            background: #0d2818;
            color: #a5d6a7;
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .topbar { flex-direction: column; gap: 10px; text-align: center; }
            .topbar nav { flex-wrap: wrap; justify-content: center; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Topbar -->
        <div class="topbar">
            <div>
                <span class="logo-title">DALA <span>COLLEGE</span></span>
                <span style="display:block; font-size:0.6rem; color:#c8e6c9;">Admin Panel</span>
            </div>
            <nav>
                <a href="admin_dashboard.php">Dashboard</a>
                <a href="admin_registrations.php">Registrations</a>
                <a href="admin_students.php">Students</a>
                <a href="admin_staff.php" class="active">Staff</a>
                <a href="admin_change_password.php">Change Password</a>
                <a href="logout.php" class="logout">Logout</a>
            </nav>
            <div>
                <span class="admin-badge">👤 <?php echo htmlspecialchars($admin_name); ?></span>
            </div>
        </div>

        <!-- Message -->
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- Add Staff -->
        <div class="card">
            <h3>➕ Add New Staff</h3>
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="fullname" placeholder="Enter full name" required>
                    </div>
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="username" placeholder="Enter username" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="Enter email address">
                    </div>
                    <div class="form-group">
                        <label>Password *</label>
                        <input type="password" name="password" placeholder="Enter password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role">
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex; align-items:flex-end;">
                        <button type="submit" name="add_staff" class="btn btn-primary">➕ Add Staff</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Staff List -->
        <div class="card">
            <h3>👤 Staff List</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($staff_list)): ?>
                            <tr><td colspan="6" style="text-align:center; padding:20px; color:#999;">No staff found.</td></tr>
                        <?php else: ?>
                            <?php $i = 1; foreach ($staff_list as $staff): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo htmlspecialchars($staff['fullname']); ?></td>
                                <td><?php echo htmlspecialchars($staff['username']); ?></td>
                                <td><?php echo htmlspecialchars($staff['email'] ?? '-'); ?></td>
                                <td><span class="role-badge <?php echo $staff['role']; ?>"><?php echo ucfirst($staff['role']); ?></span></td>
                                <td>
                                    <?php if ($staff['id'] != $_SESSION['admin_id']): ?>
                                        <a href="admin_staff.php?delete=<?php echo $staff['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this staff member?')">Delete</a>
                                    <?php else: ?>
                                        <span style="color:#999; font-size:0.75rem;">(You)</span>
                                    <?php endif; ?>
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