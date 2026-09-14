<?php
session_start();
include 'connect.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

$message = '';
$message_type = '';

// ============================================
// GENERATE SCRATCH CARDS
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_cards'])) {
    $number_of_cards = isset($_POST['number_of_cards']) ? intval($_POST['number_of_cards']) : 1000;
    $prefix = isset($_POST['prefix']) ? strtoupper(trim($_POST['prefix'])) : 'APL';
    $expiry_date = isset($_POST['expiry_date']) ? $_POST['expiry_date'] : date('Y-m-d', strtotime('+6 months'));
    $max_uses = isset($_POST['max_uses']) ? intval($_POST['max_uses']) : 5;
    
    $start1 = isset($_POST['start1']) ? intval($_POST['start1']) : 1001;
    $start2 = isset($_POST['start2']) ? intval($_POST['start2']) : 2001;
    $start3 = isset($_POST['start3']) ? intval($_POST['start3']) : 3100;
    
    if ($number_of_cards < 1 || $number_of_cards > 100000) {
        $message = '❌ Please enter a number between 1 and 100000.';
        $message_type = 'error';
    } else {
        $generated = 0;
        $errors = 0;
        $pin_list = [];
        
        for ($i = 0; $i < $number_of_cards; $i++) {
            $part1 = $start1 + $i;
            $part2 = $start2 + $i;
            $part3 = $start3 + $i;
            $pin = $prefix . '-' . $part1 . '-' . $part2 . '-' . $part3;
            
            $check = mysqli_query($conn, "SELECT id FROM scratch_cards WHERE pin = '$pin'");
            if (mysqli_num_rows($check) > 0) {
                $errors++;
                continue;
            }
            
            $insert = "INSERT INTO scratch_cards (pin, card_name, expiry_date, status, max_uses, used_count, remaining_uses) 
                       VALUES ('$pin', 'DALA COLLEGE', '$expiry_date', 'available', $max_uses, 0, $max_uses)";
            
            if (mysqli_query($conn, $insert)) {
                $generated++;
                $pin_list[] = $pin;
            } else {
                $errors++;
            }
        }
        
        if ($generated > 0) {
            $message = "✅ Successfully generated <strong>$generated</strong> scratch cards!<br>";
            $message .= "📌 Each card can be used <strong>$max_uses</strong> times.";
            if ($errors > 0) {
                $message .= "<br>⚠️ $errors cards failed to generate (duplicate PINs).";
            }
            $message_type = 'success';
            $_SESSION['generated_pins'] = $pin_list;
        } else {
            $message = '❌ Failed to generate any scratch cards.';
            $message_type = 'error';
        }
    }
}

// ============================================
// DELETE ALL SCRATCH CARDS
// ============================================
if (isset($_GET['delete_all'])) {
    mysqli_query($conn, "DELETE FROM scratch_cards");
    $message = '✅ All scratch cards have been deleted.';
    $message_type = 'success';
}

// ============================================
// GET STATISTICS
// ============================================
$total_cards = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM scratch_cards"))['total'];
$available_cards = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM scratch_cards WHERE status = 'available'"))['total'];
$used_cards = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM scratch_cards WHERE status = 'used'"))['total'];
$expired_cards = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM scratch_cards WHERE status = 'expired'"))['total'];

$cards_result = mysqli_query($conn, "SELECT * FROM scratch_cards ORDER BY id DESC LIMIT 200");
$cards_list = [];
while ($row = mysqli_fetch_assoc($cards_result)) {
    $cards_list[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scratch Card Generator - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f4f8;
            color: #1a2e1a;
            padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        
        .topbar {
            background: #0d2818;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .topbar .logo-title { color: #ffd54f; font-size: 1.3rem; font-weight: 800; }
        .topbar .logo-title span { color: #a5d6a7; }
        .topbar nav a {
            color: #c8e6c9; text-decoration: none; padding: 8px 18px; border-radius: 25px; font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        .topbar nav a:hover { background: #2e7d32; }
        .topbar nav .active { background: #ffd54f; color: #0d2818 !important; font-weight: 700; }
        .topbar nav .logout { background: #c62828; color: white !important; }
        
        .card {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            margin-bottom: 25px;
        }
        .card h2 { color: #0d2818; margin-bottom: 5px; }
        .card .sub { color: #6a8f6a; margin-bottom: 20px; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .stat-card .number { font-size: 2rem; font-weight: 700; }
        .stat-card .label { color: #6a8f6a; font-size: 0.85rem; }
        .stat-card.total .number { color: #0d2818; }
        .stat-card.available .number { color: #2e7d32; }
        .stat-card.used .number { color: #ffa000; }
        .stat-card.expired .number { color: #c62828; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #1a2e1a;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #dce8dc;
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #2e7d32;
            outline: none;
        }
        .form-group .hint {
            font-size: 0.75rem;
            color: #6a8f6a;
            margin-top: 3px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }
        
        .btn {
            padding: 12px 35px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary { background: #2e7d32; color: white; }
        .btn-primary:hover { background: #1b5e20; transform: translateY(-2px); }
        .btn-danger { background: #c62828; color: white; }
        .btn-danger:hover { background: #b71c1c; }
        .btn-secondary { background: #e0e0e0; color: #333; }
        .btn-secondary:hover { background: #bdbdbd; }
        .btn-print { background: #1976d2; color: white; }
        
        .message {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .message.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }
        .message.error { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }
        
        .table-wrapper { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        table th {
            background: #0d2818;
            color: white;
            padding: 10px 12px;
            text-align: left;
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        table td {
            padding: 8px 12px;
            border-bottom: 1px solid #f0f4f8;
        }
        table tr:hover td { background: #f8faf8; }
        .status-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-badge.available { background: #e8f5e9; color: #2e7d32; }
        .status-badge.used { background: #fff8e1; color: #ffa000; }
        .status-badge.expired { background: #ffebee; color: #c62828; }
        
        .pin-code {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            background: #f5f5f5;
            padding: 3px 10px;
            border-radius: 4px;
        }
        
        .uses-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .uses-badge.available { background: #e8f5e9; color: #2e7d32; }
        .uses-badge.low { background: #fff8e1; color: #ffa000; }
        .uses-badge.empty { background: #ffebee; color: #c62828; }
        
        .generated-pins {
            background: #f8faf8;
            padding: 20px;
            border-radius: 10px;
            margin-top: 15px;
            max-height: 500px;
            overflow-y: auto;
        }
        .generated-pins .pin-item {
            display: inline-block;
            background: white;
            padding: 5px 15px;
            border-radius: 6px;
            margin: 4px;
            border: 1px solid #e0e0e0;
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .preview-section {
            background: #f8faf8;
            padding: 15px 20px;
            border-radius: 10px;
            margin-top: 10px;
            border: 1px dashed #2e7d32;
        }
        .preview-section .preview-pin {
            font-family: 'Courier New', monospace;
            font-size: 1.2rem;
            font-weight: 700;
            color: #2e7d32;
            letter-spacing: 1px;
        }
        
        .expiry-info {
            background: #fff8e1;
            padding: 10px 15px;
            border-radius: 8px;
            margin-top: 5px;
            border-left: 4px solid #ffa000;
            font-size: 0.9rem;
        }
        .expiry-info strong { color: #e65100; }
        
        footer {
            background: #0d2818;
            color: #a5d6a7;
            text-align: center;
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .topbar { flex-direction: column; gap: 10px; text-align: center; }
            .topbar nav { flex-wrap: wrap; justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        
        <div class="topbar">
            <div>
                <span class="logo-title">DALA <span>COLLEGE</span></span>
                <span style="display:block; font-size:0.6rem; color:#c8e6c9;">Admin Panel</span>
            </div>
            <nav>
                <a href="admin_dashboard.php">Dashboard</a>
                <a href="admin_students.php">Students</a>
                <a href="scratch_card_generator.php" class="active">Scratch Cards</a>
                <a href="logout.php" class="logout">Logout</a>
            </nav>
        </div>

        <!-- ========== STATISTICS ========== -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="number"><?php echo $total_cards; ?></div>
                <div class="label">Total Cards</div>
            </div>
            <div class="stat-card available">
                <div class="number"><?php echo $available_cards; ?></div>
                <div class="label">Available</div>
            </div>
            <div class="stat-card used">
                <div class="number"><?php echo $used_cards; ?></div>
                <div class="label">Fully Used</div>
            </div>
            <div class="stat-card expired">
                <div class="number"><?php echo $expired_cards; ?></div>
                <div class="label">Expired</div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- ========== GENERATE ========== -->
        <div class="card">
            <h2><i class="fas fa-ticket-alt"></i> Generate Scratch Cards</h2>
            <p class="sub">Each card can be used <strong>multiple times</strong> (max 5 uses per card).</p>
            
            <div class="expiry-info">
                <i class="fas fa-clock"></i> 
                <strong>Default:</strong> 6 months expiry | <strong>Max Uses:</strong> 5 per card
            </div>
            
            <div class="preview-section" id="previewSection">
                <p style="color:#6a8f6a; font-size:0.85rem;">
                    <i class="fas fa-eye"></i> PIN Preview: 
                    <span class="preview-pin" id="pinPreview">APL-1001-2001-3100</span>
                </p>
            </div>
            
            <form method="POST" action="" id="generateForm">
                <div class="form-row">
                    <div class="form-group">
                        <label>Number of Cards</label>
                        <input type="number" name="number_of_cards" id="numCards" value="1000" min="1" max="100000" required onchange="updatePreview()" onkeyup="updatePreview()">
                    </div>
                    <div class="form-group">
                        <label>PIN Prefix</label>
                        <input type="text" name="prefix" id="prefix" value="APL" required onchange="updatePreview()" onkeyup="updatePreview()">
                    </div>
                    <div class="form-group">
                        <label>Max Uses Per Card</label>
                        <input type="number" name="max_uses" value="5" min="1" max="100" required>
                        <div class="hint">How many times this card can be used</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Expiry Date</label>
                        <input type="date" name="expiry_date" value="<?php echo date('Y-m-d', strtotime('+6 months')); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Starting Number (Part 1)</label>
                        <input type="number" name="start1" id="start1" value="1001" required onchange="updatePreview()" onkeyup="updatePreview()">
                    </div>
                    <div class="form-group">
                        <label>Starting Number (Part 2)</label>
                        <input type="number" name="start2" id="start2" value="2001" required onchange="updatePreview()" onkeyup="updatePreview()">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Starting Number (Part 3)</label>
                        <input type="number" name="start3" id="start3" value="3100" required onchange="updatePreview()" onkeyup="updatePreview()">
                    </div>
                    <div class="form-group" style="display:flex; align-items:flex-end;">
                        <button type="submit" name="generate_cards" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Generate Cards
                        </button>
                    </div>
                </div>
            </form>
            
            <?php if (isset($_SESSION['generated_pins']) && !empty($_SESSION['generated_pins'])): ?>
                <div class="generated-pins" id="generatedPins">
                    <h4 style="margin-bottom:10px; color:#0d2818;">
                        <i class="fas fa-check-circle" style="color:#2e7d32;"></i> 
                        Generated PINs (<?php echo count($_SESSION['generated_pins']); ?>)
                    </h4>
                    <?php 
                    $count = 0;
                    foreach ($_SESSION['generated_pins'] as $pin): 
                        $count++;
                    ?>
                        <span class="pin-item"><?php echo $pin; ?></span>
                        <?php if ($count % 10 == 0): ?><br><?php endif; ?>
                    <?php endforeach; ?>
                    <div style="margin-top:15px;">
                        <button class="btn btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                        <button class="btn btn-secondary" onclick="document.getElementById('generatedPins').style.display='none'">Hide</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ========== SCRATCH CARDS LIST ========== -->
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; margin-bottom:15px;">
                <h3 style="color:#0d2818;"><i class="fas fa-list"></i> Scratch Cards List</h3>
                <a href="scratch_card_generator.php?delete_all=1" class="btn btn-danger" onclick="return confirm('Delete all scratch cards?')">
                    <i class="fas fa-trash"></i> Delete All
                </a>
            </div>
            
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>PIN</th>
                            <th>Uses</th>
                            <th>Status</th>
                            <th>Used By</th>
                            <th>Used Date</th>
                            <th>Expiry</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cards_list)): ?>
                            <tr><td colspan="7" style="text-align:center; padding:20px; color:#999;">No scratch cards found.</td></tr>
                        <?php else: ?>
                            <?php $i = 1; foreach ($cards_list as $card): 
                                $remaining = $card['remaining_uses'] ?? 0;
                                $uses_class = $remaining > 3 ? 'available' : ($remaining > 0 ? 'low' : 'empty');
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><span class="pin-code"><?php echo $card['pin']; ?></span></td>
                                <td>
                                    <span class="uses-badge <?php echo $uses_class; ?>">
                                        <?php echo $card['used_count'] ?? 0; ?>/<?php echo $card['max_uses'] ?? 5; ?>
                                    </span>
                                    <small style="color:#6a8f6a; display:block; font-size:0.65rem;">
                                        Remaining: <?php echo $remaining; ?>
                                    </small>
                                </td>
                                <td><span class="status-badge <?php echo $card['status']; ?>"><?php echo ucfirst($card['status']); ?></span></td>
                                <td><?php 
                                    if ($card['used_by']) {
                                        $student = mysqli_fetch_assoc(mysqli_query($conn, "SELECT fullname FROM students WHERE id = '{$card['used_by']}'"));
                                        echo $student ? $student['fullname'] : 'Unknown';
                                    } else {
                                        echo '-';
                                    }
                                ?></td>
                                <td><?php echo $card['used_date'] ? date('d/m/Y H:i', strtotime($card['used_date'])) : '-'; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($card['expiry_date'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <footer>
            <p>© <?php echo date('Y'); ?> Dala College Kano. All Rights Reserved.</p>
        </footer>
        
    </div>

    <script>
    function updatePreview() {
        var prefix = document.getElementById('prefix').value || 'APL';
        var start1 = parseInt(document.getElementById('start1').value) || 1001;
        var start2 = parseInt(document.getElementById('start2').value) || 2001;
        var start3 = parseInt(document.getElementById('start3').value) || 3100;
        var preview = prefix + '-' + start1 + '-' + start2 + '-' + start3;
        document.getElementById('pinPreview').textContent = preview;
    }

    document.addEventListener('DOMContentLoaded', function() {
        updatePreview();
    });
    </script>

</body>
</html>