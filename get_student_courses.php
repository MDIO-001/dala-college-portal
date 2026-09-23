<?php
session_start();
require_once 'connect.php';
require_once 'result_functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'Exam Officer', 'Provost'])) {
    echo "<p style='color:red;'>Unauthorized</p>";
    exit();
}

$student_id = intval($_GET['student_id'] ?? 0);
$combination = $_GET['combination'] ?? '';
$level = $_GET['level'] ?? '';          // Level ɗin da admin ya zaɓa
$semester = $_GET['semester'] ?? '';
$session = $_GET['session'] ?? getSetting($pdo, 'current_session');

if (!$student_id) {
    echo "<p style='color:red;'>Student not selected.</p>";
    exit();
}

// Samo bayanan dalibi
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    echo "<p style='color:red;'>Student not found.</p>";
    exit();
}

// Gane level na yanzu (don nunawa kawai)
$current_level = getCurrentLevel($student['admission_year'], $student['programme_type'], $session);
$grad_year = getGraduationYear($student['admission_year'], $student['programme_type']);

// ============================================
// SAMO COURSES DAGA course_registrations
// BISA LEVEL + SEMESTER + SESSION DA AKA ZAƊA
// ============================================
$level_sql = buildLevelCondition($level, 'cr.level');
$variants = getLevelVariants($level);

$sql = "SELECT cr.*, 
               COALESCE(c.credits, cr.credits, 0) AS course_credits,
               c.category,
               c.course_title AS course_title_from_courses
        FROM course_registrations cr
        LEFT JOIN courses c 
            ON c.course_code = cr.course_code 
            AND c.combination = ? 
            AND c.level = ? 
            AND c.semester = ?
        WHERE cr.student_id = ?
        AND $level_sql
        AND cr.semester = ?
        AND cr.academic_year = ?
        AND cr.status IN ('registered', 'completed')
        ORDER BY FIELD(c.category, 'EDU', 'GSE', 'PED', 'CSC', 'BIO', 'ISC', 'PHY', 'ENG', 'ECO', 'ARB', 'ISS', 'HAU', 'SOS'), 
                 cr.course_code";

$params = array_merge(
    [$combination, $level, $semester, $student_id],
    $variants,
    [$semester, $session]
);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll();

// ============================================
// SAMO EXISTING RESULTS
// ============================================
$existing_results = [];
$stmt = $pdo->prepare("SELECT * FROM results 
                       WHERE student_id = ? 
                       AND level = ? 
                       AND semester = ? 
                       AND session = ?");
$stmt->execute([$student_id, $level, $semester, $session]);
while ($r = $stmt->fetch()) {
    $existing_results[$r['course_code']] = $r;
}

// ============================================
// SAMO CARRY OVERS
// ============================================
$carryovers = [];
if (function_exists('getCarryOverCourses')) {
    $co_result = getCarryOverCourses($pdo, $student_id, $combination);
    if ($co_result) {
        while ($co = $co_result->fetch()) {
            $carryovers[] = $co;
        }
    }
}
?>

<div class="student-info-box">
    <h3>📋 <?php echo htmlspecialchars($student['fullname']); ?></h3>
    <p><strong>Reg No:</strong> <?php echo htmlspecialchars($student['reg_no'] ?? $student['student_id']); ?></p>
    <p><strong>Combination:</strong> <?php echo htmlspecialchars($combination); ?></p>
    <p><strong>Level na Yanzu:</strong> <?php echo htmlspecialchars($current_level); ?> 
       (Admission: <?php echo htmlspecialchars($student['admission_year']); ?>)</p>
    <p><strong>Result ɗin da ake rubutawa:</strong> 
       <span style="background:#2e7d32; color:white; padding:3px 10px; border-radius:5px; font-weight:700;">
           <?php echo htmlspecialchars($level); ?> — <?php echo htmlspecialchars($semester); ?> — <?php echo htmlspecialchars($session); ?>
       </span>
    </p>
    <p><strong>Graduation Year:</strong> <?php echo htmlspecialchars($grad_year); ?></p>
    <?php if (count($carryovers) > 0): ?>
        <p style="color:#c62828; font-weight:700; margin-top:10px;">
            ⚠️ Carry Over: <strong><?php echo count($carryovers); ?></strong>
        </p>
    <?php endif; ?>
</div>

<?php if (count($courses) == 0): ?>
    <div style="text-align:center; padding:40px; background:#fff3e0; border-radius:10px; border-left:5px solid #f57c00; margin-bottom:20px;">
        <h3 style="color:#e65100; margin-bottom:10px;">⚠️ Babu Courses</h3>
        <p style="color:#1a2e1a;">
            Babu courses ɗin da dalibin ya yi registration a wannan Level, Semester, da Session.
        </p>
        <ul style="text-align:left; max-width:600px; margin:15px auto; color:#1a2e1a;">
            <li><strong>Combination:</strong> <?php echo htmlspecialchars($combination); ?></li>
            <li><strong>Level:</strong> <?php echo htmlspecialchars($level); ?></li>
            <li><strong>Semester:</strong> <?php echo htmlspecialchars($semester); ?></li>
            <li><strong>Session:</strong> <?php echo htmlspecialchars($session); ?></li>
        </ul>
        <p style="color:#c62828; font-weight:700;">
            Tabbatar cewa dalibin ya yi registration a <code>course_registrations</code> table.
        </p>
    </div>
<?php endif; ?>

<form method="POST" action="admin_result_entry.php">
    <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
    <input type="hidden" name="combination" value="<?php echo htmlspecialchars($combination); ?>">
    <input type="hidden" name="level" value="<?php echo htmlspecialchars($level); ?>">
    <input type="hidden" name="semester" value="<?php echo htmlspecialchars($semester); ?>">
    <input type="hidden" name="session" value="<?php echo htmlspecialchars($session); ?>">
    
    <?php if (count($carryovers) > 0): ?>
    <div class="carryover-box">
        <h3>⚠️ Carry Over Courses</h3>
        <table class="result-table">
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Course Title</th>
                    <th>Units</th>
                    <th>Original Session</th>
                    <th>Score</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($carryovers as $c): ?>
                <tr style="background:#ffebee;" 
                    data-course="<?php echo htmlspecialchars($c['course_code']); ?>" 
                    data-credits="<?php echo $c['credit_units']; ?>">
                    <td><strong><?php echo htmlspecialchars($c['course_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($c['course_title']); ?></td>
                    <td><?php echo $c['credit_units']; ?></td>
                    <td><?php echo htmlspecialchars($c['session']); ?></td>
                    <td>
                        <input type="number" 
                               name="scores[<?php echo $c['course_code']; ?>]" 
                               class="score-input" min="0" max="100" value=""
                               onchange="autoGrade(this); calculateTotal();">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <?php if (count($courses) > 0): ?>
    <div class="card">
        <h3 style="color:#0d2818; margin-bottom:15px;">
            📚 <?php echo htmlspecialchars($level); ?> — <?php echo htmlspecialchars($semester); ?> — <?php echo htmlspecialchars($session); ?>
        </h3>
        <table class="result-table">
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Course Title</th>
                    <th>Units</th>
                    <th>Score</th>
                    <th>Grade</th>
                    <th>Point</th>
                    <th>Remark</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $c): 
                    $credits = $c['course_credits'] ?? $c['credit_units'] ?? 0;
                    $title = $c['course_title_from_courses'] ?? $c['course_title'] ?? '';
                    $existing_score = $existing_results[$c['course_code']]['score'] ?? '';
                    $existing_grade = $existing_results[$c['course_code']]['grade'] ?? '—';
                    $existing_point = $existing_results[$c['course_code']]['grade_point'] ?? 0;
                    $existing_remark = $existing_results[$c['course_code']]['remark'] ?? '—';
                ?>
                <tr data-course="<?php echo htmlspecialchars($c['course_code']); ?>" 
                    data-credits="<?php echo $credits; ?>">
                    <td><strong><?php echo htmlspecialchars($c['course_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($title); ?></td>
                    <td class="center"><?php echo $credits; ?></td>
                    <td class="center">
                        <input type="number" 
                               name="scores[<?php echo $c['course_code']; ?>]" 
                               class="score-input" min="0" max="100" 
                               value="<?php echo htmlspecialchars($existing_score); ?>"
                               onchange="autoGrade(this); calculateTotal();"
                               onkeyup="autoGrade(this); calculateTotal();">
                    </td>
                    <td class="center grade-display"><?php echo $existing_grade; ?></td>
                    <td class="center point-display"><?php echo number_format($existing_point, 1); ?></td>
                    <td class="center remark-display"><?php echo $existing_remark; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <?php if (count($courses) > 0 || count($carryovers) > 0): ?>
    <div class="total-summary">
        <div class="summary-box">
            <div class="label">Total Courses</div>
            <div class="value" id="totalCourses"><?php echo count($courses); ?></div>
        </div>
        <div class="summary-box">
            <div class="label">Total Units</div>
            <div class="value" id="totalUnits">0</div>
        </div>
        <div class="summary-box gpa">
            <div class="label">GPA</div>
            <div class="value" id="gpa">0.00</div>
        </div>
    </div>
    
    <button type="submit" name="save_single" class="btn btn-green" 
            style="font-size:1rem; padding:14px 40px; margin-top:15px;">
        <i class="fas fa-save"></i> Save All Results
    </button>
    <?php endif; ?>
</form>