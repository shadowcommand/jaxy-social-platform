<?php
// این صفحه برای همه قابل مشاهده است (لاگین یا غیر لاگین)
session_start();
header('Content-Type: text/html; charset=UTF-8');

// داده‌ها با XMLHttpRequest از tweets.php (خروجی JSON) گرفته و رندر می‌شوند
?>
<!DOCTYPE html>
<html lang="fa" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore Tweets - jaxy.org</title>
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <style>
        /* =============================================
           THEME: from them-web-site.txt (hacker terminal)
           هدر توسط header.php مدیریت می‌شود
           ============================================= */
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
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: radial-gradient(circle at 50% 50%, var(--radial-glow), transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        /* ===== CONTENT ===== */
        .content {
            flex: 1;
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
            padding: 20px 30px 40px;
            position: relative;
            z-index: 1;
        }

        .content h1 {
            color: var(--text-secondary);
            font-weight: 400;
            letter-spacing: 3px;
            margin-bottom: 25px;
            border-bottom: 1px dashed var(--border);
            padding-bottom: 10px;
            font-size: 1.4rem;
        }

        /* ===== TWEET CARD (فِلَت - بدون باکس کاربر) ===== */
        .tweet-item {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px 20px;
            margin-bottom: 18px;
            backdrop-filter: blur(4px);
            transition: .3s;
        }
        .tweet-item:hover {
            border-color: var(--primary);
            box-shadow: 0 0 25px var(--shadow);
        }

        .tweet-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        .tweet-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid var(--border);
            flex-shrink: 0;
        }
        .tweet-fullname {
            font-weight: bold;
            color: var(--text-primary);
            font-size: 1rem;
        }
        .tweet-username {
            color: var(--text-muted);
            font-size: .85rem;
            margin-left: 6px;
        }
        .tweet-time {
            color: var(--text-placeholder);
            font-size: .75rem;
            display: block;
            margin-top: 2px;
        }

        .tweet-content {
            color: var(--text-secondary);
            font-size: 1rem;
            line-height: 1.6;
            word-wrap: break-word;
            white-space: pre-wrap;
            padding-left: 54px; /* هم‌راستا با آواتار */
        }
        .tweet-date {
            color: var(--text-placeholder);
            font-size: .75rem;
            margin-top: 10px;
            padding-left: 54px;
            direction: ltr;
            text-align: left;
        }
        .tweet-date .date-label {
            color: var(--primary-dark);
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
        .page-footer {
            text-align: center;
            padding: 30px;
            border-top: 1px solid var(--border);
            color: var(--text-muted);
            font-size: .85rem;
            position: relative;
            z-index: 2;
        }
        .page-footer a {
            color: var(--primary);
            text-decoration: none;
        }
    </style>
</head>
<body>

    <!-- هدر از فایل جداگانه (برای همه، لاگین یا غیر لاگین) -->
    <?php require_once 'header.php'; ?>

    <main class="content">
        <h1>EXPLORE TWEETS</h1>
        <div id="tweetsContainer">
            <div class="state-box">
                <span class="loader"></span> Loading tweets...
            </div>
        </div>
    </main>

    <footer class="page-footer">
        &copy; <span id="year"></span> <a href="index.php">jaxy.org</a> &ndash; Built with <span style="color: var(--primary);">&hearts;</span> &amp; code.
    </footer>

    <script>
        (function () {
            'use strict';

            document.getElementById('year').textContent = new Date().getFullYear();

            var container = document.getElementById('tweetsContainer');

            // ===== پیام وضعیت =====
            function showState(text, isError) {
                container.innerHTML = '';
                var box = document.createElement('div');
                box.className = 'state-box' + (isError ? ' error' : '');
                box.textContent = text;
                container.appendChild(box);
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

            // ===== رندر فلت توییت‌ها (جدید → قدیم، طبق ORDER BY در tweets.php) =====
            function renderTweets(tweets) {
                container.innerHTML = '';

                if (!Array.isArray(tweets) || tweets.length === 0) {
                    showState('No tweets found.');
                    return;
                }

                tweets.forEach(function (tweet) {
                    // کارت هر توییت به صورت مستقل
                    var item = document.createElement('article');
                    item.className = 'tweet-item';

                    // هدر: آواتار + نام + @username + زمان کوتاه
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

                    var nameRow = document.createElement('div');
                    var name = document.createElement('span');
                    name.className = 'tweet-fullname';
                    name.textContent = tweet.fullname || '';

                    var username = document.createElement('span');
                    username.className = 'tweet-username';
                    username.textContent = '@' + (tweet.username || '');

                    nameRow.appendChild(name);
                    nameRow.appendChild(username);

                    var time = document.createElement('span');
                    time.className = 'tweet-time';
                    time.textContent = formatDate(tweet.created_at);

                    info.appendChild(nameRow);
                    info.appendChild(time);

                    head.appendChild(img);
                    head.appendChild(info);

                    // محتوا (فقط متن → امن)
                    var content = document.createElement('div');
                    content.className = 'tweet-content';
                    content.textContent = tweet.content || '';

                    // تاریخ کامل (LTR)
                    var date = document.createElement('div');
                    date.className = 'tweet-date';
                    date.innerHTML = '<span class="date-label">DATE: </span>';
                    date.appendChild(document.createTextNode(tweet.created_at || ''));

                    item.appendChild(head);
                    item.appendChild(content);
                    item.appendChild(date);

                    container.appendChild(item);
                });
            }

            // ===== درخواست AJAX با XMLHttpRequest =====
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

                        renderTweets(data);
                    } else {
                        showState('Request failed (status ' + xhr.status + ').', true);
                    }
                }
            };
            xhr.onerror = function () {
                showState('Network error: could not reach tweets.php.', true);
            };
            xhr.send();
        })();
    </script>

</body>
</html>
