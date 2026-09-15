<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

$filter_type = isset($_GET['card_type']) ? mysqli_real_escape_string($conn, $_GET['card_type']) : 'application';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'unused';

// ============================================
// ACADEMIC SESSION
// ============================================
$academic_session = '2026/2027';

$where = "WHERE 1=1";
if (!empty($filter_type)) $where .= " AND card_type = '$filter_type'";
if (!empty($filter_status)) $where .= " AND status = '$filter_status'";

$cards = mysqli_query($conn, "SELECT * FROM scratch_cards $where ORDER BY id ASC LIMIT 100");

$type_labels = [
    'application' => 'Application Form',
    'course_registration' => 'Course Registration',
    'exam_card' => 'Exam Card',
    'posting_letter' => 'Posting Letter (T.P)',
    'acceptance_fee' => 'Acceptance Fee',
    'receipt' => 'Print Receipt',
];
$type_label = $type_labels[$filter_type] ?? 'Scratch Card';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Scratch Cards - Dala College</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #f0f4f8;
            padding: 20px;
        }
        
        /* ============================================ */
        /* PRINT CONTROLS */
        /* ============================================ */
        .print-controls {
            max-width: 1200px;
            margin: 0 auto 20px;
            background: white;
            padding: 20px;
            border-radius: 12px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .print-controls h1 { color: #0d2818; font-size: 1.3rem; margin-right: auto; }
        .print-controls .btn {
            padding: 10px 20px;
            background: #2e7d32;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }
        .print-controls .btn:hover { background: #1b5e20; }
        
        /* ============================================ */
        /* CARDS GRID - 5 cards a jere */
        /* ============================================ */
        .cards-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
        }
        
        /* ============================================ */
        /* SCRATCH CARD */
        /* ============================================ */
        .scratch-card {
            background: #ffffff;
            border: 1px dashed #0d2818;
            border-radius: 6px;
            padding: 5px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            page-break-inside: avoid;
            font-size: 5px;
        }
        
        .scratch-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #0d2818 0%, #2e7d32 50%, #ffd54f 100%);
        }
        
        /* HEADER */
        .card-header {
            display: flex;
            align-items: center;
            gap: 4px;
            padding-bottom: 3px;
            border-bottom: 1px solid #0d2818;
            margin-bottom: 4px;
        }
        .card-header .logo {
            width: 18px;
            height: 18px;
            object-fit: contain;
            border-radius: 50%;
        }
        .card-header .college-name {
            flex: 1;
        }
        .card-header .college-name h3 {
            font-size: 6px;
            color: #0d2818;
            font-weight: 900;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            line-height: 1.1;
        }
        .card-header .college-name p {
            font-size: 4.5px;
            color: #2e7d32;
            font-weight: 700;
            font-style: italic;
        }
        .card-header .branch-info {
            text-align: right;
            font-size: 4.5px;
            color: #6a8f6a;
            line-height: 1.2;
        }
        .card-header .branch-info .branch-name {
            font-weight: 800;
            color: #0d2818;
            font-size: 5px;
        }
        
        /* SERIAL BADGE */
        .serial-badge {
            position: absolute;
            top: 4px;
            right: 4px;
            background: #0d2818;
            color: #ffd54f;
            font-size: 4.5px;
            font-weight: 800;
            padding: 1px 4px;
            border-radius: 3px;
            letter-spacing: 0.3px;
        }
        
        /* PIN SECTION */
        .pin-section {
            text-align: center;
            padding: 4px 3px;
            background: linear-gradient(135deg, #f8faf8 0%, #e8f5e9 100%);
            border-radius: 4px;
            border: 1px solid #c8e6c9;
            margin: 3px 0;
        }
        .pin-section .pin-label {
            font-size: 4px;
            color: #6a8f6a;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1px;
        }
        .pin-section .pin-value {
            font-size: 12px;
            font-weight: 900;
            color: #0d2818;
            letter-spacing: 1px;
            font-family: 'Courier New', monospace;
            text-shadow: 1px 1px 0 #ffd54f;
        }
        
        /* CARD CODE SECTION */
        .card-code-section {
            background: #0d2818;
            color: white;
            padding: 3px 4px;
            border-radius: 3px;
            text-align: center;
            margin: 3px 0;
        }
        .card-code-section .code-label {
            font-size: 3.5px;
            color: #a5d6a7;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1px;
        }
        .card-code-section .code-value {
            font-size: 7px;
            font-weight: 900;
            color: #ffd54f;
            letter-spacing: 0.5px;
            font-family: 'Courier New', monospace;
        }
        
        /* DETAILS */
        .card-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1px 4px;
            font-size: 4.5px;
            margin: 3px 0;
        }
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 1px 0;
            border-bottom: 1px dotted #dce8dc;
        }
        .detail-item .label {
            color: #6a8f6a;
            font-weight: 600;
        }
        .detail-item .value {
            color: #0d2818;
            font-weight: 800;
        }
        
        /* INSTRUCTIONS */
        .card-instructions {
            background: #f8faf8;
            border: 1px solid #c8e6c9;
            border-radius: 3px;
            padding: 3px 4px;
            margin-top: 3px;
            font-size: 4px;
            color: #1a2e1a;
            line-height: 1.3;
        }
        .card-instructions .instructions-title {
            font-size: 4.5px;
            font-weight: 900;
            color: #0d2818;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 2px;
            padding-bottom: 1px;
            border-bottom: 1px solid #c8e6c9;
        }
        .card-instructions ol {
            padding-left: 8px;
            margin-bottom: 2px;
        }
        .card-instructions ol li {
            margin-bottom: 0.5px;
        }
        .card-instructions ol li strong {
            color: #0d2818;
        }
        .card-instructions .instructions-note {
            background: #fff3e0;
            color: #e65100;
            padding: 2px 3px;
            border-radius: 2px;
            font-weight: 700;
            font-size: 3.5px;
            text-align: center;
            border-left: 1.5px solid #ff9800;
        }
        
        /* FOOTER */
        .card-footer {
            text-align: center;
            font-size: 4px;
            color: #6a8f6a;
            padding-top: 2px;
            border-top: 1px dashed #dce8dc;
            margin-top: 2px;
        }
        .card-footer .website {
            color: #2e7d32;
            font-weight: 800;
            font-size: 4.5px;
        }
        
        /* WATERMARK */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 25px;
            font-weight: 900;
            color: rgba(13, 40, 24, 0.04);
            pointer-events: none;
            white-space: nowrap;
            letter-spacing: 3px;
        }
        
        /* ============================================ */
        /* PRINT - 10 CARDS A A4 */
        /* ============================================ */
        @media print {
            body { background: white; padding: 0; margin: 0; }
            .print-controls { display: none !important; }
            .cards-grid {
                display: grid;
                grid-template-columns: repeat(5, 1fr);
                gap: 3px;
                padding: 3px;
            }
            .scratch-card {
                box-shadow: none;
                border: 1px dashed #000;
                page-break-inside: avoid;
            }
            .scratch-card::before {
                background: #0d2818 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .serial-badge,
            .card-code-section {
                background: #0d2818 !important;
                color: #ffd54f !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .pin-section {
                background: #e8f5e9 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            @page {
                size: A4;
                margin: 4mm;
            }
        }
        
        @media (max-width: 768px) {
            .cards-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<div class="print-controls">
    <h1>🎫 Scratch Cards — <?php echo htmlspecialchars($type_label); ?></h1>
    <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap;">
        <select name="card_type" style="padding:10px; border:2px solid #dce8dc; border-radius:8px;">
            <?php foreach ($type_labels as $key => $label): ?>
                <option value="<?php echo $key; ?>" <?php echo ($filter_type == $key) ? 'selected' : ''; ?>>
                    <?php echo $label; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="status" style="padding:10px; border:2px solid #dce8dc; border-radius:8px;">
            <option value="unused" <?php echo ($filter_status == 'unused') ? 'selected' : ''; ?>>Unused</option>
            <option value="used" <?php echo ($filter_status == 'used') ? 'selected' : ''; ?>>Used</option>
            <option value="" <?php echo ($filter_status == '') ? 'selected' : ''; ?>>All</option>
        </select>
        <button type="submit" class="btn">🔍 Filter</button>
    </form>
    <button onclick="window.print()" class="btn">🖨️ Print</button>
    <a href="admin_scratch_cards.php" class="btn" style="background:#6a8f6a;">← Back</a>
</div>

<div class="cards-grid">
    <?php if ($cards && mysqli_num_rows($cards) > 0): ?>
        <?php while ($card = mysqli_fetch_assoc($cards)): ?>
        <div class="scratch-card">
            <div class="watermark">DALA</div>
            
            <div class="serial-badge">S/N: <?php echo htmlspecialchars($card['serial_no']); ?></div>
            
            <!-- HEADER -->
            <div class="card-header">
                <?php if (file_exists('images/dala-logo.png')): ?>
                    <img src="images/dala-logo.png" class="logo" alt="Logo">
                <?php else: ?>
                    <div class="logo" style="display:flex;align-items:center;justify-content:center;background:#e8f5e9;font-size:10px;">🎓</div>
                <?php endif; ?>
                <div class="college-name">
                    <h3>DALA COLLEGE OF EDUCATION, KANO</h3>
                    <p>Knowledge, Excellence & Success</p>
                </div>
                <div class="branch-info">
                    <div class="branch-name">Branch: Kano (Main)</div>
                    <div>NCCE Accredited</div>
                </div>
            </div>
            
            <!-- PIN -->
            <div class="pin-section">
                <div class="pin-label">Scratch Card PIN</div>
                <div class="pin-value"><?php echo htmlspecialchars($card['pin']); ?></div>
            </div>
            
            <!-- CARD CODE -->
            <div class="card-code-section">
                <div class="code-label">Card Code (Use this to verify)</div>
                <div class="code-value"><?php echo htmlspecialchars($card['card_code']); ?></div>
            </div>
            
            <!-- DETAILS -->
            <div class="card-details">
                <div class="detail-item">
                    <span class="label">Type:</span>
                    <span class="value"><?php echo htmlspecialchars($type_label); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Value:</span>
                    <span class="value">₦<?php echo number_format($card['usage_limit'] ?? 0); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Session:</span>
                    <span class="value"><?php echo $academic_session; ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Expiry:</span>
                    <span class="value"><?php echo !empty($card['expiry_date']) ? date('m/Y', strtotime($card['expiry_date'])) : '-'; ?></span>
                </div>
            </div>
            
            <!-- INSTRUCTIONS -->
            <div class="card-instructions">
                <div class="instructions-title">📖 HOW TO USE THIS CARD</div>
                <ol>
                    <li>Visit <strong>www.dalacoe.edu.ng</strong></li>
                    <li>Login with your <strong>Username &amp; Password</strong></li>
                    <li>Click on the Service you want (e.g. Course Registration)</li>
                    <li>Enter this <strong>PIN: <?php echo htmlspecialchars($card['pin']); ?></strong></li>
                    <li>Or enter the full <strong>Card Code: <?php echo htmlspecialchars($card['card_code']); ?></strong></li>
                    <li>Click <strong>Verify Card</strong></li>
                    <li>The service will open for you automatically</li>
                </ol>
                <div class="instructions-note">
                    ⚠️ Keep this card safe. Do not share your PIN with anyone.
                </div>
            </div>
            
            <!-- FOOTER -->
            <div class="card-footer">
                <div>Please visit <span class="website">www.dalacoe.edu.ng</span></div>
            </div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div style="grid-column: 1/-1; text-align:center; padding:40px; color:#6a8f6a;">
            <p>Babu cards da suka dace da wannan filter ɗin.</p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>