<?php
// ============================================
// RESULT SYSTEM FUNCTIONS
// Dace da Grade Setup: A=4, B=3, C=2, D=1, E=0.5, F=0
// ============================================

/**
 * Level Options (NCE + Degree)
 * NCE: NCE I, NCE II, NCE III
 * Degree: 400 Level, 500 Level
 */
function getLevelOptions() {
    return [
        'NCE I',
        'NCE II',
        'NCE III',
        '400 Level',
        '500 Level'
    ];
}

/**
 * Level daga programme
 */
function getLevelByProgramme($programme) {
    if ($programme == 'DEGREE') {
        return '400 Level';
    }
    return 'NCE I';
}

/**
 * Samo grade daga score
 */
function getGrade($conn, $score) {
    $score = intval($score);
    $result = mysqli_query($conn, "SELECT * FROM grade_setup 
                                   WHERE $score >= min_score 
                                   AND $score <= max_score 
                                   AND status = 'Active' 
                                   LIMIT 1");
    if ($row = mysqli_fetch_assoc($result)) return $row;
    return ['grade' => 'F', 'grade_point' => 0, 'remark' => 'Fail'];
}

/**
 * Samo setting
 */
function getSetting($conn, $key) {
    $key = mysqli_real_escape_string($conn, $key);
    $result = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key = '$key' LIMIT 1");
    if ($row = mysqli_fetch_assoc($result)) return $row['setting_value'];
    return null;
}

/**
 * Samo carry over courses (F grades da ba a sake ba)
 */
function getCarryOverCourses($conn, $student_id, $combination = null) {
    $student_id = intval($student_id);
    $where_comb = '';
    if ($combination) {
        $combination = mysqli_real_escape_string($conn, $combination);
        $where_comb = " AND r.combination = '$combination'";
    }
    
    $query = "SELECT r.* FROM results r
              WHERE r.student_id = $student_id 
              AND r.grade = 'F'
              $where_comb
              AND NOT EXISTS (
                  SELECT 1 FROM results r2 
                  WHERE r2.student_id = r.student_id 
                  AND r2.course_code = r.course_code 
                  AND r2.grade != 'F'
                  AND (r2.session > r.session OR (r2.session = r.session AND r2.id > r.id))
              )
              ORDER BY r.session, r.course_code";
    
    return mysqli_query($conn, $query);
}

/**
 * Samo duk courses na semester na yanzu + carry overs
 */
function getCoursesWithCarryOver($conn, $student_id, $combination, $level, $semester) {
    $student_id = intval($student_id);
    $combination = mysqli_real_escape_string($conn, $combination);
    $level = mysqli_real_escape_string($conn, $level);
    $semester = mysqli_real_escape_string($conn, $semester);
    
    $all_courses = [];
    
    // 1. Carry over courses
    $carryovers = getCarryOverCourses($conn, $student_id, $combination);
    while ($co = mysqli_fetch_assoc($carryovers)) {
        $co['is_carryover'] = true;
        $co['carryover_from_session'] = $co['session'];
        $co['original_level'] = $co['level'];
        $all_courses[] = $co;
    }
    
    // 2. Current semester courses
    $used_codes = array_column($all_courses, 'course_code');
    $current = mysqli_query($conn, "SELECT * FROM courses 
                                    WHERE combination = '$combination' 
                                    AND level = '$level' 
                                    AND semester = '$semester'
                                    AND status = 'Compulsory'
                                    ORDER BY FIELD(category, 'EDU', 'GSE', 'PED', 'CSC', 'BIO', 'ISC', 'PHY', 'ENG', 'ECO', 'ARB', 'ISS', 'HAU', 'SOS'), 
                                    course_code");
    while ($c = mysqli_fetch_assoc($current)) {
        if (in_array($c['course_code'], $used_codes)) continue;
        $c['is_carryover'] = false;
        $all_courses[] = $c;
    }
    
    return $all_courses;
}

/**
 * Duba idan dalibi yana da carry over
 */
function hasCarryOver($conn, $student_id, $combination = null) {
    $result = getCarryOverCourses($conn, $student_id, $combination);
    return $result && mysqli_num_rows($result) > 0;
}

/**
 * Samo CGPA na dalibi
 */
function getStudentCGPA($conn, $student_id) {
    $student_id = intval($student_id);
    $result = mysqli_query($conn, "SELECT * FROM results 
                                   WHERE student_id = $student_id 
                                   AND grade != 'F'");
    $total_points = 0; $total_units = 0;
    while ($r = mysqli_fetch_assoc($result)) {
        $total_points += $r['grade_point'] * $r['credit_units'];
        $total_units += $r['credit_units'];
    }
    return $total_units > 0 ? round($total_points / $total_units, 2) : 0;
}

/**
 * Lissafta GPA daga jerin results
 */
function calculateGPA($results) {
    $total_points = 0;
    $total_units = 0;
    foreach ($results as $r) {
        if ($r['grade'] != 'F') {
            $total_points += $r['grade_point'] * $r['credit_units'];
            $total_units += $r['credit_units'];
        }
    }
    return $total_units > 0 ? round($total_points / $total_units, 2) : 0;
}

/**
 * Class of Degree (Max CGPA = 4.00)
 */
function getClassOfDegree($cgpa) {
    if ($cgpa >= 3.50) return 'Distinction';
    if ($cgpa >= 3.00) return 'Upper Credit';
    if ($cgpa >= 2.50) return 'Lower Credit';
    if ($cgpa >= 2.00) return 'Merit';
    if ($cgpa >= 1.50) return 'Pass';
    return 'Fail';
}

function getGradeClassification($cgpa) {
    return getClassOfDegree($cgpa);
}

/**
 * Shekarar kammala karatu
 */
function getGraduationYear($conn, $student_id) {
    $student_id = intval($student_id);
    $result = mysqli_query($conn, "SELECT MAX(session) AS last_session FROM results WHERE student_id = $student_id");
    if ($row = mysqli_fetch_assoc($result)) {
        if ($row['last_session']) {
            $parts = explode('/', $row['last_session']);
            return $parts[1] ?? $row['last_session'];
        }
    }
    return date('Y');
}

/**
 * Session Options (10 sessions: 2022 - 2032)
 */
function getSessionOptions() {
    return [
        '2022/2023', '2023/2024', '2024/2025', '2025/2026', '2026/2027',
        '2027/2028', '2028/2029', '2029/2030', '2030/2031', '2031/2032'
    ];
}

/**
 * Category Title
 */
function getCategoryTitle($cat) {
    $titles = [
        'EDU' => 'Education',
        'GSE' => 'General Studies',
        'CSC' => 'Computer Science',
        'BIO' => 'Biology',
        'PHY' => 'Physics',
        'ISC' => 'Integrated Science',
        'ENG' => 'English',
        'ECO' => 'Economics',
        'ARB' => 'Arabic',
        'ISS' => 'Islamic Studies',
        'HAU' => 'Hausa',
        'SOS' => 'Social Studies',
        'PED' => 'Primary Education'
    ];
    return $titles[$cat] ?? $cat;
}

/**
 * CGPA na kowane category
 */
function getCategoryGPAs($conn, $student_id) {
    $student_id = intval($student_id);
    $categories = ['EDU', 'CSC', 'ISC', 'GSE', 'PED', 'BIO', 'PHY', 'ENG', 'ECO', 'ARB', 'ISS', 'HAU', 'SOS'];
    
    $result = [];
    foreach ($categories as $cat) {
        $q = "SELECT r.* FROM results r
              LEFT JOIN courses c ON r.course_code = c.course_code AND c.combination = r.combination
              WHERE r.student_id = $student_id 
              AND r.grade != 'F'
              AND (c.category = '$cat' OR r.course_code LIKE '" . substr($cat, 0, 3) . "%')";
        
        $res = mysqli_query($conn, $q);
        $total_points = 0; $total_units = 0;
        while ($r = mysqli_fetch_assoc($res)) {
            $total_points += $r['grade_point'] * $r['credit_units'];
            $total_units += $r['credit_units'];
        }
        
        if ($total_units > 0) {
            $result[$cat] = [
                'gpa' => round($total_points / $total_units, 2),
                'units' => $total_units,
                'courses' => mysqli_num_rows($res)
            ];
        }
    }
    return $result;
}
?>