<?php
session_start();
require_once 'mysql.php';

// بررسی لاگین بودن
if (!isset($_SESSION['user_id'])) {
    // با جاوااسکریپت پیام داده و به لاگین هدایت می‌شود
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Redirecting...</title>
        <script>
            alert("You must be logged in to access this page.");
            window.location.href = "login.php";
        </script>
    </head>
    <body></body>
    </html>';
    exit;
}

$userId = (int)$_SESSION['user_id'];

// دریافت اطلاعات کاربر جاری برای نمایش در هدر (دیگر نیاز نیست چون header.php این کار را انجام می‌دهد)
// اما ممکن است برای نمایش در خود صفحه نیاز داشته باشیم، ولی با خیال راحت می‌توانیم از header.php استفاده کنیم.
// با این حال برای استفاده در بخش توییت‌ها از اطلاعات کاربر استفاده نمی‌کنیم، پس نیازی به fetch مجدد نیست.

// پردازش عملیات (فقط حذف توییت الان اینجا انجام می‌شود؛
// افزودن توییت جدید با AJAX و JSON به فایل tweets.php ارسال می‌شود)
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // حذف توییت
    if ($action === 'delete_tweet') {
        $tweetId = (int)($_POST['tweet_id'] ?? 0);
        if ($tweetId > 0) {
            // اطمینان از اینکه توییت متعلق به کاربر جاری است
            $stmt = $mysql->prepare('DELETE FROM tweets WHERE id = ? AND user_id = ?');
            $stmt->bind_param('ii', $tweetId, $userId);
            if ($stmt->execute()) {
                $message = 'Tweet deleted successfully.';
                $messageType = 'success';
            } else {
                $message = 'Failed to delete tweet.';
                $messageType = 'error';
            }
            $stmt->close();
        }
    }
}

// اطلاعات سشن برای فیلتر توییت‌های خودِ کاربر سمت کلاینت
$currentUserId = (int)$userId;
?>
<!DOCTYPE html>
<html lang="fa" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Tweet – jaxy.org</title>
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <style>
        /* ==========================
           استایل‌های اختصاصی new_tweet.php
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
            max-width: 900px;
            width: 100%;
        }

        /* ===== MESSAGES ===== */
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

        /* ===== FORM ===== */
        .tweet-form {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px 25px;
            margin-bottom: 30px;
            backdrop-filter: blur(4px);
        }

        .tweet-form label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-size: 1rem;
            letter-spacing: 1px;
        }

        .tweet-form textarea {
            width: 100%;
            padding: 14px 16px;
            background: #0b0b0b;
            border: 1px solid var(--border);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 1rem;
            font-family: 'Share Tech Mono', monospace;
            resize: vertical;
            min-height: 120px;
            transition: 0.3s;
        }

        .tweet-form textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 15px var(--glow);
            outline: none;
        }

        .tweet-form .char-counter {
            text-align: right;
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 6px;
        }

        .tweet-form .char-counter.limit {
            color: #ff4455;
        }

        .tweet-form button {
            margin-top: 15px;
            padding: 12px 30px;
            border: none;
            border-radius: 40px;
            background: var(--gradient-primary);
            color: #000;
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
            transition: 0.3s;
            letter-spacing: 1px;
        }

        .tweet-form button:hover {
            box-shadow: 0 0 30px var(--shadow-strong);
            transform: scale(1.02);
        }

        /* ===== TWEETS LIST ===== */
        .tweets-section {
            margin-top: 10px;
        }

        .tweets-section h2 {
            color: var(--text-secondary);
            font-weight: 400;
            letter-spacing: 2px;
            margin-bottom: 20px;
            border-bottom: 1px dashed var(--border);
            padding-bottom: 8px;
        }

        .tweet-item {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px 20px;
            margin-bottom: 18px;
            transition: 0.3s;
            backdrop-filter: blur(4px);
        }

        .tweet-item:hover {
            border-color: var(--primary);
            box-shadow: 0 0 25px var(--shadow);
        }

        .tweet-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 10px;
        }

        .tweet-avatar {
            width: 42px;
            height: 42px;
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
            margin: 8px 0 0 0;
            padding-left: 56px; /* هم‌راستا با آواتار */
        }

        .tweet-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 10px;
            padding-left: 56px;
        }

        .delete-btn {
            background: transparent;
            border: 1px solid #ff4455;
            color: #ff4455;
            padding: 5px 18px;
            border-radius: 30px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: 0.3s;
            font-family: inherit;
            letter-spacing: 1px;
        }

        .delete-btn:hover {
            background: #ff4455;
            color: #000;
            box-shadow: 0 0 20px rgba(255, 68, 85, 0.4);
        }

        .no-tweets {
            color: var(--text-muted);
            font-style: italic;
            text-align: center;
            padding: 30px 0;
        }

        /* ===== STATES ===== */
        .state-box {
            color: var(--text-muted);
            font-style: italic;
            text-align: center;
            padding: 30px 0;
        }
        .state-box.error {
            color: #ff7777;
        }
        .loader {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid var(--border);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            vertical-align: middle;
            margin-left: 8px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
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
            .tweet-content {
                padding-left: 0;
            }
            .tweet-actions {
                padding-left: 0;
                justify-content: flex-start;
            }
            .tweet-header {
                flex-wrap: wrap;
            }
        }

        @media (max-width: 480px) {
            .content-wrapper {
                padding: 10px;
            }
            .tweet-form {
                padding: 15px;
            }
            .tweet-form textarea {
                min-height: 100px;
            }
            .tweet-item {
                padding: 14px 16px;
            }
            .delete-btn {
                padding: 4px 14px;
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>

    <!-- هدر از فایل جداگانه -->
    <?php require_once 'header.php'; ?>

    <div class="content-wrapper">
        <div class="container">

            <!-- MESSAGES -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- FORM: NEW TWEET -->
            <section class="tweet-form">
                <!-- ارسال توییت با JSON و XMLHttpRequest به tweets.php -->
                <form id="tweetForm" action="" method="POST">
                    <label for="tweetContent">✍️ What's on your mind?</label>
                    <textarea id="tweetContent" name="content" maxlength="500" placeholder="Write your tweet here (max 500 chars)..."></textarea>
                    <div class="char-counter" id="charCounter">0 / 500</div>
                    <button type="button" id="tweetSubmitBtn">🐦 Tweet</button>
                </form>
            </section>

<!-- TWEETS LIST -->
            <section class="tweets-section">
                <h2>📜 Your Tweets</h2>
                <div id="tweetsContainer">
                    <div class="state-box"><span class="loader"></span> Loading tweets...</div>
                </div>
            </section>

            <!-- FOOTER -->
            <footer class="footer">
                &copy; <?php echo date('Y'); ?> <a href="/">jaxy.org</a> – Built with <span style="color: var(--primary);">&#9829;</span> &amp; code.
            </footer>

        </div>
    </div>

    <script>
        // ===== شناسه سشن (برای فیلتر توییت‌های خودِ کاربر) =====
        var CURRENT_USER_ID = <?php echo $currentUserId ? (int)$currentUserId : 0; ?>;

        // ===== ارجکها =====
        const textarea = document.getElementById('tweetContent');
        const counter = document.getElementById('charCounter');
        const maxLength = 500;
        const tweetsContainer = document.getElementById('tweetsContainer');

        // شمارنده کاراکتر
        textarea.addEventListener('input', function() {
            const current = this.value.length;
            counter.textContent = current + ' / ' + maxLength;
            if (current >= maxLength) {
                counter.classList.add('limit');
            } else {
                counter.classList.remove('limit');
            }
        });

        // ===== نمایش وضعیت (loading / empty / error) =====
        function showState(text, isError) {
            tweetsContainer.innerHTML = '';
            var box = document.createElement('div');
            box.className = 'state-box' + (isError ? ' error' : '');
            box.textContent = text;
            tweetsContainer.appendChild(box);
        }

        // ===== فرمت تاریخ =====
        function formatDate(str) {
            if (!str) return '';
            var d = new Date(String(str).replace(' ', 'T'));
            if (isNaN(d.getTime())) return str;
            return d.toLocaleString('en-US', {
                year: 'numeric', month: 'short', day: '2-digit',
                hour: '2-digit', minute: '2-digit'
            });
        }

        // ===== رندر توییت‌های خودِ کاربر از طریق API =====
        function renderTweets(tweets) {
            tweetsContainer.innerHTML = '';

            if (!Array.isArray(tweets) || tweets.length === 0) {
                showState("You haven't tweeted yet. Share something now!");
                return;
            }

            tweets.forEach(function (tweet) {
                var item = document.createElement('article');
                item.className = 'tweet-item';

                var head = document.createElement('div');
                head.className = 'tweet-header';

                var img = document.createElement('img');
                img.className = 'tweet-avatar';
                img.src = tweet.avatar || 'uploads/avatars/default-avatar.png';
                img.alt = 'avatar';
                img.onerror = function () {
                    this.src = 'uploads/avatars/default-avatar.png';
                };

                var info = document.createElement('div');
                info.className = 'tweet-user';

                var nameSpan = document.createElement('span');
                nameSpan.className = 'fullname';
                nameSpan.textContent = tweet.fullname || '';

                var userSpan = document.createElement('span');
                userSpan.className = 'username';
                userSpan.textContent = '@' + (tweet.username || '');

                var timeSpan = document.createElement('span');
                timeSpan.className = 'timestamp';
                timeSpan.textContent = formatDate(tweet.created_at) || '';

                info.appendChild(nameSpan);
                info.appendChild(userSpan);
                info.appendChild(timeSpan);

                head.appendChild(img);
                head.appendChild(info);

                var content = document.createElement('div');
                content.className = 'tweet-content';
                content.textContent = tweet.content || '';

                item.appendChild(head);
                item.appendChild(content);

                // دکمه حذف (فرم POST به خودِ new_tweet.php)
                var actions = document.createElement('div');
                actions.className = 'tweet-actions';

                var delForm = document.createElement('form');
                delForm.setAttribute('action', '');
                delForm.setAttribute('method', 'POST');
                delForm.setAttribute('onsubmit', "return confirm('Delete this tweet?');");

                var actInput = document.createElement('input');
                actInput.type = 'hidden';
                actInput.name = 'action';
                actInput.value = 'delete_tweet';

                var idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'tweet_id';
                idInput.value = tweet.tweet_id;

                var delBtn = document.createElement('button');
                delBtn.type = 'submit';
                delBtn.className = 'delete-btn';
                delBtn.textContent = '🗑 Delete';

                delForm.appendChild(actInput);
                delForm.appendChild(idInput);
                delForm.appendChild(delBtn);
                actions.appendChild(delForm);

                item.appendChild(actions);

                tweetsContainer.appendChild(item);
            });
        }

        // ===== دریافت همه‌ی توییت‌ها از tweets.php و فیلتر خودِ کاربر =====
        function loadTweets() {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'tweets.php', true);

            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        var data;
                        try {
                            data = JSON.parse(xhr.responseText);
                        } catch (e) {
                            showState('Invalid JSON response from server.', true);
                            return;
                        }

                        
                        if (data && data.error) {
                            showState('Database error: ' + data.error, true);
                            return;
                        }

                        var mine;
                        if (Array.isArray(data)) {
                            mine = data.filter(function (t) {
                                return String(t.user_id) === String(CURRENT_USER_ID);
                            });
                        } else {
                            mine = [];
                        }

                        renderTweets(mine);
                    } else {
                        showState('Request failed (status ' + xhr.status + ').', true);
                    }
                }
            };

            xhr.onerror = function () {
                showState('Network error: could not reach tweets.php.', true);
            };

            xhr.send();
        }

        // ===== ارسال توییت با JSON و XMLHttpRequest به tweets.php =====
        function submitTweet() {
            var content = textarea.value.trim();
            if (content.length < 1) {
                
                return;
            }
            if (content.length > maxLength) {
                
                return;
            }



            // ==== postMessage sender khode hamin safhe ====
            function sendNotification(text, ok) {
                window.postMessage({
                    type: "notice",
                    sender: CURRENT_USER_ID,
                    receiver: "new_tweet.php",
                    message: text,
                    ok: !!ok,
                    timestamp: Date.now()
                }, "*");
            }
            
var btn = document.getElementById('tweetSubmitBtn');
            btn.disabled = true;
            btn.textContent = 'Posting…';

            var payload = JSON.stringify({
                action: 'add_tweet',
                content: content
            });

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'tweets.php', true);
            xhr.setRequestHeader('Content-Type', 'application/json');

            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    var data = null;
                    try {
                        data = JSON.parse(xhr.responseText);
                    } catch (e) {
                        data = null;
                    }

                    if (xhr.status === 200 && data && data.ok) {
                        // پيغام موفقيت از خود API (data.message) مييايد
                        sendNotification(data.message, true);
                        // بعد از اينکه toast رفت (3 ثانيه) reload خودکار
                        setTimeout(function () { window.location.reload(); }, 3000);
                    } else {
                        btn.disabled = false;
                        btn.textContent = '🐦 Tweet';
                        // پيغام شکست از API (data.message)
                        var msg = (data && data.message) ? data.message : 'Failed to post tweet. Please try again.';
                        sendNotification(msg, false);
                        
                    }
                }
            };

            xhr.onerror = function() {
                btn.disabled = false;
                btn.textContent = '🐦 Tweet';
                //خطاي شبکه: سرور پاسخي نداده، postMessage با پيغام پيشفرض
                sendNotification('Network error: could not reach tweets.php.', false);
            };

            xhr.send(payload);
        }

        // ===== با زدن دکمه، توییت ارسال شود =====
        document.getElementById('tweetSubmitBtn').addEventListener('click', submitTweet);

        // ===== بارگذاری اولیه توییت‌ها از API با GET =====
        loadTweets();
    </script>

    <script>
    // ====== Receiver: نمايش toast به جاي console ======
    window.addEventListener('message', function (event) {
        var msg = event.data;
        if (!msg || msg.type !== 'notice') return;
        showToast(msg.message, msg.ok);
    });

    function showToast(text, ok) {
        var old = document.getElementById('jaxy-toast');
        if (old) old.remove();

        var t = document.createElement('div');
        t.id = 'jaxy-toast';
        t.textContent = text;
        t.style.cssText = "position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:99999;" +
            "background:" + (ok ? "#00b35c" : "#cc2e2e") + ";color:#fff;padding:12px 22px;border-radius:8px;" +
            "font-family:'Share Tech Mono',monospace;font-size:15px;box-shadow:0 6px 18px rgba(0,0,0,.4);";
        document.body.appendChild(t);

        // بعد از 3 ثانیه خودش حذف ميشود
        setTimeout(function () { t.remove(); }, 3000);
    }
</script>
</body>
</html>