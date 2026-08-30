<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $roles    = $_POST['roles'] ?? ['user'];

    if (!empty($username) && !empty($password)) {
        $roleStr = implode(',', $roles);
        $hashedPass = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $hashedPass, $roleStr);

        if ($stmt->execute()) {
            $success = 'Đăng ký thành công! <a href="login.php">Đăng nhập ngay</a>';
        } else {
            $error = 'Tên tài khoản này đã tồn tại!';
        }
    } else {
        $error = 'Vui lòng điền đầy đủ thông tin!';
    }
}

include 'header.php';
?>

<div class="card" style="max-width: 450px; margin: 40px auto;">
    <h2>Đăng Ký Tài Khoản</h2>
    <?php if ($error): ?><div style="color: red; margin-bottom: 10px;"><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div style="color: green; margin-bottom: 10px;"><?= $success ?></div><?php endif; ?>

    <form method="POST" action="register.php">
        <div class="form-group">
            <label>Tên đăng nhập:</label>
            <input type="text" name="username" required>
        </div>
        <div class="form-group">
            <label>Mật khẩu:</label>
            <input type="password" name="password" required>
        </div>
        <div class="form-group">
            <label>Vai trò tài khoản:</label><br>
            <label><input type="checkbox" name="roles[]" value="user" checked> Khách Hàng</label><br>
            <label><input type="checkbox" name="roles[]" value="merchant"> Doanh Nghiệp (Bán hàng)</label><br>
            <label><input type="checkbox" name="roles[]" value="shipper"> Shipper (Giao hàng)</label>
        </div>
        <button type="submit" class="btn" style="width: 100%;">Tạo Tài Khoản</button>
    </form>
</div>

<?php include 'footer.php'; ?>