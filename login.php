<?php
require_once 'mysql.php';
// login.php – Handles login form submission

session_start();

/**
 * Redirect an already-authenticated visitor straight to the panel.
 */
function ensureNotLoggedIn(): void
{
    if (isset($_SESSION['user_id'])) {
        // If the user is already logged in, send them directly to the panel
        header('Location: panel.php');
        exit;
    }
}

ensureNotLoggedIn();

/**
 * Generate a cryptographically-random remember-me token pair.
 *
 * The returned selector identifies the DB row while the validator is the
 * actual secret. Only a SHA-256 hash of the validator is ever stored, so a
 * database leak does not expose usable tokens.
 *
 * @return array{selector: string, validator: string}
 */
function generateRememberToken(): array
{
    $selector  = bin2hex(random_bytes(16)); // 32 hex chars
    $validator = bin2hex(random_bytes(32));  // 64 hex chars
    return ['selector' => $selector, 'validator' => $validator];
}

/**
 * Persist a remember-me token for a user and return the cookie value.
 * Only the SHA-256 hash of the validator is stored in the database.
 *
 * @return string "selector:validator" to place in the cookie
 */
function createRememberSession(mysqli $mysql, int $userId): string
{
    $token         = generateRememberToken();
    $selector      = $token['selector'];
    $validator     = $token['validator'];
    $validatorHash = hash('sha256', $validator);
    $expiresAt     = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days

    $stmt = $mysql->prepare(
        'INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at) VALUES (?, ?, ?, ?)'
    );
    $stmt->bind_param('isss', $userId, $selector, $validatorHash, $expiresAt);
    $stmt->execute();
    $stmt->close();

    return $selector . ':' . $validator;
}

/**
 * Set or clear the remember-me cookie.
 */
function setRememberCookie(?string $value): void
{
    if ($value === null) {
        setcookie('remember_token', '', time() - 3600, '/');
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    setcookie('remember_token', $value, [
        'expires'  => time() + (30 * 24 * 60 * 60), // 30 days
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Fetch the stored selector row, if any.
 *
 * @return ?array{user_id: int, validator_hash: string, expires_at: string}
 */
function findRememberRow(mysqli $mysql, string $selector): ?array
{
    $stmt = $mysql->prepare('SELECT user_id, validator_hash, expires_at FROM remember_tokens WHERE selector = ?');
    $stmt->bind_param('s', $selector);
    $stmt->execute();
    $result = $stmt->get_result();
    $row    = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

/**
 * Remove a remember-me row (used when the cookie is expired or invalid).
 */
function deleteRememberToken(mysqli $mysql, string $selector): void
{
    $stmt = $mysql->prepare('DELETE FROM remember_tokens WHERE selector = ?');
    $stmt->bind_param('s', $selector);
    $stmt->execute();
    $stmt->close();
}

/**
 * If a valid remember-me cookie is present, rebuild the PHP session and
 * return the authenticated user row. Returns null when no/invalid token.
 *
 * @return ?array
 */
function handle_remember_auto_login(mysqli $mysql): ?array
{
    $cookie = $_COOKIE['remember_token'] ?? '';
    $parts  = explode(':', $cookie, 2);
    if (count($parts) !== 2) {
        return null;
    }

    [$selector, $validator] = $parts;

    $row = findRememberRow($mysql, $selector);
    if (!$row) {
        return null;
    }

    // Reject (and clean up) expired tokens.
    if (strtotime($row['expires_at']) < time()) {
        deleteRememberToken($mysql, $selector);
        return null;
    }

    // Constant-time comparison of the validator hash.
    if (!hash_equals(hash('sha256', $validator), $row['validator_hash'])) {
        return null;
    }

    $stmt = $mysql->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->bind_param('i', $row['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$user) {
        return null;
    }

    // Rebuild the session and rotate its ID for security.
    session_regenerate_id(true);
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['email']    = $user['email'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['fullname'] = $user['fullname'];

    return $user;
}

/**
 * Insert a row into login_logs for every login attempt (success or failure).
 * Failures here are swallowed (logged to the PHP error log only) so that
 * a logging problem never breaks the login flow itself.
 */
function logLoginAttempt(mysqli $mysql, ?int $userId, string $usernameAttempted, string $status, ?string $failureReason, string $ip, ?string $userAgent, ?string $sessionId = null, string $loginMethod = 'password'): void {
    // Defensive truncation to match column limits
    $usernameAttempted = mb_substr($usernameAttempted, 0, 255);
    $ip                = mb_substr($ip, 0, 45);

    $sql = 'INSERT INTO login_logs
                (user_id, username_attempted, status, failure_reason, ip_address, user_agent, login_method, session_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)';

    try {
        $stmt = $mysql->prepare($sql);

        if ($stmt === false) {
            error_log('login_logs prepare failed: ' . $mysql->error);
            return;
        }

        $stmt->bind_param('isssssss', $userId, $usernameAttempted, $status, $failureReason, $ip, $userAgent, $loginMethod, $sessionId);

        if (!$stmt->execute()) {
            error_log('login_logs insert failed: ' . $stmt->error);
        }

        $stmt->close();
    } catch (\Throwable $e) {
        // Never let a logging failure take down the login flow.
        error_log('logLoginAttempt exception: ' . $e->getMessage());
    }
}

// ==========================================================
//  CLIENT & INPUT HELPERS
// ==========================================================

/**
 * Gather the client information used for logging (IP + user agent).
 * @return array{0: string, 1: ?string} [ip, userAgent]
 */
function get_client_meta(): array
{
    $ip        = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    return [$ip, $userAgent];
}

/**
 * Read and lightly sanitize the submitted credentials.
 * @return array{0: string, 1: string} [email, password]
 */
function read_credentials(): array
{
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember_me']);
    return [$email, $password, $remember];
}

/**
 * Look up a user by email and verify the supplied password.
 * @return array{success: bool, user: ?array, failure_reason: ?string}
 */
function authenticate_user(mysqli $mysql, string $email, string $password): array
{
    $stmt = $mysql->prepare('SELECT * FROM users WHERE email = ?');

    if ($stmt === false) {
        error_log('users prepare failed: ' . $mysql->error);
        return ['success' => false, 'user' => null, 'failure_reason' => null];
    }

    $stmt->bind_param('s', $email);

    if (!$stmt->execute()) {
        error_log('users execute failed: ' . $stmt->error);
        $stmt->close();
        return ['success' => false, 'user' => null, 'failure_reason' => null];
    }

    $result = $stmt->get_result();

    if ($result === false) {
        error_log('users get_result failed: ' . $stmt->error);
        $stmt->close();
        return ['success' => false, 'user' => null, 'failure_reason' => null];
    }

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $stmt->close();

        if (password_verify($password, $user['password_hash'])) {
            return ['success' => true, 'user' => $user, 'failure_reason' => null];
        }

        return ['success' => false, 'user' => $user, 'failure_reason' => 'invalid_password'];
    }

    $stmt->close();
    return ['success' => false, 'user' => null, 'failure_reason' => 'user_not_found'];
}

// ==========================================================
//  MAIN LOGIN HANDLER
// ==========================================================

// Runs the whole login flow for a POST request.
// @return array{success: bool, error: ?string}
function handle_login(mysqli $mysql): array
{
    [$ip, $userAgent]     = get_client_meta();
    [$email, $password, $remember] = read_credentials();

    try {
        // ---- Step 1: Basic validation ----
        if ($email === '' || $password === '') {
            logLoginAttempt(
                $mysql,
                null,
                $email !== '' ? $email : '(empty)',
                'failed',
                'missing_fields',
                $ip,
                $userAgent
            );

            return ['success' => false, 'error' => 'Please fill in both email and password.'];
        }

        // ---- Step 2: Credential check ----
        $auth = authenticate_user($mysql, $email, $password);

        if ($auth['success']) {
            $user = $auth['user'];

            $_SESSION['user_id']  = $user['id'];
            $_SESSION['email']    = $user['email'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['fullname'] = $user['fullname'];

            // Regenerate session ID for security
            session_regenerate_id(true);

            logLoginAttempt(
                $mysql,
                $user['id'],
                $email,
                'success',
                null,
                $ip,
                $userAgent,
                session_id()
            );

            // Persist or clear the remember-me cookie.
            if ($remember) {
                setRememberCookie(createRememberSession($mysql, $user['id']));
            } else {
                setRememberCookie(null);
            }

            return ['success' => true, 'error' => null];
        }

        // Failure: log the specific reason, then hand the message back.
        $userId = $auth['user'] ? $auth['user']['id'] : null;

        if ($auth['failure_reason'] !== null) {
            logLoginAttempt(
                $mysql,
                $userId,
                $email,
                'failed',
                $auth['failure_reason'],
                $ip,
                $userAgent
            );
        }

        $error = ('invalid_password' === $auth['failure_reason'] || 'user_not_found' === $auth['failure_reason'])
            ? 'Invalid email or password.'
            : 'Database error – please try again later.';

        return ['success' => false, 'error' => $error];
    } catch (\Throwable $e) {
        // Last-resort safety net: never show a raw HTTP 500 to a visitor.
        error_log('login.php unexpected exception: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Something went wrong – please try again later.'];
    }
}

// ==========================================================
//  REQUEST DISPATCH
// ==========================================================

// If a valid remember-me cookie is present, log the user in automatically
// and send them straight to the panel (option A).
if (!isset($_SESSION['user_id']) && handle_remember_auto_login($mysql) !== null) {
    header('Location: panel.php');
    exit;
}

$loginResult = ['success' => false, 'error' => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginResult = handle_login($mysql);
    $success = $loginResult['success'];
    $error   = $loginResult['error'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login</title>
<?php if (isset($success) && $success): ?>
<!-- Redirect after 3 seconds using meta refresh -->
<meta http-equiv="refresh" content="3;url=panel.php">
<?php endif; ?>
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
/* ==========================
   Website Color Theme
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
    font-family: 'Share Tech Mono', monospace;
}

body {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: var(--bg-secondary);
    overflow-y: auto; /* اجازه اسکرول در صورت نیاز */
    position: relative;
}

/* Background */
body::before {
    content: "";
    position: fixed;
    inset: 0;
    background:
        radial-gradient(circle at 20% 20%, var(--radial-glow), transparent 30%),
        radial-gradient(circle at 80% 70%, var(--radial-glow), transparent 35%),
        linear-gradient(var(--bg-secondary), var(--bg-primary));
    z-index: -2;
}

body::after {
    content: "";
    position: fixed;
    inset: 0;
    background-image:
        linear-gradient(var(--grid-color) 1px, transparent 1px),
        linear-gradient(90deg, var(--grid-color) 1px, transparent 1px);
    background-size: 40px 40px;
    animation: gridMove 15s linear infinite;
    z-index: -1;
}

@keyframes gridMove {
    from { transform: translateY(0); }
    to { transform: translateY(40px); }
}

/* Main content wrapper */
.main-wrapper {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
    position: relative;
    z-index: 1;
    min-height: 0; /* برای جلوگیری از سرریز */
}

/* Card */
.login-box {
    width: 420px;
    max-width: 100%;
    padding: 45px;
    border-radius: 18px;
    background: var(--bg-card);
    border: 1px solid var(--border-glow);
    box-shadow:
        0 0 25px var(--shadow),
        0 0 80px rgba(0,255,120,.08);
    backdrop-filter: blur(12px);
    margin: 10px;
}

.login-box h1 {
    color: var(--primary);
    text-align: center;
    margin-bottom: 35px;
    font-size: 30px;
    letter-spacing: 3px;
    text-transform: uppercase;
}

/* Alert messages */
.alert {
    padding: 12px 18px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 14px;
    border: 1px solid transparent;
    text-align: center;
}

.alert-success {
    background: rgba(0, 255, 136, 0.15);
    border-color: var(--primary);
    color: var(--primary);
    box-shadow: 0 0 20px rgba(0, 255, 136, 0.1);
}

.alert-error {
    background: rgba(255, 80, 80, 0.15);
    border-color: #ff5555;
    color: #ff7777;
    box-shadow: 0 0 20px rgba(255, 80, 80, 0.1);
}

.alert a {
    color: var(--primary);
    text-decoration: underline;
}

.alert a:hover {
    color: #fff;
}

.input-group {
    margin-bottom: 22px;
}

.input-group label {
    display: block;
    margin-bottom: 8px;
    color: var(--text-secondary);
    font-size: 14px;
    letter-spacing: 1px;
}

.input-group input {
    width: 100%;
    padding: 14px 15px;
    background: #0b0b0b;
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text-primary);
    outline: none;
    transition: .3s;
    font-size: 15px;
    font-family: inherit;
}

.input-group input::placeholder {
    color: var(--text-placeholder);
}

.input-group input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 15px var(--glow);
}

button {
    width: 100%;
    margin-top: 12px;
    padding: 15px;
    border: none;
    border-radius: 10px;
    background: var(--gradient-primary);
    color: #000;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: .3s;
    font-family: inherit;
}

button:hover {
    transform: translateY(-2px);
    box-shadow: 0 0 25px var(--shadow-strong);
}

button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.footer {
    text-align: center;
    margin-top: 22px;
    color: var(--text-muted);
    font-size: 13px;
}

.footer a {
    color: var(--primary);
    text-decoration: none;
}

.footer a:hover {
    text-decoration: underline;
}

.forgot-password {
    text-align: right;
    margin-top: -8px;
    margin-bottom: 20px;
}

.forgot-password a {
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 13px;
    transition: .3s;
}

.forgot-password a:hover {
    color: var(--primary);
    text-shadow: 0 0 10px var(--glow);
    text-decoration: underline;
}


.remember-me {
    margin: -6px 0 14px;
}

.remember-me-label {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-secondary);
    font-size: 13px;
    cursor: pointer;
    letter-spacing: 1px;
}

.remember-me-input { /* native checkbox keeps default styling */ }
/* ===== RESPONSIVE ===== */
@media (max-width: 480px) {
    .login-box {
        padding: 30px 20px;
        margin: 10px;
    }
    .login-box h1 {
        font-size: 24px;
        margin-bottom: 25px;
    }
    button {
        font-size: 14px;
        padding: 12px;
    }
    .input-group input {
        font-size: 14px;
        padding: 12px 14px;
    }
    .main-wrapper {
        padding: 10px;
    }
}

/* برای گوشی‌های بسیار کوچک */
@media (max-width: 380px) {
    .login-box {
        padding: 20px 15px;
    }
    .login-box h1 {
        font-size: 20px;
    }
    button {
        font-size: 13px;
        padding: 10px;
    }
}
</style>
</head>

<body>
    <!-- هدر را با include اضافه می‌کنیم -->
    <?php include 'header.php'; ?>

    <div class="main-wrapper">
        <div class="login-box">

            <h1>Login</h1>

            <?php if (isset($success) && $success): ?>
                <!-- Success message -->
                <div class="alert alert-success">
                    ✅ Login successful! You will be redirected to the panel in 3 seconds.<br>
                    <small>If you are not redirected, <a href="panel.php">click here</a>.</small>
                </div>
                <!-- Optionally hide the form after success -->
                <!-- The meta refresh will handle redirection -->
            <?php elseif (isset($error)): ?>
                <!-- Error message -->
                <div class="alert alert-error">
                    ❌ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Show the form only if not successfully logged in -->
            <?php if (!isset($success) || !$success): ?>
            <form action="login.php" method="POST">
                <div class="input-group">
                    <label>Email</label>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        placeholder="Enter your email"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        required>
                </div>

                <div class="input-group">
                    <label>Password</label>
                    <input
                        type="password"
                        name="password"
                        id="password"
                        placeholder="Enter your password"
                        required>
                </div>

                <div class="forgot-password">
                    <a href="forgot_password.php">Forgot your password?</a>
                </div>
                <div class="remember-me">
                    <label class="remember-me-label">
                        <input type="checkbox" name="remember_me" value="1">
                        <span>Remember me</span>
                    </label>
                </div>

                <button type="submit">Sign In</button>
            </form>

            <div class="footer">
                Don't have an account?
                <a href="register.php">Create one</a>
            </div>
            <?php else: ?>
                <!-- After success, show a disabled button or nothing, but the meta refresh will redirect -->
                <div style="text-align:center; color:var(--text-muted); margin-top:10px;">
                    Redirecting...
                </div>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>