<?php
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $conn->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(?)");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];

        header("Location: index.php");
        exit();
    } else {
        $error = 'Tài khoản hoặc mật khẩu không chính xác!';
    }
}

include 'header.php';
?>

<div class="card" style="max-width: 400px; margin: 40px auto;">
    <h2>Đăng Nhập</h2>
    <?php if ($error): ?><div style="color: red; margin-bottom: 10px;"><?= $error ?></div><?php endif; ?>

    <form method="POST" action="login.php">
        <div class="form-group">
            <label>Tên đăng nhập:</label>
            <input type="text" name="username" required>
        </div>
        <div class="form-group">
            <label>Mật khẩu:</label>
            <input type="password" name="password" required>
        </div>
        <button type="submit" class="btn" style="width: 100%;">Đăng Nhập</button>
    </form>
</div>

<?php include 'footer.php'; ?>