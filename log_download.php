<?php
// ============================================
// LOG DOWNLOAD - Dala College
// ============================================
session_start();
include 'connect.php';

// ============================================
// HEADERS
// ============================================
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// ============================================
// CHECK AUTHENTICATION
// ============================================
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized - Please login'
    ]);
    exit();
}

// ============================================
// GET DATA
// ============================================
$student_id = intval($_POST['student_id'] ?? 0);
$document_type = mysqli_real_escape_string($conn, $_POST['document_type'] ?? '');
$action_type = mysqli_real_escape_string($conn, $_POST['action_type'] ?? 'print');
$notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
$user_id = intval($_SESSION['user_id']);

// ============================================
// VALIDATE
// ============================================
if ($student_id <= 0 || empty($document_type)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid data - student_id and document_type are required'
    ]);
    exit();
}

// ============================================
// VALID DOCUMENT TYPES (GYARAN - an ƙara tp_posting_letter)
// ============================================
$valid_documents = [
    'admission_letter', 'acceptance_letter', 'student_id_card',
    'exam_card', 'result_slip', 'result_slip_pro', 'transcript',
    'statement_of_result', 'final_result', 'introductory_letter',
    'posting_letter', 'tp_posting_letter', 'tp_result', 
    'course_registration', 'application_form', 'payment_receipt',
    'semester_result', 'id_card'
];

if (!in_array($document_type, $valid_documents)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid document type: ' . $document_type
    ]);
    exit();
}

// ============================================
// VALID ACTION TYPES
// ============================================
if (!in_array($action_type, ['print', 'download', 'view'])) {
    $action_type = 'print';
}

// ============================================
// GET CLIENT INFO
// ============================================
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
} elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $ip_address = $_SERVER['HTTP_CLIENT_IP'];
}

$user_agent = mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
$ip_address = mysqli_real_escape_string($conn, $ip_address);

// ============================================
// CHECK IF STUDENT EXISTS
// ============================================
$check_student = mysqli_query($conn, "SELECT id FROM students WHERE id = $student_id LIMIT 1");
if (!$check_student || mysqli_num_rows($check_student) == 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Student not found'
    ]);
    exit();
}

// ============================================
// CHECK IF download_logs TABLE EXISTS (auto-create)
// ============================================
$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'download_logs'");
if (!$check_table || mysqli_num_rows($check_table) == 0) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS download_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        document_type VARCHAR(50) NOT NULL,
        action_type VARCHAR(20) DEFAULT 'download',
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_student (student_id),
        INDEX idx_document (document_type),
        INDEX idx_date (downloaded_at)
    )");
}

// ============================================
// CHECK IF security_logs TABLE EXISTS (auto-create)
// ============================================
$check_security_table = mysqli_query($conn, "SHOW TABLES LIKE 'security_logs'");
if (!$check_security_table || mysqli_num_rows($check_security_table) == 0) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS security_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        event_type VARCHAR(50) NOT NULL,
        details TEXT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_event (event_type),
        INDEX idx_date (created_at)
    )");
}

// ============================================
// INSERT LOG
// ============================================
$sql = "INSERT INTO download_logs 
        (student_id, document_type, action_type, ip_address, user_agent, downloaded_at) 
        VALUES 
        ($student_id, '$document_type', '$action_type', '$ip_address', '$user_agent', NOW())";

if (mysqli_query($conn, $sql)) {
    $log_id = mysqli_insert_id($conn);
    
    // Log security event (idan table ɗin yana nan)
    $security_details = "User ID: $user_id | Student ID: $student_id | Document: $document_type | Action: $action_type";
    $security_details_esc = mysqli_real_escape_string($conn, $security_details);
    
    mysqli_query($conn, "INSERT INTO security_logs 
                         (user_id, event_type, details, ip_address, user_agent, created_at) 
                         VALUES 
                         ($user_id, 'document_access', '$security_details_esc', '$ip_address', '$user_agent', NOW())");
    
    echo json_encode([
        'success' => true,
        'message' => 'Log saved successfully',
        'log_id' => $log_id,
        'document_type' => $document_type,
        'action_type' => $action_type
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . mysqli_error($conn)
    ]);
}
?>