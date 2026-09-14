<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = $_POST['password'];
    $role = isset($_POST['role']) ? $_POST['role'] : 'student';

    // ============================================
    // LOGIN NA ADMIN (daga staff table, role=admin)
    // ============================================
    if ($role == 'admin') {
        $query = "SELECT * FROM staff WHERE username = '$username' AND role = 'admin'";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            
            if (password_verify($password, $user['password']) || $user['password'] == $password) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['fullname'] = $user['fullname'] ?? $user['full_name'];
                $_SESSION['role'] = 'admin';
                $_SESSION['position'] = 'System Administrator';
                header('Location: admin_dashboard.php');
                ob_end_flush();
                exit();
            } else {
                $error = '❌ Invalid admin password.';
            }
        } else {
            $error = '❌ Admin account not found.';
        }
    }
    
    // ============================================
    // LOGIN NA STAFF - AINIHIN ROLE DAGA DATABASE
    // ============================================
    elseif ($role == 'staff') {
        $query = "SELECT * FROM staff WHERE username = '$username'";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            
            if (password_verify($password, $user['password']) || $user['password'] == $password) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['fullname'] = $user['fullname'] ?? $user['full_name'];
                
                // ============================================
                // MUHIMMI: Ɗauko AINIHIN ROLE daga database
                // ============================================
                $_SESSION['role'] = $user['role']; // 'Provost', 'Exam Officer', 'Accountant', da sauransu
                $_SESSION['position'] = $user['position'] ?? $user['role'];
                
                header('Location: staff_dashboard.php');
                ob_end_flush();
                exit();
            } else {
                $error = '❌ Invalid staff password.';
            }
        } else {
            $error = '❌ Staff account not found.';
        }
    }
    
    // ============================================
    // LOGIN NA STUDENT
    // ============================================
    elseif ($role == 'student') {
        $query = "SELECT * FROM students WHERE username = '$username' OR email = '$username' OR phone = '$username'";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            
            if (password_verify($password, $user['password']) || $user['password'] == $password) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = 'student';
                header('Location: student_dashboard.php');
                ob_end_flush();
                exit();
            } else {
                $error = '❌ Invalid student password.';
            }
        } else {
            $error = '❌ Student not found.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Dala College</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f5f0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .login-box {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 4px 30px rgba(0,0,0,0.1);
            width: 420px;
        }
        h2 { text-align: center; color: #0d2818; font-size: 1.8rem; }
        .sub { text-align: center; color: #6a8f6a; margin-bottom: 25px; }
        
        .role-selector {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 20px;
            background: #f0f4f8;
            padding: 5px;
            border-radius: 30px;
        }
        .role-selector label {
            flex: 1;
            text-align: center;
            padding: 10px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .role-selector input[type="radio"] { display: none; }
        .role-selector input[type="radio"]:checked + label {
            background: #2e7d32;
            color: white;
        }

        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-weight: 600; color: #1a2e1a; margin-bottom: 5px; }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #dce8dc;
            border-radius: 8px;
            font-size: 1rem;
        }
        .form-group input:focus {
            border-color: #2e7d32;
            outline: none;
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: #2e7d32;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
        }
        .btn:hover { background: #1b5e20; }
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
        }
        .topbar {
            background: #0d2818;
            padding: 15px 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .topbar .logo-title { color: #ffd54f; font-size: 1.3rem; font-weight: 800; }
        .topbar .logo-title span { color: #a5d6a7; }
        .topbar .logo-sub { display: block; font-size: 0.6rem; color: #c8e6c9; }
        .topbar a {
            color: #0d2818;
            text-decoration: none;
            padding: 8px 18px;
            border-radius: 25px;
            background: #ffd54f;
            font-weight: 700;
        }
        .links { text-align: center; margin-top: 18px; }
        .links a { color: #2e7d32; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>

    <div class="topbar">
        <div>
            <span class="logo-title">DALA <span>COLLEGE</span></span>
            <span class="logo-sub">of Education, Kano</span>
        </div>
        <a href="index.html">Home</a>
    </div>

    <div class="login-box">
        <h2>Portal Login</h2>
        <p class="sub">Sign in to your account</p>

        <?php if (!empty($error)): ?>
            <div class="error">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="role-selector">
                <input type="radio" name="role" id="role_student" value="student" checked>
                <label for="role_student">🎓 Student</label>
                
                <input type="radio" name="role" id="role_staff" value="staff">
                <label for="role_staff">👨‍🏫 Staff</label>
                
                <input type="radio" name="role" id="role_admin" value="admin">
                <label for="role_admin">👨‍💼 Admin</label>
            </div>

            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter your username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="btn">Login</button>
        </form>

        <div class="links">
            <p>Don't have an account? <a href="apply.php">Apply Now</a></p>
        </div>
    </div>

</body>
</html>