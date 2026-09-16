<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$student_id = intval($_POST['student_id'] ?? 0);
$document_type = mysqli_real_escape_string($conn, $_POST['document_type'] ?? '');
$status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'approved');

$allowed_types = ['admission_letter', 'acceptance_letter', 'introductory_letter', 'posting_letter'];
if (!in_array($document_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid document type']);
    exit();
}

if ($student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
    exit();
}

// ============================================
// UPLOAD FILE (Idan akwai)
// ============================================
$file_path = null;
if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] == 0) {
    $upload_dir = 'uploads/documents/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_ext = strtolower(pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png'];
    
    if (!in_array($file_ext, $allowed_ext)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only PDF, JPG, PNG allowed.']);
        exit();
    }
    
    $new_filename = $document_type . '_' . $student_id . '_' . time() . '.' . $file_ext;
    $file_path = $upload_dir . $new_filename;
    
    if (!move_uploaded_file($_FILES['document_file']['tmp_name'], $file_path)) {
        echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
        exit();
    }
}

// ============================================
// SAVE TO DATABASE
// ============================================
$admin_name = $_SESSION['fullname'] ?? 'Admin';

$sql = "INSERT INTO student_documents 
        (student_id, document_type, file_path, status, approved_at, approved_by)
        VALUES 
        ($student_id, '$document_type', " . ($file_path ? "'$file_path'" : "NULL") . ", '$status', NOW(), '$admin_name')
        ON DUPLICATE KEY UPDATE
        file_path = " . ($file_path ? "'$file_path'" : "file_path") . ",
        status = '$status',
        approved_at = NOW(),
        approved_by = '$admin_name'";

if (mysqli_query($conn, $sql)) {
    echo json_encode(['success' => true, 'message' => 'Document saved successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
}
?>