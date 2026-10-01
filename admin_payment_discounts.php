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
$staff_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// ============================================
// ADD DISCOUNT
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_discount'])) {
    $student_id = intval($_POST['student_id']);
    $discount_type = mysqli_real_escape_string($conn, $_POST['discount_type']);
    $discount_value = floatval($_POST['discount_value']);
    $reason = mysqli_real_escape_string($conn, trim($_POST['reason']));
    $valid_until = mysqli_real_escape_string($conn, $_POST['valid_until']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    if ($student_id <= 0 || $discount_value <= 0) {
        $message = "❌ Dole ka zaɓi ɗalibi kuma ka saka adadin rangwame.";
        $message_type = 'error';
    } else {
        $insert = "INSERT INTO payment_discounts 
                    (student_id, discount_type, discount_value, reason, approved_by, status, valid_until) 
                   VALUES 
                    ($student_id, '$discount_type', $discount_value, '$reason', $staff_id, '$status', '$valid_until')";
        if (mysqli_query($conn, $insert)) {
            $message = "✅ Discount added successfully!";
            $message_type = 'success';
        } else {
            $message = "❌ Error: " . mysqli_error($conn);
            $message_type = 'error';
        }
    }
}

// ============================================
// UPDATE STATUS
// ============================================
if (isset($_GET['approve']) && is_numeric($_GET['approve'])) {
    $id = intval($_GET['approve']);
    mysqli_query($conn, "UPDATE payment_discounts SET status = 'approved' WHERE id = $id");
    $message = "✅ Discount approved!";
    $message_type = 'success';
}

if (isset($_GET['reject']) && is_numeric($_GET['reject'])) {
    $id = intval($_GET['reject']);
    mysqli_query($conn, "UPDATE payment_discounts SET status = 'rejected' WHERE id = $id");
    $message = "❌ Discount rejected!";
    $message_type = 'success';
}

// ============================================
// DELETE
// ============================================
if (isset($_GET['delete']) && is_numeric($_GET['delete']) && $user_role == 'admin') {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM payment_discounts WHERE id = $id");
    $message = "🗑️ Discount deleted!";
    $message_type = 'success';
}

// ============================================
// EXPORT CSV
// ============================================
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payment_discounts_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['S/N', 'Reg No', 'Student Name', 'Type', 'Value', 'Reason', 'Status', 'Valid Until', 'Created']);
    
    $export = mysqli_query($conn, "
        SELECT pd.*, s.reg_no, s.fullname 
        FROM payment_discounts pd
        JOIN students s ON pd.student_id = s.id
        ORDER BY pd.id DESC
    ");
    
    $sn = 1;
    while ($e = mysqli_fetch_assoc($export)) {
        fputcsv($output, [
            $sn++,
            $e['reg_no'],
            $e['fullname'],
            $e['discount_type'],
            number_format($e['discount_value'], 2),
            $e['reason'],
            ucfirst($e['status']),
            $e['valid_until'],
            $e['created_at']
        ]);
    }
    fclose($output);
    exit();
}

// ============================================
// FETCH DISCOUNTS
// ============================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_status = isset($_GET['filter_status']) ? mysqli_real_escape_string($conn, $_GET['filter_status']) : '';
$filter_type = isset($_GET['filter_type']) ? mysqli_real_escape_string($conn, $_GET['filter_type']) : '';

$where = "WHERE 1=1";
if (!empty($search)) $where .= " AND (s.fullname LIKE '%$search%' OR s.reg_no LIKE '%$search%' OR pd.reason LIKE '%$search%')";
if (!empty($filter_status)) $where .= " AND pd.status = '$filter_status'";
if (!empty($filter_type)) $where .= " AND pd.discount_type = '$filter_type'";

$discounts = mysqli_query($conn, "
    SELECT pd.*, s.reg_no, s.fullname, s.combination, s.level 
    FROM payment_discounts pd
    JOIN students s ON pd.student_id = s.id
    $where
    ORDER BY pd.id DESC
");

// ============================================
// STATS
// ============================================
$total_discounts = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM payment_discounts"))['c'];
$pending_discounts = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM payment_discounts WHERE status = 'pending'"))['c'];
$approved_discounts = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM payment_discounts WHERE status = 'approved'"))['c'];
$total_value = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(discount_value),0) AS t FROM payment_discounts WHERE status = 'approved'"))['t'];

// ============================================
// STUDENTS DROPDOWN
// ============================================
$students_list = mysqli_query($conn, "SELECT id, reg_no, fullname, combination, level FROM students WHERE status IN ('active', 'approved') ORDER BY fullname");

date_default_timezone_set('Africa/Lagos');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Discounts - Dala College</title>
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
        .stat-card.blue { border-left-color:#1976d2; }
        .stat-card.blue .number { color:#1976d2; }
        .stat-card.purple { border-left-color:#7b1fa2; }
        .stat-card.purple .number { color:#7b1fa2; }
        
        .form-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:12px; }
        .form-group label { display:block; font-weight:700; color:#0d2818; margin-bottom:5px; font-size:0.78rem; }
        .form-group input, .form-group select, .form-group textarea { width:100%; padding:9px 12px; border:2px solid #dce8dc; border-radius:8px; font-size:0.85rem; font-family:inherit; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color:#2e7d32; outline:none; }
        .form-group textarea { resize:vertical; min-height:60px; }
        
        .btn { padding:9px 18px; border:none; border-radius:8px; font-weight:700; font-size:0.85rem; cursor:pointer; text-decoration:none; display:inline-block; transition:all 0.3s ease; }
        .btn:hover { transform:translateY(-2px); }
        .btn-green { background:#2e7d32; color:white; }
        .btn-blue { background:#1976d2; color:white; }
        .btn-red { background:#c62828; color:white; }
        .btn-orange { background:#f57c00; color:white; }
        .btn-gray { background:#6a8f6a; color:white; }
        .btn-sm { padding:5px 10px; font-size:0.75rem; }
        
        .filter-bar { display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:10px; align-items:end; margin-bottom:15px; }
        
        .table-container { overflow:auto; border-radius:8px; }
        table { width:100%; border-collapse:collapse; }
        table th { background:#0d2818; color:white; padding:10px 12px; text-align:left; font-size:0.72rem; text-transform:uppercase; }
        table td { padding:10px 12px; border-bottom:1px solid #e0e0e0; font-size:0.85rem; }
        table tr:hover { background:#f8faf8; }
        .amount { text-align:right; font-weight:700; color:#2e7d32; }
        
        .badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:0.7rem; font-weight:700; }
        .badge-pending { background:#fff8e1; color:#f57c00; }
        .badge-approved { background:#e8f5e9; color:#2e7d32; }
        .badge-rejected { background:#ffebee; color:#c62828; }
        .badge-percent { background:#e3f2fd; color:#0d47a1; }
        .badge-fixed { background:#f3e5f5; color:#6a1b9a; }
        
        .empty-state { text-align:center; padding:40px; color:#6a8f6a; }
        .empty-state i { font-size:3rem; color:#dce8dc; display:block; margin-bottom:15px; }
        
        @media (max-width:900px) {
            .form-grid { grid-template-columns:1fr 1fr; }
            .stats-grid { grid-template-columns:1fr 1fr; }
            .filter-bar { grid-template-columns:1fr 1fr; }
        }
        @media (max-width:600px) {
            .form-grid, .filter-bar, .stats-grid { grid-template-columns:1fr; }
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
                <a href="admin_payments_verify.php">✅ Verify</a>
                <a href="admin_payment_items.php">⚙️ Items</a>
                <a href="admin_payment_discounts.php" class="active">🎁 Discounts</a>
                <a href="admin_payment_reports.php">📊 Reports</a>
                <a href="staff_dashboard.php">🏠 Dashboard</a>
                <a href="logout.php" style="background:#c62828; color:white;">Logout</a>
            </nav>
            <div>
                <span class="admin-badge">👤 <?php echo htmlspecialchars($staff_name); ?> (<?php echo htmlspecialchars($user_role); ?>)</span>
            </div>
        </div>

        <h2 style="color:#0d2818; margin-bottom:5px;">🎁 Payment Discounts & Scholarships</h2>
        <p style="color:#6a8f6a; margin-bottom:20px; font-size:0.9rem;">
            Ba da rangwame (discount) ko scholarship ga ɗalibai
        </p>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $total_discounts; ?></div>
                <div class="label">🎁 Total Discounts</div>
            </div>
            <div class="stat-card orange">
                <div class="number"><?php echo $pending_discounts; ?></div>
                <div class="label">⏳ Pending</div>
            </div>
            <div class="stat-card blue">
                <div class="number"><?php echo $approved_discounts; ?></div>
                <div class="label">✅ Approved</div>
            </div>
            <div class="stat-card purple">
                <div class="number">₦<?php echo number_format($total_value, 2); ?></div>
                <div class="label">💰 Total Value</div>
            </div>
        </div>

        <!-- ADD DISCOUNT -->
        <div class="card">
            <h2>➕ Add New Discount</h2>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Student *</label>
                        <select name="student_id" required>
                            <option value="">-- Select Student --</option>
                            <?php while ($s = mysqli_fetch_assoc($students_list)): ?>
                                <option value="<?php echo $s['id']; ?>">
                                    <?php echo htmlspecialchars($s['reg_no']); ?> — <?php echo htmlspecialchars($s['fullname']); ?> (<?php echo htmlspecialchars($s['level']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Discount Type *</label>
                        <select name="discount_type" required>
                            <option value="fixed">Fixed Amount (₦)</option>
                            <option value="percentage">Percentage (%)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Discount Value *</label>
                        <input type="number" name="discount_value" step="0.01" min="0" required placeholder="e.g. 5000 ko 10">
                    </div>
                    <div class="form-group">
                        <label>Valid Until</label>
                        <input type="date" name="valid_until" value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>">
                    </div>
                    <div class="form-group">
                        <label>Status *</label>
                        <select name="status" required>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Reason / Notes</label>
                        <textarea name="reason" placeholder="Misali: Scholarship for excellent performance, Staff ward, etc."></textarea>
                    </div>
                </div>
                <button type="submit" name="add_discount" class="btn btn-green" style="margin-top:15px;">
                    <i class="fas fa-plus"></i> Add Discount
                </button>
            </form>
        </div>

        <!-- FILTERS -->
        <div class="card">
            <h2>🔍 Search & Filter</h2>
            <form method="GET">
                <div class="filter-bar">
                    <div class="form-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Name, reg no, reason..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="filter_status">
                            <option value="">All</option>
                            <option value="pending" <?php echo ($filter_status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo ($filter_status == 'approved') ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo ($filter_status == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="filter_type">
                            <option value="">All</option>
                            <option value="fixed" <?php echo ($filter_type == 'fixed') ? 'selected' : ''; ?>>Fixed Amount</option>
                            <option value="percentage" <?php echo ($filter_type == 'percentage') ? 'selected' : ''; ?>>Percentage</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-green" style="width:100%;">🔍 Filter</button>
                    </div>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:8px;">
                    <a href="admin_payment_discounts.php" class="btn btn-orange btn-sm">🔄 Reset</a>
                    <a href="?export_csv=1" class="btn btn-blue btn-sm"><i class="fas fa-download"></i> Export CSV</a>
                    <button onclick="window.print()" class="btn btn-green btn-sm"><i class="fas fa-print"></i> Print</button>
                </div>
            </form>
        </div>

        <!-- DISCOUNTS TABLE -->
        <div class="card">
            <h2>📋 All Discounts (<?php echo $discounts->num_rows; ?> shown)</h2>
            
            <?php if ($discounts->num_rows > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Reg No</th>
                            <th>Student Name</th>
                            <th>Combination</th>
                            <th>Level</th>
                            <th>Type</th>
                            <th>Value</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Valid Until</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($d = mysqli_fetch_assoc($discounts)): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><strong><?php echo htmlspecialchars($d['reg_no']); ?></strong></td>
                            <td><?php echo htmlspecialchars($d['fullname']); ?></td>
                            <td><?php echo htmlspecialchars($d['combination']); ?></td>
                            <td><?php echo htmlspecialchars($d['level']); ?></td>
                            <td>
                                <?php if ($d['discount_type'] == 'percentage'): ?>
                                    <span class="badge badge-percent">%</span>
                                <?php else: ?>
                                    <span class="badge badge-fixed">₦</span>
                                <?php endif; ?>
                            </td>
                            <td class="amount">
                                <?php 
                                if ($d['discount_type'] == 'percentage') {
                                    echo $d['discount_value'] . '%';
                                } else {
                                    echo '₦' . number_format($d['discount_value'], 2);
                                }
                                ?>
                            </td>
                            <td style="font-size:0.78rem; color:#666; max-width:200px;">
                                <?php echo htmlspecialchars($d['reason'] ?: '—'); ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $d['status']; ?>">
                                    <?php echo ucfirst($d['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $d['valid_until'] ? date('d-M-Y', strtotime($d['valid_until'])) : '—'; ?></td>
                            <td style="font-size:0.78rem; color:#666;">
                                <?php echo date('d-M-Y', strtotime($d['created_at'])); ?>
                            </td>
                            <td style="white-space:nowrap;">
                                <?php if ($d['status'] == 'pending'): ?>
                                    <a href="?approve=<?php echo $d['id']; ?>" class="btn btn-green btn-sm" 
                                       onclick="return confirm('Approve this discount?')" title="Approve">
                                        <i class="fas fa-check"></i>
                                    </a>
                                    <a href="?reject=<?php echo $d['id']; ?>" class="btn btn-red btn-sm" 
                                       onclick="return confirm('Reject this discount?')" title="Reject">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($user_role == 'admin'): ?>
                                <a href="?delete=<?php echo $d['id']; ?>" class="btn btn-red btn-sm" 
                                   onclick="return confirm('Delete this discount?')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-gift"></i>
                    <h3>Babu Discounts</h3>
                    <p>Babu wani rangwame da aka bayar tukuna. Yi amfani da form ɗin da ke sama.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>