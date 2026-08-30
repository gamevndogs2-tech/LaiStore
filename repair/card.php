<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    foreach ($_POST['qty'] as $p_id => $quantity) {
        if ($quantity <= 0) unset($_SESSION['cart'][$p_id]);
        else $_SESSION['cart'][$p_id] = (int)$quantity;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    checkLogin();
    $name = trim($_POST['customer_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $payment = $_POST['payment_method'];
    $cart = $_SESSION['cart'] ?? [];

    if (!empty($cart) && !empty($name) && !empty($address)) {
        $total_amount = 0;
        $ids = implode(',', array_keys($cart));
        $res = $conn->query("SELECT id, price FROM products WHERE id IN ($ids)");
        $prices = [];
        while ($r = $res->fetch_assoc()) $prices[$r['id']] = $r['price'];

        foreach ($cart as $id => $qty) $total_amount += $prices[$id] * $qty;

        $stmt = $conn->prepare("INSERT INTO orders (customer_id, customer_name, phone, address, total_amount, payment_method) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssis", $_SESSION['user_id'], $name, $phone, $address, $total_amount, $payment);
        $stmt->execute();
        $order_id = $stmt->insert_id;

        $itemStmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        foreach ($cart as $id => $qty) {
            $itemStmt->bind_param("iiii", $order_id, $id, $qty, $prices[$id]);
            $itemStmt->execute();
        }

        unset($_SESSION['cart']);
        header("Location: orders.php?msg=success");
        exit();
    }
}

include 'header.php';

$cart = $_SESSION['cart'] ?? [];
$cart_products = [];
$total_price = 0;

if (!empty($cart)) {
    $ids = implode(',', array_keys($cart));
    $result = $conn->query("SELECT * FROM products WHERE id IN ($ids)");
    while ($row = $result->fetch_assoc()) {
        $row['qty'] = $cart[$row['id']];
        $row['subtotal'] = $row['price'] * $row['qty'];
        $total_price += $row['subtotal'];
        $cart_products[] = $row;
    }
}
?>

<h2>Giỏ Hàng Của Bạn</h2>

<?php if (!empty($cart_products)): ?>
    <form method="POST" action="cart.php">
        <table>
            <thead>
                <tr>
                    <th>Tên Sản Phẩm</th>
                    <th>Giá</th>
                    <th>Số Lượng</th>
                    <th>Thành Tiền</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cart_products as $item): ?>
                    <tr>
                        <td><b><?= htmlspecialchars($item['name']) ?></b></td>
                        <td><?= number_format($item['price']) ?> đ</td>
                        <td><input type="number" name="qty[<?= $item['id'] ?>]" value="<?= $item['qty'] ?>" min="0" style="width: 60px;"></td>
                        <td><b><?= number_format($item['subtotal']) ?> đ</b></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div style="margin-top: 15px; text-align: right;">
            <h3>Tổng Tiền: <span style="color: #10b981;"><?= number_format($total_price) ?> đ</span></h3>
            <button type="submit" name="update_cart" class="btn" style="background: #64748b;">Cập Nhật Giỏ Hàng</button>
        </div>
    </form>

    <hr style="margin: 30px 0;">

    <div class="card" style="max-width: 500px; margin: 0 auto;">
        <h3>Thông Tin Giao Hàng</h3>
        <form method="POST" action="cart.php">
            <div class="form-group">
                <label>Họ và Tên:</label>
                <input type="text" name="customer_name" required>
            </div>
            <div class="form-group">
                <label>Số điện thoại:</label>
                <input type="text" name="phone" required>
            </div>
            <div class="form-group">
                <label>Địa chỉ nhận hàng:</label>
                <textarea name="address" required></textarea>
            </div>
            <div class="form-group">
                <label>Hình thức thanh toán:</label>
                <select name="payment_method">
                    <option value="COD">Thanh toán khi nhận hàng (COD)</option>
                    <option value="BANK">Chuyển khoản ngân hàng</option>
                </select>
            </div>
            <button type="submit" name="checkout" class="btn" style="width: 100%;">Xác Nhận Đặt Hàng</button>
        </form>
    </div>
<?php else: ?>
    <p>Giỏ hàng đang trống. <a href="index.php">Quay lại mua sắm</a></p>
<?php endif; ?>

<?php include 'footer.php'; ?>