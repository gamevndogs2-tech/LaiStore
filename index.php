<?php
require_once 'config.php';

// Xử lý thêm vào giỏ hàng qua AJAX (trả về JSON)
if (isset($_GET['add_to_cart_ajax'])) {
    header('Content-Type: application/json');
    $p_id = (int)$_GET['add_to_cart_ajax'];
    
    $chk_p = $conn->prepare("SELECT name, product_type, stock FROM products WHERE id = ?");
    $chk_p->bind_param("i", $p_id);
    $chk_p->execute();
    $p_info = $chk_p->get_result()->fetch_assoc();

    if ($p_info) {
        $is_license = (($p_info['product_type'] ?? 'PHYSICAL') === 'LICENSE_KEY');
        $available = 0;

        if ($is_license) {
            $stock_q = $conn->query("SELECT COUNT(*) as total FROM product_keys WHERE product_id = $p_id AND is_sold = 0");
            $available = $stock_q->fetch_assoc()['total'] ?? 0;
        } else {
            $available = $p_info['stock'] ?? 0;
        }

        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        $current_qty = $_SESSION['cart'][$p_id] ?? 0;

        if ($current_qty < $available) {
            $_SESSION['cart'][$p_id] = $current_qty + 1;
            $total_cart_items = count($_SESSION['cart']);
            echo json_encode(['status' => 'success', 'message' => 'Đã thêm vào giỏ hàng thành công!', 'cart_count' => $total_cart_items]);
        } else {
            echo json_encode(['status' => 'out_of_stock', 'message' => 'Sản phẩm này đã hết hàng hoặc vượt quá số lượng tồn kho!']);
        }
        exit();
    }
    echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy sản phẩm!']);
    exit();
}

$search = $_GET['search'] ?? '';
$sql = "SELECT p.*, u.username as merchant_name,
               (SELECT COUNT(*) FROM product_keys pk WHERE pk.product_id = p.id AND pk.is_sold = 0) as available_keys 
        FROM products p 
        LEFT JOIN users u ON p.merchant_id = u.id 
        WHERE p.name LIKE ? 
        ORDER BY p.id DESC";
$stmt = $conn->prepare($sql);
$searchTerm = "%$search%";
$stmt->bind_param("s", $searchTerm);
$stmt->execute();
$products = $stmt->get_result();

include 'header.php';
?>

<div class="relative bg-gradient-to-r from-indigo-600 to-violet-600 rounded-2xl sm:rounded-3xl p-6 sm:p-12 text-white shadow-xl shadow-indigo-100 mb-6 sm:mb-10 overflow-hidden">
    <div class="relative z-10 max-w-2xl">
        <span class="bg-white/20 backdrop-blur-md text-[10px] sm:text-xs uppercase font-extrabold tracking-widest px-2.5 sm:px-3 py-1 rounded-full text-white inline-block mb-3">Mùa Mua Sắm 2026</span>
        <h1 class="text-2xl sm:text-5xl font-black leading-tight mb-2 sm:mb-4">Trải Nghiệm Mua Sắm Đỉnh Cao Tại LaiStore</h1>
        <p class="text-indigo-100 text-xs sm:text-base font-medium mb-6 sm:mb-8">Khám phá hàng ngàn sản phẩm công nghệ, key bản quyền tự động chất lượng nhất.</p>
        
        <form method="GET" action="index.php" class="flex gap-1.5 sm:gap-2 bg-white p-1.5 sm:p-2 rounded-2xl shadow-lg">
            <div class="flex-grow flex items-center px-2 sm:px-3 text-slate-400">
                <i class="fa-solid fa-magnifying-glass text-base sm:text-lg"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm kiếm sản phẩm..." class="w-full px-2 sm:px-3 py-1.5 sm:py-2 text-slate-800 focus:outline-none font-medium text-xs sm:text-sm">
            </div>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-4 sm:px-6 py-2.5 sm:py-3 rounded-xl text-xs sm:text-sm transition whitespace-nowrap">
                Tìm Kiếm
            </button>
        </form>
    </div>
</div>

<div class="flex items-center justify-between mb-4 sm:mb-6">
    <h2 class="text-xl sm:text-2xl font-bold text-slate-900">Sản Phẩm Nổi Bật</h2>
    <span class="text-xs sm:text-sm font-semibold text-slate-500"><?= $products->num_rows ?> sản phẩm</span>
</div>

<div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-6">
    <?php if ($products->num_rows > 0): ?>
        <?php while ($row = $products->fetch_assoc()): 
            $is_license = (($row['product_type'] ?? 'PHYSICAL') === 'LICENSE_KEY');
            $display_stock = $is_license ? $row['available_keys'] : ($row['stock'] ?? 0);
            $is_out_of_stock = ($display_stock <= 0);

            $img_src = (!empty($row['image_url']) && strlen($row['image_url']) > 10) ? $row['image_url'] : 'https://images.unsplash.com/photo-1526738549149-8e07eca6c147?w=500';
        ?>
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col overflow-hidden group relative">
                
                <div class="absolute top-2 left-2 sm:top-3 sm:left-3 z-10">
                    <?php if ($is_license): ?>
                        <span class="bg-indigo-600/90 backdrop-blur-md text-white px-2 py-0.5 sm:px-2.5 sm:py-1 rounded-lg text-[9px] sm:text-[10px] font-black uppercase shadow-sm">
                            <i class="fa-solid fa-key mr-0.5"></i> Key Online
                        </span>
                    <?php else: ?>
                        <span class="bg-slate-800/90 backdrop-blur-md text-white px-2 py-0.5 sm:px-2.5 sm:py-1 rounded-lg text-[9px] sm:text-[10px] font-black uppercase shadow-sm">
                            <i class="fa-solid fa-box mr-0.5"></i> Vật Lý
                        </span>
                    <?php endif; ?>
                </div>

                <div onclick='openDetailModal(<?= htmlspecialchars(json_encode($row, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>)' class="h-36 sm:h-48 w-full bg-slate-100 overflow-hidden relative cursor-pointer">
                    <img src="<?= $img_src ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500 <?= $is_out_of_stock ? 'grayscale opacity-60' : '' ?>" alt="Product Image">
                </div>

                <div class="p-3 sm:p-5 flex-grow flex flex-col justify-between">
                    <div>
                        <div class="text-[9px] sm:text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1"><?= htmlspecialchars($row['category']) ?></div>
                        <h3 onclick='openDetailModal(<?= htmlspecialchars(json_encode($row, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>)' class="font-bold text-slate-800 text-xs sm:text-base mb-1.5 sm:mb-2 group-hover:text-indigo-600 transition line-clamp-1 cursor-pointer">
                            <?= htmlspecialchars($row['name']) ?>
                        </h3>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2.5 sm:mb-3">
                            <div class="text-sm sm:text-lg font-extrabold text-emerald-600">
                                <?= number_format($row['price']) ?> <span class="text-[10px] sm:text-xs">đ</span>
                            </div>
                            <div>
                                <?php if ($is_out_of_stock): ?>
                                    <span class="text-[9px] sm:text-[10px] font-black text-rose-500 bg-rose-50 px-2 py-0.5 rounded-md border border-rose-100">Hết Hàng</span>
                                <?php else: ?>
                                    <?php if ($is_license): ?>
                                        <span class="text-[9px] sm:text-[10px] font-black text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md border border-indigo-100">Còn: <?= $display_stock ?> key</span>
                                    <?php else: ?>
                                        <span class="text-[9px] sm:text-[10px] font-black text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-100">Kho: <?= $display_stock ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 sm:gap-2">
                            <button onclick='openDetailModal(<?= htmlspecialchars(json_encode($row, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>)' class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-2 sm:py-2.5 px-2 rounded-xl text-[11px] sm:text-xs transition hidden sm:flex items-center justify-center">
                                <i class="fa-solid fa-eye mr-1"></i> Chi tiết
                            </button>
                            
                            <?php if ($is_out_of_stock): ?>
                                <button disabled class="bg-slate-100 text-slate-400 font-bold py-2 sm:py-2.5 px-2 rounded-xl text-[11px] sm:text-xs flex items-center justify-center col-span-full cursor-not-allowed">
                                    Hết Hàng
                                </button>
                            <?php else: ?>
                                <!-- Nút bấm gọi Ajax thêm giỏ hàng không load lại trang -->
                                <button onclick="addToCartAjax(<?= $row['id'] ?>)" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 sm:py-2.5 px-2 rounded-xl text-[11px] sm:text-xs flex items-center justify-center gap-1 transition col-span-full">
                                    <i class="fa-solid fa-cart-plus"></i> Thêm giỏ
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="col-span-full bg-white rounded-2xl p-8 sm:p-12 text-center border border-slate-100 shadow-sm">
            <div class="w-14 h-14 sm:w-16 sm:h-16 bg-slate-100 text-slate-400 rounded-2xl flex items-center justify-center mx-auto mb-3 sm:mb-4 text-xl sm:text-2xl">
                <i class="fa-solid fa-box-open"></i>
            </div>
            <h3 class="font-bold text-slate-700 text-base sm:text-lg mb-1">Chưa Có Sản Phẩm Nào</h3>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL CHI TIẾT SẢN PHẨM -->
<div id="detailModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-end sm:items-center justify-center p-0 sm:p-4">
    <div class="bg-white rounded-t-3xl sm:rounded-3xl max-w-2xl w-full p-5 sm:p-8 shadow-2xl relative max-h-[85vh] sm:max-h-[90vh] overflow-y-auto animate-slide-up">
        <button onclick="closeDetailModal()" class="absolute top-4 right-4 sm:top-6 sm:right-6 text-slate-400 hover:text-slate-600 text-xl w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 items-start">
            <img id="detail_img" src="" class="w-full h-48 sm:h-64 object-cover rounded-2xl border border-slate-100">
            <div class="space-y-3 sm:space-y-4">
                <span id="detail_category" class="bg-indigo-50 text-indigo-600 text-[10px] font-extrabold uppercase px-3 py-1 rounded-md"></span>
                <h2 id="detail_name" class="text-xl sm:text-2xl font-black text-slate-900"></h2>
                <div id="detail_price" class="text-xl sm:text-2xl font-black text-emerald-600"></div>
                <div class="text-xs text-slate-400 font-semibold">Người bán: <span id="detail_merchant" class="text-slate-700 font-bold"></span></div>
                
                <hr class="border-slate-100">
                
                <div>
                    <h4 class="text-[11px] sm:text-xs font-bold text-slate-400 uppercase mb-1">Mô Tả Sản Phẩm</h4>
                    <p id="detail_desc" class="text-slate-600 text-xs sm:text-sm leading-relaxed whitespace-pre-line"></p>
                </div>

                <div class="pt-2 sm:pt-4 flex gap-2 sm:gap-3">
                    <button id="chatMerchantBtn" class="flex-1 bg-slate-900 hover:bg-slate-800 text-white font-bold py-2.5 sm:py-3 rounded-xl text-xs flex items-center justify-center gap-1.5 sm:gap-2 transition">
                        <i class="fa-solid fa-comments"></i> Nhắn Người Bán
                    </button>
                    <!-- Nút thêm giỏ hàng trong modal cũng hỗ trợ Ajax -->
                    <button id="modalAddToCartBtn" onclick="" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 sm:py-3 rounded-xl text-xs flex items-center justify-center gap-1.5 sm:gap-2 transition shadow-md shadow-indigo-200">
                        <i class="fa-solid fa-cart-plus"></i> Thêm Giỏ
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Hàm xử lý Thêm vào giỏ hàng bằng Ajax (không load lại trang)
function addToCartAjax(productId) {
    fetch('index.php?add_to_cart_ajax=' + productId)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Hiển thị thông báo Toast góc trên màn hình
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000,
                    timerProgressBar: true
                });
                Toast.fire({
                    icon: 'success',
                    title: data.message
                });

                // Tự động cập nhật lại con số trên biểu tượng giỏ hàng ở Header
                const cartBadge = document.querySelector('a[href="cart.php"] span');
                if (cartBadge) {
                    cartBadge.innerText = data.cart_count;
                    cartBadge.classList.remove('hidden');
                } else {
                    const cartLink = document.querySelector('a[href="cart.php"]');
                    if (cartLink && !cartLink.querySelector('span')) {
                        const newBadge = document.createElement('span');
                        newBadge.className = 'absolute -top-1 right-1 bg-indigo-600 text-white text-[10px] font-bold px-1.5 py-0.2 rounded-full';
                        newBadge.innerText = data.cart_count;
                        cartLink.style.position = 'relative';
                        cartLink.appendChild(newBadge);
                    }
                }
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Thông báo',
                    text: data.message
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Lỗi', 'Không thể kết nối đến máy chủ.', 'error');
        });
}

function openDetailModal(p) {
    document.getElementById('detail_img').src = (!empty(p.image_url) && p.image_url.length > 10) ? p.image_url : 'https://images.unsplash.com/photo-1526738549149-8e07eca6c147?w=500';
    document.getElementById('detail_name').innerText = p.name;
    document.getElementById('detail_category').innerText = p.category;
    document.getElementById('detail_price').innerText = new Intl.NumberFormat('vi-VN').format(p.price) + ' đ';
    document.getElementById('detail_merchant').innerText = p.merchant_name || 'Hệ thống LaiStore';
    document.getElementById('detail_desc').innerText = p.description || 'Chưa có mô tả chi tiết cho sản phẩm này.';
    
    // Gắn hàm Ajax cho nút thêm giỏ hàng trong modal
    document.getElementById('modalAddToCartBtn').setAttribute('onclick', 'addToCartAjax(' + p.id + ')');
    
    const chatBtn = document.getElementById('chatMerchantBtn');
    chatBtn.onclick = function() {
        closeDetailModal();
        const widget = document.getElementById('chatWidget');
        if (widget) {
            widget.classList.remove('hidden');
            widget.classList.add('flex');
            if (typeof fetchMessages === 'function') {
                fetchMessages();
            }
        } else {
            Swal.fire('Thông báo', 'Vui lòng đăng nhập để sử dụng tính năng nhắn tin!', 'info');
        }
    };

    document.getElementById('detailModal').classList.remove('hidden');
    document.getElementById('detailModal').classList.add('flex');
}

function empty(val) {
    return (val === undefined || val === null || val === '');
}

function closeDetailModal() {
    const modal = document.getElementById('detailModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}
</script>

<?php include 'footer.php'; ?>