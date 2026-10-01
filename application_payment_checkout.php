<?php
session_start();
include 'connect.php';

// ============================================
// GET PROGRAMME
// ============================================
$programme = isset($_GET['programme']) ? strtoupper(trim($_GET['programme'])) : 'NCE';
if ($programme != 'NCE' && $programme != 'DEGREE' && $programme != 'DEG') {
    $programme = 'NCE';
}

// Zaɓi item code
if ($programme == 'NCE') {
    $item_code = 'APP-NCE';
    $fee_name = 'NCE Application Fee';
} else {
    $item_code = 'APP-DEG';
    $fee_name = 'Degree Application Fee';
    $programme = 'DEGREE';
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
    die("Application fee item not found. Please contact admin.");
}

$item = mysqli_fetch_assoc($item_query);

// ============================================
// GET EMAIL (idan an aiko daga form)
// ============================================
$email = isset($_GET['email']) ? trim($_GET['email']) : '';
$fullname = isset($_GET['fullname']) ? trim($_GET['fullname']) : '';
$phone = isset($_GET['phone']) ? trim($_GET['phone']) : '';

// ============================================
// CREATE PENDING PAYMENT RECORD
// ============================================
$reference = 'APP-' . strtoupper(uniqid()) . '-' . rand(1000, 9999);
$student_id = 0; // Ba a shiga ba (guest application)

// Idan an shiga, yi amfani da student_id
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    $student_id = intval($_SESSION['user_id']);
}

$email_esc = mysqli_real_escape_string($conn, $email);
$fullname_esc = mysqli_real_escape_string($conn, $fullname);
$phone_esc = mysqli_real_escape_string($conn, $phone);
$programme_esc = mysqli_real_escape_string($conn, $programme);
$amount = floatval($item['amount']);
$item_id = intval($item['id']);

$stmt = $conn->prepare("INSERT INTO payments 
    (student_id, reg_no, student_name, combination, level, payment_item_id, amount, payment_type, session, reference_no, status, payment_date, recorded_by) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), 'Application')");
$stmt->bind_param("issssidsss", 
    $student_id, 
    $email_esc,
    $fullname_esc, 
    $phone_esc,
    $programme_esc,
    $item_id, 
    $amount, 
    $fee_name, 
    $programme_esc, 
    $reference
);
$stmt->execute();
$payment_id = $conn->insert_id;

$paystack_public_key = 'pk_test_36290a2aeb9b1b6d4167be781d2c277455fed594';
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
            max-width: 500px;
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
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            font-weight: 700;
            color: #0d2818;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #dce8dc;
            border-radius: 10px;
            font-size: 0.95rem;
        }
        .form-group input:focus {
            outline: none;
            border-color: #2e7d32;
            box-shadow: 0 0 0 3px rgba(46,125,50,0.15);
        }
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
            margin-top: 20px;
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
        
        <div class="form-group">
            <label>Full Name *</label>
            <input type="text" id="fullname" value="<?php echo htmlspecialchars($fullname); ?>" placeholder="Enter your full name" required>
        </div>
        
        <div class="form-group">
            <label>Email Address *</label>
            <input type="email" id="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="Enter your email" required>
        </div>
        
        <div class="form-group">
            <label>Phone Number *</label>
            <input type="tel" id="phone" value="<?php echo htmlspecialchars($phone); ?>" placeholder="Enter your phone number" required>
        </div>
        
        <button class="btn-paystack" onclick="payWithPaystack()">
            <i class="fas fa-credit-card"></i> Pay ₦<?php echo number_format($item['amount'], 2); ?>
        </button>
        
        <a href="index.php" class="btn-back">← Cancel and go back</a>
        
        <p class="info-note">
            <i class="fas fa-shield-alt"></i> Payment is secured by Paystack
        </p>
    </div>

    <script src="https://js.paystack.co/v1/inline.js"></script>
    <script>
    function payWithPaystack() {
        var fullname = document.getElementById('fullname').value.trim();
        var email = document.getElementById('email').value.trim();
        var phone = document.getElementById('phone').value.trim();
        
        if (!fullname || !email || !phone) {
            alert('Please fill in all fields');
            return;
        }
        
        var handler = PaystackPop.setup({
            key: '<?php echo $paystack_public_key; ?>',
            email: email,
            amount: <?php echo intval($item['amount'] * 100); ?>,
            currency: 'NGN',
            ref: '<?php echo $reference; ?>',
            metadata: {
                custom_fields: [
                    { display_name: "Applicant Name", variable_name: "applicant_name", value: fullname },
                    { display_name: "Phone", variable_name: "phone", value: phone },
                    { display_name: "Programme", variable_name: "programme", value: "<?php echo $programme; ?>" }
                ]
            },
            callback: function(response) {
                window.location.href = 'application_payment_verify.php?reference=' + response.reference + '&payment_id=<?php echo $payment_id; ?>&programme=<?php echo urlencode($programme); ?>';
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