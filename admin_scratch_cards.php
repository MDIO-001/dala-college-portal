<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

$admin_name = $_SESSION['fullname'] ?? 'Admin';
$message = '';
$message_type = '';

// ============================================
// ENABLE / DISABLE CARD
// ============================================
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $current = mysqli_fetch_assoc(mysqli_query($conn, "SELECT is_active FROM scratch_cards WHERE id = $id"));
    $new_status = ($current['is_active'] == 1) ? 0 : 1;
    mysqli_query($conn, "UPDATE scratch_cards SET is_active = $new_status WHERE id = $id");
    $message = $new_status ? "✅ An kunna card ɗin!" : "🔒 An kashe card ɗin!";
    $message_type = 'success';
}

// ============================================
// DELETE CARD
// ============================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM scratch_cards WHERE id = $id");
    $message = "🗑️ An share card ɗin!";
    $message_type = 'success';
}

// ============================================
// EXPORT CSV
// ============================================
if (isset($_GET['export_csv'])) {
    $filter_type = mysqli_real_escape_string($conn, $_GET['card_type'] ?? '');
    $filter_status = mysqli_real_escape_string($conn, $_GET['status'] ?? '');
    $filter_active = mysqli_real_escape_string($conn, $_GET['active'] ?? '');
    
    $where = "WHERE 1=1";
    if (!empty($filter_type)) $where .= " AND card_type = '$filter_type'";
    if (!empty($filter_status)) $where .= " AND status = '$filter_status'";
    if ($filter_active !== '') $where .= " AND is_active = '$filter_active'";
    
    $export_query = mysqli_query($conn, "SELECT * FROM scratch_cards $where ORDER BY card_type, id ASC");
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="scratch_cards_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['S/N', 'Card Code', 'PIN', 'Nau\'i', 'Serial No', 'Status', 'Active', 'Usage Count', 'Usage Limit', 'Expiry Date', 'Used By', 'Used At']);
    
    $sn = 1;
    while ($row = mysqli_fetch_assoc($export_query)) {
        fputcsv($output, [
            $sn++,
            $row['card_code'],
            $row['pin'],
            $row['card_type'],
            $row['serial_no'],
            $row['status'],
            ($row['is_active'] == 1) ? 'Yes' : 'No',
            $row['usage_count'] ?? 0,
            $row['usage_limit'] ?? 10,
            $row['expiry_date'] ?? '-',
            $row['used_by'] ?? '-',
            $row['used_at'] ?? '-'
        ]);
    }
    
    fclose($output);
    exit();
}

// ============================================
// FILTERS
// ============================================
$filter_type = isset($_GET['card_type']) ? mysqli_real_escape_string($conn, $_GET['card_type']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$filter_active = isset($_GET['active']) ? mysqli_real_escape_string($conn, $_GET['active']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$where = "WHERE 1=1";
if (!empty($filter_type)) $where .= " AND card_type = '$filter_type'";
if (!empty($filter_status)) $where .= " AND status = '$filter_status'";
if ($filter_active !== '') $where .= " AND is_active = '$filter_active'";
if (!empty($search)) $where .= " AND (card_code LIKE '%$search%' OR pin LIKE '%$search%')";

$cards_query = "SELECT * FROM scratch_cards $where ORDER BY card_type, id ASC LIMIT 500";
$cards_result = mysqli_query($conn, $cards_query);
$total_cards = mysqli_num_rows($cards_result);

// Stats
$count_all = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM scratch_cards"))['c'];
$count_unused = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM scratch_cards WHERE status = 'unused'"))['c'];
$count_used = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM scratch_cards WHERE status = 'used'"))['c'];
$count_inactive = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM scratch_cards WHERE is_active = 0"))['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Scratch Cards Management - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Tahoma,sans-serif; background:#f0f4f8; padding:20px; }
        .container { max-width:1400px; margin:0 auto; }
        
        .topbar { background:#0d2818; color:white; padding:15px 25px; display:flex; justify-content:space-between; align-items:center; border-radius:12px; margin-bottom:20px; flex-wrap:wrap; gap:10px; }
        .topbar h1 { font-size:1.5rem; }
        .topbar .logout { background:#c62828; color:white; padding:8px 15px; border-radius:6px; text-decoration:none; font-weight:bold; }
        
        .card { background:white; padding:25px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); margin-bottom:20px; }
        .card h2 { color:#0d2818; margin-bottom:15px; font-size:1.2rem; }
        
        .message { padding:12px 20px; border-radius:8px; margin-bottom:15px; font-weight:600; }
        .message.success { background:#e8f5e9; color:#2e7d32; border-left:4px solid #2e7d32; }
        
        .stats { display:grid; grid-template-columns:repeat(4, 1fr); gap:15px; margin-bottom:20px; }
        .stat-card { background:white; padding:20px; border-radius:12px; text-align:center; box-shadow:0 2px 10px rgba(0,0,0,0.05); border-left:4px solid #2e7d32; }
        .stat-card .number { font-size:1.8rem; font-weight:900; color:#2e7d32; }
        .stat-card .label { font-size:0.8rem; color:#6a8f6a; font-weight:700; margin-top:5px; }
        .stat-card.blue { border-left-color:#1976d2; }
        .stat-card.blue .number { color:#1976d2; }
        .stat-card.orange { border-left-color:#f57c00; }
        .stat-card.orange .number { color:#f57c00; }
        .stat-card.red { border-left-color:#c62828; }
        .stat-card.red .number { color:#c62828; }
        
        .filter-bar { display:grid; grid-template-columns:repeat(5, 1fr); gap:10px; margin-bottom:15px; }
        .filter-bar select, .filter-bar input { width:100%; padding:10px; border:2px solid #dce8dc; border-radius:8px; font-size:0.9rem; }
        .filter-bar button { padding:10px 20px; background:#2e7d32; color:white; border:none; border-radius:8px; font-weight:700; cursor:pointer; }
        
        .btn { padding:10px 20px; border-radius:8px; font-weight:700; font-size:0.85rem; cursor:pointer; text-decoration:none; display:inline-block; transition:all 0.3s ease; }
        .btn:hover { transform:translateY(-2px); }
        .btn-green { background:#2e7d32; color:white; }
        .btn-blue { background:#1976d2; color:white; }
        .btn-orange { background:#f57c00; color:white; }
        .btn-red { background:#c62828; color:white; }
        .btn-sm { padding:5px 10px; font-size:0.75rem; }
        
        .table-wrapper { overflow:auto; max-height:600px; }
        table { width:100%; border-collapse:collapse; min-width:1200px; }
        th { background:#0d2818; color:white; padding:10px; text-align:left; font-size:0.8rem; text-transform:uppercase; position:sticky; top:0; }
        td { padding:8px 10px; border-bottom:1px solid #eee; font-size:0.85rem; }
        tr:hover { background:#f8faf8; }
        
        .badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:0.7rem; font-weight:700; }
        .badge-unused { background:#e8f5e9; color:#2e7d32; }
        .badge-used { background:#ffebee; color:#c62828; }
        .badge-expired { background:#fff3e0; color:#e65100; }
        .badge-active { background:#e8f5e9; color:#1b5e20; }
        .badge-inactive { background:#f5f5f5; color:#757575; }
        
        .card-type { font-weight:700; color:#0d2818; font-size:0.8rem; }
        .action-btns { display:flex; gap:4px; flex-wrap:wrap; }
        
        .empty { text-align:center; padding:40px; color:#6a8f6a; }
        
        @media (max-width:768px) {
            .stats { grid-template-columns:1fr 1fr; }
            .filter-bar { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="topbar">
        <h1>🎫 Scratch Cards Management</h1>
        <div>
            <a href="print_cards.php" style="color:#ffd54f; margin-right:15px; text-decoration:none; font-weight:600;" target="_blank">
                <i class="fas fa-print"></i> Print Cards
            </a>
            <a href="admin_dashboard.php" style="color:#ffd54f; margin-right:15px; text-decoration:none; font-weight:600;">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats">
        <div class="stat-card">
            <div class="number"><?php echo $count_all; ?></div>
            <div class="label">📊 Total Cards</div>
        </div>
        <div class="stat-card blue">
            <div class="number"><?php echo $count_unused; ?></div>
            <div class="label">✅ Unused</div>
        </div>
        <div class="stat-card orange">
            <div class="number"><?php echo $count_used; ?></div>
            <div class="label">🔄 Used</div>
        </div>
        <div class="stat-card red">
            <div class="number"><?php echo $count_inactive; ?></div>
            <div class="label">🔒 Inactive</div>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="card">
        <h2>🔍 Filter Cards</h2>
        <form method="GET">
            <div class="filter-bar">
                <select name="card_type">
                    <option value="">All Types</option>
                    <option value="application" <?php echo ($filter_type == 'application') ? 'selected' : ''; ?>>Application</option>
                    <option value="course_registration" <?php echo ($filter_type == 'course_registration') ? 'selected' : ''; ?>>Course Registration</option>
                    <option value="exam_card" <?php echo ($filter_type == 'exam_card') ? 'selected' : ''; ?>>Exam Card</option>
                    <option value="posting_letter" <?php echo ($filter_type == 'posting_letter') ? 'selected' : ''; ?>>Posting Letter</option>
                    <option value="acceptance_fee" <?php echo ($filter_type == 'acceptance_fee') ? 'selected' : ''; ?>>Acceptance Fee</option>
                    <option value="receipt" <?php echo ($filter_type == 'receipt') ? 'selected' : ''; ?>>Print Receipt</option>
                </select>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="unused" <?php echo ($filter_status == 'unused') ? 'selected' : ''; ?>>Unused</option>
                    <option value="used" <?php echo ($filter_status == 'used') ? 'selected' : ''; ?>>Used</option>
                </select>
                <select name="active">
                    <option value="">All</option>
                    <option value="1" <?php echo ($filter_active === '1') ? 'selected' : ''; ?>>Active</option>
                    <option value="0" <?php echo ($filter_active === '0') ? 'selected' : ''; ?>>Inactive</option>
                </select>
                <input type="text" name="search" placeholder="Search card code or PIN..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit"><i class="fas fa-filter"></i> Filter</button>
            </div>
            <div style="display:flex; gap:10px; margin-top:10px;">
                <a href="admin_scratch_cards.php" class="btn btn-orange">Reset</a>
                <a href="?export_csv=1&card_type=<?php echo urlencode($filter_type); ?>&status=<?php echo urlencode($filter_status); ?>&active=<?php echo urlencode($filter_active); ?>" class="btn btn-blue">
                    <i class="fas fa-download"></i> Download CSV
                </a>
                <a href="print_cards.php?card_type=<?php echo urlencode($filter_type); ?>&status=<?php echo urlencode($filter_status); ?>" target="_blank" class="btn btn-green">
                    <i class="fas fa-print"></i> Print Cards
                </a>
            </div>
        </form>
    </div>

    <!-- CARDS TABLE -->
    <div class="card">
        <h2>🎫 Cards (<?php echo $total_cards; ?> shown)</h2>
        
        <?php if ($cards_result && mysqli_num_rows($cards_result) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Card Code</th>
                        <th>PIN</th>
                        <th>Nau'i</th>
                        <th>Serial</th>
                        <th>Status</th>
                        <th>Active</th>
                        <th>Usage</th>
                        <th>Expiry</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; while ($card = mysqli_fetch_assoc($cards_result)): 
                        $is_expired = !empty($card['expiry_date']) && strtotime($card['expiry_date']) < strtotime(date('Y-m-d'));
                        $is_active = ($card['is_active'] ?? 1) == 1;
                    ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><strong><?php echo htmlspecialchars($card['card_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($card['pin']); ?></td>
                        <td class="card-type"><?php echo strtoupper(str_replace('_', ' ', $card['card_type'])); ?></td>
                        <td><?php echo htmlspecialchars($card['serial_no']); ?></td>
                        <td>
                            <?php if ($is_expired): ?>
                                <span class="badge badge-expired">EXPIRED</span>
                            <?php elseif ($card['status'] == 'used'): ?>
                                <span class="badge badge-used">USED</span>
                            <?php else: ?>
                                <span class="badge badge-unused">UNUSED</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($is_active): ?>
                                <span class="badge badge-active">✅ YES</span>
                            <?php else: ?>
                                <span class="badge badge-inactive">🔒 NO</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo ($card['usage_count'] ?? 0) . ' / ' . ($card['usage_limit'] ?? 10); ?></td>
                        <td><?php echo !empty($card['expiry_date']) ? date('d/m/Y', strtotime($card['expiry_date'])) : '-'; ?></td>
                        <td>
                            <div class="action-btns">
                                <a href="?toggle=<?php echo $card['id']; ?>" class="btn btn-sm <?php echo $is_active ? 'btn-red' : 'btn-green'; ?>" onclick="return confirm('<?php echo $is_active ? 'Kashe' : 'Kunna'; ?> wannan card ɗin?')" title="<?php echo $is_active ? 'Disable' : 'Enable'; ?>">
                                    <i class="fas fa-<?php echo $is_active ? 'lock' : 'unlock'; ?>"></i>
                                </a>
                                <a href="?delete=<?php echo $card['id']; ?>" class="btn btn-sm btn-red" onclick="return confirm('Share wannan card ɗin?')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty">
            <i class="fas fa-ticket-alt" style="font-size:3rem; color:#dce8dc; display:block; margin-bottom:10px;"></i>
            <p>Babu cards da suka dace da wannan filter ɗin.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>