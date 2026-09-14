<?php
session_start();
include 'connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $query = "SELECT * FROM staff WHERE username = '$username' AND password = '$password'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['role'] = 'staff';
        $_SESSION['staff_role'] = $user['role'];
        $_SESSION['can_accept'] = $user['can_accept'];
        $_SESSION['department'] = $user['department'] ?? '';
        
        header('Location: staff_dashboard.php');
        exit();
    } else {
        $error = 'Invalid Staff ID or Password.';
        header("Location: staff_login.html?error=" . urlencode($error));
        exit();
    }
}
?>