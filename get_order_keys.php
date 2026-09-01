<?php
require_once 'config.php';
checkLogin();

$user_id = $_SESSION['user_id'];
$order_id = (int)($_GET['order_id'] ?? 0);

if ($order_id <= 0) {
    echo '<div class="text-rose-500 font-bold">Mã đơn hàng không hợp lệ!</div>';
    exit();
}

// Kiểm tra xem đơn hàng này có đúng thuộc về người dùng đang đăng nhập hay không (hoặc là Admin/Shipper)
$chk = $conn->prepare("SELECT id, customer_id, status FROM orders WHERE id = ?");
$chk->bind_param("i", $order_id);
$chk->execute();
$order = $chk->get_result()->fetch_assoc();

if (!$order) {
    echo '<div class="text-rose-500 font-bold">Không tìm thấy thông tin đơn hàng!</div>';
    exit();
}

$roles = explode(',', $_SESSION['user_role'] ?? '');
if ($order['customer_id'] != $user_id && !in_array('admin', $roles) && !in_array('merchant', $roles)) {
    echo '<div class="text-rose-500 font-bold">Bạn không có quyền xem thông tin của đơn hàng này!</div>';
    exit();
}

// Lấy danh sách các sản phẩm và key bản quyền đã được cấp trong đơn hàng này
$keys_q = $conn->prepare("SELECT pk.license_key, p.name as product_name 
                          FROM product_keys pk 
                          JOIN products p ON pk.product_id = p.id 
                          WHERE pk.order_id = ?");
$keys_q->bind_param("i", $order_id);
$keys_q->execute();
$keys_res = $keys_q->get_result();

// Lấy thông tin các sản phẩm vật lý trong đơn hàng
$items_q = $conn->prepare("SELECT oi.*, p.name as product_name, p.product_type 
                           FROM order_items oi 
                           JOIN products p ON oi.product_id = p.id 
                           WHERE oi.order_id = ?");
$items_q->bind_param("i", $order_id);
$items_q->execute();
$items_res = $items_q->get_result();
?>

<div class="space-y-4">
    <div class="bg-slate-50 p-3 rounded-2xl border border-slate-100 flex items-center justify-between">
        <span class="font-bold text-slate-700">Mã đơn hàng: #<?= $order['id'] ?></span>
        <span class="text-indigo-600 font-extrabold uppercase text-[10px] bg-indigo-50 px-2.5 py-1 rounded-lg">Trạng thái: <?= $order['status'] ?></span>
    </div>

    <!-- Hiển thị Key bản quyền nếu có -->
    <?php if ($keys_res && $keys_res->num_rows > 0): ?>
        <div>
            <h4 class="font-black text-slate-800 text-xs uppercase tracking-wider mb-2 text-indigo-600"><i class="fa-solid fa-key mr-1"></i> Key Bản Quyền / Kỹ Thuật Số:</h4>
            <div class="space-y-2">
                <?php while ($k = $keys_res->fetch_assoc()): ?>
                    <div class="bg-indigo-50/50 border border-indigo-100 p-3 rounded-2xl flex items-center justify-between gap-2">
                        <div>
                            <div class="text-[10px] font-bold text-slate-400"><?= htmlspecialchars($k['product_name']) ?></div>
                            <div class="font-mono font-bold text-indigo-700 text-sm select-all"><?= htmlspecialchars($k['license_key']) ?></div>
                        </div>
                        <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($k['license_key'], ENT_QUOTES) ?>'); Swal.fire({toast: true, position: 'top-end', icon: 'success', title: 'Đã sao chép key!', showConfirmButton: false, timer: 2000});" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-xl text-[11px] font-bold transition whitespace-nowrap">
                            <i class="fa-regular fa-copy mr-1"></i> Copy
                        </button>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Hiển thị danh sách sản phẩm trong đơn -->
    <div>
        <h4 class="font-black text-slate-800 text-xs uppercase tracking-wider mb-2 text-slate-600"><i class="fa-solid fa-box-open mr-1"></i> Chi Tiết Sản Phẩm:</h4>
        <div class="space-y-2">
            <?php while ($it = $items_res->fetch_assoc()): ?>
                <div class="bg-white border border-slate-100 p-3 rounded-2xl flex items-center justify-between">
                    <div>
                        <div class="font-bold text-slate-800"><?= htmlspecialchars($it['product_name']) ?></div>
                        <div class="text-[11px] text-slate-400">Số lượng: x<?= $it['quantity'] ?></div>
                    </div>
                    <div class="font-extrabold text-emerald-600">
                        <?= number_format($it['price'] * $it['quantity']) ?> đ
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>