<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$student_id = intval($_POST['student_id'] ?? 0);
$document_type = mysqli_real_escape_string($conn, $_POST['document_type'] ?? '');
$action_type = mysqli_real_escape_string($conn, $_POST['action_type'] ?? 'download');
$notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
$user_id = intval($_SESSION['user_id']);

if ($student_id > 0 && !empty($document_type)) {
    $sql = "INSERT INTO download_logs (student_id, document_type, action_type, downloaded_by, notes) 
            VALUES ($student_id, '$document_type', '$action_type', $user_id, '$notes')";
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
}
?>