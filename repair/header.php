<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LaiStore - Mua Sắm Trực Tuyến</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="navbar">
    <div class="nav-flex">
        <a href="index.php" class="logo">🛍️ LaiStore</a>
        <nav class="nav-links">
            <a href="index.php">Trang Chủ</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php 
                $roles = explode(',', $_SESSION['user_role']);
                if (in_array('merchant', $roles)): ?>
                    <a href="products.php">Quản Lý Sản Phẩm</a>
                <?php endif; ?>
                
                <a href="cart.php">Giỏ Hàng</a>
                <a href="orders.php">Đơn Hàng</a>
                <span>(Xin chào, <b><?= htmlspecialchars($_SESSION['username']) ?></b>)</span>
                <a href="logout.php" style="color: #ef4444;">Đăng Xuất</a>
            <?php else: ?>
                <a href="login.php">Đăng Nhập</a>
                <a href="register.php" class="btn">Đăng Ký</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<div class="container">