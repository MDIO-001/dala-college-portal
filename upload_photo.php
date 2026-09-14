<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$student_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['photo'])) {
    $target_dir = "uploads/";
    
    // Create uploads directory if it doesn't exist
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $imageFileType = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    $photo_name = time() . '_' . bin2hex(random_bytes(8)) . '.' . $imageFileType;
    $target_file = $target_dir . $photo_name;
    
    // Check if image file is actual image
    $check = getimagesize($_FILES['photo']['tmp_name']);
    if ($check === false) {
        echo json_encode(['success' => false, 'message' => 'File is not an image']);
        exit();
    }
    
    // Allow certain file formats
    if (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
        echo json_encode(['success' => false, 'message' => 'Only JPG, JPEG, PNG & GIF files are allowed']);
        exit();
    }
    
    // Check file size (max 5MB)
    if ($_FILES['photo']['size'] > 5000000) {
        echo json_encode(['success' => false, 'message' => 'File is too large (max 5MB)']);
        exit();
    }
    
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
        // Update student record with photo name
        $query = "UPDATE students SET photo = '$photo_name' WHERE id = '$student_id'";
        if (mysqli_query($conn, $query)) {
            echo json_encode(['success' => true, 'message' => 'Photo uploaded successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Error uploading file']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
}
?>