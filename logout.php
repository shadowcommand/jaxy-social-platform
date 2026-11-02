<?php
session_start();

// پاک کردن تمام داده‌های session
$_SESSION = [];

// حذف سشن روی سرور
session_destroy();

// حذف (به‌جای logout) کوکی remember-me و رکورد مرتبط در پایگاه داده
if (isset($_COOKIE['remember_token'])) {
    $parts = explode(':', $_COOKIE['remember_token'], 2);
    $selector = $parts[0] ?? '';

    if ($selector !== '') {
        require_once 'mysql.php';
        $stmt = $mysql->prepare('DELETE FROM remember_tokens WHERE selector = ?');
        $stmt->bind_param('s', $selector);
        $stmt->execute();
        $stmt->close();
    }

    setcookie('remember_token', '', time() - 3600, '/');
}


// حذف کوکی سشن روی مرورگر کاربر
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// بعد از پاک کردن سشن، صفحه خروج با پیام نمایش داده می‌شود
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging out...</title>
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <style>
        /* ==========================
           HACKER THEME – jaxy.org (Logout)
           ========================== */
        :root {
            --bg-primary: #000000;
            --bg-secondary: #050505;
            --bg-card: rgba(10, 10, 10, 0.85);
            --primary: #00ff88;
            --primary-dark: #00b35c;
            --border: #1d5f3b;
            --border-glow: rgba(0,255,120,.35);
            --text-primary: #ffffff;
            --text-secondary: #9dffbf;
            --text-muted: #7cb98d;
            --text-placeholder: #6f9d80;
            --shadow: rgba(0,255,120,.15);
            --shadow-strong: rgba(0,255,136,.45);
            --glow: rgba(0,255,136,.35);
            --grid-color: rgba(0,255,120,.05);
            --radial-glow: rgba(0,255,100,.08);
            --gradient-primary: linear-gradient(90deg, #00b35c, #00ff88);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Share Tech Mono', monospace;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background-image: 
                linear-gradient(var(--grid-color) 1px, transparent 1px),
                linear-gradient(90deg, var(--grid-color) 1px, transparent 1px);
            background-size: 40px 40px;
            position: relative;
            padding: 20px;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: radial-gradient(circle at 50% 50%, var(--radial-glow), transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        .logout-container {
            position: relative;
            z-index: 1;
            max-width: 600px;
            width: 100%;
            text-align: center;
            background: var(--bg-card);
            backdrop-filter: blur(4px);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 50px 40px;
            box-shadow: 0 20px 60px var(--shadow), inset 0 0 80px rgba(0,255,120,.04);
            animation: fadeSlide 0.6s ease-out;
        }

        .logout-container:hover {
            border-color: var(--primary);
            box-shadow: 0 0 40px var(--border-glow), inset 0 0 80px rgba(0,255,120,.06);
        }

        .logout-icon {
            font-size: 80px;
            margin-bottom: 20px;
            display: block;
        }

        .logout-container h1 {
            font-size: 2.8rem;
            font-weight: 400;
            color: var(--primary);
            text-shadow: 0 0 20px var(--glow);
            letter-spacing: 3px;
            margin-bottom: 10px;
        }

        .logout-container p {
            font-size: 1.2rem;
            color: var(--text-secondary);
            margin-bottom: 30px;
            opacity: 0.9;
        }

        .logout-container .glow-line {
            width: 60px;
            height: 2px;
            background: var(--gradient-primary);
            margin: 0 auto;
            border-radius: 4px;
            box-shadow: 0 0 20px var(--glow);
        }

        .logout-container .redirect-note {
            margin-top: 25px;
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .logout-container .redirect-note a {
            color: var(--primary);
            text-decoration: none;
        }

        .logout-container .redirect-note a:hover {
            text-decoration: underline;
        }

        @keyframes fadeSlide {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 480px) {
            .logout-container {
                padding: 30px 20px;
            }
            .logout-container h1 {
                font-size: 2rem;
            }
            .logout-icon {
                font-size: 60px;
            }
        }
    </style>
    <!-- بعد از ۲ ثانیه به login.php هدایت می‌شود -->
    <meta http-equiv="refresh" content="2;url=login.php">
</head>
<body>
    <div class="logout-container">
        <span class="logout-icon">👋</span>
        <h1>Come back soon, friend!</h1>
        <p>You have been successfully logged out. See you next time.</p>
        <div class="glow-line"></div>
        <div class="redirect-note">
            Redirecting to <a href="login.php">login page</a> in 2 seconds...
        </div>
    </div>
</body>
</html>