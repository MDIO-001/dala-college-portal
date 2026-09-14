<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);
    $query = mysqli_query($conn, "SELECT * FROM students WHERE id = $id");
    
    if ($query && mysqli_num_rows($query) > 0) {
        $student = mysqli_fetch_assoc($query);
        echo json_encode([
            'success' => true,
            'id' => $student['id'],
            'fullname' => $student['fullname'] ?? '',
            'username' => $student['username'] ?? '',
            'email' => $student['email'] ?? '',
            'phone' => $student['phone'] ?? '',
            'programme' => $student['programme'] ?? 'NCE',
            'course' => $student['course'] ?? '',
            'level' => $student['level'] ?? 'NCE I',
            'status' => $student['status'] ?? 'pending'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
}
?>