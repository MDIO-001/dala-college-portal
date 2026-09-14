<?php
session_start();
include 'connect.php';
include 'result_functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Role ɗin da aka yarda su shiga Result Database
$user_role = $_SESSION['role'] ?? '';
$allowed_roles = ['admin', 'Exam Officer', 'Provost'];

if (!in_array($user_role, $allowed_roles)) {
    header('Location: login.php');
    exit();
}

$admin_name = $_SESSION['fullname'] ?? 'Admin';
$message = '';
$message_type = '';

// ============================================
// DELETE RESULT
// ============================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if (mysqli_query($conn, "DELETE FROM results WHERE id = $id")) {
        $message = "🗑️ Result deleted successfully!";
        $message_type = 'success';
    }
}

// ============================================
// UPDATE RESULT
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_result'])) {
    $id = intval($_POST['result_id']);
    $score = intval($_POST['score']);
    $g = getGrade($conn, $score);
    
    $update = "UPDATE results SET 
               score = $score,
               grade = '{$g['grade']}',
               grade_point = {$g['grade_point']},
               remark = '{$g['remark']}'
               WHERE id = $id";
    
    if (mysqli_query($conn, $update)) {
        $message = "✅ Result updated successfully!";
        $message_type = 'success';
    } else {
        $message = "❌ Error: " . mysqli_error($conn);
        $message_type = 'error';
    }
}

// ============================================
// EXPORT CSV
// ============================================
if (isset($_GET['export_csv'])) {
    $combination = mysqli_real_escape_string($conn, $_GET['combination'] ?? '');
    $level = mysqli_real_escape_string($conn, $_GET['level'] ?? '');
    $semester = mysqli_real_escape_string($conn, $_GET['semester'] ?? '');
    $session = mysqli_real_escape_string($conn, $_GET['session'] ?? '');
    $search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
    
    $where = "WHERE 1=1";
    if (!empty($combination)) $where .= " AND combination = '$combination'";
    if (!empty($level)) $where .= " AND level = '$level'";
    if (!empty($semester)) $where .= " AND semester = '$semester'";
    if (!empty($session)) $where .= " AND session = '$session'";
    if (!empty($search)) $where .= " AND (course_code LIKE '%$search%' OR student_name LIKE '%$search%' OR reg_no LIKE '%$search%')";
    
    $export_query = mysqli_query($conn, "SELECT * FROM results $where ORDER BY session, level, semester, student_name, course_code");
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="result_database_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['S/N', 'Reg No', 'Student Name', 'Course Code', 'Course Title', 'Units', 'Score', 'Grade', 'Point', 'Remark', 'Level', 'Semester', 'Session', 'Combination']);
    
    $sn = 1;
    while ($r = mysqli_fetch_assoc($export_query)) {
        fputcsv($output, [
            $sn++,
            $r['reg_no'],
            $r['student_name'],
            $r['course_code'],
            $r['course_title'],
            $r['credit_units'],
            $r['score'],
            $r['grade'],
            number_format($r['grade_point'], 1),
            $r['remark'],
            $r['level'],
            $r['semester'],
            $r['session'],
            $r['combination']
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
$filter_sem = isset($_GET['semester']) ? mysqli_real_escape_string($conn, $_GET['semester']) : '';
$filter_session = isset($_GET['session']) ? mysqli_real_escape_string($conn, $_GET['session']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$where = "WHERE 1=1";
if (!empty($filter_comb)) $where .= " AND combination = '$filter_comb'";
if (!empty($filter_level)) $where .= " AND level = '$filter_level'";
if (!empty($filter_sem)) $where .= " AND semester = '$filter_sem'";
if (!empty($filter_session)) $where .= " AND session = '$filter_session'";
if (!empty($search)) $where .= " AND (course_code LIKE '%$search%' OR student_name LIKE '%$search%' OR reg_no LIKE '%$search%')";

$results_query = "SELECT * FROM results $where 
                  ORDER BY session DESC, level, semester, student_name, course_code 
                  LIMIT 500";
$results_result = mysqli_query($conn, $results_query);
$total_results = mysqli_num_rows($results_result);

// Counts
$count_all = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM results"))['c'];
$count_filtered = $count_all;
if (!empty($filter_comb) || !empty($filter_level) || !empty($filter_sem) || !empty($filter_session) || !empty($search)) {
    $count_filtered = $total_results;
}

$combinations_list = [];
$cq = mysqli_query($conn, "SELECT DISTINCT combination FROM courses WHERE combination IS NOT NULL AND combination != '' ORDER BY combination");
while ($c = mysqli_fetch_assoc($cq)) $combinations_list[] = $c['combination'];

$session_options = getSessionOptions();
$level_options = getLevelOptions();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Result Database - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Tahoma,sans-serif; background:#f0f4f8; padding:20px; }
        .container { max-width:1400px; margin:0 auto; }
        
        .topbar { background:#0d2818; padding:15px 25px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; border-radius:12px; margin-bottom:20px; }
        .topbar .logo-title { color:#ffd54f; font-size:1.3rem; font-weight:800; }
        .topbar .logo-title span { color:#a5d6a7; }
        .topbar .logo-sub { display:block; font-size:0.55rem; color:#c8e6c9; }
        .topbar nav a { color:#c8e6c9; text-decoration:none; padding:8px 16px; border-radius:25px; font-size:0.85rem; }
        .topbar nav a:hover { background:#2e7d32; }
        .topbar nav .logout { background:#c62828; color:white !important; }
        .topbar .admin-badge { background:#c62828; color:white; padding:6px 18px; border-radius:20px; font-size:0.8rem; font-weight:600; }
        
        .result-nav { background:white; padding:15px; border-radius:12px; margin-bottom:20px; display:flex; gap:8px; flex-wrap:wrap; align-items:center; box-shadow:0 2px 10px rgba(0,0,0,0.05); }
        .result-nav .label { font-weight:700; color:#0d2818; margin-right:10px; font-size:0.85rem; }
        .result-nav a { padding:8px 15px; border-radius:6px; text-decoration:none; font-weight:600; font-size:0.75rem; }
        .r-course { background:#2e7d32; color:white; }
        .r-grade { background:#f9a825; color:#0d2818; }
        .r-entry { background:#8d2c2c; color:white; }
        .r-slip { background:#e53935; color:white; }
        .r-slip-pro { background:#a5d6a7; color:#0d2818; }
        .r-transcript { background:#5d4037; color:white; }
        .r-final { background:#558b2f; color:white; }
        .r-settings { background:#e65100; color:white; }
        .r-database { background:#37474f; color:white; box-shadow:0 0 0 3px #ffd54f; }
        .r-statement { background:#455a64; color:white; }
        
        .card { background:white; padding:25px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); margin-bottom:20px; }
        .card h2 { color:#0d2818; margin-bottom:15px; font-size:1.2rem; }
        
        .message { padding:12px 20px; border-radius:8px; margin-bottom:15px; font-weight:600; }
        .message.success { background:#e8f5e9; color:#2e7d32; border-left:4px solid #2e7d32; }
        .message.error { background:#ffebee; color:#c62828; border-left:4px solid #c62828; }
        
        .stats-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:15px; margin-bottom:20px; }
        .stat-card { background:white; padding:20px; border-radius:12px; text-align:center; box-shadow:0 2px 10px rgba(0,0,0,0.05); border-left:4px solid #37474f; }
        .stat-card .number { font-size:2rem; font-weight:900; color:#37474f; }
        .stat-card .label { font-size:0.8rem; color:#6a8f6a; font-weight:700; }
        .stat-card.blue { border-left-color:#1976d2; }
        .stat-card.blue .number { color:#1976d2; }
        .stat-card.green { border-left-color:#2e7d32; }
        .stat-card.green .number { color:#2e7d32; }
        
        .filter-bar { display:grid; grid-template-columns:repeat(6, 1fr); gap:10px; margin-bottom:15px; align-items:end; }
        .filter-bar .form-group label { display:block; font-weight:600; color:#0d2818; margin-bottom:5px; font-size:0.8rem; }
        .filter-bar select, .filter-bar input { width:100%; padding:8px 12px; border:2px solid #dce8dc; border-radius:8px; font-size:0.85rem; }
        .filter-bar button { padding:10px 20px; background:#2e7d32; color:white; border:none; border-radius:8px; font-weight:700; cursor:pointer; }
        
        .table-container { overflow:auto; max-height:600px; }
        table { width:100%; border-collapse:collapse; min-width:1200px; }
        table th { background:#0d2818; color:white; padding:10px 12px; text-align:left; font-size:0.75rem; text-transform:uppercase; position:sticky; top:0; }
        table td { padding:8px 12px; border-bottom:1px solid #e0e0e0; font-size:0.85rem; }
        table tr:hover { background:#f8faf8; }
        
        .badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:0.7rem; font-weight:700; }
        .grade-A { background:#e8f5e9; color:#2e7d32; }
        .grade-B { background:#e3f2fd; color:#0d47a1; }
        .grade-C { background:#fff3e0; color:#e65100; }
        .grade-D { background:#f3e5f5; color:#6a1b9a; }
        .grade-E { background:#fce4ec; color:#c2185b; }
        .grade-F { background:#ffebee; color:#c62828; }
        
        .btn { padding:10px 20px; border:none; border-radius:8px; font-weight:700; font-size:0.85rem; cursor:pointer; text-decoration:none; display:inline-block; transition:all 0.3s ease; }
        .btn:hover { transform:translateY(-2px); }
        .btn-green { background:#2e7d32; color:white; }
        .btn-blue { background:#1976d2; color:white; }
        .btn-red { background:#c62828; color:white; }
        .btn-orange { background:#f57c00; color:white; }
        .btn-sm { padding:5px 10px; font-size:0.75rem; }
        
        .action-btns { display:flex; gap:4px; }
        
        .modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; overflow-y:auto; padding:30px; }
        .modal-content { max-width:500px; margin:30px auto; background:white; padding:30px; border-radius:16px; position:relative; }
        .modal-close { position:absolute; top:15px; right:20px; font-size:2rem; background:none; border:none; cursor:pointer; color:#c62828; }
        
        .empty-state { text-align:center; padding:40px; color:#6a8f6a; }
        .empty-state i { font-size:3rem; color:#dce8dc; display:block; margin-bottom:15px; }
        
        @media (max-width:768px) {
            .filter-bar { grid-template-columns:1fr; }
            .stats-grid { grid-template-columns:1fr; }
            .topbar { flex-direction:column; gap:10px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="topbar">
            <div>
                <span class="logo-title">DALA <span>COLLEGE</span></span>
                <span class="logo-sub">Admin Panel</span>
            </div>
            <nav>
                <a href="admin_dashboard.php">Dashboard</a>
                <a href="logout.php" class="logout">Logout</a>
            </nav>
            <div>
                <span class="admin-badge">👤 <?php echo htmlspecialchars($admin_name); ?></span>
            </div>
        </div>

        <div class="result-nav">
            <span class="label">📊 RESULT SYSTEM:</span>
            <a href="admin_course_structure.php" class="r-course">COURSE_STRUCTURE</a>
            <a href="admin_grade_setup.php" class="r-grade">GRADE_SETUP</a>
            <a href="admin_result_entry.php" class="r-entry">RESULT_ENTRY</a>
            <a href="admin_result_slip.php" class="r-slip">RESULT_SLIP</a>
            <a href="admin_result_slip_pro.php" class="r-slip-pro">RESULT_SLIP_PRO</a>
            <a href="admin_transcript.php" class="r-transcript">TRANSCRIPT</a>
            <a href="admin_final_result.php" class="r-final">FINAL_RESULT</a>
            <a href="admin_settings.php" class="r-settings">SETTINGS</a>
            <a href="admin_result_database.php" class="r-database">RESULT_DATABASE</a>
            <a href="admin_statement_of_result.php" class="r-statement">STATEMENT_OF_RESULT</a>
        </div>

        <h2 style="color:#0d2818; margin-bottom:15px;">🗄️ Result Database</h2>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $count_all; ?></div>
                <div class="label">📊 Total Results</div>
            </div>
            <div class="stat-card blue">
                <div class="number"><?php echo $count_filtered; ?></div>
                <div class="label">🔍 Filtered Results</div>
            </div>
            <div class="stat-card green">
                <div class="number"><?php echo count($combinations_list); ?></div>
                <div class="label">🎓 Combinations</div>
            </div>
        </div>

        <!-- FILTERS -->
        <div class="card">
            <h2>🔍 Filter Results</h2>
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
                        <label>Semester</label>
                        <select name="semester">
                            <option value="">All</option>
                            <option value="First Semester" <?php echo ($filter_sem == 'First Semester') ? 'selected' : ''; ?>>First</option>
                            <option value="Second Semester" <?php echo ($filter_sem == 'Second Semester') ? 'selected' : ''; ?>>Second</option>
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
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Course, name, reg no..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <button type="submit">🔍 Filter</button>
                    </div>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <a href="admin_result_database.php" class="btn btn-orange btn-sm">Reset</a>
                    <a href="?export_csv=1&combination=<?php echo urlencode($filter_comb); ?>&level=<?php echo urlencode($filter_level); ?>&semester=<?php echo urlencode($filter_sem); ?>&session=<?php echo urlencode($filter_session); ?>&search=<?php echo urlencode($search); ?>" 
                       class="btn btn-blue btn-sm">
                        <i class="fas fa-download"></i> Export CSV
                    </a>
                </div>
            </form>
        </div>

        <!-- RESULTS TABLE -->
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; margin-bottom:15px;">
                <h2 style="margin-bottom:0;">📋 Results (<?php echo $total_results; ?> shown)</h2>
                <button onclick="window.print()" class="btn btn-green btn-sm">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
            
            <?php if ($results_result && mysqli_num_rows($results_result) > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Reg No</th>
                            <th>Student Name</th>
                            <th>Course Code</th>
                            <th>Course Title</th>
                            <th>Unit</th>
                            <th>Score</th>
                            <th>Grade</th>
                            <th>Point</th>
                            <th>Remark</th>
                            <th>Level</th>
                            <th>Semester</th>
                            <th>Session</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($r = mysqli_fetch_assoc($results_result)): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><strong><?php echo htmlspecialchars($r['reg_no']); ?></strong></td>
                            <td><?php echo htmlspecialchars($r['student_name']); ?></td>
                            <td><strong><?php echo htmlspecialchars($r['course_code']); ?></strong></td>
                            <td><?php echo htmlspecialchars($r['course_title']); ?></td>
                            <td class="center"><?php echo $r['credit_units']; ?></td>
                            <td class="center"><?php echo $r['score']; ?></td>
                            <td class="center">
                                <span class="badge grade-<?php echo $r['grade']; ?>">
                                    <?php echo $r['grade']; ?>
                                </span>
                            </td>
                            <td class="center"><?php echo number_format($r['grade_point'], 1); ?></td>
                            <td class="center"><?php echo htmlspecialchars($r['remark']); ?></td>
                            <td><?php echo htmlspecialchars($r['level']); ?></td>
                            <td><?php echo htmlspecialchars($r['semester']); ?></td>
                            <td><?php echo htmlspecialchars($r['session']); ?></td>
                            <td>
                                <div class="action-btns">
                                    <a href="#" class="btn btn-blue btn-sm" onclick='editResult(<?php echo json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT); ?>); return false;' title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=<?php echo $r['id']; ?>" class="btn btn-red btn-sm" onclick="return confirm('Delete this result?')" title="Delete">
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
                <div class="empty-state">
                    <i class="fas fa-database"></i>
                    <h3>No results found</h3>
                    <p>Try different filters or check the Result Entry page.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- EDIT MODAL -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-content">
            <button class="modal-close" onclick="document.getElementById('editModal').style.display='none'">&times;</button>
            <h3 style="margin-bottom:20px;">✏️ Edit Result</h3>
            <div id="editFormContainer"></div>
        </div>
    </div>

    <script>
    function editResult(result) {
        var html = `
        <form method="POST">
            <input type="hidden" name="result_id" value="${result.id}">
            <div style="background:#f8faf8; padding:15px; border-radius:8px; margin-bottom:15px;">
                <p><strong>Student:</strong> ${result.student_name}</p>
                <p><strong>Reg No:</strong> ${result.reg_no}</p>
                <p><strong>Course:</strong> ${result.course_code} - ${result.course_title}</p>
                <p><strong>Current:</strong> ${result.score} (${result.grade})</p>
            </div>
            <div style="margin-bottom:15px;">
                <label style="display:block; font-weight:700; margin-bottom:5px;">New Score (0-100)</label>
                <input type="number" name="score" min="0" max="100" value="${result.score}" required 
                       style="width:100%; padding:12px; border:2px solid #dce8dc; border-radius:8px; font-size:1rem;">
            </div>
            <button type="submit" name="update_result" class="btn btn-green" style="width:100%;">
                <i class="fas fa-save"></i> Update Result
            </button>
        </form>`;
        document.getElementById('editFormContainer').innerHTML = html;
        document.getElementById('editModal').style.display = 'block';
    }
    
    window.onclick = function(event) {
        if (event.target.id === 'editModal') {
            event.target.style.display = 'none';
        }
    }
    </script>
</body>
</html>