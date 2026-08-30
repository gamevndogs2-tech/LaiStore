<?php
require_once 'config.php';
checkLogin();

// Kiểm tra quyền Shipper
$roles = explode(',', $_SESSION['user_role'] ?? 'user');
if (!in_array('shipper', $roles)) {
    header("Location: index.php");
    exit();
}

// XỬ LÝ CẬP NHẬT TRẠNG THÁI ĐƠN HÀNG
if (isset($_GET['update_status']) && isset($_GET['id'])) {
    $status = $_GET['update_status'];
    $id = (int)$_GET['id'];
    
    $stmt = $conn->prepare("UPDATE orders SET status = ?, shipper_id = ? WHERE id = ?");
    $stmt->bind_param("sii", $status, $_SESSION['user_id'], $id);
    $stmt->execute();
    
    header("Location: shipper.php?alert=status_updated");
    exit();
}

// LẤY TOÀN BỘ ĐƠN HÀNG HỆ THỐNG DÀNH CHO SHIPPER
$stmt = $conn->prepare("SELECT * FROM orders ORDER BY id DESC");
$stmt->execute();
$orders = $stmt->get_result();

include 'header.php';
?>

<div class="mb-8 flex items-center justify-between">
    <div>
        <div class="flex items-center gap-2">
            <span class="bg-sky-100 text-sky-600 text-[10px] font-extrabold uppercase px-2.5 py-1 rounded-md border border-sky-200">Khu Vực Shipper</span>
            <h2 class="text-3xl font-extrabold text-slate-900">Quản Lý Giao Hàng</h2>
        </div>
        <p class="text-slate-500 text-sm mt-1">Tiếp nhận đơn hàng mới và kiểm tra hình thức thu tiền / thanh toán.</p>
    </div>
    <div class="bg-sky-50 border border-sky-100 text-sky-700 px-4 py-2 rounded-2xl text-xs font-bold">
        <i class="fa-solid fa-truck-ramp-box mr-1"></i> Bảng điều khiển vận chuyển
    </div>
</div>

<!-- BẢNG ĐIỀU KHIỂN SHIPPER -->
<div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-100 text-slate-400 uppercase text-xs font-bold">
                <th class="p-4">Mã Đơn</th>
                <th class="p-4">Khách Hàng</th>
                <th class="p-4">Địa Chỉ Giao Hàng & SĐT</th>
                <th class="p-4">Phương Thức TT</th>
                <th class="p-4">Tiền Thu (COD)</th>
                <th class="p-4">Trạng Thái</th>
                <th class="p-4 text-center">Thao Tác Cập Nhật</th>
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
                            <div class="text-xs text-slate-500 mt-0.5"><i class="fa-solid fa-phone text-slate-400 mr-1"></i><?= htmlspecialchars($o['phone']) ?></div>
                        </td>
                        
                        <!-- HIỂN THỊ PHƯƠNG THỨC THANH TOÁN -->
                        <td class="p-4">
                            <?php if ($o['payment_method'] === 'WALLET'): ?>
                                <span class="bg-indigo-50 text-indigo-600 border border-indigo-200/60 px-2.5 py-1 rounded-lg text-xs font-extrabold inline-flex items-center gap-1">
                                    <i class="fa-solid fa-wallet"></i> Ví Wallet
                                </span>
                            <?php else: ?>
                                <span class="bg-amber-50 text-amber-600 border border-amber-200/60 px-2.5 py-1 rounded-lg text-xs font-extrabold inline-flex items-center gap-1">
                                    <i class="fa-solid fa-money-bill-wave"></i> Tiền Mặt (COD)
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- SỐ TIỀN CẦN THU CỦA KHÁCH -->
                        <td class="p-4">
                            <?php if ($o['payment_method'] === 'WALLET'): ?>
                                <span class="text-xs font-extrabold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200/60">
                                    0 đ (Đã thanh toán)
                                </span>
                            <?php else: ?>
                                <span class="font-black text-rose-600 text-sm">
                                    <?= number_format($o['total_amount']) ?> đ
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- TRẠNG THÁI GIAO HÀNG -->
                        <td class="p-4">
                            <?php if ($o['status'] === 'PENDING'): ?>
                                <span class="bg-amber-50 text-amber-600 border border-amber-200/60 px-3 py-1 rounded-full text-xs font-bold inline-flex items-center gap-1.5"><i class="fa-solid fa-clock"></i> Chờ Nhận</span>
                            <?php elseif ($o['status'] === 'SHIPPING'): ?>
                                <span class="bg-sky-50 text-sky-600 border border-sky-200/60 px-3 py-1 rounded-full text-xs font-bold inline-flex items-center gap-1.5"><i class="fa-solid fa-truck-fast"></i> Đang Giao</span>
                            <?php elseif ($o['status'] === 'DELIVERED'): ?>
                                <span class="bg-emerald-50 text-emerald-600 border border-emerald-200/60 px-3 py-1 rounded-full text-xs font-bold inline-flex items-center gap-1.5"><i class="fa-solid fa-circle-check"></i> Đã Giao</span>
                            <?php else: ?>
                                <span class="bg-rose-50 text-rose-600 border border-rose-200/60 px-3 py-1 rounded-full text-xs font-bold inline-flex items-center gap-1.5"><i class="fa-solid fa-ban"></i> Đã Hủy</span>
                            <?php endif; ?>
                        </td>

                        <!-- THAO TÁC CẬP NHẬT -->
                        <td class="p-4 text-center">
                            <?php if ($o['status'] === 'PENDING'): ?>
                                <a href="shipper.php?id=<?= $o['id'] ?>&update_status=SHIPPING" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-xs font-bold shadow-md shadow-indigo-100 transition inline-flex items-center gap-1.5">
                                    <i class="fa-solid fa-hand-holding-hand"></i> Nhận Đơn Này
                                </a>
                            <?php elseif ($o['status'] === 'SHIPPING'): ?>
                                <a href="shipper.php?id=<?= $o['id'] ?>&update_status=DELIVERED" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-bold shadow-md shadow-emerald-100 transition inline-flex items-center gap-1.5">
                                    <i class="fa-solid fa-check"></i> Đã Giao Hàng
                                </a>
                            <?php else: ?>
                                <span class="text-slate-400 text-xs italic font-semibold">Hoàn tất</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="p-8 text-center text-slate-400">Hiện tại chưa có đơn hàng nào trong hệ thống.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('alert') === 'status_updated') {
    const Toast = Swal.mixin({
        toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true
    });
    Toast.fire({ icon: 'success', title: 'Cập nhật tiến độ giao hàng thành công!' });
}
</script>

<?php include 'footer.php'; ?>