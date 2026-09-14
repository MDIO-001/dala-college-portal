<?php
// ============================================
// CONNECTION FILE - HOSTING (NAIRAHHOST)
// ============================================

$host = 'localhost';                 // Yawanci 'localhost' ne a hosting
$user = 'dalacoee_dbuser';           // MySQL Username daga DirectAdmin
$password = 'your_db_password';      // MySQL Password da ka saita
$database = 'dalacoee_dala';         // MySQL Database Name

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// ============================================
// DUBA IDAN TABLES SUN WANZU
// ============================================
$check_branches = mysqli_query($conn, "SHOW TABLES LIKE 'branches'");
$check_students = mysqli_query($conn, "SHOW TABLES LIKE 'students'");

// Idan babu tables, sai mu kirkiri su
if (!$check_branches || mysqli_num_rows($check_branches) == 0 || 
    !$check_students || mysqli_num_rows($check_students) == 0) {
    
    // ============================================
    // KIRKIRI TABLES
    // ============================================
    
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
    
    // Students
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reg_no VARCHAR(50) UNIQUE NOT NULL,
        student_id VARCHAR(50) UNIQUE,
        username VARCHAR(50) UNIQUE,
        password VARCHAR(255) NOT NULL,
        fullname VARCHAR(100) NOT NULL,
        email VARCHAR(100) NULL,
        phone VARCHAR(20) UNIQUE,
        course VARCHAR(100),
        programme VARCHAR(50),
        level VARCHAR(20) DEFAULT 'NCE III',
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
        course_code VARCHAR(50) UNIQUE NOT NULL,
        course_title VARCHAR(200) NOT NULL,
        department VARCHAR(100),
        programme VARCHAR(50),
        level VARCHAR(20),
        credits INT DEFAULT 3,
        semester VARCHAR(20),
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
        course_code VARCHAR(50) NOT NULL,
        semester VARCHAR(20),
        academic_year VARCHAR(10),
        branch_code VARCHAR(20),
        ca_score DECIMAL(5,2),
        exam_score DECIMAL(5,2),
        total_score DECIMAL(5,2),
        grade VARCHAR(2),
        grade_point DECIMAL(3,2),
        status ENUM('pass', 'fail', 'incomplete') DEFAULT 'incomplete',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
    
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
    
    // ============================================
    // SAKA BAYANAN
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
    
    // Students (48)
    mysqli_query($conn, "INSERT IGNORE INTO students (reg_no, student_id, username, password, fullname, email, phone, course, programme, level, branch_code, status, created_at) VALUES 
    ('DLCOE/NCE/24A001/CSB001', 'DLCOE/NCE/24A001/CSB001', 'mujitapha@123', 'student123', 'Mujitapha Danjuma', 'mujitapha@email.com', '08012345681', 'CSC/BIO', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A002/CSB002', 'DLCOE/NCE/24A002/CSB002', 'unknown@123', 'student123', 'Unknown Student', 'unknown@email.com', '08012345682', 'CSC/BIO', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A003/CSB003', 'DLCOE/NCE/24A003/CSB003', 'muazu@123', 'student123', 'Muazu Abdullahi Ibrahim', 'muazu.abdullahi@email.com', '08012345683', 'CSC/BIO', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A004/CSB004', 'DLCOE/NCE/24A004/CSB004', 'muhammad.idris@123', 'student123', 'Muhammad Idris Adam', 'muhammad.idris@email.com', '08012345684', 'CSC/BIO', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A026/CSB005', 'DLCOE/NCE/24A026/CSB005', 'umar.lawan@123', 'student123', 'Umar Lawan Nababa', 'umar.lawan@email.com', '08012345685', 'CSC/BIO', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A032/CSB006', 'DLCOE/NCE/24A032/CSB006', 'fatima.sani@123', 'student123', 'Fatima Sani', 'fatima.sani@email.com', '08012345686', 'CSC/BIO', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A034/CSB007', 'DLCOE/NCE/24A034/CSB007', 'asiya@123', 'student123', 'Asiya Ismail Ibrahim', 'asiya.ismail@email.com', '08012345687', 'CSC/BIO', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A037/CSB008', 'DLCOE/NCE/24A037/CSB008', 'umar.hassan@123', 'student123', 'Umar Hassan Maaruf', 'umar.hassan@email.com', '08012345688', 'CSC/BIO', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A009/ENH001', 'DLCOE/NCE/24A009/ENH001', 'salisu.adam@123', 'student123', 'Salisu Adam Salisu', 'salisu.adam@email.com', '08012345689', 'HAU/ENG', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A010/ENH002', 'DLCOE/NCE/24A010/ENH002', 'zainab.sani@123', 'student123', 'Zainab Sani Ibrahim', 'zainab.sani@email.com', '08012345690', 'HAU/ENG', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A020/ENH003', 'DLCOE/NCE/24A020/ENH003', 'zainab.bala@123', 'student123', 'Zainab Bala Usman', 'zainab.bala@email.com', '08012345691', 'HAU/ENG', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A022/ENH004', 'DLCOE/NCE/24A022/ENH004', 'ramatu.ado@123', 'student123', 'Ramatu Ado', 'ramatu.ado@email.com', '08012345692', 'HAU/ENG', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A024/ENH005', 'DLCOE/NCE/24A024/ENH005', 'maryam.tasiu@123', 'student123', 'Maryam Tasin', 'maryam.tasin@email.com', '08012345693', 'HAU/ENG', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A025/ENH006', 'DLCOE/NCE/24A025/ENH006', 'faruq@123', 'student123', 'Faruq Hamza Shuaibu', 'faruq.hamza@email.com', '08012345694', 'HAU/ENG', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A031/ENH007', 'DLCOE/NCE/24A031/ENH007', 'hafsat.lawan@123', 'student123', 'Hafsat Lawan Aliyu', 'hafsat.lawan@email.com', '08012345695', 'HAU/ENG', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A005/ENI001', 'DLCOE/NCE/24A005/ENI001', 'khadija@123', 'student123', 'Khadija Abdullahi', 'khadija.abdullahi@email.com', '08012345696', 'ENG/ISS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A006/ENI002', 'DLCOE/NCE/24A006/ENI002', 'auwalu.isa@123', 'student123', 'Auwalu Isa Musa', 'auwalu.isa@email.com', '08012345697', 'ENG/ISS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A007/ENI003', 'DLCOE/NCE/24A007/ENI003', 'mariya.kabiru@123', 'student123', 'Mariya Kabiru', 'mariya.kabiru@email.com', '08012345698', 'ENG/ISS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A008/ENI004', 'DLCOE/NCE/24A008/ENI004', 'surajo.sani@123', 'student123', 'Surajo Sani', 'surajo.sani@email.com', '08012345699', 'ENG/ISS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A018/ENI005', 'DLCOE/NCE/24A018/ENI005', 'kausar.musa@123', 'student123', 'Kausar Musa Sulaiman', 'kausar.musa@email.com', '08012345700', 'ENG/ISS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A028/ENI006', 'DLCOE/NCE/24A028/ENI006', 'zainab.dauda@123', 'student123', 'Zainab Dauda Abubakar', 'zainab.dauda@email.com', '08012345701', 'ENG/ISS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A029/ENI007', 'DLCOE/NCE/24A029/ENI007', 'hassana.abubakar@123', 'student123', 'Hassana Abubakar', 'hassana.abubakar@email.com', '08012345702', 'ENG/ISS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A035/ENI008', 'DLCOE/NCE/24A035/ENI008', 'ismail.hamisu@123', 'student123', 'Ismail Hamisu Muhammad', 'ismail.hamisu@email.com', '08012345703', 'ENG/ISS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A038/ENI009', 'DLCOE/NCE/24A038/ENI009', 'fatima.shuaibu@123', 'student123', 'Fatima Shuaibu Sani', 'fatima.shuaibu@email.com', '08012345704', 'ENG/ISS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A044/ENI010', 'DLCOE/NCE/24A044/ENI010', 'zakiyya@123', 'student123', 'Zakiyya Abdullahi Muhammad', 'zakiyya.abdullahi@email.com', '08012345705', 'ENG/ISS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A045/ENI011', 'DLCOE/NCE/24A045/ENI011', 'fatima.abdullahi@123', 'student123', 'Fatima Abdullahi Muhammad', 'fatima.abdullahi@email.com', '08012345706', 'ENG/ISS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A011/ENS001', 'DLCOE/NCE/24A011/ENS001', 'abdullahi.hassan@123', 'student123', 'Abdullahi Hassan Idris', 'abdullahi.hassan@email.com', '08012345707', 'ENG/SOS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A012/ENS002', 'DLCOE/NCE/24A012/ENS002', 'ansariyya@123', 'student123', 'Ansariyya Alkasim Muhammad', 'ansariyya.alkasim@email.com', '08012345708', 'ENG/SOS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A013/ENS003', 'DLCOE/NCE/24A013/ENS003', 'faiza@123', 'student123', 'Faiza Muhammad Jibril', 'faiza.muhammad@email.com', '08012345709', 'ENG/SOS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A014/ENS004', 'DLCOE/NCE/24A014/ENS004', 'fatima.umar@123', 'student123', 'Fatima Umar Tijjani', 'fatima.umar@email.com', '08012345710', 'ENG/SOS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A015/ENS005', 'DLCOE/NCE/24A015/ENS005', 'muhammad.munkaila@123', 'student123', 'Muhammad Munkaila Yakub', 'muhammad.munkaila@email.com', '08012345711', 'ENG/SOS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A016/ENS006', 'DLCOE/NCE/24A016/ENS006', 'salman@123', 'student123', 'Salman Jamil Abba', 'salman.jamil@email.com', '08012345712', 'ENG/SOS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A017/ENS007', 'DLCOE/NCE/24A017/ENS007', 'usaini@123', 'student123', 'Usaini Mikailu', 'usaini.mikailu@email.com', '08012345713', 'ENG/SOS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A019/ENS008', 'DLCOE/NCE/24A019/ENS008', 'hafsat.muhammad@123', 'student123', 'Hafsat Muhammad Abdullahi', 'hafsat.muhammad@email.com', '08012345714', 'ENG/SOS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A021/ENS009', 'DLCOE/NCE/24A021/ENS009', 'saadatu@123', 'student123', 'Saadatu Salman Sulaiman', 'saadatu.salman@email.com', '08012345715', 'ENG/SOS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A023/ENS010', 'DLCOE/NCE/24A023/ENS010', 'garzali@123', 'student123', 'Garzali Badamasi', 'garzali.badamasi@email.com', '08012345716', 'ENG/SOS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A027/ENS011', 'DLCOE/NCE/24A027/ENS011', 'ummusalma@123', 'student123', 'Ummusalma Adam Sani', 'ummusalma.adam@email.com', '08012345717', 'ENG/SOS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A030/ENS012', 'DLCOE/NCE/24A030/ENS012', 'amina.adam@123', 'student123', 'Amina Adam Sani', 'amina.adam@email.com', '08012345718', 'ENG/SOS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A033/ENS013', 'DLCOE/NCE/24A033/ENS013', 'rumasau@123', 'student123', 'Rumasau Adam Sani', 'rumasau.adam@email.com', '08012345719', 'ENG/SOS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A036/ENS014', 'DLCOE/NCE/24A036/ENS014', 'fatima.haruna@123', 'student123', 'Fatima Haruna Muhammad', 'fatima.haruna@email.com', '08012345720', 'ENG/SOS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A039/ENS015', 'DLCOE/NCE/24A039/ENS015', 'atika@123', 'student123', 'Atika Mahmud Muhammad', 'atika.mahmud@email.com', '08012345721', 'ENG/SOS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A040/ENS016', 'DLCOE/NCE/24A040/ENS016', 'hauwau@123', 'student123', 'Hauwau Bashir Abdullahi', 'hauwau.bashir@email.com', '08012345722', 'ENG/SOS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A041/ENS017', 'DLCOE/NCE/24A041/ENS017', 'khadijat@123', 'student123', 'Khadijat Alhassan Ibrahim', 'khadijat.alhassan@email.com', '08012345723', 'ENG/SOS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A042/ENS018', 'DLCOE/NCE/24A042/ENS018', 'khadija.salisu@123', 'student123', 'Khadija Salisu', 'khadija.salisu@email.com', '08012345724', 'ENG/SOS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A043/ENS019', 'DLCOE/NCE/24A043/ENS019', 'usaina@123', 'student123', 'Usaina Musa Zakari', 'usaina.musa@email.com', '08012345725', 'ENG/SOS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A046/ENS020', 'DLCOE/NCE/24A046/ENS020', 'muhammad.aminu@123', 'student123', 'Muhammad Aminu Muhammad', 'muhammad.amimu@email.com', '08012345726', 'ENG/SOS', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A047/CSB009', 'DLCOE/NCE/24A047/CSB009', 'hussaini@123', 'student123', 'Hussaini Nura Sharif', 'hussaini.nura@email.com', '08012345727', 'CSC/BIO', 'NCE', 'NCE III', 'SHINGE', 'active', NOW()),
    ('DLCOE/NCE/24A048/CSB010', 'DLCOE/NCE/24A048/CSB010', 'hassan.nura@123', 'student123', 'Hassan Nura Sharif', 'hassan.nura@email.com', '08012345728', 'CSC/BIO', 'NCE', 'NCE III', 'SHINGE', 'active', NOW())");
    
    // Applications
    mysqli_query($conn, "INSERT INTO applications (student_id, fullname, email, phone, course_applied, programme, branch_code, status, created_at)
    SELECT id, fullname, email, phone, course, programme, branch_code, 'pending', created_at
    FROM students WHERE email IS NOT NULL AND email != ''");
    
    // Courses
    mysqli_query($conn, "INSERT INTO courses (course_code, course_title, department, programme, level, credits, semester, status) VALUES 
    ('EDU111', 'History of Education', 'PED', 'NCE', 'NCE I', 2, 'First Semester', 'active'),
    ('GSE111', 'General English I', 'GSE', 'NCE', 'NCE I', 2, 'First Semester', 'active'),
    ('PED111', 'Introduction to Primary Education', 'PED', 'NCE', 'NCE I', 2, 'First Semester', 'active'),
    ('CSC111', 'Introduction to Computer Science', 'CSC', 'NCE', 'NCE I', 2, 'First Semester', 'active'),
    ('MTH111', 'Basic Mathematics I', 'MTH', 'NCE', 'NCE I', 2, 'First Semester', 'active'),
    ('ENG111', 'Introduction to English Literature', 'ENG', 'NCE', 'NCE I', 2, 'First Semester', 'active'),
    ('SOC111', 'Introduction to Sociology', 'SOC', 'NCE', 'NCE I', 2, 'First Semester', 'active'),
    ('ISC111', 'Introduction to Islamic Studies', 'ISC', 'NCE', 'NCE I', 2, 'First Semester', 'active'),
    ('ARI111', 'Introduction to Arabic Language', 'ARI', 'NCE', 'NCE I', 2, 'First Semester', 'active')");
}
?>