<?php
session_start();
include 'connect.php';

// ============================================
// SESSION CHECK
// ============================================
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_role = $_SESSION['role'] ?? '';
$allowed_roles = ['admin'];

if (!in_array($user_role, $allowed_roles)) {
    echo "<div style='background:#ffebee; padding:30px; font-family:Arial; text-align:center; margin:50px auto; max-width:600px; border-radius:12px;'>";
    echo "<h2 style='color:#c62828;'>❌ Ba ka da izinin shiga wannan shafin</h2>";
    echo "<a href='staff_dashboard.php' style='display:inline-block; padding:10px 25px; background:#2e7d32; color:white; text-decoration:none; border-radius:8px; font-weight:bold;'>Koma Dashboard</a>";
    echo "</div>";
    exit();
}

$staff_name = $_SESSION['fullname'] ?? 'Staff';
$message = '';
$message_type = '';

// ============================================
// VERIFY SINGLE PAYMENT
// ============================================
if (isset($_GET['verify']) && is_numeric($_GET['verify'])) {
    $id = intval($_GET['verify']);
    $update = "UPDATE payments SET status = 'paid', recorded_by = CONCAT(recorded_by, ' | Verified by $staff_name') WHERE id = $id";
    if (mysqli_query($conn, $update)) {
        $message = "✅ Payment verified successfully!";
        $message_type = 'success';
    } else {
        $message = "❌ Error: " . mysqli_error($conn);
        $message_type = 'error';
    }
}

// ============================================
// REJECT SINGLE PAYMENT
// ============================================
if (isset($_GET['reject']) && is_numeric($_GET['reject'])) {
    $id = intval($_GET['reject']);
    $update = "UPDATE payments SET status = 'failed', recorded_by = CONCAT(recorded_by, ' | Rejected by $staff_name') WHERE id = $id";
    if (mysqli_query($conn, $update)) {
        $message = "❌ Payment rejected!";
        $message_type = 'success';
    }
}

// ============================================
// BULK VERIFY
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_verify'])) {
    if (!empty($_POST['payment_ids'])) {
        $ids = array_map('intval', $_POST['payment_ids']);
        $ids_str = implode(',', $ids);
        $update = "UPDATE payments SET status = 'paid', recorded_by = CONCAT(recorded_by, ' | Bulk verified by $staff_name') WHERE id IN ($ids_str)";
        if (mysqli_query($conn, $update)) {
            $count = count($ids);
            $message = "✅ $count payment(s) verified successfully!";
            $message_type = 'success';
        }
    } else {
        $message = "⚠️ Babu payment da aka zaɓa.";
        $message_type = 'error';
    }
}

// ============================================
// BULK REJECT
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_reject'])) {
    if (!empty($_POST['payment_ids'])) {
        $ids = array_map('intval', $_POST['payment_ids']);
        $ids_str = implode(',', $ids);
        $update = "UPDATE payments SET status = 'failed', recorded_by = CONCAT(recorded_by, ' | Bulk rejected by $staff_name') WHERE id IN ($ids_str)";
        if (mysqli_query($conn, $update)) {
            $count = count($ids);
            $message = "❌ $count payment(s) rejected!";
            $message_type = 'success';
        }
    } else {
        $message = "⚠️ Babu payment da aka zaɓa.";
        $message_type = 'error';
    }
}

// ============================================
// STATS
// ============================================
$count_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM payments WHERE status = 'pending'"))['c'];
$count_paid = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM payments WHERE status = 'paid'"))['c'];
$count_failed = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM payments WHERE status = 'failed'"))['c'];
$total_pending_amount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) AS t FROM payments WHERE status = 'pending'"))['t'];
$today_verified = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM payments WHERE status = 'paid' AND DATE(paid_at) = CURDATE()"))['c'] ?? 0;

// ============================================
// FETCH PENDING PAYMENTS
// ============================================
$pending = mysqli_query($conn, "SELECT * FROM payments WHERE status = 'pending' ORDER BY payment_date DESC, id DESC");

// ============================================
// FETCH RECENTLY VERIFIED (last 10)
// ============================================
$recent = mysqli_query($conn, "SELECT * FROM payments WHERE status = 'paid' ORDER BY id DESC LIMIT 10");

// ============================================
// OPTIONS
// ============================================
$level_options = ['NCE I', 'NCE II', 'NCE III', '400 Level', '500 Level'];

date_default_timezone_set('Africa/Lagos');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Payments - Dala College</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Tahoma,sans-serif; background:#f0f4f8; color:#1a2e1a; min-height:100vh; padding:20px; }
        .container { max-width:1400px; margin:0 auto; }
        
        .topbar { background:#0d2818; padding:15px 25px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; border-radius:12px; margin-bottom:20px; }
        .topbar .logo-title { color:#ffd54f; font-size:1.3rem; font-weight:800; }
        .topbar .logo-title span { color:#a5d6a7; }
        .topbar .logo-sub { display:block; font-size:0.55rem; color:#c8e6c9; }
        .topbar nav a { color:#c8e6c9; text-decoration:none; padding:8px 16px; border-radius:25px; font-size:0.85rem; }
        .topbar nav a:hover { background:#2e7d32; }
        .topbar nav .active { background:#2e7d32; color:white; }
        .topbar .admin-badge { background:#c62828; color:white; padding:6px 18px; border-radius:20px; font-size:0.8rem; font-weight:600; }
        
        .card { background:white; padding:25px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); margin-bottom:20px; }
        .card h2 { color:#0d2818; margin-bottom:15px; font-size:1.15rem; }
        
        .message { padding:12px 20px; border-radius:8px; margin-bottom:15px; font-weight:600; }
        .message.success { background:#e8f5e9; color:#2e7d32; border-left:4px solid #2e7d32; }
        .message.error { background:#ffebee; color:#c62828; border-left:4px solid #c62828; }
        
        .stats-grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:15px; margin-bottom:20px; }
        .stat-card { background:white; padding:20px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); border-left:4px solid #2e7d32; }
        .stat-card .number { font-size:1.5rem; font-weight:900; color:#2e7d32; }
        .stat-card .label { font-size:0.75rem; color:#6a8f6a; font-weight:700; text-transform:uppercase; }
        .stat-card.orange { border-left-color:#f57c00; }
        .stat-card.orange .number { color:#f57c00; }
        .stat-card.red { border-left-color:#c62828; }
        .stat-card.red .number { color:#c62828; }
        .stat-card.blue { border-left-color:#1976d2; }
        .stat-card.blue .number { color:#1976d2; }
        
        .btn { padding:9px 18px; border:none; border-radius:8px; font-weight:700; font-size:0.85rem; cursor:pointer; text-decoration:none; display:inline-block; transition:all 0.3s ease; }
        .btn:hover { transform:translateY(-2px); }
        .btn-green { background:#2e7d32; color:white; }
        .btn-blue { background:#1976d2; color:white; }
        .btn-red { background:#c62828; color:white; }
        .btn-orange { background:#f57c00; color:white; }
        .btn-gray { background:#6a8f6a; color:white; }
        .btn-sm { padding:5px 10px; font-size:0.75rem; }
        
        .table-container { overflow:auto; border-radius:8px; }
        table { width:100%; border-collapse:collapse; }
        table th { background:#0d2818; color:white; padding:10px 12px; text-align:left; font-size:0.72rem; text-transform:uppercase; }
        table td { padding:10px 12px; border-bottom:1px solid #e0e0e0; font-size:0.85rem; }
        table tr:hover { background:#f8faf8; }
        .amount { text-align:right; font-weight:700; color:#2e7d32; }
        
        .badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:0.7rem; font-weight:700; }
        .badge-paid { background:#e8f5e9; color:#2e7d32; }
        .badge-pending { background:#fff8e1; color:#f57c00; }
        .badge-failed { background:#ffebee; color:#c62828; }
        
        .empty-state { text-align:center; padding:40px; color:#6a8f6a; }
        .empty-state i { font-size:3rem; color:#dce8dc; display:block; margin-bottom:15px; }
        
        .bulk-actions { display:flex; gap:10px; margin-bottom:15px; flex-wrap:wrap; align-items:center; padding:12px 18px; background:#fff9c4; border-radius:8px; border-left:4px solid #f9a825; }
        .bulk-actions label { font-weight:700; color:#0d2818; }
        
        .checkbox-cell { width:40px; text-align:center; }
        .checkbox-cell input { width:18px; height:18px; cursor:pointer; }
        
        @media (max-width:900px) {
            .stats-grid { grid-template-columns:1fr 1fr; }
        }
        @media (max-width:600px) {
            .stats-grid { grid-template-columns:1fr; }
            .topbar { flex-direction:column; gap:10px; }
        }
    </style>
</head>
<body>
    <div class="container">
        
        <!-- TOPBAR -->
        <div class="topbar">
            <div>
                <span class="logo-title">DALA <span>COLLEGE</span></span>
                <span class="logo-sub">Payment Management</span>
            </div>
            <nav>
                <a href="admin_payments.php">💰 Payments</a>
                <a href="admin_payments_list.php">📋 All Payments</a>
                <a href="admin_payments_verify.php" class="active">✅ Verify</a>
                <a href="admin_payment_items.php">⚙️ Items</a>
                <a href="admin_payment_reports.php">📊 Reports</a>
                <a href="staff_dashboard.php">🏠 Dashboard</a>
                <a href="logout.php" style="background:#c62828; color:white;">Logout</a>
            </nav>
            <div>
                <span class="admin-badge">👤 <?php echo htmlspecialchars($staff_name); ?> (<?php echo htmlspecialchars($user_role); ?>)</span>
            </div>
        </div>

        <h2 style="color:#0d2818; margin-bottom:5px;">✅ Verify Payments</h2>
        <p style="color:#6a8f6a; margin-bottom:20px; font-size:0.9rem;">
            Tabbatar da payments ɗin da suke <strong>pending</strong> — ka mai da su <strong>paid</strong> ko <strong>failed</strong>
        </p>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card orange">
                <div class="number"><?php echo $count_pending; ?></div>
                <div class="label">⏳ Pending</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $count_paid; ?></div>
                <div class="label">✅ Paid</div>
            </div>
            <div class="stat-card red">
                <div class="number"><?php echo $count_failed; ?></div>
                <div class="label">❌ Failed</div>
            </div>
            <div class="stat-card blue">
                <div class="number">₦<?php echo number_format($total_pending_amount, 2); ?></div>
                <div class="label">💰 Pending Amount</div>
            </div>
        </div>

        <!-- PENDING PAYMENTS -->
        <div class="card">
            <h2>⏳ Pending Payments (<?php echo $pending->num_rows; ?>)</h2>
            
            <?php if ($pending->num_rows > 0): ?>
            
            <form method="POST">
                <div class="bulk-actions">
                    <label><i class="fas fa-tasks"></i> Bulk Actions:</label>
                    <button type="submit" name="bulk_verify" class="btn btn-green btn-sm" onclick="return confirm('Verify all selected payments?')">
                        <i class="fas fa-check"></i> Verify Selected
                    </button>
                    <button type="submit" name="bulk_reject" class="btn btn-red btn-sm" onclick="return confirm('Reject all selected payments?')">
                        <i class="fas fa-times"></i> Reject Selected
                    </button>
                    <button type="button" onclick="selectAll()" class="btn btn-blue btn-sm">
                        <i class="fas fa-check-square"></i> Select All
                    </button>
                    <button type="button" onclick="deselectAll()" class="btn btn-gray btn-sm">
                        <i class="fas fa-square"></i> Deselect All
                    </button>
                </div>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th class="checkbox-cell">
                                    <input type="checkbox" id="selectAllBox" onclick="toggleAll(this)">
                                </th>
                                <th>#</th>
                                <th>Reg No</th>
                                <th>Student Name</th>
                                <th>Combination</th>
                                <th>Level</th>
                                <th>Amount</th>
                                <th>Type</th>
                                <th>Session</th>
                                <th>Reference</th>
                                <th>Bank</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; while ($p = mysqli_fetch_assoc($pending)): ?>
                            <tr>
                                <td class="checkbox-cell">
                                    <input type="checkbox" name="payment_ids[]" value="<?php echo $p['id']; ?>" class="payment-checkbox">
                                </td>
                                <td><?php echo $i++; ?></td>
                                <td><strong><?php echo htmlspecialchars($p['reg_no']); ?></strong></td>
                                <td><?php echo htmlspecialchars($p['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($p['combination']); ?></td>
                                <td><?php echo htmlspecialchars($p['level']); ?></td>
                                <td class="amount">₦<?php echo number_format($p['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($p['payment_type']); ?></td>
                                <td><?php echo htmlspecialchars($p['session']); ?></td>
                                <td><?php echo htmlspecialchars($p['reference_no']); ?></td>
                                <td><?php echo htmlspecialchars($p['bank']); ?></td>
                                <td><?php echo date('d-M-Y', strtotime($p['payment_date'])); ?></td>
                                <td style="white-space:nowrap;">
                                    <a href="?verify=<?php echo $p['id']; ?>" class="btn btn-green btn-sm" 
                                       onclick="return confirm('Verify this payment?')" title="Verify">
                                        <i class="fas fa-check"></i> Verify
                                    </a>
                                    <a href="?reject=<?php echo $p['id']; ?>" class="btn btn-red btn-sm" 
                                       onclick="return confirm('Reject this payment?')" title="Reject">
                                        <i class="fas fa-times"></i> Reject
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </form>
            
            <script>
            function toggleAll(box) {
                document.querySelectorAll('.payment-checkbox').forEach(cb => cb.checked = box.checked);
            }
            function selectAll() {
                document.querySelectorAll('.payment-checkbox').forEach(cb => cb.checked = true);
                document.getElementById('selectAllBox').checked = true;
            }
            function deselectAll() {
                document.querySelectorAll('.payment-checkbox').forEach(cb => cb.checked = false);
                document.getElementById('selectAllBox').checked = false;
            }
            </script>
            
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle" style="color:#a5d6a7;"></i>
                    <h3>Babu Pending Payments</h3>
                    <p>Duk payments ɗin an riga an tabbatar da su. Babu wani abu da ya rage.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- RECENTLY VERIFIED -->
        <div class="card">
            <h2>✅ Recently Verified (Last 10)</h2>
            <?php if ($recent->num_rows > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Reg No</th>
                            <th>Student Name</th>
                            <th>Amount</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Recorded By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($r = mysqli_fetch_assoc($recent)): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><strong><?php echo htmlspecialchars($r['reg_no']); ?></strong></td>
                            <td><?php echo htmlspecialchars($r['student_name']); ?></td>
                            <td class="amount">₦<?php echo number_format($r['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($r['payment_type']); ?></td>
                            <td><?php echo date('d-M-Y', strtotime($r['payment_date'])); ?></td>
                            <td><span class="badge badge-paid">Paid</span></td>
                            <td><?php echo htmlspecialchars($r['recorded_by']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-inbox"></i><p>Babu data</p></div>
            <?php endif; ?>
        </div>
        
    </div>
</body>
</html>