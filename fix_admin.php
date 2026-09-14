<?php
include 'connect.php';

$new_password = 'admin123';
$hashed = password_hash($new_password, PASSWORD_DEFAULT);

echo "<h2>Fix Admin Password</h2>";
echo "<p>Sabon password: <strong>$new_password</strong></p>";
echo "<p>Sabon hash: <code style='font-size:11px;'>$hashed</code></p>";

// Sabunta admin
$update = "UPDATE staff SET `password` = '$hashed' WHERE username = 'admin'";

if (mysqli_query($conn, $update)) {
    $rows = mysqli_affected_rows($conn);
    echo "<p style='color:green; font-weight:bold;'>✅ An canza password! (rows: $rows)</p>";
} else {
    echo "<p style='color:red;'>❌ Error: " . mysqli_error($conn) . "</p>";
}

// Nuna admin
$result = mysqli_query($conn, "SELECT id, username, fullname, role, `password` FROM staff WHERE username = 'admin'");
if ($row = mysqli_fetch_assoc($result)) {
    echo "<hr>";
    echo "<h3>Admin ɗin yanzu:</h3>";
    echo "ID: {$row['id']}<br>";
    echo "Username: <strong>{$row['username']}</strong><br>";
    echo "Fullname: {$row['fullname']}<br>";
    echo "Role: <strong>{$row['role']}</strong><br>";
    echo "Password (a database): <code style='font-size:11px;'>" . substr($row['password'], 0, 40) . "...</code><br>";
    
    // Gwada password_verify
    echo "<hr>";
    echo "<h3>Gwada password_verify():</h3>";
    if (password_verify('admin123', $row['password'])) {
        echo "<p style='color:green; font-weight:bold;'>✅ password_verify YANA AIKI! Login zai yi aiki.</p>";
    } else {
        echo "<p style='color:red; font-weight:bold;'>❌ password_verify BA YA AIKI! Sabunta bai yi ba.</p>";
    }
} else {
    echo "<p style='color:red;'>❌ Babu admin da username = 'admin'</p>";
}
?>