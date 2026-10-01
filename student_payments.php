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
// NEMO ITEMS ƊIN DA SUKE DACE DA LEVEL
// ============================================
if ($student_programme == 'DEGREE' || $student_programme == 'DEG') {
    // Degree: Nemi items ɗin da suka dace da Degree
    $items_query = "
        SELECT * FROM payment_items 
        WHERE is_active = 1
        AND (
            level = '$student_level' 
            OR (
                level IS NULL 
                AND item_code NOT LIKE '%NCE%'
            )
        )
        AND (session = '$student_session' OR session IS NULL)
        ORDER BY 
            FIELD(SUBSTRING(item_code, 1, 3), 'TUI', 'ACC', 'EXM', 'IDC', 'LIB', 'MED', 'SPE'),
            amount DESC
    ";
} elseif ($student_programme == 'NCE') {
    // NCE: Nemi items ɗin da suka dace da NCE
    $items_query = "
        SELECT * FROM payment_items 
        WHERE is_active = 1
        AND (
            level = '$student_level' 
            OR (
                level IS NULL 
                AND item_code NOT LIKE '%DEG%'
            )
        )
        AND (session = '$student_session' OR session IS NULL)
        ORDER BY 
            FIELD(SUBSTRING(item_code, 1, 3), 'TUI', 'ACC', 'EXM', 'TPF', 'IDC', 'LIB', 'MED', 'SPE'),
            amount DESC
    ";
} else {
    // Sauran (Entrepreneurship)
    $items_query = "
        SELECT * FROM payment_items 
        WHERE is_active = 1
        AND (level = '$student_level' OR level IS NULL)
        AND (session = '$student_session' OR session IS NULL)
        ORDER BY id ASC
    ";
}

$items_result = mysqli_query($conn, $items_query);

// ============================================
// TATTAARA ITEMS DA STATUS
// ============================================
$payment_items = [];

while ($row = mysqli_fetch_assoc($items_result)) {
    // Duba idan an riga an biya
    $check = $conn->prepare("SELECT * FROM payments WHERE student_id = ? AND payment_item_id = ? AND status = 'paid' LIMIT 1");
    $check->bind_param("ii", $student_id, $row['id']);
    $check->execute();
    $paid = $check->get_result()->fetch_assoc();
    
    $row['paid'] = $paid ? true : false;
    $row['paid_at'] = $paid['paid_at'] ?? null;
    $row['reference'] = $paid['reference_no'] ?? null;
    
    $payment_items[] = $row;
}

// ============================================
// LISSAFTA TOTALS
// ============================================
$total_required = 0;
$total_paid = 0;
$total_pending = 0;

foreach ($payment_items as $item) {
    if ($item['is_mandatory']) {
        $total_required += $item['amount'];
        if ($item['paid']) {
            $total_paid += $item['amount'];
        } else {
            $total_pending += $item['amount'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Payments - Dala College</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Tahoma,sans-serif; background:#f0f4f8; padding:20px; }
        .container { max-width:1100px; margin:0 auto; }
        
        /* HEADER */
        .page-header {
            background: linear-gradient(135deg, #0d2818, #2e7d32);
            color: white;
            padding: 25px 30px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-header h1 { font-size: 1.5rem; font-weight: 800; display: flex; align-items: center; gap: 12px; }
        .page-header .back-btn {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        .page-header .back-btn:hover { background: rgba(255,255,255,0.25); }
        
        /* STUDENT INFO */
        .student-info {
            background: white;
            padding: 18px 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        .student-info .info-item .label {
            color: #666;
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .student-info .info-item .value {
            color: #0d2818;
            font-weight: 800;
            font-size: 0.95rem;
        }
        
        /* SUMMARY CARDS */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 5px solid #2e7d32;
        }
        .summary-card.danger { border-left-color: #c62828; }
        .summary-card.info { border-left-color: #1976d2; }
        .summary-card .label {
            font-size: 0.8rem;
            color: #666;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .summary-card .amount {
            font-size: 1.6rem;
            font-weight: 800;
            color: #0d2818;
        }
        
        /* PAYMENT SECTION */
        .payment-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .payment-section .section-header {
            background: #f8faf8;
            padding: 18px 25px;
            border-bottom: 2px solid #e0e8e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .payment-section .section-header h2 {
            font-size: 1.15rem;
            color: #0d2818;
            font-weight: 800;
        }
        .payment-section .section-header .session-badge {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
        }
        
        /* PAYMENT TABLE */
        .payment-table {
            width: 100%;
            border-collapse: collapse;
        }
        .payment-table th {
            background: #0d2818;
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .payment-table td {
            padding: 14px 15px;
            border-bottom: 1px solid #e8f0e8;
            font-size: 0.9rem;
            color: #1a2e1a;
            vertical-align: middle;
        }
        .payment-table tr:hover td { background: #f8faf8; }
        .payment-table .amount-col {
            font-weight: 800;
            color: #0d2818;
            text-align: right;
            font-size: 0.95rem;
        }
        
        /* BADGES */
        .badge {
            display: inline-block;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-paid { background: #e8f5e9; color: #2e7d32; }
        .badge-pending { background: #fff3e0; color: #f57c00; }
        .badge-partial { background: #e3f2fd; color: #1976d2; }
        .badge-na { background: #f5f5f5; color: #999; }
        
        /* BUTTONS */
        .btn-pay {
            background: linear-gradient(135deg, #2e7d32, #1b5e20);
            color: white;
            padding: 9px 22px;
            border: none;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.82rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46,125,50,0.3);
        }
        .btn-receipt {
            background: #f0f4f8;
            color: #0d2818;
            padding: 9px 18px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.82rem;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        .btn-receipt:hover { background: #e0e8e0; }
        
        /* TAGS */
        .mandatory-tag {
            display: inline-block;
            background: #c62828;
            color: white;
            font-size: 0.62rem;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 700;
            margin-left: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .optional-tag {
            display: inline-block;
            background: #e3f2fd;
            color: #0d47a1;
            font-size: 0.62rem;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 700;
            margin-left: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* NO PAYMENTS */
        .no-payments {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .no-payments i {
            font-size: 4rem;
            color: #dce8dc;
            display: block;
            margin-bottom: 15px;
        }
        
        /* RESPONSIVE */
        @media (max-width: 600px) {
            .payment-table { font-size: 0.82rem; }
            .payment-table th, .payment-table td { padding: 10px 8px; }
            .summary-card .amount { font-size: 1.3rem; }
            .page-header h1 { font-size: 1.2rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        
        <!-- HEADER -->
        <div class="page-header">
            <h1><i class="fas fa-credit-card"></i> My Payments</h1>
            <a href="student_dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <!-- STUDENT INFO -->
        <div class="student-info">
            <div class="info-item">
                <div class="label">Student Name</div>
                <div class="value"><?php echo htmlspecialchars($student['fullname']); ?></div>
            </div>
            <div class="info-item">
                <div class="label">Registration No</div>
                <div class="value"><?php echo htmlspecialchars($student['reg_no'] ?? $student['student_id']); ?></div>
            </div>
            <div class="info-item">
                <div class="label">Level</div>
                <div class="value"><?php echo htmlspecialchars($student_level); ?></div>
            </div>
            <div class="info-item">
                <div class="label">Programme</div>
                <div class="value"><?php echo htmlspecialchars($student_programme); ?></div>
            </div>
            <div class="info-item">
                <div class="label">Session</div>
                <div class="value"><?php echo htmlspecialchars($student_session); ?></div>
            </div>
        </div>
        
        <!-- SUMMARY CARDS -->
        <div class="summary-grid">
            <div class="summary-card info">
                <div class="label"><i class="fas fa-file-invoice"></i> Total Required</div>
                <div class="amount">₦<?php echo number_format($total_required, 2); ?></div>
            </div>
            <div class="summary-card">
                <div class="label"><i class="fas fa-check-circle"></i> Total Paid</div>
                <div class="amount">₦<?php echo number_format($total_paid, 2); ?></div>
            </div>
            <div class="summary-card danger">
                <div class="label"><i class="fas fa-exclamation-triangle"></i> Outstanding</div>
                <div class="amount">₦<?php echo number_format($total_pending, 2); ?></div>
            </div>
        </div>
        
        <!-- PAYMENT TABLE -->
        <div class="payment-section">
            <div class="section-header">
                <h2><i class="fas fa-list-alt"></i> Payment Items</h2>
                <span class="session-badge"><?php echo htmlspecialchars($student_session); ?> Session</span>
            </div>
            
            <?php if (empty($payment_items)): ?>
                <div class="no-payments">
                    <i class="fas fa-receipt"></i>
                    <h3>No Payment Items Found</h3>
                    <p>Babu wani abu da aka sanya maka na biya a yanzu.</p>
                </div>
            <?php else: ?>
                <table class="payment-table">
                    <thead>
                        <tr>
                            <th style="width:45px;">#</th>
                            <th>Item</th>
                            <th style="width:140px; text-align:right;">Amount</th>
                            <th style="width:130px; text-align:center;">Status</th>
                            <th style="width:180px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($payment_items as $item): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                <?php if ($item['is_mandatory']): ?>
                                    <span class="mandatory-tag">Mandatory</span>
                                <?php else: ?>
                                    <span class="optional-tag">Optional</span>
                                <?php endif; ?>
                                <?php if (!empty($item['description'])): ?>
                                    <div style="font-size:0.75rem; color:#666; margin-top:4px;">
                                        <?php echo htmlspecialchars($item['description']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="amount-col">₦<?php echo number_format($item['amount'], 2); ?></td>
                            <td style="text-align:center;">
                                <?php if ($item['paid']): ?>
                                    <span class="badge badge-paid"><i class="fas fa-check"></i> Paid</span>
                                <?php else: ?>
                                    <span class="badge badge-pending"><i class="fas fa-clock"></i> Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['paid']): ?>
                                    <a href="student_payment_receipt.php?ref=<?php echo urlencode($item['reference']); ?>" class="btn-receipt">
                                        <i class="fas fa-file-invoice"></i> Receipt
                                    </a>
                                <?php else: ?>
                                    <a href="student_payment_checkout.php?item_id=<?php echo $item['id']; ?>" class="btn-pay">
                                        <i class="fas fa-credit-card"></i> Pay Now
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
    </div>
</body>
</html>