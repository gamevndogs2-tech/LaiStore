<?php
session_start();

$host = 'sql305.infinityfree.com';
$user = 'if0_42777594';
$pass = 'NHAP_MAT_KHAU_VPANEL_CUA_BAN'; // Thay bằng Mật khẩu vPanel MySQL của bạn
$db   = 'if0_42777594_LaiStore';

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