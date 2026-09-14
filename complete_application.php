<?php
session_start();
include 'connect.php';

// Check if student is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Fetch student data
$query = "SELECT * FROM students WHERE id = $student_id";
$result = mysqli_query($conn, $query);
$student = mysqli_fetch_assoc($result);

// ============================================
// HANDLE FORM SUBMISSION
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['complete_application'])) {
    $fullname = mysqli_real_escape_string($conn, trim($_POST['fullname']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $dob = mysqli_real_escape_string($conn, $_POST['dob']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $address = mysqli_real_escape_string($conn, trim($_POST['address']));
    $guardian_name = mysqli_real_escape_string($conn, trim($_POST['guardian_name']));
    $guardian_phone = mysqli_real_escape_string($conn, trim($_POST['guardian_phone']));
    
    $update = "UPDATE students SET 
        fullname = '$fullname',
        phone = '$phone',
        dob = '$dob',
        gender = '$gender',
        address = '$address',
        guardian_name = '$guardian_name',
        guardian_phone = '$guardian_phone',
        status = 'pending'
        WHERE id = $student_id";
    
    if (mysqli_query($conn, $update)) {
        $success = "✅ Application completed successfully!";
        // Refresh student data
        $query = "SELECT * FROM students WHERE id = $student_id";
        $result = mysqli_query($conn, $query);
        $student = mysqli_fetch_assoc($result);
    } else {
        $error = "❌ Error: " . mysqli_error($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Application - Dala College</title>
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
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 4px 30px rgba(0,0,0,0.1);
        }
        h1 { color: #0d2818; margin-bottom: 5px; }
        .sub { color: #6a8f6a; margin-bottom: 25px; }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #1a2e1a;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        .form-group label .required { color: #c62828; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #dce8dc;
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.3s ease;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: #2e7d32;
            outline: none;
            box-shadow: 0 0 0 3px rgba(46,125,50,0.1);
        }
        
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
        
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #2e7d32, #1b5e20);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(46,125,50,0.3);
        }
        .btn-submit i { margin-right: 8px; }
        
        .btn-back {
            display: inline-block;
            margin-top: 15px;
            color: #2e7d32;
            text-decoration: none;
            font-weight: 600;
        }
        .btn-back:hover { text-decoration: underline; }
        
        .info-box {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #1976d2;
        }
        .info-box i { color: #0d47a1; margin-right: 8px; }
        
        @media (max-width: 768px) {
            .container { padding: 25px; }
            .form-row { grid-template-columns: 1fr; }
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
            <a href="student_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="container">
        <h1><i class="fas fa-pen-fancy" style="color:#2e7d32;"></i> Complete Application</h1>
        <p class="sub">Please fill in all required fields to complete your application</p>

        <?php if ($success): ?>
            <div class="alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <strong>Student:</strong> <?php echo htmlspecialchars($student['fullname']); ?> 
            (<?php echo htmlspecialchars($student['reg_no'] ?? $student['student_id']); ?>)
        </div>

        <form method="POST" action="">
            <!-- Personal Information -->
            <h3 style="color: #0d2818; margin-bottom: 15px; border-bottom: 2px solid #e8f5e9; padding-bottom: 10px;">
                <i class="fas fa-user"></i> Personal Information
            </h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" name="fullname" required 
                           value="<?php echo htmlspecialchars($student['fullname'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Phone Number <span class="required">*</span></label>
                    <input type="tel" name="phone" required 
                           value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Date of Birth <span class="required">*</span></label>
                    <input type="date" name="dob" required 
                           value="<?php echo htmlspecialchars($student['dob'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Gender <span class="required">*</span></label>
                    <select name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male" <?php echo ($student['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($student['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo ($student['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Address <span class="required">*</span></label>
                <textarea name="address" rows="3" required placeholder="Enter your full address"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
            </div>

            <!-- Guardian Information -->
            <h3 style="color: #0d2818; margin: 20px 0 15px; border-bottom: 2px solid #e8f5e9; padding-bottom: 10px;">
                <i class="fas fa-user-friends"></i> Guardian Information
            </h3>

            <div class="form-row">
                <div class="form-group">
                    <label>Guardian Name <span class="required">*</span></label>
                    <input type="text" name="guardian_name" required 
                           value="<?php echo htmlspecialchars($student['guardian_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Guardian Phone <span class="required">*</span></label>
                    <input type="tel" name="guardian_phone" required 
                           value="<?php echo htmlspecialchars($student['guardian_phone'] ?? ''); ?>">
                </div>
            </div>

            <!-- Academic Information (Read Only) -->
            <h3 style="color: #0d2818; margin: 20px 0 15px; border-bottom: 2px solid #e8f5e9; padding-bottom: 10px;">
                <i class="fas fa-graduation-cap"></i> Academic Information (Read Only)
            </h3>

            <div class="form-row">
                <div class="form-group">
                    <label>Programme</label>
                    <input type="text" value="<?php echo htmlspecialchars($student['programme'] ?? 'NCE'); ?>" disabled style="background:#f5f5f5;">
                </div>
                <div class="form-group">
                    <label>Course</label>
                    <input type="text" value="<?php echo htmlspecialchars($student['course'] ?? ''); ?>" disabled style="background:#f5f5f5;">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Level</label>
                    <input type="text" value="<?php echo htmlspecialchars($student['level'] ?? 'NCE I'); ?>" disabled style="background:#f5f5f5;">
                </div>
                <div class="form-group">
                    <label>Registration Number</label>
                    <input type="text" value="<?php echo htmlspecialchars($student['reg_no'] ?? $student['student_id'] ?? ''); ?>" disabled style="background:#f5f5f5;">
                </div>
            </div>

            <button type="submit" name="complete_application" class="btn-submit">
                <i class="fas fa-save"></i> Submit Application
            </button>
        </form>

        <a href="student_dashboard.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

</body>
</html>