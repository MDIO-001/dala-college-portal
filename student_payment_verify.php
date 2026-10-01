<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$reference = $_GET['reference'] ?? '';
$payment_id = intval($_GET['payment_id'] ?? 0);

if (empty($reference) || $payment_id <= 0) {
    die("Invalid payment reference.");
}

// ============================================
// PAYSTACK SECRET KEY (ka saka naka)
// ============================================
$paystack_secret_key = 'sk_test_94091cbfc8d0cdf7bd12c6b33d26145c4b4d342d';

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

if ($result && $result['status'] && $result['data']['status'] === 'success') {
    // Payment successful
    $amount_paid = $result['data']['amount'] / 100;
    $gateway_ref = $result['data']['reference'];
    $method = $result['data']['channel'] ?? 'card';
    
    // Update payment record
    $stmt = $conn->prepare("UPDATE payments 
        SET status = 'paid', paid_at = NOW(), payment_method = ?, recorded_by = CONCAT(recorded_by, ' | Verified via Paystack') 
        WHERE id = ? AND student_id = ?");
    $stmt->bind_param("sii", $method, $payment_id, $student_id);
    $stmt->execute();
    
    // Get reference for receipt
    $stmt = $conn->prepare("SELECT reference_no FROM payments WHERE id = ?");
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $ref_row = $stmt->get_result()->fetch_assoc();
    
    header('Location: student_payment_receipt.php?ref=' . urlencode($ref_row['reference_no']));
    exit();
} else {
    // Payment failed
    $stmt = $conn->prepare("UPDATE payments SET status = 'failed' WHERE id = ? AND student_id = ?");
    $stmt->bind_param("ii", $payment_id, $student_id);
    $stmt->execute();
    
    die("
        <div style='font-family:Arial; text-align:center; padding:50px;'>
            <h2 style='color:#c62828;'>❌ Payment Verification Failed</h2>
            <p>Please try again or contact support.</p>
            <a href='student_payments.php' style='display:inline-block; margin-top:20px; padding:12px 25px; background:#2e7d32; color:white; text-decoration:none; border-radius:8px; font-weight:bold;'>Back to Payments</a>
        </div>
    ");
}