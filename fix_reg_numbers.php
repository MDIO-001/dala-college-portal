<?php
// ============================================
// GYARA DUK DUPLICATES A REG NO
// ============================================
include 'connect.php';

echo "<h2>Gyara Duplicates a Reg No</h2>";

// ============================================
// 1. NEMO DUK DUPLICATES
// ============================================
$dup_query = "SELECT reg_no, COUNT(*) AS total, GROUP_CONCAT(id ORDER BY id ASC) AS ids
              FROM students 
              WHERE reg_no IS NOT NULL AND reg_no != ''
              GROUP BY reg_no 
              HAVING total > 1 
              ORDER BY reg_no ASC";
$dup_result = mysqli_query($conn, $dup_query);

if (!$dup_result) die("Query error: " . mysqli_error($conn));

$total_duplicates = mysqli_num_rows($dup_result);

if ($total_duplicates == 0) {
    echo "<p style='color:green; font-size:1.2rem;'>✅ Babu duplicates! Komai yana daidai.</p>";
    mysqli_close($conn);
    exit();
}

echo "<p style='color:#c62828; font-size:1.1rem;'>⚠️ An sami <strong>$total_duplicates</strong> reg numbers da suka maimaita.</p>";

// ============================================
// 2. GYARA KOWANNE DUPLICATE
// ============================================
$fixed = 0;
$errors = [];

echo "<h3>Gyare-gyaren:</h3>";
echo "<pre style='background:#f8faf8; padding:15px; border-radius:8px; max-height:600px; overflow-y:auto;'>";

while ($dup = mysqli_fetch_assoc($dup_result)) {
    $reg_no = $dup['reg_no'];
    $ids = explode(',', $dup['ids']);
    
    // Bar na farko (id na farko) a matsayinsa
    // Gyara sauran
    $first_id = array_shift($ids);
    
    // Fitar da sassan reg_no
    // Tsarin: DLCOE/NCE/26C246/ARI001
    if (!preg_match('/^(DLCOE\/[A-Z]+)\/(\d{2}[A-Z])(\d+)\/([A-Z]{3})(\d+)$/', $reg_no, $matches)) {
        echo "⚠️ Reg No ba daidai ba: $reg_no<br>";
        continue;
    }
    
    $prefix = $matches[1];        // DLCOE/NCE
    $year_branch = $matches[2];   // 26C
    $adm_num = $matches[3];       // 246
    $course_code = $matches[4];   // ARI
    $dept_num = intval($matches[5]); // 1
    
    // Nemo mafi girman department number da aka yi amfani da shi a wannan course
    $max_query = "SELECT reg_no FROM students 
                  WHERE reg_no LIKE '$prefix/$year_branch%/$course_code%' 
                  AND reg_no NOT IN (SELECT reg_no FROM (SELECT reg_no FROM students GROUP BY reg_no HAVING COUNT(*) > 1) AS t)";
    $max_result = mysqli_query($conn, $max_query);
    $max_dept = $dept_num;
    if ($max_result) {
        while ($row = mysqli_fetch_assoc($max_result)) {
            if (preg_match('/' . preg_quote($course_code, '/') . '(\d+)$/', $row['reg_no'], $m)) {
                $num = intval($m[1]);
                if ($num > $max_dept) $max_dept = $num;
            }
        }
    }
    
    // Gyara kowanne ɗalibin da ya maimaita (baya ga na farko)
    foreach ($ids as $id) {
        $max_dept++;
        $new_dept = str_pad($max_dept, 3, '0', STR_PAD_LEFT);
        $new_reg = "$prefix/$year_branch$adm_num/$course_code$new_dept";
        
        // Tabbatar sabon reg_no bai wanzu ba
        $check = mysqli_query($conn, "SELECT id FROM students WHERE reg_no = '$new_reg' AND id != $id");
        if ($check && mysqli_num_rows($check) > 0) {
            // Idan ya wanzu, ci gaba da ƙara
            $max_dept++;
            $new_dept = str_pad($max_dept, 3, '0', STR_PAD_LEFT);
            $new_reg = "$prefix/$year_branch$adm_num/$course_code$new_dept";
        }
        
        // Sabunta
        $update = "UPDATE students SET reg_no = '$new_reg' WHERE id = $id";
        if (mysqli_query($conn, $update)) {
            echo "✅ ID $id: $reg_no → $new_reg<br>";
            $fixed++;
        } else {
            echo "❌ ID $id: " . mysqli_error($conn) . "<br>";
            $errors[] = "ID $id: " . mysqli_error($conn);
        }
    }
}

echo "</pre>";
echo "<hr><h3>An gyara: <strong style='color:#2e7d32;'>$fixed</strong></h3>";

// ============================================
// 3. DUBA SAKAMAKON
// ============================================
echo "<h3>Duba Duplicates Bayan Gyara:</h3>";
$check = mysqli_query($conn, "SELECT reg_no, COUNT(*) AS total 
                              FROM students 
                              WHERE reg_no IS NOT NULL AND reg_no != ''
                              GROUP BY reg_no 
                              HAVING total > 1");
if ($check && mysqli_num_rows($check) > 0) {
    echo "<p style='color:#c62828;'>⚠️ Har yanzu akwai duplicates:</p>";
    echo "<table border='1' cellpadding='8' style='color:red;'>";
    echo "<tr><th>Reg No</th><th>Count</th></tr>";
    while ($r = mysqli_fetch_assoc($check)) {
        echo "<tr><td>{$r['reg_no']}</td><td>{$r['total']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:green; font-size:1.2rem;'>✅ Babu duplicates! Komai yana daidai.</p>";
}

// ============================================
// 4. DUBA ADADIN KOWACE SHEKARA
// ============================================
echo "<h3>Adadin Kowace Shekara:</h3>";
$check2 = mysqli_query($conn, "SELECT level, SUBSTRING(reg_no, 11, 2) AS year_code, COUNT(*) AS total 
                               FROM students 
                               WHERE reg_no LIKE 'DLCOE/%'
                               GROUP BY level, year_code 
                               ORDER BY level, year_code");
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>Level</th><th>Year Code</th><th>Total</th></tr>";
while ($r = mysqli_fetch_assoc($check2)) {
    echo "<tr><td>{$r['level']}</td><td>{$r['year_code']}</td><td>{$r['total']}</td></tr>";
}
echo "</table>";

mysqli_close($conn);
?>