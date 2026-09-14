<?php
session_start();
include 'connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    // 1. Duba table ɗin admins
    $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
    
    // Idan table ɗin babu, sai a nuna kuskure
    if ($stmt === false) {
        die("❌ Database Error: Table 'admins' not found. Please run the SQL setup.");
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();

    // 2. Duba idan an sami admin
    if ($admin) {
        // A) Idan an yi amfani da password_hash
        if (password_verify($password, $admin['password'])) {
            $_SESSION['user_id'] = $admin['id'];
            $_SESSION['role'] = 'admin';
            $_SESSION['fullname'] = $admin['fullname'];
            header('Location: admin_dashboard.php');
            exit();
        }
        // B) Fallback: Idan password ɗin yana cikin plain text (kamar admin123)
        elseif ($admin['password'] === $password) {
            $_SESSION['user_id'] = $admin['id'];
            $_SESSION['role'] = 'admin';
            $_SESSION['fullname'] = $admin['fullname'];
            header('Location: admin_dashboard.php');
            exit();
        } else {
            $error = "❌ Invalid password.";
        }
    } else {
        // 3. Idan babu admin a table ɗin admins, sai mu duba table ɗin users (domin tsarin da ka yi a farko)
        $user_stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin'");
        if ($user_stmt !== false) {
            $user_stmt->bind_param("s", $username);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            $user = $user_result->fetch_assoc();
            
            if ($user) {
                if (password_verify($password, $user['password']) || $user['password'] === $password) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = 'admin';
                    $_SESSION['fullname'] = $user['fullname'] ?? 'Admin';
                    header('Location: admin_dashboard.php');
                    exit();
                }
            }
        }
        
        $error = "❌ Admin account not found. Please create one.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Dala College</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0d2818; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-box { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); width: 400px; }
        .login-box h2 { text-align: center; color: #0d2818; margin-bottom: 30px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        .form-group input { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px; }
        .btn { width: 100%; padding: 12px; background: #2e7d32; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; }
        .btn:hover { background: #1b5e20; }
        .error { background: #ffebee; color: #c62828; padding: 10px; border-radius: 6px; margin-bottom: 15px; text-align: center; }
        .back-link { text-align: center; margin-top: 15px; }
        .back-link a { color: #6a8f6a; text-decoration: none; font-size: 14px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>🔐 Admin Login</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter admin username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter admin password" required>
            </div>
            <button type="submit" class="btn">Login</button>
        </form>
        <div class="back-link">
            <a href="index.php">← Back to Home</a>
        </div>
    </div>
</body>
</html>