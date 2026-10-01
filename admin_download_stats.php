<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'Provost', 'Accountant'])) {
    header('Location: login.php');
    exit();
}

$admin_name = $_SESSION['fullname'] ?? 'Admin';

// ============================================
// CHECK IF download_logs TABLE EXISTS
// ============================================
$table_exists = false;
$check = mysqli_query($conn, "SHOW TABLES LIKE 'download_logs'");
if ($check && mysqli_num_rows($check) > 0) $table_exists = true;

// ============================================
// OVERALL STATS
// ============================================
$total_downloads = 0;
$intro_downloads = 0;
$posting_downloads = 0;
$admission_downloads = 0;
$exam_card_downloads = 0;
$result_downloads = 0;
$by_level = null;
$recent = null;
$top_docs = null;

if ($table_exists) {
    $total_downloads = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM download_logs"))['c'] ?? 0;
    $intro_downloads = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT student_id) AS c FROM download_logs WHERE document_type = 'introductory_letter'"))['c'] ?? 0;
    $posting_downloads = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT student_id) AS c FROM download_logs WHERE document_type IN ('tp_posting_letter','posting_letter')"))['c'] ?? 0;
    $admission_downloads = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT student_id) AS c FROM download_logs WHERE document_type = 'admission_letter'"))['c'] ?? 0;
    $exam_card_downloads = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT student_id) AS c FROM download_logs WHERE document_type = 'exam_card'"))['c'] ?? 0;
    $result_downloads = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT student_id) AS c FROM download_logs WHERE document_type IN ('result_slip','semester_result')"))['c'] ?? 0;
    
    $by_level = mysqli_query($conn, "
        SELECT 
            s.level,
            COUNT(DISTINCT CASE WHEN dl.document_type = 'introductory_letter' THEN dl.student_id END) AS intro_count,
            COUNT(DISTINCT CASE WHEN dl.document_type IN ('tp_posting_letter','posting_letter') THEN dl.student_id END) AS posting_count,
            COUNT(DISTINCT CASE WHEN dl.document_type = 'admission_letter' THEN dl.student_id END) AS admission_count,
            COUNT(DISTINCT CASE WHEN dl.document_type = 'exam_card' THEN dl.student_id END) AS exam_count,
            COUNT(DISTINCT CASE WHEN dl.document_type IN ('result_slip','semester_result') THEN dl.student_id END) AS result_count
        FROM students s
        LEFT JOIN download_logs dl ON s.id = dl.student_id
        WHERE s.status NOT IN ('rejected', 'inactive')
        GROUP BY s.level
        ORDER BY s.level
    ");
    
    $recent = mysqli_query($conn, "
        SELECT dl.*, s.fullname, s.reg_no, s.level
        FROM download_logs dl
        JOIN students s ON dl.student_id = s.id
        ORDER BY dl.id DESC
        LIMIT 20
    ");
    
    $top_docs = mysqli_query($conn, "
        SELECT document_type, COUNT(*) AS count 
        FROM download_logs 
        GROUP BY document_type 
        ORDER BY count DESC
        LIMIT 10
    ");
}

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
        'course_registration' => '📝 Course Registration',
        'tp_result' => '📈 T.P Result',
    ];
    return $labels[$type] ?? ucwords(str_replace('_', ' ', $type));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Download Statistics - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Tahoma,sans-serif; background:#f0f4f8; color:#1a2e1a; padding:20px; }
        .container { max-width:1400px; margin:0 auto; }
        .topbar { background:#0d2818; color:white; padding:15px 25px; display:flex; justify-content:space-between; align-items:center; border-radius:12px; margin-bottom:20px; flex-wrap:wrap; gap:10px; }
        .topbar h1 { margin:0; font-size:1.5rem; }
        .topbar a { color:#ffd54f; text-decoration:none; font-weight:600; }
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin-bottom:25px; }
        .stat-card { background:white; padding:22px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); border-left:5px solid #2e7d32; }
        .stat-card .number { font-size:2rem; font-weight:800; color:#2e7d32; line-height:1; }
        .stat-card .label { color:#6a8f6a; font-size:0.78rem; font-weight:700; margin-top:6px; text-transform:uppercase; }
        .stat-card.blue { border-left-color:#1976d2; }
        .stat-card.blue .number { color:#1976d2; }
        .stat-card.orange { border-left-color:#f57c00; }
        .stat-card.orange .number { color:#f57c00; }
        .stat-card.purple { border-left-color:#7b1fa2; }
        .stat-card.purple .number { color:#7b1fa2; }
        .stat-card.teal { border-left-color:#00897b; }
        .stat-card.teal .number { color:#00897b; }
        .card { background:white; padding:25px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); margin-bottom:25px; }
        .card h2 { color:#0d2818; margin-bottom:20px; font-size:1.15rem; display:flex; align-items:center; gap:10px; }
        table { width:100%; border-collapse:collapse; }
        table th { background:#0d2818; color:white; padding:12px; text-align:left; font-size:0.78rem; text-transform:uppercase; }
        table td { padding:12px; border-bottom:1px solid #e8f0e8; font-size:0.88rem; }
        table tr:hover { background:#f8faf8; }
        .badge { display:inline-block; padding:4px 12px; border-radius:12px; font-size:0.75rem; font-weight:700; }
        .badge-green { background:#e8f5e9; color:#2e7d32; }
        .badge-blue { background:#e3f2fd; color:#0d47a1; }
        .badge-orange { background:#fff3e0; color:#e65100; }
        .badge-purple { background:#f3e5f5; color:#6a1b9a; }
        .progress-bar { background:#e8f0e8; border-radius:10px; height:22px; position:relative; overflow:hidden; }
        .progress-bar .fill { background:linear-gradient(90deg, #2e7d32, #66bb6a); height:100%; border-radius:10px; }
        .progress-bar .text { position:absolute; top:0; left:0; right:0; bottom:0; display:flex; align-items:center; justify-content:center; color:white; font-weight:700; font-size:0.72rem; }
        .alert { padding:20px; border-radius:12px; margin-bottom:20px; font-weight:600; text-align:center; }
        .alert-warning { background:#fff9c4; color:#e65100; border-left:4px solid #f9a825; }
        @media (max-width:768px) {
            .topbar { flex-direction:column; text-align:center; }
            table { font-size:0.78rem; }
            table th, table td { padding:8px 5px; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="topbar">
        <h1><i class="fas fa-chart-pie"></i> Download Statistics</h1>
        <div style="display:flex; gap:15px; flex-wrap:wrap;">
            <a href="admin_dashboard.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <a href="logout.php" style="color:#ff8a80;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <?php if (!$table_exists): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle" style="font-size:2rem; display:block; margin-bottom:10px;"></i>
            <strong>⚠️ `download_logs` Table bai wanzu ba!</strong><br>
            Don ganin statistics ɗin, dole ne a ƙirƙiri `download_logs` table. Gudu wannan SQL:
            <pre style="background:#fff; padding:15px; border-radius:8px; margin-top:15px; text-align:left; font-size:0.8rem; overflow-x:auto;">CREATE TABLE IF NOT EXISTS download_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    document_type VARCHAR(50) NOT NULL,
    action_type VARCHAR(20) DEFAULT 'download',
    ip_address VARCHAR(50),
    user_agent TEXT,
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student (student_id),
    INDEX idx_doc (document_type),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);</pre>
        </div>
    <?php else: ?>
    
    <div class="stats-grid">
    <a href="admin_download_list.php?doc=all" style="text-decoration:none; color:inherit;">
        <div class="stat-card" style="cursor:pointer;">
            <div class="number"><?php echo number_format($total_downloads); ?></div>
            <div class="label">📥 Total Downloads</div>
        </div>
    </a>
    <a href="admin_download_list.php?doc=introductory_letter" style="text-decoration:none; color:inherit;">
        <div class="stat-card blue" style="cursor:pointer;">
            <div class="number"><?php echo $intro_downloads; ?></div>
            <div class="label">📝 T.P Introductory</div>
        </div>
    </a>
    <a href="admin_download_list.php?doc=tp_posting_letter" style="text-decoration:none; color:inherit;">
        <div class="stat-card orange" style="cursor:pointer;">
            <div class="number"><?php echo $posting_downloads; ?></div>
            <div class="label">📍 T.P Posting Letter</div>
        </div>
    </a>
    <a href="admin_download_list.php?doc=admission_letter" style="text-decoration:none; color:inherit;">
        <div class="stat-card purple" style="cursor:pointer;">
            <div class="number"><?php echo $admission_downloads; ?></div>
            <div class="label">🎓 Admission Letter</div>
        </div>
    </a>
    <a href="admin_download_list.php?doc=exam_card" style="text-decoration:none; color:inherit;">
        <div class="stat-card teal" style="cursor:pointer;">
            <div class="number"><?php echo $exam_card_downloads; ?></div>
            <div class="label">📋 Exam Card</div>
        </div>
    </a>
    <a href="admin_download_list.php?doc=result_slip" style="text-decoration:none; color:inherit;">
        <div class="stat-card" style="cursor:pointer;">
            <div class="number"><?php echo $result_downloads; ?></div>
            <div class="label">📊 Result Slip</div>
        </div>
    </a>
</div>
    
    <!-- BREAKDOWN BY LEVEL -->
    <div class="card">
        <h2><i class="fas fa-layer-group" style="color:#1976d2;"></i> Breakdown by Level</h2>
        <?php if ($by_level && mysqli_num_rows($by_level) > 0): ?>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Level</th>
                        <th style="text-align:center;">📝 T.P Intro</th>
                        <th style="text-align:center;">📍 T.P Posting</th>
                        <th style="text-align:center;">🎓 Admission</th>
                        <th style="text-align:center;">📋 Exam Card</th>
                        <th style="text-align:center;">📊 Result</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($by_level)): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['level'] ?? 'N/A'); ?></strong></td>
                       <td style="text-align:center;">
    <a href="admin_download_list.php?doc=introductory_letter&level=<?php echo urlencode($row['level']); ?>" style="text-decoration:none;">
        <span class="badge badge-blue"><?php echo $row['intro_count']; ?></span>
    </a>
</td>
                        <td style="text-align:center;"><span class="badge badge-orange"><?php echo $row['posting_count']; ?></span></td>
                        <td style="text-align:center;"><span class="badge badge-purple"><?php echo $row['admission_count']; ?></span></td>
                        <td style="text-align:center;"><span class="badge badge-green"><?php echo $row['exam_count']; ?></span></td>
                        <td style="text-align:center;"><span class="badge badge-blue"><?php echo $row['result_count']; ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p style="text-align:center; color:#6a8f6a; padding:30px;">Babu data tukuna.</p>
        <?php endif; ?>
    </div>
    
    <!-- TOP DOCUMENTS -->
    <div class="card">
        <h2><i class="fas fa-trophy" style="color:#f57c00;"></i> Most Downloaded Documents</h2>
        <?php 
        $max_count = 0;
        $top_docs_data = [];
        if ($top_docs && mysqli_num_rows($top_docs) > 0) {
            while ($row = mysqli_fetch_assoc($top_docs)) {
                $top_docs_data[] = $row;
                if ($row['count'] > $max_count) $max_count = $row['count'];
            }
        }
        ?>
        <?php if (!empty($top_docs_data)): ?>
            <?php foreach ($top_docs_data as $doc): 
                $pct = $max_count > 0 ? round(($doc['count'] / $max_count) * 100) : 0;
            ?>
                <div style="margin-bottom:15px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                        <strong><?php echo docLabel($doc['document_type']); ?></strong>
                        <span style="font-weight:800; color:#0d2818;"><?php echo $doc['count']; ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="fill" style="width:<?php echo $pct; ?>%;"></div>
                        <div class="text"><?php echo $pct; ?>%</div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align:center; color:#6a8f6a; padding:30px;">Babu data tukuna.</p>
        <?php endif; ?>
    </div>
    
    <!-- RECENT DOWNLOADS -->
    <div class="card">
        <h2><i class="fas fa-history" style="color:#7b1fa2;"></i> Recent Downloads (Last 20)</h2>
        <?php if ($recent && mysqli_num_rows($recent) > 0): ?>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>#</th><th>Student</th><th>Reg No</th><th>Level</th>
                        <th>Document</th><th>Action</th><th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; while ($row = mysqli_fetch_assoc($recent)): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['fullname']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['reg_no']); ?></td>
                        <td><span class="badge badge-green"><?php echo htmlspecialchars($row['level']); ?></span></td>
                        <td><?php echo docLabel($row['document_type']); ?></td>
                        <td><span class="badge badge-blue"><?php echo ucfirst($row['action_type'] ?? 'download'); ?></span></td>
                        <td style="font-size:0.82rem; color:#6a8f6a;">
                            <?php echo $row['downloaded_at'] ? date('d-M-Y H:i', strtotime($row['downloaded_at'])) : '—'; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p style="text-align:center; color:#6a8f6a; padding:30px;">
                <i class="fas fa-inbox" style="font-size:3rem; color:#dce8dc; display:block; margin-bottom:10px;"></i>
                Babu download tukuna.
            </p>
        <?php endif; ?>
    </div>
    
    <?php endif; ?>
</div>

</body>
</html>