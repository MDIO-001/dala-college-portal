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
// VERIFY WITH PAYSTACK
// ============================================
$paystack_secret_key = 'sk_test_xxxxxxxxxxxxxxxxxxxxx'; // Ka saka naka

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
    $amount_paid = $result['data']['amount'] / 100; // Convert from kobo
    $gateway_ref = $result['data']['reference'];
    
    // Update payment record
    $stmt = $conn->prepare("UPDATE payments SET status = 'paid', paid_at = NOW(), transaction_id = ?, payment_method = ? WHERE id = ? AND student_id = ?");
    $method = $result['data']['channel'] ?? 'card';
    $stmt->bind_param("ssii", $gateway_ref, $method, $payment_id, $student_id);
    $stmt->execute();
    
    // Log transaction
    $stmt = $conn->prepare("INSERT INTO payment_transactions (payment_id, gateway, gateway_reference, amount, status, response_data, created_at) VALUES (?, 'paystack', ?, ?, 'success', ?, NOW())");
    $response_json = json_encode($result);
    $stmt->bind_param("isd s", $payment_id, $gateway_ref, $amount_paid, $response_json);
    $stmt->execute();
    
    header('Location: payment_receipt.php?ref=' . urlencode($reference));
    exit();
} else {
    // Payment failed
    $stmt = $conn->prepare("UPDATE payments SET status = 'failed' WHERE id = ? AND student_id = ?");
    $stmt->bind_param("ii", $payment_id, $student_id);
    $stmt->execute();
    
    die("Payment verification failed. Please try again or contact support.");
}