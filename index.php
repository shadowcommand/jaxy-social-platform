<?php
session_start();
require_once 'mysql.php'; // شامل اطلاعات اتصال به دیتابیس

// متغیرهای پیش‌فرض
$isLoggedIn = false;
$userData = null;

// بررسی لاگین بودن
if (isset($_SESSION['user_id'])) {
    $userId = (int)$_SESSION['user_id'];
    // دریافت اطلاعات کاربر از دیتابیس
    $stmt = $mysql->prepare('SELECT fullname, username, avatar FROM users WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $isLoggedIn = true;
            $userData = $row;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>jaxy.org – Home</title>
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <style>
        /* ==========================
           استایل‌های اختصاصی index.php
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
            flex-direction: column;
            background-image: 
                linear-gradient(var(--grid-color) 1px, transparent 1px),
                linear-gradient(90deg, var(--grid-color) 1px, transparent 1px);
            background-size: 40px 40px;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: radial-gradient(circle at 50% 50%, var(--radial-glow), transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        /* هدر توسط فایل header.php مدیریت می‌شود */
        .header {
            position: relative;
            z-index: 2;
            margin-bottom: 0;
        }

        /* wrapper برای محتوای اصلی */
        .content-wrapper {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px 30px;
            position: relative;
            z-index: 1;
        }

        .container {
            max-width: 1200px;
            width: 100%;
        }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 40px 0;
            min-height: 50vh;
        }

        .main-content h1 {
            font-size: 3.5rem;
            font-weight: 400;
            margin-bottom: 15px;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 40px var(--glow);
        }

        .main-content p {
            font-size: 1.2rem;
            color: var(--text-secondary);
            max-width: 600px;
            margin-bottom: 30px;
        }

        .main-content .greeting {
            font-size: 1.8rem;
            color: var(--text-primary);
        }
        .main-content .greeting small {
            font-size: 1rem;
            color: var(--text-muted);
        }

        .action-buttons {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 20px;
        }

        .btn-primary {
            padding: 12px 35px;
            border: 2px solid var(--primary);
            border-radius: 40px;
            background: transparent;
            color: var(--primary);
            font-size: 1.1rem;
            font-weight: bold;
            text-decoration: none;
            letter-spacing: 2px;
            transition: all 0.3s ease;
            cursor: pointer;
            text-transform: uppercase;
        }

        .btn-primary:hover {
            background: var(--primary);
            color: #000;
            box-shadow: 0 0 30px var(--shadow-strong);
            transform: translateY(-3px);
        }

        .btn-secondary {
            background: var(--gradient-primary);
            color: #000;
            border: none;
        }

        .btn-secondary:hover {
            box-shadow: 0 0 40px var(--shadow-strong);
            transform: translateY(-3px) scale(1.02);
        }

        /* ===== FOOTER ===== */
        .footer {
            border-top: 1px solid var(--border);
            padding: 20px 0;
            margin-top: 30px;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .footer a {
            color: var(--primary);
            text-decoration: none;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .content-wrapper {
                padding: 15px;
            }
            .main-content h1 {
                font-size: 2.5rem;
            }
            .main-content p {
                font-size: 1rem;
                padding: 0 15px;
            }
            .action-buttons {
                flex-direction: column;
                align-items: center;
                width: 100%;
            }
            .btn-primary {
                width: 80%;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .main-content h1 {
                font-size: 2rem;
            }
            .btn-primary {
                width: 100%;
                padding: 10px 20px;
                font-size: 0.95rem;
            }
            .main-content .greeting {
                font-size: 1.4rem;
            }
        }
    </style>
</head>
<body>

    <!-- هدر از فایل جداگانه -->
    <?php require_once 'header.php'; ?>

    <div class="content-wrapper">
        <div class="container">

            <!-- ===== MAIN CONTENT ===== -->
            <main class="main-content">
                <?php if ($isLoggedIn && $userData): ?>
                    <div class="greeting">
                        👋 Welcome back, <strong><?php echo htmlspecialchars($userData['fullname']); ?></strong>
                        <small>(@<?php echo htmlspecialchars($userData['username']); ?>)</small>
                    </div>
                    <p>Ready to explore, tweet, and connect? Your journey starts here.</p>
                    <div class="action-buttons">
                        <a href="panel.php" class="btn-primary">⚡ Dashboard</a>
                        <a href="explore_tweet.php" class="btn-primary btn-secondary">🐦 Explore Tweet</a>
                        <a href="profile.php?userId=<?php echo (int)$_SESSION['user_id']; ?>" class="btn-primary">👤 My Profile</a>
                    </div>
                <?php else: ?>
                    <h1>Welcome to jaxy</h1>
                    <p>Join the community, share your thoughts, and connect with others.</p>
                    <div class="action-buttons">
                        <a href="login.php" class="btn-primary">🔐 Login</a>
                        <a href="register.php" class="btn-primary btn-secondary">🚀 Register</a>
                    </div>
                <?php endif; ?>
            </main>

            <!-- ===== FOOTER ===== -->
            <footer class="footer">
                &copy; <?php echo date('Y'); ?> <a href="/">jaxy.org</a> – Built with <span style="color: var(--primary);">&#9829;</span> &amp; code.
            </footer>

        </div>
    </div>

</body>
</html>