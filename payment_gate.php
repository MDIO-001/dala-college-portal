<?php
// ============================================
// PAYMENT GATE - Dala College (VERSION 5)
// ============================================

if (!function_exists('hasPaidItem')) {
    function hasPaidItem($conn, $student_id, $item_code) {
        if (empty($student_id) || !is_numeric($student_id) || $student_id <= 0) {
            return false;
        }
        
        $student_id = intval($student_id);
        
        if (empty($item_code) || !is_string($item_code)) {
            return true;
        }
        
        $item_code = strtoupper(trim($item_code));
        $item_code_esc = mysqli_real_escape_string($conn, $item_code);
        
        // Nemo level da programme ɗin ɗalibi
        $student_query = mysqli_query($conn, "SELECT level, programme FROM students WHERE id = $student_id LIMIT 1");
        if (!$student_query || mysqli_num_rows($student_query) == 0) {
            return false;
        }
        $student = mysqli_fetch_assoc($student_query);
        $student_level = $student['level'] ?? '';
        
        // Nemo duk items ɗin da suka dace
        $item_query = mysqli_query($conn, "
            SELECT id, item_name, amount, level 
            FROM payment_items 
            WHERE (item_code = '$item_code_esc' 
                   OR item_code LIKE '$item_code_esc-%')
            AND is_active = 1
        ");
        
        if (!$item_query || mysqli_num_rows($item_query) == 0) {
            return true;
        }
        
        $item_ids = [];
        $item_names = [];
        $item_amounts = [];
        
        while ($row = mysqli_fetch_assoc($item_query)) {
            // Idan item ɗin yana da level, dole ya dace
            if (!empty($row['level']) && $row['level'] != $student_level) {
                continue;
            }
            
            $item_ids[] = intval($row['id']);
            $item_names[] = mysqli_real_escape_string($conn, $row['item_name']);
            $item_amounts[] = floatval($row['amount']);
        }
        
        if (empty($item_ids)) {
            return true;
        }
        
        $ids_str = implode(',', $item_ids);
        $names_str = "'" . implode("','", array_unique($item_names)) . "'";
        
        // HANYA TA 1: DUBA TA payment_item_id
        $query1 = mysqli_query($conn, "
            SELECT id FROM payments 
            WHERE student_id = $student_id 
            AND payment_item_id IN ($ids_str) 
            AND status = 'paid' 
            LIMIT 1
        ");
        
        if ($query1 && mysqli_num_rows($query1) > 0) {
            return true;
        }
        
        // HANYA TA 2: DUBA TA payment_type
        $query2 = mysqli_query($conn, "
            SELECT id FROM payments 
            WHERE student_id = $student_id 
            AND payment_type IN ($names_str) 
            AND status = 'paid' 
            LIMIT 1
        ");
        
        if ($query2 && mysqli_num_rows($query2) > 0) {
            return true;
        }
        
        // HANYA TA 3: DUBA TA amount
        if (!empty($item_amounts)) {
            $amounts_str = implode(',', array_unique($item_amounts));
            $query3 = mysqli_query($conn, "
                SELECT id FROM payments 
                WHERE student_id = $student_id 
                AND amount IN ($amounts_str) 
                AND status = 'paid' 
                LIMIT 1
            ");
            
            if ($query3 && mysqli_num_rows($query3) > 0) {
                return true;
            }
        }
        
        return false;
    }
    
    function showPaymentRequired($item_name, $back_url = 'student_payments.php') {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Payment Required - Dala College</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                * { margin:0; padding:0; box-sizing:border-box; }
                body {
                    font-family: 'Segoe UI', sans-serif;
                    background: linear-gradient(135deg, #f0f4f8 0%, #e0e8e0 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .box {
                    background: white;
                    padding: 45px 40px;
                    border-radius: 20px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
                    max-width: 520px;
                    width: 100%;
                    text-align: center;
                }
                .lock-icon {
                    display: inline-block;
                    background: #ffebee;
                    color: #c62828;
                    width: 100px;
                    height: 100px;
                    border-radius: 50%;
                    line-height: 100px;
                    font-size: 3rem;
                    margin-bottom: 20px;
                }
                h1 { color: #0d2818; font-size: 1.5rem; font-weight: 800; margin-bottom: 10px; }
                .item-name { color: #c62828; font-size: 1.1rem; font-weight: 700; margin-bottom: 15px; }
                p { color: #666; font-size: 0.95rem; line-height: 1.7; margin-bottom: 25px; }
                .btn-pay {
                    display: inline-block;
                    background: linear-gradient(135deg, #2e7d32, #1b5e20);
                    color: white;
                    padding: 14px 40px;
                    border-radius: 30px;
                    text-decoration: none;
                    font-weight: 700;
                    font-size: 1rem;
                    transition: all 0.3s ease;
                    margin-bottom: 12px;
                }
                .btn-pay:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(46,125,50,0.35); }
                .btn-back { display: block; color: #666; text-decoration: none; font-size: 0.9rem; margin-top: 15px; }
                .btn-back:hover { color: #c62828; }
                .info-note {
                    background: #fff9c4;
                    border-left: 4px solid #f9a825;
                    padding: 12px 18px;
                    border-radius: 8px;
                    text-align: left;
                    margin-top: 25px;
                    font-size: 0.85rem;
                    color: #0d2818;
                }
                .info-note i { color: #f57c00; margin-right: 5px; }
            </style>
        </head>
        <body>
            <div class="box">
                <div class="lock-icon"><i class="fas fa-lock"></i></div>
                <h1>Payment Required</h1>
                <div class="item-name"><?php echo htmlspecialchars($item_name); ?></div>
                <p>You are required to pay the <strong><?php echo htmlspecialchars($item_name); ?></strong> before you can access this document.</p>
                <a href="student_payments.php" class="btn-pay"><i class="fas fa-credit-card"></i> Go to Payments</a>
                <a href="<?php echo htmlspecialchars($back_url); ?>" class="btn-back">← Back to Dashboard</a>
                <div class="info-note">
                    <i class="fas fa-info-circle"></i>
                    <strong>After payment:</strong> Come back here and try again.
                </div>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
}
?>