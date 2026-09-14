<?php
session_start();
include 'connect.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

$admin_name = $_SESSION['fullname'] ?? 'Admin';

// ============================================
// HANDLE ADD STUDENT
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_student'])) {
    
    $fullname = isset($_POST['fullname']) ? mysqli_real_escape_string($conn, trim($_POST['fullname'])) : '';
    $email = isset($_POST['email']) ? mysqli_real_escape_string($conn, trim($_POST['email'])) : '';
    $phone = isset($_POST['phone']) ? mysqli_real_escape_string($conn, trim($_POST['phone'])) : '';
    $programme = isset($_POST['programme']) ? mysqli_real_escape_string($conn, $_POST['programme']) : 'NCE';
    $course = isset($_POST['course']) ? mysqli_real_escape_string($conn, trim($_POST['course'])) : '';
    $study_centre = isset($_POST['study_centre']) ? mysqli_real_escape_string($conn, $_POST['study_centre']) : 'Shinge';
    $dob = isset($_POST['dob']) ? mysqli_real_escape_string($conn, $_POST['dob']) : '';
    $address = isset($_POST['address']) ? mysqli_real_escape_string($conn, trim($_POST['address'])) : '';
    $gender = isset($_POST['gender']) ? mysqli_real_escape_string($conn, $_POST['gender']) : '';
    $guardian_name = isset($_POST['guardian_name']) ? mysqli_real_escape_string($conn, trim($_POST['guardian_name'])) : '';
    $guardian_phone = isset($_POST['guardian_phone']) ? mysqli_real_escape_string($conn, trim($_POST['guardian_phone'])) : '';
    $state_of_origin = isset($_POST['state_of_origin']) ? mysqli_real_escape_string($conn, trim($_POST['state_of_origin'])) : '';
    $lga = isset($_POST['lga']) ? mysqli_real_escape_string($conn, trim($_POST['lga'])) : '';
    $nationality = isset($_POST['nationality']) ? mysqli_real_escape_string($conn, trim($_POST['nationality'])) : 'Nigerian';
    $religion = isset($_POST['religion']) ? mysqli_real_escape_string($conn, $_POST['religion']) : 'Islam';
    $marital_status = isset($_POST['marital_status']) ? mysqli_real_escape_string($conn, $_POST['marital_status']) : 'Single';
    $exam_type = isset($_POST['exam_type']) ? mysqli_real_escape_string($conn, $_POST['exam_type']) : 'WAEC';
    $admission_no = isset($_POST['admission_no']) ? mysqli_real_escape_string($conn, trim($_POST['admission_no'])) : '';
    
    if (empty($fullname) || empty($email) || empty($phone)) {
        $add_error = "❌ Full name, email and phone are required.";
    } else {
        $check_email = mysqli_query($conn, "SELECT id FROM students WHERE email = '$email'");
        if (mysqli_num_rows($check_email) > 0) {
            $add_error = "❌ Email <strong>'$email'</strong> already exists.";
        } else {
            if (!empty($admission_no)) {
                $check_admission = mysqli_query($conn, "SELECT id FROM students WHERE admission_no = '$admission_no'");
                if (mysqli_num_rows($check_admission) > 0) {
                    $add_error = "❌ Admission number <strong>'$admission_no'</strong> already exists.";
                }
            }
            
            if (!isset($add_error)) {
                if (empty($admission_no)) {
                    $year = date('y');
                    $count_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM students");
                    $count = mysqli_fetch_assoc($count_result)['total'] + 1;
                    $admission_no = "DLCOE/NCE/{$year}A" . str_pad($count, 4, '0', STR_PAD_LEFT) . "/EIS" . str_pad($count, 3, '0', STR_PAD_LEFT);
                }
                
                $password = password_hash('student123', PASSWORD_DEFAULT);
                $username = $email;
                
                $query = "INSERT INTO students (
                    admission_no, username, password, fullname, email, phone, 
                    programme, course, study_centre, dob, address, gender, 
                    guardian_name, guardian_phone, state_of_origin, lga, 
                    nationality, religion, marital_status, exam_type, status
                ) VALUES (
                    '$admission_no', '$username', '$password', '$fullname', '$email', '$phone',
                    '$programme', '$course', '$study_centre', '$dob', '$address', '$gender',
                    '$guardian_name', '$guardian_phone', '$state_of_origin', '$lga',
                    '$nationality', '$religion', '$marital_status', '$exam_type', 'pending'
                )";
                
                if (mysqli_query($conn, $query)) {
                    $add_success = "✅ Student added successfully!<br>
                                    📋 Admission No: <strong>$admission_no</strong><br>
                                    👤 Name: <strong>$fullname</strong><br>
                                    🔑 Password: <strong>student123</strong>";
                } else {
                    $add_error = "❌ Error: " . mysqli_error($conn);
                }
            }
        }
    }
}

// ============================================
// HANDLE EDIT STUDENT
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_student'])) {
    $student_id = isset($_POST['student_id']) ? mysqli_real_escape_string($conn, $_POST['student_id']) : 0;
    $fullname = isset($_POST['fullname']) ? mysqli_real_escape_string($conn, trim($_POST['fullname'])) : '';
    $email = isset($_POST['email']) ? mysqli_real_escape_string($conn, trim($_POST['email'])) : '';
    $phone = isset($_POST['phone']) ? mysqli_real_escape_string($conn, trim($_POST['phone'])) : '';
    $programme = isset($_POST['programme']) ? mysqli_real_escape_string($conn, $_POST['programme']) : 'NCE';
    $course = isset($_POST['course']) ? mysqli_real_escape_string($conn, trim($_POST['course'])) : '';
    $study_centre = isset($_POST['study_centre']) ? mysqli_real_escape_string($conn, $_POST['study_centre']) : 'Shinge';
    $dob = isset($_POST['dob']) ? mysqli_real_escape_string($conn, $_POST['dob']) : '';
    $address = isset($_POST['address']) ? mysqli_real_escape_string($conn, trim($_POST['address'])) : '';
    $gender = isset($_POST['gender']) ? mysqli_real_escape_string($conn, $_POST['gender']) : '';
    $guardian_name = isset($_POST['guardian_name']) ? mysqli_real_escape_string($conn, trim($_POST['guardian_name'])) : '';
    $guardian_phone = isset($_POST['guardian_phone']) ? mysqli_real_escape_string($conn, trim($_POST['guardian_phone'])) : '';
    $state_of_origin = isset($_POST['state_of_origin']) ? mysqli_real_escape_string($conn, trim($_POST['state_of_origin'])) : '';
    $lga = isset($_POST['lga']) ? mysqli_real_escape_string($conn, trim($_POST['lga'])) : '';
    $nationality = isset($_POST['nationality']) ? mysqli_real_escape_string($conn, trim($_POST['nationality'])) : 'Nigerian';
    $religion = isset($_POST['religion']) ? mysqli_real_escape_string($conn, $_POST['religion']) : 'Islam';
    $marital_status = isset($_POST['marital_status']) ? mysqli_real_escape_string($conn, $_POST['marital_status']) : 'Single';
    $exam_type = isset($_POST['exam_type']) ? mysqli_real_escape_string($conn, $_POST['exam_type']) : 'WAEC';
    $status = isset($_POST['status']) ? mysqli_real_escape_string($conn, $_POST['status']) : 'pending';
    
    $check_email = mysqli_query($conn, "SELECT id FROM students WHERE email = '$email' AND id != '$student_id'");
    if (mysqli_num_rows($check_email) > 0) {
        $edit_error = "❌ Email <strong>'$email'</strong> already exists for another student.";
    } else {
        $query = "UPDATE students SET 
            fullname = '$fullname',
            email = '$email',
            phone = '$phone',
            programme = '$programme',
            course = '$course',
            study_centre = '$study_centre',
            dob = '$dob',
            address = '$address',
            gender = '$gender',
            guardian_name = '$guardian_name',
            guardian_phone = '$guardian_phone',
            state_of_origin = '$state_of_origin',
            lga = '$lga',
            nationality = '$nationality',
            religion = '$religion',
            marital_status = '$marital_status',
            exam_type = '$exam_type',
            status = '$status'
            WHERE id = '$student_id'";
        
        if (mysqli_query($conn, $query)) {
            $edit_success = "✅ Student updated successfully!";
        } else {
            $edit_error = "❌ Error: " . mysqli_error($conn);
        }
    }
}

// ============================================
// HANDLE CHANGE PASSWORD
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $student_id = isset($_POST['student_id']) ? mysqli_real_escape_string($conn, $_POST['student_id']) : 0;
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    if (empty($student_id)) {
        $pass_error = "❌ Invalid student ID.";
    } elseif (empty($new_password) || empty($confirm_password)) {
        $pass_error = "❌ Please enter both password fields.";
    } elseif ($new_password !== $confirm_password) {
        $pass_error = "❌ Passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $pass_error = "❌ Password must be at least 6 characters.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $query = "UPDATE students SET password = '$hashed_password' WHERE id = '$student_id'";
        if (mysqli_query($conn, $query)) {
            $name_query = mysqli_query($conn, "SELECT fullname FROM students WHERE id = '$student_id'");
            $name_row = mysqli_fetch_assoc($name_query);
            $pass_success = "✅ Password changed successfully for " . ($name_row['fullname'] ?? 'Student');
        } else {
            $pass_error = "❌ Error: " . mysqli_error($conn);
        }
    }
}

// ============================================
// HANDLE IMPORT STUDENTS FROM CSV - FIXED
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import_students'])) {
    
    if (isset($_FILES['student_file']) && $_FILES['student_file']['error'] == 0) {
        $file_tmp = $_FILES['student_file']['tmp_name'];
        $file_ext = strtolower(pathinfo($_FILES['student_file']['name'], PATHINFO_EXTENSION));
        
        if ($file_ext != 'csv') {
            $import_error = "❌ Please upload a CSV file.";
        } else {
            $file = fopen($file_tmp, 'r');
            // Skip header
            fgetcsv($file);
            
            $imported = 0;
            $errors = [];
            $row_num = 1;
            
            while (($row = fgetcsv($file, 0, ',', '"', "\\")) !== FALSE) {
                $row_num++;
                if (empty(array_filter($row))) continue;
                
                $admission_no = isset($row[0]) ? mysqli_real_escape_string($conn, trim($row[0])) : '';
                $fullname = isset($row[1]) ? mysqli_real_escape_string($conn, trim($row[1])) : '';
                $email = isset($row[2]) ? mysqli_real_escape_string($conn, trim($row[2])) : '';
                $phone = isset($row[3]) ? mysqli_real_escape_string($conn, trim($row[3])) : '';
                $course = isset($row[4]) ? mysqli_real_escape_string($conn, trim($row[4])) : 'CSC/PHY';
                $programme = isset($row[5]) ? mysqli_real_escape_string($conn, trim($row[5])) : 'NCE';
                $level = isset($row[6]) ? mysqli_real_escape_string($conn, trim($row[6])) : 'NCE I';
                $password = isset($row[7]) ? trim($row[7]) : 'password123';
                $gender = isset($row[8]) ? mysqli_real_escape_string($conn, trim($row[8])) : '';
                $dob = isset($row[9]) ? mysqli_real_escape_string($conn, trim($row[9])) : '';
                $address = isset($row[10]) ? mysqli_real_escape_string($conn, trim($row[10])) : '';
                $state_of_origin = isset($row[11]) ? mysqli_real_escape_string($conn, trim($row[11])) : '';
                $lga = isset($row[12]) ? mysqli_real_escape_string($conn, trim($row[12])) : '';
                $guardian_name = isset($row[13]) ? mysqli_real_escape_string($conn, trim($row[13])) : '';
                $guardian_phone = isset($row[14]) ? mysqli_real_escape_string($conn, trim($row[14])) : '';
                $study_centre = isset($row[15]) ? mysqli_real_escape_string($conn, trim($row[15])) : 'Shinge';
                $nationality = isset($row[16]) ? mysqli_real_escape_string($conn, trim($row[16])) : 'Nigerian';
                $religion = isset($row[17]) ? mysqli_real_escape_string($conn, trim($row[17])) : 'Islam';
                $marital_status = isset($row[18]) ? mysqli_real_escape_string($conn, trim($row[18])) : 'Single';
                $exam_type = isset($row[19]) ? mysqli_real_escape_string($conn, trim($row[19])) : 'WAEC';
                
                if (empty($admission_no) || empty($fullname)) {
                    $errors[] = "Row $row_num: Missing admission number or name";
                    continue;
                }
                
                // CHECK IF ADMISSION NO ALREADY EXISTS
                $check = mysqli_query($conn, "SELECT id FROM students WHERE admission_no = '$admission_no'");
                if (mysqli_num_rows($check) > 0) {
                    $errors[] = "Row $row_num: Admission No '$admission_no' already exists";
                    continue;
                }
                
                // CHECK IF EMAIL ALREADY EXISTS
                if (!empty($email)) {
                    $check_email = mysqli_query($conn, "SELECT id FROM students WHERE email = '$email'");
                    if (mysqli_num_rows($check_email) > 0) {
                        $errors[] = "Row $row_num: Email '$email' already exists";
                        continue;
                    }
                }
                
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $username = !empty($email) ? $email : $admission_no;
                
                $insert = "INSERT INTO students (
                    admission_no, username, password, fullname, email, phone, 
                    course, programme, level, gender, dob, address, 
                    state_of_origin, lga, guardian_name, guardian_phone, 
                    study_centre, nationality, religion, marital_status, 
                    exam_type, status
                ) VALUES (
                    '$admission_no', '$username', '$hashed_password', '$fullname', '$email', '$phone',
                    '$course', '$programme', '$level', '$gender', '$dob', '$address',
                    '$state_of_origin', '$lga', '$guardian_name', '$guardian_phone',
                    '$study_centre', '$nationality', '$religion', '$marital_status',
                    '$exam_type', 'pending'
                )";
                
                if (mysqli_query($conn, $insert)) {
                    $imported++;
                } else {
                    $errors[] = "Row $row_num: " . mysqli_error($conn);
                }
            }
            fclose($file);
            
            if ($imported > 0) {
                $import_success = "✅ <strong>$imported</strong> students imported successfully!";
                if (!empty($errors)) {
                    $import_success .= "<br>⚠️ Errors: " . implode('<br>', array_slice($errors, 0, 10));
                    if (count($errors) > 10) {
                        $import_success .= "<br>... and " . (count($errors) - 10) . " more errors.";
                    }
                }
            } else {
                $import_error = "❌ No students were imported.<br>" . implode('<br>', array_slice($errors, 0, 10));
            }
        }
    } else {
        $import_error = "❌ Please select a file to upload.";
    }
}

// ============================================
// HANDLE DELETE STUDENT
// ============================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $student_id = $_GET['delete'];
    $query = "DELETE FROM students WHERE id = '$student_id'";
    if (mysqli_query($conn, $query)) {
        $delete_success = "✅ Student deleted successfully!";
    } else {
        $delete_error = "❌ Error: " . mysqli_error($conn);
    }
}

// ============================================
// HANDLE CHANGE STATUS
// ============================================
if (isset($_GET['change_status']) && is_numeric($_GET['change_status'])) {
    $student_id = $_GET['change_status'];
    $new_status = $_GET['status'];
    $query = "UPDATE students SET status = '$new_status' WHERE id = '$student_id'";
    if (mysqli_query($conn, $query)) {
        $status_success = "✅ Status changed to $new_status!";
    } else {
        $status_error = "❌ Error: " . mysqli_error($conn);
    }
}

// ============================================
// DOWNLOAD SAMPLE CSV
// ============================================
if (isset($_GET['download_sample'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="student_import_sample.csv"');
    
    $output = fopen('php://output', 'w');
    
    fputcsv($output, [
        'Admission No', 'Full Name', 'Email', 'Phone', 'Course', 'Programme', 
        'Level', 'Password', 'Gender', 'Date of Birth', 'Address', 'State of Origin',
        'LGA', 'Guardian Name', 'Guardian Phone', 'Study Centre', 'Nationality',
        'Religion', 'Marital Status', 'Exam Type'
    ]);
    
    fputcsv($output, [
        'DLCOE/NCE/26A0001/EIS001', 'Aliyu Musa', 'aliyu@example.com', '08012345678', 
        'CSC/PHY', 'NCE', 'NCE I', 'password123', 'Male', '2000-01-15', 
        'No 5, Kano Road', 'Kano', 'Kano Municipal', 'Malam Musa', '08012345678',
        'Shinge', 'Nigerian', 'Islam', 'Single', 'WAEC'
    ]);
    
    fclose($output);
    exit();
}

// ============================================
// GET ALL STUDENTS - REFRESH AFTER IMPORT
// ============================================
$students_query = "SELECT * FROM students ORDER BY id DESC";
$students_result = mysqli_query($conn, $students_query);
$total_students = mysqli_num_rows($students_result);

// Reset pointer for display
mysqli_data_seek($students_result, 0);

// Debug: Check if any students exist
// echo "Total Students: " . $total_students; // Uncomment to debug
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Features - Dala College</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ============================================ */
        /* GENERAL */
        /* ============================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f4f8;
            color: #1a2e1a;
            min-height: 100vh;
        }

        /* ============================================ */
        /* TOPBAR */
        /* ============================================ */
        .topbar {
            background: #0d2818;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: 0 2px 15px rgba(0,0,0,0.2);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .topbar .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .topbar .logo-section img {
            height: 50px;
            width: 50px;
            border-radius: 50%;
            border: 2px solid #ffd54f;
            object-fit: contain;
            background: white;
            padding: 5px;
        }
        .topbar .logo-section .brand h1 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #ffd54f;
            letter-spacing: 1px;
        }
        .topbar .logo-section .brand h1 span { color: #a5d6a7; }
        .topbar .logo-section .brand p {
            font-size: 0.6rem;
            color: #c8e6c9;
            margin-top: -2px;
        }
        .topbar nav {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        .topbar nav a {
            color: #c8e6c9;
            text-decoration: none;
            padding: 8px 18px;
            border-radius: 25px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        .topbar nav a:hover { background: #2e7d32; color: white; }
        .topbar nav a.active {
            background: #ffd54f;
            color: #0d2818 !important;
            font-weight: 700;
        }
        .topbar nav a.logout {
            background: #c62828;
            color: white !important;
        }
        .topbar nav a.logout:hover { background: #b71c1c; }

        /* ============================================ */
        /* CONTAINER */
        /* ============================================ */
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 25px;
            background: white;
            padding: 20px 30px;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .header-section h1 {
            color: #0d2818;
            font-size: 1.8rem;
        }
        .header-section h1 i {
            color: #2e7d32;
            margin-right: 10px;
        }
        .header-section .count {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 6px 18px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .header-section .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .btn-back {
            padding: 10px 25px;
            border: 2px solid #2e7d32;
            color: #2e7d32;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            background: transparent;
        }
        .btn-back:hover {
            background: #2e7d32;
            color: white;
        }

        /* ============================================ */
        /* CARD */
        /* ============================================ */
        .card {
            background: white;
            padding: 25px 30px;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            margin-bottom: 25px;
        }
        .card h3 {
            color: #0d2818;
            margin-bottom: 15px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card h3 i {
            color: #2e7d32;
        }

        /* ============================================ */
        /* FORM */
        /* ============================================ */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }
        .form-grid .form-group {
            margin-bottom: 10px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #1a2e1a;
            font-size: 0.8rem;
            margin-bottom: 3px;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #dce8dc;
            border-radius: 8px;
            font-size: 0.9rem;
            font-family: inherit;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: #2e7d32;
            outline: none;
        }
        
        .btn-submit {
            padding: 10px 30px;
            background: #2e7d32;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        .btn-submit:hover {
            background: #1b5e20;
            transform: translateY(-2px);
        }
        .btn-submit i { margin-right: 8px; }

        /* ============================================ */
        /* IMPORT AREA */
        /* ============================================ */
        .import-area {
            background: #f8faf8;
            padding: 20px;
            border-radius: 8px;
            border: 2px dashed #2e7d32;
            text-align: center;
            margin: 10px 0;
        }
        .import-area .icon { font-size: 2.5rem; margin-bottom: 10px; }
        .import-area input[type="file"] {
            padding: 10px;
            border: 1px solid #dce8dc;
            border-radius: 8px;
            background: white;
            width: 100%;
            max-width: 400px;
            margin: 10px auto;
        }
        .import-area .btn-import {
            padding: 10px 30px;
            background: #2e7d32;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .import-area .btn-import:hover { background: #1b5e20; }
        .import-area .btn-sample {
            padding: 10px 25px;
            background: #1976d2;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .import-area .btn-sample:hover { background: #0d47a1; }
        .import-area .file-info {
            font-size: 0.85rem;
            color: #6a8f6a;
            margin-top: 10px;
        }
        .import-area .file-info strong { color: #0d2818; }

        /* ============================================ */
        /* TABLE - FORCE CENTERED */
        /* ============================================ */
        .table-wrapper {
            overflow-x: auto;
            margin: 10px 0;
            border-radius: 12px;
            border: 1px solid #eef2ef;
        }

        .table-wrapper table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
            min-width: 1300px;
        }

        /* HEADER - CENTERED */
        .table-wrapper table th {
            background: #0d2818;
            color: white;
            padding: 12px 10px;
            text-align: center !important;
            vertical-align: middle !important;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            border-right: 1px solid #1a3a2a;
        }

        .table-wrapper table th:last-child {
            border-right: none;
        }

        /* CELLS - FORCE CENTERED */
        .table-wrapper table td {
            padding: 10px 8px;
            text-align: center !important;
            vertical-align: middle !important;
            border-bottom: 1px solid #f0f4f8;
            font-size: 0.78rem;
        }

        .table-wrapper table tr:last-child td {
            border-bottom: none;
        }

        .table-wrapper table tr:nth-child(even) td {
            background: #fafcfa;
        }

        .table-wrapper table tr:nth-child(even):hover td {
            background: #f0f5f0;
        }

        .table-wrapper table tr:hover td {
            background: #f8faf8;
        }

        /* STATUS BADGE */
        .table-wrapper table td .status-badge {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-align: center !important;
            min-width: 70px;
        }

        .status-badge.approved { 
            background: #e8f5e9; 
            color: #2e7d32; 
        }

        .status-badge.pending { 
            background: #fff8e1; 
            color: #ffa000; 
        }

        .status-badge.rejected { 
            background: #ffebee; 
            color: #c62828; 
        }

        /* BUTTON GROUP - CENTERED */
        .table-wrapper table td .btn-group {
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
            gap: 4px;
            flex-wrap: wrap;
        }

        .table-wrapper table td .btn {
            padding: 4px 10px;
            border: none;
            border-radius: 4px;
            font-size: 0.65rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center !important;
            min-width: 35px;
        }

        .btn-edit {
            background: #ffa000;
            color: white;
        }
        .btn-edit:hover { background: #e65100; }

        .btn-pass {
            background: #6a1b9a;
            color: white;
        }
        .btn-pass:hover { background: #4a148c; }

        .btn-danger {
            background: #c62828;
            color: white;
        }
        .btn-danger:hover { background: #b71c1c; }

        .btn-status {
            padding: 3px 10px;
            border: none;
            border-radius: 4px;
            font-size: 0.6rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center !important;
        }
        .btn-status.approved { background: #e8f5e9; color: #2e7d32; }
        .btn-status.pending { background: #fff8e1; color: #ffa000; }
        .btn-status.rejected { background: #ffebee; color: #c62828; }
        .btn-status:hover { opacity: 0.8; }

        .table-wrapper table td .admission-no {
            font-size: 0.7rem;
            font-weight: 500;
            color: #0d2818;
            background: #f0f4f8;
            padding: 2px 8px;
            border-radius: 4px;
            white-space: nowrap;
            display: inline-block;
        }

        .table-wrapper table td .student-name {
            font-weight: 600;
            color: #0d2818;
        }

        .table-wrapper table td .sn {
            font-weight: 700;
            color: #2e7d32;
        }

        /* ============================================ */
        /* ALERTS */
        /* ============================================ */
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #2e7d32;
        }
        .alert-error {
            background: #ffebee;
            color: #c62828;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #c62828;
        }

        /* ============================================ */
        /* MODAL */
        /* ============================================ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow-y: auto;
            padding: 30px;
        }
        .modal-content {
            max-width: 900px;
            margin: 30px auto;
            background: white;
            padding: 35px;
            border-radius: 16px;
            position: relative;
        }
        .modal-close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 2rem;
            background: none;
            border: none;
            cursor: pointer;
            color: #c62828;
        }
        .modal-close:hover { transform: scale(1.2); }

        /* ============================================ */
        /* RESPONSIVE */
        /* ============================================ */
        @media (max-width: 768px) {
            .topbar { flex-direction: column; gap: 10px; }
            .form-grid { grid-template-columns: 1fr; }
            .header-section { flex-direction: column; gap: 10px; text-align: center; }
            .modal-content { padding: 20px; margin: 10px; }
            .table-wrapper table {
                font-size: 0.7rem;
                min-width: 800px;
            }
            .table-wrapper table th,
            .table-wrapper table td {
                padding: 6px 5px;
                text-align: center !important;
            }
            .table-wrapper table td .btn {
                font-size: 0.6rem;
                padding: 3px 7px;
                min-width: 30px;
            }
        }
    </style>
</head>
<body>

    <!-- ========== TOPBAR ========== -->
    <div class="topbar">
        <div class="logo-section">
            <img src="images/dala-logo.png" alt="Dala College Logo" onerror="this.style.display='none'">
            <div class="brand">
                <h1>DALA <span>COLLEGE</span></h1>
                <p>of Education, Kano</p>
            </div>
        </div>
        <nav>
            <a href="index.html"><i class="fas fa-home"></i> Home</a>
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="admin_features.php" class="active"><i class="fas fa-cog"></i> Admin Tools</a>
            <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="container">

        <!-- ========== HEADER ========== -->
        <div class="header-section">
            <div>
                <h1><i class="fas fa-cog"></i> Admin Tools</h1>
                <span class="count"><i class="fas fa-user-graduate"></i> Total Students: <?php echo $total_students; ?></span>
            </div>
            <div class="actions">
                <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>
        </div>

        <!-- ========== IMPORT STUDENTS ========== -->
        <div class="card" id="import">
            <h3><i class="fas fa-file-upload"></i> Import Students from CSV</h3>
            <?php if (isset($import_success)): ?>
                <div class="alert-success"><?php echo $import_success; ?></div>
            <?php endif; ?>
            <?php if (isset($import_error)): ?>
                <div class="alert-error"><?php echo $import_error; ?></div>
            <?php endif; ?>
            <div class="import-area">
                <div class="icon">📄</div>
                <p style="color:#0d2818; font-weight:600; margin-bottom:5px;">
                    Upload CSV file with student data
                </p>
                <p style="color:#6a8f6a; font-size:0.85rem; margin-bottom:10px;">
                    Supported format: <strong>CSV</strong>
                </p>
                <form method="POST" action="" enctype="multipart/form-data" id="importForm">
                    <input type="file" name="student_file" accept=".csv" required>
                    <br><br>
                    <button type="submit" name="import_students" class="btn-import"><i class="fas fa-upload"></i> Import Students</button>
                    <a href="admin_features.php?download_sample=1" class="btn-sample"><i class="fas fa-download"></i> Download Sample CSV</a>
                </form>
            </div>
        </div>

        <!-- ========== ADD STUDENT FORM ========== -->
        <div class="card" id="add">
            <h3><i class="fas fa-user-plus"></i> Add New Student</h3>
            <?php if (isset($add_success)): ?>
                <div class="alert-success"><?php echo $add_success; ?></div>
            <?php endif; ?>
            <?php if (isset($add_error)): ?>
                <div class="alert-error"><?php echo $add_error; ?></div>
            <?php endif; ?>
            <form method="POST" action="" id="studentForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Admission No</label>
                        <input type="text" name="admission_no" placeholder="Leave empty to auto-generate">
                    </div>
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="fullname" required>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Phone *</label>
                        <input type="tel" name="phone" required>
                    </div>
                    
                    <!-- ===== PROGRAMME DROPDOWN ===== -->
                    <div class="form-group">
                        <label>Programme *</label>
                        <select name="programme" id="programme" required onchange="updateCourses()">
                            <option value="">-- Select Programme --</option>
                            <option value="NCE">NCE</option>
                            <option value="DEGREE">Degree</option>
                            <option value="ENTREPRENEURSHIP">Entrepreneurship</option>
                        </select>
                    </div>
                    
                    <!-- ===== COURSE DROPDOWN (Dynamic) ===== -->
                    <div class="form-group">
                        <label>Course *</label>
                        <select name="course" id="course" required>
                            <option value="">-- Select Programme First --</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Study Centre *</label>
                        <select name="study_centre" required>
                            <option value="Shinge">Shinge (A)</option>
                            <option value="Sabuwar Kofa">Sabuwar Kofa (B)</option>
                            <option value="Tudun Yola">Tudun Yola (C)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="dob">
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" rows="2" placeholder="Enter address"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender">
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Guardian Name</label>
                        <input type="text" name="guardian_name">
                    </div>
                    <div class="form-group">
                        <label>Guardian Phone</label>
                        <input type="tel" name="guardian_phone">
                    </div>
                    <div class="form-group">
                        <label>State of Origin</label>
                        <input type="text" name="state_of_origin">
                    </div>
                    <div class="form-group">
                        <label>LGA</label>
                        <input type="text" name="lga">
                    </div>
                    <div class="form-group">
                        <label>Nationality</label>
                        <input type="text" name="nationality" value="Nigerian">
                    </div>
                    <div class="form-group">
                        <label>Religion</label>
                        <select name="religion">
                            <option value="Islam">Islam</option>
                            <option value="Christianity">Christianity</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Marital Status</label>
                        <select name="marital_status">
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                            <option value="Divorced">Divorced</option>
                            <option value="Widowed">Widowed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Exam Type</label>
                        <select name="exam_type">
                            <option value="WAEC">WAEC</option>
                            <option value="NECO">NECO</option>
                            <option value="NABTEB">NABTEB</option>
                            <option value="NBAIS">NBAIS</option>
                            <option value="GCE">GCE</option>
                            <option value="JAMB">JAMB</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <input type="hidden" name="add_student" value="1">
                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Add Student</button>
            </form>
        </div>

        <!-- ========== STUDENTS LIST ========== -->
        <div class="card">
            <h3><i class="fas fa-users"></i> All Students (<?php echo $total_students; ?>)</h3>
            
            <?php if (isset($delete_success)): ?>
                <div class="alert-success"><?php echo $delete_success; ?></div>
            <?php endif; ?>
            <?php if (isset($delete_error)): ?>
                <div class="alert-error"><?php echo $delete_error; ?></div>
            <?php endif; ?>
            <?php if (isset($edit_success)): ?>
                <div class="alert-success"><?php echo $edit_success; ?></div>
            <?php endif; ?>
            <?php if (isset($edit_error)): ?>
                <div class="alert-error"><?php echo $edit_error; ?></div>
            <?php endif; ?>
            <?php if (isset($status_success)): ?>
                <div class="alert-success"><?php echo $status_success; ?></div>
            <?php endif; ?>
            <?php if (isset($status_error)): ?>
                <div class="alert-error"><?php echo $status_error; ?></div>
            <?php endif; ?>
            <?php if (isset($pass_success)): ?>
                <div class="alert-success"><?php echo $pass_success; ?></div>
            <?php endif; ?>
            <?php if (isset($pass_error)): ?>
                <div class="alert-error"><?php echo $pass_error; ?></div>
            <?php endif; ?>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width:50px; text-align:center !important;">S/N</th>
                            <th style="min-width:120px; text-align:center !important;">Full Name</th>
                            <th style="min-width:100px; text-align:center !important;">Email</th>
                            <th style="min-width:90px; text-align:center !important;">Phone</th>
                            <th style="min-width:80px; text-align:center !important;">Programme</th>
                            <th style="min-width:100px; text-align:center !important;">Course</th>
                            <th style="min-width:100px; text-align:center !important;">Status</th>
                            <th style="min-width:140px; text-align:center !important;">Admission No.</th>
                            <th style="min-width:90px; text-align:center !important;">Date of Birth</th>
                            <th style="min-width:70px; text-align:center !important;">Gender</th>
                            <th style="min-width:100px; text-align:center !important;">Guardian Name</th>
                            <th style="min-width:90px; text-align:center !important;">State of Origin</th>
                            <th style="min-width:80px; text-align:center !important;">LGA</th>
                            <th style="min-width:80px; text-align:center !important;">Religion</th>
                            <th style="min-width:80px; text-align:center !important;">Exam Type</th>
                            <th style="min-width:140px; text-align:center !important;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $i = 1;
                        if (mysqli_num_rows($students_result) > 0) {
                            while ($row = mysqli_fetch_assoc($students_result)): 
                                $status_class = $row['status'] == 'approved' ? 'approved' : ($row['status'] == 'pending' ? 'pending' : 'rejected');
                        ?>
                        <tr>
                            <td style="text-align:center !important;"><span class="sn"><?php echo $i++; ?></span></td>
                            <td style="text-align:center !important;"><span class="student-name"><?php echo htmlspecialchars($row['fullname'] ?? '-'); ?></span></td>
                            <td style="text-align:center !important;"><?php echo htmlspecialchars($row['email'] ?? '-'); ?></td>
                            <td style="text-align:center !important;"><?php echo htmlspecialchars($row['phone'] ?? '-'); ?></td>
                            <td style="text-align:center !important;"><?php echo htmlspecialchars($row['programme'] ?? '-'); ?></td>
                            <td style="text-align:center !important;"><small><?php echo htmlspecialchars($row['course'] ?? '-'); ?></small></td>
                            <td style="text-align:center !important;"><span class="status-badge <?php echo $status_class; ?>"><?php echo ucfirst($row['status'] ?? 'pending'); ?></span></td>
                            <td style="text-align:center !important;"><span class="admission-no"><?php echo htmlspecialchars($row['admission_no'] ?? '-'); ?></span></td>
                            <td style="text-align:center !important;"><?php echo !empty($row['dob']) ? date('d/m/Y', strtotime($row['dob'])) : '-'; ?></td>
                            <td style="text-align:center !important;"><?php echo htmlspecialchars($row['gender'] ?? '-'); ?></td>
                            <td style="text-align:center !important;"><?php echo htmlspecialchars($row['guardian_name'] ?? '-'); ?></td>
                            <td style="text-align:center !important;"><?php echo htmlspecialchars($row['state_of_origin'] ?? '-'); ?></td>
                            <td style="text-align:center !important;"><?php echo htmlspecialchars($row['lga'] ?? '-'); ?></td>
                            <td style="text-align:center !important;"><?php echo htmlspecialchars($row['religion'] ?? '-'); ?></td>
                            <td style="text-align:center !important;"><?php echo htmlspecialchars($row['exam_type'] ?? '-'); ?></td>
                            <td style="text-align:center !important;">
                                <div class="btn-group">
                                    <a href="#" class="btn btn-edit" onclick="editStudent(<?php echo $row['id']; ?>)"><i class="fas fa-edit"></i></a>
                                    <a href="#" class="btn btn-pass" onclick="changePassword(<?php echo $row['id']; ?>)"><i class="fas fa-key"></i></a>
                                    <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i></a>
                                    <?php if ($row['status'] == 'rejected'): ?>
                                        <a href="?change_status=<?php echo $row['id']; ?>&status=pending" class="btn-status pending"><i class="fas fa-undo"></i></a>
                                    <?php endif; ?>
                                    <?php if ($row['status'] == 'pending'): ?>
                                        <a href="?change_status=<?php echo $row['id']; ?>&status=approved" class="btn-status approved"><i class="fas fa-check"></i></a>
                                        <a href="?change_status=<?php echo $row['id']; ?>&status=rejected" class="btn-status rejected"><i class="fas fa-times"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        } else {
                            echo '<tr><td colspan="16" style="text-align:center; padding:20px; color:#999;">No students found.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ========== EDIT MODAL ========== -->
        <div class="modal-overlay" id="editModal">
            <div class="modal-content">
                <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
                <h3 style="margin-bottom:20px;"><i class="fas fa-edit"></i> Edit Student</h3>
                <div id="editFormContainer"></div>
            </div>
        </div>

        <!-- ========== CHANGE PASSWORD MODAL ========== -->
        <div class="modal-overlay" id="passModal">
            <div class="modal-content" style="max-width:500px;">
                <button class="modal-close" onclick="closeModal('passModal')">&times;</button>
                <h3 style="margin-bottom:20px;"><i class="fas fa-key"></i> Change Password</h3>
                <div id="passFormContainer"></div>
            </div>
        </div>

    </div>

    <script>
    // ============================================
    // DYNAMIC COURSE DROPDOWN - ADD FORM
    // ============================================
    function updateCourses() {
        var programme = document.getElementById('programme').value;
        var courseSelect = document.getElementById('course');
        
        courseSelect.innerHTML = '';
        
        var defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = '-- Select Course --';
        courseSelect.appendChild(defaultOption);
        
        var courses = [];
        
        if (programme === 'NCE') {
            courses = [
                { value: 'ARB/ISS', text: 'ARB/ISS - Arabic / Islamic Studies' },
                { value: 'ENG/ISS', text: 'ENG/ISS - English / Islamic Studies' },
                { value: 'PED', text: 'PED - Primary Education' },
                { value: 'HAU/ENG', text: 'HAU/ENG - Hausa / English' },
                { value: 'CSC/ISC', text: 'CSC/ISC - Computer Science / Islamic Studies' },
                { value: 'ENG/SOS', text: 'ENG/SOS - English / Social Studies' },
                { value: 'CSC/BIO', text: 'CSC/BIO - Computer Science / Biology' },
                { value: 'CSC/PHY', text: 'CSC/PHY - Computer Science / Physics' },
                { value: 'ENG/ECO', text: 'ENG/ECO - English / Economics' }
            ];
        } else if (programme === 'DEGREE') {
            courses = [
                { value: 'BA_ARABIC', text: 'B.A. Arabic' },
                { value: 'BA_ISLAMIC', text: 'B.A. Islamic Studies' },
                { value: 'BED_ENGLISH', text: 'B.Ed. English Education' },
                { value: 'BED_HAUSA', text: 'B.Ed. Hausa Education' },
                { value: 'BED_SOCIAL', text: 'B.Ed. Social Studies' },
                { value: 'BSC_ECONOMICS', text: 'B.Sc. Economics Education' },
                { value: 'BSC_CSC', text: 'B.Sc. Computer Science Education' },
                { value: 'BSC_BIOLOGY', text: 'B.Sc. Biology Education' },
                { value: 'BSC_PHYSICS', text: 'B.Sc. Physics Education' },
                { value: 'BSC_ISC', text: 'B.Sc. Integrated Science Education' }
            ];
        } else if (programme === 'ENTREPRENEURSHIP') {
            courses = [
                { value: 'TAILORING', text: 'Tailoring and Fashion Design' },
                { value: 'AI_TECH', text: 'Computer / AI Technology' },
                { value: 'SALOON', text: 'Saloon and Hairdressing' },
                { value: 'HENNA', text: 'Henna Art and Decoration' },
                { value: 'FISH_FARMING', text: 'Fish Farming (Kiwon Kifi)' },
                { value: 'POULTRY', text: 'Poultry Farming (Kiwon Kaji)' },
                { value: 'SOAP_MAKING', text: 'Soap Making (Hada Sabulu)' },
                { value: 'CATERING', text: 'Catering and Confectionery' },
                { value: 'BEAD_MAKING', text: 'Bead Making and Crafts' },
                { value: 'GRAPHIC_DESIGN', text: 'Graphic Design' }
            ];
        }
        
        courses.forEach(function(course) {
            var option = document.createElement('option');
            option.value = course.value;
            option.textContent = course.text;
            courseSelect.appendChild(option);
        });
    }

    // ============================================
    // EDIT STUDENT
    // ============================================
    function editStudent(id) {
        fetch('get_student.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    var html = `
                    <form method="POST" action="" id="editForm">
                        <input type="hidden" name="student_id" value="${data.id}">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Full Name *</label>
                                <input type="text" name="fullname" value="${data.fullname || ''}" required>
                            </div>
                            <div class="form-group">
                                <label>Email *</label>
                                <input type="email" name="email" value="${data.email || ''}" required>
                            </div>
                            <div class="form-group">
                                <label>Phone *</label>
                                <input type="tel" name="phone" value="${data.phone || ''}" required>
                            </div>
                            <div class="form-group">
                                <label>Programme *</label>
                                <select name="programme" id="edit_programme" required onchange="updateEditCourses()">
                                    <option value="">-- Select Programme --</option>
                                    <option value="NCE" ${data.programme == 'NCE' ? 'selected' : ''}>NCE</option>
                                    <option value="DEGREE" ${data.programme == 'DEGREE' ? 'selected' : ''}>Degree</option>
                                    <option value="ENTREPRENEURSHIP" ${data.programme == 'ENTREPRENEURSHIP' ? 'selected' : ''}>Entrepreneurship</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Course *</label>
                                <select name="course" id="edit_course" required>
                                    <option value="">-- Select Programme First --</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Study Centre *</label>
                                <select name="study_centre" required>
                                    <option value="Shinge" ${data.study_centre == 'Shinge' ? 'selected' : ''}>Shinge (A)</option>
                                    <option value="Sabuwar Kofa" ${data.study_centre == 'Sabuwar Kofa' ? 'selected' : ''}>Sabuwar Kofa (B)</option>
                                    <option value="Tudun Yola" ${data.study_centre == 'Tudun Yola' ? 'selected' : ''}>Tudun Yola (C)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Date of Birth</label>
                                <input type="date" name="dob" value="${data.dob || ''}">
                            </div>
                            <div class="form-group">
                                <label>Address</label>
                                <textarea name="address" rows="2">${data.address || ''}</textarea>
                            </div>
                            <div class="form-group">
                                <label>Gender</label>
                                <select name="gender">
                                    <option value="">Select</option>
                                    <option value="Male" ${data.gender == 'Male' ? 'selected' : ''}>Male</option>
                                    <option value="Female" ${data.gender == 'Female' ? 'selected' : ''}>Female</option>
                                    <option value="Other" ${data.gender == 'Other' ? 'selected' : ''}>Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Guardian Name</label>
                                <input type="text" name="guardian_name" value="${data.guardian_name || ''}">
                            </div>
                            <div class="form-group">
                                <label>Guardian Phone</label>
                                <input type="tel" name="guardian_phone" value="${data.guardian_phone || ''}">
                            </div>
                            <div class="form-group">
                                <label>State of Origin</label>
                                <input type="text" name="state_of_origin" value="${data.state_of_origin || ''}">
                            </div>
                            <div class="form-group">
                                <label>LGA</label>
                                <input type="text" name="lga" value="${data.lga || ''}">
                            </div>
                            <div class="form-group">
                                <label>Nationality</label>
                                <input type="text" name="nationality" value="${data.nationality || 'Nigerian'}">
                            </div>
                            <div class="form-group">
                                <label>Religion</label>
                                <select name="religion">
                                    <option value="Islam" ${data.religion == 'Islam' ? 'selected' : ''}>Islam</option>
                                    <option value="Christianity" ${data.religion == 'Christianity' ? 'selected' : ''}>Christianity</option>
                                    <option value="Other" ${data.religion == 'Other' ? 'selected' : ''}>Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Marital Status</label>
                                <select name="marital_status">
                                    <option value="Single" ${data.marital_status == 'Single' ? 'selected' : ''}>Single</option>
                                    <option value="Married" ${data.marital_status == 'Married' ? 'selected' : ''}>Married</option>
                                    <option value="Divorced" ${data.marital_status == 'Divorced' ? 'selected' : ''}>Divorced</option>
                                    <option value="Widowed" ${data.marital_status == 'Widowed' ? 'selected' : ''}>Widowed</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Exam Type</label>
                                <select name="exam_type">
                                    <option value="WAEC" ${data.exam_type == 'WAEC' ? 'selected' : ''}>WAEC</option>
                                    <option value="NECO" ${data.exam_type == 'NECO' ? 'selected' : ''}>NECO</option>
                                    <option value="NABTEB" ${data.exam_type == 'NABTEB' ? 'selected' : ''}>NABTEB</option>
                                    <option value="NBAIS" ${data.exam_type == 'NBAIS' ? 'selected' : ''}>NBAIS</option>
                                    <option value="GCE" ${data.exam_type == 'GCE' ? 'selected' : ''}>GCE</option>
                                    <option value="JAMB" ${data.exam_type == 'JAMB' ? 'selected' : ''}>JAMB</option>
                                    <option value="Other" ${data.exam_type == 'Other' ? 'selected' : ''}>Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status">
                                    <option value="pending" ${data.status == 'pending' ? 'selected' : ''}>Pending</option>
                                    <option value="approved" ${data.status == 'approved' ? 'selected' : ''}>Approved</option>
                                    <option value="rejected" ${data.status == 'rejected' ? 'selected' : ''}>Rejected</option>
                                </select>
                            </div>
                        </div>
                        <input type="hidden" name="edit_student" value="1">
                        <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Update Student</button>
                    </form>
                    `;
                    document.getElementById('editFormContainer').innerHTML = html;
                    document.getElementById('editModal').style.display = 'block';
                    
                    setTimeout(function() {
                        updateEditCourses();
                        var editCourse = document.getElementById('edit_course');
                        if (data.course) {
                            for (var i = 0; i < editCourse.options.length; i++) {
                                if (editCourse.options[i].value === data.course) {
                                    editCourse.selectedIndex = i;
                                    break;
                                }
                            }
                        }
                    }, 100);
                } else {
                    alert('Error loading student data');
                }
            })
            .catch(error => {
                alert('Error: ' + error);
            });
    }

    // ============================================
    // UPDATE EDIT COURSES
    // ============================================
    function updateEditCourses() {
        var programme = document.getElementById('edit_programme').value;
        var courseSelect = document.getElementById('edit_course');
        
        courseSelect.innerHTML = '';
        
        var defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = '-- Select Course --';
        courseSelect.appendChild(defaultOption);
        
        var courses = [];
        
        if (programme === 'NCE') {
            courses = [
                { value: 'ARB/ISS', text: 'ARB/ISS - Arabic / Islamic Studies' },
                { value: 'ENG/ISS', text: 'ENG/ISS - English / Islamic Studies' },
                { value: 'PED', text: 'PED - Primary Education' },
                { value: 'HAU/ENG', text: 'HAU/ENG - Hausa / English' },
                { value: 'CSC/ISC', text: 'CSC/ISC - Computer Science / Islamic Studies' },
                { value: 'ENG/SOS', text: 'ENG/SOS - English / Social Studies' },
                { value: 'CSC/BIO', text: 'CSC/BIO - Computer Science / Biology' },
                { value: 'CSC/PHY', text: 'CSC/PHY - Computer Science / Physics' },
                { value: 'ENG/ECO', text: 'ENG/ECO - English / Economics' }
            ];
        } else if (programme === 'DEGREE') {
            courses = [
                { value: 'BA_ARABIC', text: 'B.A. Arabic' },
                { value: 'BA_ISLAMIC', text: 'B.A. Islamic Studies' },
                { value: 'BED_ENGLISH', text: 'B.Ed. English Education' },
                { value: 'BED_HAUSA', text: 'B.Ed. Hausa Education' },
                { value: 'BED_SOCIAL', text: 'B.Ed. Social Studies' },
                { value: 'BSC_ECONOMICS', text: 'B.Sc. Economics Education' },
                { value: 'BSC_CSC', text: 'B.Sc. Computer Science Education' },
                { value: 'BSC_BIOLOGY', text: 'B.Sc. Biology Education' },
                { value: 'BSC_PHYSICS', text: 'B.Sc. Physics Education' },
                { value: 'BSC_ISC', text: 'B.Sc. Integrated Science Education' }
            ];
        } else if (programme === 'ENTREPRENEURSHIP') {
            courses = [
                { value: 'TAILORING', text: 'Tailoring and Fashion Design' },
                { value: 'AI_TECH', text: 'Computer / AI Technology' },
                { value: 'SALOON', text: 'Saloon and Hairdressing' },
                { value: 'HENNA', text: 'Henna Art and Decoration' },
                { value: 'FISH_FARMING', text: 'Fish Farming (Kiwon Kifi)' },
                { value: 'POULTRY', text: 'Poultry Farming (Kiwon Kaji)' },
                { value: 'SOAP_MAKING', text: 'Soap Making (Hada Sabulu)' },
                { value: 'CATERING', text: 'Catering and Confectionery' },
                { value: 'BEAD_MAKING', text: 'Bead Making and Crafts' },
                { value: 'GRAPHIC_DESIGN', text: 'Graphic Design' }
            ];
        }
        
        courses.forEach(function(course) {
            var option = document.createElement('option');
            option.value = course.value;
            option.textContent = course.text;
            courseSelect.appendChild(option);
        });
    }

    // ============================================
    // CHANGE PASSWORD
    // ============================================
    function changePassword(id) {
        fetch('get_student.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    var html = `
                    <form method="POST" action="">
                        <input type="hidden" name="student_id" value="${data.id}">
                        <div style="background:#f8faf8; padding:15px; border-radius:8px; margin-bottom:20px;">
                            <p><strong>Student:</strong> ${data.fullname}</p>
                            <p><strong>Email:</strong> ${data.email}</p>
                        </div>
                        <div class="form-group">
                            <label>New Password *</label>
                            <input type="password" name="new_password" required minlength="6" placeholder="Enter new password (min 6 chars)">
                        </div>
                        <div class="form-group">
                            <label>Confirm Password *</label>
                            <input type="password" name="confirm_password" required placeholder="Confirm new password">
                        </div>
                        <input type="hidden" name="change_password" value="1">
                        <button type="submit" class="btn-submit"><i class="fas fa-key"></i> Change Password</button>
                    </form>
                    `;
                    document.getElementById('passFormContainer').innerHTML = html;
                    document.getElementById('passModal').style.display = 'block';
                } else {
                    alert('Error loading student data');
                }
            })
            .catch(error => {
                alert('Error: ' + error);
            });
    }

    // ============================================
    // CLOSE MODAL
    // ============================================
    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target.className === 'modal-overlay') {
            event.target.style.display = 'none';
        }
    }

    // ============================================
    // RUN ON PAGE LOAD
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        var programme = document.getElementById('programme');
        if (programme && programme.value) {
            updateCourses();
        }
    });
    </script>

</body>
</html>