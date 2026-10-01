<?php
include 'connect.php';

$secret_key = 'sk_test_94091cbfc8d0cdf7bd12c6b33d26145c4b4d342d';
$input = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

// Verify signature
if (hash_hmac('sha512', $input, $secret_key) !== $signature) {
    http_response_code(401);
    exit('Invalid signature');
}

$event = json_decode($input, true);

if ($event['event'] === 'charge.success') {
    $reference = $event['data']['reference'];
    $amount = $event['data']['amount'] / 100;
    
    $stmt = $conn->prepare("UPDATE payments SET status = 'paid', paid_at = NOW(), transaction_id = ? WHERE reference = ? AND status = 'pending'");
    $stmt->bind_param("ss", $reference, $reference);
    $stmt->execute();
}

http_response_code(200);
echo 'OK';