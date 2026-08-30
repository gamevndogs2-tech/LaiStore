<?php
require_once 'config.php';
checkLogin();

if (isset($_GET['update_status']) && isset($_GET['id'])) {
    $roles = explode(',', $_SESSION['user_role']);
    if (in_array('shipper', $roles)) {
        $status = $_GET['update_status'];
        $id = (int)$_GET['id'];
        $stmt = $conn->prepare("UPDATE orders SET status = ?, shipper_id = ? WHERE id = ?");
        $stmt->bind_param("sii", $status, $_SESSION['user_id'], $id);
        $stmt->execute();
        header("Location: orders.php");
        exit();
    }
}

include 'header.php';

$roles = explode(',', $_SESSION['user_role']);

if (in_array('shipper', $roles)) {
    $sql = "SELECT * FROM orders ORDER BY id DESC";
    $stmt = $conn->prepare($sql);
} else {
    $sql = "SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
}

$stmt->execute();
$orders = $stmt->get_result();
?>

<h2>Quản Lý Đơn Hàng</h2>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
    <div style="background: #d1fae5; color: #065f46; padding: 10px; border-radius: 6px; margin-bottom: 15px;">
        🎉 Đặt hàng thành công! Đơn hàng của bạn đang được xử lý.
    </div>
<?php endif; ?>

<table>
    <thead>
        <tr>
            <th>Mã Đơn</th>
            <th>Khách Hàng</th>
            <th>Địa Chỉ / SĐT</th>
            <th>Tổng Tiền</th>
            <th>Trạng Thái</th>
            <?php if (in_array('shipper', $roles)): ?><th>Thao Tác Shipper</th><?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php if ($orders->num_rows > 0): ?>
            <?php while ($row = $orders->fetch_assoc()): ?>
                <tr>
                    <td>#<?= $row['id'] ?></td>
                    <td><b><?= htmlspecialchars($row['customer_name']) ?></b></td>
                    <td><?= htmlspecialchars($row['address']) ?><br><small><?= htmlspecialchars($row['phone']) ?></small></td>
                    <td><b><?= number_format($row['total_amount']) ?> đ</b></td>
                    <td><span class="badge badge-<?= $row['status'] ?>"><?= $row['status'] ?></span></td>
                    <?php if (in_array('shipper', $roles)): ?>
                        <td>
                            <?php if ($row['status'] === 'PENDING'): ?>
                                <a href="orders.php?id=<?= $row['id'] ?>&update_status=SHIPPING" class="btn" style="font-size: 0.8rem;">Nhận Giao</a>
                            <?php elseif ($row['status'] === 'SHIPPING'): ?>
                                <a href="orders.php?id=<?= $row['id'] ?>&update_status=DELIVERED" class="btn" style="font-size: 0.8rem; background: #10b981;">Đã Giao</a>
                            <?php else: ?>
                                <i>Hoàn tất</i>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6">Chưa có đơn hàng nào.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<?php include 'footer.php'; ?>