<?php
require_once 'config.php';
checkLogin();

$user_id = $_SESSION['user_id'];

// XỬ LÝ HỦY ĐƠN HÀNG
if (isset($_GET['cancel_order_id'])) {
    $cancel_id = (int)$_GET['cancel_order_id'];

    // Lấy thông tin chi tiết đơn hàng trước khi hủy
    $stmt_check = $conn->prepare("SELECT * FROM orders WHERE id = ? AND customer_id = ? AND status = 'PENDING'");
    $stmt_check->bind_param("ii", $cancel_id, $user_id);
    $stmt_check->execute();
    $order = $stmt_check->get_result()->fetch_assoc();

    if ($order) {
        // Cập nhật trạng thái đơn thành CANCELED
        $stmt_cancel = $conn->prepare("UPDATE orders SET status = 'CANCELED' WHERE id = ?");
        $stmt_cancel->bind_param("i", $cancel_id);
        $stmt_cancel->execute();

        // NẾU ĐƠN HÀNG ĐÃ ĐƯỢC THANH TOÁN BẰNG VÍ WALLET -> HOÀN TIỀN VÀO VÍ
        if ($order['payment_method'] === 'WALLET') {
            $stmt_refund = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt_refund->bind_param("di", $order['total_amount'], $user_id);
            $stmt_refund->execute();
            header("Location: orders.php?alert=canceled_refunded");
        } else {
            header("Location: orders.php?alert=canceled");
        }
    } else {
        header("Location: orders.php?alert=cannot_cancel");
    }
    exit();
}

// Truy vấn danh sách đơn hàng của khách hàng
$stmt = $conn->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result();

include 'header.php';
?>

<div class="mb-8">
    <h2 class="text-3xl font-extrabold text-slate-900">Lịch Sử Đơn Hàng Của Tôi</h2>
    <p class="text-slate-500 text-sm mt-1">Theo dõi tiến độ, thanh toán và quản lý hủy đơn hàng.</p>
</div>

<!-- BẢNG ĐƠN HÀNG KHÁCH HÀNG -->
<div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-100 text-slate-400 uppercase text-xs font-bold">
                <th class="p-4">Mã Đơn</th>
                <th class="p-4">Người Nhận</th>
                <th class="p-4">Địa Chỉ Giao Hàng</th>
                <th class="p-4">Thanh Toán</th>
                <th class="p-4">Tổng Tiền</th>
                <th class="p-4">Trạng Thái</th>
                <th class="p-4 text-center">Thao Tác</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm font-medium">
            <?php if ($orders->num_rows > 0): ?>
                <?php while ($o = $orders->fetch_assoc()): ?>
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="p-4 font-bold text-indigo-600">#<?= $o['id'] ?></td>
                        <td class="p-4 font-bold text-slate-800"><?= htmlspecialchars($o['customer_name']) ?></td>
                        <td class="p-4">
                            <div class="font-semibold text-slate-700"><?= htmlspecialchars($o['address']) ?></div>
                            <div class="text-xs text-slate-400"><i class="fa-solid fa-phone mr-1"></i><?= htmlspecialchars($o['phone']) ?></div>
                        </td>
                        <td class="p-4">
                            <?php if ($o['payment_method'] === 'WALLET'): ?>
                                <span class="bg-indigo-50 text-indigo-600 border border-indigo-200/60 px-2.5 py-0.5 rounded-lg text-xs font-extrabold"><i class="fa-solid fa-wallet mr-1"></i>Ví Wallet</span>
                            <?php else: ?>
                                <span class="bg-slate-100 text-slate-600 border border-slate-200 px-2.5 py-0.5 rounded-lg text-xs font-extrabold"><i class="fa-solid fa-money-bill-wave mr-1"></i>COD</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 font-extrabold text-emerald-600"><?= number_format($o['total_amount']) ?> đ</td>
                        <td class="p-4">
                            <?php if ($o['status'] === 'PENDING'): ?>
                                <span class="bg-amber-50 text-amber-600 border border-amber-200/60 px-3 py-1 rounded-full text-xs font-bold inline-flex items-center gap-1.5"><i class="fa-solid fa-clock"></i> Chờ Tiếp Nhận</span>
                            <?php elseif ($o['status'] === 'SHIPPING'): ?>
                                <span class="bg-sky-50 text-sky-600 border border-sky-200/60 px-3 py-1 rounded-full text-xs font-bold inline-flex items-center gap-1.5"><i class="fa-solid fa-truck-fast"></i> Đang Giao Hàng</span>
                            <?php elseif ($o['status'] === 'DELIVERED'): ?>
                                <span class="bg-emerald-50 text-emerald-600 border border-emerald-200/60 px-3 py-1 rounded-full text-xs font-bold inline-flex items-center gap-1.5"><i class="fa-solid fa-circle-check"></i> Đã Giao Thành Công</span>
                            <?php else: ?>
                                <span class="bg-rose-50 text-rose-600 border border-rose-200/60 px-3 py-1 rounded-full text-xs font-bold inline-flex items-center gap-1.5"><i class="fa-solid fa-ban"></i> Đã Hủy Đơn</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 text-center">
                            <?php if ($o['status'] === 'PENDING'): ?>
                                <button onclick="confirmCancelOrder(<?= $o['id'] ?>)" class="bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200 px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1 mx-auto">
                                    <i class="fa-solid fa-xmark"></i> Hủy Đơn
                                </button>
                            <?php else: ?>
                                <span class="text-xs text-slate-300 italic font-semibold">Không thể hủy</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="p-8 text-center text-slate-400">Bạn chưa có đơn hàng nào. <a href="index.php" class="text-indigo-600 font-bold underline">Mua sắm ngay</a>!</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function confirmCancelOrder(id) {
    Swal.fire({
        title: 'Hủy đơn hàng này?',
        text: "Bạn có chắc chắn muốn hủy đơn hàng không?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Đồng ý hủy',
        cancelButtonText: 'Quay lại'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'orders.php?cancel_order_id=' + id;
        }
    });
}

const urlParams = new URLSearchParams(window.location.search);
const alertType = urlParams.get('alert');
if (alertType === 'canceled_refunded') {
    Swal.fire('Đã Hủy Đơn!', 'Đơn hàng đã được hủy và số tiền đã được HOÀN 100% vào Ví LaiStore Wallet của bạn!', 'success');
} else if (alertType === 'canceled') {
    Swal.fire('Đã Hủy Đơn!', 'Đơn hàng của bạn đã được hủy thành công!', 'info');
} else if (alertType === 'cannot_cancel') {
    Swal.fire('Thất Bại!', 'Đơn hàng đang giao hoặc đã hủy từ trước nên không thể thực hiện thao tác!', 'error');
}
</script>

<?php include 'footer.php'; ?>