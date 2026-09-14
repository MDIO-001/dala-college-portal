<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['user_id'];

// Get student data
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    die("Student not found!");
}

// Get application status
$app_stmt = $conn->prepare("SELECT status FROM applications WHERE student_id = ? ORDER BY id DESC LIMIT 1");
$app_stmt->bind_param("i", $student_id);
$app_stmt->execute();
$app_result = $app_stmt->get_result();
$application = $app_result->fetch_assoc();
$app_status = $application['status'] ?? 'pending';

// Generate Receipt Number
$receipt_no = 'RCP/' . date('Y') . '/' . str_pad($student['id'], 6, '0', STR_PAD_LEFT) . '/' . time();

// Generate QR Code
$qr_data = "RECEIPT: " . $receipt_no . " | " . $student['fullname'] . " | " . $student['reg_no'];
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qr_data);

// Photo path
$photo_file = '';
if (!empty($student['photo'])) {
    if (file_exists('uploads/students/' . $student['photo'])) {
        $photo_file = 'uploads/students/' . $student['photo'];
    } elseif (file_exists('uploads/' . $student['photo'])) {
        $photo_file = 'uploads/' . $student['photo'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Receipt - Dala College</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Times New Roman', serif;
            background: #f0f4f8;
            padding: 40px 20px;
        }
        
        .receipt-container {
            max-width: 750px;
            margin: 0 auto;
            background: white;
            padding: 40px 50px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border-radius: 8px;
            border: 3px double #0d2818;
            position: relative;
            overflow: hidden;
        }
        
        /* WATERMARK LOGO */
        .receipt-container::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 400px;
            height: 400px;
            background-image: url('images/dala-logo.png');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            opacity: 0.05;
            z-index: 0;
            pointer-events: none;
        }
        
        .receipt-container > * {
            position: relative;
            z-index: 1;
        }
        
        /* HEADER */
        .receipt-header {
            text-align: center;
            border-bottom: 3px double #0d2818;
            padding-bottom: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .receipt-header .header-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            flex-shrink: 0;
        }
        
        .receipt-header .header-center {
            flex: 1;
            text-align: left;
        }
        
        .receipt-header h1 {
            font-size: 22px;
            color: #0d2818;
            letter-spacing: 2px;
            font-weight: 900;
            margin-bottom: 3px;
        }
        
        .receipt-header h2 {
            font-size: 14px;
            color: #2e7d32;
            font-weight: 600;
            margin-bottom: 3px;
        }
        
        .receipt-header .accreditation {
            font-size: 11px;
            color: #c62828;
            font-weight: 700;
            margin-bottom: 3px;
        }
        
        .receipt-header .contact-info {
            font-size: 11px;
            color: #0d2818;
            font-weight: 600;
        }
        
        .receipt-header .receipt-info {
            display: flex;
            justify-content: space-between;
            margin-top: 12px;
            font-size: 12px;
            color: #6a8f6a;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .receipt-header .receipt-info span {
            background: #f8faf8;
            padding: 4px 12px;
            border-radius: 4px;
            border-left: 3px solid #2e7d32;
        }
        
        /* STUDENT SECTION */
        .student-section {
            display: flex;
            gap: 25px;
            align-items: flex-start;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8faf8;
            border-radius: 8px;
            border: 1px solid #dce8dc;
        }
        
        .student-photo {
            width: 100px;
            height: 120px;
            border: 3px solid #2e7d32;
            overflow: hidden;
            border-radius: 4px;
            flex-shrink: 0;
            background: #e8f5e9;
        }
        
        .student-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .student-photo .no-photo {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            font-size: 35px;
            color: #999;
        }
        
        .student-details { flex: 1; }
        
        .student-details table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .student-details td {
            padding: 5px 8px;
            border-bottom: 1px solid #e8f5e9;
            font-size: 13px;
        }
        
        .student-details td.label {
            font-weight: bold;
            color: #0d2818;
            width: 35%;
        }
        
        /* PAYMENT DETAILS */
        .payment-details {
            padding: 15px;
            background: #e8f5e9;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #2e7d32;
        }
        
        .payment-details h3 {
            color: #0d2818;
            margin-bottom: 10px;
            font-size: 15px;
            border-bottom: 2px solid #2e7d32;
            padding-bottom: 5px;
        }
        
        .payment-details table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .payment-details td {
            padding: 6px 8px;
            font-size: 13px;
            border-bottom: 1px solid #c8e6c9;
        }
        
        .payment-details td.label {
            font-weight: bold;
            color: #0d2818;
            width: 40%;
        }
        
        .payment-details tr:last-child td {
            border-bottom: none;
        }
        
        /* TOTAL AMOUNT */
        .total-amount {
            background: #0d2818;
            color: #ffd54f;
            padding: 12px 15px;
            border-radius: 8px;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin: 15px 0;
            letter-spacing: 1px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        /* QR SECTION */
        .qr-section {
            text-align: center;
            padding: 15px;
            background: #f8faf8;
            border-radius: 8px;
            border: 2px dashed #2e7d32;
            margin: 15px 0;
        }
        
        .qr-section img {
            width: 130px;
            height: 130px;
        }
        
        .qr-section p {
            font-size: 11px;
            color: #6a8f6a;
            margin-top: 5px;
            font-weight: 600;
        }
        
        /* FOOTER */
        .receipt-footer {
            margin-top: 25px;
            padding-top: 15px;
            border-top: 2px solid #e8f5e9;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            font-size: 12px;
        }
        
        .receipt-footer .signature {
            text-align: center;
        }
        
        .receipt-footer .signature .line {
            display: inline-block;
            width: 150px;
            border-bottom: 1.5px solid #0d2818;
            margin-top: 25px;
        }
        
        .receipt-footer .signature p {
            margin: 3px 0;
        }
        
        /* BUTTONS */
        .btn-print {
            display: inline-block;
            padding: 12px 30px;
            background: #2e7d32;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
        }
        
        .btn-print:hover { background: #1b5e20; }
        
        .btn-back {
            display: inline-block;
            padding: 12px 25px;
            background: #6a8f6a;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            margin-right: 10px;
        }
        
        .btn-back:hover { background: #4a6a4a; }
        
        .actions {
            text-align: center;
            margin-top: 20px;
            max-width: 750px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* ============================================ */
        /* PRINT STYLES - A4 PORTRAIT */
        /* ============================================ */
        @media print {
            .no-print { display: none !important; }
            
            body { 
                background: white; 
                padding: 0; 
                margin: 0;
                font-size: 11px;
            }
            
            .receipt-container { 
                box-shadow: none; 
                padding: 15px; 
                border: 2px solid #000;
                border-radius: 0;
                max-width: 100%;
                margin: 0;
            }
            
            .receipt-container::before {
                opacity: 0.08 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .receipt-header {
                padding-bottom: 10px;
                margin-bottom: 15px;
                border-bottom: 3px double #000;
            }
            
            .receipt-header .header-logo {
                width: 70px;
                height: 70px;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .receipt-header h1 {
                font-size: 1.3rem;
                color: #000;
            }
            
            .receipt-header h2 {
                font-size: 0.8rem;
            }
            
            .receipt-header .accreditation {
                font-size: 0.7rem;
            }
            
            .receipt-header .contact-info {
                font-size: 0.7rem;
            }
            
            .receipt-header .receipt-info {
                font-size: 0.75rem;
            }
            
            .receipt-header .receipt-info span {
                background: #f0f0f0 !important;
                border-left: 3px solid #000;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .student-section {
                padding: 10px;
                background: #f5f5f5 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .student-photo {
                width: 80px;
                height: 100px;
                border: 2px solid #000;
            }
            
            .student-details td {
                font-size: 0.8rem;
                padding: 3px 6px;
            }
            
            .payment-details {
                background: #f5f5f5 !important;
                padding: 10px;
                border-left: 4px solid #000;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .payment-details h3 {
                font-size: 0.9rem;
            }
            
            .payment-details td {
                font-size: 0.8rem;
                padding: 4px 6px;
            }
            
            .total-amount {
                background: #000 !important;
                color: #fff !important;
                font-size: 1rem;
                padding: 8px;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .qr-section {
                padding: 10px;
                background: #f5f5f5 !important;
                border: 1px dashed #000;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .qr-section img {
                width: 100px;
                height: 100px;
            }
            
            .receipt-footer {
                font-size: 0.75rem;
                margin-top: 15px;
                padding-top: 10px;
                border-top: 1.5px solid #000;
            }
            
            .receipt-footer .signature .line {
                width: 120px;
                border-bottom: 1.5px solid #000;
            }
            
            @page { 
                size: A4 portrait; 
                margin: 10mm; 
            }
        }
        
        @media (max-width: 600px) {
            .receipt-container { padding: 20px; }
            .student-section { flex-direction: column; align-items: center; text-align: center; }
            .student-photo { width: 80px; height: 100px; }
            .receipt-footer { flex-direction: column; align-items: center; gap: 15px; }
            .receipt-header { flex-direction: column; text-align: center; }
            .receipt-header .header-center { text-align: center; }
        }
    </style>
</head>
<body>

<div class="receipt-container" id="printArea">
    
    <!-- HEADER -->
    <div class="receipt-header">
        <img src="images/dala-logo.png" alt="Dala College Logo" class="header-logo" onerror="this.style.display='none'">
        <div class="header-center">
            <h1>DALA COLLEGE OF EDUCATION, KANO</h1>
            <h2>Knowledge, Excellence &amp; Success</h2>
            <div class="accreditation">✅ Accredited by National Commission for Colleges of Education (NCCE), Abuja</div>
            <div class="contact-info">🌐 www.dalacollege.edu.ng | ✉️ dalacollegekano@gmail.com</div>
        </div>
    </div>

    <!-- RECEIPT INFO -->
    <div class="receipt-info" style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px; margin-bottom:15px; padding:8px 15px; background:#f8faf8; border-radius:8px; font-size:12px;">
        <span>🧾 Receipt No: <strong><?php echo $receipt_no; ?></strong></span>
        <span>📅 Date: <?php echo date('d/m/Y H:i'); ?></span>
        <span>📌 Status: <strong style="color:#2e7d32;">PAID</strong></span>
    </div>

    <!-- STUDENT SECTION -->
    <div class="student-section">
        <div class="student-photo">
            <?php if (!empty($photo_file) && file_exists($photo_file)): ?>
                <img src="<?php echo $photo_file; ?>" alt="Student Photo">
            <?php else: ?>
                <div class="no-photo">📷</div>
            <?php endif; ?>
        </div>
        <div class="student-details">
            <table>
                <tr>
                    <td class="label">Full Name</td>
                    <td><strong><?php echo htmlspecialchars($student['fullname']); ?></strong></td>
                </tr>
                <tr>
                    <td class="label">Student ID</td>
                    <td><?php echo htmlspecialchars($student['student_id'] ?? $student['reg_no'] ?? 'Pending'); ?></td>
                </tr>
                <tr>
                    <td class="label">Registration No</td>
                    <td><?php echo htmlspecialchars($student['reg_no'] ?? 'Pending'); ?></td>
                </tr>
                <tr>
                    <td class="label">Programme</td>
                    <td><?php echo htmlspecialchars($student['programme'] ?? 'NCE'); ?></td>
                </tr>
                <tr>
                    <td class="label">Course</td>
                    <td><?php echo htmlspecialchars($student['course'] ?? 'Not Selected'); ?></td>
                </tr>
                <tr>
                    <td class="label">Phone</td>
                    <td><?php echo htmlspecialchars($student['phone'] ?? 'Not provided'); ?></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- PAYMENT DETAILS -->
    <div class="payment-details">
        <h3>💰 Payment Details</h3>
        <table>
            <tr>
                <td class="label">Description</td>
                <td>School Fees - <?php echo htmlspecialchars($student['programme'] ?? 'NCE'); ?> Programme</td>
            </tr>
            <tr>
                <td class="label">Academic Session</td>
                <td>2025/2026</td>
            </tr>
            <tr>
                <td class="label">Semester</td>
                <td>First Semester</td>
            </tr>
            <tr>
                <td class="label">Amount</td>
                <td><strong style="font-size:15px;">₦75,000.00</strong></td>
            </tr>
            <tr>
                <td class="label">Payment Method</td>
                <td>Online Payment</td>
            </tr>
            <tr>
                <td class="label">Transaction ID</td>
                <td>TXN<?php echo date('Ymd') . str_pad($student['id'], 4, '0', STR_PAD_LEFT); ?></td>
            </tr>
        </table>
    </div>

    <!-- TOTAL AMOUNT -->
    <div class="total-amount">
        Total Paid: ₦75,000.00
    </div>

    <!-- QR CODE -->
    <div class="qr-section">
        <img src="<?php echo $qr_url; ?>" alt="QR Code">
        <p>Scan to verify receipt | <?php echo $receipt_no; ?></p>
    </div>

    <!-- FOOTER -->
    <div class="receipt-footer">
        <div>
            <p><strong>Bursar's Office</strong></p>
            <p>Dala College of Education</p>
            <p>Kano State, Nigeria</p>
        </div>
        <div class="signature">
            <div class="line"></div>
            <p><strong>Bursar</strong></p>
        </div>
        <div class="signature">
            <div class="line"></div>
            <p><strong>Registrar</strong></p>
        </div>
    </div>
</div>

<!-- ACTIONS -->
<div class="actions no-print">
    <a href="student_dashboard.php" class="btn-back">← Back to Dashboard</a>
    <button onclick="window.print()" class="btn-print">🖨️ Print / Download PDF</button>
</div>

<script>
    window.onload = function() {
        if (window.location.search.includes('print=1')) {
            window.print();
        }
    }
</script>

</body>
</html>