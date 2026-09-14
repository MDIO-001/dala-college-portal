<?php
session_start();
include 'connect.php';
include 'result_functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

$admin_name = $_SESSION['fullname'] ?? 'Admin';
$message = '';
$message_type = '';

// ============================================
// UPDATE SETTINGS
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_settings'])) {
    $settings_to_save = [
        'current_session' => trim($_POST['current_session'] ?? ''),
        'current_semester' => trim($_POST['current_semester'] ?? ''),
        'school_name' => trim($_POST['school_name'] ?? ''),
        'pass_mark' => intval($_POST['pass_mark'] ?? 40),
        'max_cgpa' => floatval($_POST['max_cgpa'] ?? 4.00),
        'grading_system' => trim($_POST['grading_system'] ?? 'NCE'),
        'result_footer' => trim($_POST['result_footer'] ?? ''),
        'registrar_name' => trim($_POST['registrar_name'] ?? ''),
        'dean_name' => trim($_POST['dean_name'] ?? ''),
        'provost_name' => trim($_POST['provost_name'] ?? '')
    ];
    
    $saved = 0;
    foreach ($settings_to_save as $key => $value) {
        $key_esc = mysqli_real_escape_string($conn, $key);
        $value_esc = mysqli_real_escape_string($conn, $value);
        
        $check = mysqli_query($conn, "SELECT id FROM settings WHERE setting_key = '$key_esc'");
        if (mysqli_num_rows($check) > 0) {
            $update = "UPDATE settings SET setting_value = '$value_esc' WHERE setting_key = '$key_esc'";
            if (mysqli_query($conn, $update)) $saved++;
        } else {
            $insert = "INSERT INTO settings (setting_key, setting_value) VALUES ('$key_esc', '$value_esc')";
            if (mysqli_query($conn, $insert)) $saved++;
        }
    }
    
    $message = "✅ Settings saved successfully! ($saved items)";
    $message_type = 'success';
}

// ============================================
// RESET SETTINGS
// ============================================
if (isset($_GET['reset'])) {
    $defaults = [
        'current_session' => '2024/2025',
        'current_semester' => 'First Semester',
        'school_name' => 'Dala College of Education, Kano',
        'pass_mark' => '40',
        'max_cgpa' => '4.00',
        'grading_system' => 'NCE',
        'result_footer' => 'This is a computer-generated result. No signature is required.',
        'registrar_name' => 'REGISTRAR',
        'dean_name' => 'DEAN OF SCHOOL',
        'provost_name' => 'PROVOST'
    ];
    
    foreach ($defaults as $key => $value) {
        $key_esc = mysqli_real_escape_string($conn, $key);
        $value_esc = mysqli_real_escape_string($conn, $value);
        
        $check = mysqli_query($conn, "SELECT id FROM settings WHERE setting_key = '$key_esc'");
        if (mysqli_num_rows($check) > 0) {
            mysqli_query($conn, "UPDATE settings SET setting_value = '$value_esc' WHERE setting_key = '$key_esc'");
        } else {
            mysqli_query($conn, "INSERT INTO settings (setting_key, setting_value) VALUES ('$key_esc', '$value_esc')");
        }
    }
    
    $message = "🔄 Settings reset to default!";
    $message_type = 'success';
}

// ============================================
// EXPORT SETTINGS (CSV)
// ============================================
if (isset($_GET['export_settings'])) {
    $settings_query = mysqli_query($conn, "SELECT * FROM settings ORDER BY setting_key");
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="settings_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Setting Key', 'Setting Value']);
    
    while ($s = mysqli_fetch_assoc($settings_query)) {
        fputcsv($output, [$s['setting_key'], $s['setting_value']]);
    }
    
    fclose($output);
    exit();
}

// ============================================
// IMPORT SETTINGS (CSV)
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import_settings'])) {
    if (isset($_FILES['settings_file']) && $_FILES['settings_file']['error'] == 0) {
        $file_tmp = $_FILES['settings_file']['tmp_name'];
        $file_ext = strtolower(pathinfo($_FILES['settings_file']['name'], PATHINFO_EXTENSION));
        
        if ($file_ext != 'csv') {
            $message = "❌ Please upload a CSV file only.";
            $message_type = 'error';
        } else {
            $file = fopen($file_tmp, 'r');
            fgetcsv($file); // Skip header
            
            $imported = 0;
            while (($row = fgetcsv($file)) !== FALSE) {
                if (empty($row[0])) continue;
                
                $key = mysqli_real_escape_string($conn, trim($row[0]));
                $value = mysqli_real_escape_string($conn, trim($row[1] ?? ''));
                
                $check = mysqli_query($conn, "SELECT id FROM settings WHERE setting_key = '$key'");
                if (mysqli_num_rows($check) > 0) {
                    mysqli_query($conn, "UPDATE settings SET setting_value = '$value' WHERE setting_key = '$key'");
                } else {
                    mysqli_query($conn, "INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$value')");
                }
                $imported++;
            }
            fclose($file);
            
            $message = "✅ Imported <strong>$imported</strong> settings!";
            $message_type = 'success';
        }
    }
}

// ============================================
// GET SETTINGS
// ============================================
$settings = [];
$sq = mysqli_query($conn, "SELECT * FROM settings");
while ($s = mysqli_fetch_assoc($sq)) {
    $settings[$s['setting_key']] = $s['setting_value'];
}

$get = function($key, $default = '') use ($settings) {
    return $settings[$key] ?? $default;
};

$session_options = getSessionOptions();
$level_options = getLevelOptions();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Tahoma,sans-serif; background:#f0f4f8; padding:20px; }
        .container { max-width:1200px; margin:0 auto; }
        
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
        .r-settings { background:#e65100; color:white; box-shadow:0 0 0 3px #ffd54f; }
        .r-database { background:#37474f; color:white; }
        .r-statement { background:#455a64; color:white; }
        
        .card { background:white; padding:25px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); margin-bottom:20px; }
        .card h2 { color:#0d2818; margin-bottom:15px; font-size:1.2rem; }
        .card h2 i { color:#e65100; margin-right:8px; }
        
        .message { padding:12px 20px; border-radius:8px; margin-bottom:15px; font-weight:600; }
        .message.success { background:#e8f5e9; color:#2e7d32; border-left:4px solid #2e7d32; }
        .message.error { background:#ffebee; color:#c62828; border-left:4px solid #c62828; }
        
        .form-grid { display:grid; grid-template-columns:repeat(2, 1fr); gap:20px; }
        .form-group { margin-bottom:15px; }
        .form-group label { display:block; font-weight:700; color:#0d2818; margin-bottom:6px; font-size:0.85rem; }
        .form-group label i { color:#e65100; margin-right:5px; }
        .form-group input, .form-group select, .form-group textarea { 
            width:100%; 
            padding:10px 12px; 
            border:2px solid #dce8dc; 
            border-radius:8px; 
            font-size:0.9rem;
            font-family:inherit;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { 
            border-color:#e65100; 
            outline:none; 
        }
        .form-group .hint { font-size:0.75rem; color:#6a8f6a; margin-top:3px; }
        
        .btn { padding:12px 30px; border:none; border-radius:8px; font-weight:700; font-size:0.95rem; cursor:pointer; text-decoration:none; display:inline-block; transition:all 0.3s ease; }
        .btn:hover { transform:translateY(-2px); }
        .btn-green { background:#2e7d32; color:white; }
        .btn-blue { background:#1976d2; color:white; }
        .btn-red { background:#c62828; color:white; }
        .btn-orange { background:#f57c00; color:white; }
        
        .btn-row { display:flex; gap:10px; flex-wrap:wrap; margin-top:15px; }
        
        .section-title {
            background:#f8faf8;
            padding:10px 15px;
            border-radius:8px;
            margin-bottom:15px;
            color:#0d2818;
            font-weight:800;
            font-size:0.95rem;
            border-left:4px solid #e65100;
        }
        
        .info-box {
            background:#e3f2fd;
            padding:15px;
            border-radius:8px;
            border-left:4px solid #1976d2;
            margin-bottom:15px;
            font-size:0.85rem;
        }
        .info-box i { color:#0d47a1; margin-right:8px; }
        
        .import-area { background:#f8faf8; padding:20px; border-radius:10px; border:2px dashed #e65100; text-align:center; }
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

        <h2 style="color:#0d2818; margin-bottom:15px;">⚙️ Settings</h2>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <strong>Info:</strong> Waɗannan settings suna shafar duk result system ɗinka (Result Slip, Transcript, Final Result, da sauransu).
        </div>

        <!-- SETTINGS FORM -->
        <form method="POST">
            <!-- GENERAL SETTINGS -->
            <div class="card">
                <div class="section-title"><i class="fas fa-cog"></i> General Settings</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> Current Session *</label>
                        <select name="current_session" required>
                            <?php foreach ($session_options as $sess): ?>
                                <option value="<?php echo $sess; ?>" <?php echo ($get('current_session') == $sess) ? 'selected' : ''; ?>><?php echo $sess; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="hint">Shekarar da ake ciki yanzu</div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt"></i> Current Semester *</label>
                        <select name="current_semester" required>
                            <option value="First Semester" <?php echo ($get('current_semester') == 'First Semester') ? 'selected' : ''; ?>>First Semester</option>
                            <option value="Second Semester" <?php echo ($get('current_semester') == 'Second Semester') ? 'selected' : ''; ?>>Second Semester</option>
                        </select>
                        <div class="hint">Semester ɗin da ake ciki</div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-school"></i> School Name</label>
                        <input type="text" name="school_name" value="<?php echo htmlspecialchars($get('school_name', 'Dala College of Education, Kano')); ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-star"></i> Grading System</label>
                        <select name="grading_system">
                            <option value="NCE" <?php echo ($get('grading_system') == 'NCE') ? 'selected' : ''; ?>>NCE (4.00 CGPA)</option>
                            <option value="DEGREE" <?php echo ($get('grading_system') == 'DEGREE') ? 'selected' : ''; ?>>Degree (4.00 CGPA)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- GRADING SETTINGS -->
            <div class="card">
                <div class="section-title"><i class="fas fa-percent"></i> Grading Settings</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-check-circle"></i> Pass Mark *</label>
                        <input type="number" name="pass_mark" value="<?php echo htmlspecialchars($get('pass_mark', '40')); ?>" min="0" max="100" required>
                        <div class="hint">Makin da ake buƙatar don ci (misali: 40)</div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-trophy"></i> Max CGPA *</label>
                        <input type="number" name="max_cgpa" value="<?php echo htmlspecialchars($get('max_cgpa', '4.00')); ?>" step="0.01" min="0" max="5" required>
                        <div class="hint">Mafi girman CGPA (misali: 4.00)</div>
                    </div>
                </div>
            </div>

            <!-- SIGNATURE SETTINGS -->
            <div class="card">
                <div class="section-title"><i class="fas fa-signature"></i> Signature Names</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-user-tie"></i> Registrar Name</label>
                        <input type="text" name="registrar_name" value="<?php echo htmlspecialchars($get('registrar_name', 'REGISTRAR')); ?>">
                        <div class="hint">Sunan da zai bayyana a Result Slip</div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user-graduate"></i> Dean Name</label>
                        <input type="text" name="dean_name" value="<?php echo htmlspecialchars($get('dean_name', 'DEAN OF SCHOOL')); ?>">
                        <div class="hint">Sunan Dean ɗin makaranta</div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user-shield"></i> Provost Name</label>
                        <input type="text" name="provost_name" value="<?php echo htmlspecialchars($get('provost_name', 'PROVOST')); ?>">
                        <div class="hint">Sunan Provost ɗin makaranta</div>
                    </div>
                </div>
            </div>

            <!-- FOOTER SETTINGS -->
            <div class="card">
                <div class="section-title"><i class="fas fa-shoe-prints"></i> Result Footer Text</div>
                <div class="form-group">
                    <label>Footer Text</label>
                    <textarea name="result_footer" rows="2"><?php echo htmlspecialchars($get('result_footer', 'This is a computer-generated result. No signature is required.')); ?></textarea>
                    <div class="hint">Rubutun da zai bayyana a ƙasan kowane result</div>
                </div>
            </div>

            <!-- BUTTONS -->
            <div class="btn-row">
                <button type="submit" name="save_settings" class="btn btn-green">
                    <i class="fas fa-save"></i> Save All Settings
                </button>
                <a href="?reset=1" class="btn btn-orange" onclick="return confirm('Reset duk settings zuwa default?')">
                    <i class="fas fa-redo"></i> Reset to Default
                </a>
                <a href="?export_settings=1" class="btn btn-blue">
                    <i class="fas fa-download"></i> Export Settings
                </a>
            </div>
        </form>

        <!-- IMPORT SETTINGS -->
        <div class="card">
            <h2><i class="fas fa-upload"></i> Import Settings</h2>
            <div class="import-area">
                <form method="POST" enctype="multipart/form-data">
                    <p style="font-weight:700; margin-bottom:10px;">Upload Settings CSV file</p>
                    <input type="file" name="settings_file" accept=".csv" required>
                    <button type="submit" name="import_settings" class="btn btn-orange" style="margin-top:10px;">
                        <i class="fas fa-upload"></i> Import Settings
                    </button>
                </form>
            </div>
        </div>

        <!-- CURRENT SETTINGS VIEW -->
        <div class="card">
            <h2><i class="fas fa-list"></i> Current Settings</h2>
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="background:#0d2818; color:white; padding:10px; text-align:left; font-size:0.8rem;">KEY</th>
                        <th style="background:#0d2818; color:white; padding:10px; text-align:left; font-size:0.8rem;">VALUE</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($settings as $key => $value): ?>
                    <tr>
                        <td style="padding:8px 12px; border-bottom:1px solid #eee; font-size:0.85rem;"><strong><?php echo htmlspecialchars($key); ?></strong></td>
                        <td style="padding:8px 12px; border-bottom:1px solid #eee; font-size:0.85rem;"><?php echo htmlspecialchars($value); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>