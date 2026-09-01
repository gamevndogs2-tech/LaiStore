<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'unauthorized', 'message' => 'Vui lòng đăng nhập để mua hàng!']);
    exit();
}

$product_id = (int)($_POST['product_id'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 1);

if ($product_id > 0) {
    // Kiểm tra sản phẩm có tồn tại không
    $stmt = $conn->prepare("SELECT id, name, price, stock, product_type FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($product = $res->fetch_assoc()) {
        // Khởi tạo giỏ hàng nếu chưa có
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        // Thêm hoặc cộng dồn số lượng vào giỏ hàng trong session
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => $quantity,
                'product_type' => $product['product_type']
            ];
        }

        // Đếm tổng số lượng sản phẩm trong giỏ
        $total_cart_items = count($_SESSION['cart']);

        echo json_encode([
            'status' => 'success', 
            'message' => 'Đã thêm sản phẩm vào giỏ hàng!',
            'cart_count' => $total_cart_items
        ]);
        exit();
    }
}

echo json_encode(['status' => 'error', 'message' => 'Sản phẩm không hợp lệ!']);