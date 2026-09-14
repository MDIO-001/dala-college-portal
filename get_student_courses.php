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

// Samo courses
$courses = [];
$cq = mysqli_query($conn, "SELECT * FROM courses 
                           WHERE combination = '$combination' 
                           AND level = '$level' 
                           AND semester = '$semester'
                           AND status = 'Compulsory'
                           ORDER BY FIELD(category, 'EDU', 'GSE', 'PED', 'CSC', 'BIO', 'ISC', 'PHY', 'ENG', 'ECO', 'ARB', 'ISS', 'HAU', 'SOS'), 
                           course_code");
while ($c = mysqli_fetch_assoc($cq)) $courses[] = $c;

// Samo existing results
$existing_results = [];
$rq = mysqli_query($conn, "SELECT * FROM results 
                           WHERE student_id = $student_id 
                           AND level = '$level' 
                           AND semester = '$semester' 
                           AND session = '$session'");
while ($r = mysqli_fetch_assoc($rq)) {
    $existing_results[$r['course_code']] = $r;
}

// Samo carry overs
$carryovers = [];
$co_result = getCarryOverCourses($conn, $student_id, $combination);
while ($co = mysqli_fetch_assoc($co_result)) {
    $carryovers[] = $co;
}
?>

<div class="student-info-box">
    <h3>📋 <?php echo htmlspecialchars($student['fullname']); ?></h3>
    <p><strong>Reg No:</strong> <?php echo htmlspecialchars($student['reg_no'] ?? $student['student_id']); ?></p>
    <p><strong>Combination:</strong> <?php echo htmlspecialchars($combination); ?></p>
    <p><strong>Level:</strong> <?php echo htmlspecialchars($level); ?> | <strong>Semester:</strong> <?php echo htmlspecialchars($semester); ?></p>
    <p><strong>Session:</strong> <?php echo htmlspecialchars($session); ?></p>
    <?php if (count($carryovers) > 0): ?>
        <p style="color:#c62828; font-weight:700; margin-top:10px;">⚠️ Carry Over: <strong><?php echo count($carryovers); ?></strong></p>
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
                <tr style="background:#ffebee;">
                    <td><strong><?php echo htmlspecialchars($c['course_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($c['course_title']); ?></td>
                    <td><?php echo $c['credit_units']; ?></td>
                    <td><?php echo htmlspecialchars($c['session']); ?></td>
                    <td>
                        <input type="number" name="scores[<?php echo $c['course_code']; ?>]" 
                               class="score-input" min="0" max="100" value=""
                               onchange="autoGrade(this); calculateTotal();">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
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
                    <th>Score</th>
                    <th>Grade</th>
                    <th>Point</th>
                    <th>Remark</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $c): 
                    $existing_score = $existing_results[$c['course_code']]['score'] ?? '';
                    $existing_grade = $existing_results[$c['course_code']]['grade'] ?? '—';
                    $existing_point = $existing_results[$c['course_code']]['grade_point'] ?? 0;
                    $existing_remark = $existing_results[$c['course_code']]['remark'] ?? '—';
                ?>
                <tr data-course="<?php echo htmlspecialchars($c['course_code']); ?>" data-credits="<?php echo $c['credits']; ?>">
                    <td><strong><?php echo htmlspecialchars($c['course_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($c['course_title']); ?></td>
                    <td class="center"><?php echo $c['credits']; ?></td>
                    <td class="center">
                        <input type="number" name="scores[<?php echo $c['course_code']; ?>]" 
                               class="score-input" min="0" max="100" 
                               value="<?php echo $existing_score; ?>"
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
    
    <!-- TOTAL SUMMARY -->
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
    
    <button type="submit" name="save_single" class="btn btn-green" style="font-size:1rem; padding:14px 40px; margin-top:15px;">
        <i class="fas fa-save"></i> Save All Results
    </button>
</form>

<script>
function autoGrade(input) {
    var score = parseInt(input.value) || 0;
    var grade = 'F'; var cls = 'grade-F'; var point = 0.0; var remark = 'Fail';
    
    if (score >= 70) { grade = 'A'; cls = 'grade-A'; point = 4.0; remark = 'Distinction'; }
    else if (score >= 60) { grade = 'B'; cls = 'grade-B'; point = 3.0; remark = 'Credit'; }
    else if (score >= 50) { grade = 'C'; cls = 'grade-C'; point = 2.0; remark = 'Merit'; }
    else if (score >= 45) { grade = 'D'; cls = 'grade-D'; point = 1.0; remark = 'Pass'; }
    else if (score >= 40) { grade = 'E'; cls = 'grade-E'; point = 0.5; remark = 'Lower Pass'; }
    
    var tr = input.closest('tr');
    tr.querySelector('.grade-display').innerHTML = '<span class="grade-badge ' + cls + '">' + grade + '</span>';
    tr.querySelector('.point-display').textContent = point.toFixed(1);
    tr.querySelector('.remark-display').textContent = remark;
    tr.dataset.point = point;
}

function calculateTotal() {
    var totalUnits = 0;
    var totalPoints = 0;
    
    document.querySelectorAll('tr[data-course]').forEach(function(tr) {
        var score = tr.querySelector('.score-input').value;
        var credits = parseInt(tr.dataset.credits) || 0;
        var point = parseFloat(tr.dataset.point) || 0;
        
        if (score !== '' && point > 0) {
            totalUnits += credits;
            totalPoints += point * credits;
        }
    });
    
    document.getElementById('totalUnits').textContent = totalUnits;
    var gpa = totalUnits > 0 ? (totalPoints / totalUnits) : 0;
    document.getElementById('gpa').textContent = gpa.toFixed(2);
}

// Run on page load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.score-input').forEach(function(input) {
        if (input.value !== '') {
            autoGrade(input);
        }
    });
    calculateTotal();
});
</script>

<style>
.student-info-box { background:linear-gradient(135deg, #e8f5e9, #c8e6c9); padding:20px; border-radius:10px; margin-bottom:20px; border-left:5px solid #2e7d32; }
.student-info-box h3 { color:#0d2818; margin-bottom:10px; }
.student-info-box p { color:#1a2e1a; margin:5px 0; font-size:0.9rem; }
.carryover-box { background:#ffebee; padding:20px; border-radius:10px; margin-bottom:20px; border-left:5px solid #c62828; }
.carryover-box h3 { color:#c62828; margin-bottom:15px; }
.result-table { width:100%; border-collapse:collapse; margin-bottom:15px; }
.result-table th { background:#0d2818; color:white; padding:10px; text-align:left; font-size:0.8rem; }
.result-table td { padding:10px; border-bottom:1px solid #eee; font-size:0.9rem; }
.result-table tr:hover td { background:#f8faf8; }
.result-table .center { text-align:center; }
.score-input { width:80px; padding:8px; border:2px solid #dce8dc; border-radius:6px; text-align:center; font-weight:700; font-size:0.95rem; }
.score-input:focus { border-color:#2e7d32; outline:none; }
.grade-badge { padding:4px 12px; border-radius:15px; font-weight:700; font-size:0.85rem; }
.grade-A { background:#e8f5e9; color:#2e7d32; }
.grade-B { background:#e3f2fd; color:#0d47a1; }
.grade-C { background:#fff3e0; color:#e65100; }
.grade-D { background:#f3e5f5; color:#6a1b9a; }
.grade-E { background:#fce4ec; color:#c2185b; }
.grade-F { background:#ffebee; color:#c62828; }
.total-summary { display:grid; grid-template-columns:repeat(3, 1fr); gap:15px; margin-top:20px; margin-bottom:15px; }
.total-summary .summary-box { border:2px solid #0d2818; padding:15px; text-align:center; border-radius:8px; background:#f8faf8; }
.total-summary .summary-box.gpa { border-color:#2e7d32; background:#e8f5e9; }
.total-summary .summary-box .label { font-size:0.75rem; text-transform:uppercase; color:#6a8f6a; font-weight:700; }
.total-summary .summary-box .value { font-size:1.8rem; font-weight:900; color:#0d2818; line-height:1; margin-top:5px; }
.total-summary .summary-box.gpa .value { color:#2e7d32; }
</style>