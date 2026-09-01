<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = '';
$user = '';
$pass = ''; // Mật khẩu MySQL vPanel của bạn
$db   = '';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Lỗi kết nối CSDL: " . $conn->connect_error);
}

// ÉP BẮT BUỘC KẾT NỐI SỬ DỤNG UTF-8 FULL
$conn->set_charset("utf8mb4");
mysqli_set_charset($conn, "utf8mb4");
$conn->query("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");

// KIỂM TRA XEM NGƯỜI DÙNG CÓ ĐANG TRUY CẬP TỪ ỨNG DỤNG ANDROID (APP) KHÔNG
$is_app = isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'LaiStoreApp') !== false;

function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}
?>