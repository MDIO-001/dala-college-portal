<?php
session_start();
include 'connect.php';
include 'result_functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'exam_officer'])) {
    echo "<p style='color:red;'>Unauthorized</p>";
    exit();
}

$student_id = intval($_GET['student_id'] ?? 0);
$combination = mysqli_real_escape_string($conn, $_GET['combination'] ?? '');
$level = mysqli_real_escape_string($conn, $_GET['level'] ?? '');
$semester = mysqli_real_escape_string($conn, $_GET['semester'] ?? '');
$session = mysqli_real_escape_string($conn, $_GET['session'] ?? getSetting($conn, 'current_session'));

if (!$student_id) { echo "<p style='color:red;'>Student not selected.</p>"; exit(); }

$student = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM students WHERE id = $student_id"));
if (!$student) { echo "<p style='color:red;'>Student not found.</p>"; exit(); }

// Samo courses + carry overs
$all_courses = getCoursesWithCarryOver($conn, $student_id, $combination, $level, $semester);

$carryovers = [];
$current = [];
foreach ($all_courses as $c) {
    if (!empty($c['is_carryover'])) $carryovers[] = $c;
    else $current[] = $c;
}
?>

<div class="student-info-box">
    <h3>📋 <?php echo htmlspecialchars($student['fullname']); ?></h3>
    <p><strong>Reg No:</strong> <?php echo htmlspecialchars($student['reg_no'] ?? $student['student_id']); ?></p>
    <p><strong>Combination:</strong> <?php echo htmlspecialchars($combination); ?></p>
    <p><strong>Level:</strong> <?php echo htmlspecialchars($level); ?> | <strong>Semester:</strong> <?php echo htmlspecialchars($semester); ?></p>
    <p><strong>Session:</strong> <?php echo htmlspecialchars($session); ?></p>
    <?php if (count($carryovers) > 0): ?>
        <p style="color:#c62828; font-weight:700; margin-top:10px;">⚠️ Yana da <strong><?php echo count($carryovers); ?></strong> carry over!</p>
    <?php endif; ?>
</div>

<form method="POST">
    <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
    <input type="hidden" name="combination" value="<?php echo htmlspecialchars($combination); ?>">
    <input type="hidden" name="level" value="<?php echo htmlspecialchars($level); ?>">
    <input type="hidden" name="semester" value="<?php echo htmlspecialchars($semester); ?>">
    <input type="hidden" name="session" value="<?php echo htmlspecialchars($session); ?>">
    
    <?php if (count($carryovers) > 0): ?>
    <div class="carryover-box">
        <h3>⚠️ Carry Over Courses (Daga Semester ta Baya)</h3>
        <table class="result-table">
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Course Title</th>
                    <th>Units</th>
                    <th>Original Level</th>
                    <th>Original Session</th>
                    <th>Score</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($carryovers as $c): ?>
                <tr style="background:#ffebee;">
                    <td><strong><?php echo htmlspecialchars($c['course_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($c['course_title']); ?></td>
                    <td><?php echo $c['credit_units']; ?></td>
                    <td><?php echo htmlspecialchars($c['level']); ?></td>
                    <td><?php echo htmlspecialchars($c['session']); ?></td>
                    <td><input type="number" name="scores[<?php echo $c['course_code']; ?>]" class="score-input" min="0" max="100" value=""></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="color:#6a8f6a; font-size:0.85rem; margin-top:10px;">💡 Idan ya sake yin course ɗin kuma ya ci, za a sabunta sakamakon.</p>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <h3 style="color:#0d2818; margin-bottom:15px;">📚 Current Semester Courses</h3>
        <table class="result-table">
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Course Title</th>
                    <th>Units</th>
                    <th>Category</th>
                    <th>Score</th>
                    <th>Grade (Auto)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($current as $c): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($c['course_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($c['course_title']); ?></td>
                    <td><?php echo $c['credits']; ?></td>
                    <td><?php echo htmlspecialchars($c['category']); ?></td>
                    <td><input type="number" name="scores[<?php echo $c['course_code']; ?>]" class="score-input" min="0" max="100" value="" onchange="autoGrade(this)"></td>
                    <td><span class="grade-display">—</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <button type="submit" name="save_single" class="btn btn-green" style="font-size:1rem; padding:14px 40px;">
        <i class="fas fa-save"></i> Save All Results
    </button>
</form>

<script>
function autoGrade(input) {
    var score = parseInt(input.value) || 0;
    var grade = 'F'; var cls = 'grade-F';
    if (score >= 70) { grade = 'A'; cls = 'grade-A'; }
    else if (score >= 60) { grade = 'B'; cls = 'grade-B'; }
    else if (score >= 50) { grade = 'C'; cls = 'grade-C'; }
    else if (score >= 45) { grade = 'D'; cls = 'grade-D'; }
    else if (score >= 40) { grade = 'E'; cls = 'grade-E'; }
    var td = input.parentElement.nextElementSibling;
    td.innerHTML = '<span class="grade-badge ' + cls + '">' + grade + '</span>';
}
</script>