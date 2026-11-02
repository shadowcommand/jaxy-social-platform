<?php
// فایل: header.php
// این فایل فقط شامل کد هدر و استایل‌های مربوط به هدر است (بدون تگ‌های DOCTYPE، html، head، body)
// برای استفاده در صفحات، کافی است بعد از تگ <body> آن را include کنید.
?>
<style>
    /* ==========================
       استایل‌های هدر (با تم جاکسی)
       ========================== */
    @import url('https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap');

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

    .header {
        font-family: 'Share Tech Mono', monospace;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        padding: 20px 30px 15px;
        border-bottom: 1px solid var(--border);
        margin-bottom: 25px;
        background: var(--bg-primary);
        position: relative;
        z-index: 2;
    }

    .logo {
        font-size: 2rem;
        font-weight: bold;
        color: var(--primary);
        text-shadow: 0 0 20px var(--glow);
        letter-spacing: 2px;
        text-decoration: none;
        font-family: inherit;
    }
    .logo span {
        color: var(--text-secondary);
    }

    .nav-links {
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }

    .nav-btn {
        padding: 8px 20px;
        border: 1px solid var(--primary);
        border-radius: 30px;
        background: transparent;
        color: var(--primary);
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: bold;
        letter-spacing: 1px;
        transition: all 0.3s ease;
        cursor: pointer;
        white-space: nowrap;
        font-family: inherit;
    }

    .nav-btn:hover {
        background: var(--primary);
        color: #000;
        box-shadow: 0 0 25px var(--shadow-strong);
        transform: scale(1.05);
    }

    .nav-btn.logout {
        border-color: #ff4455;
        color: #ff4455;
    }
    .nav-btn.logout:hover {
        background: #ff4455;
        color: #000;
        box-shadow: 0 0 25px rgba(255,68,85,0.4);
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--primary);
        box-shadow: 0 0 15px var(--glow);
        background: #0a0a0a;
    }

    .user-details {
        display: flex;
        flex-direction: column;
        line-height: 1.2;
        font-family: inherit;
    }
    .user-details .fullname {
        font-size: 1rem;
        color: var(--text-primary);
    }
    .user-details .username {
        font-size: 0.8rem;
        color: var(--text-muted);
    }

    /* ===== RESPONSIVE HEADER ===== */
    @media (max-width: 820px) {
        .header {
            flex-direction: column;
            align-items: stretch;
            gap: 12px;
            padding: 15px;
        }
        .logo {
            text-align: center;
            font-size: 1.8rem;
        }
        .nav-links {
            justify-content: center;
            gap: 10px;
        }
        .user-info {
            justify-content: center;
        }
        .nav-btn {
            padding: 6px 16px;
            font-size: 0.85rem;
        }
    }

    @media (max-width: 480px) {
        .header {
            padding: 12px 10px;
        }
        .logo {
            font-size: 1.5rem;
        }
        .nav-links {
            gap: 8px;
        }
        .nav-btn {
            padding: 5px 12px;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }
        .user-avatar {
            width: 32px;
            height: 32px;
        }
        .user-details .fullname {
            font-size: 0.85rem;
        }
        .user-details .username {
            font-size: 0.7rem;
        }
    }

    @media (max-width: 400px) {
        .nav-links {
            flex-direction: column;
            align-items: center;
            width: 100%;
        }
        .nav-btn {
            width: 100%;
            text-align: center;
        }
        .user-info {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<header class="header">
    <a href="index.php" class="logo">jaxy<span>.org</span></a>
    <div class="nav-links">
        <?php if (isset($_SESSION['user_id'])): 
            // اگر کاربر لاگین است، اطلاعات را از دیتابیس بگیریم
            require_once __DIR__ . '/mysql.php';
            // require_once 'mysql.php';  **BOMB**
            $userId = (int)$_SESSION['user_id'];
            $userData = null;
            $stmt = $mysql->prepare('SELECT fullname, username, avatar FROM users WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $userData = $row;
                }
                $stmt->close();
            }
            if ($userData): ?>
            <div class="user-info">
                <img class="user-avatar" 
                     src="<?php echo htmlspecialchars($userData['avatar'] ?? 'uploads/avatars/default-avatar.png'); ?>" 
                     alt="avatar">
                <div class="user-details">
                    <span class="fullname"><?php echo htmlspecialchars($userData['fullname']); ?></span>
                    <span class="username">@<?php echo htmlspecialchars($userData['username']); ?></span>
                </div>
            </div>
            <a href="panel.php" class="nav-btn">Panel</a>
            <a href="new_tweet.php" class="nav-btn">New Tweet</a>
            <a href="explore_tweet.php" class="nav-btn">Explore</a>
            <a href="all_users.php" target="_blank" class="nav-btn">All Users</a>
            <a href="profile.php?userId=<?php echo $userId; ?>" class="nav-btn">My Profile</a>
            <a href="logout.php" class="nav-btn logout">Logout</a>
            <?php endif; ?>
        <?php else: ?>
            <a href="explore_tweet.php" class="nav-btn">Explore</a>
            <a href="login.php" class="nav-btn">Login</a>
            <a href="register.php" class="nav-btn">Register</a>
            <a href="all_users.php" target="_blank" class="nav-btn">All Users</a>
        <?php endif; ?>
    </div>
</header>