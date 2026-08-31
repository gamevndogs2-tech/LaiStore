<?php
require_once 'config.php';
checkLogin();

// 1. XỬ LÝ CỘNG TIỀN VÀO VÍ TÀI KHOẢN (ADMIN)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_balance_action'])) {
    $u_id   = (int)$_POST['u_id'];
    $amount = (float)$_POST['amount'];

    if ($amount > 0 && $u_id > 0) {
        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->bind_param("di", $amount, $u_id);
        $stmt->execute();
        header("Location: admin.php?alert=balance_added");
        exit();
    }
}

// 2. XỬ LÝ CẬP NHẬT TÀI KHOẢN & VAI TRÒ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $u_id     = (int)$_POST['u_id'];
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $new_pass = trim($_POST['new_password']);
    
    $roles    = $_POST['roles'] ?? ['user'];
    $roleStr  = implode(',', $roles);

    $checkStmt = $conn->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?) AND id != ?");
    $checkStmt->bind_param("si", $username, $u_id);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        header("Location: admin.php?alert=username_exists");
        exit();
    }

    if (!empty($new_pass)) {
        $hashed_pass = password_hash($new_pass, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET username=?, email=?, phone=?, role=?, password=? WHERE id=?");
        $stmt->bind_param("sssssi", $username, $email, $phone, $roleStr, $hashed_pass, $u_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET username=?, email=?, phone=?, role=? WHERE id=?");
        $stmt->bind_param("ssssi", $username, $email, $phone, $roleStr, $u_id);
    }

    $stmt->execute();
    header("Location: admin.php?alert=updated");
    exit();
}

// 3. XỬ LÝ XÓA TÀI KHOẢN
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
    header("Location: admin.php?alert=deleted");
    exit();
}

$result = $conn->query("SELECT * FROM users ORDER BY id DESC");

include 'header.php';
?>

<div class="mb-6 sm:mb-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
    <div>
        <div class="flex items-center gap-2">
            <span class="bg-rose-100 text-rose-600 text-[10px] font-extrabold uppercase px-2.5 py-1 rounded-md border border-rose-200">Trang Bí Mật</span>
            <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900">Bảng Điều Khiển Admin</h2>
        </div>
        <p class="text-slate-500 text-xs sm:text-sm mt-1">Quản lý tài khoản, cộng tiền ví LaiStore Wallet và phân quyền hệ thống.</p>
    </div>
    <div class="bg-indigo-50 border border-indigo-100 text-indigo-700 px-4 py-2 rounded-2xl text-xs font-bold">
        <i class="fa-solid fa-users mr-1"></i> Tổng: <?= $result->num_rows ?> tài khoản
    </div>
</div>

<!-- BẢNG DANH SÁCH TÀI KHOẢN (CUỘN NGANG TRÊN MOBILE) -->
<div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[700px]">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100 text-slate-400 uppercase text-[10px] sm:text-xs font-bold">
                    <th class="p-3.5 sm:p-4">ID</th>
                    <th class="p-3.5 sm:p-4">Tên Tài Khoản</th>
                    <th class="p-3.5 sm:p-4">Số Dư Ví</th>
                    <th class="p-3.5 sm:p-4">Thẻ / Ngân Hàng</th>
                    <th class="p-3.5 sm:p-4">Vai Trò</th>
                    <th class="p-3.5 sm:p-4 text-center">Thao Tác</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs sm:text-sm font-medium">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($u = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="p-3.5 sm:p-4 font-bold text-slate-400">#<?= $u['id'] ?></td>
                            <td class="p-3.5 sm:p-4 font-extrabold text-slate-800">
                                <?= htmlspecialchars($u['username']) ?>
                            </td>
                            <td class="p-3.5 sm:p-4 font-black text-emerald-600">
                                <?= number_format($u['balance'] ?? 0) ?> đ
                            </td>
                            <td class="p-3.5 sm:p-4 text-xs text-slate-600">
                                <?php if (!empty($u['bank_name'])): ?>
                                    <span class="font-bold text-slate-800"><?= htmlspecialchars($u['bank_name']) ?></span><br>
                                    <span class="text-slate-400"><?= htmlspecialchars($u['account_number']) ?></span>
                                <?php else: ?>
                                    <span class="text-slate-300 italic">Chưa liên kết</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3.5 sm:p-4">
                                <div class="flex flex-wrap gap-1">
                                    <?php 
                                    $userRoles = explode(',', $u['role'] ?? 'user');
                                    foreach ($userRoles as $r):
                                        $badgeStyle = 'bg-slate-100 text-slate-600 border-slate-200';
                                        if ($r === 'admin') $badgeStyle = 'bg-rose-50 text-rose-600 border-rose-200';
                                        if ($r === 'merchant') $badgeStyle = 'bg-indigo-50 text-indigo-600 border-indigo-200';
                                        if ($r === 'shipper') $badgeStyle = 'bg-sky-50 text-sky-600 border-sky-200';
                                    ?>
                                        <span class="border px-2 py-0.5 rounded-lg text-[10px] font-extrabold uppercase <?= $badgeStyle ?>"><?= $r ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td class="p-3.5 sm:p-4 text-center">
                                <div class="flex items-center justify-center gap-1.5 sm:gap-2">
                                    <button onclick='openAddBalanceModal(<?= json_encode($u) ?>)' class="bg-emerald-50 text-emerald-600 hover:bg-emerald-100 px-2.5 sm:px-3 py-1.5 rounded-xl text-[11px] sm:text-xs font-bold transition flex items-center gap-1">
                                        <i class="fa-solid fa-circle-plus"></i> Cộng
                                    </button>
                                    <button onclick='openEditModal(<?= json_encode($u) ?>)' class="bg-amber-50 text-amber-600 hover:bg-amber-100 px-2.5 sm:px-3 py-1.5 rounded-xl text-[11px] sm:text-xs font-bold transition flex items-center gap-1">
                                        <i class="fa-solid fa-user-pen"></i> Sửa
                                    </button>
                                    <a href="javascript:void(0)" onclick="confirmDeleteUser(<?= $u['id'] ?>)" class="bg-rose-50 text-rose-600 hover:bg-rose-100 px-2.5 sm:px-3 py-1.5 rounded-xl text-[11px] sm:text-xs font-bold transition flex items-center gap-1">
                                        <i class="fa-solid fa-trash"></i> Xóa
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL CỘNG TIỀN VÍ -->
<div id="balanceModal" class="fixed inset-0 bg-slate-900/70 backdrop-blur-md z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl">
        <div class="flex justify-between items-center mb-5">
            <h3 class="text-lg sm:text-xl font-bold text-slate-900">Cộng Tiền Vào Ví Tài Khoản</h3>
            <button onclick="closeAddBalanceModal()" class="text-slate-400 hover:text-slate-600 text-lg"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form method="POST" action="admin.php" class="space-y-4">
            <input type="hidden" name="u_id" id="bal_u_id">
            
            <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100 mb-4">
                <div class="text-[10px] text-slate-400 uppercase font-bold">Tài khoản thụ hưởng</div>
                <div id="bal_username" class="text-sm sm:text-base font-extrabold text-slate-800"></div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Số Tiền Cộng Thêm (VNĐ)</label>
                <input type="number" name="amount" min="1000" step="1000" required placeholder="100000" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-sm font-bold bg-slate-50/50">
            </div>

            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeAddBalanceModal()" class="flex-1 bg-slate-100 text-slate-600 font-bold py-3.5 rounded-xl text-sm">Hủy</button>
                <button type="submit" name="add_balance_action" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3.5 rounded-xl text-sm shadow-lg shadow-emerald-200">Cộng Tiền</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL POPUP SỬA TÀI KHOẢN -->
<div id="editModal" class="fixed inset-0 bg-slate-900/70 backdrop-blur-md z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-5">
            <h3 class="text-lg sm:text-xl font-bold text-slate-900">Chỉnh Sửa Tài Khoản</h3>
            <button onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600 text-lg"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form method="POST" action="admin.php" class="space-y-4">
            <input type="hidden" name="u_id" id="edit_u_id">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Tên Đăng Nhập</label>
                    <input type="text" name="username" id="edit_username" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-sm font-medium bg-slate-50/50">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Số Điện Thoại</label>
                    <input type="text" name="phone" id="edit_phone" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-sm font-medium bg-slate-50/50">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Địa Chỉ Email</label>
                <input type="email" name="email" id="edit_email" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-sm font-medium bg-slate-50/50">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Phân Quyền Vai Trò</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <label class="border border-slate-200 p-2.5 rounded-xl flex items-center gap-2 cursor-pointer bg-slate-50/50">
                        <input type="checkbox" name="roles[]" value="user" id="role_user" class="accent-indigo-600 w-4 h-4">
                        <span class="text-xs font-bold text-slate-700">Khách Hàng (user)</span>
                    </label>

                    <label class="border border-slate-200 p-2.5 rounded-xl flex items-center gap-2 cursor-pointer bg-slate-50/50">
                        <input type="checkbox" name="roles[]" value="merchant" id="role_merchant" class="accent-indigo-600 w-4 h-4">
                        <span class="text-xs font-bold text-slate-700">Bán Hàng (merchant)</span>
                    </label>

                    <label class="border border-slate-200 p-2.5 rounded-xl flex items-center gap-2 cursor-pointer bg-slate-50/50">
                        <input type="checkbox" name="roles[]" value="shipper" id="role_shipper" class="accent-indigo-600 w-4 h-4">
                        <span class="text-xs font-bold text-slate-700">Giao Hàng (shipper)</span>
                    </label>

                    <label class="border border-slate-200 p-2.5 rounded-xl flex items-center gap-2 cursor-pointer bg-slate-50/50">
                        <input type="checkbox" name="roles[]" value="admin" id="role_admin" class="accent-indigo-600 w-4 h-4">
                        <span class="text-xs font-bold text-rose-600">Quản Trị (admin)</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Mật Khẩu Mới (Bỏ trống nếu giữ cũ)</label>
                <input type="password" name="new_password" placeholder="••••••••" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-sm font-medium bg-slate-50/50">
            </div>

            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeEditModal()" class="flex-1 bg-slate-100 text-slate-600 font-bold py-3.5 rounded-xl text-sm">Hủy</button>
                <button type="submit" name="update_user" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 rounded-xl text-sm shadow-lg shadow-indigo-200">Lưu Thay Đổi</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddBalanceModal(user) {
    document.getElementById('bal_u_id').value = user.id;
    document.getElementById('bal_username').innerText = user.username;
    document.getElementById('balanceModal').classList.remove('hidden');
    document.getElementById('balanceModal').classList.add('flex');
}
function closeAddBalanceModal() {
    document.getElementById('balanceModal').classList.add('hidden');
    document.getElementById('balanceModal').classList.remove('flex');
}

function openEditModal(user) {
    document.getElementById('edit_u_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_email').value = user.email || '';
    document.getElementById('edit_phone').value = user.phone || '';

    const userRoles = (user.role || 'user').split(',');
    document.getElementById('role_user').checked = userRoles.includes('user');
    document.getElementById('role_merchant').checked = userRoles.includes('merchant');
    document.getElementById('role_shipper').checked = userRoles.includes('shipper');
    document.getElementById('role_admin').checked = userRoles.includes('admin');

    document.getElementById('editModal').classList.remove('hidden');
    document.getElementById('editModal').classList.add('flex');
}
function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
    document.getElementById('editModal').classList.remove('flex');
}

function confirmDeleteUser(id) {
    Swal.fire({
        title: 'Xóa tài khoản này?',
        text: "Hành động này sẽ xóa vĩnh viễn tài khoản khỏi CSDL!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Đồng ý xóa',
        cancelButtonText: 'Hủy'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'admin.php?delete=' + id;
        }
    });
}

const urlParams = new URLSearchParams(window.location.search);
const alertType = urlParams.get('alert');
if (alertType) {
    const Toast = Swal.mixin({
        toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true
    });
    if (alertType === 'balance_added') Toast.fire({ icon: 'success', title: 'Đã cộng tiền ví thành công!' });
    if (alertType === 'updated') Toast.fire({ icon: 'success', title: 'Cập nhật tài khoản thành công!' });
    if (alertType === 'deleted') Toast.fire({ icon: 'info', title: 'Đã xóa tài khoản!' });
}
</script>

<?php include 'footer.php'; ?>