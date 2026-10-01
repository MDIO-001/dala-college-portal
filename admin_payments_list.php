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
// PAGINATION
// ============================================
$per_page = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

// ============================================
// FILTERS
// ============================================
$filter_comb = isset($_GET['combination']) ? mysqli_real_escape_string($conn, $_GET['combination']) : '';
$filter_level = isset($_GET['level']) ? mysqli_real_escape_string($conn, $_GET['level']) : '';
$filter_session = isset($_GET['session']) ? mysqli_real_escape_string($conn, $_GET['session']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$filter_type = isset($_GET['payment_type']) ? mysqli_real_escape_string($conn, $_GET['payment_type']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? mysqli_real_escape_string($conn, $_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? mysqli_real_escape_string($conn, $_GET['date_to']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date_desc';

$where = "WHERE 1=1";
if (!empty($filter_comb)) $where .= " AND combination = '$filter_comb'";
if (!empty($filter_level)) $where .= " AND level = '$filter_level'";
if (!empty($filter_session)) $where .= " AND session = '$filter_session'";
if (!empty($filter_status)) $where .= " AND status = '$filter_status'";
if (!empty($filter_type)) $where .= " AND payment_type = '$filter_type'";
if (!empty($search)) $where .= " AND (student_name LIKE '%$search%' OR reg_no LIKE '%$search%' OR reference_no LIKE '%$search%')";
if (!empty($date_from)) $where .= " AND payment_date >= '$date_from'";
if (!empty($date_to)) $where .= " AND payment_date <= '$date_to'";

// Sorting
$order_by = "ORDER BY payment_date DESC, id DESC";
switch ($sort) {
    case 'date_asc': $order_by = "ORDER BY payment_date ASC, id ASC"; break;
    case 'amount_desc': $order_by = "ORDER BY amount DESC"; break;
    case 'amount_asc': $order_by = "ORDER BY amount ASC"; break;
    case 'name_asc': $order_by = "ORDER BY student_name ASC"; break;
    case 'name_desc': $order_by = "ORDER BY student_name DESC"; break;
}

// ============================================
// COUNT TOTAL
// ============================================
$count_query = "SELECT COUNT(*) AS total FROM payments $where";
$total_records = mysqli_fetch_assoc(mysqli_query($conn, $count_query))['total'];
$total_pages = ceil($total_records / $per_page);

// ============================================
// GET PAYMENTS
// ============================================
$payments_query = "SELECT * FROM payments $where $order_by LIMIT $per_page OFFSET $offset";
$payments_result = mysqli_query($conn, $payments_query);

// ============================================
// STATS
// ============================================
$total_amount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) AS t FROM payments $where"))['t'];
$count_paid = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM payments $where AND status='paid'"))['c'];
$count_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM payments $where AND status='pending'"))['c'];
$count_failed = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM payments $where AND status='failed'"))['c'];

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
    <title>All Payments - Dala College</title>
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
        .card h2 { color:#0d2818; margin-bottom:15px; font-size:1.2rem; }
        
        .stats-grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:15px; margin-bottom:20px; }
        .stat-card { background:white; padding:20px; border-radius:12px; text-align:center; box-shadow:0 2px 10px rgba(0,0,0,0.05); border-left:4px solid #2e7d32; }
        .stat-card .number { font-size:1.6rem; font-weight:900; color:#2e7d32; }
        .stat-card .label { font-size:0.78rem; color:#6a8f6a; font-weight:700; }
        .stat-card.blue { border-left-color:#1976d2; }
        .stat-card.blue .number { color:#1976d2; }
        .stat-card.orange { border-left-color:#f57c00; }
        .stat-card.orange .number { color:#f57c00; }
        .stat-card.red { border-left-color:#c62828; }
        .stat-card.red .number { color:#c62828; }
        
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
        
        .table-container { overflow:auto; max-height:600px; border-radius:8px; }
        table { width:100%; border-collapse:collapse; min-width:1300px; }
        table th { background:#0d2818; color:white; padding:10px 12px; text-align:left; font-size:0.72rem; text-transform:uppercase; position:sticky; top:0; }
        table td { padding:8px 12px; border-bottom:1px solid #e0e0e0; font-size:0.83rem; }
        table tr:hover { background:#f8faf8; }
        
        .badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:0.7rem; font-weight:700; }
        .badge-paid { background:#e8f5e9; color:#2e7d32; }
        .badge-pending { background:#fff8e1; color:#f57c00; }
        .badge-failed { background:#ffebee; color:#c62828; }
        
        .pagination { display:flex; gap:5px; justify-content:center; margin-top:20px; flex-wrap:wrap; }
        .pagination a, .pagination span { padding:8px 14px; background:white; border:2px solid #dce8dc; border-radius:6px; text-decoration:none; color:#0d2818; font-weight:700; font-size:0.85rem; }
        .pagination a:hover { background:#2e7d32; color:white; border-color:#2e7d32; }
        .pagination .active { background:#2e7d32; color:white; border-color:#2e7d32; }
        .pagination .disabled { opacity:0.5; cursor:not-allowed; }
        
        .empty-state { text-align:center; padding:40px; color:#6a8f6a; }
        .empty-state i { font-size:3rem; color:#dce8dc; display:block; margin-bottom:15px; }
        
        @media (max-width:768px) {
            .filter-bar { grid-template-columns:1fr 1fr; }
            .stats-grid { grid-template-columns:1fr 1fr; }
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
                <a href="admin_payments_list.php" class="active">📋 All Payments</a>
                <a href="admin_payment_reports.php">📊 Reports</a>
                <a href="staff_dashboard.php">🏠 Dashboard</a>
                <a href="logout.php" style="background:#c62828; color:white;">Logout</a>
            </nav>
            <div>
                <span class="admin-badge">👤 <?php echo htmlspecialchars($staff_name); ?> (<?php echo htmlspecialchars($user_role); ?>)</span>
            </div>
        </div>

        <h2 style="color:#0d2818; margin-bottom:5px;">📋 All Payments</h2>
        <p style="color:#6a8f6a; margin-bottom:20px; font-size:0.9rem;">
            Complete list of all payments recorded in the system
        </p>

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
                <div class="number"><?php echo $count_pending; ?></div>
                <div class="label">⏳ Pending</div>
            </div>
            <div class="stat-card red">
                <div class="number"><?php echo $count_failed; ?></div>
                <div class="label">❌ Failed</div>
            </div>
        </div>

        <!-- FILTERS -->
        <div class="card">
            <h2>🔍 Filter & Sort</h2>
            <form method="GET">
                <div class="filter-bar">
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
                        <label>Date From</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="form-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="form-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Name, reg no, ref..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <label>Sort By</label>
                        <select name="sort">
                            <option value="date_desc" <?php echo ($sort == 'date_desc') ? 'selected' : ''; ?>>Date (Newest)</option>
                            <option value="date_asc" <?php echo ($sort == 'date_asc') ? 'selected' : ''; ?>>Date (Oldest)</option>
                            <option value="amount_desc" <?php echo ($sort == 'amount_desc') ? 'selected' : ''; ?>>Amount (High → Low)</option>
                            <option value="amount_asc" <?php echo ($sort == 'amount_asc') ? 'selected' : ''; ?>>Amount (Low → High)</option>
                            <option value="name_asc" <?php echo ($sort == 'name_asc') ? 'selected' : ''; ?>>Name (A → Z)</option>
                            <option value="name_desc" <?php echo ($sort == 'name_desc') ? 'selected' : ''; ?>>Name (Z → A)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-green" style="width:100%;">🔍 Apply</button>
                    </div>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:8px;">
                    <a href="admin_payments_list.php" class="btn btn-orange btn-sm">🔄 Reset</a>
                    <a href="?export_csv=1" class="btn btn-blue btn-sm"><i class="fas fa-download"></i> Export CSV</a>
                </div>
            </form>
        </div>

        <!-- PAYMENTS TABLE -->
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; margin-bottom:15px;">
                <h2 style="margin-bottom:0;">💰 Payments (<?php echo $total_records; ?> total)</h2>
                <button onclick="window.print()" class="btn btn-green btn-sm">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
            
            <?php if ($payments_result && mysqli_num_rows($payments_result) > 0): ?>
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
                            <th>Semester</th>
                            <th>Reference</th>
                            <th>Bank</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Study_Center</th>
                            <th>Recorded By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = $offset + 1; while ($p = mysqli_fetch_assoc($payments_result)): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><strong><?php echo htmlspecialchars($p['reg_no']); ?></strong></td>
                            <td><?php echo htmlspecialchars($p['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($p['combination']); ?></td>
                            <td><?php echo htmlspecialchars($p['level']); ?></td>
                            <td><strong>₦<?php echo number_format($p['amount'], 2); ?></strong></td>
                            <td><?php echo htmlspecialchars($p['payment_type']); ?></td>
                            <td><?php echo htmlspecialchars($p['session']); ?></td>
                            <td><?php echo htmlspecialchars($p['semester']); ?></td>
                            <td><?php echo htmlspecialchars($p['reference_no']); ?></td>
                            <td><?php echo htmlspecialchars($p['bank']); ?></td>
                            <td><?php echo date('d-M-Y', strtotime($p['payment_date'])); ?></td>
                            <td>
                                <span class="badge badge-<?php echo strtolower($p['status']); ?>">
                                    <?php echo ucfirst($p['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($p['recorded_by']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- PAGINATION -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&<?php echo http_build_query(array_filter(['combination'=>$filter_comb,'level'=>$filter_level,'session'=>$filter_session,'status'=>$filter_status,'payment_type'=>$filter_type,'search'=>$search,'date_from'=>$date_from,'date_to'=>$date_to,'sort'=>$sort])); ?>">← Prev</a>
                <?php endif; ?>
                
                <?php 
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                for ($p = $start; $p <= $end; $p++): 
                ?>
                    <?php if ($p == $page): ?>
                        <span class="active"><?php echo $p; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $p; ?>&<?php echo http_build_query(array_filter(['combination'=>$filter_comb,'level'=>$filter_level,'session'=>$filter_session,'status'=>$filter_status,'payment_type'=>$filter_type,'search'=>$search,'date_from'=>$date_from,'date_to'=>$date_to,'sort'=>$sort])); ?>"><?php echo $p; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&<?php echo http_build_query(array_filter(['combination'=>$filter_comb,'level'=>$filter_level,'session'=>$filter_session,'status'=>$filter_status,'payment_type'=>$filter_type,'search'=>$search,'date_from'=>$date_from,'date_to'=>$date_to,'sort'=>$sort])); ?>">Next →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-money-bill-wave"></i>
                    <h3>Babu payments</h3>
                    <p>Babu payment da ya dace da wannan filter ɗin.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>