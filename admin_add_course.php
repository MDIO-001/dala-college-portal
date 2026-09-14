<?php
session_start();
include 'connect.php';

// ============================================
// CHECK ADMIN LOGIN (daidai da admin_dashboard.php)
// ============================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

$admin_name = $_SESSION['fullname'] ?? 'Admin';
$message = '';
$message_type = '';

// ============================================
// ADD COURSE
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_course'])) {
    $course_code = mysqli_real_escape_string($conn, strtoupper(trim($_POST['course_code'])));
    $course_title = mysqli_real_escape_string($conn, trim($_POST['course_title']));
    $combination = mysqli_real_escape_string($conn, $_POST['combination']);
    $school = mysqli_real_escape_string($conn, $_POST['school']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $programme = mysqli_real_escape_string($conn, $_POST['programme']);
    $level = mysqli_real_escape_string($conn, $_POST['level']);
    $credits = intval($_POST['credits']);
    $semester = mysqli_real_escape_string($conn, $_POST['semester']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    
    // Check if course already exists
    $check = mysqli_query($conn, "SELECT id FROM courses 
                                  WHERE course_code = '$course_code' 
                                  AND combination = '$combination' 
                                  AND level = '$level' 
                                  AND semester = '$semester'");
    
    if (mysqli_num_rows($check) > 0) {
        $message = "❌ Course '$course_code' already exists for $combination - $level - $semester!";
        $message_type = 'error';
    } else {
        $insert = "INSERT INTO courses (course_code, combination, school, course_title, 
                    department, programme, level, credits, semester, status, category) 
                   VALUES ('$course_code', '$combination', '$school', '$course_title', 
                    '$department', '$programme', '$level', $credits, '$semester', '$status', '$category')";
        
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
    $course_code = mysqli_real_escape_string($conn, strtoupper(trim($_POST['course_code'])));
    $course_title = mysqli_real_escape_string($conn, trim($_POST['course_title']));
    $combination = mysqli_real_escape_string($conn, $_POST['combination']);
    $school = mysqli_real_escape_string($conn, $_POST['school']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $programme = mysqli_real_escape_string($conn, $_POST['programme']);
    $level = mysqli_real_escape_string($conn, $_POST['level']);
    $credits = intval($_POST['credits']);
    $semester = mysqli_real_escape_string($conn, $_POST['semester']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    
    $update = "UPDATE courses SET 
        course_code = '$course_code',
        course_title = '$course_title',
        combination = '$combination',
        school = '$school',
        department = '$department',
        programme = '$programme',
        level = '$level',
        credits = $credits,
        semester = '$semester',
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
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $delete = "DELETE FROM courses WHERE id = $id";
    if (mysqli_query($conn, $delete)) {
        $message = "🗑️ Course deleted successfully!";
        $message_type = 'success';
    }
}

// ============================================
// GET COURSES WITH FILTER
// ============================================
$filter_comb = isset($_GET['combination']) ? mysqli_real_escape_string($conn, $_GET['combination']) : '';
$filter_level = isset($_GET['level']) ? mysqli_real_escape_string($conn, $_GET['level']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$where = "WHERE 1=1";
if (!empty($filter_comb)) $where .= " AND combination = '$filter_comb'";
if (!empty($filter_level)) $where .= " AND level = '$filter_level'";
if (!empty($search)) $where .= " AND (course_code LIKE '%$search%' OR course_title LIKE '%$search%')";

$courses_query = "SELECT * FROM courses $where 
                  ORDER BY combination, level, semester, 
                  FIELD(category, 'EDU', 'GSE', 'PED', 'CSC', 'BIO', 'ISC', 'PHY', 'ENG', 'ECO', 'ARB', 'ISS', 'HAU', 'SOS'), 
                  course_code";
$courses_result = mysqli_query($conn, $courses_query);

// Get unique combinations for filter
$combinations = [];
$comb_result = mysqli_query($conn, "SELECT DISTINCT combination FROM courses ORDER BY combination");
while ($c = mysqli_fetch_assoc($comb_result)) {
    $combinations[] = $c['combination'];
}

$total_courses = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM courses"))['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f4f8;
            padding: 20px;
        }
        .container { max-width: 1300px; margin: 0 auto; }
        
        .topbar {
            background: #0d2818;
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .topbar .logo-title { color: #ffd54f; font-size: 1.3rem; font-weight: 800; }
        .topbar .logo-title span { color: #a5d6a7; }
        .topbar .logo-sub { display: block; font-size: 0.55rem; color: #c8e6c9; }
        .topbar nav a {
            color: #c8e6c9; text-decoration: none; padding: 8px 16px; border-radius: 25px; font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        .topbar nav a:hover { background: #2e7d32; }
        .topbar nav .active { background: #ffd54f; color: #0d2818 !important; font-weight: 700; }
        .topbar nav .logout { background: #c62828; color: white !important; }
        .topbar .admin-badge {
            background: #c62828; color: white; padding: 6px 18px; border-radius: 20px;
            font-size: 0.8rem; font-weight: 600;
        }
        
        .card {
            background: white; padding: 25px; border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px;
        }
        .card h3 { color: #0d2818; margin-bottom: 15px; }
        
        .message {
            padding: 12px 20px; border-radius: 8px; margin-bottom: 15px; font-weight: 600;
        }
        .message.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }
        .message.error { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        .form-group { margin-bottom: 5px; }
        .form-group label {
            display: block; font-weight: 600; color: #0d2818;
            margin-bottom: 5px; font-size: 0.85rem;
        }
        .form-group input, .form-group select {
            width: 100%; padding: 10px 12px; border: 2px solid #dce8dc;
            border-radius: 8px; font-size: 0.9rem; font-family: inherit;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #2e7d32; outline: none;
        }
        
        .btn-submit {
            background: #2e7d32; color: white; border: none;
            padding: 12px 30px; border-radius: 8px;
            font-weight: 700; font-size: 0.95rem; cursor: pointer;
            margin-top: 15px;
        }
        .btn-submit:hover { background: #1b5e20; }
        
        .filter-bar {
            display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; align-items: center;
        }
        .filter-bar select, .filter-bar input {
            padding: 8px 15px; border: 2px solid #dce8dc;
            border-radius: 8px; font-size: 0.85rem;
        }
        .filter-bar button {
            padding: 8px 20px; background: #2e7d32; color: white;
            border: none; border-radius: 8px; font-weight: 600; cursor: pointer;
        }
        
        .table-container { overflow: auto; max-height: 600px; }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        table th {
            background: #0d2818; color: white; padding: 10px 12px;
            text-align: left; font-size: 0.75rem; text-transform: uppercase;
            position: sticky; top: 0;
        }
        table td {
            padding: 8px 12px; border-bottom: 1px solid #e0e0e0; font-size: 0.85rem;
        }
        table tr:hover { background: #f8faf8; }
        
        .badge {
            display: inline-block; padding: 3px 10px; border-radius: 12px;
            font-size: 0.7rem; font-weight: 700;
        }
        .badge-edu { background: #e3f2fd; color: #0d47a1; }
        .badge-gse { background: #f3e5f5; color: #6a1b9a; }
        .badge-csc { background: #e8f5e9; color: #2e7d32; }
        .badge-bio { background: #fff3e0; color: #e65100; }
        .badge-phy { background: #e0f7fa; color: #00695c; }
        .badge-eng { background: #fce4ec; color: #c2185b; }
        .badge-eco { background: #fff8e1; color: #f57f17; }
        .badge-other { background: #f5f5f5; color: #424242; }
        
        .btn {
            padding: 4px 10px; border: none; border-radius: 5px;
            font-weight: 600; font-size: 0.7rem; cursor: pointer;
            text-decoration: none; display: inline-block; margin: 1px;
        }
        .btn-danger { background: #c62828; color: white; }
        .btn-danger:hover { background: #b71c1c; }
        .btn-edit { background: #1976d2; color: white; }
        .btn-edit:hover { background: #0d47a1; }
        
        .course-count {
            color: #6a8f6a; font-size: 0.9rem; font-weight: 600;
        }
        
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0;
            width: 100%; height: 100%; background: rgba(0,0,0,0.5);
            z-index: 1000; overflow-y: auto; padding: 30px;
        }
        .modal-content {
            max-width: 700px; margin: 30px auto; background: white;
            padding: 30px; border-radius: 16px; position: relative;
        }
        .modal-close {
            position: absolute; top: 15px; right: 20px;
            font-size: 2rem; background: none; border: none;
            cursor: pointer; color: #c62828;
        }
        
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .topbar { flex-direction: column; gap: 10px; }
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
                <a href="admin_registrations.php">Registrations</a>
                <a href="admin_students.php">Students</a>
                <a href="admin_add_course.php" class="active">Courses</a>
                <a href="admin_view_results.php">Results</a>
                <a href="logout.php" class="logout">Logout</a>
            </nav>
            <div>
                <span class="admin-badge">👤 <?php echo htmlspecialchars($admin_name); ?></span>
            </div>
        </div>

        <h2 style="color:#0d2818; margin-bottom:15px;">📚 Manage Courses</h2>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- ========== ADD COURSE ========== -->
        <div class="card">
            <h3>➕ Add New Course</h3>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Course Code *</label>
                        <input type="text" name="course_code" placeholder="e.g. CSC111" required>
                    </div>
                    <div class="form-group">
                        <label>Course Title *</label>
                        <input type="text" name="course_title" placeholder="e.g. Introduction to Computer Science" required>
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
                        </select>
                    </div>
                    <div class="form-group">
                        <label>School *</label>
                        <select name="school" required>
                            <option value="School of Science">School of Science</option>
                            <option value="School of Arts and Social Sciences">School of Arts and Social Sciences</option>
                            <option value="School of Languages">School of Languages</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Department *</label>
                        <input type="text" name="department" placeholder="e.g. COMPUTER/BIOLOGY STUDIES" required>
                    </div>
                    <div class="form-group">
                        <label>Programme *</label>
                        <select name="programme" required>
                            <option value="NCE">NCE</option>
                            <option value="DEGREE">DEGREE</option>
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
                <button type="submit" name="add_course" class="btn-submit">
                    <i class="fas fa-plus"></i> Add Course
                </button>
            </form>
        </div>

        <!-- ========== COURSES LIST ========== -->
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; margin-bottom:15px;">
                <h3 style="margin-bottom:0;">📋 All Courses <span class="course-count">(<?php echo $total_courses; ?> total)</span></h3>
            </div>
            
            <!-- Filter -->
            <form method="GET" class="filter-bar">
                <select name="combination">
                    <option value="">All Combinations</option>
                    <?php foreach ($combinations as $comb): ?>
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
                <input type="text" name="search" placeholder="Search course..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit"><i class="fas fa-search"></i> Filter</button>
                <a href="admin_add_course.php" style="padding:8px 15px; background:#f0f4f8; border-radius:8px; text-decoration:none; color:#0d2818; font-weight:600;">Reset</a>
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
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($courses_result) > 0): ?>
                            <?php $i = 1; while ($c = mysqli_fetch_assoc($courses_result)): 
                                $cat = strtolower($c['category']);
                                $badge_class = 'badge-other';
                                if (in_array($cat, ['edu', 'gse', 'csc', 'bio', 'phy', 'eng', 'eco'])) {
                                    $badge_class = 'badge-' . $cat;
                                }
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
                                    <a href="#" class="btn btn-edit" onclick="editCourse(<?php echo $c['id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="?delete=<?php echo $c['id']; ?>" 
                                       class="btn btn-danger"
                                       onclick="return confirm('Delete this course?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align:center; padding:40px; color:#999;">
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

    <!-- ========== EDIT COURSE MODAL ========== -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal()">&times;</button>
            <h3 style="margin-bottom:20px;">✏️ Edit Course</h3>
            <div id="editFormContainer"></div>
        </div>
    </div>

    <script>
    function closeModal() {
        document.getElementById('editModal').style.display = 'none';
    }
    
    window.onclick = function(event) {
        if (event.target.className === 'modal-overlay') {
            event.target.style.display = 'none';
        }
    }
    
    function editCourse(id) {
        fetch('get_course.php?id=' + id)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    var html = `
                    <form method="POST">
                        <input type="hidden" name="course_id" value="${data.id}">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Course Code</label>
                                <input type="text" name="course_code" value="${data.course_code || ''}" required>
                            </div>
                            <div class="form-group">
                                <label>Course Title</label>
                                <input type="text" name="course_title" value="${data.course_title || ''}" required>
                            </div>
                            <div class="form-group">
                                <label>Combination</label>
                                <select name="combination" required>
                                    <option value="CSC/BIO" ${data.combination == 'CSC/BIO' ? 'selected' : ''}>CSC/BIO</option>
                                    <option value="CSC/PHY" ${data.combination == 'CSC/PHY' ? 'selected' : ''}>CSC/PHY</option>
                                    <option value="CSC/ISC" ${data.combination == 'CSC/ISC' ? 'selected' : ''}>CSC/ISC</option>
                                    <option value="ENG/ECO" ${data.combination == 'ENG/ECO' ? 'selected' : ''}>ENG/ECO</option>
                                    <option value="ENG/ISS" ${data.combination == 'ENG/ISS' ? 'selected' : ''}>ENG/ISS</option>
                                    <option value="ENG/HAU" ${data.combination == 'ENG/HAU' ? 'selected' : ''}>ENG/HAU</option>
                                    <option value="ENG/SOS" ${data.combination == 'ENG/SOS' ? 'selected' : ''}>ENG/SOS</option>
                                    <option value="ARB/ISS" ${data.combination == 'ARB/ISS' ? 'selected' : ''}>ARB/ISS</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>School</label>
                                <select name="school" required>
                                    <option value="School of Science" ${data.school == 'School of Science' ? 'selected' : ''}>School of Science</option>
                                    <option value="School of Arts and Social Sciences" ${data.school == 'School of Arts and Social Sciences' ? 'selected' : ''}>School of Arts and Social Sciences</option>
                                    <option value="School of Languages" ${data.school == 'School of Languages' ? 'selected' : ''}>School of Languages</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Department</label>
                                <input type="text" name="department" value="${data.department || ''}" required>
                            </div>
                            <div class="form-group">
                                <label>Programme</label>
                                <select name="programme" required>
                                    <option value="NCE" ${data.programme == 'NCE' ? 'selected' : ''}>NCE</option>
                                    <option value="DEGREE" ${data.programme == 'DEGREE' ? 'selected' : ''}>DEGREE</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Level</label>
                                <select name="level" required>
                                    <option value="NCE I" ${data.level == 'NCE I' ? 'selected' : ''}>NCE I</option>
                                    <option value="NCE II" ${data.level == 'NCE II' ? 'selected' : ''}>NCE II</option>
                                    <option value="NCE III" ${data.level == 'NCE III' ? 'selected' : ''}>NCE III</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Semester</label>
                                <select name="semester" required>
                                    <option value="First Semester" ${data.semester == 'First Semester' ? 'selected' : ''}>First Semester</option>
                                    <option value="Second Semester" ${data.semester == 'Second Semester' ? 'selected' : ''}>Second Semester</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Credits</label>
                                <input type="number" name="credits" value="${data.credits || 2}" min="1" max="6" required>
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" required>
                                    <option value="Compulsory" ${data.status == 'Compulsory' ? 'selected' : ''}>Compulsory</option>
                                    <option value="Elective" ${data.status == 'Elective' ? 'selected' : ''}>Elective</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Category</label>
                                <select name="category" required>
                                    <option value="EDU" ${data.category == 'EDU' ? 'selected' : ''}>EDU</option>
                                    <option value="GSE" ${data.category == 'GSE' ? 'selected' : ''}>GSE</option>
                                    <option value="CSC" ${data.category == 'CSC' ? 'selected' : ''}>CSC</option>
                                    <option value="BIO" ${data.category == 'BIO' ? 'selected' : ''}>BIO</option>
                                    <option value="PHY" ${data.category == 'PHY' ? 'selected' : ''}>PHY</option>
                                    <option value="ISC" ${data.category == 'ISC' ? 'selected' : ''}>ISC</option>
                                    <option value="ENG" ${data.category == 'ENG' ? 'selected' : ''}>ENG</option>
                                    <option value="ECO" ${data.category == 'ECO' ? 'selected' : ''}>ECO</option>
                                    <option value="ARB" ${data.category == 'ARB' ? 'selected' : ''}>ARB</option>
                                    <option value="ISS" ${data.category == 'ISS' ? 'selected' : ''}>ISS</option>
                                    <option value="HAU" ${data.category == 'HAU' ? 'selected' : ''}>HAU</option>
                                    <option value="SOS" ${data.category == 'SOS' ? 'selected' : ''}>SOS</option>
                                    <option value="PED" ${data.category == 'PED' ? 'selected' : ''}>PED</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" name="update_course" class="btn-submit" style="margin-top:15px;">
                            <i class="fas fa-save"></i> Update Course
                        </button>
                    </form>`;
                    document.getElementById('editFormContainer').innerHTML = html;
                    document.getElementById('editModal').style.display = 'block';
                } else {
                    alert('Course not found!');
                }
            })
            .catch(err => alert('Error: ' + err.message));
    }
    </script>

</body>
</html>