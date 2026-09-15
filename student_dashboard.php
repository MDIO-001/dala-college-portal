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

// ============================================
// HANDLE CHANGE USERNAME (STUDENT)
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_username'])) {
    $user_id = $_SESSION['user_id'];
    $new_username = mysqli_real_escape_string($conn, trim($_POST['new_username']));
    
    if (empty($new_username)) {
        $username_error = "❌ Username is required.";
    } elseif (!preg_match('/^[a-zA-Z0-9@._-]+$/', $new_username)) {
        $username_error = "❌ Username contains invalid characters. Use letters, numbers, @, ., _, - only.";
    } elseif (strlen($new_username) < 3) {
        $username_error = "❌ Username must be at least 3 characters.";
    } else {
        $check = mysqli_query($conn, "SELECT id FROM students WHERE username = '$new_username' AND id != $user_id");
        if ($check && mysqli_num_rows($check) > 0) {
            $username_error = "❌ Username already taken. Please choose another.";
        } else {
            $update = "UPDATE students SET username = '$new_username' WHERE id = $user_id";
            if (mysqli_query($conn, $update)) {
                $_SESSION['username'] = $new_username;
                $username_success = "✅ Username changed successfully! New username: <strong>$new_username</strong>";
            } else {
                $username_error = "❌ Error: " . mysqli_error($conn);
            }
        }
    }
}

// ============================================
// HANDLE PHOTO UPLOAD
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['photo'])) {
    $target_dir = "uploads/students/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_name = time() . '_' . basename($_FILES['photo']['name']);
    $target_file = $target_dir . $file_name;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    $check = getimagesize($_FILES['photo']['tmp_name']);
    if ($check !== false) {
        if (in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
            if ($_FILES['photo']['size'] < 5000000) {
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                    $update = "UPDATE students SET photo = '$file_name' WHERE id = $student_id";
                    if (mysqli_query($conn, $update)) {
                        $success = '✅ Photo uploaded successfully!';
                        $_SESSION['photo'] = $file_name;
                    } else {
                        $error = '❌ Database error: ' . mysqli_error($conn);
                    }
                } else {
                    $error = '❌ Failed to upload photo.';
                }
            } else {
                $error = '❌ File is too large. Maximum size is 5MB.';
            }
        } else {
            $error = '❌ Only JPG, JPEG, PNG, and GIF files are allowed.';
        }
    } else {
        $error = '❌ File is not a valid image.';
    }
}

// ============================================
// FETCH STUDENT DATA
// ============================================
$query = "SELECT * FROM students WHERE id = $student_id";
$result = mysqli_query($conn, $query);
$student = mysqli_fetch_assoc($result);

if (!$student) {
    echo "Student not found!";
    exit();
}

// ============================================
// CHECK ADMISSION STATUS
// ============================================
$is_approved = ($student['status'] == 'active' || $student['status'] == 'approved');

$app_query = "SELECT * FROM applications WHERE email = '{$student['email']}' OR student_id = '{$student['id']}' LIMIT 1";
$app_result = mysqli_query($conn, $app_query);
$application = mysqli_fetch_assoc($app_result);

$can_download_letter = false;
if ($application && ($application['status'] == 'approved' || $is_approved)) {
    $can_download_letter = true;
} elseif ($is_approved) {
    $can_download_letter = true;
}

$branch_names = [
    'SHINGE' => 'A - Shinge',
    'SABUWA' => 'B - Sabuwar Kofa',
    'TUDUN' => 'C - Tudun Yola'
];
$branch_display = isset($branch_names[$student['branch_code']]) ? $branch_names[$student['branch_code']] : $student['branch_code'];

$photo_path = 'uploads/students/';
$photo_file = !empty($student['photo']) ? $photo_path . $student['photo'] : 'assets/default-avatar.png';

date_default_timezone_set('Africa/Lagos');
$current_date = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Dala College</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; background:#f0f4f8; min-height:100vh; }
        
        .topbar { background:#0d2818; padding:15px 30px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; }
        .topbar .logo-title { color:#ffd54f; font-size:1.3rem; font-weight:800; }
        .topbar .logo-title span { color:#a5d6a7; }
        .topbar .logo-sub { display:block; font-size:0.6rem; color:#c8e6c9; }
        .topbar nav a { color:#c8e6c9; text-decoration:none; padding:8px 18px; border-radius:25px; transition:all 0.3s ease; }
        .topbar nav a:hover { background:#2e7d32; }
        
        .container { max-width:1200px; margin:20px auto; padding:0 20px; }
        
        .welcome-box {
            background:white; padding:25px 30px; border-radius:12px;
            margin-bottom:25px; box-shadow:0 2px 10px rgba(0,0,0,0.08);
            display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;
        }
        .welcome-box h2 { color:#0d2818; }
        .welcome-box h2 span { color:#2e7d32; }
        .welcome-box .date { color:#6a8f6a; }
        .status-badge { padding:8px 20px; border-radius:25px; font-weight:700; font-size:0.85rem; }
        .status-badge.pending { background:#fff3e0; color:#e65100; }
        .status-badge.approved { background:#e8f5e9; color:#2e7d32; }
        .status-badge.active { background:#e8f5e9; color:#1b5e20; }
        .status-badge.rejected { background:#ffebee; color:#c62828; }
        
        .admission-banner {
            padding:20px 25px; border-radius:12px; margin-bottom:25px;
            display:flex; justify-content:space-between; align-items:center;
            flex-wrap:wrap; gap:15px;
        }
        .admission-banner.approved { background:linear-gradient(135deg, #e8f5e9, #c8e6c9); border-left:5px solid #2e7d32; }
        .admission-banner.pending { background:#fff3e0; border-left:5px solid #ffa000; }
        .admission-banner h3 { margin-bottom:5px; }
        .admission-banner.approved h3 { color:#1b5e20; }
        .admission-banner.pending h3 { color:#e65100; }
        .admission-banner p { font-size:0.9rem; }
        .admission-banner.approved p { color:#2e7d32; }
        .admission-banner.pending p { color:#6a8f6a; }
        .admission-banner .btn-download {
            background:#2e7d32; color:white; padding:12px 25px;
            border-radius:8px; text-decoration:none; font-weight:700;
        }
        .admission-banner .btn-download:hover { background:#1b5e20; }
        
        .alert-success { background:#e8f5e9; color:#2e7d32; padding:12px 18px; border-radius:8px; margin-bottom:15px; border-left:4px solid #2e7d32; }
        .alert-error { background:#ffebee; color:#c62828; padding:12px 18px; border-radius:8px; margin-bottom:15px; border-left:4px solid #c62828; }
        
        .dashboard-grid { display:grid; grid-template-columns:1fr 2fr; gap:25px; }
        
        .profile-card { background:white; border-radius:12px; padding:25px; box-shadow:0 2px 10px rgba(0,0,0,0.08); text-align:center; }
        .profile-card .avatar { width:150px; height:150px; border-radius:50%; object-fit:cover; border:4px solid #2e7d32; margin:0 auto 15px; display:block; }
        .profile-card .avatar-placeholder { width:150px; height:150px; border-radius:50%; background:#e8f5e9; display:flex; align-items:center; justify-content:center; margin:0 auto 15px; font-size:4rem; color:#2e7d32; border:4px solid #2e7d32; }
        .profile-card h3 { color:#0d2818; margin-bottom:5px; }
        .profile-card .student-id { color:#6a8f6a; font-size:0.85rem; margin-bottom:15px; }
        .profile-card .info-item { text-align:left; padding:8px 0; border-bottom:1px solid #f0f4f8; display:flex; justify-content:space-between; }
        .profile-card .info-item:last-child { border-bottom:none; }
        .profile-card .info-item .label { color:#6a8f6a; font-weight:600; }
        .profile-card .info-item .value { color:#0d2818; }
        
        .upload-form { margin-top:15px; padding-top:15px; border-top:2px solid #e8f5e9; }
        .upload-form input[type="file"] { display:block; margin:10px auto; padding:8px; border:2px dashed #dce8dc; border-radius:8px; width:100%; }
        .upload-form .btn-upload { background:#2e7d32; color:white; border:none; padding:8px 25px; border-radius:8px; cursor:pointer; font-weight:600; }
        .upload-form .btn-upload:hover { background:#1b5e20; }
        
        .actions-card { background:white; border-radius:12px; padding:25px; box-shadow:0 2px 10px rgba(0,0,0,0.08); }
        .actions-card h3 { color:#0d2818; margin-bottom:20px; padding-bottom:15px; border-bottom:2px solid #e8f5e9; }
        .actions-grid { display:grid; grid-template-columns:1fr 1fr; gap:15px; }
        .action-btn { display:flex; align-items:center; gap:12px; padding:15px 20px; background:#f8faf8; border-radius:10px; text-decoration:none; color:#0d2818; transition:all 0.3s ease; border:1px solid #e8f5e9; }
        .action-btn:hover { background:#e8f5e9; transform:translateY(-2px); box-shadow:0 4px 15px rgba(46,125,50,0.15); }
        .action-btn .icon { font-size:1.5rem; color:#2e7d32; width:40px; height:40px; display:flex; align-items:center; justify-content:center; background:white; border-radius:8px; }
        .action-btn .text { font-weight:600; }
        .action-btn .sub-text { font-size:0.75rem; color:#6a8f6a; font-weight:400; }
        
        .action-btn.locked { opacity:0.6; cursor:not-allowed; border-color:#ffcc80; background:#fff8e1; }
        .action-btn.locked .icon { color:#ffa000; }
        .action-btn.locked .text { color:#e65100; }
        
        .full-width { grid-column:1 / -1; }
        
        .btn-teal { background:#00695c; color:white; border:none; padding:10px 25px; border-radius:8px; font-weight:700; cursor:pointer; }
        .btn-teal:hover { background:#004d40; transform:translateY(-2px); }
        
        footer { text-align:center; padding:20px; color:#6a8f6a; margin-top:30px; }
        
        @media (max-width:768px) {
            .dashboard-grid { grid-template-columns:1fr; }
            .actions-grid { grid-template-columns:1fr; }
            .welcome-box { flex-direction:column; text-align:center; }
            .topbar { flex-direction:column; gap:10px; text-align:center; }
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

        <!-- WELCOME BOX -->
        <div class="welcome-box">
            <div>
                <h2>Welcome, <span><?php echo htmlspecialchars($student['fullname']); ?></span></h2>
                <div class="date">📅 <?php echo $current_date; ?></div>
            </div>
            <div>
                <span class="status-badge <?php echo strtolower($student['status']); ?>">
                    <?php 
                    if ($student['status'] == 'active' || $student['status'] == 'approved') {
                        echo '✅ ADMITTED';
                    } elseif ($student['status'] == 'pending') {
                        echo '⏳ PENDING';
                    } else {
                        echo '❌ ' . strtoupper($student['status']);
                    }
                    ?>
                </span>
            </div>
        </div>

        <!-- ADMISSION BANNER -->
        <?php if ($can_download_letter): ?>
            <div class="admission-banner approved">
                <div>
                    <h3>🎉 Congratulations! Your Application is Approved</h3>
                    <p>You can now download your <strong>Admission Letter</strong>.</p>
                </div>
                <a href="admission_letter.php" class="btn-download">
                    <i class="fas fa-download"></i> Download Admission Letter
                </a>
            </div>
        <?php else: ?>
            <div class="admission-banner pending">
                <div>
                    <h3>⏳ Admission Letter Not Available</h3>
                    <p>Your application is still pending review. You will be able to download your <strong>Admission Letter</strong> once an admin approves your application.</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- CHANGE USERNAME -->
        <div style="background:white; border-radius:12px; padding:25px; margin-bottom:25px; box-shadow:0 2px 10px rgba(0,0,0,0.08);">
            <h3 style="color:#0d2818; margin-bottom:10px;">
                <i class="fas fa-user-tag" style="color:#2e7d32;"></i> Change Username
            </h3>
            <p style="color:#6a8f6a; font-size:0.9rem; margin-bottom:15px;">
                Current Username: <strong><?php echo htmlspecialchars($student['username'] ?? ''); ?></strong>
            </p>
            
            <?php if (isset($username_success)): ?>
                <div class="alert-success"><?php echo $username_success; ?></div>
            <?php endif; ?>
            <?php if (isset($username_error)): ?>
                <div class="alert-error"><?php echo $username_error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div style="display:flex; gap:15px; flex-wrap:wrap; align-items:center;">
                    <div style="flex:1; min-width:200px;">
                        <input type="text" name="new_username" placeholder="Enter new username" 
                               style="width:100%; padding:12px 15px; border:2px solid #dce8dc; border-radius:8px; font-size:0.95rem;"
                               required pattern="[a-zA-Z0-9@._-]+">
                        <small style="color:#6a8f6a; font-size:0.75rem;">Allowed: letters, numbers, @, ., _, - (min 3 characters)</small>
                    </div>
                    <button type="submit" name="change_username" class="btn-teal">
                        <i class="fas fa-save"></i> Update Username
                    </button>
                </div>
            </form>
        </div>

        <!-- DASHBOARD GRID -->
        <div class="dashboard-grid">

            <!-- PROFILE CARD -->
            <div class="profile-card">
                <?php if (!empty($student['photo']) && file_exists($photo_path . $student['photo'])): ?>
                    <img src="<?php echo $photo_path . $student['photo']; ?>" alt="Profile Photo" class="avatar">
                <?php else: ?>
                    <div class="avatar-placeholder">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                <?php endif; ?>
                
                <h3><?php echo htmlspecialchars($student['fullname']); ?></h3>
                <div class="student-id">
                    <strong>Username:</strong> <?php echo htmlspecialchars($student['username']); ?>
                </div>
                <div class="student-id">
                    <strong>Student ID:</strong> <?php echo htmlspecialchars($student['student_id']); ?>
                </div>

                <div class="info-item"><span class="label">Branch</span><span class="value"><?php echo $branch_display; ?></span></div>
                <div class="info-item"><span class="label">Programme</span><span class="value"><?php echo htmlspecialchars($student['programme']); ?></span></div>
                <div class="info-item"><span class="label">Course</span><span class="value"><?php echo htmlspecialchars($student['course']); ?></span></div>
                <div class="info-item"><span class="label">Level</span><span class="value"><?php echo htmlspecialchars($student['level'] ?? 'NCE I'); ?></span></div>
                <div class="info-item"><span class="label">Phone</span><span class="value"><?php echo htmlspecialchars($student['phone']); ?></span></div>
                <div class="info-item"><span class="label">Gender</span><span class="value"><?php echo htmlspecialchars($student['gender'] ?? 'Not provided'); ?></span></div>
                <div class="info-item"><span class="label">Date of Birth</span><span class="value"><?php echo !empty($student['dob']) ? htmlspecialchars($student['dob']) : 'Not provided'; ?></span></div>
                <div class="info-item">
                    <span class="label">Status</span>
                    <span class="value" style="color:<?php echo ($is_approved) ? '#2e7d32' : '#e65100'; ?>; font-weight:700;">
                        <?php echo strtoupper($student['status']); ?>
                    </span>
                </div>

                <div class="upload-form">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <label style="font-weight:600; color:#0d2818;">
                            <i class="fas fa-camera"></i> Update Photo
                        </label>
                        <input type="file" name="photo" accept="image/*" required>
                        <button type="submit" class="btn-upload">
                            <i class="fas fa-upload"></i> Upload Photo
                        </button>
                    </form>
                </div>
            </div>

            <!-- QUICK ACTIONS -->
            <div class="actions-card">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                <div class="actions-grid">
                    
                    <!-- APPLICATION FORM -->
                    <a href="application_form.php" class="action-btn">
                        <div class="icon" style="color:#1976d2;"><i class="fas fa-file-signature"></i></div>
                        <div>
                            <div class="text">Application Form</div>
                            <div class="sub-text">Fill your application form</div>
                        </div>
                    </a>
                    
                    <!-- ADMISSION LETTER -->
                    <?php if ($can_download_letter): ?>
                        <a href="admission_letter.php" class="action-btn">
                            <div class="icon" style="color:#2e7d32;"><i class="fas fa-file-download"></i></div>
                            <div>
                                <div class="text">Admission Letter</div>
                                <div class="sub-text">Download your admission letter</div>
                            </div>
                        </a>
                    <?php else: ?>
                        <div class="action-btn locked">
                            <div class="icon"><i class="fas fa-lock"></i></div>
                            <div>
                                <div class="text">Admission Letter</div>
                                <div class="sub-text">⏳ Wait for admin approval</div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- INTRODUCTORY LETTER -->
                    <a href="introductory_letter.php" class="action-btn">
                        <div class="icon" style="color:#5d4037;"><i class="fas fa-file-alt"></i></div>
                        <div>
                            <div class="text">Introductory Letter</div>
                            <div class="sub-text">For Teaching Practice</div>
                        </div>
                    </a>
                    
                    <!-- COURSE REGISTRATION -->
                    <a href="course_registration.php" class="action-btn">
                        <div class="icon"><i class="fas fa-book-open"></i></div>
                        <div>
                            <div class="text">Course Registration</div>
                            <div class="sub-text">Register for courses this semester</div>
                        </div>
                    </a>
                    
                    <!-- EXAM CARD -->
                    <a href="exam_card.php" class="action-btn">
                        <div class="icon"><i class="fas fa-id-card"></i></div>
                        <div>
                            <div class="text">Exam Card</div>
                            <div class="sub-text">Download your exam card</div>
                        </div>
                    </a>
                    
                    <!-- PRINT RECEIPT -->
                    <a href="print_receipt.php" class="action-btn">
                        <div class="icon"><i class="fas fa-receipt"></i></div>
                        <div>
                            <div class="text">Print Receipt</div>
                            <div class="sub-text">Download payment receipt</div>
                        </div>
                    </a>
                    
                    <!-- POSTING LETTER -->
<a href="verify_card.php?type=posting_letter" class="action-btn">
    <div class="icon"><i class="fas fa-map-marker-alt"></i></div>
    <div>
        <div class="text">Posting Letter (T.P)</div>
        <div class="sub-text">Enter Scratch Card to download</div>
    </div>
</a>
<a href="verify_card.php?type=acceptance_letter" class="action-btn">
    <i class="fas fa-file-signature"></i>
    <div>
        <div class="text">Acceptance Letter</div>
        <div class="sub-text">Enter Scratch Card to download</div>
    </div>
</a>
                    
                    <!-- LOGOUT -->
                    <a href="logout.php" class="action-btn full-width" style="border-color:#ffcdd2;">
                        <div class="icon" style="color:#c62828;"><i class="fas fa-sign-out-alt"></i></div>
                        <div>
                            <div class="text" style="color:#c62828;">Logout</div>
                            <div class="sub-text">Sign out of your account</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <p>© <?php echo date('Y'); ?> Dala College of Education, Kano. All Rights Reserved.</p>
    </footer>

</body>
</html>