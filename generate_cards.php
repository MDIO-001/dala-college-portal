<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Generate Cards</h2>";

include 'connect.php';

if (!$conn) {
    die("❌ Connection failed: " . mysqli_connect_error());
}
echo "✅ Connected to database<br>";

$count_before = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM scratch_cards"))['total'];
echo "📊 Cards kafin: <strong>$count_before</strong><br><br>";

// ============================================
// SAMAR DA CARDS 1000
// ============================================
$start_serial = 1001;
$total_cards = 1000;

$inserted = 0;
$skipped = 0;
$errors = 0;

for ($i = 0; $i < $total_cards; $i++) {
    $serial1 = $start_serial + $i;
    $serial2 = $start_serial + 1000 + $i;
    $pin = $start_serial + 2099 + $i;
    
    $card_code = "APL-{$serial1}-{$serial2}-{$pin}";
    $pin_str = (string)$pin;
    
    $check = mysqli_query($conn, "SELECT id FROM scratch_cards WHERE card_code = '$card_code'");
    if ($check && mysqli_num_rows($check) > 0) {
        $skipped++;
        continue;
    }
    
    $insert = "INSERT INTO scratch_cards (card_code, pin, serial_no) VALUES ('$card_code', '$pin_str', '$serial1')";
    if (mysqli_query($conn, $insert)) {
        $inserted++;
    } else {
        $errors++;
    }
}

$count_after = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM scratch_cards"))['total'];

echo "<div style='background:#e8f5e9; padding:20px; border-radius:8px; border-left:4px solid #2e7d32;'>";
echo "<h3>✅ An Gama!</h3>";
echo "<p><strong>An saka:</strong> $inserted</p>";
echo "<p><strong>An tsallake:</strong> $skipped</p>";
echo "<p><strong>Kuskure:</strong> $errors</p>";
echo "<p><strong>Jimilla (kafin):</strong> $count_before</p>";
echo "<p><strong>Jimilla (bayan):</strong> $count_after</p>";
echo "</div>";

echo "<br><a href='admin_dashboard.php' style='padding:10px 20px; background:#2e7d32; color:white; text-decoration:none; border-radius:8px;'>Koma Dashboard</a>";
?>