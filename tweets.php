<?php
session_start();

require_once 'mysql.php';

// ==== POST: ذخیره توییت جدید (دریافت JSON ارسالی از XMLHttpRequest در new_tweet.php) ====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // بررسی لاگین بودن
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['ok' => false, 'message' => 'Not logged in.']);
        exit;
    }

    // خواندن بدنه JSON خام
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody, true);
    if (!is_array($data)) {
        $data = [];
    }

    $action = $data['action'] ?? '';
    $userId = (int)$_SESSION['user_id'];

    if ($action === 'add_tweet') {
        $content = trim((string)($data['content'] ?? ''));

        if (mb_strlen($content, 'UTF-8') < 1) {
            echo json_encode(['ok' => false, 'message' => 'Tweet cannot be empty.']);
            exit;
        }
        if (mb_strlen($content, 'UTF-8') > 500) {
            echo json_encode(['ok' => false, 'message' => 'Tweet must be 500 characters or less.']);
            exit;
        }

        // ذخیره در دیتابیس (با پشتیبانی از ایموجی و UTF-8)
        $stmt = $mysql->prepare('INSERT INTO tweets (user_id, content) VALUES (?, ?)');
        $stmt->bind_param('is', $userId, $content);
        if ($stmt->execute()) {
            echo json_encode(['ok' => true, 'message' => 'Tweet posted successfully!']);
        } else {
            echo json_encode(['ok' => false, 'message' => 'Failed to post tweet. Please try again.']);
        }
        $stmt->close();
        exit;
    }

    // action ناشناخته
    echo json_encode(['ok' => false, 'message' => 'Invalid action.']);
    exit;
}

// ==== GET: تمام توییت‌ها به صورت فلت (مسطح) و مرتب شده از جدید به قدیم ====
$query = "
    SELECT
        t.id AS tweet_id,
        t.user_id AS user_id,
        t.content,
        t.created_at,
        u.username,
        u.fullname,
        u.avatar
    FROM tweets t
    JOIN users u ON t.user_id = u.id
    ORDER BY t.created_at DESC
";

$result = $mysql->query($query);
if ($result === false) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $mysql->error]);
    exit;
}

$tweets = [];
while ($row = $result->fetch_assoc()) {
    $tweets[] = $row;
}

header('Content-Type: application/json');
// header('Access-Control-Allow-Origin: *');
echo json_encode($tweets, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
