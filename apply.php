<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'connect.php';

$success = '';
$error = '';
$admission_number = '';
$show_success = false;

// ============================================
// GET DEPARTMENT CODE (FROM COURSE)
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
    return $dept_codes[$course] ?? 'GEN';
}

// ============================================
// GET LEVEL BY PROGRAMME
// ============================================
function getLevelByProgramme($programme) {
    if ($programme == 'DEGREE') {
        return '400 Level';
    }
    return 'NCE I';
}

// ============================================
// GENERATE ADMISSION NUMBER
// DLCOE/NCE/26A[School#]/[DEPT][Dept#]
// ============================================
function generateAdmissionNumber($programme, $branch_code, $course) {
    global $conn;
    $year = date('y');
    
    // 1. Branch letter
    $branch_letter = 'X';
    if ($branch_code == 'SHINGE') $branch_letter = 'A';
    elseif ($branch_code == 'SABUWA') $branch_letter = 'B';
    elseif ($branch_code == 'TUDUN') $branch_letter = 'C';
    
    // 2. Department code
    $dept_code = getDepartmentCode($course);
    
    // 3. Total students in school (first number)
    $total = 1;
    $total_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM students");
    if ($total_query) {
        $row = mysqli_fetch_assoc($total_query);
        $total = $row['total'] + 1;
    }
    $school_number = str_pad($total, 3, '0', STR_PAD_LEFT);
    
    // 4. Total students in department (last number)
    $course_escaped = mysqli_real_escape_string($conn, $course);
    $dept_total = 1;
    $dept_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM students 
                                       WHERE course = '$course_escaped' 
                                       OR combination = '$course_escaped'");
    if ($dept_query) {
        $row = mysqli_fetch_assoc($dept_query);
        $dept_total = $row['total'] + 1;
    }
    $dept_number = str_pad($dept_total, 3, '0', STR_PAD_LEFT);
    
    // 5. Prefix
    $prefix = 'DLCOE/NCE';
    if ($programme == 'DEGREE') $prefix = 'DLCOE/DEG';
    elseif ($programme == 'ENTREPRENEURSHIP') $prefix = 'DLCOE/ENT';
    
    // 6. Final
    return $prefix . "/{$year}{$branch_letter}{$school_number}/{$dept_code}{$dept_number}";
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
    
    $errors = [];
    
    if (empty($fullname)) $errors[] = 'Full name is required.';
    if (empty($phone)) $errors[] = 'Phone number is required.';
    if (empty($programme)) $errors[] = 'Programme is required.';
    if (empty($course)) $errors[] = 'Course is required.';
    if (empty($branch)) $errors[] = 'Please select a branch.';
    if (empty($password)) $errors[] = 'Password is required.';
    if (empty($confirm_password)) $errors[] = 'Please confirm your password.';
    
    if (!empty($phone) && !preg_match('/^[0-9]{10,15}$/', $phone)) {
        $errors[] = 'Please enter a valid phone number (10-15 digits).';
    }
    if (!empty($password) && strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if (!empty($password) && !empty($confirm_password) && $password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    
    if (!empty($phone)) {
        $check_phone = mysqli_query($conn, "SELECT id FROM students WHERE phone = '$phone'");
        if (mysqli_num_rows($check_phone) > 0) {
            $errors[] = 'This phone number is already registered. Please contact support.';
        }
    }
    
    if (empty($errors)) {
        $admission_number = generateAdmissionNumber($programme, $branch, $course);
        $username = strtolower(str_replace(' ', '_', $fullname)) . rand(10, 99);
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $student_level = getLevelByProgramme($programme);
        
        $insert = "INSERT INTO students (
            student_id, reg_no, username, password, fullname, phone, 
            programme, course, combination, dob, address, gender, branch_code, status, level, created_at
        ) VALUES (
            '$admission_number', '$admission_number', '$username', '$hashed_password', '$fullname', '$phone',
            '$programme', '$course', '$course', '$dob', '$address', '$gender', '$branch', 'pending', '$student_level', NOW()
        )";
        
        if (mysqli_query($conn, $insert)) {
            $new_id = mysqli_insert_id($conn);
            
            $app_insert = "INSERT INTO applications (
                student_id, fullname, phone, course_applied, programme, branch_code, application_date, status
            ) VALUES (
                '$new_id', '$fullname', '$phone', '$course', '$programme', '$branch', CURDATE(), 'pending'
            )";
            mysqli_query($conn, $app_insert);
            
            $_SESSION['user_id'] = $new_id;
            $_SESSION['fullname'] = $fullname;
            $_SESSION['phone'] = $phone;
            $_SESSION['role'] = 'student';
            $_SESSION['student_id'] = $admission_number;
            $_SESSION['branch_code'] = $branch;
            $_SESSION['course'] = $course;
            $_SESSION['level'] = $student_level;
            
            $success = '✅ Application submitted successfully!';
            $show_success = true;
            
            echo '<meta http-equiv="refresh" content="3;url=student_dashboard.php">';
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
        }
        .success-box .highlight strong {
            color: #2e7d32;
        }
        .success-box .redirect-timer {
            color: #6a8f6a;
            font-size: 0.9rem;
            margin-top: 10px;
        }
        .success-box .btn-dashboard {
            display: inline-block;
            padding: 10px 30px;
            background: #2e7d32;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 700;
            margin-top: 15px;
            transition: all 0.3s ease;
        }
        .success-box .btn-dashboard:hover {
            background: #1b5e20;
            transform: translateY(-2px);
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
            <strong>Application:</strong> Fill the form below to submit your application.
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
                <p>Your application has been received and is pending review.</p>
                
                <div class="highlight">
                    <p><strong>Admission Number:</strong> <?php echo htmlspecialchars($_SESSION['student_id'] ?? $admission_number); ?></p>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($_SESSION['fullname'] ?? $fullname); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($_SESSION['phone'] ?? $phone); ?></p>
                    <p><strong>Branch:</strong> <?php echo htmlspecialchars($_SESSION['branch_code'] ?? $branch); ?></p>
                    <p><strong>Level:</strong> <?php echo htmlspecialchars($_SESSION['level'] ?? $student_level); ?></p>
                </div>
                
                <p style="color:#6a8f6a; font-size:0.9rem;">
                    ⏳ Please wait for admin review. You will be notified once your application is processed.
                </p>
                
                <div class="redirect-timer">
                    <i class="fas fa-spinner fa-spin"></i> 
                    Redirecting to dashboard in <span id="countdown">3</span> seconds...
                </div>
                
                <a href="student_dashboard.php" class="btn-dashboard">
                    <i class="fas fa-tachometer-alt"></i> Go to Dashboard Now
                </a>
            </div>
            
            <script>
            var seconds = 3;
            var countdown = setInterval(function() {
                seconds--;
                document.getElementById('countdown').textContent = seconds;
                if (seconds <= 0) {
                    clearInterval(countdown);
                    window.location.href = 'student_dashboard.php';
                }
            }, 1000);
            </script>
        <?php endif; ?>

        <?php if (!$show_success): ?>
        <form method="POST" action="" id="applyForm">
            
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
                    <div class="hint">This will be your login username</div>
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
                <div class="hint">Choose your preferred study center branch</div>
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
                    <div class="hint">Password must be at least 6 characters</div>
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
            { value: 'HAU/ENG', text: 'HAU/ENG - Hausa / English' },
            { value: 'CSC/ISC', text: 'CSC/ISC - Computer Science / Islamic Studies' },
            { value: 'ENG/SOS', text: 'ENG/SOS - English / Social Studies' },
            { value: 'CSC/BIO', text: 'CSC/BIO - Computer Science / Biology' },
            { value: 'CSC/PHY', text: 'CSC/PHY - Computer Science / Physics' },
            { value: 'ENG/ECO', text: 'ENG/ECO - English / Economics' }
        ],
        DEGREE: [
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
        ],
        ENTREPRENEURSHIP: [
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
        
        if (!selectedProgramme) {
            var msgOption = document.createElement('option');
            msgOption.value = '';
            msgOption.textContent = '-- Select Programme First --';
            msgOption.disabled = true;
            msgOption.selected = true;
            courseSelect.appendChild(msgOption);
            return;
        }
        
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