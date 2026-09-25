<?php
// ============================================
// DALA COLLEGE - ERROR PAGE
// Red Theme - English Only
// ============================================

$code = intval($_GET['code'] ?? 404);
if (!in_array($code, [400, 401, 403, 404, 405, 408, 429, 500, 502, 503, 504])) {
    $code = 404;
}

$errors = [
    400 => [
        'title' => 'Bad Request',
        'message' => 'The request you made is invalid. Please try again.',
        'icon' => '⚠️'
    ],
    401 => [
        'title' => 'Unauthorized',
        'message' => 'You are not authorized to access this page. Please log in with your account.',
        'icon' => '🔒'
    ],
    403 => [
        'title' => 'Access Forbidden',
        'message' => 'You do not have permission to access this page. This is a restricted area.',
        'icon' => '🚫'
    ],
    404 => [
        'title' => 'Page Not Found',
        'message' => 'The page you are looking for does not exist. It may have been removed or the URL may have changed.',
        'icon' => '🔍'
    ],
    405 => [
        'title' => 'Method Not Allowed',
        'message' => 'The method you used is not allowed for this page.',
        'icon' => '⛔'
    ],
    408 => [
        'title' => 'Request Timeout',
        'message' => 'The request took too long. Please try again.',
        'icon' => '⏱️'
    ],
    429 => [
        'title' => 'Too Many Requests',
        'message' => 'You have made too many requests too quickly. Please wait a moment and try again.',
        'icon' => '🐢'
    ],
    500 => [
        'title' => 'Internal Server Error',
        'message' => 'There is a problem with the server. Please try again later.',
        'icon' => '⚠️'
    ],
    502 => [
        'title' => 'Bad Gateway',
        'message' => 'The server received an invalid response. Please try again.',
        'icon' => '🔌'
    ],
    503 => [
        'title' => 'Service Unavailable',
        'message' => 'The service is temporarily unavailable. Please try again later.',
        'icon' => '🔧'
    ],
    504 => [
        'title' => 'Gateway Timeout',
        'message' => 'The server did not respond in time. Please try again.',
        'icon' => '⏰'
    ],
];

$error = $errors[$code];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $error['title']; ?> - Dala College</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .error-container {
            background: white;
            padding: 60px 50px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(198, 40, 40, 0.25);
            text-align: center;
            max-width: 550px;
            width: 100%;
            border-top: 10px solid #c62828;
            position: relative;
            overflow: hidden;
        }
        
        /* Background Pattern */
        .error-container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(198, 40, 40, 0.03) 1px, transparent 1px);
            background-size: 20px 20px;
            pointer-events: none;
            z-index: 0;
        }
        
        .error-container > * {
            position: relative;
            z-index: 1;
        }
        
        /* Logo */
        .logo {
            font-size: 1.5rem;
            font-weight: 900;
            color: #0d2818;
            letter-spacing: 3px;
            margin-bottom: 25px;
            text-transform: uppercase;
        }
        .logo span {
            color: #c62828;
        }
        .logo-sub {
            display: block;
            font-size: 0.6rem;
            color: #6a8f6a;
            letter-spacing: 4px;
            margin-top: 3px;
            font-weight: 600;
        }
        
        /* Error Icon */
        .error-icon {
            font-size: 5rem;
            margin-bottom: 15px;
            display: block;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        /* Error Code */
        .error-code {
            font-size: 6rem;
            font-weight: 900;
            color: #c62828;
            line-height: 1;
            margin-bottom: 10px;
            text-shadow: 4px 4px 0 rgba(198, 40, 40, 0.1);
            letter-spacing: 5px;
        }
        
        /* Error Title */
        .error-title {
            font-size: 1.6rem;
            font-weight: 800;
            color: #0d2818;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        /* Error Message */
        .error-message {
            font-size: 1rem;
            color: #6a8f6a;
            margin-bottom: 35px;
            line-height: 1.7;
            padding: 0 20px;
        }
        
        /* Divider */
        .divider {
            width: 80px;
            height: 3px;
            background: linear-gradient(to right, transparent, #c62828, transparent);
            margin: 0 auto 30px auto;
            border-radius: 3px;
        }
        
        /* Buttons */
        .buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 35px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            letter-spacing: 1px;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #c62828, #b71c1c);
            color: white;
            box-shadow: 0 6px 20px rgba(198, 40, 40, 0.35);
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(198, 40, 40, 0.45);
        }
        
        .btn-secondary {
            background: white;
            color: #c62828;
            border: 2px solid #c62828;
        }
        .btn-secondary:hover {
            background: #c62828;
            color: white;
            transform: translateY(-3px);
        }
        
        /* Footer */
        .footer {
            margin-top: 35px;
            padding-top: 20px;
            border-top: 1px solid #ffcdd2;
            font-size: 0.75rem;
            color: #6a8f6a;
            font-style: italic;
        }
        
        .footer a {
            color: #c62828;
            text-decoration: none;
            font-weight: 700;
        }
        .footer a:hover {
            text-decoration: underline;
        }
        
        /* Responsive */
        @media (max-width: 600px) {
            .error-container {
                padding: 40px 25px;
            }
            .error-code {
                font-size: 4.5rem;
            }
            .error-title {
                font-size: 1.3rem;
            }
            .error-message {
                font-size: 0.9rem;
                padding: 0;
            }
            .btn {
                padding: 12px 25px;
                font-size: 0.85rem;
                width: 100%;
                justify-content: center;
            }
            .buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

    <div class="error-container">
        
        <!-- Logo -->
        <div class="logo">
            DALA <span>COLLEGE</span>
            <span class="logo-sub">of Education, Kano</span>
        </div>
        
        <!-- Error Icon -->
        <span class="error-icon"><?php echo $error['icon']; ?></span>
        
        <!-- Error Code -->
        <div class="error-code"><?php echo $code; ?></div>
        
        <!-- Error Title -->
        <h1 class="error-title"><?php echo $error['title']; ?></h1>
        
        <!-- Divider -->
        <div class="divider"></div>
        
        <!-- Error Message -->
        <p class="error-message"><?php echo $error['message']; ?></p>
        
        <!-- Buttons -->
        <div class="buttons">
            <a href="index.php" class="btn btn-primary">
                🏠 Back to Home
            </a>
            <a href="javascript:history.back()" class="btn btn-secondary">
                ← Go Back
            </a>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            If this problem persists, contact 
            <a href="mailto:info@dalacoe.edu.ng">info@dalacoe.edu.ng</a>
            <br><br>
            © <?php echo date('Y'); ?> Dala College of Education, Kano
        </div>
        
    </div>

</body>
</html>