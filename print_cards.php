<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

$filter_type = isset($_GET['card_type']) ? mysqli_real_escape_string($conn, $_GET['card_type']) : 'application';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'unused';

$where = "WHERE 1=1";
if (!empty($filter_type)) $where .= " AND card_type = '$filter_type'";
if (!empty($filter_status)) $where .= " AND status = '$filter_status'";

$cards = mysqli_query($conn, "SELECT * FROM scratch_cards $where ORDER BY id ASC LIMIT 50");

$type_labels = [
    'application' => 'Application Form',
    'course_registration' => 'Course Registration',
    'exam_card' => 'Exam Card',
    'posting_letter' => 'Posting Letter (T.P)',
    'acceptance_letter' => 'Acceptance Letter',
    'receipt' => 'Print Receipt',
];

// ============================================
// LAUNI DA ALAMAR KOWANE NAU'I
// ============================================
$type_colors = [
    'application'         => ['bg' => '#2e7d32', 'text' => '#ffffff', 'icon' => '📝', 'label' => 'APPLICATION'],
    'course_registration' => ['bg' => '#1976d2', 'text' => '#ffffff', 'icon' => '📚', 'label' => 'COURSE REG'],
    'exam_card'           => ['bg' => '#c62828', 'text' => '#ffffff', 'icon' => '🎓', 'label' => 'EXAM CARD'],
    'posting_letter'      => ['bg' => '#f57c00', 'text' => '#ffffff', 'icon' => '📮', 'label' => 'POSTING'],
    'acceptance_letter'   => ['bg' => '#7b1fa2', 'text' => '#ffffff', 'icon' => '📜', 'label' => 'ACCEPTANCE'],
    'receipt'             => ['bg' => '#00695c', 'text' => '#ffffff', 'icon' => '🧾', 'label' => 'RECEIPT'],
];

$type_label = $type_labels[$filter_type] ?? 'Scratch Card';
$type_color = $type_colors[$filter_type] ?? ['bg' => '#0d2818', 'text' => '#ffffff', 'icon' => '🎫', 'label' => 'CARD'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Scratch Cards - Dala College</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f0f4f8; padding: 20px; }
        
        /* ============================================ */
        /* PRINT CONTROLS */
        /* ============================================ */
        .print-controls {
            max-width: 1400px; margin: 0 auto 20px; background: white;
            padding: 20px; border-radius: 12px; display: flex;
            gap: 15px; align-items: center; flex-wrap: wrap;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .print-controls h1 { color: #0d2818; font-size: 1.3rem; margin-right: auto; }
        .print-controls .btn {
            padding: 10px 20px; background: #2e7d32; color: white;
            border: none; border-radius: 8px; font-weight: 700;
            cursor: pointer; text-decoration: none;
        }
        .print-controls .btn:hover { background: #1b5e20; }
        
        /* ============================================ */
        /* CARDS GRID */
        /* ============================================ */
        .cards-grid {
            max-width: 1400px; margin: 0 auto;
            display: grid; grid-template-columns: 1fr;
            gap: 10px; justify-items: center;
        }
        
        /* ============================================ */
        /* CARD WRAPPER - FRONT da BACK */
        /* ============================================ */
        .card-wrapper {
            border: 1.5px dashed #0d2818;
            border-radius: 8px;
            padding: 4px;
            background: white;
            page-break-inside: avoid;
            display: grid;
            grid-template-columns: 85mm 85mm;
            gap: 4px;
            width: fit-content;
            margin: 0 auto;
        }
        
        /* ============================================ */
        /* FRONT SIDE - 85mm x 55mm */
        /* ============================================ */
        .front-card {
            width: 85mm;
            height: 55mm;
            border: 1.5px solid #ffd54f;
            border-radius: 5px;
            overflow: hidden;
            position: relative;
            background-image: url('images/School-card-front.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        
        /* ============================================ */
        /* BACK SIDE - 85mm x 55mm */
        /* ============================================ */
        .back-card {
            width: 85mm;
            height: 55mm;
            border: 1.5px solid #0d2818;
            border-radius: 5px;
            position: relative;
            overflow: hidden;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            padding: 0;
        }
        
        /* TOP ACCENT BAR */
        .back-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, #0d2818, #2e7d32, #ffd54f);
            z-index: 2;
        }
        
        /* ============================================ */
        /* HEADER */
        /* ============================================ */
        .back-header {
            display: flex;
            align-items: center;
            gap: 3px;
            padding: 4px 5px 3px;
            border-bottom: 1px solid #0d2818;
            margin-top: 3px;
        }
        .back-header .logo {
            width: 16px; height: 16px;
            object-fit: contain;
            border-radius: 50%;
            border: 1px solid #0d2818;
            background: white;
            padding: 1px;
        }
        .back-header .college-name {
            flex: 1;
            line-height: 1.1;
        }
        .back-header .college-name h3 {
            font-size: 8px;
            color: #0d2818;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }
        .back-header .college-name p {
            font-size: 6px;
            color: #2e7d32;
            font-weight: 700;
            font-style: italic;
        }
        
        /* ============================================ */
        /* TYPE BADGE */
        /* ============================================ */
        .type-badge {
            position: absolute;
            top: 6px;
            right: 4px;
            z-index: 3;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 7px;
            font-weight: 900;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            box-shadow: 0 1px 4px rgba(0,0,0,0.3);
            background: <?php echo $type_color['bg']; ?> !important;
            color: <?php echo $type_color['text']; ?> !important;
        }
        
        /* ============================================ */
        /* PIN SECTION */
        /* ============================================ */
        .pin-section {
            text-align: center;
            padding: 3px 4px;
            background: linear-gradient(135deg, #f8faf8 0%, #e8f5e9 100%);
            border-radius: 4px;
            border: 1px solid #c8e6c9;
            margin: 3px 5px 2px;
        }
        .pin-section .pin-label {
            font-size: 6px;
            color: #6a8f6a;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1px;
        }
        .pin-section .pin-value {
            font-size: 22px;
            font-weight: 900;
            color: #0d2818;
            letter-spacing: 3px;
            font-family: 'Courier New', monospace;
            text-shadow: 1px 1px 0 #ffd54f;
        }
        
        /* ============================================ */
        /* CARD CODE SECTION */
        /* ============================================ */
        .card-code-section {
            background: #0d2818;
            color: white;
            padding: 3px 5px;
            border-radius: 4px;
            text-align: center;
            margin: 2px 5px;
        }
        .card-code-section .code-label {
            font-size: 5.5px;
            color: #a5d6a7;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 1px;
        }
        .card-code-section .code-value {
            font-size: 10px;
            font-weight: 900;
            color: #ffd54f;
            letter-spacing: 0.8px;
            font-family: 'Courier New', monospace;
        }
        
        /* ============================================ */
        /* DETAILS */
        /* ============================================ */
        .card-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1px 5px;
            font-size: 6px;
            margin: 2px 5px;
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
        
        /* ============================================ */
        /* INFO ROW - INSTRUCTIONS + NOTICE A GEFE */
        /* ============================================ */
        .info-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3px;
            margin: 2px 5px;
            flex-grow: 1;
        }
        
        /* ============================================ */
        /* HOW TO USE THIS CARD */
        /* ============================================ */
        .card-instructions {
            background: #f8faf8;
            border: 1px solid #c8e6c9;
            border-radius: 4px;
            padding: 3px 4px;
            margin: 0;
            font-size: 8px;
            color: #1a2e1a;
            line-height: 1.00;
            overflow: hidden;
        }
        .card-instructions .instructions-title {
            font-size: 6px;
            font-weight: 900;
            color: #0d2818;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 1px;
            padding-bottom: 1px;
            border-bottom: 1px solid #c8e6c9;
        }
        .card-instructions ol {
            padding-left: 8px;
            margin-bottom: 0;
        }
        .card-instructions ol li {
            margin-bottom: 0.3px;
        }
        .card-instructions ol li strong {
            color: #0d2818;
        }
        
        /* ============================================ */
        /* IMPORTANT NOTICE */
        /* ============================================ */
        .important-notice {
            background: #fff3e0;
            border: 1px solid #ff9800;
            border-radius: 4px;
            padding: 3px 4px;
            margin: 0;
            font-size: 10px;
            color: #e65100;
            line-height: 2.00
            overflow: hidden;
        }
        .important-notice .notice-title {
            font-weight: 900;
            font-size: 6px;
            margin-bottom: 1px;
            text-transform: uppercase;
        }
        
        /* ============================================ */
        /* FOOTER */
        /* ============================================ */
        .back-footer {
            text-align: center;
            font-size: 5px;
            color: #6a8f6a;
            padding: 2px;
            margin-top: auto;
            border-top: 1px dashed #dce8dc;
        }
        .back-footer .website {
            color: #2e7d32;
            font-weight: 800;
            font-size: 5.5px;
        }
        
        /* ============================================ */
        /* PRINT */
        /* ============================================ */
        @media print {
            body { background: white; padding: 0; margin: 0; }
            .print-controls { display: none !important; }
            .cards-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 6px;
                padding: 0;
                max-width: 100%;
                justify-items: center;
            }
            .card-wrapper {
                border: 1px dashed #000;
                padding: 2px;
                gap: 2px;
                page-break-inside: avoid;
                display: grid;
                grid-template-columns: 85mm 85mm;
                width: fit-content;
                margin: 0 auto;
            }
            .front-card {
                background-image: url('images/School-card-front.jpg') !important;
                background-size: cover !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                width: 85mm;
                height: 55mm;
            }
            .back-card {
                width: 85mm;
                height: 55mm;
            }
            .back-card::before {
                background: linear-gradient(90deg, #0d2818, #2e7d32, #ffd54f) !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .pin-section {
                background: #e8f5e9 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .card-code-section {
                background: #0d2818 !important;
                color: #ffd54f !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .type-badge {
                background: <?php echo $type_color['bg']; ?> !important;
                color: <?php echo $type_color['text']; ?> !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .important-notice {
                background: #fff3e0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            @page { size: A4; margin: 4mm; }
        }
        
        @media (max-width: 768px) {
            .card-wrapper { grid-template-columns: 85mm; }
        }
    </style>
</head>
<body>

<div class="print-controls">
    <h1><?php echo $type_color['icon']; ?> Scratch Cards — <?php echo htmlspecialchars($type_label); ?></h1>
    <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap;">
        <select name="card_type" style="padding:10px; border:2px solid #dce8dc; border-radius:8px;">
            <?php foreach ($type_labels as $key => $label): ?>
                <option value="<?php echo $key; ?>" <?php echo ($filter_type == $key) ? 'selected' : ''; ?>>
                    <?php echo $type_colors[$key]['icon'] . ' ' . $label; ?>
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
        <?php while ($card = mysqli_fetch_assoc($cards)): 
            $card_type_db = $card['card_type'];
            $card_color = $type_colors[$card_type_db] ?? ['bg' => '#0d2818', 'text' => '#ffffff', 'icon' => '🎫', 'label' => 'CARD'];
            $card_label = $type_labels[$card_type_db] ?? 'Scratch Card';
        ?>
        <div class="card-wrapper">
            
            <!-- ============================================ -->
            <!-- FRONT SIDE -->
            <!-- ============================================ -->
            <div class="front-card">
                <?php if (!file_exists('images/School-card-front.jpg')): ?>
                    <div style="display:flex;align-items:center;justify-content:center;height:100%;background:#0d2818;color:#ffd54f;font-size:8px;text-align:center;padding:10px;">
                        HOTO BA YA NAN<br>images/School-card-front.jpg
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- ============================================ -->
            <!-- BACK SIDE -->
            <!-- ============================================ -->
            <div class="back-card">
                
                <!-- TYPE BADGE -->
                <div class="type-badge" style="background: <?php echo $card_color['bg']; ?>; color: <?php echo $card_color['text']; ?>;">
                    <?php echo $card_color['icon']; ?> <?php echo $card_color['label']; ?>
                </div>
                
                <!-- HEADER -->
                <div class="back-header">
                    <?php if (file_exists('images/dala-logo.png')): ?>
                        <img src="images/dala-logo.png" class="logo" alt="Logo">
                    <?php endif; ?>
                    <div class="college-name">
                        <h3>DALA COLLEGE OF EDUCATION, KANO</h3>
                        <p>Knowledge, Excellence & Success</p>
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
                        <span class="value"><?php echo htmlspecialchars($card_label); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Value:</span>
                        <span class="value">₦<?php echo number_format($card['usage_limit'] ?? 0); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Session:</span>
                        <span class="value">2026/2027</span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Expiry:</span>
                        <span class="value"><?php echo !empty($card['expiry_date']) ? date('m/Y', strtotime($card['expiry_date'])) : '-'; ?></span>
                    </div>
                </div>
                
                <!-- ============================================ -->
                <!-- INSTRUCTIONS + NOTICE A GEFE -->
                <!-- ============================================ -->
                <div class="info-row">
                    
                    <!-- HOW TO USE THIS CARD -->
                    <div class="card-instructions">
                        <div class="instructions-title">📖 HOW TO USE</div>
                        <ol>
                            <li>Visit <strong>www.dalacoe.edu.ng</strong></li>
                            <li>Login with <strong>Username &amp; Password</strong></li>
                            <li>Click the Service</li>
                            <li>Enter PIN: <strong><?php echo htmlspecialchars($card['pin']); ?></strong></li>
                            <li>Or enter Card Code</li>
                            <li>Click <strong>Verify Card</strong></li>
                            <li>Service opens</li>
                        </ol>
                    </div>
                    
                    <!-- IMPORTANT NOTICE -->
                    <div class="important-notice">
                        <div class="notice-title">⚠️ NOTICE</div>
                        <div>• Keep card safe.</div>
                        <div>• Do not share PIN.</div>
                        <div>• PIN used once.</div>
                        <div>• Report if damaged.</div>
                    </div>
                    
                </div>
                
                <!-- FOOTER -->
                <div class="back-footer">
                    Powered by <span class="website">Dala College ICT Unit</span>
                </div>
                
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