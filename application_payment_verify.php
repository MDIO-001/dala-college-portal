<?php
session_start();
include 'connect.php';

// ============================================
// KARƊI BAYANAI DAGA URL
// ============================================
$reference = $_GET['reference'] ?? '';
$payment_id = intval($_GET['payment_id'] ?? 0);
$student_id = intval($_GET['student_id'] ?? 0);

if (empty($reference) || $payment_id <= 0 || $student_id <= 0) {
    die("
        <div style='font-family:Arial; text-align:center; padding:50px;'>
            <h2 style='color:#c62828;'>❌ Invalid payment reference</h2>
            <p>Please try again or contact support.</p>
            <a href='apply.php' style='display:inline-block; margin-top:20px; padding:12px 25px; background:#2e7d32; color:white; text-decoration:none; border-radius:8px; font-weight:bold;'>Back to Apply</a>
        </div>
    ");
}

// ============================================
// PAYSTACK SECRET KEY
// ============================================
$paystack_secret_key = 'sk_test_94091cbfc8d0cdf7bd12c6b33d26145c4b4d342d';

// ============================================
// VERIFY PAYMENT WITH PAYSTACK
// ============================================
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . $paystack_secret_key,
        "Cache-Control: no-cache",
    ],
]);
$response = curl_exec($curl);
curl_close($curl);

$result = json_decode($response, true);

// ============================================
// IDAN PAYMENT YA YI NASARA
// ============================================
if ($result && $result['status'] && $result['data']['status'] === 'success') {
    $amount_paid = $result['data']['amount'] / 100;
    $method = $result['data']['channel'] ?? 'card';
    $gateway_ref = $result['data']['reference'];
    
    // ============================================
    // UPDATE PAYMENT STATUS
    // ============================================
    $stmt = $conn->prepare("UPDATE payments 
        SET status = 'paid', paid_at = NOW(), payment_method = ?, transaction_id = ? 
        WHERE id = ?");
    $stmt->bind_param("ssi", $method, $gateway_ref, $payment_id);
    $stmt->execute();
    
    // ============================================
    // NEMO BAYANAN ƊALIBI (USERNAME + PASSWORD)
    // ============================================
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    
    if (!$student) {
        die("Student not found.");
    }
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Payment Successful - Dala College</title>
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
            .box {
                background: white;
                padding: 50px 40px;
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.15);
                max-width: 560px;
                width: 100%;
                text-align: center;
            }
            .success-icon {
                display: inline-block;
                background: #e8f5e9;
                color: #2e7d32;
                width: 110px;
                height: 110px;
                border-radius: 50%;
                line-height: 110px;
                font-size: 3.5rem;
                margin-bottom: 20px;
                animation: pop 0.5s ease;
            }
            @keyframes pop {
                0% { transform: scale(0); }
                70% { transform: scale(1.15); }
                100% { transform: scale(1); }
            }
            h1 {
                color: #0d2818;
                font-size: 1.6rem;
                font-weight: 800;
                margin-bottom: 8px;
            }
            .subtitle {
                color: #666;
                font-size: 0.95rem;
                line-height: 1.6;
                margin-bottom: 20px;
            }
            
            /* LOGIN DETAILS BOX */
            .login-details {
                background: #fff9c4;
                border: 2px solid #f9a825;
                border-radius: 12px;
                padding: 20px 25px;
                margin: 20px 0;
                text-align: left;
            }
            .login-details h3 {
                color: #0d2818;
                font-size: 1rem;
                margin-bottom: 15px;
                text-align: center;
                text-transform: uppercase;
                letter-spacing: 1px;
                padding-bottom: 10px;
                border-bottom: 2px dashed #f9a825;
            }
            .login-details .row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 0;
                border-bottom: 1px dashed #f9a825;
            }
            .login-details .row:last-child { border-bottom: none; }
            .login-details .label { 
                color: #666; 
                font-size: 0.85rem; 
                font-weight: 600;
            }
            .login-details .value { 
                color: #0d2818; 
                font-weight: 800; 
                font-family: 'Courier New', monospace; 
                font-size: 0.95rem;
                background: white;
                padding: 4px 12px;
                border-radius: 6px;
                border: 1px solid #f9a825;
                letter-spacing: 0.5px;
            }
            
            /* WARNING */
            .warning-msg {
                background: #ffebee;
                color: #c62828;
                padding: 12px 18px;
                border-radius: 8px;
                font-size: 0.85rem;
                font-weight: 700;
                margin: 15px 0;
                border-left: 4px solid #c62828;
                text-align: left;
            }
            
            /* PAYMENT SUMMARY */
            .payment-summary {
                background: #f0f8f0;
                padding: 15px 20px;
                border-radius: 10px;
                margin: 15px 0;
                text-align: left;
                border: 1px solid #a5d6a7;
            }
            .payment-summary .row {
                display: flex;
                justify-content: space-between;
                padding: 6px 0;
                font-size: 0.85rem;
            }
            .payment-summary .row .label { color: #666; }
            .payment-summary .row .value { 
                color: #0d2818; 
                font-weight: 700;
                font-family: monospace;
                font-size: 0.82rem;
            }
            
            .btn-login {
                display: inline-block;
                background: linear-gradient(135deg, #2e7d32, #1b5e20);
                color: white;
                padding: 15px 45px;
                border-radius: 30px;
                text-decoration: none;
                font-weight: 700;
                font-size: 1rem;
                transition: all 0.3s ease;
                margin-top: 15px;
            }
            .btn-login:hover {
                transform: translateY(-3px);
                box-shadow: 0 10px 25px rgba(46,125,50,0.35);
            }
            .btn-print {
                display: inline-block;
                background: #1976d2;
                color: white;
                padding: 12px 35px;
                border-radius: 30px;
                text-decoration: none;
                font-weight: 700;
                font-size: 0.95rem;
                transition: all 0.3s ease;
                margin: 10px 5px 0;
                border: none;
                cursor: pointer;
            }
            .btn-print:hover {
                background: #0d47a1;
                transform: translateY(-2px);
            }
            
            @media print {
                body { background: white; }
                .no-print { display: none !important; }
                .box { box-shadow: none; }
            }
        </style>
    </head>
    <body>
        <div class="box">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h1>Payment Successful!</h1>
            <p class="subtitle">Your application fee has been received. Below are your login details.</p>
            
            <!-- ============================================ -->
            <!-- LOGIN DETAILS -->
            <!-- ============================================ -->
            <div class="login-details">
                <h3>🔑 Your Login Details</h3>
                <div class="row">
                    <span class="label">Username:</span>
                    <span class="value"><?php echo htmlspecialchars($student['username']); ?></span>
                </div>
                <div class="row">
                    <span class="label">Password:</span>
                    <span class="value"><?php echo htmlspecialchars($student['password_plain'] ?? '(the one you chose)'); ?></span>
                </div>
            </div>
            
          <div class="warning-msg">
    ⚠️ <strong>Important:</strong> Save these login details in a safe place. You will need them to access your Dashboard.
</div>
            
            <!-- ============================================ -->
            <!-- PAYMENT SUMMARY -->
            <!-- ============================================ -->
            <div class="payment-summary">
                <div class="row">
                    <span class="label">Programme:</span>
                    <span class="value"><?php echo htmlspecialchars($student['programme']); ?></span>
                </div>
                <div class="row">
                    <span class="label">Course:</span>
                    <span class="value"><?php echo htmlspecialchars($student['course']); ?></span>
                </div>
                <div class="row">
                    <span class="label">Reference:</span>
                    <span class="value"><?php echo htmlspecialchars($reference); ?></span>
                </div>
                <div class="row">
                    <span class="label">Amount Paid:</span>
                    <span class="value">₦<?php echo number_format($amount_paid, 2); ?></span>
                </div>
            </div>
            
            <p style="color:#6a8f6a; font-size:0.85rem; margin-top:15px;">
                Your application is now pending admin review. You will be notified once processed.
            </p>
            
            <a href="login.php" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Go to Login
            </a>
            
            <div class="no-print" style="margin-top:12px;">
                <button onclick="window.print()" class="btn-print">
                    <i class="fas fa-print"></i> Print This Page
                </button>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
    
} else {
    // ============================================
    // PAYMENT FAILED
    // ============================================
    $stmt = $conn->prepare("UPDATE payments SET status = 'failed' WHERE id = ?");
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Payment Failed - Dala College</title>
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
            .box {
                background: white;
                padding: 50px 40px;
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.15);
                max-width: 520px;
                width: 100%;
                text-align: center;
            }
            .fail-icon {
                display: inline-block;
                background: #ffebee;
                color: #c62828;
                width: 110px;
                height: 110px;
                border-radius: 50%;
                line-height: 110px;
                font-size: 3.5rem;
                margin-bottom: 20px;
            }
            h1 {
                color: #c62828;
                font-size: 1.5rem;
                font-weight: 800;
                margin-bottom: 10px;
            }
            p {
                color: #666;
                font-size: 0.95rem;
                line-height: 1.7;
                margin-bottom: 15px;
            }
            .btn-retry {
                display: inline-block;
                background: linear-gradient(135deg, #2e7d32, #1b5e20);
                color: white;
                padding: 15px 45px;
                border-radius: 30px;
                text-decoration: none;
                font-weight: 700;
                font-size: 1rem;
                margin: 10px 5px;
                transition: all 0.3s ease;
            }
            .btn-retry:hover {
                transform: translateY(-3px);
                box-shadow: 0 10px 25px rgba(46,125,50,0.35);
            }
        </style>
    </head>
    <body>
        <div class="box">
            <div class="fail-icon">
                <i class="fas fa-times"></i>
            </div>
            <h1>Payment Verification Failed</h1>
            <p>Your payment could not be verified. This may be due to a network issue or a cancelled transaction.</p>
            <p>Please try again or contact support if the problem persists.</p>
            
            <a href="application_payment.php?student_id=<?php echo $student_id; ?>" class="btn-retry">
                <i class="fas fa-redo"></i> Try Again
            </a>
        </div>
    </body>
    </html>
    <?php
    exit();
}
?>