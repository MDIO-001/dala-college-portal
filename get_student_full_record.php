// ============================================
// 1. ADMISSION LETTER
// ============================================
$adm_query = "SELECT * FROM student_documents WHERE student_id = $student_id AND document_type = 'admission_letter' LIMIT 1";
$adm_result = mysqli_query($conn, $adm_query);
$admission_letter = mysqli_fetch_assoc($adm_result);

// ============================================
// 2. ACCEPTANCE LETTER
// ============================================
$acc_query = "SELECT * FROM student_documents WHERE student_id = $student_id AND document_type = 'acceptance_letter' LIMIT 1";
$acc_result = mysqli_query($conn, $acc_query);
$acceptance_letter = mysqli_fetch_assoc($acc_result);

// ============================================
// 3. INTRODUCTORY LETTER
// ============================================
$intro_query = "SELECT * FROM student_documents WHERE student_id = $student_id AND document_type = 'introductory_letter' LIMIT 1";
$intro_result = mysqli_query($conn, $intro_query);
$introductory_letter = mysqli_fetch_assoc($intro_result);

// ============================================
// 4. POSTING LETTER
// ============================================
$post_query = "SELECT * FROM student_documents WHERE student_id = $student_id AND document_type = 'posting_letter' LIMIT 1";
$post_result = mysqli_query($conn, $post_query);
$posting_letter = mysqli_fetch_assoc($post_result);

// ============================================
// 5. EXAM CARD
// ============================================
$exam_query = "SELECT COUNT(*) as total FROM course_registrations 
               WHERE student_id = $student_id AND status != 'dropped'";
$exam_result = mysqli_query($conn, $exam_query);
$exam_count = mysqli_fetch_assoc($exam_result)['total'];
$has_exam_card = ($exam_count > 0);

// ============================================
// 6. RESULTS
// ============================================
$res_query = "SELECT COUNT(*) as total FROM results 
              WHERE student_id = $student_id AND status IN ('approved', 'published')";
$res_result = mysqli_query($conn, $res_query);
$result_count = mysqli_fetch_assoc($res_result)['total'];
$has_results = ($result_count > 0);

// ============================================
// 7. T.P RESULT
// ============================================
$tp_query = "SELECT * FROM tp_results WHERE student_id = $student_id ORDER BY id DESC LIMIT 1";
$tp_result = mysqli_query($conn, $tp_query);
$tp = mysqli_fetch_assoc($tp_result);

// ============================================
// 8. ID CARD
// ============================================
$idcard_query = "SELECT * FROM id_cards WHERE student_id = $student_id ORDER BY id DESC LIMIT 1";
$idcard_result = mysqli_query($conn, $idcard_query);
$idcard = mysqli_fetch_assoc($idcard_result);

// ============================================
// 9. SCRATCH CARDS
// ============================================
$sc_query = "SELECT * FROM scratch_cards WHERE student_id = $student_id ORDER BY id DESC";
$sc_result = mysqli_query($conn, $sc_query);
$scratch_cards = [];
if ($sc_result) {
    while ($row = mysqli_fetch_assoc($sc_result)) {
        $scratch_cards[] = $row;
    }
}

// ============================================
// 10. SESSION & LEVEL HISTORY
// ============================================
$sess_query = "SELECT * FROM student_sessions WHERE student_id = $student_id ORDER BY id DESC";
$sess_result = mysqli_query($conn, $sess_query);
$sessions = [];
if ($sess_result) {
    while ($row = mysqli_fetch_assoc($sess_result)) {
        $sessions[] = $row;
    }
}

// ============================================
// 11. COURSE REGISTRATIONS
// ============================================
$reg_query = "SELECT level, semester, academic_year, COUNT(*) as total_courses, SUM(credits) as total_units
              FROM course_registrations 
              WHERE student_id = $student_id AND status != 'dropped'
              GROUP BY level, semester, academic_year
              ORDER BY academic_year DESC, level, semester";
$reg_result = mysqli_query($conn, $reg_query);
$registrations = [];
if ($reg_result) {
    while ($row = mysqli_fetch_assoc($reg_result)) {
        $registrations[] = $row;
    }
}

// ============================================
// 12. TOTAL PRINTED (Idan kana son ka lissafta)
// ============================================
// A nan muna lissafta duk abin da aka buga (idan akwai tracking table)
// A halin yanzu muna amfani da id_cards.printed_count
$total_printed = [
    'id_card' => $idcard['printed_count'] ?? 0,
    'exam_card' => 0, // Idan babu tracking, zai zama 0
    'tp_result' => 0,
    'admission_letter' => 0,
    'acceptance_letter' => 0,
    'introductory_letter' => 0,
    'posting_letter' => 0
];

// ============================================
// RETURN JSON
// ============================================
echo json_encode([
    'success' => true,
    'student' => [
        'id' => $student['id'],
        'reg_no' => $student['reg_no'] ?? $student['student_id'] ?? 'N/A',
        'fullname' => $student['fullname'] ?? '',
        'email' => $student['email'] ?? '',
        'phone' => $student['phone'] ?? '',
        'combination' => $student['combination'] ?? $student['course'] ?? '',
        'programme' => $student['programme'] ?? 'NCE',
        'level' => $student['level'] ?? 'NCE I',
        'status' => $student['status'] ?? 'pending',
        'branch_code' => $student['branch_code'] ?? 'SHINGE',
        'photo' => $student['photo'] ?? null
    ],
    'documents' => [
        'admission_letter' => $admission_letter ?: null,
        'acceptance_letter' => $acceptance_letter ?: null,
        'introductory_letter' => $introductory_letter ?: null,
        'posting_letter' => $posting_letter ?: null
    ],
    'exam_card' => [
        'has_exam_card' => $has_exam_card,
        'total_registered_courses' => $exam_count
    ],
    'results' => [
        'has_results' => $has_results,
        'total_results' => $result_count
    ],
    'tp_result' => $tp ?: null,
    'id_card' => $idcard ?: null,
    'scratch_cards' => $scratch_cards,
    'sessions' => $sessions,
    'registrations' => $registrations,
    'total_printed' => $total_printed
]);
?>