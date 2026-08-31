<?php
require_once 'config.php';
checkLogin();

$user_id = $_SESSION['user_id'];

// 1. XỬ LÝ LƯU HOẶC CẬP NHẬT ĐỊA CHỈ GIAO HÀNG MẶC ĐỊNH
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_address'])) {
    $saved_address = trim($_POST['saved_address']);
    $saved_phone = trim($_POST['saved_phone']);
    $saved_name = trim($_POST['saved_name']);

    if (!empty($saved_address) && !empty($saved_phone)) {
        $stmt_up_addr = $conn->prepare("UPDATE users SET address = ?, phone = ?, full_name = ? WHERE id = ?");
        $stmt_up_addr->bind_param("sssi", $saved_address, $saved_phone, $saved_name, $user_id);
        $stmt_up_addr->execute();
        header("Location: cart.php?alert=address_saved");
        exit();
    }
}

// Lấy thông tin user an toàn
$user_balance = 0;
$user_info = ['address' => '', 'phone' => '', 'full_name' => ''];

$stmt_user = $conn->prepare("SELECT balance, address, phone, full_name FROM users WHERE id = ?");
if ($stmt_user) {
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $res_user = $stmt_user->get_result();
    if ($row_u = $res_user->fetch_assoc()) {
        $user_balance = $row_u['balance'] ?? 0;
        $user_info = $row_u;
    }
}

// 2. XỬ LÝ TĂNG GIẢM SỐ LƯỢNG SẢN PHẨM TRONG GIỎ
if (isset($_GET['update_qty']) && isset($_GET['id'])) {
    $p_id = (int)$_GET['id'];
    $action = $_GET['update_qty'];

    if (isset($_SESSION['cart'][$p_id])) {
        if ($action === 'plus') {
            $_SESSION['cart'][$p_id]++;
        } elseif ($action === 'minus') {
            $_SESSION['cart'][$p_id]--;
            if ($_SESSION['cart'][$p_id] <= 0) {
                unset($_SESSION['cart'][$p_id]);
            }
        }
    }
    header("Location: cart.php");
    exit();
}

if (isset($_GET['remove'])) {
    $rem_id = (int)$_GET['remove'];
    unset($_SESSION['cart'][$rem_id]);
    header("Location: cart.php");
    exit();
}

// 3. XỬ LÝ THANH TOÁN & KIỂM TRA TỒN KHO KEY BẢN QUYỀN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $customer_name  = trim($_POST['customer_name']);
    $phone          = trim($_POST['phone']);
    $address        = trim($_POST['address']);
    $payment_method = $_POST['payment_method'] ?? 'COD';
    $total_amount   = (float)$_POST['total_amount'];

    if (!empty($_SESSION['cart']) && $total_amount > 0) {
        
        $has_license_key = false;

        // KIỂM TRA TỒN KHO & PHÂN LOẠI SẢN PHẨM TRONG GIỎ
        foreach ($_SESSION['cart'] as $p_id => $qty) {
            $chk_type = $conn->query("SELECT name, product_type FROM products WHERE id = $p_id");
            if ($chk_type && $row_t = $chk_type->fetch_assoc()) {
                $p_type = $row_t['product_type'] ?? 'PHYSICAL';
                
                if ($p_type === 'LICENSE_KEY') {
                    $has_license_key = true;

                    // Kiểm tra số lượng key thực tế còn lại trong kho
                    $stock_q = $conn->query("SELECT COUNT(*) as total FROM product_keys WHERE product_id = $p_id AND is_sold = 0");
                    $stock_res = $stock_q->fetch_assoc();
                    $available_keys = $stock_res['total'] ?? 0;

                    // Nếu mua vượt quá số key tồn kho hoặc hết sạch key -> Chặn lại ngay lập tức
                    if ($qty > $available_keys) {
                        header("Location: cart.php?alert=out_of_stock&product=" . urlencode($row_t['name']));
                        exit();
                    }
                }
            }
        }

        // NẾU CÓ KEY BẢN QUYỀN -> BẮT BUỘC THANH TOÁN QUA VÍ WALLET
        if ($has_license_key && $payment_method !== 'WALLET') {
            header("Location: cart.php?alert=license_wallet_only");
            exit();
        }

        // KIỂM TRA SỐ DƯ NẾU THANH TOÁN BẰNG VÍ WALLET
        if ($payment_method === 'WALLET') {
            if ($user_balance < $total_amount) {
                header("Location: cart.php?alert=insufficient_balance");
                exit();
            }
            $stmt_sub = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
            $stmt_sub->bind_param("di", $total_amount, $user_id);
            $stmt_sub->execute();
        }

        // KEY BẢN QUYỀN -> TRẠNG THÁI 'DELIVERED' (HOÀN TẤT, KHÔNG QUA SHIPPER)
        // VẬT LÝ -> TRẠNG THÁI 'PENDING' (CHỜ SHIPPER)
        $initial_status = $has_license_key ? 'DELIVERED' : 'PENDING';

        $stmt_order = $conn->prepare("INSERT INTO orders (customer_id, customer_name, phone, address, total_amount, payment_method, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_order->bind_param("isssdss", $user_id, $customer_name, $phone, $address, $total_amount, $payment_method, $initial_status);
        $stmt_order->execute();
        $order_id = $conn->insert_id;

        $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        
        foreach ($_SESSION['cart'] as $p_id => $qty) {
            $p_query = $conn->prepare("SELECT price FROM products WHERE id = ?");
            $p_query->bind_param("i", $p_id);
            $p_query->execute();
            $p_price = $p_query->get_result()->fetch_assoc()['price'] ?? 0;

            $stmt_item->bind_param("iiid", $order_id, $p_id, $qty, $p_price);
            $stmt_item->execute();

            // Tự động gán key bản quyền vào đơn hàng và đánh dấu đã bán
            for ($i = 0; $i < $qty; $i++) {
                $key_q = $conn->prepare("SELECT id FROM product_keys WHERE product_id = ? AND is_sold = 0 LIMIT 1");
                $key_q->bind_param("i", $p_id);
                $key_q->execute();
                $key_res = $key_q->get_result();

                if ($row_key = $key_res->fetch_assoc()) {
                    $key_id = $row_key['id'];
                    $update_key = $conn->prepare("UPDATE product_keys SET order_id = ?, is_sold = 1 WHERE id = ?");
                    $update_key->bind_param("ii", $order_id, $key_id);
                    $update_key->execute();
                }
            }
        }

        unset($_SESSION['cart']);
        header("Location: orders.php?alert=success");
        exit();
    }
}

$cart_products = [];
$total_cart_amount = 0;
if (!empty($_SESSION['cart'])) {
    $ids = implode(',', array_keys($_SESSION['cart']));
    $result = $conn->query("SELECT * FROM products WHERE id IN ($ids)");
    if ($result) {
        while ($p = $result->fetch_assoc()) {
            $p['qty'] = $_SESSION['cart'][$p['id']];
            $p['subtotal'] = $p['price'] * $p['qty'];
            $total_cart_amount += $p['subtotal'];
            $cart_products[] = $p;
        }
    }
}

include 'header.php';
?>

<div class="mb-6 sm:mb-8">
    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900">Giỏ Hàng & Thanh Toán</h2>
    <p class="text-slate-500 text-xs sm:text-sm mt-1">Điều chỉnh số lượng, quản lý địa chỉ nhận hàng và nhận key bản quyền trực tuyến.</p>
</div>

<?php if (!empty($cart_products)): ?>
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-8 items-start">
        
        <div class="lg:col-span-7 space-y-6">
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-4 sm:p-6 space-y-4">
                <h3 class="font-extrabold text-slate-800 text-base sm:text-lg mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-cart-shopping text-indigo-600"></i> Sản Phẩm Đã Chọn
                </h3>
                
                <div class="divide-y divide-slate-100">
                    <?php foreach ($cart_products as $p): ?>
                        <div class="py-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <img src="<?= $p['image_url'] ?: 'https://images.unsplash.com/photo-1526738549149-8e07eca6c147?w=500' ?>" class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl object-cover border border-slate-100 flex-shrink-0">
                                <div>
                                    <h4 class="font-extrabold text-slate-800 text-xs sm:text-sm"><?= htmlspecialchars($p['name']) ?></h4>
                                    <div class="text-[11px] sm:text-xs text-emerald-600 font-bold mt-0.5">
                                        <?= number_format($p['price']) ?> đ / sản phẩm
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between w-full sm:w-auto gap-4">
                                <div class="flex items-center border border-slate-200 rounded-xl bg-slate-50 overflow-hidden">
                                    <a href="cart.php?update_qty=minus&id=<?= $p['id'] ?>" class="px-3 py-1.5 text-slate-600 hover:bg-slate-200 font-bold text-xs transition">
                                        <i class="fa-solid fa-minus"></i>
                                    </a>
                                    <span class="px-3 py-1.5 text-xs font-black text-slate-800 bg-white"><?= $p['qty'] ?></span>
                                    <a href="cart.php?update_qty=plus&id=<?= $p['id'] ?>" class="px-3 py-1.5 text-slate-600 hover:bg-slate-200 font-bold text-xs transition">
                                        <i class="fa-solid fa-plus"></i>
                                    </a>
                                </div>

                                <div class="text-right">
                                    <div class="font-black text-emerald-600 text-xs sm:text-sm mb-1"><?= number_format($p['subtotal']) ?> đ</div>
                                    <a href="cart.php?remove=<?= $p['id'] ?>" class="text-[11px] text-rose-500 hover:underline font-bold">Xóa</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="pt-4 border-t border-slate-100 flex justify-between items-center">
                    <span class="text-xs sm:text-sm font-bold text-slate-500">Tổng Giá Trị Đơn Hàng:</span>
                    <span class="text-xl sm:text-2xl font-black text-emerald-600"><?= number_format($total_cart_amount) ?> VNĐ</span>
                </div>
            </div>

            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-4 sm:p-6">
                <h3 class="font-extrabold text-slate-800 text-base mb-2 flex items-center gap-2">
                    <i class="fa-solid fa-address-card text-indigo-600"></i> Lưu Thông Tin Giao Hàng Mặc Định
                </h3>
                <p class="text-xs text-slate-400 mb-4">Lưu lại để các lần mua sau tự động điền nhanh chóng.</p>

                <form method="POST" action="cart.php" class="space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Họ Tên Mặc Định</label>
                            <input type="text" name="saved_name" required value="<?= htmlspecialchars($user_info['full_name'] ?? $_SESSION['username']) ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-medium bg-slate-50/50">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Số Điện Thoại Mặc Định</label>
                            <input type="text" name="saved_phone" required value="<?= htmlspecialchars($user_info['phone'] ?? '') ?>" placeholder="09xx..." class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-medium bg-slate-50/50">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Địa Chỉ Nhận Hàng Mặc Định</label>
                        <input type="text" name="saved_address" required value="<?= htmlspecialchars($user_info['address'] ?? '') ?>" placeholder="Số nhà, đường, phường/xã..." class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-medium bg-slate-50/50">
                    </div>
                    <button type="submit" name="save_address" class="bg-slate-800 hover:bg-slate-900 text-white font-bold px-4 py-2 rounded-xl text-xs transition shadow-sm">
                        <i class="fa-solid fa-floppy-disk mr-1"></i> Lưu Địa Chỉ Này
                    </button>
                </form>
            </div>
        </div>

        <div class="lg:col-span-5 bg-white rounded-3xl border border-slate-100 shadow-sm p-4 sm:p-6">
            <h3 class="font-extrabold text-slate-800 text-base sm:text-lg mb-5 flex items-center gap-2">
                <i class="fa-solid fa-truck-fast text-indigo-600"></i> Tiến Hành Đặt Hàng
            </h3>

            <form method="POST" action="cart.php" class="space-y-4">
                <input type="hidden" name="total_amount" value="<?= $total_cart_amount ?>">

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Họ Và Tên Người Nhận</label>
                    <input type="text" name="customer_name" required value="<?= htmlspecialchars($user_info['full_name'] ?? $_SESSION['username']) ?>" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-xs sm:text-sm font-medium bg-slate-50/50">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Số Điện Thoại</label>
                    <input type="text" name="phone" required value="<?= htmlspecialchars($user_info['phone'] ?? '') ?>" placeholder="09xx xxx xxx" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-xs sm:text-sm font-medium bg-slate-50/50">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Địa Chỉ Nhận Hàng / Giao Key</label>
                    <textarea name="address" required rows="2" placeholder="Số nhà, đường, hoặc ghi chú nhận key..." class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-xs sm:text-sm font-medium bg-slate-50/50"><?= htmlspecialchars($user_info['address'] ?? '') ?></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Phương Thức Thanh Toán</label>
                    <div class="space-y-2">
                        <label class="border border-slate-200 p-3 rounded-2xl flex items-center gap-3 cursor-pointer hover:border-indigo-600 transition bg-slate-50/50">
                            <input type="radio" name="payment_method" value="COD" checked class="accent-indigo-600 w-4 h-4">
                            <div>
                                <div class="text-xs font-extrabold text-slate-800"><i class="fa-solid fa-money-bill-wave text-emerald-600 mr-1"></i> Thanh toán COD</div>
                                <div class="text-[10px] text-slate-400">Chỉ áp dụng cho sản phẩm vật lý</div>
                            </div>
                        </label>

                        <label class="border border-slate-200 p-3 rounded-2xl flex items-center justify-between cursor-pointer hover:border-indigo-600 transition bg-slate-50/50">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="payment_method" value="WALLET" class="accent-indigo-600 w-4 h-4">
                                <div>
                                    <div class="text-xs font-extrabold text-slate-800"><i class="fa-solid fa-wallet text-indigo-600 mr-1"></i> Ví LaiStore Wallet</div>
                                    <div class="text-[10px] text-slate-400">Bắt buộc khi mua Key bản quyền</div>
                                </div>
                            </div>
                            <span class="text-xs font-black text-emerald-600"><?= number_format($user_balance) ?> đ</span>
                        </label>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" name="checkout" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold py-3.5 px-6 rounded-xl text-sm shadow-lg shadow-indigo-200 transition flex items-center justify-center gap-2">
                        <i class="fa-solid fa-bag-shopping"></i> Xác Nhận Đặt Hàng & Nhận Key
                    </button>
                </div>
            </form>
        </div>

    </div>
<?php else: ?>
    <div class="bg-white rounded-3xl p-8 sm:p-12 text-center border border-slate-100 shadow-sm max-w-md mx-auto">
        <div class="w-16 h-16 bg-slate-100 text-slate-400 rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl">
            <i class="fa-solid fa-cart-flatbed"></i>
        </div>
        <h3 class="font-extrabold text-slate-800 text-lg mb-2">Giỏ Hàng Đang Trống</h3>
        <p class="text-xs text-slate-400 mb-6">Hãy chọn những sản phẩm hoặc key bản quyền ưng ý để mua sắm nhé!</p>
        <a href="index.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-6 py-3 rounded-xl text-xs transition inline-block">Khám Phá Sản Phẩm</a>
    </div>
<?php endif; ?>

<script>
const urlParams = new URLSearchParams(window.location.search);
const alertType = urlParams.get('alert');

if (alertType === 'insufficient_balance') {
    Swal.fire('Số Dư Không Đủ!', 'Ví LaiStore Wallet không đủ để thanh toán đơn hàng này!', 'error');
} else if (alertType === 'license_wallet_only') {
    Swal.fire('Yêu Cầu Thanh Toán Online!', 'Sản phẩm có chứa Key bản quyền bắt buộc phải thanh toán trực tuyến qua Ví LaiStore Wallet để hệ thống cấp code tự động ngay lập tức!', 'warning');
} else if (alertType === 'out_of_stock') {
    const productName = urlParams.get('product') || 'Sản phẩm';
    Swal.fire('Hết Key Trong Kho!', `Sản phẩm "${productName}" hiện không đủ số lượng key bản quyền trong kho để đáp ứng đơn hàng của bạn. Vui lòng giảm số lượng hoặc chọn sản phẩm khác!`, 'error');
} else if (alertType === 'address_saved') {
    const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
    Toast.fire({ icon: 'success', title: 'Đã lưu thông tin giao hàng mặc định!' });
}
</script>

<?php include 'footer.php'; ?>