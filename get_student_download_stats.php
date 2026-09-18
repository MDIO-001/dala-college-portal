<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false]);
    exit();
}

// ============================================
// STATS: DOWNLOADS
// ============================================
$stats = [];

$doc_types = [
    'id_card' => 'ID Card',
    'exam_card' => 'Exam Card',
    'result' => 'Result',
    'admission_letter' => 'Admission Letter',
    'acceptance_letter' => 'Acceptance Letter',
    'introductory_letter' => 'Introductory Letter',
    'posting_letter' => 'Posting Letter',
    'tp_result' => 'T.P Result'
];

foreach ($doc_types as $key => $label) {
    $q = mysqli_query($conn, "SELECT COUNT(*) as c FROM download_logs WHERE document_type = '$key'");
    $stats[$key] = [
        'label' => $label,
        'count' => mysqli_fetch_assoc($q)['c'] ?? 0
    ];
}

// ============================================
// STATS: NAME CHANGES
// ============================================
$nc_query = mysqli_query($conn, "SELECT COUNT(*) as c FROM name_change_logs");
$name_changes = mysqli_fetch_assoc($nc_query)['c'] ?? 0;

// ============================================
// STATS: RECENT DOWNLOADS
// ============================================
$recent_query = mysqli_query($conn, "
    SELECT dl.*, s.fullname, s.reg_no 
    FROM download_logs dl 
    LEFT JOIN students s ON s.id = dl.student_id 
    ORDER BY dl.id DESC LIMIT 10
");
$recent = [];
while ($row = mysqli_fetch_assoc($recent_query)) {
    $recent[] = $row;
}

// ============================================
// STATS: RECENT NAME CHANGES
// ============================================
$recent_nc_query = mysqli_query($conn, "
    SELECT nc.*, s.reg_no 
    FROM name_change_logs nc 
    LEFT JOIN students s ON s.id = nc.student_id 
    ORDER BY nc.id DESC LIMIT 10
");
$recent_nc = [];
while ($row = mysqli_fetch_assoc($recent_nc_query)) {
    $recent_nc[] = $row;
}

echo json_encode([
    'success' => true,
    'downloads' => $stats,
    'name_changes' => $name_changes,
    'recent_downloads' => $recent,
    'recent_name_changes' => $recent_nc
]);
?>