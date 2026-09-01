<?php if (isset($_SESSION['user_id'])): 
    $current_u = (int)$_SESSION['user_id'];
    
    // Tìm ID người nhận (Cửa hàng / Admin)
    $target_u = 1;
    $res_u = $conn->query("SELECT id FROM users WHERE id != $current_u ORDER BY id ASC LIMIT 1");
    if ($res_u && $r_u = $res_u->fetch_assoc()) {
        $target_u = (int)$r_u['id'];
    }

    // Lấy lịch sử tin nhắn trực tiếp từ CSDL
    $msg_query = $conn->query("SELECT * FROM messages WHERE (sender_id = $current_u AND receiver_id = $target_u) OR (sender_id = $target_u AND receiver_id = $current_u) ORDER BY id ASC");
    $chat_list = [];
    if ($msg_query) {
        while ($m = $msg_query->fetch_assoc()) {
            $chat_list[] = $m;
        }
    }

    $auto_open = isset($_GET['open_chat']) ? true : false;
?>
<div class="fixed bottom-16 right-6 z-50 md:bottom-6">
    <!-- Nút Bật Khung Chat Floating -->
    <button onclick="toggleChatWidget()" class="bg-indigo-600 hover:bg-indigo-700 text-white w-12 h-12 md:w-14 md:h-14 rounded-full flex items-center justify-center shadow-2xl shadow-indigo-400 transition transform hover:scale-105">
        <i class="fa-solid fa-comments text-xl md:text-2xl"></i>
    </button>

    <!-- Khung Nhắn Tin Floating Window -->
    <div id="chatWidget" class="<?= $auto_open ? 'flex' : 'hidden' ?> absolute bottom-14 right-0 w-80 sm:w-96 bg-white rounded-3xl border border-slate-100 shadow-2xl overflow-hidden flex-col h-[420px] md:h-[480px]">
        <!-- Header Chat -->
        <div class="bg-indigo-600 text-white p-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-2.5 h-2.5 bg-emerald-400 rounded-full animate-ping"></div>
                <span class="font-extrabold text-sm"><i class="fa-solid fa-store mr-1"></i> LaiStore Official</span>
            </div>
            <button onclick="toggleChatWidget()" class="text-white/80 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <!-- Khung Hiển Thị Lịch Sử Tin Nhắn -->
        <div id="chatBody" class="p-4 flex-grow overflow-y-auto space-y-3 bg-slate-50/50">
            <?php if (!empty($chat_list)): ?>
                <?php foreach ($chat_list as $msg): ?>
                    <?php if ((int)$msg['sender_id'] === $current_u): ?>
                        <div class="flex justify-end mb-2">
                            <div class="bg-indigo-600 text-white text-xs p-3 rounded-2xl rounded-tr-none max-w-[80%] shadow-sm">
                                <div><?= htmlspecialchars($msg['message']) ?></div>
                                <div class="text-[9px] text-indigo-200 text-right mt-1"><?= date('H:i', strtotime($msg['created_at'])) ?></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="flex justify-start mb-2">
                            <div class="max-w-[80%]">
                                <div class="text-[9px] text-slate-400 font-bold mb-0.5">LaiStore Official</div>
                                <div class="bg-white border border-slate-200 text-slate-800 text-xs p-3 rounded-2xl rounded-tl-none shadow-sm">
                                    <div><?= htmlspecialchars($msg['message']) ?></div>
                                    <div class="text-[9px] text-slate-400 mt-1"><?= date('H:i', strtotime($msg['created_at'])) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center text-slate-400 text-xs py-8">Hãy gửi tin nhắn để trao đổi trực tiếp với LaiStore Official!</div>
            <?php endif; ?>
        </div>

        <form action="send_msg.php" method="POST" class="p-3 bg-white border-t border-slate-100 flex gap-2">
            <input type="hidden" name="receiver_id" value="<?= $target_u ?>">
            <input type="text" name="msg_content" required placeholder="Nhập câu hỏi của bạn..." autocomplete="off" class="flex-grow px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-indigo-600 font-medium">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-xs font-bold transition">
                <i class="fa-solid fa-paper-plane"></i>
            </button>
        </form>
    </div>
</div>

<script>
function toggleChatWidget() {
    const widget = document.getElementById('chatWidget');
    if (!widget) return;
    widget.classList.toggle('hidden');
    widget.classList.toggle('flex');
    scrollChatToBottom();
}

function scrollChatToBottom() {
    const chatBody = document.getElementById('chatBody');
    if (chatBody) {
        chatBody.scrollTop = chatBody.scrollHeight;
    }
}

document.addEventListener('DOMContentLoaded', scrollChatToBottom);
</script>
<?php endif; ?>

<!-- ========================================== -->
<!-- THANH MENU RÚT GỌN CỐ ĐỊNH DƯỚI ĐÁY (MOBILE) -->
<!-- ========================================== -->
<div class="md:hidden fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-md border-t border-slate-200 z-40 px-2 py-2 flex items-center justify-around shadow-lg">
    <a href="index.php" class="flex flex-col items-center text-slate-600 hover:text-indigo-600 text-[10px] font-bold transition">
        <i class="fa-solid fa-house text-base mb-0.5"></i>
        Trang Chủ
    </a>
    <a href="cart.php" class="relative flex flex-col items-center text-slate-600 hover:text-indigo-600 text-[10px] font-bold transition">
        <i class="fa-solid fa-cart-shopping text-base mb-0.5"></i>
        Giỏ Hàng
        <?php if (!empty($_SESSION['cart'])): ?>
            <span class="absolute -top-1 right-0 bg-indigo-600 text-white text-[9px] font-black px-1.5 py-0.2 rounded-full"><?= count($_SESSION['cart']) ?></span>
        <?php endif; ?>
    </a>
    <a href="orders.php" class="flex flex-col items-center text-slate-600 hover:text-indigo-600 text-[10px] font-bold transition">
        <i class="fa-solid fa-box text-base mb-0.5"></i>
        Đơn Hàng
    </a>
    <button onclick="toggleMobileMenuDrawer()" class="flex flex-col items-center text-slate-600 hover:text-indigo-600 text-[10px] font-bold transition focus:outline-none">
        <i class="fa-solid fa-bars text-base mb-0.5"></i>
        Menu
    </button>
</div>

<!-- KHUNG MENU MỞ RA / THU LẠI TRÊN MOBILE (DRAWER) -->
<div id="mobileMenuDrawer" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-end transition-all md:hidden">
    <div class="bg-white w-full rounded-t-3xl p-5 shadow-2xl space-y-4 max-h-[80vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="font-extrabold text-sm text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-compass text-indigo-600"></i> Danh Mục & Tiện Ích
            </div>
            <button onclick="toggleMobileMenuDrawer()" class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center font-bold">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="grid grid-cols-2 gap-2.5 text-xs font-bold">
            <a href="index.php" class="p-3 bg-slate-50 rounded-2xl flex items-center gap-2.5 text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 transition">
                <i class="fa-solid fa-house text-indigo-600"></i> Trang Chủ
            </a>
            <a href="cart.php" class="p-3 bg-slate-50 rounded-2xl flex items-center gap-2.5 text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 transition">
                <i class="fa-solid fa-cart-shopping text-indigo-600"></i> Giỏ Hàng
            </a>
            <a href="orders.php" class="p-3 bg-slate-50 rounded-2xl flex items-center gap-2.5 text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 transition">
                <i class="fa-solid fa-box text-indigo-600"></i> Đơn Hàng
            </a>
            <a href="account.php" class="p-3 bg-slate-50 rounded-2xl flex items-center gap-2.5 text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 transition">
                <i class="fa-solid fa-user-gear text-indigo-600"></i> Tài Khoản
            </a>

            <?php if (isset($_SESSION['user_id'])): ?>
                <?php $roles = explode(',', $_SESSION['user_role'] ?? 'user'); ?>
                <?php if (in_array('merchant', $roles)): ?>
                    <a href="products.php" class="p-3 bg-indigo-50 text-indigo-700 rounded-2xl flex items-center gap-2.5 col-span-2">
                        <i class="fa-solid fa-store"></i> Quản Lý Bán Hàng (Merchant)
                    </a>
                <?php endif; ?>
                <?php if (in_array('shipper', $roles)): ?>
                    <a href="shipper.php" class="p-3 bg-sky-50 text-sky-700 rounded-2xl flex items-center gap-2.5 col-span-2">
                        <i class="fa-solid fa-truck-fast"></i> Bảng Shipper Giao Hàng
                    </a>
                <?php endif; ?>
                <?php if (in_array('admin', $roles)): ?>
                    <a href="admin.php" class="p-3 bg-rose-50 text-rose-700 rounded-2xl flex items-center gap-2.5 col-span-2">
                        <i class="fa-solid fa-user-shield"></i> Bảng Quản Trị Admin
                    </a>
                <?php endif; ?>
                <a href="logout.php" class="p-3 bg-rose-50 text-rose-600 rounded-2xl flex items-center gap-2.5 col-span-2 justify-center mt-2">
                    <i class="fa-solid fa-right-from-bracket"></i> Đăng Xuất Tài Khoản
                </a>
            <?php else: ?>
                <a href="login.php" class="p-3 bg-indigo-600 text-white rounded-2xl flex items-center justify-center gap-2 col-span-2">
                    Đăng Nhập Ngay
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleMobileMenuDrawer() {
    const drawer = document.getElementById('mobileMenuDrawer');
    if (!drawer) return;
    drawer.classList.toggle('hidden');
    drawer.classList.toggle('flex');
}
</script>

<!-- ========================================== -->
<!-- FOOTER TRÀN FULL 2 BÊN MÀN HÌNH (DESKTOP)   -->
<!-- ========================================== -->
<!-- Bổ sung padding-bottom mb-16 trên mobile để nội dung không bị thanh menu che khuất -->
<div class="pb-16 md:pb-0"></div>

<footer class="hidden md:block w-screen relative left-1/2 right-1/2 -ml-[50vw] -mr-[50vw] bg-slate-900 text-slate-400 pt-12 pb-8 border-t border-slate-800 mt-20">
    <div class="max-w-[95%] lg:max-w-[1440px] mx-auto px-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 pb-8 border-b border-slate-800 items-center">
            
            <!-- Cột 1: Thương hiệu -->
            <div class="space-y-3">
                <a href="index.php" class="flex items-center gap-3 text-xl font-black text-white">
                    <div class="w-9 h-9 bg-indigo-600 text-white rounded-xl flex items-center justify-center shadow-md shadow-indigo-500/30">
                        <i class="fa-solid fa-bag-shopping"></i>
                    </div>
                    <span>LaiStore Corporation</span>
                </a>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Nền tảng thương mại điện tử hàng đầu thuộc Công ty Cổ phần Công nghệ LaiGroup. Phân phối sản phẩm công nghệ và key bản quyền tự động.
                </p>
            </div>

            <!-- Cột 2: Liên kết nhanh rút gọn -->
            <div class="flex flex-wrap gap-6 text-xs font-semibold justify-center">
                <a href="index.php" class="hover:text-white transition">Trang Chủ</a>
                <a href="cart.php" class="hover:text-white transition">Giỏ Hàng</a>
                <a href="orders.php" class="hover:text-white transition">Đơn Hàng</a>
                <a href="#" class="hover:text-white transition">Chính Sách Bảo Mật</a>
                <a href="#" class="hover:text-white transition">Hỗ Trợ 24/7</a>
            </div>

            <!-- Cột 3: Trụ sở & Liên hệ nhanh -->
            <div class="text-right text-xs space-y-1.5">
                <div class="text-white font-bold"><i class="fa-solid fa-phone text-indigo-500 mr-1.5"></i> Hotline: 1900 8888</div>
                <div><i class="fa-solid fa-envelope text-indigo-500 mr-1.5"></i> support@laistore.vn</div>
                <div class="text-slate-500">Tòa nhà LaiGroup Tower, Cầu Giấy, Hà Nội</div>
            </div>

        </div>

        <!-- Bản quyền -->
        <div class="pt-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-[11px] font-medium text-slate-500">
            <p>&copy; <?= date('Y') ?> LaiStore Corporation. All Rights Reserved.</p>
            <div class="flex items-center gap-4">
                <span class="flex items-center gap-1"><i class="fa-solid fa-shield-halved text-emerald-500"></i> Đã chứng nhận BCT</span>
                <span class="flex items-center gap-1"><i class="fa-solid fa-lock text-indigo-500"></i> SSL Secured</span>
            </div>
        </div>
    </div>
</footer>

</body>
</html>