<?php
require_once 'config.php';

// Thêm sản phẩm vào giỏ
if (isset($_GET['add_to_cart'])) {
    $p_id = (int)$_GET['add_to_cart'];
    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    $_SESSION['cart'][$p_id] = ($_SESSION['cart'][$p_id] ?? 0) + 1;
    header("Location: index.php?msg=added");
    exit();
}

$search = $_GET['search'] ?? '';
$sql = "SELECT * FROM products WHERE name LIKE ? ORDER BY id DESC";
$stmt = $conn->prepare($sql);
$searchTerm = "%$search%";
$stmt->bind_param("s", $searchTerm);
$stmt->execute();
$products = $stmt->get_result();

include 'header.php';
?>

<h2>Danh Sách Sản Phẩm</h2>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'added'): ?>
    <div style="background: #d1fae5; color: #065f46; padding: 10px; border-radius: 6px; margin-bottom: 15px;">
        ✅ Đã thêm sản phẩm vào giỏ hàng! <a href="cart.php"><b>Xem giỏ hàng</b></a>
    </div>
<?php endif; ?>

<form method="GET" action="index.php" style="margin-bottom: 20px; display: flex; gap: 10px;">
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm sản phẩm..." style="padding: 10px; border: 1px solid #ccc; border-radius: 6px; flex-grow: 1;">
    <button type="submit" class="btn">Tìm Kiếm</button>
</form>

<div class="product-grid">
    <?php if ($products->num_rows > 0): ?>
        <?php while ($row = $products->fetch_assoc()): ?>
            <div class="product-card">
                <img src="<?= $row['image_url'] ?: 'https://images.unsplash.com/photo-1526738549149-8e07eca6c147?w=500' ?>" class="product-img" alt="Sản phẩm">
                <div class="product-info">
                    <div>
                        <small style="color: #64748b;"><?= htmlspecialchars($row['category']) ?></small>
                        <h3 style="margin: 5px 0;"><?= htmlspecialchars($row['name']) ?></h3>
                        <div class="price"><?= number_format($row['price']) ?> đ</div>
                    </div>
                    <a href="index.php?add_to_cart=<?= $row['id'] ?>" class="btn" style="margin-top: 10px; text-align: center;">🛒 Thêm Vào Giỏ</a>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>Không tìm thấy sản phẩm nào.</p>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>