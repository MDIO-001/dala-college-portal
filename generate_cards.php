<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Generate Cards</h1>";

include 'connect.php';

if (!$conn) {
    die("❌ Connection failed: " . mysqli_connect_error());
}
echo "✅ Connected to database<br>";

$count_before = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM scratch_cards"))['total'];
echo "📊 Cards kafin: <strong>$count_before</strong><br><br>";

$card_types = [
    'application'         => ['prefix' => 'APL', 'start' => 1001,  'count' => 500, 'label' => 'Application'],
    'course_registration' => ['prefix' => 'CRG', 'start' => 5001,  'count' => 500, 'label' => 'Course Registration'],
    'exam_card'           => ['prefix' => 'EXM', 'start' => 7001,  'count' => 500, 'label' => 'Exam Card'],
    'posting_letter'      => ['prefix' => 'PST', 'start' => 9001,  'count' => 500, 'label' => 'Posting Letter (T.P)'],
    'acceptance_letter'   => ['prefix' => 'ACL', 'start' => 11001, 'count' => 500, 'label' => 'Acceptance Letter'],
    'receipt'             => ['prefix' => 'RCP', 'start' => 13001, 'count' => 500, 'label' => 'Print Receipt'],
];

$expiry_date = date('Y-m-d', strtotime('+6 months'));
$usage_limit = 10;

$grand_total = 0;

foreach ($card_types as $type => $config) {
    $prefix = $config['prefix'];
    $start = $config['start'];
    $count = $config['count'];
    $label = $config['label'];
    
    $inserted = 0;
    $skipped = 0;
    $errors = 0;
    
    for ($i = 0; $i < $count; $i++) {
        $serial1 = $start + $i;
        $serial2 = $start + 1000 + $i;
        $pin = $start + 2099 + $i;
        
        $card_code = "{$prefix}-{$serial1}-{$serial2}-{$pin}";
        $pin_str = (string)$pin;
        
        $check = mysqli_query($conn, "SELECT id FROM scratch_cards WHERE card_code = '$card_code'");
        if ($check && mysqli_num_rows($check) > 0) {
            $skipped++;
            continue;
        }
        
        $insert = "INSERT INTO scratch_cards 
                   (card_code, pin, card_type, serial_no, status, is_active, usage_limit, usage_count, expiry_date) 
                   VALUES ('$card_code', '$pin_str', '$type', '$serial1', 'unused', 1, $usage_limit, 0, '$expiry_date')";
        if (mysqli_query($conn, $insert)) {
            $inserted++;
        } else {
            $errors++;
        }
    }
    
    echo "<p><strong>$label:</strong> An saka $inserted | An tsallake $skipped | Kuskure $errors</p>";
    $grand_total += $inserted;
}

$count_after = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM scratch_cards"))['total'];

echo "<hr>";
echo "<h2>✅ An Gama!</h2>";
echo "<p><strong>Jimilla da aka saka:</strong> $grand_total</p>";
echo "<p><strong>Jimilla a cikin table:</strong> $count_after</p>";
echo "<br><a href='admin_scratch_cards.php'>Koma Dashboard</a>";
?>