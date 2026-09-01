<?php
require_once 'config.php';
checkLogin();

$user_id = $_SESSION['user_id'];

// XỬ LÝ HỦY ĐƠN HÀNG (Chỉ cho phép hủy khi đơn ở trạng thái PENDING)
if (isset($_GET['cancel_id'])) {
    $cancel_id = (int)$_GET['cancel_id'];
    
    // Kiểm tra xem đơn hàng có đúng của user này và đang ở trạng thái PENDING không
    $chk_order = $conn->prepare("SELECT id, total_amount, payment_method FROM orders WHERE id = ? AND customer_id = ? AND status = 'PENDING'");
    $chk_order->bind_param("ii", $cancel_id, $user_id);
    $chk_order->execute();
    $ord_res = $chk_order->get_result();

    if ($ord_res->num_rows > 0) {
        $ord_data = $ord_res->fetch_assoc();
        
        // Cập nhật trạng thái đơn hàng thành CANCELLED
        $up_ord = $conn->prepare("UPDATE orders SET status = 'CANCELLED' WHERE id = ?");
        $up_ord->bind_param("i", $cancel_id);
        $up_ord->execute();

        // Hoàn tiền vào ví cho khách nếu trước đó chọn thanh toán bằng ví (WALLET)
        if (strtoupper($ord_data['payment_method']) === 'WALLET') {
            $refund_amt = $ord_data['total_amount'];
            $conn->query("UPDATE users SET balance = balance + $refund_amt WHERE id = $user_id");
        }

        // Hoàn lại số lượng tồn kho hoặc key nếu cần thiết
        $items_c = $conn->query("SELECT product_id, quantity FROM order_items WHERE order_id = $cancel_id");
        while ($it = $items_c->fetch_assoc()) {
            $p_id = $it['product_id'];
            $qty = $it['quantity'];
            $p_type_q = $conn->query("SELECT product_type FROM products WHERE id = $p_id");
            if ($p_t = $p_type_q->fetch_assoc()) {
                if (($p_t['product_type'] ?? 'PHYSICAL') === 'LICENSE_KEY') {
                    $conn->query("UPDATE product_keys SET order_id = NULL, is_sold = 0 WHERE order_id = $cancel_id AND product_id = $p_id");
                } else {
                    $conn->query("UPDATE products SET stock = stock + $qty WHERE id = $p_id");
                }
            }
        }

        header("Location: orders.php?alert=cancelled");
        exit();
    }
}

// Lấy danh sách đơn hàng của user hiện tại, kèm thông tin người vận chuyển (shipper)
$sql = "SELECT o.*, u.username as shipper_name, u.phone as shipper_phone,
               (SELECT GROUP_CONCAT(CONCAT(p.name, ' (x', oi.quantity, ')') SEPARATOR ', ') 
                FROM order_items oi JOIN products p ON oi.product_id = p.id 
                WHERE oi.order_id = o.id) as items_summary
        FROM orders o 
        LEFT JOIN users u ON o.shipper_id = u.id
        WHERE o.customer_id = ? 
        ORDER BY o.id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result();

include 'header.php';
?>

<div class="mb-6 sm:mb-8">
    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900">Lịch Sử Đơn Hàng Của Tôi</h2>
    <p class="text-slate-500 text-xs sm:text-sm mt-1">Theo dõi tiến độ giao hàng, mã key bản quyền và thông tin người vận chuyển trực tuyến.</p>
</div>

<?php if (isset($_GET['alert']) && $_GET['alert'] === 'success'): ?>
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 sm:px-5 py-3.5 rounded-2xl mb-6 flex items-center gap-2.5 font-medium text-xs sm:text-sm shadow-sm">
        <i class="fa-solid fa-circle-check text-emerald-500 text-lg"></i>
        <span>Đặt hàng thành công! Đơn hàng của bạn đã được ghi nhận.</span>
    </div>
<?php elseif (isset($_GET['alert']) && $_GET['alert'] === 'cancelled'): ?>
    <!-- Đã đổi sang tông màu xanh lá (emerald) thân thiện và trực quan hơn -->
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 sm:px-5 py-3.5 rounded-2xl mb-6 flex items-center gap-2.5 font-medium text-xs sm:text-sm shadow-sm">
        <i class="fa-solid fa-circle-check text-emerald-500 text-lg"></i>
        <span>Đã hủy đơn hàng thành công! Tiền (nếu thanh toán bằng ví) và tồn kho đã được hoàn lại.</span>
    </div>
<?php endif; ?>

<div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[900px]">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100 text-slate-400 uppercase text-[10px] sm:text-xs font-bold">
                    <th class="p-3.5 sm:p-4">Mã Đơn</th>
                    <th class="p-3.5 sm:p-4">Sản Phẩm</th>
                    <th class="p-3.5 sm:p-4">Tổng Tiền</th>
                    <th class="p-3.5 sm:p-4">Thanh Toán</th>
                    <th class="p-3.5 sm:p-4">Người Vận Chuyển (Shipper)</th>
                    <th class="p-3.5 sm:p-4">Trạng Thái</th>
                    <th class="p-3.5 sm:p-4 text-center">Thao Tác / Key Bản Quyền</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs sm:text-sm font-medium">
                <?php if ($orders && $orders->num_rows > 0): ?>
                    <?php while ($o = $orders->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="p-3.5 sm:p-4 font-black text-indigo-600">#<?= $o['id'] ?></td>
                            
                            <td class="p-3.5 sm:p-4 text-slate-800 font-bold max-w-xs">
                                <div><?= htmlspecialchars($o['items_summary'] ?? 'Sản phẩm kỹ thuật số') ?></div>
                                <div class="text-[10px] text-slate-400 font-normal mt-0.5">Nhận: <?= htmlspecialchars($o['customer_name']) ?> (<?= htmlspecialchars($o['phone']) ?>)</div>
                            </td>

                            <td class="p-3.5 sm:p-4 text-emerald-600 font-extrabold whitespace-nowrap">
                                <?= number_format($o['total_amount']) ?> đ
                            </td>

                            <td class="p-3.5 sm:p-4">
                                <span class="bg-slate-100 text-slate-700 px-2.5 py-1 rounded-lg text-[10px] font-black uppercase">
                                    <?= $o['payment_method'] ?>
                                </span>
                            </td>

                            <td class="p-3.5 sm:p-4">
                                <?php if (!empty($o['shipper_name'])): ?>
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 bg-sky-100 text-sky-700 rounded-full flex items-center justify-center font-bold text-xs">
                                            <i class="fa-solid fa-truck-fast"></i>
                                        </div>
                                        <div>
                                            <div class="font-extrabold text-slate-800"><?= htmlspecialchars($o['shipper_name']) ?></div>
                                            <?php if (!empty($o['shipper_phone'])): ?>
                                                <div class="text-[10px] text-slate-500 font-bold"><i class="fa-solid fa-phone mr-0.5"></i><?= htmlspecialchars($o['shipper_phone']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-slate-400 italic text-xs">Hệ thống đang phân công...</span>
                                <?php endif; ?>
                            </td>

                            <td class="p-3.5 sm:p-4">
                                <?php 
                                    $status = $o['status'];
                                    $badgeClass = 'bg-amber-50 text-amber-700 border-amber-200';
                                    $statusText = 'Chờ xử lý';
                                    if ($status === 'SHIPPING') {
                                        $badgeClass = 'bg-sky-50 text-sky-700 border-sky-200';
                                        $statusText = 'Đang giao hàng';
                                    } elseif ($status === 'DELIVERED') {
                                        $badgeClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                                        $statusText = 'Đã hoàn tất';
                                    } elseif ($status === 'CANCELLED') {
                                        $badgeClass = 'bg-rose-50 text-rose-700 border-rose-200';
                                        $statusText = 'Đã hủy';
                                    }
                                ?>
                                <span class="border px-2.5 py-1 rounded-lg text-[10px] sm:text-xs font-black uppercase <?= $badgeClass ?>">
                                    <?= $statusText ?>
                                </span>
                            </td>

                            <td class="p-3.5 sm:p-4 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <?php if ($o['status'] === 'PENDING'): ?>
                                        <a href="javascript:void(0)" onclick="confirmCancelOrder(<?= $o['id'] ?>)" class="bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold px-3 py-1.5 rounded-xl text-xs transition inline-flex items-center gap-1 whitespace-nowrap">
                                            <i class="fa-solid fa-ban"></i> Hủy Đơn
                                        </a>
                                    <?php endif; ?>
                                    
                                    <button onclick="openOrderKeys(<?= $o['id'] ?>)" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-600 font-bold px-3 py-1.5 rounded-xl text-xs transition inline-flex items-center gap-1.5 whitespace-nowrap">
                                        <i class="fa-solid fa-key"></i> Xem Key / Chi Tiết
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="p-10 text-center text-slate-400 text-xs sm:text-sm">Bạn chưa có đơn hàng nào trong lịch sử.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL XEM CHI TIẾT KEY BẢN QUYỀN TRONG ĐƠN -->
<div id="orderKeyModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4 border-b border-slate-100 pb-3">
            <h3 class="text-base font-bold text-slate-900"><i class="fa-solid fa-key text-indigo-600 mr-1"></i> Chi Tiết Key Bản Quyền Đơn Hàng</h3>
            <button onclick="closeOrderKeyModal()" class="text-slate-400 hover:text-slate-600 text-lg w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div id="orderKeyContent" class="space-y-2 text-xs">
            <span class="text-slate-400 italic">Đang tải thông tin...</span>
        </div>
    </div>
</div>

<script>
function confirmCancelOrder(orderId) {
    Swal.fire({
        title: 'Xác nhận hủy đơn hàng #' + orderId + '?',
        text: "Đơn hàng sẽ bị hủy, số lượng tồn kho và tiền thanh toán (nếu dùng ví) sẽ được hoàn trả!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Đồng ý hủy',
        cancelButtonText: 'Không'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'orders.php?cancel_id=' + orderId;
        }
    })
}

function openOrderKeys(orderId) {
    const modal = document.getElementById('orderKeyModal');
    const content = document.getElementById('orderKeyContent');
    content.innerHTML = '<span class="text-slate-400 italic">Đang tải thông tin...</span>';
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    fetch('get_order_keys.php?order_id=' + orderId)
        .then(res => res.text())
        .then(html => {
            content.innerHTML = html;
        })
        .catch(err => {
            content.innerHTML = '<span class="text-rose-500">Không thể tải dữ liệu key cho đơn hàng này.</span>';
        });
}

function closeOrderKeyModal() {
    const modal = document.getElementById('orderKeyModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
</script>

<?php include 'footer.php'; ?>