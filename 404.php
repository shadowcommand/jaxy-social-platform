<?php
// صفحه 404 - Not Found
// این صفحه وقتی نمایش داده می‌شود که آدرس درخواستی وجود نداشته باشد

// نیازی به session نیست ولی می‌توان برای هدر استفاده کرد
session_start();
?>
<!DOCTYPE html>
<html lang="fa" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | jaxy.org</title>
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <style>
        /* ==========================
           استایل‌های اختصاصی 404
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
            max-width: 900px;
            width: 100%;
        }

        /* ===== 404 CONTENT ===== */
        .error-content {
            text-align: center;
            padding: 30px 20px;
            animation: fadeSlide 0.8s ease-out;
        }

        @keyframes fadeSlide {
            0% { opacity: 0; transform: translateY(30px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .error-code {
            font-size: 10rem;
            font-weight: 700;
            line-height: 1;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 60px var(--glow);
            margin-bottom: 10px;
            letter-spacing: 10px;
            position: relative;
            display: inline-block;
        }

        .error-code::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--gradient-primary);
            box-shadow: 0 0 30px var(--glow);
            border-radius: 4px;
        }

        .error-title {
            font-size: 2.5rem;
            font-weight: 400;
            color: var(--text-secondary);
            margin-top: 30px;
            letter-spacing: 3px;
            text-shadow: 0 0 20px rgba(0,255,136,.15);
        }

        .error-message {
            font-size: 1.2rem;
            color: var(--text-muted);
            max-width: 500px;
            margin: 20px auto 35px;
            line-height: 1.8;
            padding: 0 15px;
        }

        .error-message strong {
            color: var(--primary);
            font-weight: 400;
        }

        .error-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            justify-content: center;
        }

        .btn-404 {
            padding: 12px 35px;
            border: 2px solid var(--primary);
            border-radius: 40px;
            background: transparent;
            color: var(--primary);
            font-size: 1rem;
            font-weight: bold;
            text-decoration: none;
            letter-spacing: 2px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            font-family: inherit;
            cursor: pointer;
        }

        .btn-404:hover {
            background: var(--primary);
            color: #000;
            box-shadow: 0 0 30px var(--shadow-strong);
            transform: translateY(-3px);
        }

        .btn-404.primary {
            background: var(--gradient-primary);
            color: #000;
            border: none;
        }

        .btn-404.primary:hover {
            box-shadow: 0 0 40px var(--shadow-strong);
            transform: translateY(-3px) scale(1.02);
        }

        /* خطوط تزئینی متحرک (اختیاری) */
        .glitch-lines {
            position: relative;
            margin: 30px auto;
            width: 80%;
            max-width: 400px;
            height: 2px;
            background: var(--border);
            border-radius: 4px;
            overflow: hidden;
        }

        .glitch-lines::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 60%;
            height: 100%;
            background: var(--gradient-primary);
            animation: slideLine 3s linear infinite;
            box-shadow: 0 0 20px var(--glow);
        }

        @keyframes slideLine {
            0% { left: -100%; }
            100% { left: 200%; }
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
            .error-code {
                font-size: 7rem;
                letter-spacing: 6px;
            }
            .error-title {
                font-size: 2rem;
            }
            .error-message {
                font-size: 1rem;
                padding: 0 10px;
            }
            .btn-404 {
                padding: 10px 25px;
                font-size: 0.9rem;
                width: 80%;
            }
            .error-actions {
                flex-direction: column;
                align-items: center;
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .error-code {
                font-size: 5rem;
                letter-spacing: 4px;
            }
            .error-title {
                font-size: 1.6rem;
            }
            .btn-404 {
                width: 100%;
                padding: 10px 20px;
                font-size: 0.85rem;
            }
            .glitch-lines {
                width: 90%;
            }
        }
    </style>
</head>
<body>

    <!-- هدر از فایل جداگانه -->
    <?php require_once 'header.php'; ?>

    <div class="content-wrapper">
        <div class="container">

            <!-- ===== 404 ERROR ===== -->
            <div class="error-content">
                <div class="error-code">404</div>
                <div class="glitch-lines"></div>
                <h1 class="error-title">Page Not Found</h1>
                <p class="error-message">
                    Oops! The page you're looking for <strong>does not exist</strong> or has been moved.<br>
                    Maybe you typed a wrong address or followed a broken link.
                </p>
                <div class="error-actions">
                    <a href="/index.php" class="btn-404 primary">🏠 Return Home</a>
                    <a href="javascript:history.back()" class="btn-404">↩ Go Back</a>
                    <a href="/explore_tweet.php" class="btn-404">🔍 Explore</a>
                </div>
            </div>

            <!-- ===== FOOTER ===== -->
            <footer class="footer">
                &copy; <?php echo date('Y'); ?> <a href="/">jaxy.org</a> – Built with <span style="color: var(--primary);">&#9829;</span> &amp; code.
            </footer>

        </div>
    </div>

</body>
</html>