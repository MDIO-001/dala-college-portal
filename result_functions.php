<?php
// ============================================
// RESULT SYSTEM FUNCTIONS
// Yana aiki da PDO DA mysqli
// ============================================

// ============================================
// LEVEL OPTIONS
// ============================================
if (!function_exists('getLevelOptions')) {
    function getLevelOptions() {
        return ['NCE I', 'NCE II', 'NCE III', '400 Level', '500 Level'];
    }
}

if (!function_exists('getLevelByProgramme')) {
    function getLevelByProgramme($programme, $explicit_level = null) {
        if ($explicit_level && !empty($explicit_level)) return trim($explicit_level);
        $programme = strtoupper(trim($programme));
        if ($programme == 'DEGREE' || $programme == 'DEG') return '400 Level';
        if ($programme == 'ENTREPRENEURSHIP' || $programme == 'ENT') return 'ENTREPRENEURSHIP';
        return 'NCE I';
    }
}

// ============================================
// GET SETTING (mysqli)
// ============================================
if (!function_exists('getSetting')) {
    function getSetting($conn, $key) {
        $key = mysqli_real_escape_string($conn, $key);
        $result = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key = '$key'");
        
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            return $row['setting_value'];
        }
        
        return null;
    }
}

// ============================================
// GET GRADE
// ============================================
if (!function_exists('getGrade')) {
    function getGrade($pdo, $score) {
        $score = intval($score);

        try {
            $stmt = $pdo->prepare("SELECT * FROM grade_setup 
                                   WHERE ? >= min_score 
                                   AND ? <= max_score 
                                   AND status = 'Active' 
                                   ORDER BY min_score DESC
                                   LIMIT 1");
            $stmt->execute([$score, $score]);
            $row = $stmt->fetch();

            if ($row && is_array($row)) {
                return [
                    'grade' => $row['grade'] ?? 'F',
                    'grade_point' => $row['grade_point'] ?? 0,
                    'remark' => $row['remark'] ?? 'Fail'
                ];
            }
        } catch (PDOException $e) {
            // Ci gaba da fallback
        }

        // Fallback
        if ($score >= 70) return ['grade' => 'A', 'grade_point' => 4.0, 'remark' => 'Distinction'];
        if ($score >= 60) return ['grade' => 'B', 'grade_point' => 3.0, 'remark' => 'Credit'];
        if ($score >= 50) return ['grade' => 'C', 'grade_point' => 2.0, 'remark' => 'Merit'];
        if ($score >= 45) return ['grade' => 'D', 'grade_point' => 1.0, 'remark' => 'Pass'];
        if ($score >= 40) return ['grade' => 'E', 'grade_point' => 0.5, 'remark' => 'Lower Pass'];
        return ['grade' => 'F', 'grade_point' => 0.0, 'remark' => 'Fail'];
    }
}

// ============================================
// GET STUDENT CGPA (Yana aiki da PDO DA mysqli)
// ============================================
if (!function_exists('getStudentCGPA')) {
    function getStudentCGPA($conn, $student_id) {
        $student_id = intval($student_id);
        $total_points = 0; 
        $total_units = 0;
        
        // Duba ko PDO ne ko mysqli
        if ($conn instanceof PDO) {
            $stmt = $conn->prepare("SELECT * FROM results 
                                    WHERE student_id = ? 
                                    AND grade != 'F'");
            $stmt->execute([$student_id]);
            while ($r = $stmt->fetch()) {
                $total_points += $r['grade_point'] * $r['credit_units'];
                $total_units += $r['credit_units'];
            }
        } else {
            // mysqli
            $result = mysqli_query($conn, "SELECT * FROM results 
                                           WHERE student_id = $student_id 
                                           AND grade != 'F'");
            if ($result) {
                while ($r = mysqli_fetch_assoc($result)) {
                    $total_points += $r['grade_point'] * $r['credit_units'];
                    $total_units += $r['credit_units'];
                }
            }
        }
        
        return $total_units > 0 ? round($total_points / $total_units, 2) : 0;
    }
}

// ============================================
// CALCULATE GPA
// ============================================
if (!function_exists('calculateGPA')) {
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
}

// ============================================
// GET CLASS OF DEGREE
// ============================================
if (!function_exists('getClassOfDegree')) {
    function getClassOfDegree($cgpa) {
        if ($cgpa >= 3.50) return 'Distinction';
        if ($cgpa >= 3.00) return 'Credit';
        if ($cgpa >= 2.50) return 'Merit';
        if ($cgpa >= 2.00) return 'Pass';
        if ($cgpa >= 1.50) return 'Lower Pass';
        return 'Fail';
    }
}

if (!function_exists('getGradeClassification')) {
    function getGradeClassification($cgpa) {
        return getClassOfDegree($cgpa);
    }
}

// ============================================
// GET GRADUATION YEAR (Yana aiki da PDO DA mysqli)
// ============================================
if (!function_exists('getGraduationYear')) {
    function getGraduationYear($conn, $student_id = null) {
        // Idan an ba da $student_id, nemo daga database
        if ($student_id !== null) {
            $student_id = intval($student_id);
            $admission_year = null;
            $programme = 'NCE';
            
            if ($conn instanceof PDO) {
                $stmt = $conn->prepare("SELECT admission_year, entry_year, programme, programme_type FROM students WHERE id = ? LIMIT 1");
                $stmt->execute([$student_id]);
                $student = $stmt->fetch();
                if ($student) {
                    $admission_year = $student['entry_year'] ?? $student['admission_year'] ?? null;
                    $programme = $student['programme'] ?? $student['programme_type'] ?? 'NCE';
                }
            } else {
                $result = mysqli_query($conn, "SELECT admission_year, entry_year, programme, programme_type FROM students WHERE id = $student_id LIMIT 1");
                if ($result && mysqli_num_rows($result) > 0) {
                    $student = mysqli_fetch_assoc($result);
                    $admission_year = $student['entry_year'] ?? $student['admission_year'] ?? null;
                    $programme = $student['programme'] ?? $student['programme_type'] ?? 'NCE';
                }
            }
            
            if (!$admission_year) return date('Y');
            
            $start = (int) explode('/', $admission_year)[0];
            $duration = (strtoupper($programme) === 'NCE') ? 3 : 2;
            $grad_start = $start + $duration;
            return $grad_start . '/' . ($grad_start + 1);
        }
        
        // Idan an ba da admission_year kai tsaye
        if (empty($conn)) return date('Y');
        $start = (int) explode('/', $conn)[0];
        $grad_start = $start + 3;
        return $grad_start . '/' . ($grad_start + 1);
    }
}

// ============================================
// SESSION OPTIONS
// ============================================
if (!function_exists('getSessionOptions')) {
    function getSessionOptions() {
        return [
            '2022/2023', '2023/2024', '2024/2025', '2025/2026', '2026/2027',
            '2027/2028', '2028/2029', '2029/2030', '2030/2031', '2031/2032'
        ];
    }
}

// ============================================
// GET CATEGORY TITLE
// ============================================
if (!function_exists('getCategoryTitle')) {
    function getCategoryTitle($cat) {
        $titles = [
            'EDU' => 'Education', 'GSE' => 'General Studies',
            'CSC' => 'Computer Science', 'BIO' => 'Biology',
            'PHY' => 'Physics', 'ISC' => 'Integrated Science',
            'ENG' => 'English', 'ECO' => 'Economics',
            'ARB' => 'Arabic', 'ISS' => 'Islamic Studies',
            'HAU' => 'Hausa', 'SOS' => 'Social Studies',
            'PED' => 'Primary Education'
        ];
        return $titles[$cat] ?? $cat;
    }
}

// ============================================
// GET CATEGORY GPAs (Yana aiki da PDO DA mysqli)
// ============================================
if (!function_exists('getCategoryGPAs')) {
    function getCategoryGPAs($conn, $student_id) {
        $student_id = intval($student_id);
        $categories = ['EDU', 'CSC', 'ISC', 'GSE', 'PED', 'BIO', 'PHY', 'ENG', 'ECO', 'ARB', 'ISS', 'HAU', 'SOS'];
        
        // Fara da nemo duk results na ɗalibin
        $all_results = [];
        
        if ($conn instanceof PDO) {
            $stmt = $conn->prepare("SELECT * FROM results WHERE student_id = ? AND grade != 'F'");
            $stmt->execute([$student_id]);
            $all_results = $stmt->fetchAll();
        } else {
            $result = mysqli_query($conn, "SELECT * FROM results WHERE student_id = $student_id AND grade != 'F'");
            if ($result) {
                while ($r = mysqli_fetch_assoc($result)) {
                    $all_results[] = $r;
                }
            }
        }
        
        // Ƙididdige GPA na kowace category
        $result_data = [];
        foreach ($categories as $cat) {
            $total_points = 0; 
            $total_units = 0; 
            $count = 0;
            
            foreach ($all_results as $r) {
                // Duba ko course ɗin yana cikin wannan category
                $code_prefix = substr($r['course_code'], 0, 3);
                if ($code_prefix == $cat || strpos($r['course_code'], $cat) !== false) {
                    $total_points += $r['grade_point'] * $r['credit_units'];
                    $total_units += $r['credit_units'];
                    $count++;
                }
            }
            
            if ($total_units > 0) {
                $result_data[$cat] = [
                    'gpa' => round($total_points / $total_units, 2),
                    'units' => $total_units,
                    'courses' => $count
                ];
            }
        }
        return $result_data;
    }
}

// ============================================
// CARRY OVER COURSES
// ============================================
if (!function_exists('getCarryOverCourses')) {
    function getCarryOverCourses($conn, $student_id, $combination = null) {
        $student_id = intval($student_id);
        
        if ($conn instanceof PDO) {
            $where_comb = '';
            $params = [$student_id];
            if ($combination) {
                $where_comb = " AND r.combination = ?";
                $params[] = $combination;
            }
            $query = "SELECT r.* FROM results r
                      WHERE r.student_id = ? 
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
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } else {
            $where_comb = $combination ? " AND r.combination = '$combination'" : "";
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
            $result = mysqli_query($conn, $query);
            $rows = [];
            if ($result) {
                while ($r = mysqli_fetch_assoc($result)) {
                    $rows[] = $r;
                }
            }
            return $rows;
        }
    }
}

if (!function_exists('hasCarryOver')) {
    function hasCarryOver($conn, $student_id, $combination = null) {
        $result = getCarryOverCourses($conn, $student_id, $combination);
        return is_array($result) && count($result) > 0;
    }
}

// ============================================
// GET CARRY OVER FOR LEVEL
// ============================================
if (!function_exists('getCarryOverForLevel')) {
    function getCarryOverForLevel($conn, $student_id, $level = null, $semester = null, $session = null) {
        $student_id = intval($student_id);
        
        if ($conn instanceof PDO) {
            $query = "SELECT r.*, 'carryover' AS type,
                             r.session AS original_session,
                             r.level AS original_level
                      FROM results r
                      WHERE r.student_id = ? 
                      AND r.grade = 'F'
                      AND NOT EXISTS (
                          SELECT 1 FROM results r2 
                          WHERE r2.student_id = r.student_id 
                          AND r2.course_code = r.course_code 
                          AND r2.grade != 'F'
                          AND (r2.session > r.session OR (r2.session = r.session AND r2.id > r.id))
                      )
                      ORDER BY r.session ASC, r.course_code ASC";
            $stmt = $conn->prepare($query);
            $stmt->execute([$student_id]);
            return $stmt->fetchAll();
        } else {
            $query = "SELECT r.*, 'carryover' AS type,
                             r.session AS original_session,
                             r.level AS original_level
                      FROM results r
                      WHERE r.student_id = $student_id 
                      AND r.grade = 'F'
                      AND NOT EXISTS (
                          SELECT 1 FROM results r2 
                          WHERE r2.student_id = r.student_id 
                          AND r2.course_code = r.course_code 
                          AND r2.grade != 'F'
                          AND (r2.session > r.session OR (r2.session = r.session AND r2.id > r.id))
                      )
                      ORDER BY r.session ASC, r.course_code ASC";
            $result = mysqli_query($conn, $query);
            $rows = [];
            if ($result) {
                while ($r = mysqli_fetch_assoc($result)) {
                    $rows[] = $r;
                }
            }
            return $rows;
        }
    }
}

// ============================================
// LEVEL VARIANTS
// ============================================
if (!function_exists('getLevelVariants')) {
    function getLevelVariants($level) {
        $map = [
            'NCE I'     => ['NCE I', 'NCEI', 'NCE 1', 'NCE1', '100'],
            'NCE II'    => ['NCE II', 'NCEII', 'NCE 2', 'NCE2', '200'],
            'NCE III'   => ['NCE III', 'NCEIII', 'NCE 3', 'NCE3', '300'],
            '400 Level' => ['400 Level', '400L', '400'],
            '500 Level' => ['500 Level', '500L', '500'],
        ];
        return $map[$level] ?? [$level];
    }
}

if (!function_exists('buildLevelCondition')) {
    function buildLevelCondition($level, $column = 'cr.level') {
        $variants = getLevelVariants($level);
        $placeholders = implode(',', array_fill(0, count($variants), '?'));
        return "$column IN ($placeholders)";
    }
}

// ============================================
// GET CURRENT LEVEL
// ============================================
if (!function_exists('getCurrentLevel')) {
    function getCurrentLevel($admission_year, $programme_type, $current_session) {
        $start = (int) explode('/', $admission_year ?? '0/0')[0];
        $now   = (int) explode('/', $current_session)[0];
        $years_spent = $now - $start;

        if ($programme_type === 'NCE') {
            $levels = ['NCE I', 'NCE II', 'NCE III'];
            return $levels[$years_spent] ?? 'GRADUATED';
        }
        if ($programme_type === 'DEGREE') {
            $levels = ['400 Level', '500 Level'];
            return $levels[$years_spent] ?? 'GRADUATED';
        }
        return 'UNKNOWN';
    }
}
?>