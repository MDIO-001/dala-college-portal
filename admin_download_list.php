<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'Provost', 'Accountant'])) {
    header('Location: login.php');
    exit();
}

$admin_name = $_SESSION['fullname'] ?? 'Admin';

// ============================================
// GET FILTERS
// ============================================
$doc = $_GET['doc'] ?? 'all';
$level = $_GET['level'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// ============================================
// DOCUMENT TYPE LABELS
// ============================================
function docLabel($type) {
    $labels = [
        'introductory_letter' => '📝 T.P Introductory Letter',
        'tp_posting_letter' => '📍 T.P Posting Letter',
        'posting_letter' => '📍 T.P Posting Letter',
        'admission_letter' => '🎓 Admission Letter',
        'exam_card' => '📋 Exam Card',
        'result_slip' => '📊 Result Slip',
        'semester_result' => '📊 Semester Result',
        'transcript' => '📜 Transcript',
        'id_card' => '🆔 ID Card',
        'student_id_card' => '🆔 Student ID Card',
        'course_registration' => '📝 Course Registration',
        'tp_result' => '📈 T.P Result',
        'acceptance_letter' => '📄 Acceptance Letter',
        'statement_of_result' => '📋 Statement of Result',
        'final_result' => '📊 Final Result',
        'application_form' => '📝 Application Form',
        'payment_receipt' => '🧾 Payment Receipt',
        'all' => '📥 All Documents',
    ];
    return $labels[$type] ?? ucwords(str_replace('_', ' ', $type));
}

// ============================================
// BUILD WHERE CLAUSE
// ============================================
$where = "WHERE 1=1";

if ($doc != 'all' && !empty($doc)) {
    if ($doc == 'tp_posting_letter') {
        $where .= " AND dl.document_type IN ('tp_posting_letter', 'posting_letter')";
    } elseif ($doc == 'result_slip') {
        $where .= " AND dl.document_type IN ('result_slip', 'semester_result')";
    } else {
        $doc_esc = mysqli_real_escape_string($conn, $doc);
        $where .= " AND dl.document_type = '$doc_esc'";
    }
}

if (!empty($level)) {
    $level_esc = mysqli_real_escape_string($conn, $level);
    $where .= " AND s.level = '$level_esc'";
}

if (!empty($date_from)) {
    $date_from_esc = mysqli_real_escape_string($conn, $date_from);
    $where .= " AND DATE(dl.downloaded_at) >= '$date_from_esc'";
}

if (!empty($date_to)) {
    $date_to_esc = mysqli_real_escape_string($conn, $date_to);
    $where .= " AND DATE(dl.downloaded_at) <= '$date_to_esc'";
}

if (!empty($search)) {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $where .= " AND (s.fullname LIKE '%$search_esc%' OR s.reg_no LIKE '%$search_esc%' OR s.student_id LIKE '%$search_esc%')";
}

// ============================================
// EXPORT CSV
// ============================================
if (isset($_GET['export_csv'])) {
    $export_query = mysqli_query($conn, "
        SELECT 
            dl.id,
            s.reg_no,
            s.student_id,
            s.fullname,
            s.combination,
            s.course,
            s.level,
            s.branch_code,
            s.email,
            s.phone,
            dl.document_type,
            dl.action_type,
            dl.ip_address,
            dl.downloaded_at
        FROM download_logs dl
        INNER JOIN students s ON dl.student_id = s.id
        $where
        ORDER BY dl.downloaded_at DESC
    ");
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="download_list_' . $doc . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Header
    fputcsv($output, ['DALA COLLEGE OF EDUCATION, KANO']);
    fputcsv($output, ['DOWNLOAD LIST — ' . docLabel($doc)]);
    if (!empty($level)) fputcsv($output, ['Level:', $level]);
    if (!empty($date_from)) fputcsv($output, ['From:', $date_from]);
    if (!empty($date_to)) fputcsv($output, ['To:', $date_to]);
    fputcsv($output, ['Generated:', date('F j, Y g:i A')]);
    fputcsv($output, ['Total Records:', mysqli_num_rows($export_query)]);
    fputcsv($output, []);
    
    fputcsv($output, [
        'S/N', 'REG NO', 'STUDENT ID', 'FULL NAME', 'COMBINATION', 'COURSE',
        'LEVEL', 'STUDY CENTRE', 'EMAIL', 'PHONE',
        'DOCUMENT', 'ACTION', 'IP ADDRESS', 'DOWNLOADED AT'
    ]);
    
    $sn = 1;
    while ($row = mysqli_fetch_assoc($export_query)) {
        fputcsv($output, [
            $sn++,
            $row['reg_no'] ?? '—',
            $row['student_id'] ?? '—',
            strtoupper($row['fullname'] ?? ''),
            $row['combination'] ?? $row['course'] ?? '—',
            $row['course'] ?? '—',
            $row['level'] ?? '—',
            $row['branch_code'] ?? '—',
            $row['email'] ?? '—',
            $row['phone'] ?? '—',
            docLabel($row['document_type']),
            ucfirst($row['action_type'] ?? 'download'),
            $row['ip_address'] ?? '—',
            $row['downloaded_at'] ? date('d-M-Y H:i', strtotime($row['downloaded_at'])) : '—'
        ]);
    }
    fclose($output);
    exit();
}

// ============================================
// FETCH DOWNLOAD LIST
// ============================================
$list_query = mysqli_query($conn, "
    SELECT 
        dl.id,
        s.id AS sid,
        s.reg_no,
        s.student_id AS student_no,
        s.fullname,
        s.combination,
        s.course,
        s.level,
        s.branch_code,
        s.email,
        s.phone,
        dl.document_type,
        dl.action_type,
        dl.ip_address,
        dl.downloaded_at
    FROM download_logs dl
    INNER JOIN students s ON dl.student_id = s.id
    $where
    ORDER BY dl.downloaded_at DESC
    LIMIT 1000
");

$total_records = $list_query ? mysqli_num_rows($list_query) : 0;

// ============================================
// STATS FOR CURRENT FILTER
// ============================================
$unique_students = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(DISTINCT dl.student_id) AS c 
    FROM download_logs dl
    INNER JOIN students s ON dl.student_id = s.id
    $where
"))['c'] ?? 0;

$total_downloads = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS c 
    FROM download_logs dl
    INNER JOIN students s ON dl.student_id = s.id
    $where
"))['c'] ?? 0;

// ============================================
// LEVEL OPTIONS
// ============================================
$level_options = [];
$lq = mysqli_query($conn, "SELECT DISTINCT level FROM students WHERE level IS NOT NULL AND level != '' ORDER BY level");
while ($l = mysqli_fetch_assoc($lq)) $level_options[] = $l['level'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Download List - <?php echo docLabel($doc); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Tahoma,sans-serif; background:#f0f4f8; color:#1a2e1a; padding:20px; }
        .container { max-width:1500px; margin:0 auto; }
        
        .topbar { background:#0d2818; color:white; padding:15px 25px; display:flex; justify-content:space-between; align-items:center; border-radius:12px; margin-bottom:20px; flex-wrap:wrap; gap:10px; }
        .topbar h1 { margin:0; font-size:1.4rem; }
        .topbar a { color:#ffd54f; text-decoration:none; font-weight:600; }
        
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin-bottom:25px; }
        .stat-card { background:white; padding:22px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); border-left:5px solid #2e7d32; }
        .stat-card .number { font-size:2rem; font-weight:800; color:#2e7d32; line-height:1; }
        .stat-card .label { color:#6a8f6a; font-size:0.78rem; font-weight:700; margin-top:6px; text-transform:uppercase; }
        .stat-card.blue { border-left-color:#1976d2; }
        .stat-card.blue .number { color:#1976d2; }
        
        .card { background:white; padding:25px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); margin-bottom:25px; }
        .card h2 { color:#0d2818; margin-bottom:20px; font-size:1.15rem; display:flex; align-items:center; gap:10px; }
        
        .filter-bar { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; align-items:end; margin-bottom:15px; }
        .form-group label { display:block; font-weight:700; color:#0d2818; margin-bottom:5px; font-size:0.78rem; }
        .form-group input, .form-group select { width:100%; padding:9px 12px; border:2px solid #dce8dc; border-radius:8px; font-size:0.85rem; }
        .form-group input:focus, .form-group select:focus { border-color:#2e7d32; outline:none; }
        
        .btn { padding:10px 20px; border:none; border-radius:8px; font-weight:700; font-size:0.85rem; cursor:pointer; text-decoration:none; display:inline-block; transition:all 0.3s ease; }
        .btn:hover { transform:translateY(-2px); }
        .btn-green { background:#2e7d32; color:white; }
        .btn-blue { background:#1976d2; color:white; }
        .btn-orange { background:#f57c00; color:white; }
        .btn-red { background:#c62828; color:white; }
        .btn-sm { padding:6px 12px; font-size:0.78rem; }
        
        table { width:100%; border-collapse:collapse; }
        table th { background:#0d2818; color:white; padding:12px; text-align:left; font-size:0.75rem; text-transform:uppercase; position:sticky; top:0; }
        table td { padding:10px 12px; border-bottom:1px solid #e8f0e8; font-size:0.85rem; }
        table tr:hover { background:#f8faf8; }
        
        .badge { display:inline-block; padding:4px 10px; border-radius:12px; font-size:0.72rem; font-weight:700; }
        .badge-green { background:#e8f5e9; color:#2e7d32; }
        .badge-blue { background:#e3f2fd; color:#0d47a1; }
        .badge-orange { background:#fff3e0; color:#e65100; }
        .badge-purple { background:#f3e5f5; color:#6a1b9a; }
        .badge-red { background:#ffebee; color:#c62828; }
        
        .table-wrapper { overflow-x:auto; max-height:700px; overflow-y:auto; }
        
        .empty-state { text-align:center; padding:60px 20px; color:#6a8f6a; }
        .empty-state i { font-size:3rem; color:#dce8dc; display:block; margin-bottom:15px; }
        
        .breadcrumb { background:#f8faf8; padding:12px 20px; border-radius:8px; margin-bottom:20px; font-size:0.88rem; color:#0d2818; font-weight:600; }
        .breadcrumb a { color:#1976d2; text-decoration:none; }
        
        @media (max-width:768px) {
            .topbar { flex-direction:column; text-align:center; }
            table { font-size:0.78rem; }
            table th, table td { padding:8px 6px; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="topbar">
        <h1><i class="fas fa-list-alt"></i> Download List</h1>
        <div style="display:flex; gap:15px; flex-wrap:wrap;">
            <a href="admin_download_stats.php"><i class="fas fa-chart-pie"></i> Statistics</a>
            <a href="admin_dashboard.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <a href="logout.php" style="color:#ff8a80;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <!-- BREADCRUMB -->
    <div class="breadcrumb">
        <i class="fas fa-info-circle" style="color:#1976d2;"></i>
        <strong>Document:</strong> <?php echo docLabel($doc); ?>
        <?php if (!empty($level)): ?>
            &nbsp;|&nbsp; <strong>Level:</strong> <?php echo htmlspecialchars($level); ?>
        <?php endif; ?>
        <?php if (!empty($date_from)): ?>
            &nbsp;|&nbsp; <strong>From:</strong> <?php echo date('d M Y', strtotime($date_from)); ?>
        <?php endif; ?>
        <?php if (!empty($date_to)): ?>
            &nbsp;|&nbsp; <strong>To:</strong> <?php echo date('d M Y', strtotime($date_to)); ?>
        <?php endif; ?>
    </div>
    
    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="number"><?php echo number_format($total_downloads); ?></div>
            <div class="label">📥 Total Downloads</div>
        </div>
        <div class="stat-card blue">
            <div class="number"><?php echo number_format($unique_students); ?></div>
            <div class="label">👥 Unique Students</div>
        </div>
    </div>
    
    <!-- FILTERS -->
    <div class="card">
        <h2><i class="fas fa-filter" style="color:#f57c00;"></i> Filter</h2>
        <form method="GET">
            <input type="hidden" name="doc" value="<?php echo htmlspecialchars($doc); ?>">
            <div class="filter-bar">
                <div class="form-group">
                    <label>Document Type</label>
                    <select name="doc">
                        <option value="all" <?php echo ($doc == 'all') ? 'selected' : ''; ?>>📥 All Documents</option>
                        <option value="introductory_letter" <?php echo ($doc == 'introductory_letter') ? 'selected' : ''; ?>>📝 T.P Introductory Letter</option>
                        <option value="tp_posting_letter" <?php echo ($doc == 'tp_posting_letter') ? 'selected' : ''; ?>>📍 T.P Posting Letter</option>
                        <option value="admission_letter" <?php echo ($doc == 'admission_letter') ? 'selected' : ''; ?>>🎓 Admission Letter</option>
                        <option value="exam_card" <?php echo ($doc == 'exam_card') ? 'selected' : ''; ?>>📋 Exam Card</option>
                        <option value="result_slip" <?php echo ($doc == 'result_slip') ? 'selected' : ''; ?>>📊 Result Slip</option>
                        <option value="id_card" <?php echo ($doc == 'id_card') ? 'selected' : ''; ?>>🆔 ID Card</option>
                        <option value="transcript" <?php echo ($doc == 'transcript') ? 'selected' : ''; ?>>📜 Transcript</option>
                        <option value="tp_result" <?php echo ($doc == 'tp_result') ? 'selected' : ''; ?>>📈 T.P Result</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Level</label>
                    <select name="level">
                        <option value="">All Levels</option>
                        <?php foreach ($level_options as $lvl): ?>
                            <option value="<?php echo $lvl; ?>" <?php echo ($level == $lvl) ? 'selected' : ''; ?>><?php echo $lvl; ?></option>
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
                    <label>Search (Name, Reg No)</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nemo ɗalibi...">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-green" style="width:100%;">🔍 Apply</button>
                </div>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:10px;">
                <a href="admin_download_list.php?doc=<?php echo urlencode($doc); ?>" class="btn btn-orange btn-sm">🔄 Reset</a>
                <a href="?doc=<?php echo urlencode($doc); ?>&level=<?php echo urlencode($level); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&search=<?php echo urlencode($search); ?>&export_csv=1" 
                   class="btn btn-blue btn-sm">
                    <i class="fas fa-download"></i> Export CSV
                </a>
                <button onclick="window.print()" class="btn btn-green btn-sm">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </form>
    </div>
    
    <!-- LIST -->
    <div class="card">
        <h2>
            <i class="fas fa-users" style="color:#1976d2;"></i> 
            <?php echo docLabel($doc); ?> — 
            <?php echo number_format($total_records); ?> record(s)
        </h2>
        
        <?php if ($list_query && mysqli_num_rows($list_query) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Reg No</th>
                        <th>Student Name</th>
                        <th>Combination</th>
                        <th>Level</th>
                        <th>Study Centre</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Document</th>
                        <th>Action</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; while ($row = mysqli_fetch_assoc($list_query)): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['reg_no'] ?? '—'); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                        <td><?php echo htmlspecialchars($row['combination'] ?? $row['course'] ?? '—'); ?></td>
                        <td><span class="badge badge-green"><?php echo htmlspecialchars($row['level']); ?></span></td>
                        <td><?php echo htmlspecialchars($row['branch_code'] ?? '—'); ?></td>
                        <td style="font-size:0.78rem;"><?php echo htmlspecialchars($row['email'] ?? '—'); ?></td>
                        <td style="font-size:0.78rem;"><?php echo htmlspecialchars($row['phone'] ?? '—'); ?></td>
                        <td style="font-size:0.78rem;"><?php echo docLabel($row['document_type']); ?></td>
                        <td>
                            <span class="badge badge-blue">
                                <?php echo ucfirst($row['action_type'] ?? 'download'); ?>
                            </span>
                        </td>
                        <td style="font-size:0.78rem; color:#6a8f6a;">
                            <?php echo $row['downloaded_at'] ? date('d-M-Y H:i', strtotime($row['downloaded_at'])) : '—'; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Babu records</h3>
                <p>Babu wani ɗalibi da ya yi download na wannan document ɗin.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>