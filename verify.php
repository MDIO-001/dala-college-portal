<?php
session_start();
require_once 'connect.php';
require_once 'result_functions.php';

$reg_no = $_GET['reg_no'] ?? '';

if (empty($reg_no)) {
    die("No registration number provided.");
}

// ============================================
// NEMO ƊALIBIN
// ============================================
$stmt = $pdo->prepare("SELECT * FROM students WHERE reg_no = ? OR student_id = ? LIMIT 1");
$stmt->execute([$reg_no, $reg_no]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student not found: " . htmlspecialchars($reg_no));
}

// ============================================
// SAMO CGPA DA SAURAN BAYANAI
// ============================================
$cgpa = getStudentCGPA($conn, $student['id']);
$class = getGradeClassification($cgpa);
$grad_year = getGraduationYear($conn, $student['id']);

// ============================================
// NEMO HOTON ƊALIBIN (HANYA DA YAWAN)
// ============================================
$photo_path = 'uploads/students/default.png';  // Default

// 1. Duba idan an adana sunan hoto a $student['photo']
if (!empty($student['photo'])) {
    $possible_paths = [
        'uploads/students/' . $student['photo'],
        'uploads/students/' . basename($student['photo']),
        $student['photo'],  // Idan cikakken hanya ce
    ];
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            $photo_path = $path;
            break;
        }
    }
}

// 2. Idan ba a samu ba, gwada da reg_no
if ($photo_path == 'uploads/students/default.png' && !empty($student['reg_no'])) {
    $safe_reg = str_replace(['/', '\\', ' '], '_', $student['reg_no']);
    $possible_extensions = ['jpg', 'jpeg', 'png', 'JPG', 'JPEG', 'PNG'];
    
    foreach ($possible_extensions as $ext) {
        $paths = [
            'uploads/students/' . $student['reg_no'] . '.' . $ext,
            'uploads/students/' . $safe_reg . '.' . $ext,
        ];
        foreach ($paths as $path) {
            if (file_exists($path)) {
                $photo_path = $path;
                break 2;
            }
        }
    }
}

// 3. Idan ba a samu ba, gwada da student_id
if ($photo_path == 'uploads/students/default.png' && !empty($student['student_id'])) {
    $safe_id = str_replace(['/', '\\', ' '], '_', $student['student_id']);
    foreach (['jpg', 'jpeg', 'png'] as $ext) {
        $paths = [
            'uploads/students/' . $student['student_id'] . '.' . $ext,
            'uploads/students/' . $safe_id . '.' . $ext,
        ];
        foreach ($paths as $path) {
            if (file_exists($path)) {
                $photo_path = $path;
                break 2;
            }
        }
    }
}

// 4. Idan ba a samu ba, gwada da id
if ($photo_path == 'uploads/students/default.png' && !empty($student['id'])) {
    foreach (['jpg', 'jpeg', 'png'] as $ext) {
        $paths = [
            'uploads/students/' . $student['id'] . '.' . $ext,
            'uploads/students/student_' . $student['id'] . '.' . $ext,
        ];
        foreach ($paths as $path) {
            if (file_exists($path)) {
                $photo_path = $path;
                break 2;
            }
        }
    }
}

// 5. Idan har yanzu babu, yi amfani da placeholder
if ($photo_path == 'uploads/students/default.png' && !file_exists('uploads/students/default.png')) {
    $photo_path = 'https://via.placeholder.com/150x180/cccccc/333333?text=PHOTO';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Result - <?php echo htmlspecialchars($student['fullname']); ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #f0f4f8, #dce8dc);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.15);
            max-width: 700px;
            width: 100%;
            border-top: 8px solid #2e7d32;
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 3px double #0d2818;
            margin-bottom: 25px;
        }
        .logo {
            font-size: 2rem;
            font-weight: 900;
            color: #0d2818;
        }
        .logo span { color: #2e7d32; }
        .subtitle {
            color: #6a8f6a;
            font-size: 0.85rem;
            margin-top: 5px;
        }
        .verify-badge {
            display: inline-block;
            background: #2e7d32;
            color: white;
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.85rem;
            margin-top: 15px;
        }
        .verify-badge::before {
            content: '✅ ';
        }
        
        /* ============================================ */
        /* STUDENT INFO + PHOTO */
        /* ============================================ */
        .student-wrapper {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 25px;
            margin-bottom: 20px;
            background: #f8faf8;
            padding: 20px;
            border-radius: 10px;
            border-left: 5px solid #2e7d32;
            align-items: start;
        }
        .student-photo {
            width: 150px;
            height: 180px;
            border: 3px solid #0d2818;
            border-radius: 8px;
            overflow: hidden;
            background: #f0f0f0;
            box-shadow: 4px 4px 10px rgba(0,0,0,0.15);
        }
        .student-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .student-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .info-row {
            display: grid;
            grid-template-columns: 150px 1fr;
            padding: 6px 0;
            border-bottom: 1px dotted #ccc;
        }
        .info-row:last-child { border-bottom: none; }
        .info-row .label {
            font-weight: 800;
            color: #0d2818;
            font-size: 0.9rem;
        }
        .info-row .value {
            font-weight: 600;
            color: #1a2e1a;
            font-size: 0.9rem;
        }
        
        /* ============================================ */
        /* RESULT BOX */
        /* ============================================ */
        .result-box {
            background: #e8f5e9;
            padding: 25px;
            border-radius: 10px;
            border: 2px solid #2e7d32;
            text-align: center;
            margin-bottom: 20px;
        }
        .result-box .big {
            font-size: 3rem;
            font-weight: 900;
            color: #2e7d32;
            line-height: 1;
        }
        .result-box .label {
            font-size: 0.85rem;
            color: #6a8f6a;
            text-transform: uppercase;
            font-weight: 700;
            margin-top: 5px;
        }
        .result-box .class {
            font-size: 1.2rem;
            font-weight: 800;
            color: #0d2818;
            margin-top: 10px;
        }
        
        .footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid #ccc;
            font-size: 0.8rem;
            color: #6a8f6a;
        }
        .footer a {
            color: #2e7d32;
            text-decoration: none;
            font-weight: 700;
        }
        
        /* ============================================ */
        /* RESPONSIVE */
        /* ============================================ */
        @media (max-width: 600px) {
            .container { padding: 20px; }
            .student-wrapper {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .student-photo {
                width: 130px;
                height: 160px;
                margin: 0 auto;
            }
            .info-row {
                grid-template-columns: 1fr;
                gap: 3px;
                text-align: left;
            }
            .info-row .label {
                font-size: 0.8rem;
                color: #6a8f6a;
            }
            .result-box .big { font-size: 2.2rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <div class="header">
            <div class="logo">DALA <span>COLLEGE</span></div>
            <div class="subtitle">of Education, Kano</div>
            <div class="verify-badge">Verified Document</div>
        </div>
        
        <!-- STUDENT INFO + PHOTO -->
        <div class="student-wrapper">
            <div class="student-photo">
                <img src="<?php echo htmlspecialchars($photo_path); ?>" 
                     alt="Student Photo"
                     onerror="this.src='https://via.placeholder.com/150x180/cccccc/333333?text=PHOTO'">
            </div>
            <div class="student-info">
                <div class="info-row">
                    <span class="label">Student Name:</span>
                    <span class="value"><?php echo htmlspecialchars($student['fullname']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Registration No:</span>
                    <span class="value"><?php echo htmlspecialchars($student['reg_no'] ?? $student['student_id']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Combination:</span>
                    <span class="value"><?php echo htmlspecialchars($student['combination'] ?? $student['course'] ?? '—'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Programme:</span>
                    <span class="value"><?php echo htmlspecialchars($student['programme'] ?? 'NCE'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Level:</span>
                    <span class="value"><?php echo htmlspecialchars($student['level'] ?? '—'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Graduation Year:</span>
                    <span class="value"><?php echo htmlspecialchars($grad_year); ?></span>
                </div>
            </div>
        </div>
        
        <!-- RESULT BOX -->
        <div class="result-box">
            <div class="big"><?php echo number_format($cgpa, 2); ?></div>
            <div class="label">CGPA</div>
            <div class="class"><?php echo $class; ?></div>
        </div>
        
        <!-- FOOTER -->
        <div class="footer">
            This document is verified by Dala College of Education, Kano.
            <br>
            For more information, visit 
            <a href="https://dalacoe.edu.ng">dalacoe.edu.ng</a>
        </div>
    </div>
</body>
</html>