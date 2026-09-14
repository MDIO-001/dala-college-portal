<?php
session_start();
include 'connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] != 'admin') {
    header('Location: admin_login.php');
    exit();
}

$success = '';
$error = '';
$uploaded_count = 0;
$failed_count = 0;
$failed_rows = [];

// ============================================
// GET DEPARTMENT CODE
// ============================================
function getDepartmentCode($course) {
    $dept_codes = [
        'ARB/ISS' => 'ARI', 'ENG/ISS' => 'ENG', 'PED' => 'PED',
        'HAU/ENG' => 'HAU', 'CSC/ISC' => 'CSC', 'ENG/SOS' => 'SOC',
        'CSC/BIO' => 'BIO', 'CSC/PHY' => 'PHY', 'ENG/ECO' => 'ECO',
        'BA_ARABIC' => 'ARI', 'BA_ISLAMIC' => 'ISC',
        'BED_ENGLISH' => 'ENG', 'BED_HAUSA' => 'HAU',
        'BED_SOCIAL' => 'SOC', 'BSC_ECONOMICS' => 'ECO',
        'BSC_CSC' => 'CSC', 'BSC_BIOLOGY' => 'BIO',
        'BSC_PHYSICS' => 'PHY', 'BSC_ISC' => 'ISC',
        'TAILORING' => 'TAI', 'AI_TECH' => 'AIT',
        'SALOON' => 'SAL', 'HENNA' => 'HEN',
        'FISH_FARMING' => 'FIS', 'POULTRY' => 'POU',
        'SOAP_MAKING' => 'SOA', 'CATERING' => 'CAT',
        'BEAD_MAKING' => 'BEA', 'GRAPHIC_DESIGN' => 'GRA'
    ];
    return isset($dept_codes[$course]) ? $dept_codes[$course] : 'GEN';
}

// ============================================
// GENERATE ADMISSION NUMBER
// ============================================
function generateAdmissionNumber($programme, $branch_code, $course) {
    global $conn;
    $year = date('y');
    
    $branch_letter = '';
    if ($branch_code == 'SHINGE') $branch_letter = 'A';
    elseif ($branch_code == 'SABUWA') $branch_letter = 'B';
    elseif ($branch_code == 'TUDUN') $branch_letter = 'C';
    else $branch_letter = 'X';
    
    $dept_code = getDepartmentCode($course);
    
    $count_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM students");
    $count = mysqli_fetch_assoc($count_result)['total'] + 1;
    $student_number = str_pad($count, 3, '0', STR_PAD_LEFT);
    
    $prefix = 'DLCOE';
    if ($programme == 'NCE') $prefix = 'DLCOE/NCE';
    elseif ($programme == 'DEGREE') $prefix = 'DLCOE/DEG';
    else $prefix = 'DLCOE/ENT';
    
    return $prefix . "/{$year}{$branch_letter}{$student_number}/{$dept_code}{$student_number}";
}

// ============================================
// HANDLE FILE UPLOAD
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    
    if ($file['error'] != 0) {
        $error = '❌ Error uploading file.';
    } else {
        $file_type = pathinfo($file['name'], PATHINFO_EXTENSION);
        
        if (!in_array(strtolower($file_type), ['csv', 'xlsx', 'xls'])) {
            $error = '❌ Please upload a CSV or Excel file.';
        } else {
            // Read CSV file
            $handle = fopen($file['tmp_name'], 'r');
            $headers = fgetcsv($handle); // Skip headers
            
            $row_number = 1;
            while (($data = fgetcsv($handle)) !== FALSE) {
                $row_number++;
                
                // Expected columns:
                // 0: fullname, 1: phone, 2: programme, 3: course, 4: level, 
                // 5: branch, 6: gender, 7: dob, 8: address, 9: guardian_name, 10: guardian_phone
                
                if (count($data) < 4) {
                    $failed_count++;
                    $failed_rows[] = "Row $row_number: Insufficient data";
                    continue;
                }
                
                $fullname = trim($data[0] ?? '');
                $phone = trim($data[1] ?? '');
                $programme = trim($data[2] ?? 'NCE');
                $course = trim($data[3] ?? '');
                $level = trim($data[4] ?? 'NCE I');
                $branch = trim($data[5] ?? 'SHINGE');
                $gender = trim($data[6] ?? 'Male');
                $dob = trim($data[7] ?? '');
                $address = trim($data[8] ?? '');
                $guardian_name = trim($data[9] ?? '');
                $guardian_phone = trim($data[10] ?? '');
                $password = 'student123'; // Default password
                
                // Validate
                if (empty($fullname) || empty($phone) || empty($course)) {
                    $failed_count++;
                    $failed_rows[] = "Row $row_number: Missing required fields (name, phone, course)";
                    continue;
                }
                
                // Check phone
                $check = mysqli_query($conn, "SELECT id FROM students WHERE phone = '$phone'");
                if (mysqli_num_rows($check) > 0) {
                    $failed_count++;
                    $failed_rows[] = "Row $row_number: Phone $phone already exists";
                    continue;
                }
                
                // Generate admission number
                $admission_number = generateAdmissionNumber($programme, $branch, $course);
                $username = strtolower(str_replace(' ', '_', $fullname)) . rand(10, 99);
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $insert = "INSERT INTO students (
                    reg_no, student_id, username, password, fullname, phone,
                    programme, course, level, branch_code, gender, dob,
                    address, guardian_name, guardian_phone, status, created_at
                ) VALUES (
                    '$admission_number', '$admission_number', '$username', '$hashed_password', '$fullname', '$phone',
                    '$programme', '$course', '$level', '$branch', '$gender', '$dob',
                    '$address', '$guardian_name', '$guardian_phone', 'active', NOW()
                )";
                
                if (mysqli_query($conn, $insert)) {
                    $uploaded_count++;
                } else {
                    $failed_count++;
                    $failed_rows[] = "Row $row_number: " . mysqli_error($conn);
                }
            }
            fclose($handle);
            
            $success = "✅ Upload complete!<br>
                        📥 Added: <strong>$uploaded_count</strong> students<br>
                        ❌ Failed: <strong>$failed_count</strong> students";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Upload - Dala College</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            padding: 20px;
        }
        
        .topbar {
            background: #0d2818;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            border-radius: 12px;
            margin-bottom: 20px;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
        }
        .topbar .logo-title { color: #ffd54f; font-size: 1.3rem; font-weight: 800; }
        .topbar .logo-title span { color: #a5d6a7; }
        .topbar .logo-sub { display: block; font-size: 0.6rem; color: #c8e6c9; }
        .topbar nav a { 
            color: #c8e6c9; 
            text-decoration: none; 
            padding: 8px 18px; 
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        .topbar nav a:hover { background: #2e7d32; }
        .topbar nav .btn-active { background: #2e7d32; }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 4px 30px rgba(0,0,0,0.1);
        }
        h1 { color: #0d2818; }
        .sub { color: #6a8f6a; margin-bottom: 25px; }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #2e7d32;
        }
        .alert-error {
            background: #ffebee;
            color: #c62828;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #c62828;
        }
        
        .upload-box {
            border: 3px dashed #dce8dc;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            margin: 20px 0;
            transition: all 0.3s ease;
        }
        .upload-box:hover { border-color: #2e7d32; }
        .upload-box .icon {
            font-size: 4rem;
            color: #2e7d32;
            margin-bottom: 15px;
        }
        .upload-box input[type="file"] {
            display: block;
            margin: 15px auto;
            padding: 10px;
            border: 1px solid #dce8dc;
            border-radius: 8px;
            width: 100%;
            max-width: 400px;
        }
        .btn-upload {
            padding: 12px 40px;
            background: linear-gradient(135deg, #2e7d32, #1b5e20);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-upload:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(46,125,50,0.3);
        }
        .btn-upload i { margin-right: 8px; }
        
        .btn-back {
            display: inline-block;
            margin-top: 15px;
            color: #2e7d32;
            text-decoration: none;
            font-weight: 600;
        }
        .btn-back:hover { text-decoration: underline; }
        
        .template-box {
            background: #f8faf8;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .template-box table {
            width: 100%;
            font-size: 0.85rem;
            border-collapse: collapse;
        }
        .template-box th {
            background: #0d2818;
            color: white;
            padding: 8px 12px;
            text-align: left;
        }
        .template-box td {
            padding: 6px 12px;
            border-bottom: 1px solid #e8f5e9;
        }
        .template-box .download-btn {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 20px;
            background: #1976d2;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
        }
        
        .failed-list {
            background: #ffebee;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            max-height: 200px;
            overflow-y: auto;
        }
        .failed-list li { 
            list-style: none; 
            padding: 3px 0; 
            font-size: 0.85rem; 
            color: #c62828;
        }
        
        @media (max-width: 768px) {
            .container { padding: 25px; }
            .topbar { flex-direction: column; text-align: center; gap: 10px; }
        }
    </style>
</head>
<body>

    <div class="topbar">
        <div>
            <span class="logo-title">DALA <span>COLLEGE</span></span>
            <span class="logo-sub">of Education, Kano</span>
        </div>
        <nav>
            <a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="admin_add_student.php"><i class="fas fa-user-plus"></i> Add Student</a>
            <a href="admin_bulk_upload.php" class="btn-active"><i class="fas fa-file-excel"></i> Bulk Upload</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="container">
        <h1><i class="fas fa-file-excel" style="color:#2e7d32;"></i> Bulk Upload Students</h1>
        <p class="sub">Upload multiple students at once using CSV or Excel file</p>

        <?php if ($success): ?>
            <div class="alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
            <?php if (!empty($failed_rows)): ?>
                <div class="failed-list">
                    <strong>⚠️ Failed Rows:</strong>
                    <ul>
                        <?php foreach ($failed_rows as $row): ?>
                            <li>• <?php echo $row; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Template -->
        <div class="template-box">
            <h4 style="color: #0d2818; margin-bottom: 10px;">
                <i class="fas fa-table"></i> CSV/Excel Template
            </h4>
            <p style="font-size: 0.85rem; color: #6a8f6a; margin-bottom: 10px;">
                Your file should have these columns in order:
            </p>
            <table>
                <thead>
                    <tr>
                        <th>Full Name</th>
                        <th>Phone</th>
                        <th>Programme</th>
                        <th>Course</th>
                        <th>Level</th>
                        <th>Branch</th>
                        <th>Gender</th>
                        <th>DOB</th>
                        <th>Address</th>
                        <th>Guardian Name</th>
                        <th>Guardian Phone</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Musa Abdullahi</td>
                        <td>08012345678</td>
                        <td>NCE</td>
                        <td>CSC/ISC</td>
                        <td>NCE I</td>
                        <td>SHINGE</td>
                        <td>Male</td>
                        <td>2000-01-01</td>
                        <td>Kano</td>
                        <td>Abdullahi</td>
                        <td>08087654321</td>
                    </tr>
                    <tr>
                        <td>Amina Ibrahim</td>
                        <td>08087654321</td>
                        <td>NCE</td>
                        <td>PED</td>
                        <td>NCE I</td>
                        <td>SABUWA</td>
                        <td>Female</td>
                        <td>2000-02-01</td>
                        <td>Kano</td>
                        <td>Ibrahim</td>
                        <td>08011223344</td>
                    </tr>
                </tbody>
            </table>
            <p style="font-size: 0.8rem; color: #6a8f6a; margin-top: 10px;">
                <i class="fas fa-info-circle"></i> Branch: SHINGE, SABUWA, or TUDUN | Programme: NCE, DEGREE, or ENTREPRENEURSHIP
            </p>
        </div>

        <!-- Upload Form -->
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="upload-box">
                <div class="icon"><i class="fas fa-upload"></i></div>
                <h3 style="color: #0d2818;">Upload Your File</h3>
                <p style="color: #6a8f6a;">Supported formats: CSV, XLSX, XLS</p>
                <input type="file" name="excel_file" accept=".csv,.xlsx,.xls" required>
                <button type="submit" name="upload" class="btn-upload">
                    <i class="fas fa-upload"></i> Upload Students
                </button>
            </div>
        </form>

        <a href="admin_dashboard.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

</body>
</html>