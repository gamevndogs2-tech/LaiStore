<?php
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $conn->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(?)");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];

        header("Location: index.php");
        exit();
    } else {
        $error = 'Tài khoản hoặc mật khẩu không chính xác!';
    }
}

include 'header.php';
?>

<div class="min-h-[75vh] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-white p-8 sm:p-10 rounded-3xl border border-slate-100 shadow-xl shadow-indigo-100/50 relative overflow-hidden">
        
        <!-- Dải màu trang trí phía trên -->
        <div class="absolute top-0 left-0 right-0 h-2 bg-gradient-to-r from-indigo-600 via-purple-600 to-violet-600"></div>

        <!-- Tiêu đề & Logo -->
        <div class="text-center">
            <div class="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl font-black shadow-inner">
                <i class="fa-solid fa-right-to-bracket"></i>
            </div>
            <h2 class="text-2xl font-extrabold text-slate-900 tracking-tight">Đăng Nhập Tài Khoản</h2>
            <p class="mt-2 text-xs font-semibold text-slate-400">Chào mừng bạn quay trở lại với hệ thống LaiStore</p>
        </div>

        <!-- Thông Báo Lỗi -->
        <?php if ($error): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-xs font-bold flex items-center gap-3 animate-pulse">
                <i class="fa-solid fa-circle-exclamation text-base"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Form Đăng Nhập -->
        <form class="mt-8 space-y-5" method="POST" action="login.php">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Tên Đăng Nhập</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-user text-sm"></i>
                    </div>
                    <input type="text" name="username" required placeholder="Nhập tên tài khoản..." class="w-full pl-10 pr-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-sm font-medium transition bg-slate-50/50 focus:bg-white">
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-slate-600 uppercase">Mật Khẩu</label>
                </div>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-lock text-sm"></i>
                    </div>
                    <input type="password" name="password" required placeholder="••••••••" class="w-full pl-10 pr-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-sm font-medium transition bg-slate-50/50 focus:bg-white">
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold py-3.5 px-6 rounded-xl text-sm shadow-lg shadow-indigo-200 transition transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                    <span>Đăng Nhập Ngay</span>
                    <i class="fa-solid fa-arrow-right text-xs"></i>
                </button>
            </div>
        </form>

        <!-- Chân trang Form chuyển hướng Đăng ký -->
        <div class="pt-6 border-t border-slate-100 text-center">
            <p class="text-xs font-semibold text-slate-500">
                Chưa có tài khoản trên LaiStore? 
                <a href="register.php" class="font-extrabold text-indigo-600 hover:text-indigo-800 transition underline underline-offset-4 ml-1">Tạo tài khoản mới</a>
            </p>
        </div>

    </div>
</div>

<?php include 'footer.php'; ?>