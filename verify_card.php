<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$card_type = $_GET['type'] ?? '';
$message = '';
$message_type = '';

// ============================================
// TABBATAR DA CARD
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_card'])) {
    $scratch_pin = mysqli_real_escape_string($conn, trim($_POST['scratch_pin']));
    $card_type = mysqli_real_escape_string($conn, $_POST['card_type']);
    
    // ============================================
    // ƊAUKO BAYANAN CARD DA ƊALIBIN DA YA RIGA YA YI AMFANI
    // ============================================
    $check = mysqli_query($conn, "SELECT sc.*, s.fullname AS used_by_name, s.reg_no AS used_by_regno 
                                  FROM scratch_cards sc
                                  LEFT JOIN students s ON sc.used_by = s.id
                                  WHERE (sc.pin = '$scratch_pin' OR sc.card_code = '$scratch_pin') 
                                  AND sc.card_type = '$card_type'");
    
    if (!$check || mysqli_num_rows($check) == 0) {
        $message = "❌ Invalid Scratch Card or not for this service.";
        $message_type = 'error';
    } else {
        $card = mysqli_fetch_assoc($check);
        
        // ============================================
        // DUBA IDAN CARD ɗIN A KASHE
        // ============================================
        if (isset($card['is_active']) && $card['is_active'] == 0) {
            $message = "❌ This Scratch Card has been disabled. Contact Admin.";
            $message_type = 'error';
        }
        // ============================================
        // DUBA EXPIRY
        // ============================================
        elseif (!empty($card['expiry_date']) && strtotime($card['expiry_date']) < strtotime(date('Y-m-d'))) {
            $message = "❌ This Scratch Card has expired.";
            $message_type = 'error';
        }
        // ============================================
        // DUBA USAGE LIMIT
        // ============================================
        elseif (isset($card['usage_count']) && isset($card['usage_limit']) && $card['usage_count'] >= $card['usage_limit']) {
            $message = "❌ This Scratch Card has reached its usage limit ({$card['usage_limit']} times).";
            $message_type = 'error';
        }
        // ============================================
        // DUBA IDAN WANI ƊALIBI YA RIGA YA YI AMFANI
        // ============================================
        elseif (!empty($card['used_by']) && $card['used_by'] != $student_id) {
            $used_by_name = $card['used_by_name'] ?? 'Unknown Student';
            $used_by_regno = $card['used_by_regno'] ?? 'N/A';
            
            $message = "❌ This Scratch Card has already been used.<br>";
            $message .= "👤 <strong>Used by:</strong> " . htmlspecialchars($used_by_name) . "<br>";
            $message .= "🎓 <strong>Reg No:</strong> " . htmlspecialchars($used_by_regno);
            $message_type = 'error';
        }
        // ============================================
        // DUBA STATUS
        // ============================================
        elseif ($card['status'] == 'used' && ($card['usage_count'] ?? 0) >= ($card['usage_limit'] ?? 10)) {
            $used_by_name = $card['used_by_name'] ?? 'Unknown Student';
            $used_by_regno = $card['used_by_regno'] ?? 'N/A';
            
            $message = "❌ This Scratch Card has been fully used.<br>";
            $message .= "👤 <strong>Used by:</strong> " . htmlspecialchars($used_by_name) . "<br>";
            $message .= "🎓 <strong>Reg No:</strong> " . htmlspecialchars($used_by_regno);
            $message_type = 'error';
        }
        else {
            // ============================================
            // SABUNTA USAGE COUNT
            // ============================================
            $new_count = ($card['usage_count'] ?? 0) + 1;
            $new_status = ($new_count >= ($card['usage_limit'] ?? 10)) ? 'used' : 'unused';
            
            mysqli_query($conn, "UPDATE scratch_cards 
                                 SET usage_count = $new_count,
                                     status = '$new_status',
                                     used_by = $student_id, 
                                     used_at = NOW() 
                                 WHERE id = {$card['id']}");
            
            $_SESSION['verified_cards'][$card_type] = true;
            
            $redirects = [
                'application' => 'apply.php',
                'course_registration' => 'course_registration.php',
                'exam_card' => 'exam_card.php',
                'posting_letter' => 'posting_letter.php',
                'acceptance_letter' => 'acceptance_letter.php',
                'receipt' => 'print_receipt.php',
            ];
            
            $redirect = $redirects[$card_type] ?? 'student_dashboard.php';
            header("Location: $redirect");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Scratch Card - Dala College</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f0f4f8; padding: 40px 20px; }
        .box { max-width: 500px; margin: 0 auto; background: white; padding: 40px; border-radius: 16px; box-shadow: 0 4px 30px rgba(0,0,0,0.1); }
        h2 { color: #0d2818; text-align: center; margin-bottom: 10px; }
        .sub { text-align: center; color: #6a8f6a; margin-bottom: 25px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 700; color: #0d2818; margin-bottom: 5px; }
        .form-group input { width: 100%; padding: 14px; border: 2px solid #dce8dc; border-radius: 10px; font-size: 1rem; text-align: center; letter-spacing: 2px; font-weight: 700; }
        .form-group input:focus { border-color: #2e7d32; outline: none; }
        .btn { width: 100%; padding: 14px; background: linear-gradient(135deg, #2e7d32, #1b5e20); color: white; border: none; border-radius: 10px; font-size: 1.1rem; font-weight: 700; cursor: pointer; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(46,125,50,0.3); }
        .alert-error { background: #ffebee; color: #c62828; padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid #c62828; line-height: 1.8; }
        .back { display: inline-block; margin-top: 20px; color: #2e7d32; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>

<div class="box">
    <h2>🎫 Enter Scratch Card</h2>
    <p class="sub">
        <?php 
        $titles = [
            'application' => 'Application',
            'course_registration' => 'Course Registration',
            'exam_card' => 'Exam Card',
            'posting_letter' => 'Posting Letter (T.P)',
            'acceptance_letter' => 'Acceptance Letter',
            'receipt' => 'Print Receipt',
        ];
        echo $titles[$card_type] ?? 'Scratch Card';
        ?>
    </p>
    
    <?php if ($message): ?>
        <div class="alert-error"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="card_type" value="<?php echo htmlspecialchars($card_type); ?>">
        <div class="form-group">
            <label>Scratch Card PIN / Code</label>
            <input type="text" name="scratch_pin" placeholder="Enter card code" required maxlength="50">
        </div>
        <button type="submit" name="verify_card" class="btn">
            <i class="fas fa-check-circle"></i> Verify Card
        </button>
    </form>
    
    <a href="student_dashboard.php" class="back">← Back to Dashboard</a>
</div>

</body>
</html>