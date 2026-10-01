<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;

if ($item_id <= 0) {
    header('Location: student_payments.php');
    exit();
}

// Get student
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

// Get payment item
$stmt = $conn->prepare("SELECT * FROM payment_items WHERE id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    die("Payment item not found!");
}

// Check if already paid
$stmt = $conn->prepare("SELECT * FROM payments WHERE student_id = ? AND payment_item_id = ? AND status = 'paid'");
$stmt->bind_param("ii", $student_id, $item_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();

if ($existing) {
    header('Location: student_payment_receipt.php?ref=' . urlencode($existing['reference_no']));
    exit();
}

// Create pending payment record
$reference = 'DLC-' . strtoupper(uniqid()) . '-' . $student_id;
$session = '2026/2027';
$level = $student['level'] ?? 'NCE I';

$stmt = $conn->prepare("INSERT INTO payments 
    (student_id, reg_no, student_name, combination, level, payment_item_id, amount, payment_type, session, reference_no, status, payment_date, recorded_by) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), 'Self-Service')");
$stmt->bind_param("issssidsss", 
    $student_id, 
    $student['reg_no'], 
    $student['fullname'], 
    $student['combination'], 
    $level, 
    $item_id, 
    $item['amount'], 
    $item['item_name'], 
    $session, 
    $reference
);
$stmt->execute();
$payment_id = $conn->insert_id;

// ============================================
// PAYSTACK PUBLIC KEY (ka saka naka)
// ============================================
$paystack_public_key = 'pk_test_xxxxxxxxxxxxxxxxxxxxx';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Checkout - Dala College</title>
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
            max-width: 480px;
            width: 100%;
        }
        .checkout-box .lock-icon {
            text-align: center;
            margin-bottom: 20px;
        }
        .checkout-box .lock-icon i {
            font-size: 3rem;
            color: #2e7d32;
            background: #e8f5e9;
            padding: 20px;
            border-radius: 50%;
        }
        .checkout-box h1 {
            text-align: center;
            color: #0d2818;
            font-size: 1.4rem;
            font-weight: 800;
            margin-bottom: 5px;
        }
        .checkout-box .item-name {
            text-align: center;
            color: #666;
            margin-bottom: 25px;
            font-size: 1rem;
        }
        .checkout-box .amount {
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
        .checkout-box .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px dotted #e0e8e0;
            font-size: 0.9rem;
        }
        .checkout-box .info-row .label { color: #666; }
        .checkout-box .info-row .value { color: #0d2818; font-weight: 700; }
        
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-paystack:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0,195,247,0.4);
        }
        .btn-cancel {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .btn-cancel:hover { color: #c62828; }
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
        
        <div class="lock-icon">
            <i class="fas fa-lock"></i>
        </div>
        
        <h1>Secure Payment</h1>
        <p class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></p>
        
        <div class="amount">₦<?php echo number_format($item['amount'], 2); ?></div>
        
        <div class="info-row">
            <span class="label">Student:</span>
            <span class="value"><?php echo htmlspecialchars($student['fullname']); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Reg No:</span>
            <span class="value"><?php echo htmlspecialchars($student['reg_no']); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Session:</span>
            <span class="value"><?php echo htmlspecialchars($session); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Reference:</span>
            <span class="value" style="font-size:0.78rem;"><?php echo $reference; ?></span>
        </div>
        
        <button class="btn-paystack" onclick="payWithPaystack()">
            <i class="fas fa-credit-card"></i> Pay Now
        </button>
        
        <a href="student_payments.php" class="btn-cancel">← Cancel and go back</a>
        
        <p class="info-note">
            <i class="fas fa-shield-alt"></i> Your payment is secured by Paystack
        </p>
    </div>

    <script src="https://js.paystack.co/v1/inline.js"></script>
    <script>
    function payWithPaystack() {
        var handler = PaystackPop.setup({
            key: '<?php echo $paystack_public_key; ?>',
            email: '<?php echo $student['email'] ?? 'student@dalacoe.edu.ng'; ?>',
            amount: <?php echo intval($item['amount'] * 100); ?>,
            currency: 'NGN',
            ref: '<?php echo $reference; ?>',
            metadata: {
                custom_fields: [
                    { display_name: "Student Name", variable_name: "student_name", value: "<?php echo $student['fullname']; ?>" },
                    { display_name: "Student ID", variable_name: "student_id", value: "<?php echo $student_id; ?>" },
                    { display_name: "Payment Item", variable_name: "item_name", value: "<?php echo $item['item_name']; ?>" }
                ]
            },
            callback: function(response) {
                window.location.href = 'student_payment_verify.php?reference=' + response.reference + '&payment_id=<?php echo $payment_id; ?>';
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