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

// Nếu mở từ App thì không gọi header/footer cồng kềnh của web PC
if (!$is_app) {
    include 'header.php';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập - LaiStore</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-white text-slate-800 antialiased">

<div class="min-h-screen flex items-center justify-center py-6 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-6 bg-white p-6 sm:p-10 rounded-3xl border border-slate-100 shadow-xl relative">
        
        <div class="text-center">
            <div class="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl font-black shadow-inner">
                <i class="fa-solid fa-right-to-bracket"></i>
            </div>
            <h2 class="text-2xl font-extrabold text-slate-900 tracking-tight">Đăng Nhập</h2>
            <p class="mt-1 text-xs font-semibold text-slate-400">Chào mừng bạn đến với hệ thống LaiStore</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-xs font-bold flex items-center gap-3">
                <i class="fa-solid fa-circle-exclamation text-base"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form class="space-y-4" method="POST" action="login.php">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Tên Đăng Nhập</label>
                <input type="text" name="username" required placeholder="Nhập tên tài khoản..." class="w-full px-4 py-3.5 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-sm font-medium bg-slate-50">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Mật Khẩu</label>
                <input type="password" name="password" required placeholder="••••••••" class="w-full px-4 py-3.5 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-sm font-medium bg-slate-50">
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold py-3.5 rounded-xl text-sm shadow-lg shadow-indigo-200 transition">
                    Đăng Nhập Ngay
                </button>
            </div>
        </form>

        <div class="pt-4 border-t border-slate-100 text-center">
            <p class="text-xs font-semibold text-slate-500">
                Chưa có tài khoản? 
                <a href="register.php" class="font-extrabold text-indigo-600 underline">Tạo tài khoản mới</a>
            </p>
        </div>
    </div>
</div>

</body>
</html>
<?php 
if (!$is_app) {
    include 'footer.php';
}
?>