<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'connect.php';

$success = '';
$error = '';
$show_success = false;

// ============================================
// AUTO-GENERATE USERNAME: firstname@123
// ============================================
if (!function_exists('generateUsername')) {
    function generateUsername($conn, $fullname) {
        // Ɗauki sunan farko kawai
        $name_parts = explode(' ', trim($fullname));
        $first_name = strtolower(preg_replace('/[^a-zA-Z]/', '', $name_parts[0] ?? 'student'));
        
        if (empty($first_name)) $first_name = 'student';
        
        // ============================================
        // TSARIN: firstname@123, firstname1@123, firstname2@123...
        // ============================================
        $suffix = '@123';
        $username = $first_name . $suffix;
        $counter = 1;
        
        // Duba duplicate
        $check = mysqli_query($conn, "SELECT id FROM students WHERE username = '$username' LIMIT 1");
        
        while ($check && mysqli_num_rows($check) > 0) {
            // Sanya lamba KAFIN @123
            $username = $first_name . $counter . $suffix;
            $counter++;
            $check = mysqli_query($conn, "SELECT id FROM students WHERE username = '$username' LIMIT 1");
            
            if ($counter > 999) {
                $username = $first_name . time() . $suffix;
                break;
            }
        }
        
        return $username;
    }
}

// ============================================
// HANDLE FORM SUBMISSION
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_application'])) {
    
    $fullname = mysqli_real_escape_string($conn, trim($_POST['fullname'] ?? ''));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));
    $programme = mysqli_real_escape_string($conn, $_POST['programme'] ?? '');
    $course = mysqli_real_escape_string($conn, trim($_POST['course'] ?? ''));
    $dob = mysqli_real_escape_string($conn, $_POST['dob'] ?? '');
    $address = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));
    $gender = mysqli_real_escape_string($conn, $_POST['gender'] ?? '');
    $branch = mysqli_real_escape_string($conn, $_POST['branch'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $scratch_pin = mysqli_real_escape_string($conn, trim($_POST['scratch_pin'] ?? ''));
    
    $errors = [];
    
    if (empty($fullname)) $errors[] = 'Full name is required.';
    if (empty($phone)) $errors[] = 'Phone number is required.';
    if (empty($programme)) $errors[] = 'Programme is required.';
    if (empty($course)) $errors[] = 'Course is required.';
    if (empty($branch)) $errors[] = 'Please select a branch.';
    if (empty($password)) $errors[] = 'Password is required.';
    if (empty($confirm_password)) $errors[] = 'Please confirm your password.';
    if (empty($scratch_pin)) $errors[] = 'Scratch Card PIN is required.';
    
    if (!empty($phone) && !preg_match('/^[0-9]{10,15}$/', $phone)) {
        $errors[] = 'Please enter a valid phone number (10-15 digits).';
    }
    if (!empty($password) && strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if (!empty($password) && !empty($confirm_password) && $password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    
    // ============================================
    // CHECK PHONE (NO DUPLICATE)
    // ============================================
    if (!empty($phone)) {
        $check_phone = mysqli_query($conn, "SELECT id FROM students WHERE phone = '$phone'");
        if (mysqli_num_rows($check_phone) > 0) {
            $errors[] = 'This phone number is already registered. Please contact support.';
        }
    }
    
    // ============================================
    // VERIFY SCRATCH CARD (APPLICATION)
    // ============================================
    $card = null;
    if (!empty($scratch_pin)) {
        $card_check = mysqli_query($conn, "SELECT * FROM scratch_cards 
                                           WHERE (pin = '$scratch_pin' OR card_code = '$scratch_pin') 
                                           AND card_type = 'application'");
        
        if (!$card_check) {
            $errors[] = '❌ Database error: ' . mysqli_error($conn);
        } elseif (mysqli_num_rows($card_check) == 0) {
            $errors[] = '❌ Invalid Scratch Card or not an Application card.';
        } else {
            $card = mysqli_fetch_assoc($card_check);
            
            if ($card['status'] == 'used') {
                $errors[] = '❌ This Scratch Card has already been used.';
            }
            if ($card['is_active'] != 1) {
                $errors[] = '❌ This Scratch Card is not active.';
            }
            if (!empty($card['expiry_date']) && $card['expiry_date'] < date('Y-m-d')) {
                $errors[] = '❌ This Scratch Card has expired.';
            }
            if ($card['usage_count'] >= $card['usage_limit']) {
                $errors[] = '❌ This Scratch Card has reached its usage limit.';
            }
        }
    }
    
    if (empty($errors)) {
        // ============================================
        // AUTO-GENERATE USERNAME
        // ============================================
        $username = generateUsername($conn, $fullname);
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $password_plain = mysqli_real_escape_string($conn, $password);
        
        // ============================================
        // DETERMINE LEVEL AND YEARS BASED ON PROGRAMME
        // ============================================
        $programme_upper = strtoupper(trim($programme));
        
        if ($programme_upper == 'NCE') {
            $student_level = 'NCE I';
            $entry_year = '2026/2027';
            $graduation_year = '2028/2029';
        } elseif ($programme_upper == 'DEGREE' || $programme_upper == 'DEG') {
            $student_level = '400 Level';
            $entry_year = '2026/2027';
            $graduation_year = '2029/2030';
        } elseif ($programme_upper == 'ENTREPRENEURSHIP' || $programme_upper == 'ENT') {
            $student_level = 'ENTREPRENEURSHIP';
            $entry_year = '2026/2027';
            $graduation_year = '2027/2028';
        } else {
            $student_level = 'NCE I';
            $entry_year = '2026/2027';
            $graduation_year = '2028/2029';
        }
        
        // ============================================
        // INSERT STUDENT
        // ============================================
        $insert = "INSERT INTO students (
            username, password, password_plain, fullname, phone, 
            programme, course, combination, dob, address, gender, 
            branch_code, status, level, entry_year, graduation_year, created_at
        ) VALUES (
            '$username', '$hashed_password', '$password_plain', '$fullname', '$phone',
            '$programme', '$course', '$course', '$dob', '$address', '$gender', 
            '$branch', 'pending', '$student_level', '$entry_year', '$graduation_year', NOW()
        )";
        
        if (mysqli_query($conn, $insert)) {
            $new_id = mysqli_insert_id($conn);
            
            // ============================================
            // UPDATE SCRATCH CARD
            // ============================================
            if ($card) {
                mysqli_query($conn, "UPDATE scratch_cards 
                                     SET status = 'used', 
                                         used_by = $new_id, 
                                         used_at = NOW(),
                                         usage_count = usage_count + 1
                                     WHERE id = {$card['id']}");
            }
            
            // ============================================
            // INSERT INTO APPLICATIONS TABLE
            // ============================================
            $app_insert = "INSERT INTO applications (
                student_id, fullname, phone, course_applied, programme, branch_code, created_at, status
            ) VALUES (
                '$new_id', '$fullname', '$phone', '$course', '$programme', '$branch', NOW(), 'pending'
            )";
            mysqli_query($conn, $app_insert);
            
            // ============================================
            // SET SESSION
            // ============================================
            $_SESSION['user_id'] = $new_id;
            $_SESSION['fullname'] = $fullname;
            $_SESSION['phone'] = $phone;
            $_SESSION['role'] = 'student';
            $_SESSION['branch_code'] = $branch;
            $_SESSION['course'] = $course;
            $_SESSION['level'] = $student_level;
            $_SESSION['username'] = $username;
            $_SESSION['password_plain'] = $password;
            
            $success = '✅ Application submitted successfully!';
            $show_success = true;
            
            // ============================================
            // TURA ZUWA PAYMENT PAGE
            // ============================================
            echo '<meta http-equiv="refresh" content="5;url=application_payment.php?student_id=' . $new_id . '">';
        } else {
            $error = '❌ Error: ' . mysqli_error($conn);
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply Now - Dala College</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f0f4f8 0%, #dce8dc 100%);
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
            max-width: 700px;
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
        .topbar nav .btn-nav { 
            background: #ffd54f; 
            color: #0d2818 !important; 
            font-weight: 700; 
        }
        
        .container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 4px 30px rgba(0,0,0,0.1);
        }
        h1 { text-align: center; color: #0d2818; font-size: 2rem; }
        .sub { text-align: center; color: #6a8f6a; margin-bottom: 25px; }
        
        .form-group { margin-bottom: 18px; }
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
            padding: 12px 15px;
            border: 2px solid #dce8dc;
            border-radius: 10px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.3s ease;
            background: white;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: #2e7d32;
            outline: none;
            box-shadow: 0 0 0 3px rgba(46,125,50,0.1);
        }
        .form-group .hint {
            font-size: 0.75rem;
            color: #6a8f6a;
            margin-top: 3px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .scratch-box {
            background: #fff3e0;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #f57c00;
            margin-bottom: 20px;
        }
        .scratch-box label {
            display: block;
            font-weight: 700;
            color: #e65100;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        .scratch-box input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ffb74d;
            border-radius: 10px;
            font-size: 1rem;
            font-family: inherit;
            letter-spacing: 2px;
            text-align: center;
            font-weight: 700;
            text-transform: uppercase;
        }
        .scratch-box input:focus {
            border-color: #f57c00;
            outline: none;
            box-shadow: 0 0 0 3px rgba(245,124,0,0.15);
        }
        .scratch-box .hint {
            font-size: 0.75rem;
            color: #e65100;
            margin-top: 5px;
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
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(46,125,50,0.3);
        }
        .btn-submit i { margin-right: 8px; }
        
        .alert-error {
            background: #ffebee;
            color: #c62828;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #c62828;
        }
        
        .success-box {
            background: #e8f5e9;
            border: 2px solid #2e7d32;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            margin-bottom: 20px;
        }
        .success-box .icon {
            font-size: 4rem;
            color: #2e7d32;
            margin-bottom: 10px;
        }
        .success-box h2 {
            color: #2e7d32;
            margin-bottom: 10px;
        }
        .success-box p {
            color: #1a2e1a;
            font-size: 1rem;
            margin-bottom: 5px;
        }
        .success-box .highlight {
            background: white;
            padding: 10px 15px;
            border-radius: 8px;
            margin: 10px 0;
            display: inline-block;
            text-align: left;
        }
        .success-box .highlight strong {
            color: #2e7d32;
        }
        
        .login-info-box {
            background: #fff9c4;
            border: 2px solid #f9a825;
            border-radius: 10px;
            padding: 18px 20px;
            margin: 18px 0;
            text-align: left;
        }
        .login-info-box h4 {
            color: #0d2818;
            margin-bottom: 12px;
            font-size: 1rem;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .login-info-box .row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px dashed #f9a825;
        }
        .login-info-box .row:last-child { border-bottom: none; }
        .login-info-box .label { 
            color: #666; 
            font-size: 0.85rem; 
            font-weight: 600;
        }
        .login-info-box .value { 
            color: #0d2818; 
            font-weight: 800; 
            font-family: 'Courier New', monospace; 
            font-size: 1rem;
            background: white;
            padding: 4px 12px;
            border-radius: 6px;
            border: 1px solid #f9a825;
            letter-spacing: 0.5px;
        }
        
        .warning-msg {
            background: #ffebee;
            color: #c62828;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 700;
            margin: 12px 0;
            border-left: 4px solid #c62828;
            text-align: left;
        }
        
        .success-box .redirect-timer {
            color: #6a8f6a;
            font-size: 0.9rem;
            margin-top: 15px;
        }
        .success-box .btn-dashboard {
            display: inline-block;
            padding: 12px 35px;
            background: linear-gradient(135deg, #2e7d32, #1b5e20);
            color: white;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 700;
            margin-top: 15px;
            transition: all 0.3s ease;
            font-size: 1rem;
        }
        .success-box .btn-dashboard:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(46,125,50,0.35);
        }
        
        .links { text-align: center; margin-top: 20px; padding-top: 20px; border-top: 2px solid #e8f5e9; }
        .links a { color: #2e7d32; text-decoration: none; font-weight: 600; }
        
        .password-strength {
            height: 4px;
            border-radius: 4px;
            margin-top: 5px;
            transition: all 0.3s ease;
            background: #e0e0e0;
        }
        .password-strength.weak { background: #c62828; width: 33%; }
        .password-strength.medium { background: #ffa000; width: 66%; }
        .password-strength.strong { background: #2e7d32; width: 100%; }
        
        .info-box {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #1976d2;
        }
        .info-box i { color: #0d47a1; margin-right: 8px; }
        .info-box strong { color: #0d47a1; }
        
        footer {
            max-width: 700px;
            margin: 20px auto 0;
            background: #0d2818;
            color: #a5d6a7;
            text-align: center;
            padding: 20px;
            border-radius: 12px;
        }
        
        @media (max-width: 768px) {
            .container { padding: 25px; margin: 10px; }
            .topbar { flex-direction: column; gap: 10px; text-align: center; padding: 15px; }
            .form-row { grid-template-columns: 1fr; }
            h1 { font-size: 1.5rem; }
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
            <a href="index.html">Home</a>
            <a href="apply.php" class="btn-nav">Apply</a>
            <a href="login.php">Login</a>
        </nav>
    </div>

    <div class="container">
        <h1>📝 Apply Now</h1>
        <p class="sub">Join Dala College of Education, Kano</p>

        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <strong>Application:</strong> Fill the form below and enter your Scratch Card PIN.
        </div>

        <?php if ($error): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($show_success && $success): ?>
            <div class="success-box">
                <div class="icon">✅</div>
                <h2>Application Submitted Successfully!</h2>
                <p>Your application has been received. Please proceed to payment.</p>
                
                <div class="highlight">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($fullname); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($phone); ?></p>
                    <p><strong>Programme:</strong> <?php echo htmlspecialchars($programme); ?></p>
                    <p><strong>Course:</strong> <?php echo htmlspecialchars($course); ?></p>
                    <p><strong>Branch:</strong> <?php echo htmlspecialchars($branch); ?></p>
                    <p><strong>Level:</strong> <?php echo htmlspecialchars($student_level ?? 'NCE I'); ?></p>
                </div>
                
                <!-- ============================================ -->
                <!-- LOGIN DETAILS -->
                <!-- ============================================ -->
                <div class="login-info-box">
                    <h4>🔑 Your Login Details</h4>
                    <div class="row">
                        <span class="label">Username:</span>
                        <span class="value"><?php echo htmlspecialchars($username); ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Password:</span>
                        <span class="value"><?php echo htmlspecialchars($password); ?></span>
                    </div>
                </div>
                
                <div class="warning-msg">
                    ⚠️ <strong>Muhimmanci:</strong> Adana waɗannan bayanan a wuri mai aminci. Za ka buƙaci su don shiga Dashboard ɗinka.
                </div>
                
                <p style="color:#6a8f6a; font-size:0.9rem;">
                    ⏳ Proceed to payment to complete your application
                </p>
                
                <div class="redirect-timer">
                    <i class="fas fa-spinner fa-spin"></i> 
                    Redirecting to payment page in <span id="countdown">5</span> seconds...
                </div>
                
                <a href="application_payment.php?student_id=<?php echo $new_id; ?>" class="btn-dashboard">
                    <i class="fas fa-credit-card"></i> Proceed to Payment Now
                </a>
            </div>
            
            <script>
            var seconds = 5;
            var countdown = setInterval(function() {
                seconds--;
                document.getElementById('countdown').textContent = seconds;
                if (seconds <= 0) {
                    clearInterval(countdown);
                    window.location.href = 'application_payment.php?student_id=<?php echo $new_id; ?>';
                }
            }, 1000);
            </script>
        <?php endif; ?>

        <?php if (!$show_success): ?>
        <form method="POST" action="" id="applyForm">
            
            <!-- ============================================ -->
            <!-- SCRATCH CARD -->
            <!-- ============================================ -->
            <div class="scratch-box">
                <label>🎫 Scratch Card PIN <span style="color:#c62828;">*</span></label>
                <input type="text" name="scratch_pin" placeholder="APL-1001-2001-3100" required maxlength="50" 
                       value="<?php echo isset($_POST['scratch_pin']) ? htmlspecialchars($_POST['scratch_pin']) : ''; ?>">
                <div class="hint">Enter your complete Scratch Card PIN (e.g., APL-1001-2001-3100)</div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" name="fullname" placeholder="Enter your full name" required 
                           value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label>Phone Number <span class="required">*</span></label>
                    <input type="tel" name="phone" placeholder="Enter your phone number" required 
                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    <div class="hint">We will use this to contact you</div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="dob" value="<?php echo isset($_POST['dob']) ? $_POST['dob'] : ''; ?>">
                </div>
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender">
                        <option value="">Select Gender</option>
                        <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Select Branch <span class="required">*</span></label>
                <select name="branch" id="branch" required>
                    <option value="">-- Select Branch --</option>
                    <option value="SHINGE" <?php echo (isset($_POST['branch']) && $_POST['branch'] == 'SHINGE') ? 'selected' : ''; ?>>A - Shinge</option>
                    <option value="SABUWA" <?php echo (isset($_POST['branch']) && $_POST['branch'] == 'SABUWA') ? 'selected' : ''; ?>>B - Sabuwar Kofa</option>
                    <option value="TUDUN" <?php echo (isset($_POST['branch']) && $_POST['branch'] == 'TUDUN') ? 'selected' : ''; ?>>C - Tudun Yola</option>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Select Programme <span class="required">*</span></label>
                    <select name="programme" id="programme" required onchange="updateCourses()">
                        <option value="">-- Select Programme --</option>
                        <option value="NCE" <?php echo (isset($_POST['programme']) && $_POST['programme'] == 'NCE') ? 'selected' : ''; ?>>NCE</option>
                        <option value="DEGREE" <?php echo (isset($_POST['programme']) && $_POST['programme'] == 'DEGREE') ? 'selected' : ''; ?>>Degree</option>
                        <option value="ENTREPRENEURSHIP" <?php echo (isset($_POST['programme']) && $_POST['programme'] == 'ENTREPRENEURSHIP') ? 'selected' : ''; ?>>Entrepreneurship</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Select Course <span class="required">*</span></label>
                    <select name="course" id="course" required>
                        <option value="">-- Select Course --</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Address</label>
                <textarea name="address" rows="2" placeholder="Enter your address"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Create Password <span class="required">*</span></label>
                    <input type="password" name="password" id="password" placeholder="Min 6 characters" required minlength="6" onkeyup="checkPasswordStrength()">
                    <div class="password-strength" id="passwordStrength"></div>
                </div>
                <div class="form-group">
                    <label>Confirm Password <span class="required">*</span></label>
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm your password" required onkeyup="checkPasswordMatch()">
                    <div class="hint" id="passwordMatch"></div>
                </div>
            </div>

            <button type="submit" name="submit_application" class="btn-submit" id="submitBtn">
                <i class="fas fa-paper-plane"></i> Submit Application
            </button>
        </form>
        <?php endif; ?>

        <div class="links">
            <p>Already have an account? <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login here</a></p>
        </div>
    </div>

    <footer>
        <p>© <?php echo date('Y'); ?> Dala College Kano. All Rights Reserved.</p>
    </footer>

    <script>
    const courses = {
        NCE: [
            { value: 'ARB/ISS', text: 'ARB/ISS - Arabic / Islamic Studies' },
            { value: 'ENG/ISS', text: 'ENG/ISS - English / Islamic Studies' },
            { value: 'PED', text: 'PED - Primary Education' },
            { value: 'ENG/HAU', text: 'ENG/HAU - English / Hausa' },
            { value: 'CSC/ISC', text: 'CSC/ISC - Computer Science / Islamic Studies' },
            { value: 'ENG/SOS', text: 'ENG/SOS - English / Social Studies' },
            { value: 'CSC/BIO', text: 'CSC/BIO - Computer Science / Biology' },
            { value: 'CSC/PHY', text: 'CSC/PHY - Computer Science / Physics' },
            { value: 'ENG/ECO', text: 'ENG/ECO - English / Economics' }
        ],
        DEGREE: [
            { value: 'PED', text: 'PED - Primary Education' }
        ],
        ENTREPRENEURSHIP: [
            { value: 'TAILORING', text: 'Tailoring and Fashion Design' },
            { value: 'AI_TECH', text: 'Computer / AI Technology' },
            { value: 'SALOON', text: 'Saloon and Hairdressing' },
            { value: 'HENNA', text: 'Henna Art and Decoration' },
            { value: 'FISH_FARMING', text: 'Fish Farming' },
            { value: 'POULTRY', text: 'Poultry Farming' },
            { value: 'SOAP_MAKING', text: 'Soap Making' },
            { value: 'CATERING', text: 'Catering and Confectionery' },
            { value: 'BEAD_MAKING', text: 'Bead Making and Crafts' },
            { value: 'GRAPHIC_DESIGN', text: 'Graphic Design' }
        ]
    };

    function updateCourses() {
        var programme = document.getElementById('programme');
        var courseSelect = document.getElementById('course');
        var selectedProgramme = programme.value;
        
        courseSelect.innerHTML = '';
        
        var defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = '-- Select Course --';
        courseSelect.appendChild(defaultOption);
        
        if (!selectedProgramme) return;
        
        var courseList = courses[selectedProgramme];
        
        if (courseList && courseList.length > 0) {
            for (var i = 0; i < courseList.length; i++) {
                var option = document.createElement('option');
                option.value = courseList[i].value;
                option.textContent = courseList[i].text;
                courseSelect.appendChild(option);
            }
        }
    }

    function checkPasswordStrength() {
        var password = document.getElementById('password').value;
        var strengthBar = document.getElementById('passwordStrength');
        
        if (password.length === 0) {
            strengthBar.className = 'password-strength';
            return;
        }
        
        var strength = 0;
        if (password.length >= 6) strength++;
        if (password.length >= 10) strength++;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
        if (/\d/.test(password)) strength++;
        if (/[^a-zA-Z0-9]/.test(password)) strength++;
        
        if (strength <= 2) {
            strengthBar.className = 'password-strength weak';
        } else if (strength <= 3) {
            strengthBar.className = 'password-strength medium';
        } else {
            strengthBar.className = 'password-strength strong';
        }
    }

    function checkPasswordMatch() {
        var password = document.getElementById('password').value;
        var confirm = document.getElementById('confirm_password').value;
        var matchDiv = document.getElementById('passwordMatch');
        var submitBtn = document.getElementById('submitBtn');
        
        if (confirm.length === 0) {
            matchDiv.textContent = '';
            submitBtn.disabled = false;
            return;
        }
        
        if (password === confirm) {
            matchDiv.textContent = '✅ Passwords match!';
            matchDiv.style.color = '#2e7d32';
            submitBtn.disabled = false;
        } else {
            matchDiv.textContent = '❌ Passwords do not match';
            matchDiv.style.color = '#c62828';
            submitBtn.disabled = true;
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        var programme = document.getElementById('programme');
        if (programme) {
            programme.addEventListener('change', updateCourses);
            if (programme.value) {
                updateCourses();
            }
        }
    });
    </script>

</body>
</html>