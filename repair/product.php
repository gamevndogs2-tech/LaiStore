<?php
require_once 'config.php';
checkLogin();

$roles = explode(',', $_SESSION['user_role']);
if (!in_array('merchant', $roles)) {
    header("Location: index.php");
    exit();
}

$msg = '';

// Thêm sản phẩm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $price = (int)$_POST['price'];
    $description = trim($_POST['description']);
    $image_url = trim($_POST['image_url']);

    $stmt = $conn->prepare("INSERT INTO products (merchant_id, name, category, price, description, image_url) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississ", $_SESSION['user_id'], $name, $category, $price, $description, $image_url);
    if ($stmt->execute()) {
        $msg = "Đăng sản phẩm thành công!";
    }
}

// Xóa sản phẩm
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND merchant_id = ?");
    $stmt->bind_param("ii", $del_id, $_SESSION['user_id']);
    $stmt->execute();
    header("Location: products.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM products WHERE merchant_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$my_products = $stmt->get_result();

include 'header.php';
?>

<h2>Quản Lý Sản Phẩm (Doanh Nghiệp)</h2>

<?php if ($msg): ?>
    <div style="background: #d1fae5; color: #065f46; padding: 10px; border-radius: 6px; margin-bottom: 15px;"><?= $msg ?></div>
<?php endif; ?>

<div class="card">
    <h3>Đăng Sản Phẩm Mới</h3>
    <form method="POST" action="products.php">
        <div class="form-group">
            <label>Tên sản phẩm:</label>
            <input type="text" name="name" required>
        </div>
        <div class="form-group">
            <label>Danh mục:</label>
            <input type="text" name="category" placeholder="Ví dụ: Điện thoại, Thời trang..." required>
        </div>
        <div class="form-group">
            <label>Giá (VNĐ):</label>
            <input type="number" name="price" required>
        </div>
        <div class="form-group">
            <label>Đường dẫn ảnh (URL):</label>
            <input type="text" name="image_url" placeholder="https://...">
        </div>
        <div class="form-group">
            <label>Mô tả:</label>
            <textarea name="description"></textarea>
        </div>
        <button type="submit" name="add_product" class="btn">Đăng Sản Phẩm</button>
    </form>
</div>

<h3>Sản Phẩm Đã Đăng</h3>
<table>
    <thead>
        <tr>
            <th>Hình Ảnh</th>
            <th>Tên Sản Phẩm</th>
            <th>Danh Mục</th>
            <th>Giá</th>
            <th>Thao Tác</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($my_products->num_rows > 0): ?>
            <?php while ($p = $my_products->fetch_assoc()): ?>
                <tr>
                    <td><img src="<?= $p['image_url'] ?>" style="width: 50px; height: 50px; object-fit: cover;"></td>
                    <td><b><?= htmlspecialchars($p['name']) ?></b></td>
                    <td><?= htmlspecialchars($p['category']) ?></td>
                    <td><?= number_format($p['price']) ?> đ</td>
                    <td>
                        <a href="products.php?delete=<?= $p['id'] ?>" class="btn btn-danger" onclick="return confirm('Bạn chắc chắn muốn xóa?')">Xóa</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="5">Bạn chưa đăng sản phẩm nào.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<?php include 'footer.php'; ?>