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

// XỬ LÝ API NGẦM TRONG CHÍNH TRANG NÀY (HỖ TRỢ AJAX GỬI & LẤY TIN NHẮN)
if (isset($_GET['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    // 1. Gửi tin nhắn ngầm
    if ($_GET['ajax_action'] === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $message = trim($_POST['reply_content'] ?? '');
        $receiver_id = (int)($_POST['customer_id'] ?? 0);

        if (!empty($message) && $receiver_id > 0) {
            $safe_msg = $conn->real_escape_string($message);
            $conn->query("INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES ($current_user, $receiver_id, '$safe_msg', NOW())");
            echo json_encode(['status' => 'success']);
            exit();
        }
        echo json_encode(['status' => 'error']);
        exit();
    }

    // 2. Lấy danh sách tin nhắn ngầm để cập nhật realtime
    if ($_GET['ajax_action'] === 'fetch') {
        $client_id = (int)($_GET['client_id'] ?? 0);
        $messages = [];
        if ($client_id > 0) {
            $msg_q = $conn->query("
                SELECT * FROM messages 
                WHERE (sender_id = $current_user AND receiver_id = $client_id) 
                   OR (sender_id = $client_id AND receiver_id = $current_user) 
                ORDER BY id ASC
            ");
            if ($msg_q) {
                while ($msg = $msg_q->fetch_assoc()) {
                    $messages[] = [
                        'message' => htmlspecialchars($msg['message']),
                        'time' => date('H:i', strtotime($msg['created_at'])),
                        'is_me' => ((int)$msg['sender_id'] === $current_user)
                    ];
                }
            }
        }
        echo json_encode(['status' => 'success', 'messages' => $messages]);
        exit();
    }
}

// Lấy danh sách khách hàng đã nhắn tin
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

$selected_client_name = '';
if ($selected_client_id > 0) {
    $name_q = $conn->query("SELECT username FROM users WHERE id = $selected_client_id");
    if ($name_q && $nr = $name_q->fetch_assoc()) {
        $selected_client_name = $nr['username'];
    }
}

include 'header.php';
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h2 class="text-3xl font-extrabold text-slate-900">Trung Tâm Tin Nhắn Khách Hàng</h2>
        <p class="text-slate-500 text-sm mt-1">Hệ thống tổng đài hỗ trợ trực tuyến của doanh nghiệp.</p>
    </div>
    <div class="bg-indigo-50 border border-indigo-100 text-indigo-700 px-4 py-2 rounded-2xl text-xs font-extrabold flex items-center gap-2 shadow-sm">
        <i class="fa-solid fa-flag text-indigo-600"></i> Đang hoạt động với tư cách: <span class="text-slate-900 font-black"><?= $store_brand_name ?></span>
    </div>
</div>

<div class="bg-white rounded-3xl border border-slate-100 shadow-xl overflow-hidden grid grid-cols-12 h-[650px]">
    
    <!-- CỘT TRÁI: DANH SÁCH KHÁCH HÀNG (4 CỘT) -->
    <div class="col-span-12 md:col-span-4 border-r border-slate-100 flex flex-col bg-slate-50/50 h-full overflow-hidden">
        <div class="p-4 border-b border-slate-100 font-extrabold text-slate-800 text-sm flex items-center gap-2">
            <i class="fa-solid fa-users text-indigo-600"></i> Hộp Thư Khách Hàng
        </div>
        <div class="flex-grow overflow-y-auto divide-y divide-slate-100">
            <?php if (!empty($customers)): ?>
                <?php foreach ($customers as $cus): 
                    $isActive = ($cus['id'] === $selected_client_id) ? 'bg-indigo-50 border-l-4 border-indigo-600' : 'hover:bg-slate-100/60';
                ?>
                    <a href="support.php?client_id=<?= $cus['id'] ?>" class="block p-4 transition <?= $isActive ?>">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-extrabold text-slate-800 text-xs flex items-center gap-1.5">
                                <i class="fa-solid fa-user-circle text-slate-400"></i> <?= htmlspecialchars($cus['username']) ?>
                            </span>
                            <span class="text-[10px] text-indigo-600 font-bold"><i class="fa-solid fa-chevron-right"></i></span>
                        </div>
                        <p class="text-[11px] text-slate-400 font-medium">Bấm để xem lịch sử trò chuyện</p>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-8 text-center text-slate-400 text-xs">Chưa có khách hàng nào nhắn tin.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- CỘT PHẢI: KHUNG CHAT CHI TIẾT (8 CỘT) -->
    <div class="col-span-12 md:col-span-8 flex flex-col h-full bg-white overflow-hidden">
        <!-- Header Khung Chat -->
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-white shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-indigo-100 text-indigo-600 rounded-xl flex items-center justify-center font-bold text-sm">
                    <i class="fa-solid fa-store"></i>
                </div>
                <div>
                    <h4 class="font-extrabold text-slate-800 text-sm">
                        <?= $selected_client_name ? 'Khách hàng: ' . htmlspecialchars($selected_client_name) : 'Chọn khách hàng' ?>
                    </h4>
                    <span class="text-[10px] text-indigo-600 font-bold">Trả lời dưới tên: <?= $store_brand_name ?></span>
                </div>
            </div>
        </div>

        <!-- Nội dung tin nhắn (AJAX cập nhật liên tục) -->
        <div id="shopChatBody" class="p-6 flex-grow overflow-y-auto space-y-3 bg-slate-50/30 flex flex-col scroll-smooth">
            <div class="text-center text-slate-400 text-xs py-20">Đang tải tin nhắn...</div>
        </div>

        <!-- Form Trả Lời (Không reload trang) -->
        <?php if ($selected_client_id > 0): ?>
            <div class="p-4 border-t border-slate-100 flex gap-3 bg-white shrink-0">
                <input type="hidden" id="activeCustomerId" value="<?= $selected_client_id ?>">
                <input type="text" id="shopReplyInput" placeholder="Nhập câu trả lời với tư cách <?= $store_brand_name ?>..." autocomplete="off" class="flex-grow px-4 py-3 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-indigo-600 font-medium">
                <button type="button" onclick="sendShopMessage()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-6 py-3 rounded-xl text-sm transition shadow-lg shadow-indigo-100">
                    <i class="fa-solid fa-paper-plane mr-1"></i> Gửi Tin
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const activeClientId = <?= $selected_client_id ?>;
let shopInterval = null;

function fetchShopMessages(forceScroll = false) {
    if (!activeClientId) return;

    fetch(`support.php?ajax_action=fetch&client_id=${activeClientId}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                let html = '';
                if (data.messages && data.messages.length > 0) {
                    data.messages.forEach(m => {
                        if (m.is_me) {
                            html += `<div class="flex justify-end mb-2">
                                <div class="max-w-[70%]">
                                    <div class="text-[9px] text-right text-slate-400 font-bold mb-0.5"><?= $store_brand_name ?></div>
                                    <div class="bg-indigo-600 text-white text-xs p-3 rounded-2xl rounded-tr-none shadow-sm">
                                        <div>${m.message}</div>
                                        <div class="text-[9px] text-indigo-200 text-right mt-1">${m.time}</div>
                                    </div>
                                </div>
                            </div>`;
                        } else {
                            html += `<div class="flex justify-start mb-2">
                                <div class="max-w-[70%]">
                                    <div class="text-[9px] text-slate-400 font-bold mb-0.5"><?= htmlspecialchars($selected_client_name) ?></div>
                                    <div class="bg-white border border-slate-200 text-slate-800 text-xs p-3 rounded-2xl rounded-tl-none shadow-sm">
                                        <div>${m.message}</div>
                                        <div class="text-[9px] text-slate-400 mt-1">${m.time}</div>
                                    </div>
                                </div>
                            </div>`;
                        }
                    });
                } else {
                    html = '<div class="text-center text-slate-400 text-xs py-20">Chưa có lịch sử tin nhắn với khách hàng này.</div>';
                }

                const body = document.getElementById('shopChatBody');
                if (body) {
                    const isNearBottom = body.scrollHeight - body.scrollTop <= body.clientHeight + 100;
                    body.innerHTML = html;
                    if (forceScroll || isNearBottom || body.scrollTop === 0) {
                        body.scrollTop = body.scrollHeight;
                    }
                }
            }
        })
        .catch(err => console.error("Lỗi tải tin nhắn:", err));
}

function sendShopMessage() {
    const input = document.getElementById('shopReplyInput');
    const msg = input.value.trim();
    if (!msg || !activeClientId) return;

    const formData = new FormData();
    formData.append('reply_content', msg);
    formData.append('customer_id', activeClientId);

    fetch('support.php?ajax_action=send', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            input.value = '';
            fetchShopMessages(true); // Gửi xong cuộn xuống đáy ngay
        } else {
            alert('Không thể gửi tin nhắn.');
        }
    })
    .catch(err => console.error("Lỗi gửi:", err));
}

// Khởi chạy khi vào trang
document.addEventListener('DOMContentLoaded', function() {
    if (activeClientId > 0) {
        fetchShopMessages(true);
        // Tự động làm mới khung chat của shop mỗi 2 giây
        shopInterval = setInterval(() => fetchShopMessages(false), 2000);
    }

    const input = document.getElementById('shopReplyInput');
    if (input) {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendShopMessage();
            }
        });
    }
});
</script>

<?php include 'footer.php'; ?>