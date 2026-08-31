<?php
// Bật hiển thị lỗi để kiểm tra nếu có sự cố
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user = (int)$_SESSION['user_id'];

// Tự động tìm ID người nhận khác tài khoản đang đăng nhập
$target_id = 1;
$res = $conn->query("SELECT id FROM users WHERE id != $current_user ORDER BY id ASC LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    $target_id = (int)$row['id'];
}

// Xử lý khi Form gửi dữ liệu tới
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['msg_content'] ?? '');
    $receiver_id = (int)($_POST['receiver_id'] ?? 0);

    if ($receiver_id === 0 || $receiver_id === $current_user) {
        $receiver_id = $target_id;
    }

    if (!empty($message)) {
        // Sử dụng câu lệnh SQL an toàn tránh đứng trang
        $safe_message = $conn->real_escape_string($message);
        $sql = "INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES ($current_user, $receiver_id, '$safe_message', NOW())";
        
        if (!$conn->query($sql)) {
            die("Lỗi CSDL: " . $conn->error);
        }
    }

    // Chuyển hướng an toàn về trang chủ và mở khung chat
    header("Location: index.php?open_chat=1");
    exit();
}

// Nếu truy cập trực tiếp file này -> Quay về trang chủ
header("Location: index.php");
exit();
?>