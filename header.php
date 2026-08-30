<?php 
require_once 'config.php'; 

// Lấy số dư ví của tài khoản đang đăng nhập
$user_balance = 0;
if (isset($_SESSION['user_id'])) {
    $stmt_bal = $conn->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt_bal->bind_param("i", $_SESSION['user_id']);
    $stmt_bal->execute();
    $res_bal = $stmt_bal->get_result();
    if ($row_bal = $res_bal->fetch_assoc()) {
        $user_balance = $row_bal['balance'] ?? 0;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LaiStore - Sàn Thương Mại Điện Tử Tập Đoàn LaiGroup</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome 6 CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col justify-between">

<!-- NAVBAR -->
<header class="bg-white/90 backdrop-blur-md border-b border-slate-200 sticky top-0 z-50 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
        <!-- Logo -->
        <a href="index.php" class="flex items-center gap-3 text-2xl font-extrabold text-indigo-600 hover:opacity-90 transition">
            <div class="w-10 h-10 bg-indigo-600 text-white rounded-xl flex items-center justify-center shadow-lg shadow-indigo-200">
                <i class="fa-solid fa-bag-shopping"></i>
            </div>
            <span>LaiStore</span>
        </a>

        <!-- Navigation Links -->
        <nav class="flex items-center gap-6">
            <a href="index.php" class="text-sm font-semibold text-slate-600 hover:text-indigo-600 transition flex items-center gap-2">
                <i class="fa-solid fa-house"></i> Trang Chủ
            </a>

            <?php if (isset($_SESSION['user_id'])): ?>
                <?php $roles = explode(',', $_SESSION['user_role'] ?? 'user'); ?>
                
                <!-- DROPDOWN MENU TÀI KHOẢN -->
                <div class="relative">
                    <button onclick="toggleUserMenu()" class="flex items-center gap-3 bg-slate-100 hover:bg-slate-200/80 px-3.5 py-1.5 rounded-2xl transition focus:outline-none border border-slate-200">
                        <div class="w-8 h-8 bg-indigo-600 text-white rounded-xl flex items-center justify-center text-xs font-bold shadow-md shadow-indigo-200">
                            <?= strtoupper(substr($_SESSION['username'], 0, 1)) ?>
                        </div>

                        <div class="text-left">
                            <div class="text-xs font-extrabold text-slate-800 leading-tight">
                                <?= htmlspecialchars($_SESSION['username']) ?>
                            </div>
                            <div class="text-[10px] font-black text-emerald-600 flex items-center gap-1">
                                <i class="fa-solid fa-wallet text-[9px]"></i>
                                <?= number_format($user_balance) ?> đ
                            </div>
                        </div>

                        <i class="fa-solid fa-chevron-down text-xs text-slate-400 ml-1"></i>
                    </button>

                    <!-- Popup Menu Xổ Xuống -->
                    <div id="userMenu" class="hidden absolute right-0 mt-3 w-64 bg-white rounded-3xl border border-slate-100 shadow-2xl py-3 z-50 divide-y divide-slate-100">
                        <div class="px-4 py-3 bg-slate-50/50 m-2 rounded-2xl border border-slate-100">
                            <p class="text-[10px] font-extrabold uppercase text-slate-400 tracking-wider">Ví LaiStore Wallet</p>
                            <p class="text-base font-black text-emerald-600 mt-0.5"><?= number_format($user_balance) ?> VNĐ</p>
                            <a href="account.php" class="text-[10px] font-bold text-indigo-600 hover:underline inline-block mt-1">+ Nạp tiền vào ví</a>
                        </div>

                        <div class="py-2 space-y-1">
                            <!-- Giỏ Hàng -->
                            <a href="cart.php" class="flex items-center justify-between px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 transition">
                                <span class="flex items-center gap-3"><i class="fa-solid fa-cart-shopping text-slate-400"></i> Giỏ Hàng</span>
                                <?php if (!empty($_SESSION['cart'])): ?>
                                    <span class="bg-indigo-600 text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?= count($_SESSION['cart']) ?></span>
                                <?php endif; ?>
                            </a>

                            <!-- Lịch Sử Đơn Hàng -->
                            <a href="orders.php" class="flex items-center gap-3 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 transition">
                                <i class="fa-solid fa-box text-slate-400"></i> Đơn Hàng Của Tôi
                            </a>

                            <!-- Quản Lý Bán Hàng & Tin Nhắn Khách Hàng (Dành Cho Merchant) -->
                            <?php if (in_array('merchant', $roles)): ?>
                                <a href="products.php" class="flex items-center gap-3 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 transition">
                                    <i class="fa-solid fa-store text-slate-400"></i> Quản Lý Bán Hàng
                                </a>
                                <a href="support.php" class="flex items-center gap-3 px-4 py-2.5 text-sm font-semibold text-indigo-600 hover:bg-indigo-50 transition">
                                    <i class="fa-solid fa-comments"></i> Tin Nhắn Khách Hàng
                                </a>
                            <?php endif; ?>

                            <!-- Bảng Điều Khiển Giao Hàng (Dành Cho Shipper) -->
                            <?php if (in_array('shipper', $roles)): ?>
                                <a href="shipper.php" class="flex items-center gap-3 px-4 py-2.5 text-sm font-semibold text-sky-600 hover:bg-sky-50 transition">
                                    <i class="fa-solid fa-truck-fast"></i> Bảng Shipper Giao Hàng
                                </a>
                            <?php endif; ?>

                            <!-- Trang Quản Trị Bí Mật (Cho Admin) -->
                            <?php if (in_array('admin', $roles)): ?>
                                <a href="admin.php" class="flex items-center gap-3 px-4 py-2.5 text-sm font-semibold text-rose-600 hover:bg-rose-50 transition">
                                    <i class="fa-solid fa-user-shield"></i> Bảng Quản Trị Admin
                                </a>
                            <?php endif; ?>

                            <!-- Quản Lý Tài Khoản -->
                            <a href="account.php" class="flex items-center gap-3 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 transition">
                                <i class="fa-solid fa-user-gear text-slate-400"></i> Quản Lý Tài Khoản
                            </a>
                        </div>

                        <!-- Đăng Xuất -->
                        <div class="pt-2">
                            <a href="logout.php" class="flex items-center gap-3 px-4 py-2.5 text-sm font-semibold text-rose-600 hover:bg-rose-50 transition">
                                <i class="fa-solid fa-right-from-bracket"></i> Đăng Xuất
                            </a>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <a href="login.php" class="text-sm font-semibold text-slate-600 hover:text-indigo-600 transition">Đăng Nhập</a>
                <a href="register.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-lg shadow-indigo-200 transition transform hover:-translate-y-0.5">
                    <i class="fa-solid fa-user-plus mr-1"></i> Đăng Ký
                </a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<script>
function toggleUserMenu() {
    const menu = document.getElementById('userMenu');
    menu.classList.toggle('hidden');
}

window.addEventListener('click', function(e) {
    const menu = document.getElementById('userMenu');
    const button = e.target.closest('button');
    if (menu && !menu.contains(e.target) && !button) {
        menu.classList.add('hidden');
    }
});
</script>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-grow w-full">