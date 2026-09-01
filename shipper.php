<?php
require_once 'config.php';
checkLogin();

$roles = explode(',', $_SESSION['user_role'] ?? 'user');
if (!in_array('shipper', $roles) && !in_array('admin', $roles)) {
    header("Location: index.php");
    exit();
}

$shipper_id = $_SESSION['user_id'];

// 1. XỬ LÝ NHẬN ĐƠN GIAO HÀNG
if (isset($_GET['accept_order'])) {
    $order_id = (int)$_GET['accept_order'];
    
    // Kiểm tra xem đơn đã có ai nhận chưa
    $chk = $conn->prepare("SELECT id FROM orders WHERE id = ? AND (shipper_id IS NULL OR shipper_id = 0)");
    $chk->bind_param("i", $order_id);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $up = $conn->prepare("UPDATE orders SET shipper_id = ?, status = 'SHIPPING' WHERE id = ?");
        $up->bind_param("ii", $shipper_id, $order_id);
        $up->execute();
    }
    header("Location: shipper.php?alert=accepted");
    exit();
}

// 2. XỬ LÝ CẬP NHẬT TRẠNG THÁI GIAO HÀNG (Cộng tiền phí ship khi DELIVERED)
if (isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['status'];

    // Lấy thông tin đơn hàng hiện tại của đúng shipper này
    $ord_q = $conn->prepare("SELECT status, shipper_id, shipping_fee FROM orders WHERE id = ? AND shipper_id = ?");
    $ord_q->bind_param("ii", $order_id, $shipper_id);
    $ord_q->execute();
    $ord_info = $ord_q->get_result()->fetch_assoc();

    if ($ord_info) {
        $old_status = $ord_info['status'];
        $shipping_fee = $ord_info['shipping_fee'] ?? 30000; // Mặc định 30k nếu chưa có

        // Cập nhật trạng thái đơn hàng
        $up = $conn->prepare("UPDATE orders SET status = ? WHERE id = ? AND shipper_id = ?");
        $up->bind_param("sii", $new_status, $order_id, $shipper_id);
        $up->execute();

        // Nếu chuyển sang DELIVERED và trước đó chưa giao xong -> Cộng tiền phí ship vào ví shipper
        if ($new_status === 'DELIVERED' && $old_status !== 'DELIVERED') {
            $conn->query("UPDATE users SET balance = balance + $shipping_fee WHERE id = $shipper_id");
        }
    }
    header("Location: shipper.php?alert=updated");
    exit();
}

// 3. LẤY DANH SÁCH ĐƠN HÀNG: Đơn chờ nhận (PENDING) HOẶC đơn do chính shipper này đang nhận giao
$sql = "SELECT o.*, u.username as shipper_name, 
        (SELECT GROUP_CONCAT(CONCAT(p.name, ' (x', oi.quantity, ')') SEPARATOR ', ') 
         FROM order_items oi JOIN products p ON oi.product_id = p.id 
         WHERE oi.order_id = o.id) as items_summary
        FROM orders o 
        LEFT JOIN users u ON o.shipper_id = u.id
        WHERE (o.status = 'PENDING' AND (o.shipper_id IS NULL OR o.shipper_id = 0)) 
           OR o.shipper_id = ?
        ORDER BY o.id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $shipper_id);
$stmt->execute();
$orders = $stmt->get_result();

include 'header.php';
?>

<div class="mb-6 sm:mb-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
    <div>
        <div class="flex items-center gap-2">
            <span class="bg-sky-100 text-sky-600 text-[10px] font-extrabold uppercase px-2.5 py-1 rounded-md border border-sky-200">Khu Vực Shipper</span>
            <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900">Quản Lý Giao Hàng</h2>
        </div>
        <p class="text-slate-500 text-xs sm:text-sm mt-1">Tiếp nhận đơn hàng mới, xem thông tin người nhận và cập nhật tiến độ giao hàng.</p>
    </div>
    <div class="bg-sky-50 border border-sky-100 text-sky-700 px-4 py-2 rounded-2xl text-xs font-bold">
        <i class="fa-solid fa-truck-ramp-box mr-1"></i> Bảng điều khiển vận chuyển
    </div>
</div>

<!-- BẢNG ĐIỀU KHIỂN SHIPPER -->
<div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[900px]">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100 text-slate-400 uppercase text-[10px] sm:text-xs font-bold">
                    <th class="p-3.5 sm:p-4">Mã Đơn</th>
                    <th class="p-3.5 sm:p-4">Người Nhận & SĐT</th>
                    <th class="p-3.5 sm:p-4">Địa Chỉ Giao Hàng</th>
                    <th class="p-3.5 sm:p-4">Sản Phẩm</th>
                    <th class="p-3.5 sm:p-4">Tổng Tiền (COD)</th>
                    <th class="p-3.5 sm:p-4">Người Vận Chuyển</th>
                    <th class="p-3.5 sm:p-4">Trạng Thái</th>
                    <th class="p-3.5 sm:p-4 text-center">Thao Tác</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs sm:text-sm font-medium">
                <?php if ($orders && $orders->num_rows > 0): ?>
                    <?php while ($o = $orders->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="p-3.5 sm:p-4 font-bold text-indigo-600">#<?= $o['id'] ?></td>
                            
                            <!-- Thông tin tên và SĐT người nhận -->
                            <td class="p-3.5 sm:p-4">
                                <div class="font-extrabold text-slate-800"><?= htmlspecialchars($o['customer_name'] ?? 'Khách hàng') ?></div>
                                <div class="text-[11px] text-indigo-600 font-bold mt-0.5"><i class="fa-solid fa-phone mr-1"></i><?= htmlspecialchars($o['phone'] ?? 'Chưa có SĐT') ?></div>
                            </td>

                            <!-- Địa chỉ giao hàng chi tiết -->
                            <td class="p-3.5 sm:p-4 max-w-xs">
                                <div class="text-slate-700 font-semibold leading-relaxed"><i class="fa-solid fa-location-dot text-rose-500 mr-1"></i> <?= htmlspecialchars($o['address'] ?? 'Không có địa chỉ') ?></div>
                            </td>

                            <td class="p-3.5 sm:p-4 text-slate-600 text-xs">
                                <?= htmlspecialchars($o['items_summary'] ?? 'Sản phẩm kỹ thuật số') ?>
                            </td>

                            <td class="p-3.5 sm:p-4 whitespace-nowrap">
                                <span class="font-black text-rose-600 text-xs sm:text-sm">
                                    <?= number_format($o['total_amount']) ?> đ
                                </span>
                                <div class="text-[9px] text-slate-400 font-bold uppercase"><?= $o['payment_method'] ?></div>
                            </td>

                            <!-- Tên người vận chuyển -->
                            <td class="p-3.5 sm:p-4">
                                <?php if (!empty($o['shipper_name'])): ?>
                                    <span class="bg-sky-50 text-sky-700 border border-sky-200 px-2.5 py-1 rounded-xl text-xs font-bold inline-flex items-center gap-1">
                                        <i class="fa-solid fa-user-check"></i> <?= htmlspecialchars($o['shipper_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-400 italic text-xs">Chưa có ai nhận</span>
                                <?php endif; ?>
                            </td>

                            <td class="p-3.5 sm:p-4">
                                <?php 
                                    $status = $o['status'];
                                    if ($status === 'PENDING') {
                                        echo '<span class="bg-amber-50 text-amber-600 border border-amber-200/60 px-2.5 py-1 rounded-full text-[11px] font-bold inline-flex items-center gap-1"><i class="fa-solid fa-clock"></i> Chờ Nhận</span>';
                                    } elseif ($status === 'SHIPPING') {
                                        echo '<span class="bg-sky-50 text-sky-600 border border-sky-200/60 px-2.5 py-1 rounded-full text-[11px] font-bold inline-flex items-center gap-1"><i class="fa-solid fa-truck-fast"></i> Đang Giao</span>';
                                    } elseif ($status === 'DELIVERED') {
                                        echo '<span class="bg-emerald-50 text-emerald-600 border border-emerald-200/60 px-2.5 py-1 rounded-full text-[11px] font-bold inline-flex items-center gap-1"><i class="fa-solid fa-circle-check"></i> Đã Giao</span>';
                                    } else {
                                        echo '<span class="bg-rose-50 text-rose-600 border border-rose-200/60 px-2.5 py-1 rounded-full text-[11px] font-bold inline-flex items-center gap-1"><i class="fa-solid fa-ban"></i> Đã Hủy</span>';
                                    }
                                ?>
                            </td>

                            <td class="p-3.5 sm:p-4 text-center">
                                <?php if (empty($o['shipper_id']) || $o['shipper_id'] == 0): ?>
                                    <!-- Đơn chưa ai nhận -> Bấm nhận đơn -->
                                    <a href="shipper.php?accept_order=<?= $o['id'] ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3.5 py-2 rounded-xl text-[11px] sm:text-xs font-bold shadow-md shadow-indigo-100 transition inline-flex items-center gap-1 whitespace-nowrap">
                                        <i class="fa-solid fa-hand-holding-hand"></i> Nhận Giao Đơn
                                    </a>
                                <?php elseif ((int)$o['shipper_id'] === (int)$shipper_id): ?>
                                    <!-- Đơn của chính shipper này -> Form cập nhật trạng thái -->
                                    <form method="POST" action="shipper.php" class="flex items-center justify-center gap-1.5">
                                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                        <select name="status" class="px-2 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 bg-slate-50 focus:outline-none">
                                            <option value="SHIPPING" <?= $o['status'] === 'SHIPPING' ? 'selected' : '' ?>>Đang giao</option>
                                            <option value="DELIVERED" <?= $o['status'] === 'DELIVERED' ? 'selected' : '' ?>>Đã giao xong</option>
                                        </select>
                                        <button type="submit" name="update_status" class="bg-slate-900 hover:bg-slate-800 text-white px-3 py-1.5 rounded-xl text-xs font-bold transition">
                                            Lưu
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-slate-400 text-xs italic font-semibold">Shipper khác đảm nhận</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="p-8 text-center text-slate-400 text-xs sm:text-sm">Hiện tại không có đơn hàng vận chuyển nào cần xử lý.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('alert') === 'accepted') {
    const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
    Toast.fire({ icon: 'success', title: 'Đã nhận đơn giao hàng thành công!' });
} else if (urlParams.get('alert') === 'updated') {
    const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
    Toast.fire({ icon: 'success', title: 'Đã cập nhật trạng thái và cộng phí ship vào ví!' });
}
</script>

<?php include 'footer.php'; ?>