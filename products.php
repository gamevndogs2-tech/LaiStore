<?php
require_once 'config.php';
checkLogin();

$roles = explode(',', $_SESSION['user_role']);
if (!in_array('merchant', $roles)) {
    header("Location: index.php");
    exit();
}

// XỬ LÝ THÊM / SỬA SẢN PHẨM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $p_id = (int)($_POST['p_id'] ?? 0);
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $price = (int)$_POST['price'];
    $description = trim($_POST['description']);
    $image_url = trim($_POST['image_url']);

    if ($p_id > 0) {
        $stmt = $conn->prepare("UPDATE products SET name=?, category=?, price=?, description=?, image_url=? WHERE id=? AND merchant_id=?");
        $stmt->bind_param("ssissii", $name, $category, $price, $description, $image_url, $p_id, $_SESSION['user_id']);
        $stmt->execute();
        header("Location: products.php?alert=updated");
    } else {
        $stmt = $conn->prepare("INSERT INTO products (merchant_id, name, category, price, description, image_url) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ississ", $_SESSION['user_id'], $name, $category, $price, $description, $image_url);
        $stmt->execute();
        header("Location: products.php?alert=created");
    }
    exit();
}

// XỬ LÝ XÓA SẢN PHẨM
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND merchant_id = ?");
    $stmt->bind_param("ii", $del_id, $_SESSION['user_id']);
    $stmt->execute();
    header("Location: products.php?alert=deleted");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM products WHERE merchant_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$my_products = $stmt->get_result();

include 'header.php';
?>

<div class="flex items-center justify-between mb-8">
    <div>
        <h2 class="text-3xl font-extrabold text-slate-900">Quản Lý Sản Phẩm</h2>
        <p class="text-slate-500 text-sm mt-1">Đăng bài, tải ảnh sản phẩm từ máy tính và quản lý danh mục.</p>
    </div>
    <button onclick="openModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-5 py-3 rounded-xl shadow-lg shadow-indigo-200 transition flex items-center gap-2 text-sm">
        <i class="fa-solid fa-plus"></i> Đăng Bài Sản Phẩm Mới
    </button>
</div>

<!-- Danh Sách Sản Phẩm -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-100 text-slate-400 uppercase text-xs font-bold">
                <th class="p-4">Hình Ảnh</th>
                <th class="p-4">Tên Sản Phẩm</th>
                <th class="p-4">Danh Mục</th>
                <th class="p-4">Giá Bán</th>
                <th class="p-4 text-center">Thao Tác</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm font-medium">
            <?php if ($my_products->num_rows > 0): ?>
                <?php while ($p = $my_products->fetch_assoc()): ?>
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="p-4">
                            <img src="<?= $p['image_url'] ?: 'https://images.unsplash.com/photo-1526738549149-8e07eca6c147?w=500' ?>" class="w-12 h-12 rounded-xl object-cover border border-slate-200">
                        </td>
                        <td class="p-4 font-bold text-slate-800"><?= htmlspecialchars($p['name']) ?></td>
                        <td class="p-4"><span class="bg-indigo-50 text-indigo-600 px-3 py-1 rounded-lg text-xs font-bold"><?= htmlspecialchars($p['category']) ?></span></td>
                        <td class="p-4 text-emerald-600 font-extrabold"><?= number_format($p['price']) ?> đ</td>
                        <td class="p-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button onclick='editProduct(<?= json_encode($p, JSON_UNESCAPED_UNICODE) ?>)' class="bg-amber-50 text-amber-600 hover:bg-amber-100 px-3 py-1.5 rounded-lg text-xs font-bold transition">
                                    <i class="fa-solid fa-pen"></i> Sửa
                                </button>
                                <a href="javascript:void(0)" onclick="confirmDelete(<?= $p['id'] ?>)" class="bg-rose-50 text-rose-600 hover:bg-rose-100 px-3 py-1.5 rounded-lg text-xs font-bold transition">
                                    <i class="fa-solid fa-trash"></i> Xóa
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="p-8 text-center text-slate-400">Bạn chưa đăng sản phẩm nào. Hãy bấm nút Đăng Bài phía trên!</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- MODAL POPUP THÊM / SỬA -->
<div id="productModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
            <h3 id="modalTitle" class="text-xl font-bold text-slate-900">Đăng Bài Sản Phẩm Mới</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 text-lg"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form method="POST" action="products.php" class="space-y-4">
            <input type="hidden" name="p_id" id="p_id" value="0">
            <input type="hidden" name="image_url" id="p_image_url" value="">

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Tên Sản Phẩm</label>
                <input type="text" name="name" id="p_name" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-sm">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Danh Mục (Tiếng Việt)</label>
                    <input type="text" name="category" id="p_category" required placeholder="Thời Trang, Điện Thoại..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Giá Bán (VNĐ)</label>
                    <input type="number" name="price" id="p_price" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-sm">
                </div>
            </div>

            <!-- CHỌN ẢNH TỪ MÁY TÍNH -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Tải Ảnh Từ Máy Tính</label>
                <input type="file" id="p_image_file" accept="image/*" onchange="previewFileImage(this)" class="w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100 cursor-pointer">
                
                <!-- Xem trước hình ảnh -->
                <div id="imagePreviewBox" class="mt-3 hidden">
                    <img id="imagePreviewImg" src="" class="w-24 h-24 object-cover rounded-xl border border-slate-200 shadow-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Mô Tả Sản Phẩm</label>
                <textarea name="description" id="p_description" rows="3" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-sm"></textarea>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeModal()" class="flex-1 bg-slate-100 text-slate-600 font-bold py-3 rounded-xl text-sm">Hủy</button>
                <button type="submit" name="save_product" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl text-sm shadow-lg shadow-indigo-200">Lưu Sản Phẩm</button>
            </div>
        </form>
    </div>
</div>

<script>
// Đọc File Ảnh Chuyển Sang Base64
function previewFileImage(input) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function (e) {
            const base64String = e.target.result;
            document.getElementById('p_image_url').value = base64String;
            document.getElementById('imagePreviewImg').src = base64String;
            document.getElementById('imagePreviewBox').classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    }
}

function openModal() {
    document.getElementById('p_id').value = 0;
    document.getElementById('p_name').value = '';
    document.getElementById('p_category').value = '';
    document.getElementById('p_price').value = '';
    document.getElementById('p_image_url').value = '';
    document.getElementById('p_image_file').value = '';
    document.getElementById('p_description').value = '';
    document.getElementById('imagePreviewBox').classList.add('hidden');
    document.getElementById('modalTitle').innerText = 'Đăng Bài Sản Phẩm Mới';
    document.getElementById('productModal').classList.remove('hidden');
    document.getElementById('productModal').classList.add('flex');
}

function editProduct(product) {
    document.getElementById('p_id').value = product.id;
    document.getElementById('p_name').value = product.name;
    document.getElementById('p_category').value = product.category;
    document.getElementById('p_price').value = product.price;
    document.getElementById('p_image_url').value = product.image_url || '';
    document.getElementById('p_description').value = product.description;

    if (product.image_url) {
        document.getElementById('imagePreviewImg').src = product.image_url;
        document.getElementById('imagePreviewBox').classList.remove('hidden');
    } else {
        document.getElementById('imagePreviewBox').classList.add('hidden');
    }

    document.getElementById('modalTitle').innerText = 'Chỉnh Sửa Sản Phẩm';
    document.getElementById('productModal').classList.remove('hidden');
    document.getElementById('productModal').classList.add('flex');
}

function closeModal() {
    document.getElementById('productModal').classList.add('hidden');
    document.getElementById('productModal').classList.remove('flex');
}

function confirmDelete(id) {
    Swal.fire({
        title: 'Xóa sản phẩm này?',
        text: "Bạn không thể hoàn tác lại thao tác này!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Đồng ý xóa',
        cancelButtonText: 'Hủy'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'products.php?delete=' + id;
        }
    })
}

const urlParams = new URLSearchParams(window.location.search);
const alertType = urlParams.get('alert');
if (alertType) {
    const Toast = Swal.mixin({
        toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true
    });
    if (alertType === 'created') Toast.fire({ icon: 'success', title: 'Đăng bài sản phẩm thành công!' });
    if (alertType === 'updated') Toast.fire({ icon: 'success', title: 'Cập nhật sản phẩm thành công!' });
    if (alertType === 'deleted') Toast.fire({ icon: 'info', title: 'Đã xóa sản phẩm!' });
}
</script>

<?php include 'footer.php'; ?>