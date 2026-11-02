<?php
// ============================================================
// فایل: panel.php
// پنل کاربری با امکان ویرایش پروفایل و آپلود عکس
// ============================================================
require_once 'mysql.php';
session_start();

// بررسی لاگین بودن
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// دریافت اطلاعات کاربر (با bio)
$stmt = $mysql->prepare('SELECT id, username, fullname, email, password_hash, avatar, bio, created_at, updated_at FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// ----- پردازش فرم‌ها (همان کد قبلی) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ۱. تغییر fullname
    if ($action === 'update_fullname') {
        $newFullname = trim($_POST['fullname'] ?? '');
        if (strlen($newFullname) < 2) {
            $message = 'Full name must be at least 2 characters.';
            $messageType = 'error';
        } else {
            $stmt = $mysql->prepare('UPDATE users SET fullname = ? WHERE id = ?');
            $stmt->bind_param('si', $newFullname, $userId);
            if ($stmt->execute()) {
                $message = 'Full name updated successfully!';
                $messageType = 'success';
                $user['fullname'] = $newFullname;
            } else {
                $message = 'Failed to update full name.';
                $messageType = 'error';
            }
            $stmt->close();
        }
    }

    // ۲. تغییر email
    if ($action === 'update_email') {
        $newEmail = trim($_POST['email'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $messageType = 'error';
        } elseif (!password_verify($currentPassword, $user['password_hash'])) {
            $message = 'Incorrect current password.';
            $messageType = 'error';
        } else {
            $stmt = $mysql->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $stmt->bind_param('si', $newEmail, $userId);
            $stmt->execute();
            $checkResult = $stmt->get_result();
            if ($checkResult->num_rows > 0) {
                $message = 'This email is already registered.';
                $messageType = 'error';
            } else {
                $stmt = $mysql->prepare('UPDATE users SET email = ? WHERE id = ?');
                $stmt->bind_param('si', $newEmail, $userId);
                if ($stmt->execute()) {
                    $message = 'Email updated successfully!';
                    $messageType = 'success';
                    $user['email'] = $newEmail;
                    $_SESSION['email'] = $newEmail;
                } else {
                    $message = 'Failed to update email.';
                    $messageType = 'error';
                }
                $stmt->close();
            }
            $stmt->close();
        }
    }

    // ۳. تغییر password
    if ($action === 'update_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPassword, $user['password_hash'])) {
            $message = 'Incorrect current password.';
            $messageType = 'error';
        } elseif (strlen($newPassword) < 8) {
            $message = 'Password must be at least 8 characters.';
            $messageType = 'error';
        } elseif (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            $message = 'Password must contain at least one uppercase, one lowercase, and one digit.';
            $messageType = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'Passwords do not match.';
            $messageType = 'error';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $mysql->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->bind_param('si', $newHash, $userId);
            if ($stmt->execute()) {
                $message = 'Password changed successfully!';
                $messageType = 'success';
            } else {
                $message = 'Failed to change password.';
                $messageType = 'error';
            }
            $stmt->close();
        }
    }

    // ۴. تغییر bio
    if ($action === 'update_bio') {
        $newBio = trim($_POST['bio'] ?? '');
        if (strlen($newBio) > 500) {
            $message = 'Bio must be 500 characters or less.';
            $messageType = 'error';
        } else {
            $stmt = $mysql->prepare('UPDATE users SET bio = ? WHERE id = ?');
            $stmt->bind_param('si', $newBio, $userId);
            if ($stmt->execute()) {
                $message = 'Bio updated successfully!';
                $messageType = 'success';
                $user['bio'] = $newBio;
            } else {
                $message = 'Failed to update bio.';
                $messageType = 'error';
            }
            $stmt->close();
        }
    }

    // ۵. آپلود عکس پروفایل
    if ($action === 'upload_avatar') {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['avatar']['tmp_name'];
            $fileName = $_FILES['avatar']['name'];
            $fileSize = $_FILES['avatar']['size'];
            $fileType = mime_content_type($fileTmpPath);
            
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxFileSize = 2 * 1024 * 1024;

            if (!in_array($fileType, $allowedTypes)) {
                $message = 'Only JPG, PNG, GIF, and WEBP images are allowed.';
                $messageType = 'error';
            } elseif ($fileSize > $maxFileSize) {
                $message = 'File size must be less than 2 MB.';
                $messageType = 'error';
            } else {
                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                $newFileName = 'avatar_' . $userId . '_' . time() . '.' . $fileExtension;
                $uploadDir = 'uploads/avatars/';
                $destPath = $uploadDir . $newFileName;

                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true)) {
                        $message = 'Failed to create upload directory. Please check server permissions.';
                        $messageType = 'error';
                        goto end_avatar_upload;
                    }
                }

                if (!is_writable($uploadDir)) {
                    $message = 'Upload directory is not writable. Please contact administrator.';
                    $messageType = 'error';
                    goto end_avatar_upload;
                }

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $oldAvatar = $user['avatar'];
                    if ($oldAvatar && $oldAvatar !== 'uploads/avatars/default-avatar.png' && file_exists($oldAvatar)) {
                        unlink($oldAvatar);
                    }

                    $stmt = $mysql->prepare('UPDATE users SET avatar = ? WHERE id = ?');
                    $stmt->bind_param('si', $destPath, $userId);
                    if ($stmt->execute()) {
                        $message = 'Profile picture updated successfully!';
                        $messageType = 'success';
                        $user['avatar'] = $destPath;
                    } else {
                        $message = 'Failed to update database.';
                        $messageType = 'error';
                        unlink($destPath);
                    }
                    $stmt->close();
                } else {
                    $message = 'Failed to upload file. Please check directory permissions.';
                    $messageType = 'error';
                }
            }
        } else {
            $message = 'Please select an image.';
            $messageType = 'error';
        }
        end_avatar_upload:
        // nothing
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <style>
        /* ==========================
           استایل‌های اختصاصی panel.php
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
            margin-bottom: 0; /* حذف margin اضافی */
        }

        /* wrapper برای محتوای پنل */
        .panel-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        .panel-container {
            max-width: 1100px;
            width: 100%;
            background: var(--bg-card);
            backdrop-filter: blur(4px);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 30px 35px;
            box-shadow: 0 20px 60px var(--shadow), inset 0 0 80px rgba(0,255,120,.04);
            transition: all 0.3s ease;
        }

        .panel-container:hover {
            border-color: var(--primary);
            box-shadow: 0 0 40px var(--border-glow), inset 0 0 80px rgba(0,255,120,.06);
        }

        /* Alert */
        .alert {
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            border: 1px solid transparent;
            text-align: center;
        }
        .alert-success {
            background: rgba(0, 255, 136, 0.15);
            border-color: var(--primary);
            color: var(--primary);
        }
        .alert-error {
            background: rgba(255, 80, 80, 0.15);
            border-color: #ff5555;
            color: #ff7777;
        }

        /* Profile Row */
        .profile-row {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }

        .avatar-section {
            flex: 0 0 180px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }

        .avatar-section img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
            box-shadow: 0 0 30px var(--glow);
            background: #0a0a0a;
        }

        .upload-form {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .upload-form label {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-secondary);
            padding: 8px;
            border-radius: 30px;
            text-align: center;
            font-size: 0.85rem;
            cursor: pointer;
            transition: 0.3s;
        }

        .upload-form label:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .upload-form input[type="file"] {
            display: none;
        }

        .upload-form button {
            padding: 8px;
            border: none;
            border-radius: 30px;
            background: var(--gradient-primary);
            color: #000;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            font-family: inherit;
        }

        .upload-form button:hover {
            box-shadow: 0 0 20px var(--shadow-strong);
            transform: scale(1.02);
        }

        .info-section {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px 25px;
            align-content: start;
        }

        .info-item {
            background: rgba(255,255,255,.02);
            border-left: 2px solid var(--primary);
            padding: 10px 14px;
            border-radius: 8px;
        }

        .info-item .label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--text-muted);
            display: block;
            margin-bottom: 4px;
        }

        .info-item .value {
            color: var(--text-primary);
            font-size: 1rem;
            word-break: break-word;
        }

        .info-item .value.username {
            color: var(--text-secondary);
        }

        .bio-item {
            grid-column: span 2;
        }

        .bio-display {
            color: var(--text-secondary);
            font-style: italic;
            padding: 4px 0;
        }

        /* Edit Section */
        .edit-section {
            margin-top: 10px;
        }

        .edit-section h2 {
            font-weight: 400;
            color: var(--text-secondary);
            letter-spacing: 2px;
            border-bottom: 1px dashed var(--border);
            padding-bottom: 8px;
            margin-bottom: 20px;
        }

        .edit-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .edit-card {
            background: rgba(255,255,255,.02);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px 20px;
            transition: 0.3s;
        }

        .edit-card:hover {
            border-color: var(--primary);
            box-shadow: 0 0 20px var(--shadow);
        }

        .edit-card h3 {
            color: var(--text-secondary);
            font-weight: 400;
            font-size: 1.1rem;
            margin-bottom: 12px;
            letter-spacing: 1px;
        }

        .edit-card label {
            display: block;
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-bottom: 4px;
            margin-top: 10px;
        }

        .edit-card label:first-of-type {
            margin-top: 0;
        }

        .edit-card input,
        .edit-card textarea {
            width: 100%;
            padding: 10px 14px;
            background: #0b0b0b;
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text-primary);
            font-family: inherit;
            font-size: 0.95rem;
            transition: 0.3s;
        }

        .edit-card input:focus,
        .edit-card textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 15px var(--glow);
            outline: none;
        }

        .edit-card textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn-submit {
            margin-top: 14px;
            padding: 10px 25px;
            border: none;
            border-radius: 30px;
            background: var(--gradient-primary);
            color: #000;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            font-family: inherit;
            letter-spacing: 1px;
        }

        .btn-submit:hover {
            box-shadow: 0 0 25px var(--shadow-strong);
            transform: scale(1.02);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .panel-container {
                padding: 20px;
            }
            .profile-row {
                flex-direction: column;
                align-items: center;
            }
            .avatar-section {
                flex: 0 0 auto;
                width: 100%;
                max-width: 200px;
            }
            .info-section {
                grid-template-columns: 1fr;
                width: 100%;
            }
            .bio-item {
                grid-column: span 1;
            }
            .edit-grid {
                grid-template-columns: 1fr;
            }
            .edit-card[style*="grid-column: span 2;"] {
                grid-column: span 1 !important;
            }
            .edit-card form[style*="display: grid;"] {
                display: flex !important;
                flex-direction: column !important;
                gap: 12px !important;
            }
            .edit-card form[style*="display: grid;"] > div {
                width: 100%;
            }
            .edit-card form[style*="display: grid;"] > div:last-child {
                align-items: stretch !important;
            }
            .edit-card form[style*="display: grid;"] button {
                width: 100%;
                margin-top: 0;
            }
        }

        @media (max-width: 480px) {
            .panel-container {
                padding: 15px;
            }
            .avatar-section img {
                width: 120px;
                height: 120px;
            }
            .edit-card {
                padding: 14px 16px;
            }
            .btn-submit {
                width: 100%;
                text-align: center;
            }
            .upload-form button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- هدر در بیرون از کادر قرار دارد -->
    <?php require_once 'header.php'; ?>

    <div class="panel-wrapper">
        <div class="panel-container">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="profile-row">
                <div class="avatar-section">
                    <img src="<?php echo htmlspecialchars($user['avatar'] ?? 'uploads/avatars/default-avatar.png'); ?>" alt="Avatar">
                    <form action="" method="POST" enctype="multipart/form-data" class="upload-form">
                        <input type="hidden" name="action" value="upload_avatar">
                        <label for="avatarInput">📸 Change Photo</label>
                        <input type="file" name="avatar" id="avatarInput" accept="image/*" required>
                        <button type="submit">Upload</button>
                    </form>
                </div>
                <div class="info-section">
                    <div class="info-item">
                        <span class="label">Username</span>
                        <span class="value username"><?php echo htmlspecialchars($user['username']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Full Name</span>
                        <span class="value"><?php echo htmlspecialchars($user['fullname']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Email</span>
                        <span class="value"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Member Since</span>
                        <span class="value"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Last Updated</span>
                        <span class="value"><?php echo date('M d, Y H:i', strtotime($user['updated_at'] ?? $user['created_at'])); ?></span>
                    </div>
                    <div class="info-item bio-item">
                        <span class="label">📝 Bio</span>
                        <div class="bio-display">
                            <?php 
                                $bio = trim($user['bio'] ?? '');
                                if (empty($bio)) {
                                    echo '<span style="color: var(--text-muted); font-style: italic;">No bio yet</span>';
                                } else {
                                    echo nl2br(htmlspecialchars($bio));
                                }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="edit-section">
                <h2>✏️ Edit Profile</h2>
                <div class="edit-grid">
                    <div class="edit-card">
                        <h3>Full Name</h3>
                        <form action="" method="POST">
                            <input type="hidden" name="action" value="update_fullname">
                            <label for="fullname">New Full Name</label>
                            <input type="text" name="fullname" id="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                            <button type="submit" class="btn-submit">Update Name</button>
                        </form>
                    </div>

                    <div class="edit-card">
                        <h3>Email Address</h3>
                        <form action="" method="POST">
                            <input type="hidden" name="action" value="update_email">
                            <label for="email">New Email</label>
                            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            <label for="current_password_email">Current Password</label>
                            <input type="password" name="current_password" id="current_password_email" placeholder="Enter current password" required>
                            <button type="submit" class="btn-submit">Update Email</button>
                        </form>
                    </div>

                    <div class="edit-card" style="grid-column: span 2;">
                        <h3>Change Password</h3>
                        <form action="" method="POST" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <input type="hidden" name="action" value="update_password">
                            <div>
                                <label for="current_password_pass">Current Password</label>
                                <input type="password" name="current_password" id="current_password_pass" placeholder="Current password" required>
                            </div>
                            <div>
                                <label for="new_password">New Password</label>
                                <input type="password" name="new_password" id="new_password" placeholder="Min 8 chars, with uppercase, lowercase, digit" required>
                            </div>
                            <div>
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" placeholder="Re-enter new password" required>
                            </div>
                            <div style="display: flex; align-items: flex-end;">
                                <button type="submit" class="btn-submit" style="margin-top: 0;">Change Password</button>
                            </div>
                        </form>
                    </div>

                    <div class="edit-card" style="grid-column: span 2;">
                        <h3>📝 Bio</h3>
                        <form action="" method="POST">
                            <input type="hidden" name="action" value="update_bio">
                            <label for="bio">Tell us about yourself (max 500 characters)</label>
                            <textarea name="bio" id="bio" rows="4" placeholder="Write a short bio..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            <button type="submit" class="btn-submit">Update Bio</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // پیش‌نمایش بصری عکس (بدون تغییر)
    document.addEventListener('DOMContentLoaded', function() {
        const fileInput = document.getElementById('avatarInput');
        const avatarImg = document.querySelector('.avatar-section img');
        const uploadBtn = document.querySelector('.avatar-section .upload-form button[type="submit"]');

        const style = document.createElement('style');
        style.textContent = `
            .avatar-selected {
                animation: avatarPulse 1.2s ease-in-out infinite alternate;
                border-color: #ffcc00 !important;
                box-shadow: 0 0 30px rgba(255, 204, 0, 0.7) !important;
            }
            @keyframes avatarPulse {
                0% { transform: scale(1); box-shadow: 0 0 20px rgba(255,204,0,0.5); }
                100% { transform: scale(1.05); box-shadow: 0 0 50px rgba(255,204,0,0.9); }
            }
            .upload-btn-highlight {
                background: #ffcc00 !important;
                color: #000 !important;
                transform: scale(1.08);
                box-shadow: 0 0 30px rgba(255,204,0,0.6);
                transition: all 0.3s ease;
            }
        `;
        document.head.appendChild(style);

        fileInput.addEventListener('change', function(e) {
            const file = this.files[0];
            if (!file) {
                avatarImg.classList.remove('avatar-selected');
                uploadBtn.classList.remove('upload-btn-highlight');
                return;
            }

            const reader = new FileReader();
            reader.onload = function(ev) {
                avatarImg.src = ev.target.result;
                avatarImg.classList.add('avatar-selected');
                uploadBtn.classList.add('upload-btn-highlight');
                uploadBtn.textContent = '⬆ Upload Now';
            };
            reader.readAsDataURL(file);
        });
    });
    </script>
</body>
</html>