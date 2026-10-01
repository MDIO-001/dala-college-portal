<?php
session_start();
include 'connect.php';

// ============================================
// KARƊI student_id DAGA URL
// ============================================
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

if ($student_id <= 0) {
    header('Location: apply.php');
    exit();
}

// ============================================
// NEMO ƊALIBI
// ============================================
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    die("
        <div style='font-family:Arial; text-align:center; padding:50px;'>
            <h2 style='color:#c62828;'>❌ Student not found</h2>
            <p>Please contact support.</p>
            <a href='apply.php' style='display:inline-block; margin-top:20px; padding:12px 25px; background:#2e7d32; color:white; text-decoration:none; border-radius:8px; font-weight:bold;'>Back to Apply</a>
        </div>
    ");
}

// ============================================
// ZAƁI ITEM CODE BISA PROGRAMME
// ============================================
$programme = strtoupper(trim($student['programme']));

if ($programme == 'NCE') {
    $item_code = 'APP-NCE';
    $fee_name = 'NCE Application Fee';
} elseif ($programme == 'DEGREE' || $programme == 'DEG') {
    $item_code = 'APP-DEG';
    $fee_name = 'Degree Application Fee';
} elseif ($programme == 'ENTREPRENEURSHIP' || $programme == 'ENT') {
    $item_code = 'APP-ENT';
    $fee_name = 'Entrepreneurship Application Fee';
} else {
    $item_code = 'APP-NCE';
    $fee_name = 'Application Fee';
}

// ============================================
// NEMO ITEM ɗIN
// ============================================
$item_code_esc = mysqli_real_escape_string($conn, $item_code);
$item_query = mysqli_query($conn, "
    SELECT * FROM payment_items 
    WHERE item_code = '$item_code_esc' 
    AND is_active = 1 
    LIMIT 1
");

if (!$item_query || mysqli_num_rows($item_query) == 0) {
    die("
        <div style='font-family:Arial; text-align:center; padding:50px;'>
            <h2 style='color:#c62828;'>❌ Application Fee not configured</h2>
            <p>Please contact admin to set up <strong>$fee_name</strong>.</p>
            <a href='apply.php' style='display:inline-block; margin-top:20px; padding:12px 25px; background:#2e7d32; color:white; text-decoration:none; border-radius:8px; font-weight:bold;'>Back to Apply</a>
        </div>
    ");
}

$item = mysqli_fetch_assoc($item_query);
$item_id = intval($item['id']);

// ============================================
// DUBA KO YA RIGA YA BIYA
// ============================================
$check_paid = $conn->prepare("SELECT * FROM payments WHERE student_id = ? AND payment_item_id = ? AND status = 'paid' LIMIT 1");
$check_paid->bind_param("ii", $student_id, $item_id);
$check_paid->execute();
$already_paid = $check_paid->get_result()->fetch_assoc();

if ($already_paid) {
    // Ya riga ya biya — tura shi login
    header('Location: login.php?msg=already_paid');
    exit();
}

// ============================================
// DUBA KO AKWAI PENDING PAYMENT
// ============================================
$check_pending = $conn->prepare("SELECT * FROM payments WHERE student_id = ? AND payment_item_id = ? AND status = 'pending' LIMIT 1");
$check_pending->bind_param("ii", $student_id, $item_id);
$check_pending->execute();
$existing_payment = $check_pending->get_result()->fetch_assoc();

if ($existing_payment) {
    // Yi amfani da pending payment ɗin da ke nan
    $reference = $existing_payment['reference_no'];
    $payment_id = $existing_payment['id'];
} else {
    // ============================================
    // ƘIRƘIRI SABON PENDING PAYMENT
    // ============================================
    $reference = 'APP-' . strtoupper(uniqid()) . '-' . $student_id;
    $amount = floatval($item['amount']);
    $reg_no = $student['reg_no'] ?? 'N/A';
    $fullname = $student['fullname'] ?? '';
    $combination = $student['combination'] ?? $student['course'] ?? '';
    $level = $student['level'] ?? '';
    $session = '2026/2027';
    
    $stmt = $conn->prepare("INSERT INTO payments 
        (student_id, reg_no, student_name, combination, level, payment_item_id, amount, payment_type, session, reference_no, status, payment_date, recorded_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), 'Self-Service')");
    
    $stmt->bind_param("issssidsss", 
        $student_id, 
        $reg_no,
        $fullname, 
        $combination,
        $level,
        $item_id, 
        $amount, 
        $fee_name, 
        $session, 
        $reference
    );
    $stmt->execute();
    $payment_id = $conn->insert_id;
}

// ============================================
// PAYSTACK PUBLIC KEY
// ============================================
$paystack_public_key = 'pk_test_36290a2aeb9b1b6d4167be781d2c277455fed594';

// ============================================
// EMAIL ɗin da za a yi amfani da shi
// ============================================
$email = !empty($student['email']) ? $student['email'] : 'applicant' . $student_id . '@dalacoe.edu.ng';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Application Payment - Dala College</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #f0f4f8 0%, #e0e8e0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .checkout-box {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            max-width: 520px;
            width: 100%;
        }
        .lock-icon {
            text-align: center;
            margin-bottom: 20px;
        }
        .lock-icon i {
            font-size: 3rem;
            color: #2e7d32;
            background: #e8f5e9;
            padding: 20px;
            border-radius: 50%;
        }
        h1 {
            text-align: center;
            color: #0d2818;
            font-size: 1.4rem;
            font-weight: 800;
            margin-bottom: 5px;
        }
        .item-name {
            text-align: center;
            color: #666;
            margin-bottom: 25px;
            font-size: 1rem;
        }
        .amount {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 800;
            color: #2e7d32;
            margin: 20px 0;
            padding: 20px;
            background: #f0f8f0;
            border-radius: 12px;
            border: 2px dashed #a5d6a7;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px dotted #e0e8e0;
            font-size: 0.9rem;
        }
        .info-row .label { color: #666; }
        .info-row .value { color: #0d2818; font-weight: 700; }
        .btn-paystack {
            background: linear-gradient(135deg, #00c3f7, #0095c4);
            color: white;
            padding: 16px 40px;
            border: none;
            border-radius: 30px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            margin-top: 25px;
            transition: all 0.3s ease;
        }
        .btn-paystack:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0,195,247,0.4);
        }
        .btn-back {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .btn-back:hover { color: #c62828; }
        .info-note {
            text-align: center;
            margin-top: 20px;
            font-size: 0.82rem;
            color: #999;
        }
        .info-note i { color: #2e7d32; }
    </style>
</head>
<body>
    <div class="checkout-box">
        <div class="lock-icon"><i class="fas fa-lock"></i></div>
        <h1>Application Payment</h1>
        <p class="item-name"><?php echo htmlspecialchars($fee_name); ?></p>
        
        <div class="amount">₦<?php echo number_format($item['amount'], 2); ?></div>
        
        <div class="info-row">
            <span class="label">Name:</span>
            <span class="value"><?php echo htmlspecialchars($student['fullname']); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Phone:</span>
            <span class="value"><?php echo htmlspecialchars($student['phone']); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Programme:</span>
            <span class="value"><?php echo htmlspecialchars($student['programme']); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Course:</span>
            <span class="value"><?php echo htmlspecialchars($student['course']); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Reference:</span>
            <span class="value" style="font-size:0.78rem;"><?php echo $reference; ?></span>
        </div>
        
        <button class="btn-paystack" onclick="payWithPaystack()">
            <i class="fas fa-credit-card"></i> Pay ₦<?php echo number_format($item['amount'], 2); ?>
        </button>
        
        <a href="apply.php" class="btn-back">← Cancel and go back</a>
        
        <p class="info-note">
            <i class="fas fa-shield-alt"></i> Payment is secured by Paystack
        </p>
    </div>

    <script src="https://js.paystack.co/v1/inline.js"></script>
    <script>
    function payWithPaystack() {
        var handler = PaystackPop.setup({
            key: '<?php echo $paystack_public_key; ?>',
            email: '<?php echo $email; ?>',
            amount: <?php echo intval($item['amount'] * 100); ?>,
            currency: 'NGN',
            ref: '<?php echo $reference; ?>',
            metadata: {
                custom_fields: [
                    { display_name: "Applicant Name", variable_name: "applicant_name", value: "<?php echo $student['fullname']; ?>" },
                    { display_name: "Phone", variable_name: "phone", value: "<?php echo $student['phone']; ?>" },
                    { display_name: "Programme", variable_name: "programme", value: "<?php echo $student['programme']; ?>" }
                ]
            },
            callback: function(response) {
                window.location.href = 'application_payment_verify.php?reference=' + response.reference + '&payment_id=<?php echo $payment_id; ?>&student_id=<?php echo $student_id; ?>';
            },
            onClose: function() {
                alert('Payment window closed.');
            }
        });
        handler.openIframe();
    }
    </script>
</body>
</html>