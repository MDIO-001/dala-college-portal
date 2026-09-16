<?php
session_start();
include 'connect.php';

// Check if student or admin is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Idan Admin ne, zai iya duba ID Card na kowane ɗalibi ta hanyar ?student_id=X
if ($role == 'admin' && isset($_GET['student_id'])) {
    $student_id = intval($_GET['student_id']);
} elseif ($role == 'student') {
    $student_id = $user_id;
} else {
    die("Unauthorized access.");
}

// ============================================
// GET STUDENT DATA
// ============================================
$s_query = "SELECT * FROM students WHERE id = $student_id";
$s_result = mysqli_query($conn, $s_query);
$student = mysqli_fetch_assoc($s_result);

if (!$student) {
    die("Student not found!");
}

$student_combination = !empty($student['combination']) 
    ? trim($student['combination']) 
    : trim($student['course']);

// ============================================
// GET OR CREATE ID CARD
// ============================================
$session = '2024/2025';

$card_query = "SELECT * FROM id_cards WHERE student_id = $student_id AND session = '$session' LIMIT 1";
$card_result = mysqli_query($conn, $card_query);
$card = mysqli_fetch_assoc($card_result);

if (!$card) {
    // Ƙirƙiri sabon ID Card
    $card_number = 'DLC/ID/' . date('y') . '/' . str_pad($student_id, 4, '0', STR_PAD_LEFT);
    $issue_date = date('Y-m-d');
    $expiry_date = date('Y-m-d', strtotime('+3 years'));
    
    $insert = "INSERT INTO id_cards (student_id, card_number, issue_date, expiry_date, session) 
               VALUES ($student_id, '$card_number', '$issue_date', '$expiry_date', '$session')";
    mysqli_query($conn, $insert);
    
    $card_query = "SELECT * FROM id_cards WHERE student_id = $student_id AND session = '$session' LIMIT 1";
    $card_result = mysqli_query($conn, $card_query);
    $card = mysqli_fetch_assoc($card_result);
}

// ============================================
// STUDENT PHOTO
// ============================================
$photo_path = "uploads/students/default.png";
if (!empty($student['photo']) && file_exists("uploads/students/" . $student['photo'])) {
    $photo_path = "uploads/students/" . $student['photo'];
} elseif (!empty($student['reg_no']) && file_exists("uploads/students/" . $student['reg_no'] . ".jpg")) {
    $photo_path = "uploads/students/" . $student['reg_no'] . ".jpg";
} elseif (!empty($student['reg_no']) && file_exists("uploads/students/" . $student['reg_no'] . ".png")) {
    $photo_path = "uploads/students/" . $student['reg_no'] . ".png";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student ID Card - Dala College</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #f0f4f8;
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        
        /* ============ ACTIONS ============ */
        .actions {
            margin-bottom: 25px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-print { background: #2e7d32; color: white; }
        .btn-print:hover { background: #1b5e20; transform: translateY(-2px); }
        .btn-back { background: #6a8f6a; color: white; }
        .btn-back:hover { background: #4a6a4a; transform: translateY(-2px); }
        
        /* ============ ID CARD CONTAINER ============ */
        .id-card-wrapper {
            display: flex;
            flex-direction: column;
            gap: 30px;
            align-items: center;
        }
        
        /* ============ FRONT SIDE ============ */
        .id-card {
            width: 340px;
            height: 540px;
            background: linear-gradient(135deg, #0d2818 0%, #1b5e20 50%, #2e7d32 100%);
            border-radius: 18px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
            color: white;
        }
        
        .id-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,213,79,0.15) 0%, transparent 70%);
            pointer-events: none;
        }
        
        /* HEADER */
        .card-header {
            background: rgba(255,255,255,0.1);
            padding: 15px 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 2px solid #ffd54f;
            position: relative;
            z-index: 2;
        }
        .card-header .logo {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }
        .card-header .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .card-header .college-info {
            flex: 1;
        }
        .card-header .college-name {
            font-size: 12px;
            font-weight: 900;
            color: #ffd54f;
            text-transform: uppercase;
            line-height: 1.2;
            letter-spacing: 0.3px;
        }
        .card-header .college-sub {
            font-size: 9px;
            color: #c8e6c9;
            margin-top: 2px;
        }
        
        /* PHOTO SECTION */
        .photo-section {
            padding: 20px 18px 10px;
            display: flex;
            justify-content: center;
            position: relative;
            z-index: 2;
        }
        .student-photo {
            width: 130px;
            height: 150px;
            border: 4px solid #ffd54f;
            border-radius: 10px;
            overflow: hidden;
            background: #f0f0f0;
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }
        .student-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* STUDENT NAME */
        .student-name {
            text-align: center;
            padding: 0 15px;
            position: relative;
            z-index: 2;
        }
        .student-name h2 {
            font-size: 16px;
            font-weight: 900;
            color: #ffd54f;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
            line-height: 1.2;
        }
        .student-name .role {
            font-size: 10px;
            color: #a5d6a7;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        /* STUDENT DETAILS */
        .student-details {
            padding: 15px 18px;
            position: relative;
            z-index: 2;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px dashed rgba(255,255,255,0.2);
            font-size: 11px;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-row .label {
            color: #a5d6a7;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.5px;
        }
        .detail-row .value {
            color: #ffffff;
            font-weight: 700;
            text-align: right;
            max-width: 60%;
            word-break: break-word;
        }
        
        /* FOOTER */
        .card-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.3);
            padding: 10px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 9px;
            color: #c8e6c9;
            z-index: 2;
        }
        .card-footer .card-number {
            font-weight: 700;
            color: #ffd54f;
        }
        .card-footer .validity {
            text-align: right;
        }
        
        /* WATERMARK */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 80px;
            font-weight: 900;
            color: rgba(255,255,255,0.04);
            white-space: nowrap;
            pointer-events: none;
            z-index: 1;
        }
        
        /* ============ PRINT ============ */
        @media print {
            body { 
                background: white; 
                padding: 0; 
                display: block;
            }
            .actions { display: none !important; }
            .id-card-wrapper { 
                gap: 20px; 
                align-items: center;
            }
            .id-card { 
                box-shadow: none;
                page-break-inside: avoid;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            @page {
                size: A4 portrait;
                margin: 10mm;
            }
        }
        
        @media (max-width: 600px) {
            .id-card { width: 320px; height: 510px; }
        }
    </style>
</head>
<body>

    <!-- ACTIONS -->
    <div class="actions no-print">
        <?php if ($role == 'admin'): ?>
            <a href="admin_dashboard.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        <?php else: ?>
            <a href="student_dashboard.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        <?php endif; ?>
        <button onclick="window.print()" class="btn btn-print">
            <i class="fas fa-print"></i> Print ID Card
        </button>
    </div>

    <!-- ID CARD -->
    <div class="id-card-wrapper">
        <div class="id-card">
            
            <!-- WATERMARK -->
            <div class="watermark">DALA COLLEGE</div>
            
            <!-- HEADER -->
            <div class="card-header">
                <div class="logo">
                    <img src="images/dala-logo.png" alt="Logo" onerror="this.src='https://via.placeholder.com/45/ffffff/0d2818?text=DC'">
                </div>
                <div class="college-info">
                    <div class="college-name">Dala College of Education, Kano</div>
                    <div class="college-sub">Knowledge, Excellence &amp; Success</div>
                </div>
            </div>
            
            <!-- PHOTO -->
            <div class="photo-section">
                <div class="student-photo">
                    <img src="<?php echo $photo_path; ?>" alt="Student Photo" 
                         onerror="this.src='https://via.placeholder.com/130x150/cccccc/333333?text=PHOTO'">
                </div>
            </div>
            
            <!-- STUDENT NAME -->
            <div class="student-name">
                <h2><?php echo htmlspecialchars($student['fullname']); ?></h2>
                <div class="role">Student</div>
            </div>
            
            <!-- DETAILS -->
            <div class="student-details">
                <div class="detail-row">
                    <span class="label">Reg No:</span>
                    <span class="value"><?php echo htmlspecialchars($student['reg_no'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Combination:</span>
                    <span class="value"><?php echo htmlspecialchars($student_combination); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Level:</span>
                    <span class="value"><?php echo htmlspecialchars($student['level'] ?? 'NCE I'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Programme:</span>
                    <span class="value"><?php echo htmlspecialchars($student['programme'] ?? 'NCE'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Centre:</span>
                    <span class="value"><?php echo htmlspecialchars($student['branch_code'] ?? 'SHINGE'); ?></span>
                </div>
            </div>
            
            <!-- FOOTER -->
            <div class="card-footer">
                <div>
                    <div class="card-number"><?php echo htmlspecialchars($card['card_number'] ?? 'N/A'); ?></div>
                    <div>Card No.</div>
                </div>
                <div class="validity">
                    <div><strong>Valid Until:</strong> <?php echo date('M Y', strtotime($card['expiry_date'] ?? '+3 years')); ?></div>
                    <div>Session: <?php echo $session; ?></div>
                </div>
            </div>
            
        </div>
    </div>

</body>
</html>