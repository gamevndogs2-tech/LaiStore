<?php
session_start();

$host = '????????';
$user = '????????';
$pass = '??????'; // Thay bằng Mật khẩu vPanel MySQL của bạn
$db   = '???????';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Lỗi kết nối cơ sở dữ liệu: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}
?>
