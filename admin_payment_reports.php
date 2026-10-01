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
$allowed_roles = ['admin', 'Provost', 'Accountant'];

if (!in_array($user_role, $allowed_roles)) {
    echo "<div style='background:#ffebee; padding:30px; font-family:Arial; text-align:center; margin:50px auto; max-width:600px; border-radius:12px;'>";
    echo "<h2 style='color:#c62828;'>❌ Ba ka da izinin shiga wannan shafin</h2>";
    echo "<a href='staff_dashboard.php' style='display:inline-block; padding:10px 25px; background:#2e7d32; color:white; text-decoration:none; border-radius:8px; font-weight:bold;'>Koma Dashboard</a>";
    echo "</div>";
    exit();
}

$staff_name = $_SESSION['fullname'] ?? 'Staff';

// ============================================
// DATE RANGE
// ============================================
$report_type = $_GET['report_type'] ?? 'monthly';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Set default dates based on report type
if (empty($date_from) && empty($date_to)) {
    switch ($report_type) {
        case 'daily':
            $date_from = date('Y-m-d');
            $date_to = date('Y-m-d');
            break;
        case 'weekly':
            $date_from = date('Y-m-d', strtotime('monday this week'));
            $date_to = date('Y-m-d', strtotime('sunday this week'));
            break;
        case 'monthly':
            $date_from = date('Y-m-01');
            $date_to = date('Y-m-t');
            break;
        case 'yearly':
            $date_from = date('Y-01-01');
            $date_to = date('Y-12-31');
            break;
    }
}

$date_from_esc = mysqli_real_escape_string($conn, $date_from);
$date_to_esc = mysqli_real_escape_string($conn, $date_to);

// ============================================
// FILTERS
// ============================================
$filter_comb = isset($_GET['combination']) ? mysqli_real_escape_string($conn, $_GET['combination']) : '';
$filter_level = isset($_GET['level']) ? mysqli_real_escape_string($conn, $_GET['level']) : '';
$filter_session = isset($_GET['session']) ? mysqli_real_escape_string($conn, $_GET['session']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$filter_type = isset($_GET['payment_type']) ? mysqli_real_escape_string($conn, $_GET['payment_type']) : '';

$where = "WHERE payment_date >= '$date_from_esc' AND payment_date <= '$date_to_esc'";
if (!empty($filter_comb)) $where .= " AND combination = '$filter_comb'";
if (!empty($filter_level)) $where .= " AND level = '$filter_level'";
if (!empty($filter_session)) $where .= " AND session = '$filter_session'";
if (!empty($filter_status)) $where .= " AND status = '$filter_status'";
if (!empty($filter_type)) $where .= " AND payment_type = '$filter_type'";

// ============================================
// EXPORT CSV
// ============================================
if (isset($_GET['export_csv'])) {
    $export_query = mysqli_query($conn, "SELECT * FROM payments $where ORDER BY payment_date DESC, id DESC");
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payment_report_' . $date_from . '_to_' . $date_to . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['S/N', 'Reg No', 'Student Name', 'Combination', 'Level', 'Amount', 'Payment Type', 'Session', 'Semester', 'Reference No', 'Bank', 'Payment Date', 'Status', 'Recorded By']);
    
    $sn = 1;
    while ($p = mysqli_fetch_assoc($export_query)) {
        fputcsv($output, [
            $sn++,
            $p['reg_no'],
            $p['student_name'],
            $p['combination'],
            $p['level'],
            number_format($p['amount'], 2),
            $p['payment_type'],
            $p['session'],
            $p['semester'],
            $p['reference_no'],
            $p['bank'],
            $p['payment_date'],
            $p['status'],
            $p['recorded_by']
        ]);
    }
    
    fclose($output);
    exit();
}

// ============================================
// STATISTICS
// ============================================
$total_amount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) AS t FROM payments $where"))['t'];
$total_records = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM payments $where"))['c'];
$paid_amount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) AS t FROM payments $where AND status='paid'"))['t'];
$pending_amount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) AS t FROM payments $where AND status='pending'"))['t'];
$failed_amount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) AS t FROM payments $where AND status='failed'"))['t'];
$unique_students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT student_id) AS c FROM payments $where AND status='paid'"))['c'];

// ============================================
// SUMMARY BY PAYMENT TYPE
// ============================================
$by_type = mysqli_query($conn, "
    SELECT payment_type, 
           COUNT(*) AS count, 
           COALESCE(SUM(amount),0) AS total 
    FROM payments $where 
    GROUP BY payment_type 
    ORDER BY total DESC
");

// ============================================
// SUMMARY BY LEVEL
// ============================================
$by_level = mysqli_query($conn, "
    SELECT level, 
           COUNT(*) AS count, 
           COALESCE(SUM(amount),0) AS total 
    FROM payments $where 
    GROUP BY level 
    ORDER BY level
");

// ============================================
// SUMMARY BY COMBINATION
// ============================================
$by_comb = mysqli_query($conn, "
    SELECT combination, 
           COUNT(*) AS count, 
           COALESCE(SUM(amount),0) AS total 
    FROM payments $where 
    GROUP BY combination 
    ORDER BY total DESC
");

// ============================================
// DAILY BREAKDOWN
// ============================================
$daily = mysqli_query($conn, "
    SELECT payment_date, 
           COUNT(*) AS count, 
           COALESCE(SUM(amount),0) AS total 
    FROM payments $where 
    GROUP BY payment_date 
    ORDER BY payment_date DESC
    LIMIT 30
");

// ============================================
// DETAILED PAYMENTS
// ============================================
$payments = mysqli_query($conn, "SELECT * FROM payments $where ORDER BY payment_date DESC, id DESC LIMIT 200");

// ============================================
// OPTIONS
// ============================================
$combinations_list = [];
$cq = mysqli_query($conn, "SELECT DISTINCT combination FROM students WHERE combination IS NOT NULL AND combination != '' ORDER BY combination");
while ($c = mysqli_fetch_assoc($cq)) $combinations_list[] = $c['combination'];

$session_options = ['2023/2024', '2024/2025', '2025/2026', '2026/2027'];
$level_options = ['NCE I', 'NCE II', 'NCE III', '400 Level', '500 Level'];
$type_options = ['School Fees', 'Registration Fee', 'Examination Fee', 'Acceptance Fee', 'Hostel Fee', 'Other'];

date_default_timezone_set('Africa/Lagos');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Reports - Dala College</title>
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
        
        .report-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; }
        .report-tabs a { padding:10px 22px; background:white; border:2px solid #dce8dc; border-radius:25px; text-decoration:none; color:#0d2818; font-weight:700; font-size:0.85rem; transition:all 0.3s ease; }
        .report-tabs a:hover { background:#f0f8f0; }
        .report-tabs a.active { background:#2e7d32; color:white; border-color:#2e7d32; }
        
        .stats-grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:15px; margin-bottom:20px; }
        .stat-card { background:white; padding:20px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); border-left:4px solid #2e7d32; }
        .stat-card .number { font-size:1.5rem; font-weight:900; color:#2e7d32; }
        .stat-card .label { font-size:0.75rem; color:#6a8f6a; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; }
        .stat-card.blue { border-left-color:#1976d2; }
        .stat-card.blue .number { color:#1976d2; }
        .stat-card.orange { border-left-color:#f57c00; }
        .stat-card.orange .number { color:#f57c00; }
        .stat-card.red { border-left-color:#c62828; }
        .stat-card.red .number { color:#c62828; }
        .stat-card.purple { border-left-color:#7b1fa2; }
        .stat-card.purple .number { color:#7b1fa2; }
        
        .filter-bar { display:grid; grid-template-columns:repeat(4, 1fr); gap:10px; margin-bottom:15px; align-items:end; }
        .form-group label { display:block; font-weight:700; color:#0d2818; margin-bottom:5px; font-size:0.78rem; }
        .form-group input, .form-group select { width:100%; padding:8px 12px; border:2px solid #dce8dc; border-radius:8px; font-size:0.85rem; }
        .form-group input:focus, .form-group select:focus { border-color:#2e7d32; outline:none; }
        
        .btn { padding:9px 18px; border:none; border-radius:8px; font-weight:700; font-size:0.85rem; cursor:pointer; text-decoration:none; display:inline-block; transition:all 0.3s ease; }
        .btn:hover { transform:translateY(-2px); }
        .btn-green { background:#2e7d32; color:white; }
        .btn-blue { background:#1976d2; color:white; }
        .btn-red { background:#c62828; color:white; }
        .btn-orange { background:#f57c00; color:white; }
        .btn-sm { padding:5px 10px; font-size:0.75rem; }
        
        .table-container { overflow:auto; max-height:500px; border-radius:8px; }
        table { width:100%; border-collapse:collapse; }
        table th { background:#0d2818; color:white; padding:10px 12px; text-align:left; font-size:0.72rem; text-transform:uppercase; position:sticky; top:0; }
        table td { padding:8px 12px; border-bottom:1px solid #e0e0e0; font-size:0.83rem; }
        table tr:hover { background:#f8faf8; }
        .amount { text-align:right; font-weight:700; color:#2e7d32; }
        
        .two-col { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
        
        .empty-state { text-align:center; padding:40px; color:#6a8f6a; }
        .empty-state i { font-size:3rem; color:#dce8dc; display:block; margin-bottom:15px; }
        
        .date-badge { display:inline-block; background:#f0f4f8; padding:3px 10px; border-radius:10px; font-size:0.75rem; font-weight:700; color:#0d2818; }
        
        @media (max-width:900px) {
            .stats-grid { grid-template-columns:1fr 1fr; }
            .two-col { grid-template-columns:1fr; }
            .filter-bar { grid-template-columns:1fr 1fr; }
            .topbar { flex-direction:column; gap:10px; }
        }
        @media (max-width:600px) {
            .stats-grid, .filter-bar { grid-template-columns:1fr; }
        }
        
        @media print {
            .topbar, .filter-bar, .btn, .report-tabs, nav { display:none !important; }
            body { background:white; padding:0; }
            .card { box-shadow:none; border:1px solid #ddd; page-break-inside:avoid; }
            .stat-card { border:1px solid #ddd; }
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
                <a href="admin_payment_reports.php" class="active">📊 Reports</a>
                <a href="staff_dashboard.php">🏠 Dashboard</a>
                <a href="logout.php" style="background:#c62828; color:white;">Logout</a>
            </nav>
            <div>
                <span class="admin-badge">👤 <?php echo htmlspecialchars($staff_name); ?> (<?php echo htmlspecialchars($user_role); ?>)</span>
            </div>
        </div>

        <h2 style="color:#0d2818; margin-bottom:5px;">📊 Payment Reports</h2>
        <p style="color:#6a8f6a; margin-bottom:20px; font-size:0.9rem;">
            Comprehensive reports of all payments — daily, weekly, monthly, and yearly
        </p>

        <!-- REPORT TYPE TABS -->
        <div class="report-tabs">
            <a href="?report_type=daily&combination=<?php echo urlencode($filter_comb); ?>&level=<?php echo urlencode($filter_level); ?>&session=<?php echo urlencode($filter_session); ?>&status=<?php echo urlencode($filter_status); ?>&payment_type=<?php echo urlencode($filter_type); ?>" 
               class="<?php echo ($report_type == 'daily') ? 'active' : ''; ?>">
                <i class="fas fa-calendar-day"></i> Daily
            </a>
            <a href="?report_type=weekly&combination=<?php echo urlencode($filter_comb); ?>&level=<?php echo urlencode($filter_level); ?>&session=<?php echo urlencode($filter_session); ?>&status=<?php echo urlencode($filter_status); ?>&payment_type=<?php echo urlencode($filter_type); ?>" 
               class="<?php echo ($report_type == 'weekly') ? 'active' : ''; ?>">
                <i class="fas fa-calendar-week"></i> Weekly
            </a>
            <a href="?report_type=monthly&combination=<?php echo urlencode($filter_comb); ?>&level=<?php echo urlencode($filter_level); ?>&session=<?php echo urlencode($filter_session); ?>&status=<?php echo urlencode($filter_status); ?>&payment_type=<?php echo urlencode($filter_type); ?>" 
               class="<?php echo ($report_type == 'monthly') ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i> Monthly
            </a>
            <a href="?report_type=yearly&combination=<?php echo urlencode($filter_comb); ?>&level=<?php echo urlencode($filter_level); ?>&session=<?php echo urlencode($filter_session); ?>&status=<?php echo urlencode($filter_status); ?>&payment_type=<?php echo urlencode($filter_type); ?>" 
               class="<?php echo ($report_type == 'yearly') ? 'active' : ''; ?>">
                <i class="fas fa-calendar"></i> Yearly
            </a>
            <a href="?report_type=custom&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
               class="<?php echo ($report_type == 'custom') ? 'active' : ''; ?>">
                <i class="fas fa-sliders-h"></i> Custom
            </a>
        </div>

        <!-- CURRENT PERIOD INFO -->
        <div style="background:#fff9c4; padding:12px 20px; border-radius:8px; border-left:4px solid #f9a825; margin-bottom:20px; font-size:0.9rem; color:#0d2818; font-weight:600;">
            <i class="fas fa-info-circle"></i> 
            Report Period: <span class="date-badge"><?php echo date('F j, Y', strtotime($date_from)); ?></span>
            →
            <span class="date-badge"><?php echo date('F j, Y', strtotime($date_to)); ?></span>
            <?php if ($report_type == 'daily'): ?>(Daily Report)<?php endif; ?>
            <?php if ($report_type == 'weekly'): ?>(Weekly Report)<?php endif; ?>
            <?php if ($report_type == 'monthly'): ?>(Monthly Report)<?php endif; ?>
            <?php if ($report_type == 'yearly'): ?>(Yearly Report)<?php endif; ?>
        </div>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number">₦<?php echo number_format($total_amount, 2); ?></div>
                <div class="label">💰 Total Amount</div>
            </div>
            <div class="stat-card blue">
                <div class="number"><?php echo $total_records; ?></div>
                <div class="label">📊 Total Records</div>
            </div>
            <div class="stat-card orange">
                <div class="number">₦<?php echo number_format($pending_amount, 2); ?></div>
                <div class="label">⏳ Pending</div>
            </div>
            <div class="stat-card red">
                <div class="number">₦<?php echo number_format($failed_amount, 2); ?></div>
                <div class="label">❌ Failed</div>
            </div>
        </div>
        
        <div class="stats-grid" style="grid-template-columns:repeat(2, 1fr);">
            <div class="stat-card purple">
                <div class="number">₦<?php echo number_format($paid_amount, 2); ?></div>
                <div class="label">✅ Total Paid</div>
            </div>
            <div class="stat-card blue">
                <div class="number"><?php echo $unique_students; ?></div>
                <div class="label">👥 Unique Students Paid</div>
            </div>
        </div>

        <!-- FILTERS -->
        <div class="card">
            <h2>🔍 Filter Report</h2>
            <form method="GET">
                <input type="hidden" name="report_type" value="<?php echo htmlspecialchars($report_type); ?>">
                <div class="filter-bar">
                    <div class="form-group">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="form-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="form-group">
                        <label>Combination</label>
                        <select name="combination">
                            <option value="">All</option>
                            <?php foreach ($combinations_list as $c): ?>
                                <option value="<?php echo $c; ?>" <?php echo ($filter_comb == $c) ? 'selected' : ''; ?>><?php echo $c; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Level</label>
                        <select name="level">
                            <option value="">All</option>
                            <?php foreach ($level_options as $lvl): ?>
                                <option value="<?php echo $lvl; ?>" <?php echo ($filter_level == $lvl) ? 'selected' : ''; ?>><?php echo $lvl; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Session</label>
                        <select name="session">
                            <option value="">All</option>
                            <?php foreach ($session_options as $sess): ?>
                                <option value="<?php echo $sess; ?>" <?php echo ($filter_session == $sess) ? 'selected' : ''; ?>><?php echo $sess; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">All</option>
                            <option value="paid" <?php echo ($filter_status == 'paid') ? 'selected' : ''; ?>>Paid</option>
                            <option value="pending" <?php echo ($filter_status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="failed" <?php echo ($filter_status == 'failed') ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Payment Type</label>
                        <select name="payment_type">
                            <option value="">All</option>
                            <?php foreach ($type_options as $t): ?>
                                <option value="<?php echo $t; ?>" <?php echo ($filter_type == $t) ? 'selected' : ''; ?>><?php echo $t; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-green" style="width:100%;">🔍 Apply Filter</button>
                    </div>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:8px;">
                    <a href="admin_payment_reports.php" class="btn btn-orange btn-sm">🔄 Reset</a>
                    <a href="?export_csv=1&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&combination=<?php echo urlencode($filter_comb); ?>&level=<?php echo urlencode($filter_level); ?>&session=<?php echo urlencode($filter_session); ?>&status=<?php echo urlencode($filter_status); ?>&payment_type=<?php echo urlencode($filter_type); ?>" 
                       class="btn btn-blue btn-sm">
                        <i class="fas fa-download"></i> Export CSV
                    </a>
                    <button onclick="window.print()" class="btn btn-green btn-sm">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </div>
            </form>
        </div>

        <!-- SUMMARY BY PAYMENT TYPE -->
        <div class="two-col">
            <div class="card">
                <h2>💳 Summary by Payment Type</h2>
                <?php if ($by_type->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Payment Type</th>
                            <th style="text-align:center;">Count</th>
                            <th style="text-align:right;">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($t = $by_type->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($t['payment_type']); ?></strong></td>
                            <td style="text-align:center;"><?php echo $t['count']; ?></td>
                            <td class="amount">₦<?php echo number_format($t['total'], 2); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-inbox"></i><p>No data</p></div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>📚 Summary by Level</h2>
                <?php if ($by_level->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Level</th>
                            <th style="text-align:center;">Count</th>
                            <th style="text-align:right;">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($l = $by_level->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($l['level']); ?></strong></td>
                            <td style="text-align:center;"><?php echo $l['count']; ?></td>
                            <td class="amount">₦<?php echo number_format($l['total'], 2); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-inbox"></i><p>No data</p></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- SUMMARY BY COMBINATION -->
        <div class="card">
            <h2>🎓 Summary by Combination</h2>
            <?php if ($by_comb->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Combination</th>
                        <th style="text-align:center;">Count</th>
                        <th style="text-align:right;">Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($c = $by_comb->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($c['combination']); ?></strong></td>
                        <td style="text-align:center;"><?php echo $c['count']; ?></td>
                        <td class="amount">₦<?php echo number_format($c['total'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-inbox"></i><p>No data</p></div>
            <?php endif; ?>
        </div>

        <!-- DAILY BREAKDOWN -->
        <div class="card">
            <h2>📅 Daily Breakdown (Last 30 Days in Range)</h2>
            <?php if ($daily->num_rows > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th style="text-align:center;">Transactions</th>
                            <th style="text-align:right;">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($d = $daily->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo date('l, F j, Y', strtotime($d['payment_date'])); ?></strong></td>
                            <td style="text-align:center;"><?php echo $d['count']; ?></td>
                            <td class="amount">₦<?php echo number_format($d['total'], 2); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-inbox"></i><p>No data</p></div>
            <?php endif; ?>
        </div>

        <!-- DETAILED PAYMENTS -->
        <div class="card">
            <h2>💰 Detailed Payments (<?php echo $payments->num_rows; ?> shown)</h2>
            <?php if ($payments->num_rows > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Reg No</th>
                            <th>Student Name</th>
                            <th>Combination</th>
                            <th>Level</th>
                            <th>Amount</th>
                            <th>Type</th>
                            <th>Session</th>
                            <th>Reference</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Recorded By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($p = $payments->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><strong><?php echo htmlspecialchars($p['reg_no']); ?></strong></td>
                            <td><?php echo htmlspecialchars($p['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($p['combination']); ?></td>
                            <td><?php echo htmlspecialchars($p['level']); ?></td>
                            <td class="amount">₦<?php echo number_format($p['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($p['payment_type']); ?></td>
                            <td><?php echo htmlspecialchars($p['session']); ?></td>
                            <td><?php echo htmlspecialchars($p['reference_no']); ?></td>
                            <td><?php echo date('d-M-Y', strtotime($p['payment_date'])); ?></td>
                            <td><?php echo ucfirst($p['status']); ?></td>
                            <td><?php echo htmlspecialchars($p['recorded_by']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-money-bill-wave"></i><p>No payments in this period</p></div>
            <?php endif; ?>
        </div>
        
    </div>
</body>
</html>