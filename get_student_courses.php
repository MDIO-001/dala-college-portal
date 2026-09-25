<?php
session_start();
require_once 'connect.php';
require_once 'result_functions.php';
include 'check_role.php';

// ============================================
// ACCESS CONTROL
// ============================================
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color:red;'>Unauthorized</p>";
    exit();
}

if (!canAccessResultSystem()) {
    echo "<p style='color:red;'>Access Denied</p>";
    exit();
}

// ============================================
// GET PARAMETERS
// ============================================
$student_id = intval($_GET['student_id'] ?? 0);
$combination = $_GET['combination'] ?? '';
$level = $_GET['level'] ?? '';
$semester = $_GET['semester'] ?? '';
$session = $_GET['session'] ?? getSetting($conn, 'current_session');

if (!$student_id) {
    echo "<p style='color:red;'>Student not selected.</p>";
    exit();
}

// ============================================
// SAMO BAYANAN DALIBI
// ============================================
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo "<p style='color:red;'>Student not found.</p>";
    exit();
}

// ============================================
// GANE LEVEL NA YANZU
// ============================================
$admission_year = $student['entry_year'] ?? $student['admission_year'] ?? null;
$programme_type = $student['programme'] ?? $student['programme_type'] ?? 'NCE';

$current_level = 'N/A';
if ($admission_year) {
    $current_level = getCurrentLevel($admission_year, $programme_type, $session);
}

// ⚠️ GYARA: Yi amfani da $conn ba $pdo ba, kuma ba da $student_id
$grad_year = getGraduationYear($conn, $student_id);

// ============================================
// SAMO COURSES DAGA course_registrations
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
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $existing_results[$r['course_code']] = $r;
}

// ============================================
// SAMO CARRY OVERS
// ============================================
$carryovers = [];
if (function_exists('getCarryOverCourses')) {
    // ⚠️ GYARA: getCarryOverCourses yana mayar da ARRAY, ba PDOStatement ba
    $co_result = getCarryOverCourses($conn, $student_id, $combination);
    if (is_array($co_result)) {
        $carryovers = $co_result;
    }
}
?>

<div class="student-info-box">
    <h3>📋 <?php echo htmlspecialchars($student['fullname']); ?></h3>
    <p><strong>Reg No:</strong> <?php echo htmlspecialchars($student['reg_no'] ?? $student['student_id']); ?></p>
    <p><strong>Combination:</strong> <?php echo htmlspecialchars($combination); ?></p>
    <p><strong>Current Level:</strong> <?php echo htmlspecialchars($current_level); ?> 
       (Admission: <?php echo htmlspecialchars($admission_year ?? '—'); ?>)</p>
    <p><strong>Entering Result for:</strong> 
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
        <h3 style="color:#e65100; margin-bottom:10px;">⚠️ No Courses Found</h3>
        <p style="color:#1a2e1a;">
            No courses registered for this student at this Level, Semester, and Session.
        </p>
        <ul style="text-align:left; max-width:600px; margin:15px auto; color:#1a2e1a;">
            <li><strong>Combination:</strong> <?php echo htmlspecialchars($combination); ?></li>
            <li><strong>Level:</strong> <?php echo htmlspecialchars($level); ?></li>
            <li><strong>Semester:</strong> <?php echo htmlspecialchars($semester); ?></li>
            <li><strong>Session:</strong> <?php echo htmlspecialchars($session); ?></li>
        </ul>
        <p style="color:#c62828; font-weight:700;">
            Make sure the student has registered courses in the <code>course_registrations</code> table.
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
    <div class="carryover-box" style="margin-bottom:20px; padding:15px; background:#ffebee; border-radius:10px; border-left:5px solid #c62828;">
        <h3 style="color:#c62828; margin-bottom:10px;">⚠️ Carry Over Courses</h3>
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
    <div class="card" style="background:white; padding:20px; border-radius:10px; margin-bottom:20px;">
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
    <div class="total-summary" style="display:grid; grid-template-columns:repeat(3, 1fr); gap:15px; margin-bottom:15px;">
        <div class="summary-box" style="border:2px solid #0d2818; padding:15px; text-align:center; border-radius:8px; background:#f8faf8;">
            <div class="label" style="font-size:0.7rem; text-transform:uppercase; color:#6a8f6a; font-weight:700;">Total Courses</div>
            <div class="value" id="totalCourses" style="font-size:1.6rem; font-weight:900; color:#0d2818;"><?php echo count($courses) + count($carryovers); ?></div>
        </div>
        <div class="summary-box" style="border:2px solid #0d2818; padding:15px; text-align:center; border-radius:8px; background:#f8faf8;">
            <div class="label" style="font-size:0.7rem; text-transform:uppercase; color:#6a8f6a; font-weight:700;">Total Units</div>
            <div class="value" id="totalUnits" style="font-size:1.6rem; font-weight:900; color:#0d2818;">0</div>
        </div>
        <div class="summary-box gpa" style="border:2px solid #2e7d32; padding:15px; text-align:center; border-radius:8px; background:#e8f5e9;">
            <div class="label" style="font-size:0.7rem; text-transform:uppercase; color:#6a8f6a; font-weight:700;">GPA</div>
            <div class="value" id="gpa" style="font-size:1.6rem; font-weight:900; color:#2e7d32;">0.00</div>
        </div>
    </div>
    
    <button type="submit" name="save_single" class="btn btn-green" 
            style="font-size:1rem; padding:14px 40px; margin-top:15px; background:#2e7d32; color:white; border:none; border-radius:8px; font-weight:700; cursor:pointer;">
        <i class="fas fa-save"></i> Save All Results
    </button>
    <?php endif; ?>
</form>

<script>
function autoGrade(input) {
    var score = parseInt(input.value);
    if (isNaN(score) || score < 0 || score > 100) return;
    
    var grade, point, remark;
    if (score >= 70) { grade = 'A'; point = 4.0; remark = 'Distinction'; }
    else if (score >= 60) { grade = 'B'; point = 3.0; remark = 'Credit'; }
    else if (score >= 50) { grade = 'C'; point = 2.0; remark = 'Merit'; }
    else if (score >= 45) { grade = 'D'; point = 1.0; remark = 'Pass'; }
    else if (score >= 40) { grade = 'E'; point = 0.5; remark = 'Lower Pass'; }
    else { grade = 'F'; point = 0.0; remark = 'Fail'; }
    
    var row = input.closest('tr');
    if (row) {
        var gradeEl = row.querySelector('.grade-display');
        var pointEl = row.querySelector('.point-display');
        var remarkEl = row.querySelector('.remark-display');
        if (gradeEl) gradeEl.textContent = grade;
        if (pointEl) pointEl.textContent = point.toFixed(1);
        if (remarkEl) remarkEl.textContent = remark;
    }
}

function calculateTotal() {
    var rows = document.querySelectorAll('tr[data-credits]');
    var totalUnits = 0;
    var totalPoints = 0;
    
    rows.forEach(function(row) {
        var input = row.querySelector('.score-input');
        var credits = parseInt(row.getAttribute('data-credits')) || 0;
        var score = parseInt(input ? input.value : 0);
        
        if (!isNaN(score) && score >= 40 && input && input.value !== '') {
            var point = 0;
            if (score >= 70) point = 4.0;
            else if (score >= 60) point = 3.0;
            else if (score >= 50) point = 2.0;
            else if (score >= 45) point = 1.0;
            else if (score >= 40) point = 0.5;
            
            totalUnits += credits;
            totalPoints += point * credits;
        }
    });
    
    var gpa = totalUnits > 0 ? (totalPoints / totalUnits).toFixed(2) : '0.00';
    
    var unitsEl = document.getElementById('totalUnits');
    var gpaEl = document.getElementById('gpa');
    if (unitsEl) unitsEl.textContent = totalUnits;
    if (gpaEl) gpaEl.textContent = gpa;
}

document.addEventListener('DOMContentLoaded', function() {
    calculateTotal();
});
</script>