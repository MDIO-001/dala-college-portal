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
// ADD ITEM
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_item'])) {
    $item_name = mysqli_real_escape_string($conn, trim($_POST['item_name']));
    $item_code = strtoupper(mysqli_real_escape_string($conn, trim($_POST['item_code'])));
    $amount = floatval($_POST['amount']);
    $level = mysqli_real_escape_string($conn, $_POST['level']);
    $session = mysqli_real_escape_string($conn, $_POST['session']);
    $is_mandatory = intval($_POST['is_mandatory']);
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));

    // Check duplicate code
    $check = mysqli_query($conn, "SELECT id FROM payment_items WHERE item_code = '$item_code'");
    if (mysqli_num_rows($check) > 0) {
        $message = "❌ Item code '$item_code' already exists!";
        $message_type = 'error';
    } else {
        $insert = "INSERT INTO payment_items (item_name, item_code, amount, level, session, is_mandatory, is_active, description) 
                   VALUES ('$item_name', '$item_code', $amount, '$level', '$session', $is_mandatory, 1, '$description')";
        if (mysqli_query($conn, $insert)) {
            $message = "✅ Payment item added successfully!";
            $message_type = 'success';
        } else {
            $message = "❌ Error: " . mysqli_error($conn);
            $message_type = 'error';
        }
    }
}

// ============================================
// UPDATE ITEM
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_item'])) {
    $id = intval($_POST['id']);
    $item_name = mysqli_real_escape_string($conn, trim($_POST['item_name']));
    $item_code = strtoupper(mysqli_real_escape_string($conn, trim($_POST['item_code'])));
    $amount = floatval($_POST['amount']);
    $level = mysqli_real_escape_string($conn, $_POST['level']);
    $session = mysqli_real_escape_string($conn, $_POST['session']);
    $is_mandatory = intval($_POST['is_mandatory']);
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));

    // Check duplicate code (excluding current)
    $check = mysqli_query($conn, "SELECT id FROM payment_items WHERE item_code = '$item_code' AND id != $id");
    if (mysqli_num_rows($check) > 0) {
        $message = "❌ Item code '$item_code' already used by another item!";
        $message_type = 'error';
    } else {
        $update = "UPDATE payment_items SET 
                    item_name = '$item_name',
                    item_code = '$item_code',
                    amount = $amount,
                    level = '$level',
                    session = '$session',
                    is_mandatory = $is_mandatory,
                    description = '$description'
                   WHERE id = $id";
        if (mysqli_query($conn, $update)) {
            $message = "✅ Payment item updated successfully!";
            $message_type = 'success';
        } else {
            $message = "❌ Error: " . mysqli_error($conn);
            $message_type = 'error';
        }
    }
}

// ============================================
// TOGGLE ACTIVE
// ============================================
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    mysqli_query($conn, "UPDATE payment_items SET is_active = NOT is_active WHERE id = $id");
    $message = "🔄 Status changed!";
    $message_type = 'success';
}

// ============================================
// DELETE ITEM
// ============================================
if (isset($_GET['delete']) && is_numeric($_GET['delete']) && $user_role == 'admin') {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM payment_items WHERE id = $id");
    $message = "🗑️ Payment item deleted!";
    $message_type = 'success';
}

// ============================================
// EXPORT CSV
// ============================================
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payment_items_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['S/N', 'Item Name', 'Item Code', 'Amount', 'Level', 'Session', 'Mandatory', 'Status', 'Description']);
    
    $export = mysqli_query($conn, "SELECT * FROM payment_items ORDER BY id ASC");
    $sn = 1;
    while ($e = mysqli_fetch_assoc($export)) {
        fputcsv($output, [
            $sn++,
            $e['item_name'],
            $e['item_code'],
            number_format($e['amount'], 2),
            $e['level'],
            $e['session'],
            $e['is_mandatory'] ? 'Yes' : 'No',
            $e['is_active'] ? 'Active' : 'Inactive',
            $e['description']
        ]);
    }
    fclose($output);
    exit();
}

// ============================================
// FETCH ITEMS
// ============================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_status = isset($_GET['filter_status']) ? mysqli_real_escape_string($conn, $_GET['filter_status']) : '';
$filter_level = isset($_GET['filter_level']) ? mysqli_real_escape_string($conn, $_GET['filter_level']) : '';

$where = "WHERE 1=1";
if (!empty($search)) $where .= " AND (item_name LIKE '%$search%' OR item_code LIKE '%$search%')";
if (!empty($filter_level)) $where .= " AND level = '$filter_level'";
if ($filter_status === 'active') $where .= " AND is_active = 1";
if ($filter_status === 'inactive') $where .= " AND is_active = 0";

$items = mysqli_query($conn, "SELECT * FROM payment_items $where ORDER BY id ASC");

// Stats
$total_items = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM payment_items"))['c'];
$active_items = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM payment_items WHERE is_active = 1"))['c'];
$mandatory_items = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM payment_items WHERE is_mandatory = 1"))['c'];
$total_expected = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) AS t FROM payment_items WHERE is_mandatory = 1"))['t'];

// Edit item
$edit_item = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_item = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM payment_items WHERE id = $edit_id"));
}

$level_options = ['NCE I', 'NCE II', 'NCE III', '400 Level', '500 Level'];
$session_options = ['2023/2024', '2024/2025', '2025/2026', '2026/2027'];

date_default_timezone_set('Africa/Lagos');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Items - Dala College</title>
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
        .stat-card.blue { border-left-color:#1976d2; }
        .stat-card.blue .number { color:#1976d2; }
        .stat-card.orange { border-left-color:#f57c00; }
        .stat-card.orange .number { color:#f57c00; }
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
        .badge-active { background:#e8f5e9; color:#2e7d32; }
        .badge-inactive { background:#ffebee; color:#c62828; }
        .badge-mandatory { background:#fff3e0; color:#e65100; }
        .badge-optional { background:#e3f2fd; color:#0d47a1; }
        
        .edit-highlight { background:#fff9c4 !important; }
        
        .empty-state { text-align:center; padding:40px; color:#6a8f6a; }
        .empty-state i { font-size:3rem; color:#dce8dc; display:block; margin-bottom:15px; }
        
        @media (max-width:900px) {
            .form-grid { grid-template-columns:1fr 1fr; }
            .stats-grid { grid-template-columns:1fr 1fr; }
            .filter-bar { grid-template-columns:1fr 1fr; }
        }
        @media (max-width:600px) {
            .form-grid, .filter-bar, .stats-grid { grid-template-columns:1fr; }
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
                <a href="admin_payment_items.php" class="active">⚙️ Payment Items</a>
                <a href="admin_payment_reports.php">📊 Reports</a>
                <a href="staff_dashboard.php">🏠 Dashboard</a>
                <a href="logout.php" style="background:#c62828; color:white;">Logout</a>
            </nav>
            <div>
                <span class="admin-badge">👤 <?php echo htmlspecialchars($staff_name); ?> (<?php echo htmlspecialchars($user_role); ?>)</span>
            </div>
        </div>

        <h2 style="color:#0d2818; margin-bottom:5px;">⚙️ Payment Items Management</h2>
        <p style="color:#6a8f6a; margin-bottom:20px; font-size:0.9rem;">
            Sarrafa duk abubuwan biya (Tuition, Acceptance, ID Card, Library, da sauransu)
        </p>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $total_items; ?></div>
                <div class="label">📋 Total Items</div>
            </div>
            <div class="stat-card blue">
                <div class="number"><?php echo $active_items; ?></div>
                <div class="label">✅ Active</div>
            </div>
            <div class="stat-card orange">
                <div class="number"><?php echo $mandatory_items; ?></div>
                <div class="label">⚠️ Mandatory</div>
            </div>
            <div class="stat-card purple">
                <div class="number">₦<?php echo number_format($total_expected, 2); ?></div>
                <div class="label">💰 Total Mandatory</div>
            </div>
        </div>

        <!-- ADD / EDIT FORM -->
        <div class="card">
            <h2><?php echo $edit_item ? '✏️ Edit Payment Item' : '➕ Add New Payment Item'; ?></h2>
            <form method="POST">
                <?php if ($edit_item): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_item['id']; ?>">
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Item Name *</label>
                        <input type="text" name="item_name" required 
                               value="<?php echo $edit_item ? htmlspecialchars($edit_item['item_name']) : ''; ?>"
                               placeholder="e.g. Tuition Fee">
                    </div>
                    <div class="form-group">
                        <label>Item Code *</label>
                        <input type="text" name="item_code" required 
                               value="<?php echo $edit_item ? htmlspecialchars($edit_item['item_code']) : ''; ?>"
                               placeholder="e.g. TUI" style="text-transform:uppercase;">
                    </div>
                    <div class="form-group">
                        <label>Amount (₦) *</label>
                        <input type="number" name="amount" step="0.01" min="0" required 
                               value="<?php echo $edit_item ? $edit_item['amount'] : ''; ?>"
                               placeholder="e.g. 60000">
                    </div>
                    <div class="form-group">
                        <label>Level</label>
                        <select name="level">
                            <option value="">All Levels</option>
                            <?php foreach ($level_options as $lvl): ?>
                                <option value="<?php echo $lvl; ?>" <?php echo ($edit_item && $edit_item['level'] == $lvl) ? 'selected' : ''; ?>>
                                    <?php echo $lvl; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Session</label>
                        <select name="session">
                            <option value="">All Sessions</option>
                            <?php foreach ($session_options as $sess): ?>
                                <option value="<?php echo $sess; ?>" <?php echo ($edit_item && $edit_item['session'] == $sess) ? 'selected' : ''; ?>>
                                    <?php echo $sess; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Is Mandatory?</label>
                        <select name="is_mandatory">
                            <option value="1" <?php echo ($edit_item && $edit_item['is_mandatory'] == 1) ? 'selected' : ''; ?>>Yes — Mandatory</option>
                            <option value="0" <?php echo ($edit_item && $edit_item['is_mandatory'] == 0) ? 'selected' : ''; ?>>No — Optional</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Description</label>
                        <textarea name="description" placeholder="Brief description of this payment item..."><?php echo $edit_item ? htmlspecialchars($edit_item['description']) : ''; ?></textarea>
                    </div>
                </div>
                
                <div style="margin-top:15px; display:flex; gap:10px; flex-wrap:wrap;">
                    <?php if ($edit_item): ?>
                        <button type="submit" name="update_item" class="btn btn-blue">💾 Update Item</button>
                        <a href="admin_payment_items.php" class="btn btn-gray">✖ Cancel</a>
                    <?php else: ?>
                        <button type="submit" name="add_item" class="btn btn-green">➕ Add Item</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- FILTERS -->
        <div class="card">
            <h2>🔍 Search & Filter</h2>
            <form method="GET">
                <div class="filter-bar">
                    <div class="form-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Item name or code..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <label>Level</label>
                        <select name="filter_level">
                            <option value="">All Levels</option>
                            <?php foreach ($level_options as $lvl): ?>
                                <option value="<?php echo $lvl; ?>" <?php echo ($filter_level == $lvl) ? 'selected' : ''; ?>><?php echo $lvl; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="filter_status">
                            <option value="">All</option>
                            <option value="active" <?php echo ($filter_status == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($filter_status == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-green" style="width:100%;">🔍 Filter</button>
                    </div>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:8px;">
                    <a href="admin_payment_items.php" class="btn btn-orange btn-sm">🔄 Reset</a>
                    <a href="?export_csv=1" class="btn btn-blue btn-sm"><i class="fas fa-download"></i> Export CSV</a>
                    <button onclick="window.print()" class="btn btn-green btn-sm"><i class="fas fa-print"></i> Print</button>
                </div>
            </form>
        </div>

        <!-- ITEMS TABLE -->
        <div class="card">
            <h2>📋 All Payment Items (<?php echo $items->num_rows; ?> shown)</h2>
            
            <?php if ($items->num_rows > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Code</th>
                            <th>Item Name</th>
                            <th>Amount</th>
                            <th>Level</th>
                            <th>Session</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($item = $items->fetch_assoc()): ?>
                        <tr class="<?php echo ($edit_item && $edit_item['id'] == $item['id']) ? 'edit-highlight' : ''; ?>">
                            <td><?php echo $i++; ?></td>
                            <td><strong><?php echo htmlspecialchars($item['item_code']); ?></strong></td>
                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                            <td class="amount">₦<?php echo number_format($item['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($item['level'] ?: 'All'); ?></td>
                            <td><?php echo htmlspecialchars($item['session'] ?: 'All'); ?></td>
                            <td>
                                <?php if ($item['is_mandatory']): ?>
                                    <span class="badge badge-mandatory">⚠️ Mandatory</span>
                                <?php else: ?>
                                    <span class="badge badge-optional">Optional</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['is_active']): ?>
                                    <span class="badge badge-active">✅ Active</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">🚫 Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:0.78rem; color:#666; max-width:200px;">
                                <?php echo htmlspecialchars($item['description'] ?: '—'); ?>
                            </td>
                            <td style="white-space:nowrap;">
                                <a href="?edit=<?php echo $item['id']; ?>" class="btn btn-blue btn-sm" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?toggle=<?php echo $item['id']; ?>" class="btn btn-orange btn-sm" title="Toggle Status" 
                                   onclick="return confirm('Change status of this item?')">
                                    <i class="fas fa-power-off"></i>
                                </a>
                                <?php if ($user_role == 'admin'): ?>
                                <a href="?delete=<?php echo $item['id']; ?>" class="btn btn-red btn-sm" title="Delete" 
                                   onclick="return confirm('Are you sure you want to DELETE this payment item? This cannot be undone!')">
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
                    <i class="fas fa-cog"></i>
                    <h3>Babu Payment Items</h3>
                    <p>Babu wani abu da aka sanya. Yi amfani da form ɗin da ke sama don ƙara sabon abu.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>