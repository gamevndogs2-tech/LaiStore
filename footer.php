</main>

<!-- ========================================== -->
<!-- CHAT WIDGET REALTIME AJAX KHÔNG RELOAD     -->
<!-- ========================================== -->
<?php if (isset($_SESSION['user_id'])): 
    $current_u = (int)$_SESSION['user_id'];
    $target_u = 1;
    $res_u = $conn->query("SELECT id FROM users WHERE id != $current_u ORDER BY id ASC LIMIT 1");
    if ($res_u && $r_u = $res_u->fetch_assoc()) {
        $target_u = (int)$r_u['id'];
    }
?>
<div class="fixed bottom-6 right-6 z-50">
    <!-- Nút Bật Khung Chat Floating -->
    <button onclick="toggleChatWidget()" class="bg-indigo-600 hover:bg-indigo-700 text-white w-14 h-14 rounded-full flex items-center justify-center shadow-2xl shadow-indigo-400 transition transform hover:scale-105">
        <i class="fa-solid fa-comments text-2xl"></i>
    </button>

    <!-- Khung Nhắn Tin Floating Window -->
    <div id="chatWidget" class="hidden absolute bottom-16 right-0 w-80 sm:w-96 bg-white rounded-3xl border border-slate-100 shadow-2xl overflow-hidden flex-col h-[480px]">
        <!-- Header Chat -->
        <div class="bg-indigo-600 text-white p-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-2.5 h-2.5 bg-emerald-400 rounded-full animate-ping"></div>
                <span class="font-extrabold text-sm"><i class="fa-solid fa-store mr-1"></i> LaiStore Official</span>
            </div>
            <button onclick="toggleChatWidget()" class="text-white/80 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <!-- Khung Hiển Thị Lịch Sử Tin Nhắn (Đã sửa lỗi cuộn trang & xem lại lịch sử) -->
        <div id="chatBody" class="p-4 flex-grow overflow-y-auto space-y-3 bg-slate-50/50 flex flex-col scroll-smooth">
            <div class="text-center text-slate-400 text-xs py-8">Đang tải tin nhắn...</div>
        </div>

        <!-- Form Ngầm AJAX -->
        <div class="p-3 bg-white border-t border-slate-100 flex gap-2">
            <input type="hidden" id="chatReceiverId" value="<?= $target_u ?>">
            <input type="text" id="chatInput" placeholder="Nhập câu hỏi của bạn..." autocomplete="off" class="flex-grow px-4 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-indigo-600 font-medium">
            <button type="button" onclick="sendAjaxMessage()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-xs font-bold transition">
                <i class="fa-solid fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>

<script>
let chatInterval = null;

function toggleChatWidget() {
    const widget = document.getElementById('chatWidget');
    if (!widget) return;

    widget.classList.toggle('hidden');
    widget.classList.toggle('flex');
    
    if (!widget.classList.contains('hidden')) {
        fetchChatMessages(true); // Lần đầu mở thì cuộn xuống đáy
        if (chatInterval) clearInterval(chatInterval);
        // Tự động tải lại tin nhắn ngầm mỗi 2 giây
        chatInterval = setInterval(() => fetchChatMessages(false), 2000);
    } else {
        if (chatInterval) clearInterval(chatInterval);
    }
}

function fetchChatMessages(forceScroll = false) {
    fetch('send_msg.php?action=fetch')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                let html = '';
                if (data.messages && data.messages.length > 0) {
                    data.messages.forEach(m => {
                        if (m.is_me) {
                            html += `<div class="flex justify-end mb-2">
                                <div class="bg-indigo-600 text-white text-xs p-3 rounded-2xl rounded-tr-none max-w-[80%] shadow-sm">
                                    <div>${m.message}</div>
                                    <div class="text-[9px] text-indigo-200 text-right mt-1">${m.time}</div>
                                </div>
                            </div>`;
                        } else {
                            html += `<div class="flex justify-start mb-2">
                                <div class="max-w-[80%]">
                                    <div class="text-[9px] text-slate-400 font-bold mb-0.5">LaiStore Official</div>
                                    <div class="bg-white border border-slate-200 text-slate-800 text-xs p-3 rounded-2xl rounded-tl-none shadow-sm">
                                        <div>${m.message}</div>
                                        <div class="text-[9px] text-slate-400 mt-1">${m.time}</div>
                                    </div>
                                </div>
                            </div>`;
                        }
                    });
                } else {
                    html = '<div class="text-center text-slate-400 text-xs py-8">Hãy gửi tin nhắn để trao đổi trực tiếp với LaiStore Official!</div>';
                }

                const chatBody = document.getElementById('chatBody');
                if (chatBody) {
                    // Kiểm tra xem người dùng có đang đứng ở gần đáy khung chat không
                    const isNearBottom = chatBody.scrollHeight - chatBody.scrollTop <= chatBody.clientHeight + 100;
                    
                    chatBody.innerHTML = html;

                    // Chỉ tự động cuộn xuống dưới khi mở khung chat, vừa gửi tin, hoặc người dùng đang ở đáy
                    if (forceScroll || isNearBottom || chatBody.scrollTop === 0) {
                        chatBody.scrollTop = chatBody.scrollHeight;
                    }
                }
            }
        })
        .catch(err => console.error("Lỗi tải tin nhắn:", err));
}

function sendAjaxMessage() {
    const input = document.getElementById('chatInput');
    const msg = input.value.trim();
    const receiverId = document.getElementById('chatReceiverId').value;
    if (!msg) return;

    const formData = new FormData();
    formData.append('msg_content', msg);
    formData.append('receiver_id', receiverId);

    fetch('send_msg.php?action=send', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            input.value = '';
            fetchChatMessages(true); // Gửi xong ép cuộn xuống đáy để thấy tin nhắn mới
        } else {
            alert(data.message || 'Không thể gửi tin nhắn.');
        }
    })
    .catch(err => console.error("Lỗi gửi:", err));
}

// Bắt phím Enter để gửi tin nhắn nhanh
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('chatInput');
    if (input) {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendAjaxMessage();
            }
        });
    }
});
</script>
<?php endif; ?>

<!-- ========================================== -->
<!-- FOOTER DOANH NGHIỆP ENTERPRISE             -->
<!-- ========================================== -->
<footer class="bg-slate-900 text-slate-400 pt-16 pb-12 border-t border-slate-800 mt-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-10 pb-12 border-b border-slate-800">
            
            <!-- Cột 1: Thông tin tập đoàn & Mạng xã hội -->
            <div class="lg:col-span-2 space-y-4">
                <a href="index.php" class="flex items-center gap-3 text-2xl font-black text-white">
                    <div class="w-10 h-10 bg-indigo-600 text-white rounded-xl flex items-center justify-center shadow-lg shadow-indigo-500/30">
                        <i class="fa-solid fa-bag-shopping"></i>
                    </div>
                    <span>LaiStore Corporation</span>
                </a>
                <p class="text-sm text-slate-400 leading-relaxed pr-6">
                    LaiStore là nền tảng thương mại điện tử hàng đầu thuộc Công ty Cổ phần Công nghệ LaiGroup. Chúng tôi cung cấp trải nghiệm mua sắm trực tuyến vượt trội với hệ thống phân phối đa kênh và dịch vụ giao hàng siêu tốc.
                </p>
                <div class="flex items-center gap-3 pt-2">
                    <a href="https://facebook.com" target="_blank" class="w-10 h-10 bg-slate-800 hover:bg-indigo-600 text-slate-300 hover:text-white rounded-xl flex items-center justify-center transition"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="https://youtube.com" target="_blank" class="w-10 h-10 bg-slate-800 hover:bg-rose-600 text-slate-300 hover:text-white rounded-xl flex items-center justify-center transition"><i class="fa-brands fa-youtube"></i></a>
                    <a href="https://tiktok.com" target="_blank" class="w-10 h-10 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-xl flex items-center justify-center transition"><i class="fa-brands fa-tiktok"></i></a>
                    <a href="https://github.com" target="_blank" class="w-10 h-10 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-xl flex items-center justify-center transition"><i class="fa-brands fa-github"></i></a>
                </div>
            </div>

            <!-- Cột 2: Danh mục liên kết -->
            <div>
                <h4 class="text-white font-bold text-sm uppercase tracking-wider mb-4">Về LaiStore</h4>
                <ul class="space-y-2.5 text-sm font-medium">
                    <li><a href="#" class="hover:text-indigo-400 transition">Giới thiệu tập đoàn</a></li>
                    <li><a href="#" class="hover:text-indigo-400 transition">Tuyển dụng Talent</a></li>
                    <li><a href="#" class="hover:text-indigo-400 transition">Tin tức & Truyền thông</a></li>
                    <li><a href="#" class="hover:text-indigo-400 transition">Chính sách bảo mật</a></li>
                    <li><a href="#" class="hover:text-indigo-400 transition">Điều khoản dịch vụ</a></li>
                </ul>
            </div>

            <!-- Cột 3: Hỗ trợ khách hàng -->
            <div>
                <h4 class="text-white font-bold text-sm uppercase tracking-wider mb-4">Hỗ Trợ Khách Hàng</h4>
                <ul class="space-y-2.5 text-sm font-medium">
                    <li><a href="#" class="hover:text-indigo-400 transition">Trung tâm trợ giúp 24/7</a></li>
                    <li><a href="#" class="hover:text-indigo-400 transition">Hướng dẫn mua hàng</a></li>
                    <li><a href="#" class="hover:text-indigo-400 transition">Chính sách đổi trả 1:1</a></li>
                    <li><a href="#" class="hover:text-indigo-400 transition">Tra cứu vận đơn Shipper</a></li>
                    <li><a href="#" class="hover:text-indigo-400 transition">Đăng ký bán hàng (Merchant)</a></li>
                </ul>
            </div>

            <!-- Cột 4: Liên hệ trụ sở -->
            <div>
                <h4 class="text-white font-bold text-sm uppercase tracking-wider mb-4">Trụ Sở Chính</h4>
                <ul class="space-y-3 text-sm font-medium">
                    <li class="flex items-start gap-3">
                        <i class="fa-solid fa-location-dot text-indigo-500 mt-1"></i>
                        <span>Tòa nhà LaiGroup Tower, Số 123 Đường Công Nghệ, Q. Cầu Giấy, Hà Nội</span>
                    </li>
                    <li class="flex items-center gap-3">
                        <i class="fa-solid fa-phone text-indigo-500"></i>
                        <span class="text-white font-bold">1900 8888 (Hotline 24/7)</span>
                    </li>
                    <li class="flex items-center gap-3">
                        <i class="fa-solid fa-envelope text-indigo-500"></i>
                        <span>support@laistore.vn</span>
                    </li>
                </ul>
            </div>

        </div>

        <!-- Bản quyền & Chứng nhận BCT -->
        <div class="pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs font-medium text-slate-500">
            <p>&copy; <?= date('Y') ?> LaiStore Corporation. All Rights Reserved. Giấy phép ĐKKD số 0108888888 do Sở KH&ĐTN cấp.</p>
            <div class="flex items-center gap-6">
                <span class="flex items-center gap-1"><i class="fa-solid fa-shield-halved text-emerald-500"></i> Đã chứng nhận BCT</span>
                <span class="flex items-center gap-1"><i class="fa-solid fa-lock text-indigo-500"></i> SSL 256-bit Encryption</span>
            </div>
        </div>
    </div>
</footer>

</body>
</html>