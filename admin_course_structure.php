<?php
session_start();
include 'connect.php';
include 'result_functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Role ɗin da aka yarda su shiga Course Structure
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
// DOWNLOAD COURSES CSV
// ============================================
if (isset($_GET['download_courses'])) {
    $filter_comb = mysqli_real_escape_string($conn, $_GET['combination'] ?? '');
    $filter_level = mysqli_real_escape_string($conn, $_GET['level'] ?? '');
    $filter_sem = mysqli_real_escape_string($conn, $_GET['semester'] ?? '');
    
    $where = "WHERE 1=1";
    if (!empty($filter_comb)) $where .= " AND combination = '$filter_comb'";
    if (!empty($filter_level)) $where .= " AND level = '$filter_level'";
    if (!empty($filter_sem)) $where .= " AND semester = '$filter_sem'";
    
    $courses_query = mysqli_query($conn, "SELECT * FROM courses $where ORDER BY combination, level, semester, course_code");
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="courses_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Course Code', 'Course Title', 'Combination', 'Level', 'Semester', 'Credits', 'Status', 'Category']);
    
    while ($c = mysqli_fetch_assoc($courses_query)) {
        fputcsv($output, [
            $c['course_code'],
            $c['course_title'],
            $c['combination'],
            $c['level'],
            $c['semester'],
            $c['credits'],
            $c['status'],
            $c['category']
        ]);
    }
    
    fclose($output);
    exit();
}

// ============================================
// IMPORT COURSES FROM CSV
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import_courses'])) {
    if (isset($_FILES['course_file']) && $_FILES['course_file']['error'] == 0) {
        $file_tmp = $_FILES['course_file']['tmp_name'];
        $file_ext = strtolower(pathinfo($_FILES['course_file']['name'], PATHINFO_EXTENSION));
        
        if ($file_ext != 'csv') {
            $message = "❌ Please upload a CSV file only.";
            $message_type = 'error';
        } else {
            $file = fopen($file_tmp, 'r');
            $header = fgetcsv($file); // Skip header
            
            $imported = 0;
            $updated = 0;
            $errors = [];
            $row_num = 1;
            
            while (($row = fgetcsv($file)) !== FALSE) {
                $row_num++;
                if (empty(array_filter($row))) continue;
                
                $course_code = strtoupper(mysqli_real_escape_string($conn, trim($row[0] ?? '')));
                $course_title = mysqli_real_escape_string($conn, trim($row[1] ?? ''));
                $combination = mysqli_real_escape_string($conn, trim($row[2] ?? ''));
                $level = mysqli_real_escape_string($conn, trim($row[3] ?? 'NCE I'));
                $semester = mysqli_real_escape_string($conn, trim($row[4] ?? 'First Semester'));
                $credits = intval($row[5] ?? 2);
                $status = mysqli_real_escape_string($conn, trim($row[6] ?? 'Compulsory'));
                $category = mysqli_real_escape_string($conn, trim($row[7] ?? 'EDU'));
                
                if (empty($course_code) || empty($course_title) || empty($combination)) {
                    $errors[] = "Row $row_num: Missing required fields";
                    continue;
                }
                
                // Check if exists
                $check = mysqli_query($conn, "SELECT id FROM courses 
                                              WHERE course_code = '$course_code' 
                                              AND combination = '$combination' 
                                              AND level = '$level' 
                                              AND semester = '$semester'");
                
                if (mysqli_num_rows($check) > 0) {
                    $update = "UPDATE courses SET 
                               course_title = '$course_title',
                               credits = $credits,
                               status = '$status',
                               category = '$category'
                               WHERE course_code = '$course_code' 
                               AND combination = '$combination' 
                               AND level = '$level' 
                               AND semester = '$semester'";
                    if (mysqli_query($conn, $update)) $updated++;
                } else {
                    $insert = "INSERT INTO courses (course_code, course_title, combination, level, semester, credits, status, category) 
                               VALUES ('$course_code', '$course_title', '$combination', '$level', '$semester', $credits, '$status', '$category')";
                    if (mysqli_query($conn, $insert)) $imported++;
                }
            }
            fclose($file);
            
            $message = "✅ Import successful!<br>📊 New: <strong>$imported</strong> | 🔄 Updated: <strong>$updated</strong>";
            if (!empty($errors)) $message .= "<br>⚠️ Errors: " . count($errors);
            $message_type = 'success';
        }
    } else {
        $message = "❌ Please select a file.";
        $message_type = 'error';
    }
}

// ============================================
// ADD COURSE
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_course'])) {
    $course_code = strtoupper(mysqli_real_escape_string($conn, trim($_POST['course_code'])));
    $course_title = mysqli_real_escape_string($conn, trim($_POST['course_title']));
    $combination = mysqli_real_escape_string($conn, $_POST['combination']);
    $school = mysqli_real_escape_string($conn, $_POST['school'] ?? '');
    $department = mysqli_real_escape_string($conn, $_POST['department'] ?? '');
    $programme = mysqli_real_escape_string($conn, $_POST['programme'] ?? 'NCE');
    $level = mysqli_real_escape_string($conn, $_POST['level']);
    $semester = mysqli_real_escape_string($conn, $_POST['semester']);
    $credits = intval($_POST['credits']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    
    $check = mysqli_query($conn, "SELECT id FROM courses 
                                  WHERE course_code = '$course_code' 
                                  AND combination = '$combination' 
                                  AND level = '$level' 
                                  AND semester = '$semester'");
    
    if (mysqli_num_rows($check) > 0) {
        $message = "❌ Course '$course_code' already exists for $combination - $level - $semester!";
        $message_type = 'error';
    } else {
        $insert = "INSERT INTO courses (course_code, course_title, combination, school, department, programme, level, semester, credits, status, category) 
                   VALUES ('$course_code', '$course_title', '$combination', '$school', '$department', '$programme', '$level', '$semester', $credits, '$status', '$category')";
        if (mysqli_query($conn, $insert)) {
            $message = "✅ Course '$course_code' added successfully!";
            $message_type = 'success';
        } else {
            $message = "❌ Error: " . mysqli_error($conn);
            $message_type = 'error';
        }
    }
}

// ============================================
// UPDATE COURSE
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_course'])) {
    $id = intval($_POST['course_id']);
    $course_code = strtoupper(mysqli_real_escape_string($conn, trim($_POST['course_code'])));
    $course_title = mysqli_real_escape_string($conn, trim($_POST['course_title']));
    $combination = mysqli_real_escape_string($conn, $_POST['combination']);
    $level = mysqli_real_escape_string($conn, $_POST['level']);
    $semester = mysqli_real_escape_string($conn, $_POST['semester']);
    $credits = intval($_POST['credits']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    
    $update = "UPDATE courses SET 
               course_code = '$course_code',
               course_title = '$course_title',
               combination = '$combination',
               level = '$level',
               semester = '$semester',
               credits = $credits,
               status = '$status',
               category = '$category'
               WHERE id = $id";
    
    if (mysqli_query($conn, $update)) {
        $message = "✅ Course updated successfully!";
        $message_type = 'success';
    } else {
        $message = "❌ Error: " . mysqli_error($conn);
        $message_type = 'error';
    }
}

// ============================================
// DELETE COURSE
// ============================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if (mysqli_query($conn, "DELETE FROM courses WHERE id = $id")) {
        $message = "🗑️ Course deleted!";
        $message_type = 'success';
    }
}

// ============================================
// TOGGLE STATUS
// ============================================
if (isset($_GET['toggle_status']) && is_numeric($_GET['toggle_status'])) {
    $id = intval($_GET['toggle_status']);
    $result = mysqli_query($conn, "SELECT status FROM courses WHERE id = $id");
    if ($row = mysqli_fetch_assoc($result)) {
        $new_status = ($row['status'] == 'Compulsory') ? 'Elective' : 'Compulsory';
        mysqli_query($conn, "UPDATE courses SET status = '$new_status' WHERE id = $id");
        $message = "✅ Status: $new_status";
        $message_type = 'success';
    }
}

// ============================================
// FILTER
// ============================================
$filter_comb = isset($_GET['combination']) ? mysqli_real_escape_string($conn, $_GET['combination']) : '';
$filter_level = isset($_GET['level']) ? mysqli_real_escape_string($conn, $_GET['level']) : '';
$filter_sem = isset($_GET['semester']) ? mysqli_real_escape_string($conn, $_GET['semester']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$where = "WHERE 1=1";
if (!empty($filter_comb)) $where .= " AND combination = '$filter_comb'";
if (!empty($filter_level)) $where .= " AND level = '$filter_level'";
if (!empty($filter_sem)) $where .= " AND semester = '$filter_sem'";
if (!empty($search)) $where .= " AND (course_code LIKE '%$search%' OR course_title LIKE '%$search%')";

$courses_query = "SELECT * FROM courses $where 
                  ORDER BY combination, level, semester, course_code";
$courses_result = mysqli_query($conn, $courses_query);

$combinations_list = [];
$cq = mysqli_query($conn, "SELECT DISTINCT combination FROM courses WHERE combination IS NOT NULL AND combination != '' ORDER BY combination");
while ($c = mysqli_fetch_assoc($cq)) $combinations_list[] = $c['combination'];

$total_courses = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM courses"))['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Course Structure - Admin</title>
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
        .r-course { background:#2e7d32; color:white; box-shadow:0 0 0 3px #ffd54f; }
        .r-grade { background:#f9a825; color:#0d2818; }
        .r-entry { background:#8d2c2c; color:white; }
        .r-slip { background:#e53935; color:white; }
        .r-slip-pro { background:#a5d6a7; color:#0d2818; }
        .r-transcript { background:#5d4037; color:white; }
        .r-final { background:#558b2f; color:white; }
        .r-settings { background:#e65100; color:white; }
        .r-database { background:#37474f; color:white; }
        .r-statement { background:#455a64; color:white; }
        
        .card { background:white; padding:25px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); margin-bottom:20px; }
        .card h2 { color:#0d2818; margin-bottom:15px; font-size:1.2rem; }
        
        .message { padding:12px 20px; border-radius:8px; margin-bottom:15px; font-weight:600; }
        .message.success { background:#e8f5e9; color:#2e7d32; border-left:4px solid #2e7d32; }
        .message.error { background:#ffebee; color:#c62828; border-left:4px solid #c62828; }
        
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
        
        .filter-bar { display:flex; gap:10px; margin-bottom:15px; flex-wrap:wrap; align-items:center; }
        .filter-bar select, .filter-bar input { padding:8px 15px; border:2px solid #dce8dc; border-radius:8px; font-size:0.85rem; }
        .filter-bar button { padding:8px 20px; background:#2e7d32; color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer; }
        
        .table-container { overflow:auto; max-height:600px; }
        table { width:100%; border-collapse:collapse; min-width:1100px; }
        table th { background:#0d2818; color:white; padding:10px 12px; text-align:left; font-size:0.75rem; text-transform:uppercase; position:sticky; top:0; }
        table td { padding:8px 12px; border-bottom:1px solid #e0e0e0; font-size:0.85rem; }
        table tr:hover { background:#f8faf8; }
        
        .badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:0.7rem; font-weight:700; }
        .badge-edu { background:#e3f2fd; color:#0d47a1; }
        .badge-gse { background:#f3e5f5; color:#6a1b9a; }
        .badge-csc { background:#e8f5e9; color:#2e7d32; }
        .badge-bio { background:#fff3e0; color:#e65100; }
        .badge-phy { background:#e0f7fa; color:#00695c; }
        .badge-eng { background:#fce4ec; color:#c2185b; }
        .badge-eco { background:#fff8e1; color:#f57f17; }
        .badge-other { background:#f5f5f5; color:#424242; }
        .badge-compulsory { background:#e8f5e9; color:#2e7d32; }
        .badge-elective { background:#fff3e0; color:#e65100; }
        
        .action-btns { display:flex; gap:4px; flex-wrap:wrap; }
        
        .modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; overflow-y:auto; padding:30px; }
        .modal-content { max-width:800px; margin:30px auto; background:white; padding:30px; border-radius:16px; position:relative; }
        .modal-close { position:absolute; top:15px; right:20px; font-size:2rem; background:none; border:none; cursor:pointer; color:#c62828; }
        
        .import-area { background:#f8faf8; padding:25px; border-radius:10px; border:2px dashed #2e7d32; text-align:center; }
        .import-area input[type="file"] { padding:10px; border:2px solid #dce8dc; border-radius:8px; background:white; width:100%; max-width:400px; margin:10px auto; display:block; }
        
        @media (max-width:768px) {
            .form-grid { grid-template-columns:1fr; }
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

        <h2 style="color:#0d2818; margin-bottom:15px;">📚 Course Structure</h2>
        <p style="color:#6a8f6a; margin-bottom:20px; font-size:0.9rem;">
            Add, edit, delete courses — download/upload CSV
        </p>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- ADD COURSE -->
        <div class="card">
            <h2>➕ Add New Course</h2>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Course Code *</label>
                        <input type="text" name="course_code" placeholder="e.g. ISS112" required>
                    </div>
                    <div class="form-group">
                        <label>Course Title *</label>
                        <input type="text" name="course_title" placeholder="e.g. Islamic Studies" required>
                    </div>
                    <div class="form-group">
                        <label>Combination *</label>
                        <select name="combination" required>
                            <option value="">-- Select --</option>
                            <option value="CSC/BIO">CSC/BIO</option>
                            <option value="CSC/PHY">CSC/PHY</option>
                            <option value="CSC/ISC">CSC/ISC</option>
                            <option value="ENG/ECO">ENG/ECO</option>
                            <option value="ENG/ISS">ENG/ISS</option>
                            <option value="ENG/HAU">ENG/HAU</option>
                            <option value="ENG/SOS">ENG/SOS</option>
                            <option value="ARB/ISS">ARB/ISS</option>
                            <option value="PED">PED</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Level *</label>
                        <select name="level" required>
                            <option value="NCE I">NCE I</option>
                            <option value="NCE II">NCE II</option>
                            <option value="NCE III">NCE III</option>
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
                        <label>Credits *</label>
                        <input type="number" name="credits" min="1" max="6" value="2" required>
                    </div>
                    <div class="form-group">
                        <label>Status *</label>
                        <select name="status" required>
                            <option value="Compulsory">Compulsory</option>
                            <option value="Elective">Elective</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Category *</label>
                        <select name="category" required>
                            <option value="EDU">EDU</option>
                            <option value="GSE">GSE</option>
                            <option value="CSC">CSC</option>
                            <option value="BIO">BIO</option>
                            <option value="PHY">PHY</option>
                            <option value="ISC">ISC</option>
                            <option value="ENG">ENG</option>
                            <option value="ECO">ECO</option>
                            <option value="ARB">ARB</option>
                            <option value="ISS">ISS</option>
                            <option value="HAU">HAU</option>
                            <option value="SOS">SOS</option>
                            <option value="PED">PED</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="add_course" class="btn btn-green" style="margin-top:15px;">
                    <i class="fas fa-plus"></i> Add Course
                </button>
            </form>
        </div>

        <!-- IMPORT CSV -->
        <div class="card">
            <h2>📤 Import Courses from CSV</h2>
            <p style="color:#6a8f6a; font-size:0.9rem; margin-bottom:15px;">
                Format: <code>Course Code, Course Title, Combination, Level, Semester, Credits, Status, Category</code>
            </p>
            <div class="import-area">
                <form method="POST" enctype="multipart/form-data">
                    <input type="file" name="course_file" accept=".csv" required>
                    <button type="submit" name="import_courses" class="btn btn-blue" style="margin-top:10px;">
                        <i class="fas fa-upload"></i> Import CSV
                    </button>
                </form>
            </div>
        </div>

        <!-- COURSES LIST -->
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; margin-bottom:15px;">
                <h2 style="margin-bottom:0;">📋 All Courses (<?php echo $total_courses; ?>)</h2>
                <a href="?download_courses=1&combination=<?php echo urlencode($filter_comb); ?>&level=<?php echo urlencode($filter_level); ?>&semester=<?php echo urlencode($filter_sem); ?>" 
                   class="btn btn-orange">
                    <i class="fas fa-download"></i> Download CSV
                </a>
            </div>
            
            <form method="GET" class="filter-bar">
                <input type="text" name="search" placeholder="🔍 Search course..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="combination">
                    <option value="">All Combinations</option>
                    <?php foreach ($combinations_list as $comb): ?>
                        <option value="<?php echo $comb; ?>" <?php echo ($filter_comb == $comb) ? 'selected' : ''; ?>>
                            <?php echo $comb; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="level">
                    <option value="">All Levels</option>
                    <option value="NCE I" <?php echo ($filter_level == 'NCE I') ? 'selected' : ''; ?>>NCE I</option>
                    <option value="NCE II" <?php echo ($filter_level == 'NCE II') ? 'selected' : ''; ?>>NCE II</option>
                    <option value="NCE III" <?php echo ($filter_level == 'NCE III') ? 'selected' : ''; ?>>NCE III</option>
                </select>
                <select name="semester">
                    <option value="">All Semesters</option>
                    <option value="First Semester" <?php echo ($filter_sem == 'First Semester') ? 'selected' : ''; ?>>First Semester</option>
                    <option value="Second Semester" <?php echo ($filter_sem == 'Second Semester') ? 'selected' : ''; ?>>Second Semester</option>
                </select>
                <button type="submit"><i class="fas fa-filter"></i> Filter</button>
                <a href="admin_course_structure.php" style="padding:8px 15px; background:#f0f4f8; border-radius:8px; text-decoration:none; color:#0d2818; font-weight:600;">Reset</a>
            </form>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Code</th>
                            <th>Title</th>
                            <th>Combination</th>
                            <th>Level</th>
                            <th>Semester</th>
                            <th>Units</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($courses_result) > 0): ?>
                            <?php $i = 1; while ($c = mysqli_fetch_assoc($courses_result)): 
                                $cat = strtolower($c['category']);
                                $badge_class = in_array($cat, ['edu','gse','csc','bio','phy','eng','eco']) ? 'badge-'.$cat : 'badge-other';
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><strong><?php echo htmlspecialchars($c['course_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($c['course_title']); ?></td>
                                <td><?php echo htmlspecialchars($c['combination']); ?></td>
                                <td><?php echo htmlspecialchars($c['level']); ?></td>
                                <td><?php echo htmlspecialchars($c['semester']); ?></td>
                                <td><?php echo $c['credits']; ?></td>
                                <td><span class="badge <?php echo $badge_class; ?>"><?php echo $c['category']; ?></span></td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($c['status']); ?>">
                                        <?php echo $c['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <a href="#" class="btn btn-blue btn-sm" onclick='editCourse(<?php echo json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT); ?>); return false;' title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?toggle_status=<?php echo $c['id']; ?>" class="btn btn-orange btn-sm" title="Toggle Status">
                                            <i class="fas fa-sync"></i>
                                        </a>
                                        <a href="?delete=<?php echo $c['id']; ?>" class="btn btn-red btn-sm" onclick="return confirm('Delete this course?')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" style="text-align:center; padding:40px; color:#999;">
                                    <i class="fas fa-book" style="font-size:2rem; display:block; margin-bottom:10px;"></i>
                                    No courses found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- EDIT MODAL -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-content">
            <button class="modal-close" onclick="document.getElementById('editModal').style.display='none'">&times;</button>
            <h3 style="margin-bottom:20px;">✏️ Edit Course</h3>
            <div id="editFormContainer"></div>
        </div>
    </div>

    <script>
    function editCourse(course) {
        var html = `
        <form method="POST">
            <input type="hidden" name="course_id" value="${course.id}">
            <div class="form-grid" style="grid-template-columns:1fr 1fr;">
                <div class="form-group">
                    <label>Course Code</label>
                    <input type="text" name="course_code" value="${course.course_code}" required>
                </div>
                <div class="form-group">
                    <label>Course Title</label>
                    <input type="text" name="course_title" value="${course.course_title}" required>
                </div>
                <div class="form-group">
                    <label>Combination</label>
                    <select name="combination" required>
                        <option value="CSC/BIO" ${course.combination=='CSC/BIO'?'selected':''}>CSC/BIO</option>
                        <option value="CSC/PHY" ${course.combination=='CSC/PHY'?'selected':''}>CSC/PHY</option>
                        <option value="CSC/ISC" ${course.combination=='CSC/ISC'?'selected':''}>CSC/ISC</option>
                        <option value="ENG/ECO" ${course.combination=='ENG/ECO'?'selected':''}>ENG/ECO</option>
                        <option value="ENG/ISS" ${course.combination=='ENG/ISS'?'selected':''}>ENG/ISS</option>
                        <option value="ENG/HAU" ${course.combination=='ENG/HAU'?'selected':''}>ENG/HAU</option>
                        <option value="ENG/SOS" ${course.combination=='ENG/SOS'?'selected':''}>ENG/SOS</option>
                        <option value="ARB/ISS" ${course.combination=='ARB/ISS'?'selected':''}>ARB/ISS</option>
                        <option value="PED" ${course.combination=='PED'?'selected':''}>PED</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Level</label>
                    <select name="level" required>
                        <option value="NCE I" ${course.level=='NCE I'?'selected':''}>NCE I</option>
                        <option value="NCE II" ${course.level=='NCE II'?'selected':''}>NCE II</option>
                        <option value="NCE III" ${course.level=='NCE III'?'selected':''}>NCE III</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Semester</label>
                    <select name="semester" required>
                        <option value="First Semester" ${course.semester=='First Semester'?'selected':''}>First Semester</option>
                        <option value="Second Semester" ${course.semester=='Second Semester'?'selected':''}>Second Semester</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Credits</label>
                    <input type="number" name="credits" value="${course.credits}" min="1" max="6" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" required>
                        <option value="Compulsory" ${course.status=='Compulsory'?'selected':''}>Compulsory</option>
                        <option value="Elective" ${course.status=='Elective'?'selected':''}>Elective</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" required>
                        <option value="EDU" ${course.category=='EDU'?'selected':''}>EDU</option>
                        <option value="GSE" ${course.category=='GSE'?'selected':''}>GSE</option>
                        <option value="CSC" ${course.category=='CSC'?'selected':''}>CSC</option>
                        <option value="BIO" ${course.category=='BIO'?'selected':''}>BIO</option>
                        <option value="PHY" ${course.category=='PHY'?'selected':''}>PHY</option>
                        <option value="ISC" ${course.category=='ISC'?'selected':''}>ISC</option>
                        <option value="ENG" ${course.category=='ENG'?'selected':''}>ENG</option>
                        <option value="ECO" ${course.category=='ECO'?'selected':''}>ECO</option>
                        <option value="ARB" ${course.category=='ARB'?'selected':''}>ARB</option>
                        <option value="ISS" ${course.category=='ISS'?'selected':''}>ISS</option>
                        <option value="HAU" ${course.category=='HAU'?'selected':''}>HAU</option>
                        <option value="SOS" ${course.category=='SOS'?'selected':''}>SOS</option>
                        <option value="PED" ${course.category=='PED'?'selected':''}>PED</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="update_course" class="btn btn-green" style="margin-top:15px;">
                <i class="fas fa-save"></i> Update Course
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