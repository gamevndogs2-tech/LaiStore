<?php
require_once 'config.php';
checkLogin();

$current_user = (int)$_SESSION['user_id'];
$roles = explode(',', $_SESSION['user_role'] ?? 'user');
$is_merchant = in_array('merchant', $roles) || in_array('admin', $roles);

if (!$is_merchant) {
    header("Location: index.php");
    exit();
}

$store_brand_name = "LaiStore Official";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_msg'])) {
    $message = trim($_POST['reply_content'] ?? '');
    $receiver_id = (int)($_POST['customer_id'] ?? 0);

    if (!empty($message) && $receiver_id > 0) {
        $safe_msg = $conn->real_escape_string($message);
        $conn->query("INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES ($current_user, $receiver_id, '$safe_msg', NOW())");
    }
    header("Location: support.php?client_id=" . $receiver_id);
    exit();
}

$customers_query = $conn->query("
    SELECT DISTINCT u.id, u.username 
    FROM users u 
    JOIN messages m ON (u.id = m.sender_id OR u.id = m.receiver_id) 
    WHERE u.id != $current_user 
    ORDER BY m.id DESC
");

$customers = [];
if ($customers_query) {
    while ($c = $customers_query->fetch_assoc()) {
        $customers[] = $c;
    }
}

$selected_client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : (!empty($customers) ? $customers[0]['id'] : 0);

$chat_messages = [];
$selected_client_name = '';
if ($selected_client_id > 0) {
    $name_q = $conn->query("SELECT username FROM users WHERE id = $selected_client_id");
    if ($name_q && $nr = $name_q->fetch_assoc()) {
        $selected_client_name = $nr['username'];
    }

    $msg_q = $conn->query("
        SELECT * FROM messages 
        WHERE (sender_id = $current_user AND receiver_id = $selected_client_id) 
           OR (sender_id = $selected_client_id AND receiver_id = $current_user) 
        ORDER BY id ASC
    ");
    if ($msg_q) {
        while ($msg = $msg_q->fetch_assoc()) {
            $chat_messages[] = $msg;
        }
    }
}

include 'header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
    <div>
        <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900">Trung Tâm Tin Nhắn Khách Hàng</h2>
        <p class="text-slate-500 text-xs sm:text-sm mt-1">Hệ thống tổng đài hỗ trợ trực tuyến của doanh nghiệp.</p>
    </div>
    <div class="bg-indigo-50 border border-indigo-100 text-indigo-700 px-3.5 py-2 rounded-2xl text-xs font-extrabold flex items-center gap-2 shadow-sm">
        <i class="fa-solid fa-flag text-indigo-600"></i> Vai trò: <span class="text-slate-900 font-black"><?= $store_brand_name ?></span>
    </div>
</div>

<div class="bg-white rounded-3xl border border-slate-100 shadow-xl overflow-hidden grid grid-cols-12 h-[600px] sm:h-[650px]">
    
    <!-- CỘT TRÁI: DANH SÁCH KHÁCH HÀNG (Ẩn trên mobile nếu đang chọn chat hoặc hiển thị đầy đủ tùy chỉnh) -->
    <div class="col-span-12 md:col-span-4 border-r border-slate-100 flex flex-col bg-slate-50/50">
        <div class="p-3.5 sm:p-4 border-b border-slate-100 font-extrabold text-slate-800 text-xs sm:text-sm flex items-center gap-2">
            <i class="fa-solid fa-users text-indigo-600"></i> Hộp Thư Khách Hàng
        </div>
        <div class="flex-grow overflow-y-auto divide-y divide-slate-100">
            <?php if (!empty($customers)): ?>
                <?php foreach ($customers as $cus): 
                    $isActive = ($cus['id'] === $selected_client_id) ? 'bg-indigo-50 border-l-4 border-indigo-600' : 'hover:bg-slate-100/60';
                ?>
                    <a href="support.php?client_id=<?= $cus['id'] ?>" class="block p-3.5 sm:p-4 transition <?= $isActive ?>">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-extrabold text-slate-800 text-xs flex items-center gap-1.5">
                                <i class="fa-solid fa-user-circle text-slate-400"></i> <?= htmlspecialchars($cus['username']) ?>
                            </span>
                            <span class="text-[10px] text-indigo-600 font-bold"><i class="fa-solid fa-chevron-right"></i></span>
                        </div>
                        <p class="text-[11px] text-slate-400 font-medium">Bấm để xem trò chuyện</p>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-8 text-center text-slate-400 text-xs">Chưa có khách hàng nào nhắn tin.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- CỘT PHẢI: KHUNG CHAT CHI TIẾT -->
    <div class="col-span-12 md:col-span-8 flex flex-col bg-white">
        <div class="p-3.5 sm:p-4 border-b border-slate-100 flex items-center justify-between bg-white">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 sm:w-9 sm:h-9 bg-indigo-100 text-indigo-600 rounded-xl flex items-center justify-center font-bold text-xs sm:text-sm">
                    <i class="fa-solid fa-store"></i>
                </div>
                <div>
                    <h4 class="font-extrabold text-slate-800 text-xs sm:text-sm">
                        <?= $selected_client_name ? 'Khách: ' . htmlspecialchars($selected_client_name) : 'Chọn khách hàng' ?>
                    </h4>
                    <span class="text-[9px] sm:text-[10px] text-indigo-600 font-bold">Gửi dưới tên: <?= $store_brand_name ?></span>
                </div>
            </div>
        </div>

        <div id="shopChatBody" class="p-4 sm:p-6 flex-grow overflow-y-auto space-y-3 bg-slate-50/30">
            <?php if ($selected_client_id > 0): ?>
                <?php if (!empty($chat_messages)): ?>
                    <?php foreach ($chat_messages as $m): ?>
                        <?php if ((int)$m['sender_id'] === $current_user): ?>
                            <div class="flex justify-end mb-2">
                                <div class="max-w-[80%] sm:max-w-[70%]">
                                    <div class="text-[9px] text-right text-slate-400 font-bold mb-0.5"><?= $store_brand_name ?></div>
                                    <div class="bg-indigo-600 text-white text-xs p-3 rounded-2xl rounded-tr-none shadow-sm">
                                        <div><?= htmlspecialchars($m['message']) ?></div>
                                        <div class="text-[9px] text-indigo-200 text-right mt-1"><?= date('H:i', strtotime($m['created_at'])) ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="flex justify-start mb-2">
                                <div class="max-w-[80%] sm:max-w-[70%]">
                                    <div class="text-[9px] text-slate-400 font-bold mb-0.5"><?= htmlspecialchars($selected_client_name) ?></div>
                                    <div class="bg-white border border-slate-200 text-slate-800 text-xs p-3 rounded-2xl rounded-tl-none shadow-sm">
                                        <div><?= htmlspecialchars($m['message']) ?></div>
                                        <div class="text-[9px] text-slate-400 mt-1"><?= date('H:i', strtotime($m['created_at'])) ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-slate-400 text-xs py-16">Chưa có lịch sử tin nhắn với khách hàng này.</div>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center text-slate-400 text-xs py-16">Vui lòng chọn khách hàng ở danh sách bên trái.</div>
            <?php endif; ?>
        </div>

        <?php if ($selected_client_id > 0): ?>
            <form action="support.php" method="POST" class="p-3 sm:p-4 border-t border-slate-100 flex gap-2 sm:gap-3 bg-white">
                <input type="hidden" name="customer_id" value="<?= $selected_client_id ?>">
                <input type="text" name="reply_content" required placeholder="Nhập câu trả lời..." autocomplete="off" class="flex-grow px-4 py-3 rounded-xl border border-slate-200 text-xs sm:text-sm focus:outline-none focus:border-indigo-600 font-medium bg-slate-50/50">
                <button type="submit" name="reply_msg" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-4 sm:px-6 py-3 rounded-xl text-xs sm:text-sm transition shadow-md shadow-indigo-100 whitespace-nowrap">
                    <i class="fa-solid fa-paper-plane mr-1"></i> Gửi
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', function() {
    const body = document.getElementById('shopChatBody');
    if (body) {
        body.scrollTop = body.scrollHeight;
    }
});
</script>

<?php include 'footer.php'; ?>