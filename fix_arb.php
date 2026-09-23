<?php
include 'connect.php';

echo "<h2>Gyara ARB/ISS</h2><pre>";

// Nemo duk ARB/ISS
$query = "SELECT id, reg_no, fullname, level, branch_code 
          FROM students 
          WHERE (course = 'ARB/ISS' OR combination = 'ARB/ISS')
          AND reg_no LIKE 'DLCOE/NCE/26%'
          ORDER BY id ASC";
$result = mysqli_query($conn, $query);

if (!$result) die("Query error: " . mysqli_error($conn));

$count = 0;
$counter = 1;

while ($row = mysqli_fetch_assoc($result)) {
    $id = $row['id'];
    $old_reg = $row['reg_no'];
    $counter_str = str_pad($counter, 3, '0', STR_PAD_LEFT);
    
    // Sabon reg_no
    $new_reg = "DLCOE/NCE/26C246/ARI$counter_str";
    
    // Sabunta
    $update = "UPDATE students SET reg_no = '$new_reg' WHERE id = $id";
    if (mysqli_query($conn, $update)) {
        echo "✅ ID $id ({$row['fullname']}): $old_reg → $new_reg<br>";
        $count++;
    } else {
        echo "❌ ID $id: " . mysqli_error($conn) . "<br>";
    }
    
    $counter++;
}

echo "</pre><hr><h3>An gyara: $count</h3>";

// Duba sakamakon
echo "<h3>ARB/ISS Bayan Gyara:</h3>";
$check = mysqli_query($conn, "SELECT id, reg_no, fullname 
                              FROM students 
                              WHERE (course = 'ARB/ISS' OR combination = 'ARB/ISS')
                              AND reg_no LIKE 'DLCOE/NCE/26%'
                              ORDER BY reg_no ASC");
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>ID</th><th>Reg No</th><th>Full Name</th></tr>";
while ($r = mysqli_fetch_assoc($check)) {
    echo "<tr><td>{$r['id']}</td><td>{$r['reg_no']}</td><td>{$r['fullname']}</td></tr>";
}
echo "</table>";

mysqli_close($conn);
?>