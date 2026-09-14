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
// GET ALL USERS
// ============================================
$students_query = "SELECT id, fullname, admission_no, email, 'student' as type FROM students ORDER BY fullname";
$students_result = mysqli_query($conn, $students_query);
$students_list = [];
while ($row = mysqli_fetch_assoc($students_result)) {
    $students_list[] = $row;
}

$staff_query = "SELECT id, fullname, username, email, 'staff' as type FROM admin ORDER BY fullname";
$staff_result = mysqli_query($conn, $staff_query);
$staff_list = [];
while ($row = mysqli_fetch_assoc($staff_result)) {
    $staff_list[] = $row;
}

// ============================================
// PROCESS PASSWORD CHANGE
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $user_id = intval($_POST['user_id']);
    $user_type = mysqli_real_escape_string($conn, $_POST['user_type']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($user_id) || empty($user_type)) {
        $message = '❌ Please select a user.';
        $message_type = 'error';
    } elseif (empty($new_password) || empty($confirm_password)) {
        $message = '❌ Please enter both password fields.';
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = '❌ Passwords do not match.';
        $message_type = 'error';
    } elseif (strlen($new_password) < 6) {
        $message = '❌ Password must be at least 6 characters.';
        $message_type = 'error';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        if ($user_type == 'student') {
            $stmt = $conn->prepare("UPDATE students SET password = ? WHERE id = ?");
        } else {
            $stmt = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
        }
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            // Get user name for success message
            $name = '';
            if ($user_type == 'student') {
                $name_stmt = $conn->prepare("SELECT fullname FROM students WHERE id = ?");
                $name_stmt->bind_param("i", $user_id);
                $name_stmt->execute();
                $name_result = $name_stmt->get_result();
                $name_row = $name_result->fetch_assoc();
                $name = $name_row['fullname'] ?? 'Student';
            } else {
                $name_stmt = $conn->prepare("SELECT fullname FROM admin WHERE id = ?");
                $name_stmt->bind_param("i", $user_id);
                $name_stmt->execute();
                $name_result = $name_stmt->get_result();
                $name_row = $name_result->fetch_assoc();
                $name = $name_row['fullname'] ?? 'Staff';
            }
            
            $message = '✅ Password changed successfully for <strong>' . htmlspecialchars($name) . '</strong>!';
            $message_type = 'success';
        } else {
            $message = '❌ Error: ' . $conn->error;
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Admin Panel</title>
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
            transition: all 0.3s ease;
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
            font-weight: 600;
        }
        
        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .card h2 {
            color: #0d2818;
            margin-bottom: 5px;
        }
        .card .sub {
            color: #6a8f6a;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #1a2e1a;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        .form-group select,
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #dce8dc;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            font-family: inherit;
        }
        .form-group select:focus,
        .form-group input:focus {
            border-color: #2e7d32;
            outline: none;
        }
        .form-group .hint {
            font-size: 0.8rem;
            color: #6a8f6a;
            margin-top: 5px;
        }
        
        .user-info {
            background: #f8faf8;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #2e7d32;
            display: none;
        }
        .user-info.show {
            display: block;
        }
        .user-info strong {
            color: #0d2818;
        }
        .user-info .type-badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .user-info .type-badge.student {
            background: #bbdefb;
            color: #0d47a1;
        }
        .user-info .type-badge.staff {
            background: #ffcdd2;
            color: #c62828;
        }
        
        .btn {
            padding: 12px 40px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #2e7d32;
            color: white;
        }
        .btn-primary:hover {
            background: #1b5e20;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }
        .btn-secondary:hover {
            background: #bdbdbd;
        }
        
        .message {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .message.success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }
        .message.error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #c62828;
        }
        
        .password-strength {
            height: 4px;
            border-radius: 4px;
            margin-top: 5px;
            transition: all 0.3s ease;
            background: #e0e0e0;
        }
        .password-strength.weak { background: #c62828; width: 33%; }
        .password-strength.medium { background: #ffa000; width: 66%; }
        .password-strength.strong { background: #2e7d32; width: 100%; }
        
        footer {
            background: #0d2818;
            color: #a5d6a7;
            text-align: center;
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .topbar {
                flex-direction: column;
                gap: 10px;
                text-align: center;
                padding: 15px;
            }
            .topbar nav {
                flex-wrap: wrap;
                justify-content: center;
            }
            .card { padding: 20px; }
            .btn { width: 100%; text-align: center; }
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
                <a href="admin_registrations.php">Registrations</a>
                <a href="admin_students.php">Students</a>
                <a href="admin_staff.php">Staff</a>
                <a href="admin_change_password.php" class="active">Change Password</a>
                <a href="logout.php" class="logout">Logout</a>
            </nav>
            <div>
                <span class="admin-badge">👤 <?php echo htmlspecialchars($admin_name); ?></span>
            </div>
        </div>

        <!-- ========== CARD ========== -->
        <div class="card">
            <h2>🔐 Change User Password</h2>
            <p class="sub">Change password for students or staff members.</p>

            <!-- ========== MESSAGE ========== -->
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- ========== FORM ========== -->
            <form method="POST" action="" id="passwordForm">
                <div class="form-group">
                    <label for="user_id">👤 Select User</label>
                    <select name="user_id" id="user_id" required onchange="updateUserInfo()">
                        <option value="">-- Select User --</option>
                        <?php if (!empty($students_list)): ?>
                        <optgroup label="👨‍🎓 Students (<?php echo count($students_list); ?>)">
                            <?php foreach ($students_list as $student): ?>
                                <option value="<?php echo $student['id']; ?>" data-type="student" data-name="<?php echo htmlspecialchars($student['fullname']); ?>" data-extra="<?php echo htmlspecialchars($student['admission_no']); ?>">
                                    <?php echo htmlspecialchars($student['fullname']); ?> (<?php echo htmlspecialchars($student['admission_no']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endif; ?>
                        
                        <?php if (!empty($staff_list)): ?>
                        <optgroup label="👤 Staff (<?php echo count($staff_list); ?>)">
                            <?php foreach ($staff_list as $staff): ?>
                                <option value="<?php echo $staff['id']; ?>" data-type="staff" data-name="<?php echo htmlspecialchars($staff['fullname']); ?>" data-extra="<?php echo htmlspecialchars($staff['username']); ?>">
                                    <?php echo htmlspecialchars($staff['fullname']); ?> (<?php echo htmlspecialchars($staff['username']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endif; ?>
                    </select>
                    <input type="hidden" name="user_type" id="user_type" value="">
                </div>

                <!-- ========== USER INFO ========== -->
                <div class="user-info" id="userInfo">
                    <strong>Selected User:</strong> <span id="selectedName"></span>
                    <span class="type-badge" id="selectedType"></span>
                    <br>
                    <small style="color:#6a8f6a;" id="selectedExtra"></small>
                </div>

                <!-- ========== NEW PASSWORD ========== -->
                <div class="form-group">
                    <label for="new_password">🔒 New Password</label>
                    <input type="password" name="new_password" id="new_password" placeholder="Enter new password (min 6 characters)" required minlength="6" onkeyup="checkPasswordStrength()">
                    <div class="password-strength" id="passwordStrength"></div>
                    <div class="hint">Password must be at least 6 characters long.</div>
                </div>

                <!-- ========== CONFIRM PASSWORD ========== -->
                <div class="form-group">
                    <label for="confirm_password">🔒 Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password" required onkeyup="checkPasswordMatch()">
                    <div class="hint" id="passwordMatch"></div>
                </div>

                <!-- ========== BUTTONS ========== -->
                <div style="display:flex; gap:15px; flex-wrap:wrap; margin-top:10px;">
                    <button type="submit" name="change_password" class="btn btn-primary" id="submitBtn">
                        🔄 Change Password
                    </button>
                    <button type="reset" class="btn btn-secondary" onclick="resetForm()">
                        ↩️ Reset
                    </button>
                </div>
            </form>
        </div>

        <!-- ========== FOOTER ========== -->
        <footer>
            <p>© <?php echo date('Y'); ?> Dala College Kano. All Rights Reserved.</p>
        </footer>
        
    </div>

    <script>
        // ============================================
        // UPDATE USER INFO
        // ============================================
        function updateUserInfo() {
            var select = document.getElementById('user_id');
            var userType = document.getElementById('user_type');
            var userInfo = document.getElementById('userInfo');
            var selectedName = document.getElementById('selectedName');
            var selectedType = document.getElementById('selectedType');
            var selectedExtra = document.getElementById('selectedExtra');
            
            var selectedOption = select.options[select.selectedIndex];
            if (selectedOption.value) {
                userType.value = selectedOption.dataset.type;
                selectedName.textContent = selectedOption.dataset.name;
                selectedType.textContent = selectedOption.dataset.type.toUpperCase();
                selectedType.className = 'type-badge ' + selectedOption.dataset.type;
                selectedExtra.textContent = selectedOption.dataset.type == 'student' ? 
                    'Admission No: ' + selectedOption.dataset.extra : 
                    'Username: ' + selectedOption.dataset.extra;
                userInfo.className = 'user-info show';
            } else {
                userInfo.className = 'user-info';
            }
        }

        // ============================================
        // PASSWORD STRENGTH CHECK
        // ============================================
        function checkPasswordStrength() {
            var password = document.getElementById('new_password').value;
            var strengthBar = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthBar.className = 'password-strength';
                return;
            }
            
            var strength = 0;
            if (password.length >= 6) strength++;
            if (password.length >= 10) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            if (strength <= 2) {
                strengthBar.className = 'password-strength weak';
            } else if (strength <= 3) {
                strengthBar.className = 'password-strength medium';
            } else {
                strengthBar.className = 'password-strength strong';
            }
            
            checkPasswordMatch();
        }

        // ============================================
        // PASSWORD MATCH CHECK
        // ============================================
        function checkPasswordMatch() {
            var password = document.getElementById('new_password').value;
            var confirm = document.getElementById('confirm_password').value;
            var matchDiv = document.getElementById('passwordMatch');
            var submitBtn = document.getElementById('submitBtn');
            
            if (confirm.length === 0) {
                matchDiv.textContent = '';
                submitBtn.disabled = false;
                return;
            }
            
            if (password === confirm) {
                matchDiv.textContent = '✅ Passwords match!';
                matchDiv.style.color = '#2e7d32';
                submitBtn.disabled = false;
            } else {
                matchDiv.textContent = '❌ Passwords do not match';
                matchDiv.style.color = '#c62828';
                submitBtn.disabled = true;
            }
        }

        // ============================================
        // RESET FORM
        // ============================================
        function resetForm() {
            document.getElementById('user_id').value = '';
            document.getElementById('user_type').value = '';
            document.getElementById('userInfo').className = 'user-info';
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_password').value = '';
            document.getElementById('passwordStrength').className = 'password-strength';
            document.getElementById('passwordMatch').textContent = '';
            document.getElementById('submitBtn').disabled = false;
        }

        // ============================================
        // ON LOAD
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            var select = document.getElementById('user_id');
            if (select.value) {
                updateUserInfo();
            }
        });
    </script>
</body>
</html>