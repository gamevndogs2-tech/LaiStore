<?php
require_once 'config.php';
checkLogin();

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// 1. XỬ LÝ LƯU THÔNG TIN BẢO MẬT & NGÂN HÀNG LIÊN KẾT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $bank_name = trim($_POST['bank_name']);
    $account_number = trim($_POST['account_number']);
    $account_holder = trim($_POST['account_holder']);

    $stmt = $conn->prepare("UPDATE users SET email=?, phone=?, bank_name=?, account_number=?, account_holder=? WHERE id=?");
    $stmt->bind_param("sssssi", $email, $phone, $bank_name, $account_number, $account_holder, $user_id);
    
    if ($stmt->execute()) {
        $success = "Cập nhật thông tin hồ sơ và tài khoản ngân hàng thành công!";
    } else {
        $error = "Có lỗi xảy ra khi lưu thông tin.";
    }
}

// 2. XỬ LÝ NẠP TIỀN VÀO VÍ LAISTORE WALLET
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deposit_money'])) {
    $amount = (float)($_POST['amount'] ?? 0);

    if ($amount >= 10000) {
        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->bind_param("di", $amount, $user_id);
        if ($stmt->execute()) {
            $success = "Nạp thành công " . number_format($amount) . " đ vào Ví LaiStore Wallet!";
        } else {
            $error = "Giao dịch thất bại, vui lòng thử lại!";
        }
    } else {
        $error = "Số tiền nạp tối thiểu là 10.000 VNĐ!";
    }
}

// Lấy thông tin tài khoản mới nhất
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_info = $stmt->get_result()->fetch_assoc();

include 'header.php';
?>

<div class="max-w-4xl mx-auto space-y-8">
    <!-- KHUNG HIỂN THỊ VÍ LAISTORE WALLET -->
    <div class="bg-gradient-to-r from-indigo-600 via-purple-600 to-violet-600 rounded-3xl p-6 sm:p-8 text-white shadow-2xl shadow-indigo-200 flex flex-col sm:flex-row items-center justify-between gap-6">
        <div>
            <div class="flex items-center gap-2 text-indigo-100 text-xs uppercase font-extrabold tracking-wider mb-2">
                <i class="fa-solid fa-wallet"></i> Ví Điện Tử LaiStore Wallet
            </div>
            <div class="text-3xl sm:text-4xl font-black">
                <?= number_format($user_info['balance'] ?? 0) ?> <span class="text-lg">VNĐ</span>
            </div>
            <p class="text-xs text-indigo-100/80 mt-1">Dùng để thanh toán đơn hàng trực tiếp siêu tốc.</p>
        </div>
        <button onclick="openDepositModal()" class="bg-white text-indigo-600 hover:bg-indigo-50 font-extrabold px-6 py-3.5 rounded-2xl text-sm shadow-lg transition transform hover:-translate-y-0.5 flex items-center gap-2">
            <i class="fa-solid fa-circle-plus"></i> Nạp Tiền Vào Ví
        </button>
    </div>

    <!-- THÔNG BÁO -->
    <?php if ($success): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-5 py-4 rounded-2xl text-xs font-bold flex items-center gap-3">
            <i class="fa-solid fa-circle-check text-base text-emerald-600"></i>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-5 py-4 rounded-2xl text-xs font-bold flex items-center gap-3">
            <i class="fa-solid fa-circle-exclamation text-base"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- FORM THÔNG TIN CÁ NHÂN VÀ THẺ / NGÂN HÀNG LIÊN KẾT -->
    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-6 sm:p-8">
        <h3 class="text-xl font-bold text-slate-900 mb-6 flex items-center gap-2">
            <i class="fa-solid fa-user-gear text-indigo-600"></i> Quản Lý Tài Khoản & Ngân Hàng
        </h3>

        <form method="POST" action="account.php" class="space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Tên Đăng Nhập</label>
                    <input type="text" value="<?= htmlspecialchars($user_info['username']) ?>" disabled class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-500 font-bold text-sm cursor-not-allowed">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Địa Chỉ Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user_info['email'] ?? '') ?>" placeholder="example@domain.com" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-sm font-medium">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Số Điện Thoại</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($user_info['phone'] ?? '') ?>" placeholder="09xx xxx xxx" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-sm font-medium">
            </div>

            <hr class="border-slate-100">

            <div>
                <h4 class="text-sm font-extrabold text-slate-800 uppercase tracking-wider mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-credit-card text-indigo-600"></i> Ngân Hàng / Thẻ Liên Kết Nạp Tiền
                </h4>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Tên Ngân Hàng / Thẻ</label>
                        <input type="text" name="bank_name" value="<?= htmlspecialchars($user_info['bank_name'] ?? '') ?>" placeholder="MBBank, Vietcombank, Visa..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-sm font-medium">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Số Tài Khoản / Số Thẻ</label>
                        <input type="text" name="account_number" value="<?= htmlspecialchars($user_info['account_number'] ?? '') ?>" placeholder="0988xxxxxx" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-sm font-medium">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Tên Chủ Tài Khoản</label>
                        <input type="text" name="account_holder" value="<?= htmlspecialchars($user_info['account_holder'] ?? '') ?>" placeholder="NGUYEN VAN A" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-sm font-medium">
                    </div>
                </div>
            </div>

            <div class="pt-4">
                <button type="submit" name="update_profile" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-8 py-3.5 rounded-xl text-sm shadow-lg shadow-indigo-200 transition transform hover:-translate-y-0.5">
                    Lưu Thay Đổi Thông Tin
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL POPUP NẠP TIỀN -->
<div id="depositModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-slate-900">Nạp Tiền Vào LaiStore Wallet</h3>
            <button onclick="closeDepositModal()" class="text-slate-400 hover:text-slate-600 text-lg"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form method="POST" action="account.php" class="space-y-4">
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                <div class="text-xs font-bold text-slate-400 uppercase">Thẻ / Ngân Hàng Nguồn</div>
                <div class="text-sm font-extrabold text-slate-800 mt-1">
                    <?= htmlspecialchars($user_info['bank_name'] ?: 'Chưa liên kết thẻ') ?> 
                    <?= $user_info['account_number'] ? '(' . htmlspecialchars($user_info['account_number']) . ')' : '' ?>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Số Tiền Nạp (VNĐ)</label>
                <input type="number" name="amount" min="10000" step="10000" required placeholder="50000" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-sm font-bold">
            </div>

            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeDepositModal()" class="flex-1 bg-slate-100 text-slate-600 font-bold py-3 rounded-xl text-sm">Hủy</button>
                <button type="submit" name="deposit_money" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl text-sm shadow-lg shadow-indigo-200">Xác Nhận Nạp</button>
            </div>
        </form>
    </div>
</div>

<script>
function openDepositModal() {
    document.getElementById('depositModal').classList.remove('hidden');
    document.getElementById('depositModal').classList.add('flex');
}
function closeDepositModal() {
    document.getElementById('depositModal').classList.add('hidden');
    document.getElementById('depositModal').classList.remove('flex');
}
</script>

<?php include 'footer.php'; ?>