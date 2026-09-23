<?php
// ============================================
// HAƊA DA DATABASE
// ============================================
$conn = mysqli_connect("localhost", "username", "password", "database_name");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// ============================================
// 1. NEMO DUK ɗALIBAN
// ============================================
$sql = "SELECT id, first_name, last_name, program, level, branch, course, reg_no 
        FROM applications 
        ORDER BY id ASC";
$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Kuskure: " . mysqli_error($conn));
}

// ============================================
// 2. TSAFTA (MAPPINGS)
// ============================================

// --- NCE Levels ---
$nce_level_map = [
    'NCE III' => '24',   // 2024/2025 -> 2026/2027
    'NCE II'  => '25',   // 2025/2026 -> 2027/2028
    'NCE I'   => '26',   // 2026/2027 -> 2028/2029
];

// --- DEGREE Levels ---
$deg_level_map = [
    '400 LEVEL' => '26', // 2026/2027 -> 2029/2030
    '500 LEVEL' => '27', // 2027/2028 -> 2030/2031
];

// --- Course Codes ---
$course_codes = [
    'ARB/ISS' => 'ARI',
    'ENG/ISS' => 'ENI',
    'PED'     => 'PED',
    'ENG/HAU' => 'ENH',
    'CSC/ISC' => 'CSI',
    'ENG/SOS' => 'ENS',
    'CSC/BIO' => 'CSB',
    'CSC/PHY' => 'CSP',
    'ENG/ECO' => 'ENE',
    'HAU/ENG' => 'HAE',
];

// ============================================
// 3. LISSAFTA DON KIRGA ADADIN
// ============================================
$admission_counter = [];
$dept_counter      = [];

// ============================================
// 4. FARA GYARA
// ============================================
$updated = 0;
$failed  = 0;
$skipped = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $id         = $row['id'];
    $old_reg    = $row['reg_no'];
    $program    = strtoupper(trim($row['program']));
    $level      = strtoupper(trim($row['level']));
    $branch     = strtoupper(trim($row['branch']));
    $course     = strtoupper(trim($row['course']));

    // --- Program Code ---
    if ($program == 'NCE') {
        $program_code = 'NCE';
    } elseif ($program == 'DEGREE' || $program == 'DEG') {
        $program_code = 'DEG';
    } else {
        echo "⚠️ ID $id: Program ɗin ba shi da inganci ($program). An tsallake.<br>";
        $failed++;
        continue;
    }

    // --- Year Code ---
    if ($program_code == 'NCE') {
        if (!array_key_exists($level, $nce_level_map)) {
            echo "⚠️ ID $id: NCE Level ɗin ba shi da inganci ($level). An tsallake.<br>";
            $failed++;
            continue;
        }
        $year_code = $nce_level_map[$level];
    } else { // DEGREE
        if (!array_key_exists($level, $deg_level_map)) {
            echo "⚠️ ID $id: Degree Level ɗin ba shi da inganci ($level). An tsallake.<br>";
            $failed++;
            continue;
        }
        $year_code = $deg_level_map[$level];
    }

    // --- Branch ---
    if (!in_array($branch, ['A', 'B', 'C'])) {
        echo "⚠️ ID $id: Branch ɗin ba shi da inganci ($branch). An tsallake.<br>";
        $failed++;
        continue;
    }

    // --- Course Code ---
    if (!array_key_exists($course, $course_codes)) {
        echo "⚠️ ID $id: Course ɗin ba shi da inganci ($course). An tsallake.<br>";
        $failed++;
        continue;
    }
    $course_code = $course_codes[$course];

    // --- Admission Number (GA GENERAL) ---
    $adm_key = $program_code . '-' . $year_code;
    if (!isset($admission_counter[$adm_key])) {
        $admission_counter[$adm_key] = 0;
    }
    $admission_counter[$adm_key]++;
    $admission_num = str_pad($admission_counter[$adm_key], 3, '0', STR_PAD_LEFT);

    // --- Department Number ---
    $dept_key = $program_code . '-' . $year_code . '-' . $course_code;
    if (!isset($dept_counter[$dept_key])) {
        $dept_counter[$dept_key] = 0;
    }
    $dept_counter[$dept_key]++;
    $dept_num = str_pad($dept_counter[$dept_key], 3, '0', STR_PAD_LEFT);

    // --- Haɗa Reg No ---
    $new_reg_no = "DLCOE/" . $program_code . "/" . $year_code . $branch . $admission_num . "/" . $course_code . $dept_num;

    // --- Idan reg no ɗin bai canza ba, tsallake ---
    if ($old_reg == $new_reg_no) {
        echo "⏭️ ID $id: Bai canza ba ($new_reg_no)<br>";
        $skipped++;
        continue;
    }

    // --- Sabunta Database ---
    $update_sql = "UPDATE applications 
                   SET reg_no = '$new_reg_no' 
                   WHERE id = $id";
    
    if (mysqli_query($conn, $update_sql)) {
        echo "✅ ID $id: $old_reg → <strong>$new_reg_no</strong><br>";
        $updated++;
    } else {
        echo "❌ ID $id: Kuskure - " . mysqli_error($conn) . "<br>";
        $failed++;
    }
}

// ============================================
// 5. RAPPORT
// ============================================
echo "<hr>";
echo "<strong>An gyara: $updated</strong><br>";
echo "<strong>An tsallake (bai canza ba): $skipped</strong><br>";
echo "<strong>An kasa: $failed</strong><br>";

mysqli_close($conn);
?>