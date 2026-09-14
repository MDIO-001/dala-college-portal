<?php
session_start();
include 'connect.php';

// Check if staff is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header('Location: login.php');
    exit();
}

$staff_id = $_SESSION['user_id'];
$staff_name = $_SESSION['fullname'] ?? 'Staff';
$staff_role = $_SESSION['position'] ?? $_SESSION['role'] ?? 'Staff';

// Get staff data
$staff_query = "SELECT * FROM staff WHERE id = '$staff_id'";
$staff_result = mysqli_query($conn, $staff_query);
$staff = mysqli_fetch_assoc($staff_result);

$can_accept = ($staff['can_accept'] == 'yes' || $staff['role'] == 'Provost' || $staff['role'] == 'Admission Officer');

// ============================================
// GET DEPARTMENT CODE
// ============================================
function getDepartmentCode($course) {
    $dept_codes = [
        'ARB/ISS' => 'ARI', 'ENG/ISS' => 'ENG', 'PED' => 'PED',
        'HAU/ENG' => 'HAU', 'CSC/ISC' => 'CSC', 'ENG/SOS' => 'SOC',
        'CSC/BIO' => 'BIO', 'CSC/PHY' => 'PHY', 'ENG/ECO' => 'ECO',
        'BA_ARABIC' => 'ARI', 'BA_ISLAMIC' => 'ISC',
        'BED_ENGLISH' => 'ENG', 'BED_HAUSA' => 'HAU',
        'BED_SOCIAL' => 'SOC', 'BSC_ECONOMICS' => 'ECO',
        'BSC_CSC' => 'CSC', 'BSC_BIOLOGY' => 'BIO',
        'BSC_PHYSICS' => 'PHY', 'BSC_ISC' => 'ISC',
        'TAILORING' => 'TAI', 'AI_TECH' => 'AIT',
        'SALOON' => 'SAL', 'HENNA' => 'HEN',
        'FISH_FARMING' => 'FIS', 'POULTRY' => 'POU',
        'SOAP_MAKING' => 'SOA', 'CATERING' => 'CAT',
        'BEAD_MAKING' => 'BEA', 'GRAPHIC_DESIGN' => 'GRA'
    ];
    return isset($dept_codes[$course]) ? $dept_codes[$course] : 'GEN';
}

// ============================================
// GENERATE ADMISSION NUMBER
// ============================================
function generateAdmissionNumber($programme, $branch_code, $course) {
    global $conn;
    $year = date('y');
    
    $branch_letter = '';
    if ($branch_code == 'SHINGE') $branch_letter = 'A';
    elseif ($branch_code == 'SABUWA') $branch_letter = 'B';
    elseif ($branch_code == 'TUDUN') $branch_letter = 'C';
    else $branch_letter = 'X';
    
    $dept_code = getDepartmentCode($course);
    
    $count_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM students");
    $count = 1;
    if ($count_result) {
        $row = mysqli_fetch_assoc($count_result);
        $count = $row['total'] + 1;
    }
    $student_number = str_pad($count, 3, '0', STR_PAD_LEFT);
    
    $prefix = 'DLCOE';
    if ($programme == 'NCE') $prefix = 'DLCOE/NCE';
    elseif ($programme == 'DEGREE') $prefix = 'DLCOE/DEG';
    else $prefix = 'DLCOE/ENT';
    
    return $prefix . "/{$year}{$branch_letter}{$student_number}/{$dept_code}{$student_number}";
}

// ============================================
// HANDLE ACCEPT/REJECT
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $can_accept) {
    $app_id = mysqli_real_escape_string($conn, $_POST['application_id']);
    $action = mysqli_real_escape_string($conn, $_POST['action']);
    $comment = mysqli_real_escape_string($conn, trim($_POST['comment']));
    
    // Get application details
    $get_app = "SELECT * FROM applications WHERE id = '$app_id'";
    $app_result = mysqli_query($conn, $get_app);
    $app = mysqli_fetch_assoc($app_result);
    
    if ($app) {
        if ($action == 'approved') {
            // Generate admission number
            $admission_no = generateAdmissionNumber(
                $app['programme'],
                $app['branch_code'] ?? 'SHINGE',
                $app['course_applied']
            );
            
            // Generate username: firstname@123
            $name_parts = explode(' ', trim($app['fullname']));
            $first_name = strtolower($name_parts[0]);
            $username = $first_name . '@123';
            
            // Check if username exists
            $counter = 1;
            $check_username = mysqli_query($conn, "SELECT id FROM students WHERE username = '$username'");
            while ($check_username && mysqli_num_rows($check_username) > 0) {
                $username = $first_name . $counter . '@123';
                $counter++;
                $check_username = mysqli_query($conn, "SELECT id FROM students WHERE username = '$username'");
            }
            
            // Insert into students table
            $insert_student = "INSERT INTO students (
                reg_no, student_id, username, password, fullname, email, phone,
                programme, course, level, branch_code, status, created_at
            ) VALUES (
                '$admission_no', '$admission_no', '$username', '" . password_hash('student123', PASSWORD_DEFAULT) . "',
                '{$app['fullname']}', '{$app['email']}', '{$app['phone']}',
                '{$app['programme']}', '{$app['course_applied']}', 'NCE I',
                '{$app['branch_code']}', 'active', NOW()
            )";
            
            if (mysqli_query($conn, $insert_student)) {
                $new_student_id = mysqli_insert_id($conn);
                
                // Update application
                $update_app = "UPDATE applications SET 
                    status = 'approved',
                    student_id = '$new_student_id',
                    notes = '$comment',
                    reviewed_by = '$staff_name ($staff_role)',
                    reviewed_at = NOW()
                    WHERE id = '$app_id'";
                mysqli_query($conn, $update_app);
                
                $success = "✅ Applicant approved successfully!<br>";
                $success .= "🎓 Admission Number: <strong>$admission_no</strong><br>";
                $success .= "👤 Username: <strong>$username</strong><br>";
                $success .= "🔑 Password: <strong>student123</strong>";
            } else {
                $error = "❌ Error creating student: " . mysqli_error($conn);
            }
        } else {
            // Reject
            $update_app = "UPDATE applications SET 
                status = 'rejected',
                notes = '$comment',
                reviewed_by = '$staff_name ($staff_role)',
                reviewed_at = NOW()
                WHERE id = '$app_id'";
            mysqli_query($conn, $update_app);
            $success = "❌ Applicant rejected!";
        }
    }
}

// ============================================
// GET ALL APPLICATIONS - SIMPLE QUERY
// ============================================
$all_applications = mysqli_query($conn, "SELECT * FROM applications ORDER BY id DESC");

$total_applications = 0;
$pending_count = 0;
$approved_count = 0;
$rejected_count = 0;

if ($all_applications) {
    $total_applications = mysqli_num_rows($all_applications);
}

$pending_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM applications WHERE status = 'pending'");
if ($pending_query) {
    $row = mysqli_fetch_assoc($pending_query);
    $pending_count = $row['count'];
}

$approved_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM applications WHERE status = 'approved'");
if ($approved_query) {
    $row = mysqli_fetch_assoc($approved_query);
    $approved_count = $row['count'];
}

$rejected_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM applications WHERE status = 'rejected'");
if ($rejected_query) {
    $row = mysqli_fetch_assoc($rejected_query);
    $rejected_count = $row['count'];
}

// Get current tab
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications - Staff</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            padding: 20px;
        }
        .topbar {
            background: #0d2818;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .topbar .logo-title { color: #ffd54f; font-size: 1.3rem; font-weight: 800; }
        .topbar .logo-title span { color: #a5d6a7; }
        .topbar .logo-sub { display: block; font-size: 0.6rem; color: #c8e6c9; }
        .topbar nav a { 
            color: #c8e6c9; 
            text-decoration: none; 
            padding: 8px 18px; 
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        .topbar nav a:hover { background: #2e7d32; }
        .topbar nav a.active { background: #ffd54f; color: #0d2818 !important; font-weight: 700; }
        .topbar nav a.logout { background: #c62828; color: white !important; }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 30px rgba(0,0,0,0.1);
        }
        h1 { color: #0d2818; margin-bottom: 5px; }
        .sub { color: #6a8f6a; margin-bottom: 20px; }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #2e7d32;
        }
        .alert-error {
            background: #ffebee;
            color: #c62828;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #c62828;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: #f8faf8;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        .stat-card .number { font-size: 1.8rem; font-weight: 800; }
        .stat-card .label { font-size: 0.8rem; color: #6a8f6a; }
        .stat-card .icon { font-size: 1.5rem; display: block; margin-bottom: 3px; }
        .stat-card.total .number { color: #0d2818; }
        .stat-card.pending .number { color: #e65100; }
        .stat-card.approved .number { color: #2e7d32; }
        .stat-card.rejected .number { color: #c62828; }
        
        .tab-header {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e8f0e8;
            padding-bottom: 10px;
            flex-wrap: wrap;
        }
        .tab-header .tab-btn {
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f0f4f8;
            color: #4a6a4a;
            text-decoration: none;
        }
        .tab-header .tab-btn:hover {
            background: #dce8dc;
        }
        .tab-header .tab-btn.active {
            background: #2e7d32;
            color: white;
        }
        .tab-header .tab-btn .badge {
            background: rgba(255,255,255,0.3);
            padding: 2px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            margin-left: 5px;
        }
        
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        table th {
            background: #0d2818;
            color: white;
            padding: 12px;
            text-align: left;
            font-size: 0.85rem;
        }
        table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            font-size: 0.85rem;
        }
        table tr:hover td { background: #f8faf8; }
        
        .badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .badge-pending { background: #fff3e0; color: #e65100; }
        .badge-approved { background: #e8f5e9; color: #2e7d32; }
        .badge-rejected { background: #ffebee; color: #c62828; }
        
        .admission-no {
            font-weight: 700;
            color: #0d2818;
            font-size: 0.75rem;
            background: #e8f5e9;
            padding: 2px 10px;
            border-radius: 12px;
        }
        
        .btn {
            padding: 6px 15px;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-success { background: #2e7d32; color: white; }
        .btn-success:hover { background: #1b5e20; }
        .btn-danger { background: #c62828; color: white; }
        .btn-danger:hover { background: #b71c1c; }
        .btn-back { background: #6a8f6a; color: white; }
        .btn-back:hover { background: #4a6a4a; }
        
        .comment-box {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .comment-box input[type="text"] {
            padding: 6px 10px;
            border: 1px solid #dce8dc;
            border-radius: 6px;
            font-size: 0.8rem;
            min-width: 120px;
        }
        .comment-box input[type="text"]:focus {
            border-color: #2e7d32;
            outline: none;
        }
        
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        
        @media (max-width: 768px) {
            .container { padding: 15px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .tab-header { flex-direction: column; }
            .tab-header .tab-btn { text-align: center; }
            .comment-box { flex-direction: column; align-items: stretch; }
            .comment-box input[type="text"] { min-width: auto; }
        }
    </style>
</head>
<body>

    <div class="topbar">
        <div>
            <span class="logo-title">DALA <span>COLLEGE</span></span>
            <span class="logo-sub">of Education, Kano</span>
        </div>
        <nav>
            <a href="staff_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="view_applications.php" class="active"><i class="fas fa-file-alt"></i> Applications</a>
            <a href="view_students.php"><i class="fas fa-users"></i> Students</a>
            <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="container">
        <h1><i class="fas fa-file-alt" style="color:#2e7d32;"></i> Applications</h1>
        <p class="sub">View and manage applicants &amp; admitted students</p>

        <?php if (isset($success)): ?>
            <div class="alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card total" onclick="window.location.href='?tab=all'">
                <span class="icon">📋</span>
                <div class="number"><?php echo $total_applications; ?></div>
                <div class="label">Total Applications</div>
            </div>
            <div class="stat-card pending" onclick="window.location.href='?tab=pending'">
                <span class="icon">⏳</span>
                <div class="number"><?php echo $pending_count; ?></div>
                <div class="label">Pending</div>
            </div>
            <div class="stat-card approved" onclick="window.location.href='?tab=approved'">
                <span class="icon">✅</span>
                <div class="number"><?php echo $approved_count; ?></div>
                <div class="label">Approved</div>
            </div>
            <div class="stat-card rejected" onclick="window.location.href='?tab=rejected'">
                <span class="icon">❌</span>
                <div class="number"><?php echo $rejected_count; ?></div>
                <div class="label">Rejected</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tab-header">
            <a href="?tab=all" class="tab-btn <?php echo ($current_tab == 'all') ? 'active' : ''; ?>">
                📋 All <span class="badge"><?php echo $total_applications; ?></span>
            </a>
            <a href="?tab=pending" class="tab-btn <?php echo ($current_tab == 'pending') ? 'active' : ''; ?>">
                ⏳ Pending <span class="badge"><?php echo $pending_count; ?></span>
            </a>
            <a href="?tab=approved" class="tab-btn <?php echo ($current_tab == 'approved') ? 'active' : ''; ?>">
                ✅ Approved <span class="badge"><?php echo $approved_count; ?></span>
            </a>
            <a href="?tab=rejected" class="tab-btn <?php echo ($current_tab == 'rejected') ? 'active' : ''; ?>">
                ❌ Rejected <span class="badge"><?php echo $rejected_count; ?></span>
            </a>
        </div>

        <!-- ============================================ -->
        <!-- TAB: ALL APPLICATIONS -->
        <!-- ============================================ -->
        <div class="tab-content <?php echo ($current_tab == 'all') ? 'active' : ''; ?>">
            <?php
            $all_apps = mysqli_query($conn, "SELECT * FROM applications ORDER BY id DESC");
            ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Programme</th>
                            <th>Course</th>
                            <th>Status</th>
                            <th>Comment</th>
                            <?php if ($can_accept): ?>
                                <th>Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($all_apps && mysqli_num_rows($all_apps) > 0): ?>
                            <?php 
                            $i = 1;
                            while ($row = mysqli_fetch_assoc($all_apps)): 
                                $status_class = $row['status'] == 'approved' ? 'approved' : ($row['status'] == 'pending' ? 'pending' : 'rejected');
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['fullname']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                <td><?php echo htmlspecialchars($row['programme']); ?></td>
                                <td><?php echo htmlspecialchars($row['course_applied']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $status_class; ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td style="max-width:150px; font-size:0.8rem; color:#4a6a4a;">
                                    <?php echo htmlspecialchars($row['notes'] ?? '-'); ?>
                                </td>
                                <?php if ($can_accept): ?>
                                    <td>
                                        <?php if ($row['status'] == 'pending'): ?>
                                        <form method="POST" action="" class="comment-box">
                                            <input type="hidden" name="application_id" value="<?php echo $row['id']; ?>">
                                            <input type="text" name="comment" placeholder="Add comment..." required>
                                            <button type="submit" name="action" value="approved" class="btn btn-success">
                                                <i class="fas fa-check"></i> Accept
                                            </button>
                                            <button type="submit" name="action" value="rejected" class="btn btn-danger">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>
                                        <?php else: ?>
                                            <span style="color:#6a8f6a; font-size:0.8rem;">
                                                <i class="fas fa-check-circle" style="color:#2e7d32;"></i> Done
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="<?php echo $can_accept ? 8 : 7; ?>" style="text-align:center; padding:40px; color:#6a8f6a;">
                                <i class="fas fa-inbox" style="font-size:2rem; display:block; margin-bottom:10px; color:#dce8dc;"></i>
                                No applications found.
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- TAB: PENDING APPLICATIONS -->
        <!-- ============================================ -->
        <div class="tab-content <?php echo ($current_tab == 'pending') ? 'active' : ''; ?>">
            <?php
            $pending_apps = mysqli_query($conn, "SELECT * FROM applications WHERE status = 'pending' ORDER BY id DESC");
            ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Programme</th>
                            <th>Course</th>
                            <th>Status</th>
                            <th>Comment</th>
                            <?php if ($can_accept): ?>
                                <th>Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($pending_apps && mysqli_num_rows($pending_apps) > 0): ?>
                            <?php 
                            $i = 1;
                            while ($row = mysqli_fetch_assoc($pending_apps)): 
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['fullname']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                <td><?php echo htmlspecialchars($row['programme']); ?></td>
                                <td><?php echo htmlspecialchars($row['course_applied']); ?></td>
                                <td><span class="badge badge-pending">Pending</span></td>
                                <td style="max-width:150px; font-size:0.8rem; color:#4a6a4a;">
                                    <?php echo htmlspecialchars($row['notes'] ?? '-'); ?>
                                </td>
                                <?php if ($can_accept): ?>
                                    <td>
                                        <form method="POST" action="" class="comment-box">
                                            <input type="hidden" name="application_id" value="<?php echo $row['id']; ?>">
                                            <input type="text" name="comment" placeholder="Add comment..." required>
                                            <button type="submit" name="action" value="approved" class="btn btn-success">
                                                <i class="fas fa-check"></i> Accept
                                            </button>
                                            <button type="submit" name="action" value="rejected" class="btn btn-danger">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="<?php echo $can_accept ? 8 : 7; ?>" style="text-align:center; padding:40px; color:#6a8f6a;">
                                <i class="fas fa-user-plus" style="font-size:2rem; display:block; margin-bottom:10px; color:#dce8dc;"></i>
                                No pending applicants.
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- TAB: APPROVED APPLICATIONS -->
        <!-- ============================================ -->
        <div class="tab-content <?php echo ($current_tab == 'approved') ? 'active' : ''; ?>">
            <?php
            $approved_apps = mysqli_query($conn, "SELECT * FROM applications WHERE status = 'approved' ORDER BY id DESC");
            ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Programme</th>
                            <th>Course</th>
                            <th>Status</th>
                            <th>Comment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($approved_apps && mysqli_num_rows($approved_apps) > 0): ?>
                            <?php 
                            $i = 1;
                            while ($row = mysqli_fetch_assoc($approved_apps)): 
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['fullname']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                <td><?php echo htmlspecialchars($row['programme']); ?></td>
                                <td><?php echo htmlspecialchars($row['course_applied']); ?></td>
                                <td><span class="badge badge-approved">Approved</span></td>
                                <td style="max-width:150px; font-size:0.8rem; color:#4a6a4a;">
                                    <?php echo htmlspecialchars($row['notes'] ?? '-'); ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align:center; padding:40px; color:#6a8f6a;">
                                <i class="fas fa-check-circle" style="font-size:2rem; display:block; margin-bottom:10px; color:#dce8dc;"></i>
                                No approved applications.
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- TAB: REJECTED APPLICATIONS -->
        <!-- ============================================ -->
        <div class="tab-content <?php echo ($current_tab == 'rejected') ? 'active' : ''; ?>">
            <?php
            $rejected_apps = mysqli_query($conn, "SELECT * FROM applications WHERE status = 'rejected' ORDER BY id DESC");
            ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Programme</th>
                            <th>Course</th>
                            <th>Status</th>
                            <th>Comment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rejected_apps && mysqli_num_rows($rejected_apps) > 0): ?>
                            <?php 
                            $i = 1;
                            while ($row = mysqli_fetch_assoc($rejected_apps)): 
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['fullname']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                <td><?php echo htmlspecialchars($row['programme']); ?></td>
                                <td><?php echo htmlspecialchars($row['course_applied']); ?></td>
                                <td><span class="badge badge-rejected">Rejected</span></td>
                                <td style="max-width:150px; font-size:0.8rem; color:#4a6a4a;">
                                    <?php echo htmlspecialchars($row['notes'] ?? '-'); ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align:center; padding:40px; color:#6a8f6a;">
                                <i class="fas fa-times-circle" style="font-size:2rem; display:block; margin-bottom:10px; color:#dce8dc;"></i>
                                No rejected applications.
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="margin-top: 20px;">
            <a href="staff_dashboard.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>

</body>
</html>