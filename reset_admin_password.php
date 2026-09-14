<?php
include 'connect.php';

$new_password = 'admin123';
$hashed = password_hash($new_password, PASSWORD_DEFAULT);

$update = "UPDATE staff SET `password` = '$hashed' WHERE username = 'admin'";

if (mysqli_query($conn, $update)) {
    echo "✅ Password ɗin admin ya koma: <strong>$new_password</strong><br>";
    echo "Affected rows: " . mysqli_affected_rows($conn);
} else {
    echo "❌ Error: " . mysqli_error($conn);
}
?>