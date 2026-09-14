<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$success = '';
$error = '';

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
// FETCH SECONDARY SCHOOLS
// ============================================
$sec_schools = [];
$ss_query = "SELECT * FROM secondary_schools WHERE student_id = $student_id ORDER BY id";
$ss_result = mysqli_query($conn, $ss_query);
if ($ss_result) {
    while ($row = mysqli_fetch_assoc($ss_result)) {
        $sec_schools[] = $row;
    }
}

// ============================================
// HANDLE FORM SUBMISSION
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    
    $fullname = mysqli_real_escape_string($conn, trim($_POST['fullname']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $dob = mysqli_real_escape_string($conn, $_POST['dob']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $address = mysqli_real_escape_string($conn, trim($_POST['address']));
    $guardian_name = mysqli_real_escape_string($conn, trim($_POST['guardian_name']));
    $guardian_phone = mysqli_real_escape_string($conn, trim($_POST['guardian_phone']));
    $guardian_address = mysqli_real_escape_string($conn, trim($_POST['guardian_address'] ?? ''));
    $marital_status = mysqli_real_escape_string($conn, $_POST['marital_status']);
    $religion = mysqli_real_escape_string($conn, $_POST['religion']);
    $state_of_origin = mysqli_real_escape_string($conn, trim($_POST['state_of_origin']));
    $nationality = mysqli_real_escape_string($conn, trim($_POST['nationality']));
    $place_of_birth = mysqli_real_escape_string($conn, trim($_POST['place_of_birth']));
    $lga = mysqli_real_escape_string($conn, trim($_POST['lga']));
    $permanent_address = mysqli_real_escape_string($conn, trim($_POST['permanent_address']));
    $exam_type = mysqli_real_escape_string($conn, $_POST['exam_type']);
    $sponsorship_type = mysqli_real_escape_string($conn, $_POST['sponsorship_type']);
    $sponsor_name = mysqli_real_escape_string($conn, trim($_POST['sponsor_name']));
    $sponsor_address = mysqli_real_escape_string($conn, trim($_POST['sponsor_address']));
    
    $update_query = "UPDATE students SET 
        fullname = '$fullname',
        phone = '$phone',
        dob = '$dob',
        gender = '$gender',
        address = '$address',
        guardian_name = '$guardian_name',
        guardian_phone = '$guardian_phone',
        guardian_address = '$guardian_address',
        marital_status = '$marital_status',
        religion = '$religion',
        state_of_origin = '$state_of_origin',
        nationality = '$nationality',
        place_of_birth = '$place_of_birth',
        lga = '$lga',
        permanent_address = '$permanent_address',
        exam_type = '$exam_type',
        sponsorship_type = '$sponsorship_type',
        sponsor_name = '$sponsor_name',
        sponsor_address = '$sponsor_address'";
    
    for ($i = 1; $i <= 5; $i++) {
        $subject = mysqli_real_escape_string($conn, trim($_POST['subject'.$i] ?? ''));
        $grade = mysqli_real_escape_string($conn, $_POST['grade'.$i] ?? '');
        $month = mysqli_real_escape_string($conn, trim($_POST['month'.$i] ?? ''));
        $year = mysqli_real_escape_string($conn, trim($_POST['year'.$i] ?? ''));
        $centre = mysqli_real_escape_string($conn, trim($_POST['centre'.$i] ?? ''));
        $cand = mysqli_real_escape_string($conn, trim($_POST['cand'.$i] ?? ''));
        
        $update_query .= ", subject$i = '$subject', grade$i = '$grade'";
        $update_query .= ", month$i = '$month', year$i = '$year'";
        $update_query .= ", centre$i = '$centre', cand$i = '$cand'";
    }
    
    $update_query .= ", status = 'pending' WHERE id = $student_id";
    
    if (mysqli_query($conn, $update_query)) {
        
        mysqli_query($conn, "DELETE FROM secondary_schools WHERE student_id = $student_id");
        if (isset($_POST['sec_school_name']) && is_array($_POST['sec_school_name'])) {
            for ($i = 0; $i < count($_POST['sec_school_name']); $i++) {
                $sec_name = mysqli_real_escape_string($conn, trim($_POST['sec_school_name'][$i] ?? ''));
                $sec_address = mysqli_real_escape_string($conn, trim($_POST['sec_school_address'][$i] ?? ''));
                $sec_from = mysqli_real_escape_string($conn, trim($_POST['sec_from'][$i] ?? ''));
                $sec_to = mysqli_real_escape_string($conn, trim($_POST['sec_to'][$i] ?? ''));
                
                if (!empty($sec_name)) {
                    $insert = "INSERT INTO secondary_schools (student_id, school_name, school_address, from_year, to_year) 
                               VALUES ($student_id, '$sec_name', '$sec_address', '$sec_from', '$sec_to')";
                    mysqli_query($conn, $insert);
                }
            }
        }
        
        $success = "✅ Application form saved successfully!";
        
        $query = "SELECT * FROM students WHERE id = $student_id";
        $result = mysqli_query($conn, $query);
        $student = mysqli_fetch_assoc($result);
        
        $sec_schools = [];
        $ss_result = mysqli_query($conn, "SELECT * FROM secondary_schools WHERE student_id = $student_id ORDER BY id");
        while ($row = mysqli_fetch_assoc($ss_result)) $sec_schools[] = $row;
    } else {
        $error = "❌ Error: " . mysqli_error($conn);
    }
}

$name_parts = explode(' ', $student['fullname'] ?? '');
$surname = $name_parts[0] ?? '';
$firstname = $name_parts[1] ?? '';
$middlename = isset($name_parts[2]) ? implode(' ', array_slice($name_parts, 2)) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Application Form - Dala College</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Tahoma,sans-serif; background:#f0f4f8; padding:20px; }
        
        .topbar { background:#0d2818; padding:15px 30px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; border-radius:12px; margin-bottom:20px; max-width:1000px; margin-left:auto; margin-right:auto; }
        .topbar .logo-title { color:#ffd54f; font-size:1.3rem; font-weight:800; }
        .topbar .logo-title span { color:#a5d6a7; }
        .topbar .logo-sub { display:block; font-size:0.6rem; color:#c8e6c9; }
        .topbar nav a { color:#c8e6c9; text-decoration:none; padding:8px 18px; border-radius:25px; transition:all 0.3s ease; }
        .topbar nav a:hover { background:#2e7d32; }
        .topbar nav .active { background:#ffd54f; color:#0d2818 !important; font-weight:700; }
        
        .container { 
            max-width:1000px; 
            margin:0 auto; 
            background:white; 
            padding:40px; 
            border-radius:16px; 
            box-shadow:0 4px 30px rgba(0,0,0,0.1); 
            position: relative;
            overflow: hidden;
        }
        
        .container::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 500px;
            height: 500px;
            background-image: url('images/dala-logo.png');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            opacity: 0.06;
            z-index: 0;
            pointer-events: none;
        }
        
        .container > * { position: relative; z-index: 1; }
        
        /* ============================================ */
        /* HEADER */
        /* ============================================ */
        .form-header { 
            border-bottom: 3px double #2e7d32; 
            padding-bottom: 15px; 
            margin-bottom: 25px; 
            text-align: center;
        }
        
        .form-header .header-top {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 25px;
            flex-wrap: nowrap;
        }
        
        .form-header .header-logo {
            width: 120px;
            height: 120px;
            object-fit: contain;
            flex-shrink: 0;
        }
        
        .form-header .header-text {
            text-align: left;
            flex: 1;
        }
        
        .form-header h1 { 
            color: #0d2818; 
            font-size: 1.7rem; 
            margin-bottom: 4px; 
            line-height: 1.2;
            font-weight: 900;
            letter-spacing: 1px;
        }
        
        .form-header .sub { 
            color: #2e7d32; 
            font-size: 1rem; 
            margin-bottom: 4px; 
            font-weight: 600;
        }
        
        .form-header .accreditation { 
            color: #6a8f6a; 
            font-size: 0.8rem; 
            margin-bottom: 6px; 
        }
        
        .form-header .contact-line {
            display: flex;
            justify-content: flex-start;
            gap: 10px;
            flex-wrap: wrap;
            font-size: 0.75rem;
            color: #0d2818;
            font-weight: 600;
            margin: 5px 0;
        }
        
        .form-header .contact-line span {
            background: #f0f5f0;
            padding: 3px 10px;
            border-radius: 12px;
        }
        
        .form-header .session { 
            background: #ffd54f; 
            color: #0d2818; 
            padding: 5px 20px; 
            border-radius: 20px; 
            display: inline-block; 
            font-weight: 700; 
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        .section-title { background:#0d2818; color:white; padding:10px 20px; border-radius:8px; margin:25px 0 15px 0; font-size:1rem; font-weight:600; }
        .section-title span { color:#ffd54f; }
        
        .course-choice { background:#e8f5e9; padding:12px 20px; border-radius:8px; margin-bottom:20px; border-left:4px solid #2e7d32; }
        .course-choice strong { color:#0d2818; }
        .course-choice span { color:#2e7d32; font-weight:600; }
        
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:15px; }
        .form-row.three-col { grid-template-columns:1fr 1fr 1fr; }
        
        .form-field { display:flex; flex-direction:column; }
        .form-field label { font-weight:600; color:#0d2818; font-size:0.85rem; margin-bottom:3px; }
        .form-field input, .form-field select { padding:8px 12px; border:1px solid #dce8dc; border-radius:6px; font-size:0.9rem; }
        .form-field input:focus, .form-field select:focus { border-color:#2e7d32; outline:none; }
        
        .photo-section { display:flex; align-items:center; gap:20px; padding:15px; background:#f5faf5; border-radius:12px; margin-bottom:20px; flex-wrap:wrap; }
        .photo-section .photo-box { width:100px; height:120px; border:3px solid #2e7d32; border-radius:10px; overflow:hidden; background:#e8f5e9; display:flex; align-items:center; justify-content:center; }
        .photo-section .photo-box span { font-size:3rem; }
        .photo-section .photo-box img { width:100%; height:100%; object-fit:cover; }
        
        .badge-status { display:inline-block; padding:4px 14px; border-radius:20px; font-size:0.8rem; font-weight:600; }
        .badge-pending { background:#fff3e0; color:#e65100; }
        .badge-approved, .badge-active { background:#e8f5e9; color:#2e7d32; }
        .badge-rejected { background:#ffebee; color:#c62828; }
        
        .table-wrap { overflow-x:auto; margin:10px 0 20px 0; }
        .table-wrap table { width:100%; border-collapse:collapse; font-size:0.85rem; }
        .table-wrap table th { background:#0d2818; color:white; padding:8px 12px; text-align:left; }
        .table-wrap table td { padding:6px 12px; border:1px solid #dce8dc; }
        .table-wrap table td input, .table-wrap table td select { width:100%; padding:5px 8px; border:1px solid #dce8dc; border-radius:4px; font-size:0.85rem; }
        
        .signature-area { margin-top:30px; padding-top:20px; border-top:2px dashed #dce8dc; display:grid; grid-template-columns:1fr 1fr; gap:30px; }
        .signature-area .sign-box { text-align:center; }
        .signature-area .sign-box .line { border-bottom:2px solid #0d2818; width:200px; margin:30px auto 5px; }
        
        .alert-success { background:#e8f5e9; color:#2e7d32; padding:12px 18px; border-radius:8px; margin-bottom:15px; border-left:4px solid #2e7d32; }
        .alert-error { background:#ffebee; color:#c62828; padding:12px 18px; border-radius:8px; margin-bottom:15px; border-left:4px solid #c62828; }
        
        .action-buttons { display:flex; gap:15px; justify-content:center; flex-wrap:wrap; margin-top:25px; }
        .btn-save { padding:12px 30px; background:#2e7d32; color:white; border:none; border-radius:8px; font-size:1rem; font-weight:700; cursor:pointer; }
        .btn-save:hover { background:#1b5e20; }
        .btn-print { padding:12px 30px; background:#ffd54f; color:#0d2818; border:none; border-radius:8px; font-size:1rem; font-weight:700; cursor:pointer; }
        .btn-back { padding:12px 30px; border:2px solid #2e7d32; color:#2e7d32; border-radius:8px; text-decoration:none; font-weight:700; }
        .btn-back:hover { background:#2e7d32; color:white; }
        
        /* ============================================ */
        /* PRINT STYLES - A4 PORTRAIT */
        /* ============================================ */
        @media print {
            .topbar, .action-buttons, footer, .no-print, 
            .btn-save, .btn-print, .btn-back, 
            .alert-success, .alert-error { 
                display: none !important; 
            }
            
            body { 
                background: white; 
                padding: 0; 
                margin: 0; 
                font-family: 'Times New Roman', serif;
                font-size: 11px;
                color: #000;
            }
            
            .container { 
                box-shadow: none; 
                padding: 0; 
                border-radius: 0; 
                max-width: 100%;
                position: relative;
                overflow: hidden;
            }
            
            .container::before {
                opacity: 0.08 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            /* HEADER A PRINT */
            .form-header { 
                padding-bottom: 10px; 
                margin-bottom: 15px; 
                border-bottom: 3px double #000;
            }
            
            .form-header .header-top {
                gap: 20px;
                flex-wrap: nowrap;
                align-items: center;
            }
            
            .form-header .header-logo {
                width: 90px;
                height: 90px;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .form-header .header-text {
                text-align: left;
                flex: 1;
            }
            
            .form-header h1 { 
                font-size: 1.3rem; 
                font-weight: 900; 
                letter-spacing: 1px; 
                margin-bottom: 3px;
                line-height: 1.2;
                color: #000;
            }
            
            .form-header .sub { 
                font-size: 0.85rem; 
                color: #2e7d32; 
                font-weight: 600;
                margin-bottom: 3px;
            }
            
            .form-header .accreditation { 
                font-size: 0.7rem; 
                color: #c62828; 
                font-weight: 700;
                margin-bottom: 4px;
            }
            
            .form-header .contact-line {
                font-size: 0.7rem;
                gap: 8px;
                margin: 4px 0;
                justify-content: flex-start;
            }
            
            .form-header .contact-line span {
                background: transparent;
                padding: 0;
                border: none;
            }
            
            .form-header .session { 
                padding: 3px 15px; 
                font-size: 0.8rem;
                border: 1.5px solid #000;
                background: #ffd54f !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                margin-top: 4px;
            }
            
            .course-choice { 
                background: #f0f0f0 !important; 
                padding: 6px 12px; 
                margin-bottom: 10px;
                border-left: 3px solid #000;
                font-size: 0.85rem;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .course-choice strong { color: #000; }
            .course-choice span { color: #000; font-weight: 700; }
            
            .section-title { 
                background: #000 !important; 
                color: #fff !important; 
                padding: 4px 12px; 
                margin: 10px 0 8px 0; 
                font-size: 0.8rem;
                border-radius: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .section-title span { color: #ffd54f !important; }
            
            .form-row { gap: 8px; margin-bottom: 5px; }
            .form-row.three-col { grid-template-columns: 1fr 1fr 1fr; }
            
            .form-field label { font-size: 0.7rem; font-weight: 700; color: #000; margin-bottom: 1px; }
            
            .form-field input, 
            .form-field select,
            .table-wrap table td input,
            .table-wrap table td select {
                border: none !important;
                border-bottom: 1px solid #000 !important;
                background: transparent !important;
                padding: 1px 0 !important;
                font-weight: 700 !important;
                color: #000 !important;
                font-size: 0.8rem !important;
                -webkit-appearance: none;
                -moz-appearance: none;
                appearance: none;
                width: 100%;
                border-radius: 0;
                font-family: 'Times New Roman', serif;
            }
            
            .photo-section { 
                padding: 8px; 
                margin-bottom: 10px;
                background: #f5f5f5 !important;
                border: 1px solid #000;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .photo-section .photo-box { width: 70px; height: 85px; border: 1.5px solid #000; }
            .photo-section p { font-size: 0.8rem; margin: 2px 0; }
            
            .table-wrap { margin: 6px 0 10px 0; overflow: visible !important; }
            
            .table-wrap table { 
                width: 100%;
                border-collapse: collapse;
                font-size: 0.8rem; 
                border: 1.5px solid #000 !important;
            }
            
            .table-wrap table th { 
                background: #000 !important; 
                color: #fff !important; 
                padding: 4px 6px; 
                font-size: 0.7rem;
                border: 1px solid #000 !important;
                text-align: left;
                font-weight: 700;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .table-wrap table td { 
                padding: 3px 6px; 
                font-size: 0.8rem;
                border: 1px solid #000 !important;
                vertical-align: middle;
                font-weight: 700;
            }
            
            .signature-area { 
                margin-top: 20px; 
                padding-top: 10px;
                border-top: 1.5px solid #000;
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 30px;
            }
            .signature-area .sign-box .line { 
                width: 150px; 
                margin: 20px auto 3px; 
                border-bottom: 1.5px solid #000;
            }
            .signature-area .sign-box p { font-size: 0.75rem; color: #000; font-weight: 700; }
            
            .section-title + div {
                font-size: 0.8rem !important;
                padding: 8px 12px !important;
                margin-bottom: 10px !important;
            }
            
            .section-title, .table-wrap, .form-row, .photo-section, .signature-area {
                page-break-inside: avoid;
            }
            
            @page { size: A4 portrait; margin: 12mm 10mm; }
        }
        
        @media (max-width:768px) {
            .form-row, .form-row.three-col { grid-template-columns:1fr; }
            .container { padding:20px; }
            .signature-area { grid-template-columns:1fr; }
            .form-header .header-top { flex-direction: column; text-align: center; }
            .form-header .header-text { text-align: center; }
        }
    </style>
</head>
<body>
    <div class="topbar no-print">
        <div>
            <span class="logo-title">DALA <span>COLLEGE</span></span>
            <span class="logo-sub">of Education, Kano</span>
        </div>
        <nav>
            <a href="student_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="application_form.php" class="active"><i class="fas fa-file-alt"></i> Application Form</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="container">
        <!-- HEADER -->
        <div class="form-header">
            <div class="header-top">
                <img src="images/dala-logo.png" alt="Dala College Logo" class="header-logo" onerror="this.style.display='none'">
                <div class="header-text">
                    <h1>DALA COLLEGE OF EDUCATION, KANO</h1>
                    <div class="sub">Knowledge, Excellence &amp; Success</div>
                    <div class="accreditation">✅ Accredited by National Commission for Colleges of Education (NCCE), Abuja</div>
                    <div class="contact-line">
                        <span>🌐 www.dalacollege.edu.ng</span>
                        <span>✉️ dalacollegekano@gmail.com</span>
                    </div>
                    <div class="session">2025/2026 ACADEMIC SESSION</div>
                </div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert-success no-print"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-error no-print"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="course-choice">
            <strong>COURSE CHOICE:</strong> <span><?php echo htmlspecialchars($student['course'] ?? 'Not Selected'); ?></span>
            &nbsp;|&nbsp; <strong>Programme:</strong> <span><?php echo htmlspecialchars($student['programme'] ?? 'NCE'); ?></span>
            &nbsp;|&nbsp; <strong>Level:</strong> <span><?php echo htmlspecialchars($student['level'] ?? 'NCE I'); ?></span>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="update_profile" value="1">

            <!-- SECTION A: PERSONAL DATA -->
            <div class="section-title"><span>SECTION "A"</span>: PERSONAL DATA</div>

            <div class="photo-section">
                <div class="photo-box">
                    <?php if (!empty($student['photo']) && file_exists('uploads/students/'.$student['photo'])): ?>
                        <img src="uploads/students/<?php echo $student['photo']; ?>" alt="Photo">
                    <?php else: ?>
                        <span>📷</span>
                    <?php endif; ?>
                </div>
                <div>
                    <p><strong>Admission No.:</strong> <?php echo htmlspecialchars($student['reg_no'] ?? $student['student_id'] ?? 'Pending'); ?></p>
                    <p><strong>Username:</strong> <?php echo htmlspecialchars($student['username'] ?? ''); ?></p>
                    <p style="font-size:0.85rem; color:#6a8f6a;">Status: 
                        <span class="badge-status <?php echo $student['status'] ?? 'pending'; ?>">
                            <?php echo ucfirst($student['status'] ?? 'Pending'); ?>
                        </span>
                    </p>
                </div>
            </div>

            <div class="form-row three-col">
                <div class="form-field">
                    <label>Surname <span style="color:red;">*</span></label>
                    <input type="text" name="surname" value="<?php echo htmlspecialchars($surname); ?>" required>
                </div>
                <div class="form-field">
                    <label>First Name <span style="color:red;">*</span></label>
                    <input type="text" name="firstname" value="<?php echo htmlspecialchars($firstname); ?>" required>
                </div>
                <div class="form-field">
                    <label>Middle Name</label>
                    <input type="text" name="middlename" value="<?php echo htmlspecialchars($middlename); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-field">
                    <label>Full Name (Combined) <span style="color:red;">*</span></label>
                    <input type="text" name="fullname" value="<?php echo htmlspecialchars($student['fullname'] ?? ''); ?>" required>
                </div>
                <div class="form-field">
                    <label>Phone Number <span style="color:red;">*</span></label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>" required>
                </div>
            </div>

            <div class="form-row three-col">
                <div class="form-field">
                    <label>Date of Birth <span style="color:red;">*</span></label>
                    <input type="date" name="dob" value="<?php echo htmlspecialchars($student['dob'] ?? ''); ?>" required>
                </div>
                <div class="form-field">
                    <label>Gender <span style="color:red;">*</span></label>
                    <select name="gender" required>
                        <option value="">Select</option>
                        <option value="Male" <?php echo ($student['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($student['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>Marital Status</label>
                    <select name="marital_status">
                        <option value="Single" <?php echo ($student['marital_status'] ?? 'Single') == 'Single' ? 'selected' : ''; ?>>Single</option>
                        <option value="Married" <?php echo ($student['marital_status'] ?? '') == 'Married' ? 'selected' : ''; ?>>Married</option>
                        <option value="Divorced" <?php echo ($student['marital_status'] ?? '') == 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                        <option value="Widowed" <?php echo ($student['marital_status'] ?? '') == 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                    </select>
                </div>
            </div>

            <div class="form-row three-col">
                <div class="form-field">
                    <label>Religion</label>
                    <select name="religion">
                        <option value="Islam" <?php echo ($student['religion'] ?? 'Islam') == 'Islam' ? 'selected' : ''; ?>>Islam</option>
                        <option value="Christianity" <?php echo ($student['religion'] ?? '') == 'Christianity' ? 'selected' : ''; ?>>Christianity</option>
                        <option value="Other" <?php echo ($student['religion'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>Nationality</label>
                    <input type="text" name="nationality" value="<?php echo htmlspecialchars($student['nationality'] ?? 'Nigerian'); ?>">
                </div>
                <div class="form-field">
                    <label>State of Origin</label>
                    <input type="text" name="state_of_origin" value="<?php echo htmlspecialchars($student['state_of_origin'] ?? 'Kano'); ?>">
                </div>
            </div>

            <div class="form-row three-col">
                <div class="form-field">
                    <label>Place of Birth</label>
                    <input type="text" name="place_of_birth" value="<?php echo htmlspecialchars($student['place_of_birth'] ?? 'Kano'); ?>">
                </div>
                <div class="form-field">
                    <label>LGA</label>
                    <input type="text" name="lga" value="<?php echo htmlspecialchars($student['lga'] ?? 'Dala'); ?>">
                </div>
                <div class="form-field">
                    <label>Guardian's Phone <span style="color:red;">*</span></label>
                    <input type="tel" name="guardian_phone" value="<?php echo htmlspecialchars($student['guardian_phone'] ?? ''); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-field">
                    <label>Contact Address <span style="color:red;">*</span></label>
                    <input type="text" name="address" value="<?php echo htmlspecialchars($student['address'] ?? ''); ?>" required>
                </div>
                <div class="form-field">
                    <label>Permanent Home Address</label>
                    <input type="text" name="permanent_address" value="<?php echo htmlspecialchars($student['permanent_address'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-field">
                    <label>Name of Guardian <span style="color:red;">*</span></label>
                    <input type="text" name="guardian_name" value="<?php echo htmlspecialchars($student['guardian_name'] ?? ''); ?>" required>
                </div>
                <div class="form-field">
                    <label>Guardian's Address</label>
                    <input type="text" name="guardian_address" value="<?php echo htmlspecialchars($student['guardian_address'] ?? ''); ?>">
                </div>
            </div>

            <!-- SECONDARY SCHOOLS -->
            <div class="section-title"><span>SECONDARY</span> SCHOOLS / COLLEGES ATTENDED WITH DATES</div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width:40px;">S/N</th>
                            <th>Name of Institution</th>
                            <th>Address of Institution</th>
                            <th style="width:80px;">From</th>
                            <th style="width:80px;">To</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_sec = max(3, count($sec_schools));
                        for ($i = 0; $i < $total_sec; $i++):
                            $sec = $sec_schools[$i] ?? null;
                        ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><input type="text" name="sec_school_name[]" value="<?php echo htmlspecialchars($sec['school_name'] ?? ''); ?>" placeholder="e.g. Govt Sec School Dala"></td>
                            <td><input type="text" name="sec_school_address[]" value="<?php echo htmlspecialchars($sec['school_address'] ?? ''); ?>" placeholder="Address"></td>
                            <td><input type="text" name="sec_from[]" value="<?php echo htmlspecialchars($sec['from_year'] ?? ''); ?>" placeholder="2014" style="width:70px;"></td>
                            <td><input type="text" name="sec_to[]" value="<?php echo htmlspecialchars($sec['to_year'] ?? ''); ?>" placeholder="2020" style="width:70px;"></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <!-- SECTION B: O'LEVEL -->
            <div class="section-title"><span>SECTION "B"</span>: O'LEVEL QUALIFICATION</div>

            <div style="margin-bottom:15px;">
                <label style="font-weight:600;">Type of Examination:</label>
                <select name="exam_type" style="padding:5px 10px; border:1px solid #dce8dc; border-radius:4px;">
                    <option value="WAEC" <?php echo ($student['exam_type'] ?? '') == 'WAEC' ? 'selected' : ''; ?>>WAEC</option>
                    <option value="NECO" <?php echo ($student['exam_type'] ?? '') == 'NECO' ? 'selected' : ''; ?>>NECO</option>
                    <option value="NABTEB" <?php echo ($student['exam_type'] ?? '') == 'NABTEB' ? 'selected' : ''; ?>>NABTEB</option>
                    <option value="NBAIS" <?php echo ($student['exam_type'] ?? 'NBAIS') == 'NBAIS' ? 'selected' : ''; ?>>NBAIS</option>
                    <option value="GCE" <?php echo ($student['exam_type'] ?? '') == 'GCE' ? 'selected' : ''; ?>>GCE</option>
                    <option value="JAMB" <?php echo ($student['exam_type'] ?? '') == 'JAMB' ? 'selected' : ''; ?>>JAMB</option>
                </select>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width:40px;">S/N</th>
                            <th>Subject</th>
                            <th style="width:80px;">Month</th>
                            <th style="width:70px;">Year</th>
                            <th>Centre No.</th>
                            <th>Cand. No.</th>
                            <th style="width:80px;">Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <tr>
                            <td><?php echo $i; ?></td>
                            <td><input type="text" name="subject<?php echo $i; ?>" value="<?php echo htmlspecialchars($student['subject'.$i] ?? ''); ?>" placeholder="Subject"></td>
                            <td><input type="text" name="month<?php echo $i; ?>" value="<?php echo htmlspecialchars($student['month'.$i] ?? ''); ?>" placeholder="Month" style="width:70px;"></td>
                            <td><input type="text" name="year<?php echo $i; ?>" value="<?php echo htmlspecialchars($student['year'.$i] ?? ''); ?>" placeholder="Year" style="width:60px;"></td>
                            <td><input type="text" name="centre<?php echo $i; ?>" value="<?php echo htmlspecialchars($student['centre'.$i] ?? ''); ?>" placeholder="Centre No."></td>
                            <td><input type="text" name="cand<?php echo $i; ?>" value="<?php echo htmlspecialchars($student['cand'.$i] ?? ''); ?>" placeholder="Cand. No."></td>
                            <td>
                                <select name="grade<?php echo $i; ?>">
                                    <option value="">--</option>
                                    <?php foreach (['A','B','C','D','E','F'] as $g): ?>
                                        <option value="<?php echo $g; ?>" <?php echo ($student['grade'.$i] ?? '') == $g ? 'selected' : ''; ?>><?php echo $g; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <!-- SECTION C: SPONSOR -->
            <div class="section-title"><span>SECTION "C"</span>: SPONSOR</div>

            <div class="form-row three-col">
                <div class="form-field">
                    <label>Sponsorship Type</label>
                    <select name="sponsorship_type">
                        <option value="Parent/Guardian" <?php echo ($student['sponsorship_type'] ?? '') == 'Parent/Guardian' ? 'selected' : ''; ?>>Parent/Guardian</option>
                        <option value="Self-Sponsored" <?php echo ($student['sponsorship_type'] ?? '') == 'Self-Sponsored' ? 'selected' : ''; ?>>Self-Sponsored</option>
                        <option value="Government Scholarship" <?php echo ($student['sponsorship_type'] ?? '') == 'Government Scholarship' ? 'selected' : ''; ?>>Government Scholarship</option>
                        <option value="Private Scholarship" <?php echo ($student['sponsorship_type'] ?? '') == 'Private Scholarship' ? 'selected' : ''; ?>>Private Scholarship</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>Sponsor's Name</label>
                    <input type="text" name="sponsor_name" value="<?php echo htmlspecialchars($student['sponsor_name'] ?? ''); ?>">
                </div>
                <div class="form-field">
                    <label>Sponsor's Address</label>
                    <input type="text" name="sponsor_address" value="<?php echo htmlspecialchars($student['sponsor_address'] ?? ''); ?>">
                </div>
            </div>

            <!-- SECTION D: DECLARATION -->
            <div class="section-title"><span>SECTION "D"</span>: DECLARATION</div>

            <div style="padding:15px; background:#f5faf5; border-radius:8px; margin-bottom:15px; font-style:italic;">
                I hereby certify that all the information provided on this form are correct to the best of my knowledge and that my application for admission stands disqualified if found otherwise.
            </div>

            <div class="signature-area">
                <div class="sign-box">
                    <p><strong>Student's Signature</strong></p>
                    <div class="line"></div>
                    <p style="color:#6a8f6a; font-size:0.8rem;">Date: <?php echo date('d/m/Y'); ?></p>
                </div>
                <div class="sign-box">
                    <p><strong>Parent/Guardian's Signature</strong></p>
                    <div class="line"></div>
                    <p style="color:#6a8f6a; font-size:0.8rem;">Date: <?php echo date('d/m/Y'); ?></p>
                </div>
            </div>

            <div class="action-buttons no-print">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Application</button>
                <button type="button" class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print Form</button>
                <a href="student_dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
            </div>
        </form>
    </div>

    <footer class="no-print" style="background:#0d2818; color:#a5d6a7; text-align:center; padding:20px; border-radius:12px; max-width:1000px; margin:20px auto 0;">
        <p>© <?php echo date('Y'); ?> Dala College of Education, Kano. All Rights Reserved.</p>
    </footer>

</body>
</html>