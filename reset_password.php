<?php
require_once 'mysql.php';
require_once 'vendor/autoload.php'; // برای PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

// ===== رفع هشدار PCRE JIT =====
ini_set('pcre.jit', '0');

// در صورت لاگین بودن، به پنل هدایت شود
if (isset($_SESSION['user_id'])) {
    header('Location: panel.php');
    exit;
}

/**
 * ارسال ایمیل اطلاع‌رسانی تغییر رمز با PHPMailer (SMTP)
 */
function sendPasswordChangedEmail(string $to, string $username): bool {
    $mail = new PHPMailer(true);
    try {
        // تنظیمات SMTP (همانند forgot_password.php)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'javadgholami313114@gmail.com';
        $mail->Password   = 'xnuluuvhtxdlcdvw'; // بدون فاصله
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('javadgholami313114@gmail.com', 'Jaxy App');
        $mail->addAddress($to, $username);

        $mail->isHTML(false);
        $mail->Subject = 'Your password has been changed';
        $mail->Body    = "Hello {$username},\n\nYour password has been successfully changed.\nIf you did not make this change, please contact support immediately.";

        return $mail->send();
    } catch (Exception $e) {
        error_log('PHPMailer (change notification) Error: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * باطل‌سازی سشن فعلی
 */
function invalidateSession(): void {
    global $mysql;

    // Invalidate any remember-me token (both the DB row and the cookie) so
    // a password change cannot be bypassed by a stale persistent login.
    if (isset($_COOKIE['remember_token'])) {
        $parts = explode(':', $_COOKIE['remember_token'], 2);
        $selector = $parts[0] ?? '';

        if ($selector !== '' && isset($mysql)) {
            $stmt = $mysql->prepare('DELETE FROM remember_tokens WHERE selector = ?');
            if ($stmt) {
                $stmt->bind_param('s', $selector);
                $stmt->execute();
                $stmt->close();
            }
        }

        setcookie('remember_token', '', time() - 3600, '/');
    }

    session_regenerate_id(true);
}

// متغیرهای وضعیت
$error = '';
$email = null;
$token = $_GET['token'] ?? '';

// ===== مرحله ۱: اعتبارسنجی توکن (GET) =====
if ($token !== '') {
    try {
        // فقط یک کوئری برای پیدا کردن توکن معتبر
        $stmt = $mysql->prepare('SELECT email, token_hash FROM password_resets WHERE used = 0 AND expires_at > NOW()');
        $stmt->execute();
        $result = $stmt->get_result();

        $validToken = false;
        $storedEmail = null;

        while ($row = $result->fetch_assoc()) {
            if (password_verify($token, $row['token_hash'])) {
                $validToken = true;
                $storedEmail = $row['email'];
                break;
            }
        }
        $stmt->close();

        if ($validToken && $storedEmail) {
            $_SESSION['reset_email'] = $storedEmail;
            $_SESSION['reset_token'] = $token;
        } else {
            $error = 'Invalid or expired reset link.';
        }
    } catch (Exception $e) {
        error_log('reset_password verify error: ' . $e->getMessage());
        $error = 'Error validating link.';
    }
} else {
    $error = 'Invalid reset link.';
}

// ===== مرحله ۲: پردازش فرم (POST) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'], $_POST['password_confirm'])) {
    // بررسی سشن
    if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_token'])) {
        $error = 'Session is not valid. Please request a new reset link.';
    } else {
        $email = $_SESSION['reset_email'];
        $token = $_SESSION['reset_token'];
        $newPassword = $_POST['password'];
        $confirm     = $_POST['password_confirm'];

        // اعتبارسنجی رمز
        if (strlen($newPassword) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            $error = 'Password must contain at least one uppercase letter, one lowercase letter, and one number.';
        } elseif ($newPassword !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            try {
                // ===== بهینه‌سازی: یک تراکنش برای همه عملیات =====
                $mysql->begin_transaction();

                // ۱. بررسی مجدد توکن (با قفل کردن ردیف)
                $stmt = $mysql->prepare('SELECT id, token_hash FROM password_resets WHERE email = ? AND used = 0 AND expires_at > NOW() FOR UPDATE');
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $stmt->close();

                if (!$row || !password_verify($token, $row['token_hash'])) {
                    throw new Exception('Invalid or expired token.');
                }

                // ۲. به‌روزرسانی رمز کاربر
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmt = $mysql->prepare('UPDATE users SET password_hash = ? WHERE email = ?');
                $stmt->bind_param('ss', $newHash, $email);
                if (!$stmt->execute()) {
                    throw new Exception('Failed to update password.');
                }
                $stmt->close();

                // ۳. علامت‌گذاری توکن به عنوان استفاده‌شده
                $stmt = $mysql->prepare('UPDATE password_resets SET used = 1 WHERE email = ? AND token_hash = ?');
                $stmt->bind_param('ss', $email, $row['token_hash']);
                $stmt->execute();
                $stmt->close();

                // ۴. دریافت نام کاربری برای ایمیل
                $stmt = $mysql->prepare('SELECT username FROM users WHERE email = ?');
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $nameResult = $stmt->get_result();
                $nameRow = $nameResult->fetch_assoc();
                $stmt->close();
                $username = $nameRow['username'] ?? 'User';

                $mysql->commit();

                // ===== باطل‌سازی سشن =====
                invalidateSession();

                // ===== ارسال ایمیل اطلاع‌رسانی (با نادیده گرفتن خطا) =====
                // این کار را در پس‌زمینه انجام دهید تا کاربر معطل نشود
                // اما فعلاً به‌صورت همزمان اجرا می‌شود (می‌توانید بعداً آن را به صف یا غیرهمزمان منتقل کنید)
                sendPasswordChangedEmail($email, $username);

                // پاک کردن متغیرهای سشن
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_token']);

                // ریدایرکت به صفحه لاگین با پیام موفقیت
                header('Location: login.php?reset_success=1');
                exit;

            } catch (Exception $e) {
                $mysql->rollback();
                error_log('reset_password update error: ' . $e->getMessage());
                $error = 'Error changing password. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <style>
        /* (همان استایل‌های قبلی - برای اختصار حذف شد، ولی در فایل واقعی کپی کنید) */
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
        .alert-error {
            background: rgba(255, 80, 80, 0.15); border-color: #ff5555;
            color: #ff7777; box-shadow: 0 0 20px rgba(255, 80, 80, 0.1);
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
    <h1>Set New Password</h1>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error">
            ❌ <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error) && !isset($_SESSION['reset_email'])): ?>
        <div class="footer">
            <a href="forgot_password.php">Request new reset link</a>
        </div>
    <?php elseif (isset($_SESSION['reset_email']) && isset($_SESSION['reset_token'])): ?>
        <form action="reset_password.php?token=<?php echo htmlspecialchars($token ?? ''); ?>" method="POST">
            <div class="input-group">
                <label>New Password</label>
                <input type="password" name="password" placeholder="Minimum 8 characters..." required>
            </div>
            <div class="input-group">
                <label>Confirm Password</label>
                <input type="password" name="password_confirm" placeholder="Re-enter password" required>
            </div>
            <button type="submit">Change Password</button>
        </form>
        <div class="footer">
            <a href="login.php">Back to Login</a>
        </div>
    <?php else: ?>
        <div class="footer">
            <a href="forgot_password.php">Request Password Reset</a>
        </div>
    <?php endif; ?>
</div>
</body>
</html>