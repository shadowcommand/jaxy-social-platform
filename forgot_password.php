<?php
require_once  __DIR__ . '/mysql.php';
require_once 'vendor/autoload.php'; // مسیر اتولود کامپوزر

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

// رفع هشدار PCRE JIT
ini_set('pcre.jit', '0');

// در صورت لاگین بودن، به پنل هدایت شود
if (isset($_SESSION['user_id'])) {
    header('Location: panel.php');
    exit;
}

/**
 * ارسال ایمیل حاوی لینک بازنشانی با استفاده از PHPMailer (SMTP)
 */
function sendResetEmail(string $to, string $username, string $resetLink): bool {
    $mail = new PHPMailer(true);
    try {
        // تنظیمات SMTP - اطلاعات خود را اینجا وارد کنید
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'javadgholami313114@gmail.com';   // ← ایمیل خودتان
        $mail->Password   = 'xnhluuvhtxdlcdvw';            // ← App Password (بدون فاصله یا با فاصله؟ بهتره بدون فاصله وارد کنید: xnuluuvhtxdlcdvw)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // فرستنده و گیرنده
        $mail->setFrom('javadgholami313114@gmail.com', 'Jaxy App');
        $mail->addAddress($to, $username);

        // محتوای ایمیل (متن ساده)
        $mail->isHTML(false);
        $mail->Subject = 'Password Reset';
        $mail->Body    = "Hello {$username},\n\nTo reset your password, click the link below:\n{$resetLink}\n\nThis link expires in 30 minutes.\n\nIf you didn't request this, ignore this email.";

        return $mail->send();
    } catch (Exception $e) {
        error_log('PHPMailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * لاگ کردن تلاش‌های بازنشانی (اختیاری)
 */
function logResetAttempt(mysqli $mysql, string $email, string $status, string $ip, ?string $reason = null): void {
    error_log("Password reset attempt - email: $email, status: $status, ip: $ip, reason: " . ($reason ?? 'none'));
}

// متغیر پیام نمایشی
$message = '';

// بررسی ارسال فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $ip    = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // اعتبارسنجی اولیه
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        logResetAttempt($mysql, $email, 'failed', $ip, 'invalid_email_format');
    } else {
        try {
            // بررسی وجود کاربر (بدون افشا)
            $stmt = $mysql->prepare('SELECT id, username FROM users WHERE email = ?');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            // صرف‌نظر از وجود کاربر، پیام یکسان نمایش داده می‌شود
            $message = 'If an account with that email exists, a password reset link will be sent shortly.';

            if ($user) {
                // ۱. حذف توکن‌های قبلی استفاده‌نشده برای این ایمیل (اختیاری)
                $stmt = $mysql->prepare('DELETE FROM password_resets WHERE email = ? AND used = 0');
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $stmt->close();

                // ۲. تولید توکن تصادفی ۳۲ بایتی
                $token = bin2hex(random_bytes(32));

                // ۳. هش کردن توکن
                $tokenHash = password_hash($token, PASSWORD_BCRYPT);

                // ۴. زمان انقضا (۳۰ دقیقه بعد)
                $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

                // ۵. ذخیره در دیتابیس
                $stmt = $mysql->prepare('INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?, ?, ?)');
                $stmt->bind_param('sss', $email, $tokenHash, $expiresAt);
                if (!$stmt->execute()) {
                    throw new Exception('Failed to store the reset token.');
                }
                $stmt->close();

                // ۶. ساخت لینک بازنشانی
                $resetLink = 'http://' . $_SERVER['HTTP_HOST']  . '/jaxy.org/reset_password.php?token=' . $token;

                // ۷. ارسال ایمیل با PHPMailer
                $mailSent = sendResetEmail($email, $user['username'], $resetLink);

                if (!$mailSent) {
                    error_log("Failed to send reset email to $email");
                }

                logResetAttempt($mysql, $email, 'sent', $ip);
            } else {
                logResetAttempt($mysql, $email, 'not_found', $ip);
            }
        } catch (Exception $e) {
            error_log('forgot_password error: ' . $e->getMessage());
            $message = 'An unexpected error occurred. Please try again later.';
            logResetAttempt($mysql, $email, 'error', $ip, $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <style>
        /* همان استایل‌های قبلی */
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
        * { margin:0; padding:0; box-sizing:border-box; font-family: Arial, sans-serif; }
        body {
            height:100vh; display:flex; justify-content:center; align-items:center;
            background:var(--bg-secondary); overflow:hidden;
        }
        body::before {
            content:""; position:fixed; inset:0;
            background:
                radial-gradient(circle at 20% 20%, var(--radial-glow), transparent 30%),
                radial-gradient(circle at 80% 70%, var(--radial-glow), transparent 35%),
                linear-gradient(var(--bg-secondary), var(--bg-primary));
            z-index:-2;
        }
        body::after {
            content:""; position:fixed; inset:0;
            background-image:
                linear-gradient(var(--grid-color) 1px, transparent 1px),
                linear-gradient(90deg, var(--grid-color) 1px, transparent 1px);
            background-size:40px 40px;
            animation:gridMove 15s linear infinite;
            z-index:-1;
        }
        @keyframes gridMove {
            from { transform:translateY(0); }
            to { transform:translateY(40px); }
        }
        .login-box {
            width:420px; padding:45px; border-radius:18px;
            background:var(--bg-card); border:1px solid var(--border-glow);
            box-shadow: 0 0 25px var(--shadow), 0 0 80px rgba(0,255,120,.08);
            backdrop-filter:blur(12px);
        }
        .login-box h1 {
            color:var(--primary); text-align:center; margin-bottom:35px;
            font-size:30px; letter-spacing:3px; text-transform:uppercase;
        }
        .alert {
            padding: 12px 18px; border-radius: 10px; margin-bottom: 20px;
            font-size: 14px; border: 1px solid transparent; text-align: center;
        }
        .alert-info {
            background: rgba(0, 150, 255, 0.15); border-color: #00aaff;
            color: #77ccff; box-shadow: 0 0 20px rgba(0, 150, 255, 0.1);
        }
        .input-group { margin-bottom:22px; }
        .input-group label {
            display:block; margin-bottom:8px; color:var(--text-secondary);
            font-size:14px; letter-spacing:1px;
        }
        .input-group input {
            width:100%; padding:14px 15px; background:#0b0b0b;
            border:1px solid var(--border); border-radius:10px;
            color:var(--text-primary); outline:none; transition:.3s; font-size:15px;
        }
        .input-group input::placeholder { color:var(--text-placeholder); }
        .input-group input:focus {
            border-color:var(--primary); box-shadow:0 0 15px var(--glow);
        }
        button {
            width:100%; margin-top:12px; padding:15px; border:none;
            border-radius:10px; background:var(--gradient-primary);
            color:#000; font-size:16px; font-weight:bold; cursor:pointer;
            transition:.3s;
        }
        button:hover { transform:translateY(-2px); box-shadow:0 0 25px var(--shadow-strong); }
        .footer {
            text-align:center; margin-top:22px; color:var(--text-muted);
            font-size:13px;
        }
        .footer a { color:var(--primary); text-decoration:none; }
        .footer a:hover { text-decoration:underline; }
    </style>
</head>
<body>
<div class="login-box">
    <h1>Forgot Password</h1>

    <?php if (!empty($message)): ?>
        <div class="alert alert-info">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form action="forgot_password.php" method="POST">
        <div class="input-group">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="example@domain.com" required>
        </div>
        <button type="submit">Send Reset Link</button>
    </form>

    <div class="footer">
        <a href="login.php">Back to Login</a>
    </div>
</div>
</body>
</html>