<?php
session_start();
include 'connect.php';
include 'check_role.php';

if (!isset($_SESSION['user_id']) || !canAccessResultSystem()) {
    header('Location: login.php');
    exit();
}

$admin_name = $_SESSION['fullname'] ?? 'Admin';
$message = '';
$message_type = '';

// ============================================
// ADD GRADE
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_grade'])) {
    $min_score = intval($_POST['min_score']);
    $max_score = intval($_POST['max_score']);
    $grade = mysqli_real_escape_string($conn, strtoupper(trim($_POST['grade'])));
    $grade_point = floatval($_POST['grade_point']);
    $remark = mysqli_real_escape_string($conn, trim($_POST['remark']));
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    if ($min_score < 0 || $max_score > 100 || $min_score > $max_score) {
        $message = "❌ Score range ba daidai ba ne!";
        $message_type = 'error';
    } else {
        $check = mysqli_query($conn, "SELECT id FROM grade_setup WHERE grade = '$grade'");
        if (mysqli_num_rows($check) > 0) {
            $message = "❌ Grade '$grade' ya riga ya wanzu!";
            $message_type = 'error';
        } else {
            $insert = "INSERT INTO grade_setup (min_score, max_score, grade, grade_point, remark, status) 
                       VALUES ($min_score, $max_score, '$grade', $grade_point, '$remark', '$status')";
            if (mysqli_query($conn, $insert)) {
                $message = "✅ An ƙara grade '$grade' cikin nasara!";
                $message_type = 'success';
            } else {
                $message = "❌ Error: " . mysqli_error($conn);
                $message_type = 'error';
            }
        }
    }
}

// ============================================
// UPDATE GRADE
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_grade'])) {
    $id = intval($_POST['grade_id']);
    $min_score = intval($_POST['min_score']);
    $max_score = intval($_POST['max_score']);
    $grade = mysqli_real_escape_string($conn, strtoupper(trim($_POST['grade'])));
    $grade_point = floatval($_POST['grade_point']);
    $remark = mysqli_real_escape_string($conn, trim($_POST['remark']));
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $update = "UPDATE grade_setup SET 
               min_score = $min_score, 
               max_score = $max_score, 
               grade = '$grade', 
               grade_point = $grade_point, 
               remark = '$remark', 
               status = '$status' 
               WHERE id = $id";
    
    if (mysqli_query($conn, $update)) {
        $message = "✅ An sabunta grade cikin nasara!";
        $message_type = 'success';
    } else {
        $message = "❌ Error: " . mysqli_error($conn);
        $message_type = 'error';
    }
}

// ============================================
// DELETE GRADE
// ============================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if (mysqli_query($conn, "DELETE FROM grade_setup WHERE id = $id")) {
        $message = "🗑️ An share grade!";
        $message_type = 'success';
    }
}

// ============================================
// TOGGLE STATUS
// ============================================
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $result = mysqli_query($conn, "SELECT status FROM grade_setup WHERE id = $id");
    if ($row = mysqli_fetch_assoc($result)) {
        $new_status = ($row['status'] == 'Active') ? 'Inactive' : 'Active';
        mysqli_query($conn, "UPDATE grade_setup SET status = '$new_status' WHERE id = $id");
        $message = "✅ An canza status zuwa $new_status!";
        $message_type = 'success';
    }
}

// ============================================
// GET GRADES
// ============================================
$grades_result = mysqli_query($conn, "SELECT * FROM grade_setup ORDER BY min_score DESC");
$total_grades = mysqli_num_rows($grades_result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Grade Setup - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Tahoma,sans-serif; background:#f0f4f8; padding:20px; }
        .container { max-width:1200px; margin:0 auto; }
        
        .topbar { background:#0d2818; padding:15px 25px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; border-radius:12px; margin-bottom:20px; }
        .topbar .logo-title { color:#ffd54f; font-size:1.3rem; font-weight:800; }
        .topbar .logo-title span { color:#a5d6a7; }
        .topbar nav a { color:#c8e6c9; text-decoration:none; padding:8px 16px; border-radius:25px; font-size:0.85rem; }
        .topbar nav a:hover { background:#2e7d32; }
        .topbar nav .logout { background:#c62828; color:white !important; }
        .topbar .admin-badge { background:#c62828; color:white; padding:6px 18px; border-radius:20px; font-size:0.8rem; font-weight:600; }
        
        /* RESULT NAV */
        .result-nav { background:white; padding:15px; border-radius:12px; margin-bottom:20px; display:flex; gap:8px; flex-wrap:wrap; align-items:center; box-shadow:0 2px 10px rgba(0,0,0,0.05); }
        .result-nav .label { font-weight:700; color:#0d2818; margin-right:10px; font-size:0.85rem; }
        .result-nav a { padding:8px 15px; border-radius:6px; text-decoration:none; font-weight:600; font-size:0.75rem; transition:all 0.3s ease; }
        .result-nav a:hover { transform:translateY(-2px); }
        .r-course { background:#2e7d32; color:white; }
        .r-grade { background:#f9a825; color:#0d2818; box-shadow:0 0 0 3px #ffd54f; }
        .r-entry { background:#8d2c2c; color:white; }
        .r-slip { background:#e53935; color:white; }
        .r-slip-pro { background:#a5d6a7; color:#0d2818; }
        .r-transcript { background:#5d4037; color:white; }
        .r-final { background:#558b2f; color:white; }
        .r-settings { background:#e65100; color:white; }
        .r-database { background:#37474f; color:white; }
        .r-statement { background:#455a64; color:white; }
        
        .card { background:white; padding:25px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); margin-bottom:20px; }
        .card h3 { color:#0d2818; margin-bottom:15px; font-size:1.2rem; }
        
        .message { padding:12px 20px; border-radius:8px; margin-bottom:15px; font-weight:600; }
        .message.success { background:#e8f5e9; color:#2e7d32; border-left:4px solid #2e7d32; }
        .message.error { background:#ffebee; color:#c62828; border-left:4px solid #c62828; }
        
        .form-grid { display:grid; grid-template-columns:repeat(6, 1fr); gap:12px; align-items:end; }
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
        
        /* Table Style */
        .grade-table { width:100%; border-collapse:collapse; }
        .grade-table th { 
            background:#0d2818; 
            color:white; 
            padding:12px 15px; 
            text-align:left; 
            font-size:0.8rem; 
            text-transform:uppercase; 
        }
        .grade-table td { 
            padding:12px 15px; 
            border-bottom:1px solid #eee; 
            font-size:0.9rem; 
        }
        .grade-table tr:hover td { background:#f8faf8; }
        .grade-table tr:last-child td { border-bottom:none; }
        
        .grade-badge { 
            display:inline-block; 
            padding:6px 16px; 
            border-radius:20px; 
            font-weight:900; 
            font-size:0.9rem; 
            min-width:40px; 
            text-align:center;
        }
        .badge-A { background:#e8f5e9; color:#2e7d32; }
        .badge-B { background:#e3f2fd; color:#0d47a1; }
        .badge-C { background:#fff3e0; color:#e65100; }
        .badge-D { background:#f3e5f5; color:#6a1b9a; }
        .badge-E { background:#fce4ec; color:#c2185b; }
        .badge-F { background:#ffebee; color:#c62828; }
        
        .status-badge { 
            display:inline-block; 
            padding:4px 12px; 
            border-radius:15px; 
            font-weight:700; 
            font-size:0.75rem; 
        }
        .status-Active { background:#e8f5e9; color:#2e7d32; }
        .status-Inactive { background:#ffebee; color:#c62828; }
        
        .point-cell { font-weight:900; color:#0d2818; }
        .remark-cell { font-weight:600; color:#4a6a4a; }
        
        .action-btns { display:flex; gap:4px; }
        
        .empty-state { text-align:center; padding:40px; color:#6a8f6a; }
        .empty-state i { font-size:3rem; color:#dce8dc; display:block; margin-bottom:15px; }
        
        @media (max-width:768px) {
            .form-grid { grid-template-columns:1fr; }
            .topbar { flex-direction:column; gap:10px; }
            .grade-table { font-size:0.8rem; }
            .grade-table th, .grade-table td { padding:8px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="topbar">
            <div>
                <span class="logo-title">DALA <span>COLLEGE</span></span>
                <span style="display:block; font-size:0.55rem; color:#c8e6c9;">Admin Panel</span>
            </div>
            <nav>
                <a href="admin_dashboard.php">Dashboard</a>
                <a href="logout.php" class="logout">Logout</a>
            </nav>
            <div>
                <span class="admin-badge">👤 <?php echo htmlspecialchars($admin_name); ?></span>
            </div>
        </div>

        <!-- RESULT NAV -->
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

        <h2 style="color:#0d2818; margin-bottom:15px;">⭐ GRADE SETUP</h2>
        <p style="color:#6a8f6a; margin-bottom:20px; font-size:0.9rem;">
            Saita matakan maki (A, B, C, D, E, F) da Grade Points
        </p>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- ADD GRADE -->
        <div class="card">
            <h3>➕ Ƙara Sabon Grade</h3>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Min Score *</label>
                        <input type="number" name="min_score" min="0" max="100" value="70" required>
                    </div>
                    <div class="form-group">
                        <label>Max Score *</label>
                        <input type="number" name="max_score" min="0" max="100" value="100" required>
                    </div>
                    <div class="form-group">
                        <label>Grade *</label>
                        <input type="text" name="grade" placeholder="A" maxlength="2" required style="text-transform:uppercase;">
                    </div>
                    <div class="form-group">
                        <label>Grade Point *</label>
                        <input type="number" name="grade_point" step="0.01" min="0" max="5" value="4.00" required>
                    </div>
                    <div class="form-group">
                        <label>Remark *</label>
                        <input type="text" name="remark" placeholder="Distinction" required>
                    </div>
                    <div class="form-group">
                        <label>Status *</label>
                        <select name="status" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="add_grade" class="btn btn-green" style="margin-top:15px;">
                    <i class="fas fa-plus"></i> Add Grade
                </button>
            </form>
        </div>

        <!-- GRADES LIST -->
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
                <h3 style="margin-bottom:0;">📋 All Grades (<?php echo $total_grades; ?>)</h3>
            </div>
            
            <?php if ($grades_result && mysqli_num_rows($grades_result) > 0): ?>
            <table class="grade-table">
                <thead>
                    <tr>
                        <th style="width:60px;">S/N</th>
                        <th style="width:80px;">Min</th>
                        <th style="width:80px;">Max</th>
                        <th style="width:100px;">Grade</th>
                        <th style="width:100px;">Grade Point</th>
                        <th>Remark</th>
                        <th style="width:100px;">Status</th>
                        <th style="width:150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; while ($g = mysqli_fetch_assoc($grades_result)): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><strong><?php echo $g['min_score']; ?></strong></td>
                        <td><strong><?php echo $g['max_score']; ?></strong></td>
                        <td>
                            <span class="grade-badge badge-<?php echo $g['grade']; ?>">
                                <?php echo $g['grade']; ?>
                            </span>
                        </td>
                        <td class="point-cell"><?php echo number_format($g['grade_point'], 2); ?></td>
                        <td class="remark-cell"><?php echo htmlspecialchars($g['remark']); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $g['status']; ?>">
                                <?php echo $g['status']; ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-btns">
                                <a href="#" class="btn btn-blue btn-sm" onclick="editGrade(<?php echo htmlspecialchars(json_encode($g)); ?>); return false;">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?toggle=<?php echo $g['id']; ?>" class="btn btn-orange btn-sm" title="Toggle Status">
                                    <i class="fas fa-sync"></i>
                                </a>
                                <a href="?delete=<?php echo $g['id']; ?>" class="btn btn-red btn-sm" onclick="return confirm('Share wannan grade?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-star"></i>
                <h3>Babu grades</h3>
                <p>Ƙara sabon grade a sama.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- EDIT MODAL -->
    <div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; padding:30px; overflow-y:auto;">
        <div style="max-width:600px; margin:30px auto; background:white; padding:30px; border-radius:16px; position:relative;">
            <button onclick="document.getElementById('editModal').style.display='none'" style="position:absolute; top:15px; right:20px; font-size:2rem; background:none; border:none; cursor:pointer; color:#c62828;">&times;</button>
            <h3 style="margin-bottom:20px;">✏️ Edit Grade</h3>
            <div id="editFormContainer"></div>
        </div>
    </div>

    <script>
    function editGrade(grade) {
        var html = `
        <form method="POST">
            <input type="hidden" name="grade_id" value="${grade.id}">
            <div class="form-grid" style="grid-template-columns:1fr 1fr;">
                <div class="form-group"><label>Min Score</label><input type="number" name="min_score" value="${grade.min_score}" min="0" max="100" required></div>
                <div class="form-group"><label>Max Score</label><input type="number" name="max_score" value="${grade.max_score}" min="0" max="100" required></div>
                <div class="form-group"><label>Grade</label><input type="text" name="grade" value="${grade.grade}" maxlength="2" required style="text-transform:uppercase;"></div>
                <div class="form-group"><label>Grade Point</label><input type="number" name="grade_point" step="0.01" value="${grade.grade_point}" min="0" max="5" required></div>
                <div class="form-group"><label>Remark</label><input type="text" name="remark" value="${grade.remark}" required></div>
                <div class="form-group"><label>Status</label>
                    <select name="status" required>
                        <option value="Active" ${grade.status == 'Active' ? 'selected' : ''}>Active</option>
                        <option value="Inactive" ${grade.status == 'Inactive' ? 'selected' : ''}>Inactive</option>
                    </select>
                </div>
            </div>
            <input type="hidden" name="update_grade" value="1">
            <button type="submit" class="btn btn-green" style="margin-top:15px;">
                <i class="fas fa-save"></i> Update Grade
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