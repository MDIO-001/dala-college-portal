<?php
// ============================================
// CONNECTION FILE - LOCAL SERVER (XAMPP)
// Yana samar da $conn (mysqli) da $pdo (PDO)
// ============================================

$host = 'localhost';
$user = 'root';
$password = '';
$database = 'dala-college';

// ============================================
// 1. MySQLi CONNECTION (don tsofaffin fayiloli)
// ============================================
$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("MySQLi Connection failed: " . mysqli_connect_error());
}
$conn->set_charset("utf8mb4");

// ============================================
// 2. PDO CONNECTION (don sabbin fayiloli)
// ============================================
try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    die("PDO Connection failed: " . $e->getMessage());
}

// ============================================
// DUBA IDAN TABLES SUN WANZU
// ============================================
$check_branches = mysqli_query($conn, "SHOW TABLES LIKE 'branches'");
$check_students = mysqli_query($conn, "SHOW TABLES LIKE 'students'");

if (!$check_branches || mysqli_num_rows($check_branches) == 0 || 
    !$check_students || mysqli_num_rows($check_students) == 0) {
    
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
    
    // Branches
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS branches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch_code VARCHAR(20) UNIQUE NOT NULL,
        branch_name VARCHAR(100) NOT NULL,
        location VARCHAR(200),
        phone VARCHAR(20),
        head_of_branch VARCHAR(100),
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // ============================================
    // STUDENTS TABLE (GYARA)
    // - phone ba UNIQUE ba (don hana duplicate error)
    // - level default = 'NCE I'
    // - an ƙara entry_year
    // ============================================
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reg_no VARCHAR(50) UNIQUE NOT NULL,
        student_id VARCHAR(50) UNIQUE,
        username VARCHAR(50) UNIQUE,
        password VARCHAR(255) NOT NULL,
        fullname VARCHAR(100) NOT NULL,
        email VARCHAR(100) NULL,
        phone VARCHAR(20) NULL,
        course VARCHAR(100),
        combination VARCHAR(100),
        programme VARCHAR(50),
        programme_type VARCHAR(20) DEFAULT 'NCE',
        level VARCHAR(20) DEFAULT 'NCE I',
        entry_year VARCHAR(20) DEFAULT NULL,
        graduation_year VARCHAR(20) DEFAULT NULL,
        current_level VARCHAR(20) DEFAULT NULL,
        admission_year VARCHAR(20) DEFAULT NULL,
        department VARCHAR(100),
        gender ENUM('Male', 'Female', 'Other'),
        dob DATE,
        address TEXT,
        guardian_name VARCHAR(200),
        guardian_phone VARCHAR(20),
        branch_code VARCHAR(20),
        status ENUM('active', 'inactive', 'graduated', 'pending') DEFAULT 'pending',
        photo VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Staff
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS staff (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        fullname VARCHAR(100) NOT NULL,
        email VARCHAR(100) NULL,
        staff_id VARCHAR(50) UNIQUE,
        phone VARCHAR(20),
        department VARCHAR(100),
        position VARCHAR(100),
        role VARCHAR(50) NOT NULL,
        branch_code VARCHAR(20),
        can_accept VARCHAR(10) DEFAULT 'no',
        qualification VARCHAR(200),
        gender ENUM('Male', 'Female', 'Other'),
        date_joined DATE,
        photo VARCHAR(255) NULL,
        status ENUM('active', 'inactive', 'on_leave') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Admins
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        fullname VARCHAR(100) NOT NULL,
        email VARCHAR(100) NULL,
        role ENUM('admin', 'super_admin') DEFAULT 'admin',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS admin (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) NULL,
        fullname VARCHAR(200),
        last_login TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Applications
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NULL,
        fullname VARCHAR(200) NOT NULL,
        email VARCHAR(100) NULL,
        phone VARCHAR(20) NULL,
        course_applied VARCHAR(100) NULL,
        programme VARCHAR(50) NULL,
        branch_code VARCHAR(20) NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        notes TEXT NULL,
        reviewed_by VARCHAR(100) NULL,
        reviewed_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Courses
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_code VARCHAR(50) NOT NULL,
        course_title VARCHAR(200) NOT NULL,
        department VARCHAR(100),
        programme VARCHAR(50),
        combination VARCHAR(100),
        level VARCHAR(20),
        credits INT DEFAULT 3,
        semester VARCHAR(20),
        category VARCHAR(10),
        lecturer_id INT,
        status VARCHAR(20) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Course Registrations
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS course_registrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT,
        course_code VARCHAR(50) NOT NULL,
        course_title VARCHAR(200),
        semester VARCHAR(20),
        level VARCHAR(20),
        academic_year VARCHAR(10),
        credits INT DEFAULT 3,
        branch_code VARCHAR(20),
        status ENUM('registered', 'completed', 'dropped') DEFAULT 'registered',
        grade VARCHAR(2),
        registration_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_registration (student_id, course_code, semester, academic_year)
    )");
    
    // Scratch Cards
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS scratch_cards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        card_number VARCHAR(50) UNIQUE NOT NULL,
        pin VARCHAR(20) NOT NULL,
        amount DECIMAL(10,2),
        branch_code VARCHAR(20),
        status ENUM('active', 'used', 'expired') DEFAULT 'active',
        used_by INT,
        used_date TIMESTAMP NULL,
        expiry_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Results
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        reg_no VARCHAR(50),
        student_name VARCHAR(200),
        course_code VARCHAR(50) NOT NULL,
        course_title VARCHAR(200),
        credit_units INT DEFAULT 0,
        score INT DEFAULT 0,
        grade VARCHAR(2),
        grade_point DECIMAL(3,2) DEFAULT 0,
        remark VARCHAR(50),
        level VARCHAR(20),
        semester VARCHAR(20),
        session VARCHAR(20),
        combination VARCHAR(100),
        entered_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // T.P Results
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tp_results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        academic_year VARCHAR(20),
        level VARCHAR(20),
        school_name VARCHAR(200),
        supervisor_name VARCHAR(200),
        teaching_score INT DEFAULT 0,
        lesson_note_score INT DEFAULT 0,
        punctuality_score INT DEFAULT 0,
        relationship_score INT DEFAULT 0,
        total_score INT DEFAULT 0,
        grade VARCHAR(2),
        remark VARCHAR(50),
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_tp (student_id, academic_year)
    )");
    
    // Attendance
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        course_code VARCHAR(50) NOT NULL,
        branch_code VARCHAR(20),
        attendance_date DATE NOT NULL,
        status ENUM('present', 'absent', 'late') DEFAULT 'present',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_attendance (student_id, course_code, attendance_date)
    )");
    
    // Grade Setup
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS grade_setup (
        id INT AUTO_INCREMENT PRIMARY KEY,
        grade VARCHAR(2) NOT NULL,
        min_score INT NOT NULL,
        max_score INT NOT NULL,
        grade_point DECIMAL(3,2) NOT NULL,
        remark VARCHAR(50),
        status ENUM('Active', 'Inactive') DEFAULT 'Active'
    )");
    
    // Settings
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Name Change Logs
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS name_change_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        old_name VARCHAR(200),
        new_name VARCHAR(200),
        changed_by INT,
        changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
    
    // ============================================
    // SAKA BAYANAN FARKO (BABU DALIBAI)
    // ============================================
    
    // Branches
    mysqli_query($conn, "INSERT IGNORE INTO branches (branch_code, branch_name, location, phone, head_of_branch) VALUES 
    ('SHINGE', 'Shinge', 'Shinge Quarter, Kano', '08012345678', 'Dr. Aliyu Musa'),
    ('SABUWA', 'Sabuwar Kofa', 'Sabuwar Kofa, Kano', '08087654321', 'Mal. Mustapha Danjuma'),
    ('TUDUN', 'Tudun Yola', 'Tudun Yola, Kano', '08011223344', 'Dr. Nura Munzali')");
    
    // Admin
    mysqli_query($conn, "INSERT IGNORE INTO admins (username, password, fullname, role) 
    VALUES ('admin', 'admin123', 'System Administrator', 'super_admin')");
    
    mysqli_query($conn, "INSERT IGNORE INTO admin (username, password, fullname) 
    VALUES ('admin', 'admin123', 'System Administrator')");
    
    // Staff
    mysqli_query($conn, "INSERT IGNORE INTO staff (username, password, fullname, role, staff_id, position, can_accept) VALUES 
    ('provost1', 'provost123', 'Dr. Aliyu Musa', 'Provost', 'STF/001', 'Provost', 'yes'),
    ('admission1', 'admission123', 'Mal. Mustapha Danjuma', 'Admission Officer', 'STF/002', 'Admission Officer', 'yes'),
    ('bursary1', 'bursary123', 'Alh. Bilal Aliyu Musa', 'Bursary', 'STF/003', 'Bursary Officer', 'no'),
    ('accountant1', 'account123', 'Mal. Mustapha Adam Danjuma', 'Accountant', 'STF/004', 'Accountant', 'no'),
    ('exam1', 'exam123', 'Dr. Nura Munzali Ali', 'Exam Officer', 'STF/005', 'Exam Officer', 'no')");
    
    // Grade Setup
    mysqli_query($conn, "INSERT IGNORE INTO grade_setup (grade, min_score, max_score, grade_point, remark, status) VALUES
    ('A', 70, 100, 4.00, 'Distinction', 'Active'),
    ('B', 60, 69, 3.00, 'Credit', 'Active'),
    ('C', 50, 59, 2.00, 'Merit', 'Active'),
    ('D', 45, 49, 1.00, 'Pass', 'Active'),
    ('E', 40, 44, 0.50, 'Lower Pass', 'Active'),
    ('F', 0, 39, 0.00, 'Fail', 'Active')");
    
    // Settings
    mysqli_query($conn, "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES 
    ('current_session', '2026/2027'),
    ('current_semester', 'First Semester'),
    ('school_name', 'Dala College of Education, Kano')");
}
?>