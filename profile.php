<?php
session_start();
require_once 'mysql.php'; // برای دسترسی به دیتابیس برای توییت‌ها

// دریافت userId از GET
$userId = isset($_GET['userId']) ? intval($_GET['userId']) : 0;

if ($userId <= 0) {
    header('Location: panel.php');
    exit;
}

// تابع دریافت اطلاعات از API با file_get_contents (بدون curl)
function getUserFromApi($userId) {
    $url = "http://127.0.0.1:5000/api/user/" . $userId;

    $options = [
        'http' => [
            'method' => 'GET',
            'header' => "Accept: application/json\r\n",
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ];
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return ['error' => 'API connection failed. Make sure Flask is running.'];
    }

    if (isset($http_response_header[0])) {
        $statusCode = (int) explode(' ', $http_response_header[0])[1];
        if ($statusCode === 404) {
            return ['error' => 'User not found'];
        } elseif ($statusCode !== 200) {
            return ['error' => 'API error (HTTP ' . $statusCode . ')'];
        }
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Invalid JSON response'];
    }

    return $data;
}

$userData = getUserFromApi($userId);
$errorMessage = null;
$user = null;

if (isset($userData['error'])) {
    $errorMessage = $userData['error'];
} else {
    $user = $userData;
}

// دریافت توییت‌های کاربر از دیتابیس (مستقیم)
$tweets = [];
if ($user) {
    $query = "
        SELECT 
            t.id AS tweet_id,
            t.content,
            t.created_at,
            u.fullname,
            u.username,
            u.avatar
        FROM tweets t
        JOIN users u ON t.user_id = u.id
        WHERE t.user_id = ?
        ORDER BY t.created_at DESC
    ";
    $stmt = $mysql->prepare($query);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $tweets[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Profile - jaxy.org</title>
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <style>
        /* ==========================
           HACKER THEME – jaxy.org (Profile)
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
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            background: #000000;
            background-image: 
                linear-gradient(var(--grid-color) 1px, transparent 1px),
                linear-gradient(90deg, var(--grid-color) 1px, transparent 1px);
            background-size: 40px 40px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: 'Share Tech Mono', monospace;
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

        /* wrapper برای محتوای پروفایل */
        .profile-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        .profile-card {
            max-width: 820px;
            width: 100%;
            background: var(--bg-card);
            backdrop-filter: blur(4px);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 40px 35px;
            box-shadow: 0 20px 60px var(--shadow), inset 0 0 80px rgba(0,255,120,.04);
            transition: all 0.3s ease;
            animation: fadeSlide 0.6s ease-out;
        }
        .profile-card:hover {
            border-color: var(--primary);
            box-shadow: 0 0 40px var(--border-glow), inset 0 0 80px rgba(0,255,120,.06);
        }

        @keyframes fadeSlide {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
            flex-wrap: wrap;
            padding-bottom: 28px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 28px;
        }
        .avatar-wrapper {
            position: relative;
        }
        .avatar-wrapper img {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
            box-shadow: 0 0 30px rgba(0,255,136,.25), 0 0 60px rgba(0,255,136,.08);
            background: #0a0a0a;
            transition: all 0.4s ease;
        }
        .avatar-wrapper img:hover {
            transform: scale(1.03);
            box-shadow: 0 0 50px rgba(0,255,136,.4);
        }
        .avatar-wrapper::after {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            padding: 2px;
            background: var(--gradient-primary);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
        }
        .profile-name {
            flex: 1;
        }
        .profile-name h1 {
            font-size: 2.2rem;
            color: var(--text-primary);
            font-weight: 400;
            letter-spacing: 1px;
            margin-bottom: 2px;
            text-shadow: 0 0 20px rgba(0,255,136,.15);
        }
        .profile-name .username {
            font-size: 1.2rem;
            color: var(--text-secondary);
            letter-spacing: 2px;
        }
        .profile-name .username::before {
            content: '@';
            color: var(--text-muted);
        }
        .profile-name .member-since {
            display: inline-block;
            margin-top: 8px;
            font-size: 0.9rem;
            color: var(--text-muted);
            border: 1px solid var(--border);
            padding: 4px 14px;
            border-radius: 30px;
            letter-spacing: 1px;
            background: rgba(0,255,120,.04);
        }
        .profile-name .member-since::before {
            content: '🗲 ';
            color: var(--primary);
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 20px;
        }
        .info-item {
            background: rgba(255,255,255,.02);
            border-left: 2px solid var(--primary);
            padding: 12px 18px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .info-item:hover {
            background: rgba(0,255,120,.04);
            border-left-color: var(--primary-dark);
        }
        .info-item .label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--text-muted);
            display: block;
            margin-bottom: 4px;
        }
        .info-item .value {
            color: var(--text-primary);
            font-size: 1rem;
            word-break: break-word;
        }
        .info-item .value a {
            color: var(--primary);
            text-decoration: none;
            border-bottom: 1px dotted var(--border);
        }
        .info-item .value a:hover {
            color: var(--text-secondary);
            border-bottom-color: var(--primary);
        }
        .bio-box {
            grid-column: span 2;
            background: rgba(0,255,120,.03);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px 22px;
            margin-top: 4px;
            transition: all 0.3s ease;
        }
        .bio-box:hover {
            border-color: var(--primary);
            background: rgba(0,255,120,.05);
            box-shadow: 0 0 30px rgba(0,255,120,.05);
        }
        .bio-box .label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--text-muted);
            display: block;
            margin-bottom: 6px;
        }
        .bio-box .bio-text {
            color: #d0f0dd;
            font-size: 1rem;
            line-height: 1.6;
            font-style: italic;
        }
        .bio-box .bio-text.empty {
            color: var(--text-placeholder);
            font-style: italic;
        }

        /* ===== TWEETS SECTION ===== */
        .tweets-section {
            margin-top: 30px;
            border-top: 1px solid var(--border);
            padding-top: 20px;
        }
        .tweets-section h2 {
            color: var(--text-secondary);
            font-weight: 400;
            letter-spacing: 2px;
            margin-bottom: 20px;
            font-size: 1.4rem;
        }
        .tweet-item {
            background: rgba(255,255,255,.02);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px 18px;
            margin-bottom: 16px;
            transition: 0.3s;
        }
        .tweet-item:hover {
            border-color: var(--primary);
            box-shadow: 0 0 20px var(--shadow);
        }
        .tweet-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        .tweet-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid var(--border);
        }
        .tweet-user {
            flex: 1;
        }
        .tweet-user .fullname {
            font-weight: bold;
            color: var(--text-primary);
            font-size: 1rem;
        }
        .tweet-user .username {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-left: 6px;
        }
        .tweet-user .timestamp {
            color: var(--text-placeholder);
            font-size: 0.75rem;
            display: block;
            margin-top: 2px;
        }
        .tweet-content {
            color: var(--text-secondary);
            font-size: 1rem;
            line-height: 1.6;
            word-wrap: break-word;
            white-space: pre-wrap;
            margin-top: 4px;
            padding-left: 52px;
        }
        .no-tweets {
            color: var(--text-muted);
            font-style: italic;
            text-align: center;
            padding: 20px 0;
        }

        .error-box {
            background: rgba(255,30,30,.08);
            border: 1px solid #ff4455;
            color: #ff7788;
            padding: 20px 28px;
            border-radius: 16px;
            text-align: center;
            font-size: 1.1rem;
            letter-spacing: 0.5px;
            box-shadow: 0 0 30px rgba(255,0,0,.08);
        }
        .error-box strong {
            color: #ff5566;
        }
        .error-box .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 28px;
            background: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
            border-radius: 40px;
            text-decoration: none;
            font-weight: bold;
            font-size: 0.9rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            transition: all 0.3s ease;
        }
        .error-box .back-link:hover {
            color: #000000;
            background: var(--primary);
            box-shadow: 0 0 30px var(--shadow-strong);
            transform: scale(1.02);
        }

        .glow-line {
            width: 60px;
            height: 2px;
            background: var(--gradient-primary);
            margin: 20px auto 0;
            border-radius: 4px;
            box-shadow: 0 0 20px var(--glow);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 640px) {
            .profile-card { padding: 25px 18px; }
            .profile-header { flex-direction: column; text-align: center; gap: 15px; }
            .info-grid { grid-template-columns: 1fr; }
            .bio-box { grid-column: span 1; }
            .avatar-wrapper img { width: 100px; height: 100px; }
            .profile-name h1 { font-size: 1.6rem; }
            .tweet-content { padding-left: 0; }
            .tweet-header { flex-wrap: wrap; }
        }
        @media (max-width: 480px) {
            .profile-card { padding: 20px 14px; }
            .profile-name h1 { font-size: 1.4rem; }
            .info-item { padding: 8px 12px; }
            .bio-box { padding: 14px 16px; }
            .tweet-item { padding: 12px 14px; }
        }
    </style>
</head>
<body>
    <!-- هدر در بالای صفحه -->
    <?php require_once 'header.php'; ?>

    <div class="profile-wrapper">
        <div class="profile-card">
            <?php if ($errorMessage): ?>
                <div class="error-box">
                    <strong>⛔ ERROR</strong><br>
                    <?php echo htmlspecialchars($errorMessage); ?>
                    <div>
                        <a href="panel.php" class="back-link">← Return to Panel</a>
                    </div>
                </div>
            <?php elseif ($user): ?>
                <div class="profile-header">
                    <div class="avatar-wrapper">
                        <img src="<?php echo htmlspecialchars($user['avatar'] ?? 'uploads/avatars/default-avatar.png'); ?>" alt="Avatar">
                    </div>
                    <div class="profile-name">
                        <h1><?php echo htmlspecialchars($user['fullname'] ?? 'Unknown'); ?></h1>
                        <span class="username"><?php echo htmlspecialchars($user['username'] ?? ''); ?></span>
                        <span class="member-since">
                            <?php 
                                $joined = $user['created_at'] ?? 'now';
                                echo date('F Y', strtotime($joined));
                            ?>
                        </span>
                    </div>
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label">📧 Email</span>
                        <span class="value"><?php echo htmlspecialchars($user['email'] ?? 'Not set'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">🆔 User ID</span>
                        <span class="value">#<?php echo htmlspecialchars($user['id'] ?? ''); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">📅 Member Since</span>
                        <span class="value">
                            <?php 
                                $created = $user['created_at'] ?? null;
                                echo $created ? date('M d, Y', strtotime($created)) : 'Unknown';
                            ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="label">🔗 Profile Link</span>
                        <span class="value">
                            <a href="profile.php?userId=<?php echo $user['id']; ?>" target="_blank">
                                jaxy.org/p?u=<?php echo $user['id']; ?>
                            </a>
                        </span>
                    </div>
                </div>
                <div class="bio-box">
                    <span class="label">📝 Bio</span>
                    <div class="bio-text <?php echo empty(trim($user['bio'] ?? '')) ? 'empty' : ''; ?>">
                        <?php 
                            $bio = trim($user['bio'] ?? '');
                            if (empty($bio)) {
                                echo 'This user has not written a bio yet.';
                            } else {
                                echo nl2br(htmlspecialchars($bio));
                            }
                        ?>
                    </div>
                </div>

                <!-- ===== TWEETS ===== -->
                <div class="tweets-section">
                    <h2>🐦 Tweets</h2>
                    <?php if (count($tweets) === 0): ?>
                        <div class="no-tweets">This user hasn't tweeted yet.</div>
                    <?php else: ?>
                        <?php foreach ($tweets as $tweet): ?>
                            <div class="tweet-item">
                                <div class="tweet-header">
                                    <img class="tweet-avatar" 
                                         src="<?php echo htmlspecialchars($tweet['avatar'] ?? 'uploads/avatars/default-avatar.png'); ?>" 
                                         alt="avatar">
                                    <div class="tweet-user">
                                        <span class="fullname"><?php echo htmlspecialchars($tweet['fullname']); ?></span>
                                        <span class="username">@<?php echo htmlspecialchars($tweet['username']); ?></span>
                                        <span class="timestamp"><?php echo date('M d, Y H:i', strtotime($tweet['created_at'])); ?></span>
                                    </div>
                                </div>
                                <div class="tweet-content">
                                    <?php echo nl2br(htmlspecialchars($tweet['content'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="glow-line"></div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>