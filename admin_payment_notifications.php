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
// LOAD SETTINGS
// ============================================
$settings = [];
$sq = mysqli_query($conn, "SELECT setting_key, setting_value FROM notification_settings");
while ($s = mysqli_fetch_assoc($sq)) {
    $settings[$s['setting_key']] = $s['setting_value'];
}

// ============================================
// SAVE SETTINGS
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_settings'])) {
    $keys = ['sms_provider', 'termii_api_key', 'termii_sender_id', 'smtp_host', 'smtp_port', 
             'smtp_user', 'smtp_pass', 'smtp_from_email', 'smtp_from_name', 'enable_email', 'enable_sms'];
    
    foreach ($keys as $key) {
        $value = mysqli_real_escape_string($conn, $_POST[$key] ?? '');
        $insert = "INSERT INTO notification_settings (setting_key, setting_value) VALUES ('$key', '$value')
                   ON DUPLICATE KEY UPDATE setting_value = '$value'";
        mysqli_query($conn, $insert);
    }
    
    // Reload
    $settings = [];
    $sq = mysqli_query($conn, "SELECT setting_key, setting_value FROM notification_settings");
    while ($s = mysqli_fetch_assoc($sq)) {
        $settings[$s['setting_key']] = $s['setting_value'];
    }
    
    $message = "✅ Settings saved successfully!";
    $message_type = 'success';
}

// ============================================
// SEND SINGLE NOTIFICATION
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_notification'])) {
    $student_id = intval($_POST['student_id']);
    $notification_type = mysqli_real_escape_string($conn, $_POST['notification_type']);
    $subject = mysqli_real_escape_string($conn, trim($_POST['subject']));
    $msg_body = mysqli_real_escape_string($conn, trim($_POST['message']));
    
    // Get student data
    $s = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM students WHERE id = $student_id"));
    
    if ($s) {
        $recipient = $s['email'] ?? $s['phone'] ?? 'N/A';
        
        // Insert notification log
        $insert = "INSERT INTO payment_notifications 
                    (student_id, notification_type, recipient, subject, message, status, sent_at) 
                   VALUES 
                    ($student_id, '$notification_type', '$recipient', '$subject', '$msg_body', 'sent', NOW())";
        
        if (mysqli_query($conn, $insert)) {
            $message = "✅ Notification logged! (Email/SMS sending function za a haɗa daga baya)";
            $message_type = 'success';
        } else {
            $message = "❌ Error: " . mysqli_error($conn);
            $message_type = 'error';
        }
    }
}

// ============================================
// BULK NOTIFICATION
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_notify'])) {
    $level = mysqli_real_escape_string($conn, $_POST['bulk_level']);
    $notification_type = mysqli_real_escape_string($conn, $_POST['bulk_type']);
    $subject = mysqli_real_escape_string($conn, trim($_POST['bulk_subject']));
    $msg_body = mysqli_real_escape_string($conn, trim($_POST['bulk_message']));
    
    $where = "WHERE status IN ('active','approved')";
    if (!empty($level)) $where .= " AND level = '$level'";
    
    $students = mysqli_query($conn, "SELECT id, fullname, email, phone, reg_no FROM students $where");
    $count = 0;
    
    while ($s = mysqli_fetch_assoc($students)) {
        $recipient = $s['email'] ?? $s['phone'] ?? 'N/A';
        $personal_msg = str_replace(['{name}', '{reg_no}'], [$s['fullname'], $s['reg_no']], $msg_body);
        $personal_msg_esc = mysqli_real_escape_string($conn, $personal_msg);
        
        $insert = "INSERT INTO payment_notifications 
                    (student_id, notification_type, recipient, subject, message, status, sent_at) 
                   VALUES 
                    ({$s['id']}, '$notification_type', '$recipient', '$subject', '$personal_msg_esc', 'sent', NOW())";
        
        if (mysqli_query($conn, $insert)) $count++;
    }
    
    $message = "✅ $count notification(s) logged!";
    $message_type = 'success';
}

// ============================================
// DELETE NOTIFICATION
// ============================================
if (isset($_GET['delete']) && is_numeric($_GET['delete']) && $user_role == 'admin') {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM payment_notifications WHERE id = $id");
    $message = "🗑️ Notification deleted!";
    $message_type = 'success';
}

// ============================================
// EXPORT CSV
// ============================================
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="notifications_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['S/N', 'Student', 'Reg No', 'Type', 'Recipient', 'Subject', 'Status', 'Sent At']);
    
    $export = mysqli_query($conn, "
        SELECT pn.*, s.fullname, s.reg_no 
        FROM payment_notifications pn
        JOIN students s ON pn.student_id = s.id
        ORDER BY pn.id DESC
    ");
    
    $sn = 1;
    while ($e = mysqli_fetch_assoc($export)) {
        fputcsv($output, [
            $sn++,
            $e['fullname'],
            $e['reg_no'],
            $e['notification_type'],
            $e['recipient'],
            $e['subject'],
            $e['status'],
            $e['sent_at']
        ]);
    }
    fclose($output);
    exit();
}

// ============================================
// FETCH NOTIFICATIONS
// ============================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_status = isset($_GET['filter_status']) ? mysqli_real_escape_string($conn, $_GET['filter_status']) : '';
$filter_type = isset($_GET['filter_type']) ? mysqli_real_escape_string($conn, $_GET['filter_type']) : '';

$where = "WHERE 1=1";
if (!empty($search)) $where .= " AND (s.fullname LIKE '%$search%' OR s.reg_no LIKE '%$search%' OR pn.subject LIKE '%$search%')";
if (!empty($filter_status)) $where .= " AND pn.status = '$filter_status'";
if (!empty($filter_type)) $where .= " AND pn.notification_type = '$filter_type'";

$notifications = mysqli_query($conn, "
    SELECT pn.*, s.fullname, s.reg_no 
    FROM payment_notifications pn
    JOIN students s ON pn.student_id = s.id
    $where
    ORDER BY pn.id DESC
    LIMIT 200
");

// ============================================
// STATS
// ============================================
$total_sent = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM payment_notifications"))['c'];
$sent_today = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM payment_notifications WHERE DATE(sent_at) = CURDATE()"))['c'];
$email_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM payment_notifications WHERE notification_type IN ('email','both')"))['c'];
$sms_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM payment_notifications WHERE notification_type IN ('sms','both')"))['c'];

// ============================================
// STUDENTS DROPDOWN
// ============================================
$students_list = mysqli_query($conn, "SELECT id, reg_no, fullname, email, phone, level FROM students WHERE status IN ('active', 'approved') ORDER BY fullname");

$level_options = ['NCE I', 'NCE II', 'NCE III', '400 Level', '500 Level'];

date_default_timezone_set('Africa/Lagos');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications - Dala College</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Tahoma,sans-serif; background:#f0f4f8; color:#1a2e1a; min-height:100vh; padding:20px; }
        .container { max-width:1400px; margin:0 auto; }
        
        .topbar { background:#0d2818; padding:15px 25px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; border-radius:12px; margin-bottom:20px; }
        .topbar .logo-title { color:#ffd54f; font-size:1.3rem; font-weight:800; }
        .topbar .logo-title span { color:#a5d6a7; }
        .topbar .logo-sub { display:block; font-size:0.55rem; color:#c8e6c9; }
        .topbar nav a { color:#c8e6c9; text-decoration:none; padding:8px 14px; border-radius:25px; font-size:0.82rem; }
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
        .form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .form-group label { display:block; font-weight:700; color:#0d2818; margin-bottom:5px; font-size:0.78rem; }
        .form-group input, .form-group select, .form-group textarea { width:100%; padding:9px 12px; border:2px solid #dce8dc; border-radius:8px; font-size:0.85rem; font-family:inherit; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color:#2e7d32; outline:none; }
        .form-group textarea { resize:vertical; min-height:80px; }
        
        .btn { padding:9px 18px; border:none; border-radius:8px; font-weight:700; font-size:0.85rem; cursor:pointer; text-decoration:none; display:inline-block; transition:all 0.3s ease; }
        .btn:hover { transform:translateY(-2px); }
        .btn-green { background:#2e7d32; color:white; }
        .btn-blue { background:#1976d2; color:white; }
        .btn-red { background:#c62828; color:white; }
        .btn-orange { background:#f57c00; color:white; }
        .btn-gray { background:#6a8f6a; color:white; }
        .btn-purple { background:#7b1fa2; color:white; }
        .btn-sm { padding:5px 10px; font-size:0.75rem; }
        
        .filter-bar { display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:10px; align-items:end; margin-bottom:15px; }
        
        .table-container { overflow:auto; border-radius:8px; }
        table { width:100%; border-collapse:collapse; }
        table th { background:#0d2818; color:white; padding:10px 12px; text-align:left; font-size:0.72rem; text-transform:uppercase; }
        table td { padding:10px 12px; border-bottom:1px solid #e0e0e0; font-size:0.85rem; }
        table tr:hover { background:#f8faf8; }
        
        .badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:0.7rem; font-weight:700; }
        .badge-sent { background:#e8f5e9; color:#2e7d32; }
        .badge-pending { background:#fff8e1; color:#f57c00; }
        .badge-failed { background:#ffebee; color:#c62828; }
        .badge-email { background:#e3f2fd; color:#0d47a1; }
        .badge-sms { background:#f3e5f5; color:#6a1b9a; }
        .badge-both { background:#fff3e0; color:#e65100; }
        
        .tab-buttons { display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
        .tab-buttons a { padding:10px 22px; background:white; border:2px solid #dce8dc; border-radius:25px; text-decoration:none; color:#0d2818; font-weight:700; font-size:0.85rem; transition:all 0.3s ease; }
        .tab-buttons a:hover { background:#f0f8f0; }
        .tab-buttons a.active { background:#2e7d32; color:white; border-color:#2e7d32; }
        
        .empty-state { text-align:center; padding:40px; color:#6a8f6a; }
        .empty-state i { font-size:3rem; color:#dce8dc; display:block; margin-bottom:15px; }
        
        .settings-warning { background:#fff9c4; padding:15px 20px; border-radius:8px; border-left:4px solid #f9a825; margin-bottom:20px; font-size:0.9rem; color:#0d2818; }
        
        @media (max-width:900px) {
            .form-grid, .filter-bar { grid-template-columns:1fr 1fr; }
            .stats-grid { grid-template-columns:1fr 1fr; }
        }
        @media (max-width:600px) {
            .form-grid, .form-grid-2, .filter-bar, .stats-grid { grid-template-columns:1fr; }
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
                <a href="admin_payments_list.php">📋 All</a>
                <a href="admin_payments_verify.php">✅ Verify</a>
                <a href="admin_payment_items.php">⚙️ Items</a>
                <a href="admin_payment_discounts.php">🎁 Discounts</a>
                <a href="admin_payment_notifications.php" class="active">🔔 Notifications</a>
                <a href="admin_payment_reports.php">📊 Reports</a>
                <a href="staff_dashboard.php">🏠 Dashboard</a>
                <a href="logout.php" style="background:#c62828; color:white;">Logout</a>
            </nav>
            <div>
                <span class="admin-badge">👤 <?php echo htmlspecialchars($staff_name); ?> (<?php echo htmlspecialchars($user_role); ?>)</span>
            </div>
        </div>

        <h2 style="color:#0d2818; margin-bottom:5px;">🔔 Payment Notifications</h2>
        <p style="color:#6a8f6a; margin-bottom:20px; font-size:0.9rem;">
            Aika Email da SMS ga ɗalibai game da biya
        </p>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $total_sent; ?></div>
                <div class="label">📨 Total Sent</div>
            </div>
            <div class="stat-card blue">
                <div class="number"><?php echo $sent_today; ?></div>
                <div class="label">📅 Sent Today</div>
            </div>
            <div class="stat-card orange">
                <div class="number"><?php echo $email_count; ?></div>
                <div class="label">📧 Email</div>
            </div>
            <div class="stat-card purple">
                <div class="number"><?php echo $sms_count; ?></div>
                <div class="label">📱 SMS</div>
            </div>
        </div>

        <!-- TABS -->
        <div class="tab-buttons">
            <a href="?tab=send" class="<?php echo (!isset($_GET['tab']) || $_GET['tab'] == 'send') ? 'active' : ''; ?>">
                <i class="fas fa-paper-plane"></i> Send Notification
            </a>
            <a href="?tab=bulk" class="<?php echo (isset($_GET['tab']) && $_GET['tab'] == 'bulk') ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Bulk Send
            </a>
            <a href="?tab=logs" class="<?php echo (isset($_GET['tab']) && $_GET['tab'] == 'logs') ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> Logs
            </a>
            <a href="?tab=settings" class="<?php echo (isset($_GET['tab']) && $_GET['tab'] == 'settings') ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i> Settings
            </a>
        </div>

        <?php
        $tab = $_GET['tab'] ?? 'send';
        
        // ============================================
        // TAB: SEND SINGLE
        // ============================================
        if ($tab == 'send'): ?>
        <div class="card">
            <h2>📨 Send Notification to One Student</h2>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Student *</label>
                        <select name="student_id" required>
                            <option value="">-- Select Student --</option>
                            <?php while ($s = mysqli_fetch_assoc($students_list)): ?>
                                <option value="<?php echo $s['id']; ?>">
                                    <?php echo htmlspecialchars($s['reg_no']); ?> — <?php echo htmlspecialchars($s['fullname']); ?>
                                    <?php if ($s['email']): ?> (📧 <?php echo htmlspecialchars($s['email']); ?>)<?php endif; ?>
                                    <?php if ($s['phone']): ?> (📱 <?php echo htmlspecialchars($s['phone']); ?>)<?php endif; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Notification Type *</label>
                        <select name="notification_type" required>
                            <option value="email">📧 Email Only</option>
                            <option value="sms">📱 SMS Only</option>
                            <option value="both">📧📱 Both</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Subject *</label>
                        <input type="text" name="subject" required placeholder="e.g. Payment Receipt Confirmation">
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Message *</label>
                        <textarea name="message" required placeholder="Dear {name}, your payment of ₦... has been received. Thank you."></textarea>
                        <div style="font-size:0.75rem; color:#666; margin-top:5px;">
                            <strong>Placeholders:</strong> {name} = Student fullname, {reg_no} = Registration number
                        </div>
                    </div>
                </div>
                <button type="submit" name="send_notification" class="btn btn-green" style="margin-top:15px;">
                    <i class="fas fa-paper-plane"></i> Send Notification
                </button>
            </form>
        </div>
        <?php endif; ?>

        <?php
        // ============================================
        // TAB: BULK
        // ============================================
        if ($tab == 'bulk'): ?>
        <div class="card">
            <h2>👥 Send Bulk Notification</h2>
            <div class="settings-warning">
                <i class="fas fa-info-circle"></i> 
                <strong>Lura:</strong> Wannan zai aika notification ga duk ɗalibai da suka dace da filter ɗin da ka zaɓa.
                Za a iya amfani da placeholders {name} da {reg_no}.
            </div>
            <form method="POST">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Filter by Level</label>
                        <select name="bulk_level">
                            <option value="">All Levels</option>
                            <?php foreach ($level_options as $lvl): ?>
                                <option value="<?php echo $lvl; ?>"><?php echo $lvl; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Notification Type *</label>
                        <select name="bulk_type" required>
                            <option value="email">📧 Email Only</option>
                            <option value="sms">📱 SMS Only</option>
                            <option value="both">📧📱 Both</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Subject *</label>
                    <input type="text" name="bulk_subject" required placeholder="e.g. Important Payment Reminder">
                </div>
                <div class="form-group">
                    <label>Message *</label>
                    <textarea name="bulk_message" required placeholder="Dear {name} ({reg_no}), this is a reminder to complete your payment for the current session."></textarea>
                </div>
                <button type="submit" name="bulk_notify" class="btn btn-purple" style="margin-top:15px;"
                        onclick="return confirm('Send bulk notification?')">
                    <i class="fas fa-users"></i> Send Bulk Notification
                </button>
            </form>
        </div>
        <?php endif; ?>

        <?php
        // ============================================
        // TAB: LOGS
        // ============================================
        if ($tab == 'logs'): ?>
        <div class="card">
            <h2>🔍 Search & Filter Logs</h2>
            <form method="GET">
                <input type="hidden" name="tab" value="logs">
                <div class="filter-bar">
                    <div class="form-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Name, reg no, subject..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="filter_status">
                            <option value="">All</option>
                            <option value="sent" <?php echo ($filter_status == 'sent') ? 'selected' : ''; ?>>Sent</option>
                            <option value="pending" <?php echo ($filter_status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="failed" <?php echo ($filter_status == 'failed') ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="filter_type">
                            <option value="">All</option>
                            <option value="email" <?php echo ($filter_type == 'email') ? 'selected' : ''; ?>>Email</option>
                            <option value="sms" <?php echo ($filter_type == 'sms') ? 'selected' : ''; ?>>SMS</option>
                            <option value="both" <?php echo ($filter_type == 'both') ? 'selected' : ''; ?>>Both</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-green" style="width:100%;">🔍 Filter</button>
                    </div>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:8px;">
                    <a href="?tab=logs" class="btn btn-orange btn-sm">🔄 Reset</a>
                    <a href="?export_csv=1" class="btn btn-blue btn-sm"><i class="fas fa-download"></i> Export CSV</a>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>📋 Notification Logs (<?php echo $notifications->num_rows; ?> shown)</h2>
            <?php if ($notifications->num_rows > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th>Reg No</th>
                            <th>Type</th>
                            <th>Recipient</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Sent At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($n = mysqli_fetch_assoc($notifications)): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo htmlspecialchars($n['fullname']); ?></td>
                            <td><strong><?php echo htmlspecialchars($n['reg_no']); ?></strong></td>
                            <td>
                                <span class="badge badge-<?php echo $n['notification_type']; ?>">
                                    <?php echo strtoupper($n['notification_type']); ?>
                                </span>
                            </td>
                            <td style="font-size:0.78rem;"><?php echo htmlspecialchars($n['recipient']); ?></td>
                            <td style="font-size:0.82rem;"><?php echo htmlspecialchars($n['subject']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $n['status']; ?>">
                                    <?php echo ucfirst($n['status']); ?>
                                </span>
                            </td>
                            <td style="font-size:0.78rem; color:#666;">
                                <?php echo $n['sent_at'] ? date('d-M-Y H:i', strtotime($n['sent_at'])) : '—'; ?>
                            </td>
                            <td>
                                <?php if ($user_role == 'admin'): ?>
                                <a href="?tab=logs&delete=<?php echo $n['id']; ?>" class="btn btn-red btn-sm" 
                                   onclick="return confirm('Delete this notification log?')">
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
                    <i class="fas fa-bell-slash"></i>
                    <h3>Babu Notifications</h3>
                    <p>Babu wani notification da aka aika tukuna.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php
        // ============================================
        // TAB: SETTINGS
        // ============================================
        if ($tab == 'settings'): ?>
        <div class="card">
            <h2>⚙️ Notification Settings</h2>
            
            <div class="settings-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Muhimmanci:</strong> Don aika Email da SMS a zahiri, kana buƙatar:
                <ul style="margin-top:8px; margin-left:20px;">
                    <li><strong>Email:</strong> SMTP credentials daga Gmail, Outlook, ko wani provider</li>
                    <li><strong>SMS:</strong> Termii API key daga <a href="https://termii.com" target="_blank">termii.com</a></li>
                </ul>
            </div>
            
            <form method="POST">
                <h3 style="color:#0d2818; margin:20px 0 10px; font-size:1rem;">📧 Email Settings (SMTP)</h3>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Enable Email</label>
                        <select name="enable_email">
                            <option value="1" <?php echo ($settings['enable_email'] ?? '0') == '1' ? 'selected' : ''; ?>>Yes — Enabled</option>
                            <option value="0" <?php echo ($settings['enable_email'] ?? '0') == '0' ? 'selected' : ''; ?>>No — Disabled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>SMTP Host</label>
                        <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>" placeholder="smtp.gmail.com">
                    </div>
                    <div class="form-group">
                        <label>SMTP Port</label>
                        <input type="text" name="smtp_port" value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>" placeholder="587">
                    </div>
                    <div class="form-group">
                        <label>SMTP Username</label>
                        <input type="text" name="smtp_user" value="<?php echo htmlspecialchars($settings['smtp_user'] ?? ''); ?>" placeholder="your-email@gmail.com">
                    </div>
                    <div class="form-group">
                        <label>SMTP Password</label>
                        <input type="password" name="smtp_pass" value="<?php echo htmlspecialchars($settings['smtp_pass'] ?? ''); ?>" placeholder="••••••••">
                    </div>
                    <div class="form-group">
                        <label>From Email</label>
                        <input type="email" name="smtp_from_email" value="<?php echo htmlspecialchars($settings['smtp_from_email'] ?? ''); ?>" placeholder="noreply@dalacoe.edu.ng">
                    </div>
                    <div class="form-group">
                        <label>From Name</label>
                        <input type="text" name="smtp_from_name" value="<?php echo htmlspecialchars($settings['smtp_from_name'] ?? ''); ?>" placeholder="Dala College">
                    </div>
                </div>
                
                <h3 style="color:#0d2818; margin:20px 0 10px; font-size:1rem;">📱 SMS Settings (Termii)</h3>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Enable SMS</label>
                        <select name="enable_sms">
                            <option value="1" <?php echo ($settings['enable_sms'] ?? '0') == '1' ? 'selected' : ''; ?>>Yes — Enabled</option>
                            <option value="0" <?php echo ($settings['enable_sms'] ?? '0') == '0' ? 'selected' : ''; ?>>No — Disabled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>SMS Provider</label>
                        <select name="sms_provider">
                            <option value="termii" <?php echo ($settings['sms_provider'] ?? '') == 'termii' ? 'selected' : ''; ?>>Termii</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Termii API Key</label>
                        <input type="password" name="termii_api_key" value="<?php echo htmlspecialchars($settings['termii_api_key'] ?? ''); ?>" placeholder="••••••••••••••">
                    </div>
                    <div class="form-group">
                        <label>Sender ID</label>
                        <input type="text" name="termii_sender_id" value="<?php echo htmlspecialchars($settings['termii_sender_id'] ?? 'DALA'); ?>" placeholder="DALA">
                    </div>
                </div>
                
                <button type="submit" name="save_settings" class="btn btn-green" style="margin-top:20px;">
                    <i class="fas fa-save"></i> Save Settings
                </button>
            </form>
        </div>
        <?php endif; ?>
        
    </div>
</body>
</html>