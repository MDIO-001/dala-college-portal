<?php
session_start();
include 'connect.php';

// ============================================
// TABBATAR ƊALIBI YA SHIGA
// ============================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['user_id'];

// ============================================
// NEMO BAYANAN ƊALIBI
// ============================================
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    die("Student not found!");
}

$student_level = $student['level'] ?? 'NCE I';
$student_programme = strtoupper($student['programme'] ?? 'NCE');
$student_session = '2026/2027';

// ============================================
// NEMO DUK PAYMENTS ƊIN DA ƊALIBI YA YI
// ============================================
$payments_query = "
    SELECT 
        p.*,
        pi.item_name,
        pi.item_code
    FROM payments p
    LEFT JOIN payment_items pi ON p.payment_item_id = pi.id
    WHERE p.student_id = $student_id
    AND p.status = 'paid'
    ORDER BY p.paid_at ASC, p.id ASC
";

$payments_result = mysqli_query($conn, $payments_query);

$all_payments = [];
$total_paid = 0;

if ($payments_result) {
    while ($row = mysqli_fetch_assoc($payments_result)) {
        $all_payments[] = $row;
        $total_paid += $row['amount'];
    }
}

// ============================================
// NEMO TOTAL REQUIRED
// ============================================
if ($student_programme == 'DEGREE' || $student_programme == 'DEG') {
    $items_query = "
        SELECT SUM(amount) AS total FROM payment_items 
        WHERE is_active = 1
        AND is_mandatory = 1
        AND (level = '$student_level' OR (level IS NULL AND item_code NOT LIKE '%NCE%'))
        AND (session = '$student_session' OR session IS NULL)
    ";
} else {
    $items_query = "
        SELECT SUM(amount) AS total FROM payment_items 
        WHERE is_active = 1
        AND is_mandatory = 1
        AND (level = '$student_level' OR (level IS NULL AND item_code NOT LIKE '%DEG%'))
        AND (session = '$student_session' OR session IS NULL)
    ";
}

$total_required = mysqli_fetch_assoc(mysqli_query($conn, $items_query))['total'] ?? 0;
$total_outstanding = max(0, $total_required - $total_paid);

// ============================================
// QR CODE DATA
// ============================================
$qr_data = "DALA COLLEGE PAYMENT SUMMARY\n";
$qr_data .= "Student: " . $student['fullname'] . "\n";
$qr_data .= "Reg No: " . ($student['reg_no'] ?? $student['student_id']) . "\n";
$qr_data .= "Level: " . $student_level . "\n";
$qr_data .= "Total Paid: N" . number_format($total_paid, 2) . "\n";
$qr_data .= "Date: " . date('Y-m-d H:i:s');

$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qr_data);

// ============================================
// PHOTO PATH
// ============================================
$photo_path = "images/default-avatar.png";
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
    <title>Payment Summary - <?php echo htmlspecialchars($student['fullname']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Georgia','Times New Roman',serif; background:#f0f4f8; padding:20px; }
        
        .receipt-container {
            max-width: 850px;
            margin: 0 auto;
            background: white;
            border: 3px solid #0d2818;
            border-radius: 8px;
            padding: 30px 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            position: relative;
            overflow: hidden;
        }
        
        .receipt-container::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 60%;
            height: 60%;
            background: url('images/dala-logo.png') no-repeat center center;
            background-size: contain;
            opacity: 0.04;
            pointer-events: none;
            z-index: 0;
        }
        
        .receipt-container > * { position: relative; z-index: 1; }
        
        /* HEADER */
        .header {
            text-align: center;
            border-bottom: 3px double #0d2818;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header .logo-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-bottom: 10px;
        }
        .header .logo-row img {
            width: 90px;
            height: 90px;
            object-fit: contain;
        }
        .header .title-group h1 {
            font-size: 1.5rem;
            font-weight: 900;
            color: #0d2818;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .header .title-group .sub {
            font-size: 0.85rem;
            color: #2e7d32;
            font-weight: 700;
            letter-spacing: 3px;
            margin-top: 3px;
        }
        .header .title-group .motto {
            font-size: 0.75rem;
            color: #c62828;
            font-style: italic;
            font-weight: 600;
            margin-top: 3px;
        }
        .header .contact-info {
            font-size: 0.72rem;
            color: #1976d2;
            margin-top: 5px;
            font-weight: 600;
        }
        
        /* TITLE */
        .receipt-title {
            text-align: center;
            font-size: 1.1rem;
            font-weight: 800;
            color: #ffffff;
            background: linear-gradient(90deg, #2e7d32, #1b5e20);
            padding: 10px 0;
            border-radius: 4px;
            letter-spacing: 3px;
            margin-bottom: 20px;
            text-transform: uppercase;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        /* STUDENT SECTION */
        .student-section {
            display: grid;
            grid-template-columns: 1fr 110px;
            gap: 20px;
            margin-bottom: 20px;
            padding: 15px 20px;
            background: #f8faf8;
            border-radius: 8px;
            border-left: 5px solid #2e7d32;
        }
        .student-info .row {
            display: flex;
            padding: 5px 0;
            border-bottom: 1px dotted #ccc;
            font-size: 0.9rem;
        }
        .student-info .row:last-child { border-bottom: none; }
        .student-info .label {
            font-weight: 700;
            min-width: 130px;
            color: #0d2818;
        }
        .student-info .value {
            color: #1a2e1a;
            font-weight: 600;
        }
        .student-photo {
            width: 110px;
            height: 130px;
            border: 3px solid #2e7d32;
            border-radius: 6px;
            overflow: hidden;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .student-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* TABLE */
        .payments-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .payments-table th {
            background: #0d2818;
            color: white;
            padding: 10px 8px;
            font-size: 0.78rem;
            text-transform: uppercase;
            text-align: left;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .payments-table td {
            padding: 9px 8px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 0.85rem;
        }
        .payments-table tr:nth-child(even) td { background: #f8faf8; }
        .payments-table .amount-col {
            text-align: right;
            font-weight: 700;
            color: #0d2818;
        }
        .badge-paid {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        
        /* TOTAL */
        .total-section {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 2px solid #2e7d32;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 0.95rem;
        }
        .total-row.grand {
            border-top: 2px solid #2e7d32;
            margin-top: 8px;
            padding-top: 10px;
            font-size: 1.2rem;
            font-weight: 900;
        }
        .total-row.grand .amount { color: #1b5e20; }
        .total-row.outstanding .amount { color: #c62828; }
        
        /* QR + SIGNATURE */
        .bottom-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px dashed #0d2818;
        }
        .qr-section { text-align: center; }
        .qr-section img {
            width: 130px;
            height: 130px;
            border: 2px solid #0d2818;
            border-radius: 8px;
            padding: 5px;
            background: white;
        }
        .qr-section .qr-label {
            font-size: 0.7rem;
            color: #666;
            margin-top: 5px;
            font-style: italic;
        }
        .signature-section {
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }
        .signature-line {
            border-top: 2px solid #0d2818;
            padding-top: 5px;
            margin-top: 60px;
            font-size: 0.85rem;
            font-weight: 700;
            color: #0d2818;
            text-transform: uppercase;
        }
        .signature-sub {
            font-size: 0.7rem;
            color: #666;
            margin-top: 3px;
        }
        
        /* FOOTER */
        .footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid #ccc;
            font-size: 0.72rem;
            color: #666;
            font-style: italic;
        }
        
        /* BUTTONS */
        .action-buttons {
            text-align: center;
            margin-top: 25px;
            max-width: 850px;
            margin-left: auto;
            margin-right: auto;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            margin: 5px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-print { background: linear-gradient(135deg, #2e7d32, #1b5e20); color: white; }
        .btn-print:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(46,125,50,0.3); }
        .btn-back { background: #f0f4f8; color: #0d2818; }
        .btn-back:hover { background: #e0e8e0; }
        
        .no-payments {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .no-payments i {
            font-size: 4rem;
            color: #dce8dc;
            display: block;
            margin-bottom: 15px;
        }
        
       @media print {
    /* Goge duk wani abu da ba a so a print */
    body {
        background: white !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    
    /* Boye buttons da navigation */
    .no-print,
    .action-buttons,
    nav,
    button,
    footer {
        display: none !important;
        visibility: hidden !important;
    }
    
    /* Receipt container — A4 daidai */
    .receipt-container {
        box-shadow: none !important;
        margin: 0 !important;
        max-width: 100% !important;
        width: 100% !important;
        border: 2px solid #0d2818 !important;
        border-radius: 0 !important;
        page-break-inside: avoid !important;
        padding: 10mm !important;
    }
    
    /* Tabbatar launuka suna nunawa */
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    
    /* QR Code da Photo su bayyana */
    .qr-section img,
    .student-photo img,
    .header .logo-row img {
        display: block !important;
        visibility: visible !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    
    /* Page setup */
    @page {
        size: A4 portrait;
        margin: 10mm;
    }
}
    </style>
</head>
<body>

<?php if (empty($all_payments)): ?>
    <div class="receipt-container" style="text-align:center;">
        <div class="no-payments">
            <i class="fas fa-receipt"></i>
            <h2 style="color:#c62828;">No Payments Found</h2>
            <p>Babu wani payment da ka yi tukuna.</p>
            <a href="student_payments.php" class="btn btn-print" style="margin-top:20px; display:inline-block;">
                <i class="fas fa-credit-card"></i> Go to Payments
            </a>
        </div>
    </div>
<?php else: ?>

<div class="receipt-container">
    
    <!-- HEADER -->
    <div class="header">
        <div class="logo-row">
            <img src="images/dala-logo.png" alt="Dala College Logo" onerror="this.style.display='none'">
            <div class="title-group">
                <h1>Dala College of Education, Kano</h1>
                <div class="sub">KANO STATE - NIGERIA</div>
                <div class="motto">Knowledge, Excellence &amp; Success</div>
            </div>
        </div>
        <div class="contact-info">
            🌐 www.dalacoe.edu.ng &nbsp;|&nbsp; 📧 info@dalacoe.edu.ng
        </div>
    </div>
    
    <!-- TITLE -->
    <div class="receipt-title">
        Payment Summary Receipt
    </div>
    
    <!-- STUDENT INFO + PHOTO -->
    <div class="student-section">
        <div class="student-info">
            <div class="row">
                <span class="label">Student Name:</span>
                <span class="value"><?php echo htmlspecialchars($student['fullname']); ?></span>
            </div>
            <div class="row">
                <span class="label">Registration No:</span>
                <span class="value"><?php echo htmlspecialchars($student['reg_no'] ?? $student['student_id']); ?></span>
            </div>
            <div class="row">
                <span class="label">Combination:</span>
                <span class="value"><?php echo htmlspecialchars($student['combination'] ?? $student['course']); ?></span>
            </div>
            <div class="row">
                <span class="label">Programme:</span>
                <span class="value"><?php echo htmlspecialchars($student['programme']); ?></span>
            </div>
            <div class="row">
                <span class="label">Level:</span>
                <span class="value"><?php echo htmlspecialchars($student_level); ?></span>
            </div>
            <div class="row">
                <span class="label">Session:</span>
                <span class="value"><?php echo htmlspecialchars($student_session); ?></span>
            </div>
        </div>
        <div class="student-photo">
            <img src="<?php echo $photo_path; ?>" alt="Student Photo" 
                 onerror="this.src='https://via.placeholder.com/110x130/cccccc/333333?text=PHOTO'">
        </div>
    </div>
    
    <!-- PAYMENTS TABLE -->
    <table class="payments-table">
        <thead>
            <tr>
                <th style="width:40px;">#</th>
                <th>Payment Item</th>
                <th style="width:110px;">Reference</th>
                <th style="width:100px;">Date Paid</th>
                <th style="width:110px; text-align:right;">Amount</th>
                <th style="width:70px; text-align:center;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $i = 1; 
            foreach ($all_payments as $p): 
                $item_name = $p['item_name'] ?? $p['payment_type'] ?? 'Payment';
                $ref = $p['reference_no'] ?? '—';
                $date_paid = $p['paid_at'] ? date('d-M-Y', strtotime($p['paid_at'])) : ($p['payment_date'] ? date('d-M-Y', strtotime($p['payment_date'])) : '—');
            ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><strong><?php echo htmlspecialchars($item_name); ?></strong></td>
                <td style="font-size:0.72rem; font-family:monospace;"><?php echo htmlspecialchars($ref); ?></td>
                <td style="font-size:0.8rem;"><?php echo $date_paid; ?></td>
                <td class="amount-col">₦<?php echo number_format($p['amount'], 2); ?></td>
                <td style="text-align:center;">
                    <span class="badge-paid">Paid</span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- TOTAL -->
    <div class="total-section">
        <div class="total-row">
            <span><strong>Total Required (based on level):</strong></span>
            <span class="amount">₦<?php echo number_format($total_required, 2); ?></span>
        </div>
        <div class="total-row grand">
            <span><strong>TOTAL PAID:</strong></span>
            <span class="amount">₦<?php echo number_format($total_paid, 2); ?></span>
        </div>
        <div class="total-row outstanding">
            <span><strong>Outstanding Balance:</strong></span>
            <span class="amount">
                <?php if ($total_outstanding <= 0): ?>
                    ₦0.00 (Fully Paid)
                <?php else: ?>
                    ₦<?php echo number_format($total_outstanding, 2); ?>
                <?php endif; ?>
            </span>
        </div>
    </div>
    
    <!-- QR + SIGNATURE -->
    <div class="bottom-section">
        <div class="qr-section">
            <img src="<?php echo $qr_url; ?>" alt="QR Code">
            <div class="qr-label">Scan to verify this receipt</div>
        </div>
        <div class="signature-section">
            <div class="signature-line">Bursar's Signature &amp; Stamp</div>
            <div class="signature-sub">Date: <?php echo date('d-M-Y'); ?></div>
        </div>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>This is a computer-generated receipt. No signature is required.</p>
        <p>Dala College of Education, Kano &mdash; <?php echo date('Y'); ?></p>
        <p style="margin-top:5px; font-size:0.65rem;">Generated: <?php echo date('d-M-Y H:i:s'); ?></p>
    </div>
    
</div>

<div class="action-buttons no-print">
    <a href="student_dashboard.php" class="btn btn-back">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
    <button type="button" onclick="printReceipt()" class="btn btn-print" id="printBtn">
        <i class="fas fa-print"></i> Print Summary Receipt
    </button>
</div>

<script>
function printReceipt() {
    // Duba idan browser yana goyon bayan print
    if (typeof window.print === 'function') {
        // Ɗan jinkiri kaɗan kafin print
        setTimeout(function() {
            window.print();
        }, 100);
    } else {
        alert('Printing is not supported in this browser. Please use Ctrl+P to print.');
    }
}

// Haka kuma idan an danna Ctrl+P, tabbatar an nuna print styles
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        printReceipt();
    }
});
</script>
<?php endif; ?>


</body>
</html>