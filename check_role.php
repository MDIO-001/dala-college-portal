<?php
// ============================================
// ROLE CHECKING FUNCTION
// ============================================

/**
 * Duba idan user yana da izinin shiga result system
 * Admin da Exam Officer kawai
 */
function canAccessResultSystem() {
    if (!isset($_SESSION['role'])) return false;
    return in_array($_SESSION['role'], ['admin', 'exam_officer']);
}

/**
 * Duba idan user admin ne kawai
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

/**
 * Duba idan user exam officer ne
 */
function isExamOfficer() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'exam_officer';
}

/**
 * Duba idan user zai iya yin FINAL RESULT da STATEMENT
 */
function canAccessFinalResult() {
    return isAdmin();
}

/**
 * Duba idan user zai iya yin SETTINGS
 */
function canAccessSettings() {
    return isAdmin();
}

/**
 * Nuna navbar ɗin result system bisa role
 */
function renderResultNavbar($current_page = '') {
    $role = $_SESSION['role'] ?? '';
    $is_admin = ($role == 'admin');
    
    echo '<div class="result-nav">';
    echo '<span class="label">📊 RESULT SYSTEM:</span>';
    
    $links = [
        'admin_course_structure.php' => ['COURSE_STRUCTURE', 'r-course'],
        'admin_grade_setup.php' => ['GRADE_SETUP', 'r-grade'],
        'admin_result_entry.php' => ['RESULT_ENTRY', 'r-entry'],
        'admin_result_slip.php' => ['RESULT_SLIP', 'r-slip'],
        'admin_result_slip_pro.php' => ['RESULT_SLIP_PRO', 'r-slip-pro'],
        'admin_transcript.php' => ['TRANSCRIPT', 'r-transcript'],
    ];
    
    foreach ($links as $file => $data) {
        $active = ($current_page == $file) ? ' active' : '';
        echo '<a href="' . $file . '" class="' . $data[1] . $active . '">' . $data[0] . '</a>';
    }
    
    // Final Result da Statement - Admin kawai
    if ($is_admin) {
        $active_final = ($current_page == 'admin_final_result.php') ? ' active' : '';
        $active_stmt = ($current_page == 'admin_statement_of_result.php') ? ' active' : '';
        echo '<a href="admin_final_result.php" class="r-final' . $active_final . '">FINAL_RESULT</a>';
        echo '<a href="admin_settings.php" class="r-settings' . ($current_page == 'admin_settings.php' ? ' active' : '') . '">SETTINGS</a>';
    }
    
    echo '<a href="admin_result_database.php" class="r-database' . ($current_page == 'admin_result_database.php' ? ' active' : '') . '">RESULT_DATABASE</a>';
    
    if ($is_admin) {
        echo '<a href="admin_statement_of_result.php" class="r-statement' . $active_stmt . '">STATEMENT_OF_RESULT</a>';
    }
    
    echo '</div>';
}
?>