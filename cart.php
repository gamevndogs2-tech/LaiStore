<?php
require_once 'config.php';
checkLogin();

$user_id = $_SESSION['user_id'];

// Lấy số dư ví hiện tại của khách hàng
$stmt_bal = $conn->prepare("SELECT balance FROM users WHERE id = ?");
$stmt_bal->bind_param("i", $user_id);
$stmt_bal->execute();
$user_balance = $stmt_bal->get_result()->fetch_assoc()['balance'] ?? 0;

// XỬ LÝ ĐẶT HÀNG / CHECKOUT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $customer_name  = trim($_POST['customer_name']);
    $phone          = trim($_POST['phone']);
    $address        = trim($_POST['address']);
    $payment_method = $_POST['payment_method'] ?? 'COD';
    $total_amount   = (float)$_POST['total_amount'];

    if (!empty($_SESSION['cart']) && $total_amount > 0) {
        
        // KIỂM TRA NẾU THANH TOÁN BẰNG VÍ LAISTORE WALLET
        if ($payment_method === 'WALLET') {
            if ($user_balance < $total_amount) {
                header("Location: cart.php?alert=insufficient_balance");
                exit();
            }
            // Trừ tiền trong ví
            $stmt_sub = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
            $stmt_sub->bind_param("di", $total_amount, $user_id);
            $stmt_sub->execute();
        }

        // Tạo đơn hàng mới
        $stmt_order = $conn->prepare("INSERT INTO orders (customer_id, customer_name, phone, address, total_amount, payment_method, status) VALUES (?, ?, ?, ?, ?, ?, 'PENDING')");
        $stmt_order->bind_param("isssds", $user_id, $customer_name, $phone, $address, $total_amount, $payment_method);
        $stmt_order->execute();
        $order_id = $conn->insert_id;

        // Lưu chi tiết từng sản phẩm
        $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        foreach ($_SESSION['cart'] as $p_id => $qty) {
            $p_query = $conn->prepare("SELECT price FROM products WHERE id = ?");
            $p_query->bind_param("i", $p_id);
            $p_query->execute();
            $p_price = $p_query->get_result()->fetch_assoc()['price'] ?? 0;

            $stmt_item->bind_param("iiid", $order_id, $p_id, $qty, $p_price);
            $stmt_item->execute();
        }

        // Xóa giỏ hàng sau khi đặt thành công
        unset($_SESSION['cart']);
        header("Location: orders.php?msg=success");
        exit();
    }
}

// XỬ LÝ CẬP NHẬT / XÓA KHỎI GIỎ HÀNG
if (isset($_GET['remove'])) {
    $rem_id = (int)$_GET['remove'];
    unset($_SESSION['cart'][$rem_id]);
    header("Location: cart.php");
    exit();
}

// Lấy danh sách sản phẩm trong giỏ
$cart_products = [];
$total_cart_amount = 0;
if (!empty($_SESSION['cart'])) {
    $ids = implode(',', array_keys($_SESSION['cart']));
    $result = $conn->query("SELECT * FROM products WHERE id IN ($ids)");
    while ($p = $result->fetch_assoc()) {
        $p['qty'] = $_SESSION['cart'][$p['id']];
        $p['subtotal'] = $p['price'] * $p['qty'];
        $total_cart_amount += $p['subtotal'];
        $cart_products[] = $p;
    }
}

include 'header.php';
?>

<div class="mb-8">
    <h2 class="text-3xl font-extrabold text-slate-900">Giỏ Hàng & Thanh Toán</h2>
    <p class="text-slate-500 text-sm mt-1">Xác nhận thông tin đơn hàng và lựa chọn phương thức thanh toán.</p>
</div>

<?php if (!empty($cart_products)): ?>
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        <!-- DANH SÁCH SẢN PHẨM GIỎ HÀNG (7 CỘT) -->
        <div class="lg:col-span-7 bg-white rounded-3xl border border-slate-100 shadow-sm p-6 space-y-4">
            <h3 class="font-extrabold text-slate-800 text-lg mb-4 flex items-center gap-2">
                <i class="fa-solid fa-cart-shopping text-indigo-600"></i> Sản Phẩm Đã Chọn
            </h3>
            
            <div class="divide-y divide-slate-100">
                <?php foreach ($cart_products as $p): ?>
                    <div class="py-4 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <img src="<?= $p['image_url'] ?: 'https://images.unsplash.com/photo-1526738549149-8e07eca6c147?w=500' ?>" class="w-16 h-16 rounded-2xl object-cover border border-slate-100">
                            <div>
                                <h4 class="font-extrabold text-slate-800 text-sm"><?= htmlspecialchars($p['name']) ?></h4>
                                <div class="text-xs text-slate-400 mt-1">
                                    <?= number_format($p['price']) ?> đ × <span class="font-bold text-slate-700"><?= $p['qty'] ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-black text-emerald-600 text-sm mb-1"><?= number_format($p['subtotal']) ?> đ</div>
                            <a href="cart.php?remove=<?= $p['id'] ?>" class="text-xs text-rose-500 hover:underline font-bold">Xóa</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="pt-4 border-t border-slate-100 flex justify-between items-center">
                <span class="text-sm font-bold text-slate-500">Tổng Giá Trị Đơn Hàng:</span>
                <span class="text-2xl font-black text-emerald-600"><?= number_format($total_cart_amount) ?> VNĐ</span>
            </div>
        </div>

        <!-- FORM CHECKOUT & PHƯƠNG THỨC THANH TOÁN (5 CỘT) -->
        <div class="lg:col-span-5 bg-white rounded-3xl border border-slate-100 shadow-sm p-6">
            <h3 class="font-extrabold text-slate-800 text-lg mb-6 flex items-center gap-2">
                <i class="fa-solid fa-truck-fast text-indigo-600"></i> Thông Tin Giao Hàng
            </h3>

            <form method="POST" action="cart.php" class="space-y-4">
                <input type="hidden" name="total_amount" value="<?= $total_cart_amount ?>">

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Họ Và Tên Người Nhận</label>
                    <input type="text" name="customer_name" required value="<?= htmlspecialchars($_SESSION['username']) ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-sm font-medium">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Số Điện Thoại Nhận Hàng</label>
                    <input type="text" name="phone" required placeholder="09xx xxx xxx" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-sm font-medium">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Địa Chỉ Nhận Hàng Chi Tiết</label>
                    <textarea name="address" required rows="2" placeholder="Số nhà, tên đường, phường/xã..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-sm font-medium"></textarea>
                </div>

                <!-- CHỌN PHƯƠNG THỨC THANH TOÁN -->
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Phương Thức Thanh Toán</label>
                    <div class="space-y-2">
                        <!-- COD -->
                        <label class="border border-slate-200 p-3 rounded-2xl flex items-center gap-3 cursor-pointer hover:border-indigo-600 transition bg-slate-50/50">
                            <input type="radio" name="payment_method" value="COD" checked class="accent-indigo-600 w-4 h-4">
                            <div>
                                <div class="text-xs font-extrabold text-slate-800"><i class="fa-solid fa-money-bill-wave text-emerald-600 mr-1"></i> Thanh toán khi nhận hàng (COD)</div>
                                <div class="text-[10px] text-slate-400">Trả tiền mặt cho Shipper khi nhận được hàng</div>
                            </div>
                        </label>

                        <!-- LAISTORE WALLET -->
                        <label class="border border-slate-200 p-3 rounded-2xl flex items-center justify-between cursor-pointer hover:border-indigo-600 transition bg-slate-50/50">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="payment_method" value="WALLET" class="accent-indigo-600 w-4 h-4">
                                <div>
                                    <div class="text-xs font-extrabold text-slate-800"><i class="fa-solid fa-wallet text-indigo-600 mr-1"></i> Ví LaiStore Wallet</div>
                                    <div class="text-[10px] text-slate-400">Trừ trực tiếp số dư trong tài khoản</div>
                                </div>
                            </div>
                            <span class="text-xs font-black text-emerald-600"><?= number_format($user_balance) ?> đ</span>
                        </label>
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" name="checkout" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold py-3.5 px-6 rounded-xl text-sm shadow-lg shadow-indigo-200 transition transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                        <i class="fa-solid fa-bag-shopping"></i> Xác Nhận Đặt Hàng
                    </button>
                </div>
            </form>
        </div>

    </div>
<?php else: ?>
    <div class="bg-white rounded-3xl p-12 text-center border border-slate-100 shadow-sm max-w-md mx-auto">
        <div class="w-16 h-16 bg-slate-100 text-slate-400 rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl">
            <i class="fa-solid fa-cart-flatbed"></i>
        </div>
        <h3 class="font-extrabold text-slate-800 text-lg mb-2">Giỏ Hàng Đang Trống</h3>
        <p class="text-xs text-slate-400 mb-6">Hãy chọn những sản phẩm ưng ý tại trang chủ để đặt hàng nhé!</p>
        <a href="index.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-6 py-3 rounded-xl text-xs transition inline-block">Khám Phá Sản Phẩm</a>
    </div>
<?php endif; ?>

<script>
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('alert') === 'insufficient_balance') {
    Swal.fire({
        title: 'Số Dư Ví Không Đủ!',
        text: 'Số dư Ví LaiStore Wallet không đủ để hoàn tất đơn hàng. Vui lòng nạp thêm tiền hoặc chọn phương thức COD!',
        icon: 'error',
        confirmButtonColor: '#4f46e5',
        confirmButtonText: 'Đã hiểu'
    });
}
</script>

<?php include 'footer.php'; ?>