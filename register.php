<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $roles    = $_POST['roles'] ?? ['user'];

    if (!empty($username) && !empty($password)) {
        // Đảm bảo loại bỏ quyền merchant nếu có can thiệp từ form
        $filteredRoles = array_filter($roles, function($r) {
            return $r !== 'merchant';
        });
        if (empty($filteredRoles)) {
            $filteredRoles[] = 'user';
        }

        $roleStr = implode(',', $filteredRoles);
        $hashedPass = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $hashedPass, $roleStr);

        if ($stmt->execute()) {
            $success = 'Đăng ký tài khoản thành công! Bạn có thể đăng nhập ngay bây giờ.';
        } else {
            $error = 'Tên tài khoản này đã tồn tại trên hệ thống!';
        }
    } else {
        $error = 'Vui lòng điền đầy đủ tên đăng nhập và mật khẩu!';
    }
}

include 'header.php';
?>

<div class="min-h-[75vh] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-white p-8 sm:p-10 rounded-3xl border border-slate-100 shadow-xl shadow-indigo-100/50 relative overflow-hidden">
        
        <!-- Dải màu trang trí -->
        <div class="absolute top-0 left-0 right-0 h-2 bg-gradient-to-r from-indigo-600 via-purple-600 to-violet-600"></div>

        <!-- Tiêu đề & Logo -->
        <div class="text-center">
            <div class="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl font-black shadow-inner">
                <i class="fa-solid fa-user-plus"></i>
            </div>
            <h2 class="text-2xl font-extrabold text-slate-900 tracking-tight">Tạo Tài Khoản Mới</h2>
            <p class="mt-2 text-xs font-semibold text-slate-400">Gia nhập sàn thương mại điện tử LaiStore ngay hôm nay</p>
        </div>

        <!-- Thông Báo Lỗi / Thành Công -->
        <?php if ($error): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-xs font-bold flex items-center gap-3 animate-pulse">
                <i class="fa-solid fa-circle-exclamation text-base"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl text-xs font-bold flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-circle-check text-base text-emerald-600"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
                <a href="login.php" class="bg-emerald-600 text-white px-3 py-1 rounded-xl font-bold hover:bg-emerald-700 transition">Đăng nhập</a>
            </div>
        <?php endif; ?>

        <!-- Form Đăng Ký -->
        <form class="mt-8 space-y-5" method="POST" action="register.php">
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
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Mật Khẩu</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-lock text-sm"></i>
                    </div>
                    <input type="password" name="password" required placeholder="••••••••" class="w-full pl-10 pr-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-sm font-medium transition bg-slate-50/50 focus:bg-white">
                </div>
            </div>

            <!-- Tùy Chọn Vai Trò (Đã bỏ Doanh nghiệp) -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Loại Tài Khoản</label>
                <div class="space-y-2">
                    <label class="border border-slate-200 p-3 rounded-xl flex items-center gap-3 cursor-pointer hover:border-indigo-600 transition bg-slate-50/50">
                        <input type="checkbox" name="roles[]" value="user" checked class="accent-indigo-600 w-4 h-4">
                        <div>
                            <div class="text-xs font-bold text-slate-800"><i class="fa-solid fa-bag-shopping text-indigo-600 mr-1"></i> Khách Hàng</div>
                            <div class="text-[10px] text-slate-400">Mua sắm và theo dõi đơn hàng</div>
                        </div>
                    </label>

                    <label class="border border-slate-200 p-3 rounded-xl flex items-center gap-3 cursor-pointer hover:border-indigo-600 transition bg-slate-50/50">
                        <input type="checkbox" name="roles[]" value="shipper" class="accent-indigo-600 w-4 h-4">
                        <div>
                            <div class="text-xs font-bold text-slate-800"><i class="fa-solid fa-truck-fast text-indigo-600 mr-1"></i> Nhân Viên Giao Hàng (Shipper)</div>
                            <div class="text-[10px] text-slate-400">Tiếp nhận và cập nhật tiến độ vận chuyển</div>
                        </div>
                    </label>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold py-3.5 px-6 rounded-xl text-sm shadow-lg shadow-indigo-200 transition transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                    <span>Tạo Tài Khoản Ngay</span>
                    <i class="fa-solid fa-arrow-right text-xs"></i>
                </button>
            </div>
        </form>

        <div class="pt-6 border-t border-slate-100 text-center">
            <p class="text-xs font-semibold text-slate-500">
                Đã có tài khoản trên LaiStore? 
                <a href="login.php" class="font-extrabold text-indigo-600 hover:text-indigo-800 transition underline underline-offset-4 ml-1">Đăng nhập ngay</a>
            </p>
        </div>

    </div>
</div>

<?php include 'footer.php'; ?>