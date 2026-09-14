<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);
    $query = mysqli_query($conn, "SELECT * FROM staff WHERE id = $id");
    
    if ($query && mysqli_num_rows($query) > 0) {
        $staff = mysqli_fetch_assoc($query);
        echo json_encode([
            'success' => true,
            'id' => $staff['id'],
            'fullname' => $staff['fullname'],
            'email' => $staff['email'] ?? '',
            'phone' => $staff['phone'] ?? '',
            'username' => $staff['username'],
            'role' => $staff['role'] ?? 'Staff'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Staff not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
}
?>