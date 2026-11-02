<?php
session_start();
require_once 'mysql.php';

// دریافت لیست تمام کاربران به جز خود کاربر (اختیاری) یا همه کاربران
$users = [];
$query = "SELECT id, username, fullname, avatar, bio, created_at FROM users ORDER BY created_at DESC";
$result = $mysql->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $result->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Users - jaxy.org</title>
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <style>
        /* ==========================
           استایل‌های اختصاصی all_users.php
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
            padding: 20px 30px;
            position: relative;
            z-index: 1;
        }

        .container {
            max-width: 1200px;
            width: 100%;
        }

        /* ===== PAGE TITLE ===== */
        .page-title {
            font-size: 2.5rem;
            font-weight: 400;
            color: var(--primary);
            text-shadow: 0 0 20px var(--glow);
            margin-bottom: 30px;
            letter-spacing: 2px;
            text-align: center;
        }

        /* ===== USERS GRID ===== */
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }

        .user-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 20px 22px;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            backdrop-filter: blur(4px);
        }

        .user-card:hover {
            border-color: var(--primary);
            box-shadow: 0 0 30px var(--shadow-strong), inset 0 0 30px rgba(0,255,120,.04);
            transform: translateY(-4px);
        }

        .user-card-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 12px;
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
            box-shadow: 0 0 20px var(--glow);
            background: #0a0a0a;
            flex-shrink: 0;
        }

        .user-name {
            flex: 1;
        }
        .user-name .fullname {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--text-primary);
        }
        .user-name .username {
            font-size: 0.95rem;
            color: var(--text-muted);
        }
        .user-name .username::before {
            content: '@';
        }

        .user-bio {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.5;
            margin: 8px 0 12px;
            font-style: italic;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            max-height: 4.5em;
        }

        .user-bio.empty {
            color: var(--text-placeholder);
            font-style: italic;
        }

        .user-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid var(--border);
            padding-top: 12px;
            margin-top: 6px;
        }

        .user-since {
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        .view-profile-btn {
            padding: 5px 16px;
            border: 1px solid var(--primary);
            border-radius: 30px;
            background: transparent;
            color: var(--primary);
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            text-decoration: none;
            cursor: pointer;
        }

        .view-profile-btn:hover {
            background: var(--primary);
            color: #000;
            box-shadow: 0 0 20px var(--shadow-strong);
        }

        .no-users {
            text-align: center;
            color: var(--text-muted);
            font-size: 1.2rem;
            padding: 50px 0;
            grid-column: 1 / -1;
        }

        /* ===== FOOTER ===== */
        .footer {
            border-top: 1px solid var(--border);
            padding: 20px 0;
            margin-top: 40px;
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
            .users-grid {
                grid-template-columns: 1fr;
            }
            .page-title {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .content-wrapper {
                padding: 10px;
            }
            .user-card {
                padding: 16px 18px;
            }
            .user-avatar {
                width: 50px;
                height: 50px;
            }
            .user-name .fullname {
                font-size: 1rem;
            }
            .page-title {
                font-size: 1.6rem;
            }
            .view-profile-btn {
                font-size: 0.7rem;
                padding: 4px 12px;
            }
        }
    </style>
</head>
<body>

    <!-- هدر از فایل جداگانه -->
    <?php require_once 'header.php'; ?>

    <div class="content-wrapper">
        <div class="container">

            <!-- PAGE TITLE -->
            <h1 class="page-title">👥 All Users</h1>

            <!-- USERS GRID -->
            <div class="users-grid">
                <?php if (count($users) === 0): ?>
                    <div class="no-users">No users found.</div>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <a href="profile.php?userId=<?php echo $u['id']; ?>" target="_blank" class="user-card">
                            <div class="user-card-header">
                                <img class="user-avatar" 
                                     src="<?php echo htmlspecialchars($u['avatar'] ?? 'uploads/avatars/default-avatar.png'); ?>" 
                                     alt="avatar">
                                <div class="user-name">
                                    <div class="fullname"><?php echo htmlspecialchars($u['fullname']); ?></div>
                                    <div class="username"><?php echo htmlspecialchars($u['username']); ?></div>
                                </div>
                            </div>
                            <div class="user-bio <?php echo empty(trim($u['bio'] ?? '')) ? 'empty' : ''; ?>">
                                <?php 
                                    $bio = trim($u['bio'] ?? '');
                                    if (empty($bio)) {
                                        echo 'No bio yet';
                                    } else {
                                        echo nl2br(htmlspecialchars($bio));
                                    }
                                ?>
                            </div>
                            <div class="user-footer">
                                <span class="user-since">🗲 <?php echo date('M d, Y', strtotime($u['created_at'])); ?></span>
                                <span class="view-profile-btn">View Profile →</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- FOOTER -->
            <footer class="footer">
                &copy; <?php echo date('Y'); ?> <a href="/">jaxy.org</a> – Built with <span style="color: var(--primary);">&#9829;</span> &amp; code.
            </footer>

        </div>
    </div>

</body>
</html>