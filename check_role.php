<?php
// ============================================
// CHECK ROLE - ACCESS CONTROL
// ============================================

// ============================================
// CAN ACCESS RESULT SYSTEM (Admin, Provost, Exam Officer)
// ============================================
function canAccessResultSystem() {
    if (!isset($_SESSION['user_id'])) return false;
    
    $user_role = $_SESSION['role'] ?? '';
    $position = strtolower(trim($_SESSION['position'] ?? ''));
    
    return (
        $user_role == 'admin' 
        || $user_role == 'Provost' 
        || $user_role == 'Exam Officer'
        || $position == 'provost'
        || $position == 'exam officer'
    );
}

// ============================================
// CAN ACCESS SETTINGS (ADMIN KAWAI)
// ============================================
function canAccessSettings() {
    if (!isset($_SESSION['user_id'])) return false;
    return ($_SESSION['role'] ?? '') == 'admin';
}

// ============================================
// CAN ACCESS TRANSCRIPT (Admin, Provost, Exam Officer)
// ============================================
function canAccessTranscript() {
    return canAccessResultSystem();
}

// ============================================
// CAN ACCESS FINAL RESULT (Admin da Provost)
// ============================================
function canAccessFinalResult() {
    if (!isset($_SESSION['user_id'])) return false;
    
    $user_role = $_SESSION['role'] ?? '';
    $position = strtolower(trim($_SESSION['position'] ?? ''));
    
    return (
        $user_role == 'admin' 
        || $user_role == 'Provost' 
        || $position == 'provost'
    );
}

// ============================================
// CAN ACCESS STATEMENT OF RESULT (Admin da Provost)
// ============================================
function canAccessStatementOfResult() {
    if (!isset($_SESSION['user_id'])) return false;
    
    $user_role = $_SESSION['role'] ?? '';
    $position = strtolower(trim($_SESSION['position'] ?? ''));
    
    return (
        $user_role == 'admin' 
        || $user_role == 'Provost' 
        || $position == 'provost'
    );
}

// ============================================
// CAN ACCESS GRADE SETUP (Admin, Provost, Exam Officer)
// ============================================
function canAccessGradeSetup() {
    return canAccessResultSystem();
}

// ============================================
// CAN ACCESS RESULT ENTRY (Admin, Provost, Exam Officer)
// ============================================
function canAccessResultEntry() {
    return canAccessResultSystem();
}

// ============================================
// CAN ACCESS RESULT SLIP (Admin, Provost, Exam Officer)
// ============================================
function canAccessResultSlip() {
    return canAccessResultSystem();
}

// ============================================
// CAN ACCESS RESULT DATABASE (Admin, Provost, Exam Officer)
// ============================================
function canAccessResultDatabase() {
    return canAccessResultSystem();
}

// ============================================
// CAN ACCESS COURSE STRUCTURE (Admin, Provost, Exam Officer)
// ============================================
function canAccessCourseStructure() {
    return canAccessResultSystem();
}

// ============================================
// IS ADMIN
// ============================================
function isAdmin() {
    return ($_SESSION['role'] ?? '') == 'admin';
}

// ============================================
// IS PROVOST
// ============================================
function isProvost() {
    $user_role = $_SESSION['role'] ?? '';
    $position = strtolower(trim($_SESSION['position'] ?? ''));
    return ($user_role == 'Provost' || $position == 'provost');
}

// ============================================
// IS EXAM OFFICER
// ============================================
function isExamOfficer() {
    $user_role = $_SESSION['role'] ?? '';
    $position = strtolower(trim($_SESSION['position'] ?? ''));
    return ($user_role == 'Exam Officer' || $position == 'exam officer');
}

// ============================================
// IS STAFF
// ============================================
function isStaff() {
    return ($_SESSION['role'] ?? '') == 'staff';
}

// ============================================
// IS ACCOUNTANT
// ============================================
function isAccountant() {
    return ($_SESSION['role'] ?? '') == 'Accountant';
}

// ============================================
// IS BURSARY
// ============================================
function isBursary() {
    return ($_SESSION['role'] ?? '') == 'Bursary';
}

// ============================================
// IS ADMISSION OFFICER
// ============================================
function isAdmissionOfficer() {
    return ($_SESSION['role'] ?? '') == 'Admission Officer';
}

// ============================================
// CAN ACCEPT APPLICATIONS
// ============================================
function canAcceptApplications() {
    if (!isset($_SESSION['user_id'])) return false;
    
    $user_role = $_SESSION['role'] ?? '';
    $position = strtolower(trim($_SESSION['position'] ?? ''));
    $can_accept = $_SESSION['can_accept'] ?? 'no';
    
    return (
        $can_accept == 'yes'
        || $user_role == 'admin'
        || $user_role == 'Provost'
        || $user_role == 'Admission Officer'
        || $position == 'provost'
        || $position == 'admission officer'
    );
}

// ============================================
// REQUIRE RESULT SYSTEM ACCESS
// ============================================
function requireResultSystemAccess() {
    if (!canAccessResultSystem()) {
        header('Location: staff_dashboard.php?error=access_denied');
        exit();
    }
}

// ============================================
// REQUIRE ADMIN ACCESS
// ============================================
function requireAdminAccess() {
    if (!isAdmin()) {
        header('Location: staff_dashboard.php?error=access_denied');
        exit();
    }
}

// ============================================
// REQUIRE FINAL RESULT ACCESS
// ============================================
function requireFinalResultAccess() {
    if (!canAccessFinalResult()) {
        header('Location: staff_dashboard.php?error=access_denied');
        exit();
    }
}

// ============================================
// REQUIRE STATEMENT ACCESS
// ============================================
function requireStatementAccess() {
    if (!canAccessStatementOfResult()) {
        header('Location: staff_dashboard.php?error=access_denied');
        exit();
    }
}
?>