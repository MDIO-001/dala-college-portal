<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$reference = $_GET['ref'] ?? '';

if (empty($reference)) {
    die("Invalid reference.");
}

$stmt = $conn->prepare("
    SELECT p.*, s.fullname, s.reg_no 
    FROM payments p
    JOIN students s ON p.student_id = s.id
    WHERE p.reference_no = ? AND p.student_id = ? AND p.status = 'paid'
    LIMIT 1
");
$stmt->bind_param("si", $reference, $student_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

if (!$payment) {
    die("Receipt not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Receipt - Dala College</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Georgia',serif; background:#f0f4f8; padding:20px; }
        .receipt { max-width:700px; margin:0 auto; background:white; padding:40px; border-radius:12px; box-shadow:0 10px 40px rgba(0,0,0,0.1); border-top:6px solid #2e7d32; }
        .receipt-header { text-align:center; border-bottom:2px solid #0d2818; padding-bottom:20px; margin-bottom:25px; }
        .receipt-header h1 { color:#0d2818; font-size:1.5rem; font-weight:800; }
        .receipt-header p { color:#666; font-size:0.9rem; margin-top:5px; }
        .receipt-title { text-align:center; font-size:1.2rem; font-weight:800; color:#2e7d32; margin-bottom:25px; text-transform:uppercase; letter-spacing:2px; }
        .info-row { display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px dotted #ccc; }
        .info-row .label { font-weight:700; color:#0d2818; }
        .info-row .value { color:#1a2e1a; }
        .amount-box { background:#e8f5e9; padding:25px; border-radius:8px; text-align:center; margin:25px 0; }
        .amount-box .amount { font-size:2.2rem; font-weight:800; color:#2e7d32; }
        .amount-box .label { color:#666; font-size:0.85rem; text-transform:uppercase; letter-spacing:1px; }
        .success-badge { text-align:center; color:#2e7d32; font-weight:700; font-size:1.05rem; margin-bottom:20px; }
        .btn-print { display:block; width:220px; margin:20px auto; padding:12px; background:#2e7d32; color:white; border:none; border-radius:25px; font-weight:700; font-size:1rem; cursor:pointer; }
        .btn-back { display:block; width:220px; margin:10px auto; padding:12px; background:#f0f4f8; color:#0d2818; border:none; border-radius:25px; font-weight:700; font-size:1rem; cursor:pointer; text-align:center; text-decoration:none; }
        @media print {
            body { background:white; padding:0; }
            .receipt { box-shadow:none; border-radius:0; }
            .no-print { display:none !important; }
        }
    </style>
</head>
<body>

<div class="receipt" id="receipt">
    <div class="receipt-header">
        <h1>DALA COLLEGE OF EDUCATION, KANO</h1>
        <p>Kano State - Nigeria</p>
        <p>www.dalacoe.edu.ng | info@dalacoe.edu.ng</p>
    </div>
    
    <div class="receipt-title">Payment Receipt</div>
    
    <div class="success-badge">
        <i class="fas fa-check-circle"></i> Payment Successful
    </div>
    
    <div class="info-row">
        <span class="label">Receipt No:</span>
        <span class="value"><?php echo htmlspecialchars($payment['reference_no']); ?></span>
    </div>
    <div class="info-row">
        <span class="label">Student Name:</span>
        <span class="value"><?php echo htmlspecialchars($payment['fullname']); ?></span>
    </div>
    <div class="info-row">
        <span class="label">Registration No:</span>
        <span class="value"><?php echo htmlspecialchars($payment['reg_no']); ?></span>
    </div>
    <div class="info-row">
        <span class="label">Payment Item:</span>
        <span class="value"><?php echo htmlspecialchars($payment['payment_type']); ?></span>
    </div>
    <div class="info-row">
        <span class="label">Session:</span>
        <span class="value"><?php echo htmlspecialchars($payment['session']); ?></span>
    </div>
    <div class="info-row">
        <span class="label">Payment Method:</span>
        <span class="value"><?php echo htmlspecialchars(ucfirst($payment['payment_method'] ?? 'Online')); ?></span>
    </div>
    <div class="info-row">
        <span class="label">Date Paid:</span>
        <span class="value"><?php echo date('F j, Y g:i A', strtotime($payment['paid_at'])); ?></span>
    </div>
    
    <div class="amount-box">
        <div class="label">Amount Paid</div>
        <div class="amount">₦<?php echo number_format($payment['amount'], 2); ?></div>
    </div>
    
    <button class="btn-print no-print" onclick="window.print()">
        <i class="fas fa-print"></i> Print Receipt
    </button>
    <a href="student_payments.php" class="btn-back no-print">
        ← Back to Payments
    </a>
</div>

</body>
</html>