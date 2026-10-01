<?php
session_start();
include 'connect.php';
include 'payment_gate.php';

// Nemo Bilal
$bilal = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, fullname, username FROM students WHERE username = 'bilal@123'"));

if (!$bilal) {
    die("Bilal not found");
}

echo "<h2>Testing hasPaidItem for Bilal (ID: {$bilal['id']})</h2>";

// Duba TUI
$has_tui = hasPaidItem($conn, $bilal['id'], 'TUI');
echo "<p><strong>Has paid TUI:</strong> " . ($has_tui ? "✅ YES" : "❌ NO") . "</p>";

// Duba payment ɗin Bilal
$payment = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT p.*, pi.item_code, pi.item_name 
    FROM payments p
    LEFT JOIN payment_items pi ON p.payment_item_id = pi.id
    WHERE p.student_id = {$bilal['id']} 
    ORDER BY p.id DESC 
    LIMIT 5
"));

echo "<h3>Last Payment:</h3>";
echo "<pre>";
print_r($payment);
echo "</pre>";

// Duba TUI item
$tui = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM payment_items WHERE item_code = 'TUI'"));
echo "<h3>TUI Item:</h3>";
echo "<pre>";
print_r($tui);
echo "</pre>";
?>