<?php
require_once 'config.php';

if (isset($_GET['add_to_cart'])) {
    $p_id = (int)$_GET['add_to_cart'];
    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    $_SESSION['cart'][$p_id] = ($_SESSION['cart'][$p_id] ?? 0) + 1;
    header("Location: index.php?msg=added");
    exit();
}

$search = $_GET['search'] ?? '';
$sql = "SELECT p.*, u.username as merchant_name FROM products p LEFT JOIN users u ON p.merchant_id = u.id WHERE p.name LIKE ? ORDER BY p.id DESC";
$stmt = $conn->prepare($sql);
$searchTerm = "%$search%";
$stmt->bind_param("s", $searchTerm);
$stmt->execute();
$products = $stmt->get_result();

include 'header.php';
?>

<!-- HERO BANNER -->
<div class="relative bg-gradient-to-r from-indigo-600 to-violet-600 rounded-3xl p-8 sm:p-12 text-white shadow-2xl shadow-indigo-200 mb-10 overflow-hidden">
    <div class="relative z-10 max-w-2xl">
        <span class="bg-white/20 backdrop-blur-md text-xs uppercase font-extrabold tracking-widest px-3 py-1 rounded-full text-white inline-block mb-4">Mùa Mua Sắm 2026</span>
        <h1 class="text-3xl sm:text-5xl font-black leading-tight mb-4">Trải Nghiệm Mua Sắm Đỉnh Cao Tại LaiStore</h1>
        <p class="text-indigo-100 text-sm sm:text-base font-medium mb-8">Khám phá hàng ngàn sản phẩm công nghệ, thời trang chất lượng nhất.</p>
        
        <form method="GET" action="index.php" class="flex gap-2 bg-white p-2 rounded-2xl shadow-lg">
            <div class="flex-grow flex items-center px-3 text-slate-400">
                <i class="fa-solid fa-magnifying-glass text-lg"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm kiếm tên sản phẩm..." class="w-full px-3 py-2 text-slate-800 focus:outline-none font-medium text-sm">
            </div>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-6 py-3 rounded-xl text-sm transition">
                Tìm Kiếm
            </button>
        </form>
    </div>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'added'): ?>
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-5 py-4 rounded-2xl mb-8 flex items-center justify-between font-medium text-sm shadow-sm">
        <div class="flex items-center gap-3">
            <i class="fa-solid fa-circle-check text-emerald-500 text-xl"></i>
            <span>Đã thêm sản phẩm vào giỏ hàng thành công!</span>
        </div>
        <a href="cart.php" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-2 rounded-xl text-xs transition">
            Xem Giỏ Hàng <i class="fa-solid fa-arrow-right ml-1"></i>
        </a>
    </div>
<?php endif; ?>

<!-- PRODUCT GRID -->
<div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl font-bold text-slate-900">Sản Phẩm Nổi Bật</h2>
    <span class="text-sm font-semibold text-slate-500"><?= $products->num_rows ?> sản phẩm</span>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
    <?php if ($products->num_rows > 0): ?>
        <?php while ($row = $products->fetch_assoc()): ?>
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col overflow-hidden group hover:-translate-y-1">
                <div onclick='openDetailModal(<?= json_encode($row, JSON_UNESCAPED_UNICODE) ?>)' class="h-48 w-full bg-slate-100 overflow-hidden relative cursor-pointer">
                    <img src="<?= $row['image_url'] ?: 'https://images.unsplash.com/photo-1526738549149-8e07eca6c147?w=500' ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500" alt="Product Image">
                    <span class="absolute top-3 left-3 bg-white/90 backdrop-blur-md text-indigo-600 text-[10px] font-extrabold uppercase px-2.5 py-1 rounded-lg shadow-sm">
                        <?= htmlspecialchars($row['category']) ?>
                    </span>
                </div>

                <div class="p-5 flex-grow flex flex-col justify-between">
                    <div>
                        <h3 onclick='openDetailModal(<?= json_encode($row, JSON_UNESCAPED_UNICODE) ?>)' class="font-bold text-slate-800 text-base mb-2 group-hover:text-indigo-600 transition line-clamp-1 cursor-pointer">
                            <?= htmlspecialchars($row['name']) ?>
                        </h3>
                        <p class="text-slate-500 text-xs line-clamp-2 mb-4">
                            <?= htmlspecialchars($row['description'] ?: 'Sản phẩm chính hãng chất lượng cao.') ?>
                        </p>
                    </div>

                    <div>
                        <div class="text-lg font-extrabold text-emerald-600 mb-4">
                            <?= number_format($row['price']) ?> <span class="text-xs">đ</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <button onclick='openDetailModal(<?= json_encode($row, JSON_UNESCAPED_UNICODE) ?>)' class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-2.5 px-3 rounded-xl text-xs transition">
                                <i class="fa-solid fa-eye mr-1"></i> Chi tiết
                            </button>
                            <a href="index.php?add_to_cart=<?= $row['id'] ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-3 rounded-xl text-xs flex items-center justify-center gap-1 transition">
                                <i class="fa-solid fa-cart-plus"></i> Thêm giỏ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="col-span-full bg-white rounded-2xl p-12 text-center border border-slate-100 shadow-sm">
            <div class="w-16 h-16 bg-slate-100 text-slate-400 rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl">
                <i class="fa-solid fa-box-open"></i>
            </div>
            <h3 class="font-bold text-slate-700 text-lg mb-1">Chưa Có Sản Phẩm Nào</h3>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL CHI TIẾT SẢN PHẨM -->
<div id="detailModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl relative max-h-[90vh] overflow-y-auto">
        <button onclick="closeDetailModal()" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 text-xl">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
            <img id="detail_img" src="" class="w-full h-64 object-cover rounded-2xl border border-slate-100">
            <div class="space-y-4">
                <span id="detail_category" class="bg-indigo-50 text-indigo-600 text-[10px] font-extrabold uppercase px-3 py-1 rounded-md"></span>
                <h2 id="detail_name" class="text-2xl font-black text-slate-900"></h2>
                <div id="detail_price" class="text-2xl font-black text-emerald-600"></div>
                <div class="text-xs text-slate-400 font-semibold">Người bán: <span id="detail_merchant" class="text-slate-700 font-bold"></span></div>
                
                <hr class="border-slate-100">
                
                <div>
                    <h4 class="text-xs font-bold text-slate-400 uppercase mb-1">Mô Tả Sản Phẩm</h4>
                    <p id="detail_desc" class="text-slate-600 text-sm leading-relaxed whitespace-pre-line"></p>
                </div>

                <div class="pt-4 flex gap-3">
                    <button id="chatMerchantBtn" class="flex-1 bg-slate-900 hover:bg-slate-800 text-white font-bold py-3 rounded-xl text-xs flex items-center justify-center gap-2 transition">
                        <i class="fa-solid fa-comments"></i> Nhắn tin Người Bán
                    </button>
                    <a id="addToCartBtn" href="#" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl text-xs flex items-center justify-center gap-2 transition shadow-lg shadow-indigo-200">
                        <i class="fa-solid fa-cart-plus"></i> Thêm Vào Giỏ
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openDetailModal(p) {
    document.getElementById('detail_img').src = p.image_url || 'https://images.unsplash.com/photo-1526738549149-8e07eca6c147?w=500';
    document.getElementById('detail_name').innerText = p.name;
    document.getElementById('detail_category').innerText = p.category;
    document.getElementById('detail_price').innerText = new Intl.NumberFormat('vi-VN').format(p.price) + ' đ';
    document.getElementById('detail_merchant').innerText = p.merchant_name || 'Hệ thống LaiStore';
    document.getElementById('detail_desc').innerText = p.description || 'Chưa có mô tả chi tiết cho sản phẩm này.';
    document.getElementById('addToCartBtn').href = 'index.php?add_to_cart=' + p.id;
    
    // NÚT NHẮN TIN TỰ ĐỘNG MỞ CHAT WIDGET
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

function closeDetailModal() {
    document.getElementById('detailModal').classList.add('hidden');
    document.getElementById('detailModal').classList.remove('flex');
}
</script>

<?php include 'footer.php'; ?>