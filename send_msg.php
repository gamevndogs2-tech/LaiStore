<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Chưa đăng nhập']);
    exit();
}

$current_user = (int)$_SESSION['user_id'];

// Tìm ID đối phương (Cửa hàng / Admin)
$target_id = 1;
$res = $conn->query("SELECT id FROM users WHERE id != $current_user ORDER BY id ASC LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    $target_id = (int)$row['id'];
}

$action = $_GET['action'] ?? '';

// 1. GỬI TIN NHẮN NGẦM (AJAX)
if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['msg_content'] ?? '');
    $receiver_id = (int)($_POST['receiver_id'] ?? $target_id);

    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $current_user, $receiver_id, $message);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
            exit();
        }
    }
    echo json_encode(['status' => 'error', 'message' => 'Tin nhắn trống']);
    exit();
}

// 2. TẢI LỊCH SỬ TIN NHẮN (AJAX)
if ($action === 'fetch') {
    $stmt = $conn->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY id ASC");
    $stmt->bind_param("iiii", $current_user, $target_id, $target_id, $current_user);
    $stmt->execute();
    $result = $stmt->get_result();

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'message' => htmlspecialchars($row['message']),
            'time' => date('H:i', strtotime($row['created_at'])),
            'is_me' => ((int)$row['sender_id'] === $current_user)
        ];
    }
    echo json_encode(['status' => 'success', 'messages' => $messages]);
    exit();
}
?>