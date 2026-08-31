<?php
require_once 'config.php';
checkLogin();

$roles = explode(',', $_SESSION['user_role']);
if (!in_array('merchant', $roles)) {
    header("Location: index.php");
    exit();
}

$merchant_id = $_SESSION['user_id'];

// 1. XỬ LÝ THÊM / SỬA SẢN PHẨM & TỰ ĐỘNG NẠP KEY NẾU CÓ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $p_id         = (int)($_POST['p_id'] ?? 0);
    $name         = trim($_POST['name']);
    $category     = trim($_POST['category']);
    $price        = (int)$_POST['price'];
    $product_type = $_POST['product_type'] ?? 'PHYSICAL';
    $stock        = (int)($_POST['stock'] ?? 0);
    $description  = trim($_POST['description']);
    $image_url    = trim($_POST['image_url']);
    $initial_keys = trim($_POST['initial_keys'] ?? ''); 

    if ($p_id > 0) {
        $stmt = $conn->prepare("UPDATE products SET name=?, category=?, price=?, product_type=?, stock=?, description=?, image_url=? WHERE id=? AND merchant_id=?");
        $stmt->bind_param("ssissdiii", $name, $category, $price, $product_type, $stock, $description, $image_url, $p_id, $merchant_id);
        $stmt->execute();
        $target_product_id = $p_id;
        $alert_type = 'updated';
    } else {
        $stmt = $conn->prepare("INSERT INTO products (merchant_id, name, category, price, product_type, stock, description, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issisiss", $merchant_id, $name, $category, $price, $product_type, $stock, $description, $image_url);
        $stmt->execute();
        $target_product_id = $conn->insert_id;
        $alert_type = 'created';
    }

    if ($product_type === 'LICENSE_KEY' && !empty($initial_keys)) {
        $key_lines = explode("\n", $initial_keys);
        $stmt_insert_key = $conn->prepare("INSERT INTO product_keys (product_id, license_key, is_sold) VALUES (?, ?, 0)");
        foreach ($key_lines as $k) {
            $clean_key = trim($k);
            if (!empty($clean_key)) {
                $stmt_insert_key->bind_param("is", $target_product_id, $clean_key);
                $stmt_insert_key->execute();
            }
        }
    }

    header("Location: products.php?alert=" . $alert_type);
    exit();
}

// 2. XỬ LÝ NẠP THÊM KEY VÀO KHO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_license_keys'])) {
    $product_id = (int)$_POST['product_id'];
    $raw_keys = trim($_POST['license_keys']);

    $check_p = $conn->prepare("SELECT id FROM products WHERE id = ? AND merchant_id = ?");
    $check_p->bind_param("ii", $product_id, $merchant_id);
    $check_p->execute();
    
    if ($check_p->get_result()->num_rows > 0 && !empty($raw_keys)) {
        $key_lines = explode("\n", $raw_keys);
        $stmt_insert_key = $conn->prepare("INSERT INTO product_keys (product_id, license_key, is_sold) VALUES (?, ?, 0)");
        
        foreach ($key_lines as $k) {
            $clean_key = trim($k);
            if (!empty($clean_key)) {
                $stmt_insert_key->bind_param("is", $product_id, $clean_key);
                $stmt_insert_key->execute();
            }
        }
        header("Location: products.php?alert=keys_added");
        exit();
    }
}

// 3. XỬ LÝ XÓA KEY TRONG KHO
if (isset($_GET['delete_key'])) {
    $key_id = (int)$_GET['delete_key'];
    $conn->query("DELETE pk FROM product_keys pk JOIN products p ON pk.product_id = p.id WHERE pk.id = $key_id AND p.merchant_id = $merchant_id AND pk.is_sold = 0");
    header("Location: products.php?alert=key_deleted");
    exit();
}

// XỬ LÝ XÓA SẢN PHẨM
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND merchant_id = ?");
    $stmt->bind_param("ii", $del_id, $merchant_id);
    $stmt->execute();
    header("Location: products.php?alert=deleted");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM products WHERE merchant_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $merchant_id);
$stmt->execute();
$my_products = $stmt->get_result();

include 'header.php';
?>

<div class="mb-6 sm:mb-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
    <div>
        <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900">Quản Lý Sản Phẩm & Tồn Kho</h2>
        <p class="text-slate-500 text-xs sm:text-sm mt-1">Đăng bán sản phẩm vật lý (có quản lý số lượng) hoặc tự động cấp key bản quyền.</p>
    </div>
    <button onclick="openModal()" class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-5 py-3 rounded-2xl shadow-lg shadow-indigo-200 transition flex items-center justify-center gap-2 text-xs sm:text-sm">
        <i class="fa-solid fa-plus"></i> Đăng Bài Sản Phẩm Mới
    </button>
</div>

<!-- Danh Sách Sản Phẩm -->
<div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[750px]">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100 text-slate-400 uppercase text-[10px] sm:text-xs font-bold">
                    <th class="p-3.5 sm:p-4">Hình Ảnh</th>
                    <th class="p-3.5 sm:p-4">Tên Sản Phẩm</th>
                    <th class="p-3.5 sm:p-4">Phân Loại</th>
                    <th class="p-3.5 sm:p-4">Giá Bán</th>
                    <th class="p-3.5 sm:p-4">Số Lượng Tồn Kho</th>
                    <th class="p-3.5 sm:p-4 text-center">Thao Tác</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs sm:text-sm font-medium">
                <?php if ($my_products->num_rows > 0): ?>
                    <?php while ($p = $my_products->fetch_assoc()): 
                        $p_id = $p['id'];
                        $is_license = (($p['product_type'] ?? 'PHYSICAL') === 'LICENSE_KEY');

                        $available_stock = 0;
                        if ($is_license) {
                            $count_q = $conn->query("SELECT SUM(CASE WHEN is_sold = 0 THEN 1 ELSE 0 END) as available FROM product_keys WHERE product_id = $p_id");
                            $available_stock = $count_q->fetch_assoc()['available'] ?? 0;
                        } else {
                            $available_stock = $p['stock'] ?? 0;
                        }

                        // Xử lý hiển thị ảnh an toàn (Base64 hoặc URL)
                        $img_src = (!empty($p['image_url']) && strlen($p['image_url']) > 10) ? $p['image_url'] : 'https://images.unsplash.com/photo-1526738549149-8e07eca6c147?w=500';
                    ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="p-3.5 sm:p-4">
                                <img src="<?= $img_src ?>" class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl object-cover border border-slate-200">
                            </td>
                            <td class="p-3.5 sm:p-4 font-bold text-slate-800"><?= htmlspecialchars($p['name']) ?></td>
                            
                            <td class="p-3.5 sm:p-4">
                                <?php if ($is_license): ?>
                                    <span class="bg-indigo-50 text-indigo-700 border border-indigo-200 px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase inline-block"><i class="fa-solid fa-key mr-1"></i> Key Bản Quyền</span>
                                <?php else: ?>
                                    <span class="bg-slate-100 text-slate-700 border border-slate-200 px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase inline-block"><i class="fa-solid fa-box mr-1"></i> Vật Lý</span>
                                <?php endif; ?>
                            </td>

                            <td class="p-3.5 sm:p-4 text-emerald-600 font-extrabold whitespace-nowrap"><?= number_format($p['price']) ?> đ</td>
                            
                            <td class="p-3.5 sm:p-4">
                                <?php if ($is_license): ?>
                                    <div class="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1 rounded-xl text-xs font-black">
                                        <i class="fa-solid fa-warehouse"></i> Còn: <?= $available_stock ?> key
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs font-bold text-slate-700 bg-slate-100 px-3 py-1 rounded-xl border border-slate-200">
                                        Kho: <?= $available_stock ?> sản phẩm
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="p-3.5 sm:p-4 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <?php if ($is_license): ?>
                                        <button onclick="openKeyModal(<?= $p['id'] ?>, '<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>')" class="bg-indigo-50 text-indigo-600 hover:bg-indigo-100 px-2.5 py-1.5 rounded-xl text-[11px] font-bold transition flex items-center gap-1">
                                            <i class="fa-solid fa-key"></i> Nạp Key
                                        </button>
                                    <?php endif; ?>
                                    <!-- Truyền JSON an toàn qua htmlspecialchars để tránh lỗi cú pháp ký tự đặc biệt -->
                                    <button onclick='editProduct(<?= htmlspecialchars(json_encode($p, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>)' class="bg-amber-50 text-amber-600 hover:bg-amber-100 px-2.5 py-1.5 rounded-xl text-[11px] font-bold transition">
                                        <i class="fa-solid fa-pen"></i> Sửa
                                    </button>
                                    <a href="javascript:void(0)" onclick="confirmDelete(<?= $p['id'] ?>)" class="bg-rose-50 text-rose-600 hover:bg-rose-100 px-2.5 py-1.5 rounded-xl text-[11px] font-bold transition">
                                        <i class="fa-solid fa-trash"></i> Xóa
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="p-8 text-center text-slate-400 text-xs sm:text-sm">Bạn chưa đăng sản phẩm nào.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL NẠP KHO KEY -->
<div id="keyModal" class="fixed inset-0 bg-slate-900/70 backdrop-blur-md z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4 border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-base sm:text-lg font-bold text-slate-900">Quản Lý Kho Key Bản Quyền</h3>
                <p id="keyProductName" class="text-xs text-indigo-600 font-bold mt-0.5"></p>
            </div>
            <button onclick="closeKeyModal()" class="text-slate-400 hover:text-slate-600 text-lg w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form method="POST" action="products.php" class="space-y-4">
            <input type="hidden" name="product_id" id="key_product_id">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Thêm Key Mới (Mỗi dòng 1 key)</label>
                <textarea name="license_keys" rows="4" required placeholder="XXXXX-XXXXX-XXXXX&#10;YYYYY-YYYYY-YYYYY" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-xs font-mono bg-slate-50/50"></textarea>
            </div>
            <button type="submit" name="add_license_keys" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-xl text-xs sm:text-sm shadow-md transition">
                <i class="fa-solid fa-circle-plus mr-1"></i> Nạp Thêm Key Vào Kho
            </button>
        </form>

        <div class="mt-6 pt-4 border-t border-slate-100">
            <h4 class="text-xs font-bold text-slate-700 uppercase mb-2">Kho Key Chưa Bán:</h4>
            <div id="currentKeyList" class="bg-slate-50 p-3 rounded-2xl border border-slate-100 max-h-40 overflow-y-auto space-y-1.5 text-xs">
                <span class="text-slate-400 italic">Đang tải...</span>
            </div>
        </div>
    </div>
</div>

<!-- MODAL THÊM / SỬA SẢN PHẨM -->
<div id="productModal" class="fixed inset-0 bg-slate-900/70 backdrop-blur-md z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-5 border-b border-slate-100 pb-3">
            <h3 id="modalTitle" class="text-lg font-bold text-slate-900">Đăng Bài Sản Phẩm Mới</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 text-lg w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form method="POST" action="products.php" class="space-y-4">
            <input type="hidden" name="p_id" id="p_id" value="0">
            <input type="hidden" name="image_url" id="p_image_url" value="">

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Tên Sản Phẩm</label>
                <input type="text" name="name" id="p_name" required class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-xs sm:text-sm bg-slate-50/50">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Phân Loại Sản Phẩm</label>
                <select name="product_type" id="p_product_type" onchange="toggleProductTypeFields()" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-xs sm:text-sm bg-slate-50/50 font-bold text-slate-700">
                    <option value="PHYSICAL">📦 Sản Phẩm Vật Lý (Giao hàng qua Shipper)</option>
                    <option value="LICENSE_KEY">🔑 Key Bản Quyền / Kỹ Thuật Số (Tự động cấp ngay)</option>
                </select>
            </div>

            <div id="stockBox">
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Số Lượng Tồn Kho (Sản phẩm vật lý)</label>
                <input type="number" name="stock" id="p_stock" min="0" value="10" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-xs sm:text-sm bg-slate-50/50 font-bold">
            </div>

            <div id="initialKeysBox" class="hidden">
                <label class="block text-xs font-bold text-indigo-600 uppercase mb-1"><i class="fa-solid fa-key mr-1"></i> Nhập Kho Key Ban Đầu (Mỗi dòng 1 key)</label>
                <textarea name="initial_keys" id="p_initial_keys" rows="3" placeholder="XXXXX-XXXXX-XXXXX&#10;YYYYY-YYYYY-YYYYY" class="w-full px-4 py-3 rounded-xl border border-indigo-200 focus:outline-none focus:border-indigo-600 text-xs font-mono bg-indigo-50/30"></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Danh Mục</label>
                    <input type="text" name="category" id="p_category" required placeholder="Phần mềm, Game..." class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-xs sm:text-sm bg-slate-50/50">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Giá Bán (VNĐ)</label>
                    <input type="number" name="price" id="p_price" required class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-xs sm:text-sm bg-slate-50/50">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Tải Ảnh Từ Thiết Bị</label>
                <input type="file" id="p_image_file" accept="image/*" onchange="previewFileImage(this)" class="w-full text-xs text-slate-500 file:mr-4 file:py-3 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100 cursor-pointer bg-slate-50/50 rounded-xl border border-slate-200">
                
                <div id="imagePreviewBox" class="mt-3 hidden">
                    <img id="imagePreviewImg" src="" class="w-20 h-20 object-cover rounded-xl border border-slate-200 shadow-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Mô Tả Sản Phẩm</label>
                <textarea name="description" id="p_description" rows="3" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:border-indigo-600 text-xs sm:text-sm bg-slate-50/50"></textarea>
            </div>

            <div class="flex gap-3 pt-3">
                <button type="button" onclick="closeModal()" class="flex-1 bg-slate-100 text-slate-600 font-bold py-3 rounded-xl text-xs sm:text-sm">Hủy</button>
                <button type="submit" name="save_product" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl text-xs sm:text-sm shadow-lg shadow-indigo-200">Lưu Sản Phẩm</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleProductTypeFields() {
    const typeSelect = document.getElementById('p_product_type').value;
    const stockBox = document.getElementById('stockBox');
    const keyBox = document.getElementById('initialKeysBox');

    if (typeSelect === 'LICENSE_KEY') {
        stockBox.classList.add('hidden');
        keyBox.classList.remove('hidden');
    } else {
        stockBox.classList.remove('hidden');
        keyBox.classList.add('hidden');
        document.getElementById('p_initial_keys').value = '';
    }
}

function previewFileImage(input) {
    const file = input.files[0];
    if (file) {
        if (file.size > 2 * 1024 * 1024) {
            Swal.fire('Ảnh quá lớn!', 'Vui lòng chọn ảnh dung lượng dưới 2MB.', 'warning');
            input.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = function (e) {
            document.getElementById('p_image_url').value = e.target.result;
            document.getElementById('imagePreviewImg').src = e.target.result;
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
    document.getElementById('p_stock').value = 10;
    document.getElementById('p_product_type').value = 'PHYSICAL';
    document.getElementById('p_initial_keys').value = '';
    document.getElementById('p_image_url').value = '';
    document.getElementById('p_image_file').value = '';
    document.getElementById('p_description').value = '';
    document.getElementById('imagePreviewBox').classList.add('hidden');
    toggleProductTypeFields();
    document.getElementById('modalTitle').innerText = 'Đăng Bài Sản Phẩm Mới';
    document.getElementById('productModal').classList.remove('hidden');
    document.getElementById('productModal').classList.add('flex');
}

function editProduct(product) {
    document.getElementById('p_id').value = product.id;
    document.getElementById('p_name').value = product.name;
    document.getElementById('p_category').value = product.category;
    document.getElementById('p_price').value = product.price;
    document.getElementById('p_stock').value = product.stock || 0;
    document.getElementById('p_product_type').value = product.product_type || 'PHYSICAL';
    document.getElementById('p_initial_keys').value = ''; 
    document.getElementById('p_image_url').value = product.image_url || '';
    document.getElementById('p_description').value = product.description;

    toggleProductTypeFields();

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

function openKeyModal(productId, productName) {
    document.getElementById('key_product_id').value = productId;
    document.getElementById('keyProductName').innerText = 'Sản phẩm: ' + productName;
    document.getElementById('keyModal').classList.remove('hidden');
    document.getElementById('keyModal').classList.add('flex');

    const keyListDiv = document.getElementById('currentKeyList');
    keyListDiv.innerHTML = '<span class="text-slate-400 italic">Đang tải...</span>';

    fetch('get_keys.php?product_id=' + productId)
        .then(res => res.text())
        .then(html => { keyListDiv.innerHTML = html; })
        .catch(err => { keyListDiv.innerHTML = '<span class="text-rose-500">Lỗi tải dữ liệu.</span>'; });
}

function closeKeyModal() {
    document.getElementById('keyModal').classList.add('hidden');
    document.getElementById('keyModal').classList.remove('flex');
}

function confirmDelete(id) {
    Swal.fire({
        title: 'Xóa sản phẩm này?',
        text: "Hành động này không thể hoàn tác!",
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
    const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true });
    if (alertType === 'created') Toast.fire({ icon: 'success', title: 'Đăng bài thành công!' });
    if (alertType === 'updated') Toast.fire({ icon: 'success', title: 'Cập nhật thành công!' });
    if (alertType === 'deleted') Toast.fire({ icon: 'info', title: 'Đã xóa sản phẩm!' });
    if (alertType === 'keys_added') Toast.fire({ icon: 'success', title: 'Đã nạp thêm key!' });
    if (alertType === 'key_deleted') Toast.fire({ icon: 'info', title: 'Đã xóa key!' });
}
</script>

<?php include 'footer.php'; ?>