<?php
// Bật session nếu chưa có
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$username = 'root';     // Mặc định của XAMPP
$password = '';         // XAMPP mặc định không có mật khẩu (để trống)
$database = 'laistoredb'; // Tên database bạn vừa tạo ở Bước 2

// Tạo kết nối MySQLi
$conn = new mysqli($host, $username, $password, $database);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Kết nối cơ sở dữ liệu thất bại: " . $conn->connect_error);
}

// Thiết lập bảng mã tiếng Việt UTF-8
$conn->set_charset("utf8mb4");

// Hàm kiểm tra đăng nhập nhanh
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}
?>