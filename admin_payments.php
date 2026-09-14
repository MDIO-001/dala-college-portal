<?php
session_start();
include 'connect.php';
include 'result_functions.php';

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
    echo "<p style='margin:15px 0;'>Role ɗinka: <strong>" . htmlspecialchars($user_role) . "</strong></p>";
    echo "<a href='staff_dashboard.php' style='display:inline-block; padding:10px 25px; background:#2e7d32; color:white; text-decoration:none; border-radius:8px; font-weight:bold;'>Koma Dashboard</a>";
    echo "</div>";
    exit();
}

$staff_name = $_SESSION['fullname'] ?? 'Staff';

$message = '';
$message_type = '';

// ============================================
// ADD PAYMENT
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_payment'])) {
    $student_id = intval($_POST['student_id']);
    $amount = floatval($_POST['amount']);
    $payment_type = mysqli_real_escape_string($conn, $_POST['payment_type']);
    $session = mysqli_real_escape_string($conn, $_POST['session']);
    $semester = mysqli_real_escape_string($conn, $_POST['semester']);
    $reference_no = mysqli_real_escape_string($conn, $_POST['reference_no']);
    $bank = mysqli_real_escape_string($conn, $_POST['bank']);
    $payment_date = mysqli_real_escape_string($conn, $_POST['payment_date']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $s = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM students WHERE id = $student_id"));
    
    if ($s) {
        $insert = "INSERT INTO payments (
            student_id, reg_no, student_name, combination, level, 
            amount, payment_type, session, semester, reference_no, 
            bank, payment_date, status, recorded_by
        ) VALUES (
            $student_id, '{$s['reg_no']}', '{$s['fullname']}', '{$s['combination']}', '{$s['level']}', 
            $amount, '$payment_type', '$session', '$semester', '$reference_no', 
            '$bank', '$payment_date', '$status', '$staff_name'
        )";
        
        if (mysqli_query($conn, $insert)) {
            $message = "✅ Payment recorded successfully!";
            $message_type = 'success';
        } else {
            $message = "❌ Error: " . mysqli_error($conn);
            $message_type = 'error';
        }
    }
}

// ============================================
// DELETE PAYMENT
// ============================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if (mysqli_query($conn, "DELETE FROM payments WHERE id = $id")) {
        $message = "🗑️ Payment deleted!";
        $message_type = 'success';
    }
}

// ============================================
// EXPORT CSV
// ============================================
if (isset($_GET['export_csv'])) {
    $filter_comb = mysqli_real_escape_string($conn, $_GET['combination'] ?? '');
    $filter_level = mysqli_real_escape_string($conn, $_GET['level'] ?? '');
    $filter_session = mysqli_real_escape_string($conn, $_GET['session'] ?? '');
    $search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
    
    $where = "WHERE 1=1";
    if (!empty($filter_comb)) $where .= " AND combination = '$filter_comb'";
    if (!empty($filter_level)) $where .= " AND level = '$filter_level'";
    if (!empty($filter_session)) $where .= " AND session = '$filter_session'";
    if (!empty($search)) $where .= " AND (student_name LIKE '%$search%' OR reg_no LIKE '%$search%' OR reference_no LIKE '%$search%')";
    
    $export_query = mysqli_query($conn, "SELECT * FROM payments $where ORDER BY payment_date DESC, id DESC");
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payments_' . date('Y-m-d') . '.csv"');
    
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
// FILTERS
// ============================================
$filter_comb = isset($_GET['combination']) ? mysqli_real_escape_string($conn, $_GET['combination']) : '';
$filter_level = isset($_GET['level']) ? mysqli_real_escape_string($conn, $_GET['level']) : '';
$filter_session = isset($_GET['session']) ? mysqli_real_escape_string($conn, $_GET['session']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$where = "WHERE 1=1";
if (!empty($filter_comb)) $where .= " AND combination = '$filter_comb'";
if (!empty($filter_level)) $where .= " AND level = '$filter_level'";
if (!empty($filter_session)) $where .= " AND session = '$filter_session'";
if (!empty($filter_status)) $where .= " AND status = '$filter_status'";
if (!empty($search)) $where .= " AND (student_name LIKE '%$search%' OR reg_no LIKE '%$search%' OR reference_no LIKE '%$search%')";

$payments_query = "SELECT * FROM payments $where ORDER BY payment_date DESC, id DESC LIMIT 500";
$payments_result = mysqli_query($conn, $payments_query);
$total_payments = mysqli_num_rows($payments_result);

// ============================================
// STATS
// ============================================
$total_amount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM payments $where"))['total'] ?? 0;
$count_all = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM payments"))['c'];
$count_paid = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM payments WHERE status = 'paid'"))['c'];
$count_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM payments WHERE status = 'pending'"))['c'];

// ============================================
// STUDENTS DROPDOWN
// ============================================
$students_list = mysqli_query($conn, "SELECT id, reg_no, fullname, combination, level FROM students WHERE status IN ('active', 'approved') ORDER BY fullname");

// ============================================
// COMBINATIONS
// ============================================
$combinations_list = [];
$cq = mysqli_query($conn, "SELECT DISTINCT combination FROM students WHERE combination IS NOT NULL AND combination != '' ORDER BY combination");
while ($c = mysqli_fetch_assoc($cq)) $combinations_list[] = $c['combination'];

$session_options = ['2023/2024', '2024/2025', '2025/2026', '2026/2027'];
$level_options = ['NCE I', 'NCE II', 'NCE III'];

date_default_timezone_set('Africa/Lagos');
$current_date = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment List - Dala College</title>
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
        .topbar nav .logout { background:#c62828; color:white !important; }
        .topbar .admin-badge { background:#c62828; color:white; padding:6px 18px; border-radius:20px; font-size:0.8rem; font-weight:600; }
        
        .card { background:white; padding:25px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); margin-bottom:20px; }
        .card h2 { color:#0d2818; margin-bottom:15px; font-size:1.2rem; }
        
        .message { padding:12px 20px; border-radius:8px; margin-bottom:15px; font-weight:600; }
        .message.success { background:#e8f5e9; color:#2e7d32; border-left:4px solid #2e7d32; }
        .message.error { background:#ffebee; color:#c62828; border-left:4px solid #c62828; }
        
        .stats-grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:15px; margin-bottom:20px; }
        .stat-card { background:white; padding:20px; border-radius:12px; text-align:center; box-shadow:0 2px 10px rgba(0,0,0,0.05); border-left:4px solid #2e7d32; }
        .stat-card .number { font-size:1.8rem; font-weight:900; color:#2e7d32; }
        .stat-card .label { font-size:0.8rem; color:#6a8f6a; font-weight:700; }
        .stat-card.blue { border-left-color:#1976d2; }
        .stat-card.blue .number { color:#1976d2; }
        .stat-card.orange { border-left-color:#f57c00; }
        .stat-card.orange .number { color:#f57c00; }
        .stat-card.purple { border-left-color:#7b1fa2; }
        .stat-card.purple .number { color:#7b1fa2; }
        
        .form-grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:12px; }
        .form-group label { display:block; font-weight:700; color:#0d2818; margin-bottom:5px; font-size:0.8rem; }
        .form-group input, .form-group select { width:100%; padding:9px 12px; border:2px solid #dce8dc; border-radius:8px; font-size:0.85rem; }
        .form-group input:focus, .form-group select:focus { border-color:#2e7d32; outline:none; }
        
        .btn { padding:10px 20px; border:none; border-radius:8px; font-weight:700; font-size:0.85rem; cursor:pointer; text-decoration:none; display:inline-block; transition:all 0.3s ease; }
        .btn:hover { transform:translateY(-2px); }
        .btn-green { background:#2e7d32; color:white; }
        .btn-blue { background:#1976d2; color:white; }
        .btn-red { background:#c62828; color:white; }
        .btn-orange { background:#f57c00; color:white; }
        .btn-sm { padding:5px 10px; font-size:0.75rem; }
        
        .filter-bar { display:grid; grid-template-columns:repeat(6, 1fr); gap:10px; margin-bottom:15px; align-items:end; }
        .filter-bar .form-group label { font-size:0.8rem; }
        .filter-bar select, .filter-bar input { width:100%; padding:8px 12px; border:2px solid #dce8dc; border-radius:8px; font-size:0.85rem; }
        .filter-bar button { padding:10px 20px; background:#2e7d32; color:white; border:none; border-radius:8px; font-weight:700; cursor:pointer; }
        
        .table-container { overflow:auto; max-height:600px; }
        table { width:100%; border-collapse:collapse; min-width:1200px; }
        table th { background:#0d2818; color:white; padding:10px 12px; text-align:left; font-size:0.75rem; text-transform:uppercase; position:sticky; top:0; }
        table td { padding:8px 12px; border-bottom:1px solid #e0e0e0; font-size:0.85rem; }
        table tr:hover { background:#f8faf8; }
        
        .badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:0.7rem; font-weight:700; }
        .badge-paid { background:#e8f5e9; color:#2e7d32; }
        .badge-pending { background:#fff8e1; color:#f57c00; }
        .badge-failed { background:#ffebee; color:#c62828; }
        
        .empty-state { text-align:center; padding:40px; color:#6a8f6a; }
        .empty-state i { font-size:3rem; color:#dce8dc; display:block; margin-bottom:15px; }
        
        @media (max-width:768px) {
            .form-grid { grid-template-columns:1fr; }
            .filter-bar { grid-template-columns:1fr; }
            .stats-grid { grid-template-columns:1fr 1fr; }
            .topbar { flex-direction:column; gap:10px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="topbar">
            <div>
                <span class="logo-title">DALA <span>COLLEGE</span></span>
                <span class="logo-sub">Payment Management</span>
            </div>
            <nav>
                <a href="staff_dashboard.php">Dashboard</a>
                <a href="logout.php" class="logout">Logout</a>
            </nav>
            <div>
                <span class="admin-badge">👤 <?php echo htmlspecialchars($staff_name); ?> (<?php echo htmlspecialchars($user_role); ?>)</span>
            </div>
        </div>

        <h2 style="color:#0d2818; margin-bottom:15px;">💰 Payment List</h2>
        <p style="color:#6a8f6a; margin-bottom:20px; font-size:0.9rem;">
            List of payments da duk data na students ɗin da suka yi payment
        </p>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number">₦<?php echo number_format($total_amount, 2); ?></div>
                <div class="label">💰 Total Amount</div>
            </div>
            <div class="stat-card blue">
                <div class="number"><?php echo $count_all; ?></div>
                <div class="label">📊 Total Payments</div>
            </div>
            <div class="stat-card orange">
                <div class="number"><?php echo $count_paid; ?></div>
                <div class="label">✅ Paid</div>
            </div>
            <div class="stat-card purple">
                <div class="number"><?php echo $count_pending; ?></div>
                <div class="label">⏳ Pending</div>
            </div>
        </div>

        <!-- ADD PAYMENT -->
        <?php if ($user_role == 'admin' || $user_role == 'Accountant'): ?>
        <div class="card">
            <h2>➕ Add New Payment</h2>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Student *</label>
                        <select name="student_id" required>
                            <option value="">-- Select Student --</option>
                            <?php while ($s = mysqli_fetch_assoc($students_list)): ?>
                                <option value="<?php echo $s['id']; ?>">
                                    <?php echo htmlspecialchars($s['reg_no']); ?> — <?php echo htmlspecialchars($s['fullname']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount (₦) *</label>
                        <input type="number" name="amount" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Payment Type *</label>
                        <select name="payment_type" required>
                            <option value="School Fees">School Fees</option>
                            <option value="Registration Fee">Registration Fee</option>
                            <option value="Examination Fee">Examination Fee</option>
                            <option value="Acceptance Fee">Acceptance Fee</option>
                            <option value="Hostel Fee">Hostel Fee</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Session *</label>
                        <select name="session" required>
                            <?php foreach ($session_options as $sess): ?>
                                <option value="<?php echo $sess; ?>"><?php echo $sess; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Semester *</label>
                        <select name="semester" required>
                            <option value="First Semester">First Semester</option>
                            <option value="Second Semester">Second Semester</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Reference No</label>
                        <input type="text" name="reference_no" placeholder="e.g. TRX123456">
                    </div>
                    <div class="form-group">
                        <label>Bank</label>
                        <input type="text" name="bank" placeholder="e.g. First Bank">
                    </div>
                    <div class="form-group">
                        <label>Payment Date *</label>
                        <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Status *</label>
                        <select name="status" required>
                            <option value="paid">Paid</option>
                            <option value="pending">Pending</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="add_payment" class="btn btn-green" style="margin-top:15px;">
                    <i class="fas fa-plus"></i> Add Payment
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- FILTERS -->
        <div class="card">
            <h2>🔍 Filter Payments</h2>
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
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Name, reg no, ref..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <button type="submit">🔍 Filter</button>
                    </div>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <a href="admin_payments.php" class="btn btn-orange btn-sm">Reset</a>
                    <a href="?export_csv=1&combination=<?php echo urlencode($filter_comb); ?>&level=<?php echo urlencode($filter_level); ?>&session=<?php echo urlencode($filter_session); ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search); ?>" 
                       class="btn btn-blue btn-sm">
                        <i class="fas fa-download"></i> Export CSV
                    </a>
                </div>
            </form>
        </div>

        <!-- PAYMENTS TABLE -->
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; margin-bottom:15px;">
                <h2 style="margin-bottom:0;">💰 Payments (<?php echo $total_payments; ?> shown)</h2>
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
                            <th>Payment Type</th>
                            <th>Session</th>
                            <th>Semester</th>
                            <th>Reference</th>
                            <th>Bank</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Recorded By</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($p = mysqli_fetch_assoc($payments_result)): ?>
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
                            <td>
                                <?php if ($user_role == 'admin'): ?>
                                <a href="?delete=<?php echo $p['id']; ?>" class="btn btn-red btn-sm" onclick="return confirm('Delete this payment?')" title="Delete">
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
                    <i class="fas fa-money-bill-wave"></i>
                    <h3>Babu payments</h3>
                    <p>Babu payment da aka yi don wannan filter ɗin.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>