<?php
// register.php – Simple registration with invite-only access

// If the user is already logged in, send them directly to the panel
session_start();
ini_set("pcre.jit", '0');
if (isset($_SESSION['user_id'])) {
    header('Location: panel.php');
    exit;
}
require_once 'mysql.php';  // Should define $mysql (MYSQL object)

$errors = [];
$success = false;
$old = ['fullname' => '', 'username' => '', 'email' => '', 'invite_code' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and trim inputs
    $fullname   = trim($_POST['fullname'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $invite_code = trim($_POST['invite_code'] ?? '');

    $old = compact('fullname', 'username', 'email', 'invite_code');

    // --- Validation ---
    if (empty($fullname)) {
        $errors['fullname'] = 'Full name is required.';
    }

    if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $username)) {
        $errors['username'] = 'Username must be 3–20 characters (letters, numbers, underscore).';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }

    if (empty($invite_code)) {
        $errors['invite_code'] = 'Invite code is required.';
    }

    // --- Database checks (only if no previous errors) ---
    if (empty($errors)) {
        try {
            // Check if username already exists
            $stmt = $mysql->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $errors['username'] = 'Username already taken.';
            }

            // Check if email already exists
            $stmt = $mysql->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors['email'] = 'Email already registered.';
            }

            // Check invite code
            $stmt = $mysql->prepare('SELECT id, is_used FROM invite_codes WHERE code = ?');
            $stmt->execute([$invite_code]);
            $result = $stmt->get_result();
            $invite = $result->fetch_assoc();
            if (!$invite) {
                $errors['invite_code'] = 'Invalid invite code.';
            } elseif ($invite['is_used']) {
                $errors['invite_code'] = 'This invite code has already been used.';
            }
        } catch (Exception $e) {
            $errors['general'] = 'Database error: ' . $e->getMessage();
        }
    }

    // --- If no errors, register the user ---
    if (empty($errors)) {
        try {
            $mysql->begin_transaction();

            // Hash the password
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            // Insert the new user
            $stmt = $mysql->prepare(
                'INSERT INTO users (username, fullname, email, password_hash)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$username, $fullname, $email, $passwordHash]);
            $userId = $mysql->insert_id;

            // Mark the invite code as used
            $stmt = $mysql->prepare(
                'UPDATE invite_codes
                 SET is_used = 1, used_by = ?, used_at = NOW()
                 WHERE code = ?'
            );
            $stmt->execute([$userId, $invite_code]);

            $mysql->commit();
            $success = true;

            // Clear old values so the form is emptied on success
            $old = ['fullname' => '', 'username' => '', 'email' => '', 'invite_code' => ''];

        } catch (Exception $e) {
            $mysql->rollback();
            $errors['general'] = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Access — Registration</title>

<?php if ($success): ?>
    <!-- انتقال خودکار بعد از ۲ ثانیه به panel.php -->
    <meta http-equiv="refresh" content="2;url=panel.php">
<?php endif; ?>

<style>
  /* ==========================
     استایل‌های اختصاصی register.php
     ========================== */
  @import url('https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap');

  :root{
    --bg-primary:#000000;
    --bg-secondary:#050505;
    --bg-card: rgba(10,10,10,0.85);
    --primary:#00ff88;
    --primary-dark:#00b35c;
    --border:#1d5f3b;
    --border-glow:rgba(0,255,120,.35);
    --text-primary:#ffffff;
    --text-secondary:#9dffbf;
    --text-muted:#7cb98d;
    --text-placeholder:#6f9d80;
    --shadow:rgba(0,255,120,.15);
    --shadow-strong:rgba(0,255,136,.45);
    --glow:rgba(0,255,136,.35);
    --grid-color:rgba(0,255,120,.05);
    --radial-glow:rgba(0,255,100,.08);
    --gradient-primary: linear-gradient(90deg,#00b35c,#00ff88);
    --error:#ff4466;
    --error-bg:rgba(255,68,102,0.1);
    --success:#00ff88;
  }

  *{ box-sizing:border-box; margin:0; padding:0; }

  html,body{
    height:100%;
    margin:0;
    padding:0;
  }

  body{
    font-family: 'Share Tech Mono', monospace;
    background-color:var(--bg-primary);
    background-image:
      linear-gradient(var(--grid-color) 1px, transparent 1px),
      linear-gradient(90deg, var(--grid-color) 1px, transparent 1px),
      radial-gradient(circle at 50% 0%, var(--radial-glow), transparent 60%);
    background-size: 42px 42px, 42px 42px, 100% 100%;
    background-position: center;
    color:var(--text-primary);
    display:flex;
    flex-direction:column;
    min-height:100vh;
    position:relative;
    overflow-x:hidden;
  }

  /* خط اسکن بالای صفحه */
  body::before{
    content:"";
    position:fixed;
    top:0; left:0; right:0;
    height:2px;
    background: linear-gradient(90deg, transparent, var(--primary), transparent);
    opacity:.6;
    animation: scan 6s linear infinite;
    box-shadow: 0 0 12px 2px var(--glow);
    pointer-events:none;
    z-index:999;
  }

  @keyframes scan{
    0%{ transform: translateY(-10px); }
    100%{ transform: translateY(100vh); }
  }

  @media (prefers-reduced-motion: reduce){
    body::before{ animation:none; display:none; }
  }

  /* wrapper برای کل صفحه */
  .page-wrapper {
    display:flex;
    flex-direction:column;
    flex:1;
    width:100%;
    max-width:1200px;
    margin:0 auto;
    padding:0 20px;
  }

  /* هدر توسط header.php مدیریت می‌شود، اما برای اطمینان از فاصله‌دهی مناسب */
  .header {
    margin-bottom: 20px; /* فاصله از محتوای بعدی */
  }

  /* بخش اصلی (portal) که وسط‌چین می‌شود */
  .portal-wrapper {
    flex:1;
    display:flex;
    align-items:center;
    justify-content:center;
    padding: 20px 0 40px;
  }

  .portal{
    width:100%;
    max-width:440px;
    position:relative;
  }

  .portal-header{
    text-align:center;
    margin-bottom:28px;
  }

  .portal-eyebrow{
    display:inline-flex;
    align-items:center;
    gap:8px;
    font-size:11px;
    letter-spacing:.28em;
    text-transform:uppercase;
    color:var(--text-muted);
    margin-bottom:14px;
  }

  .portal-eyebrow::before,
  .portal-eyebrow::after{
    content:"";
    width:18px;
    height:1px;
    background:var(--border);
  }

  .portal-title{
    font-size:26px;
    letter-spacing:.04em;
    color:var(--text-primary);
    text-shadow: 0 0 18px var(--shadow-strong);
  }

  .portal-title span{
    background: var(--gradient-primary);
    -webkit-background-clip:text;
    background-clip:text;
    color:transparent;
  }

  .portal-sub{
    margin-top:8px;
    font-size:12.5px;
    color:var(--text-muted);
    letter-spacing:.02em;
  }

  form{
    background:var(--bg-card);
    border:1px solid var(--border);
    border-radius:10px;
    padding:34px 32px 30px;
    box-shadow: 0 0 0 1px var(--border-glow) inset, 0 20px 60px -20px var(--shadow);
    backdrop-filter: blur(6px);
  }

  .field{
    margin-bottom:20px;
    position:relative;
  }

  .field:last-of-type{ margin-bottom:0; }

  label{
    display:flex;
    align-items:baseline;
    gap:6px;
    font-size:11.5px;
    letter-spacing:.14em;
    text-transform:uppercase;
    color:var(--text-secondary);
    margin-bottom:8px;
  }

  label .req{
    color:var(--primary);
    font-size:13px;
  }

  label .tag{
    margin-left:auto;
    font-size:9.5px;
    letter-spacing:.1em;
    color:var(--primary-dark);
    border:1px solid var(--border);
    border-radius:4px;
    padding:2px 6px;
    text-transform:uppercase;
  }

  input{
    width:100%;
    background:var(--bg-secondary);
    border:1px solid var(--border);
    border-radius:6px;
    padding:12px 14px;
    font-size:14px;
    color:var(--text-primary);
    font-family: inherit;
    outline:none;
    transition:border-color .18s ease, box-shadow .18s ease, background .18s ease;
  }

  input::placeholder{
    color:var(--text-placeholder);
  }

  input:hover{
    border-color:var(--primary-dark);
  }

  input:focus{
    border-color:var(--primary);
    box-shadow: 0 0 0 3px var(--shadow), 0 0 18px var(--glow);
    background:#070707;
  }

  input:required:invalid{
    border-color:var(--border);
  }

  .field.invite input{
    border-color:var(--primary-dark);
    box-shadow: 0 0 0 1px var(--border-glow) inset;
  }

  .field.invite input:focus{
    border-color:var(--primary);
  }

  .hint{
    margin-top:6px;
    font-size:11px;
    color:var(--text-muted);
  }

  .hint.invite-hint{
    color:var(--text-secondary);
  }

  button{
    width:100%;
    margin-top:26px;
    padding:13px 16px;
    border:none;
    border-radius:6px;
    background: var(--gradient-primary);
    color:#012014;
    font-family: inherit;
    font-weight:700;
    font-size:13.5px;
    letter-spacing:.12em;
    text-transform:uppercase;
    cursor:pointer;
    box-shadow: 0 8px 24px -8px var(--shadow-strong);
    transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
  }

  button:hover{
    filter:brightness(1.08);
    box-shadow: 0 10px 30px -6px var(--shadow-strong);
    transform: translateY(-1px);
  }

  button:active{
    transform: translateY(0);
  }

  button:focus-visible,
  input:focus-visible{
    outline: 2px solid var(--primary);
    outline-offset: 2px;
  }

  .footer-note{
    text-align:center;
    margin-top:22px;
    font-size:12px;
    color:var(--text-muted);
  }

  .footer-note a{
    color:var(--primary);
    text-decoration:none;
    border-bottom:1px dashed var(--border-glow);
  }

  .footer-note a:hover{
    color:var(--text-secondary);
  }

  /* extra styles for messages */
  .message-box {
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 20px;
    font-size: 13px;
  }
  .message-box.error {
    background: var(--error-bg);
    border-left: 4px solid var(--error);
    color: #ffaabb;
  }
  .message-box.success {
    background: rgba(0,255,136,0.08);
    border-left: 4px solid var(--success);
    color: var(--text-secondary);
  }
  .field-error {
    color: var(--error);
    font-size: 11px;
    margin-top: 4px;
    display: block;
  }

  /* ===== RESPONSIVE ===== */
  @media (max-width: 600px){
    .page-wrapper {
      padding:0 15px;
    }
    .portal-title{
      font-size:22px;
    }
    form{
      padding:28px 20px 24px;
    }
    .header {
      margin-bottom: 10px;
    }
    .portal-wrapper {
      padding: 10px 0 30px;
    }
  }

  @media (max-width: 480px){
    .portal-title{
      font-size:20px;
    }
    form{
      padding:20px 16px;
    }
    .field{
      margin-bottom:16px;
    }
    input{
      padding:10px 12px;
      font-size:13px;
    }
    button{
      padding:11px 14px;
      font-size:12.5px;
    }
    .portal-eyebrow{
      font-size:10px;
    }
    .portal-sub{
      font-size:11.5px;
    }
  }
</style>
</head>
<body>

<?php require_once 'header.php'; ?>

<div class="page-wrapper">
    <div class="portal-wrapper">
        <main class="portal">
            <div class="portal-header">
                <div class="portal-eyebrow">Access Restricted</div>
                <h1 class="portal-title">Create <span>Account</span></h1>
                <p class="portal-sub">An invite code is required to register.</p>
            </div>

            <?php if ($success): ?>
                <div class="message-box success">
                    ✅ Registration successful! You can now <a href="#" style="color:var(--primary);">sign in</a>.
                </div>
            <?php else: ?>

                <form action="" method="post" novalidate>

                    <?php if (!empty($errors['general'])): ?>
                        <div class="message-box error">
                            <?= htmlspecialchars($errors['general']) ?>
                        </div>
                    <?php endif; ?>

                    <div class="field">
                        <label for="fullname">Full name <span class="req">*</span></label>
                        <input type="text" id="fullname" name="fullname" placeholder="Jane Doe"
                               value="<?= htmlspecialchars($old['fullname']) ?>" autocomplete="name" required>
                        <?php if (!empty($errors['fullname'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['fullname']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="username">Username <span class="req">*</span></label>
                        <input type="text" id="username" name="username" placeholder="janedoe"
                               value="<?= htmlspecialchars($old['username']) ?>" autocomplete="username"
                               pattern="[A-Za-z0-9_]{3,20}" title="3-20 characters: letters, numbers, underscore" required>
                        <?php if (!empty($errors['username'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['username']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="email">Email <span class="req">*</span></label>
                        <input type="email" id="email" name="email" placeholder="jane@example.com"
                               value="<?= htmlspecialchars($old['email']) ?>" autocomplete="email" required>
                        <?php if (!empty($errors['email'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['email']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="field invite">
                        <label for="invite_code">Invite code <span class="req">*</span><span class="tag">Required</span></label>
                        <input type="text" id="invite_code" name="invite_code" placeholder="XXXX-XXXX-XXXX"
                               value="<?= htmlspecialchars($old['invite_code']) ?>" autocomplete="off" required aria-required="true">
                        <p class="hint invite-hint">Registration is invite-only. You can't proceed without a valid code.</p>
                        <?php if (!empty($errors['invite_code'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['invite_code']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="password">Password <span class="req">*</span></label>
                        <input type="password" id="password" name="password" placeholder="••••••••"
                               autocomplete="new-password" minlength="8" required>
                        <?php if (!empty($errors['password'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['password']) ?></span>
                        <?php endif; ?>
                        <p class="hint">Minimum 8 characters.</p>
                    </div>

                    <button type="submit">Request Access</button>

                    <p class="footer-note" style="margin-top:18px; text-align:center;">Already have access? <a href="/login.php">Sign in</a></p>
                </form>

            <?php endif; ?>
        </main>
    </div>
</div>

</body>
</html>