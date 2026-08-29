const API_URL = '/api/products';
let currentCategory = 'All';
let cart = [];

document.addEventListener('DOMContentLoaded', () => {
  checkLoginState();
  fetchProducts();

  window.addEventListener('click', (e) => {
    const dropdown = document.getElementById('user-dropdown-menu');
    const userBtn = document.querySelector('.user-icon-btn');
    if (dropdown && !userBtn?.contains(e.target) && !dropdown.contains(e.target)) {
      dropdown.classList.remove('show');
    }
  });
});

// XỬ LÝ ĐỌC FILE ẢNH TỪ MÁY TÍNH & PREVIEW
function previewImageFile(e) {
  const file = e.target.files[0];
  const previewBox = document.getElementById('image-preview-box');
  const previewImg = document.getElementById('image-preview-img');
  const base64Input = document.getElementById('p-image-base64');

  if (file) {
    const reader = new FileReader();
    reader.onload = function (evt) {
      const base64String = evt.target.result;
      base64Input.value = base64String;
      previewImg.src = base64String;
      previewBox.style.display = 'block';
    };
    reader.readAsDataURL(file);
  }
}

// TOAST THÔNG BÁO SMOOTH
function showToast(message, type = 'success') {
  let background = '#10b981';
  if (type === 'error') background = '#ef4444';
  if (type === 'info') background = '#2563eb';

  Toastify({
    text: message,
    duration: 3000,
    close: true,
    gravity: "top",
    position: "right",
    stopOnFocus: true,
    style: {
      background: background,
      borderRadius: "8px",
      fontSize: "0.95rem",
      boxShadow: "0 10px 15px -3px rgba(0, 0, 0, 0.1)"
    }
  }).showToast();
}

function toggleUserDropdown(e) {
  e.stopPropagation();
  const dropdown = document.getElementById('user-dropdown-menu');
  if (dropdown) dropdown.classList.toggle('show');
}

// 1. CHUYỂN ĐỔI TRANG
function switchPage(pageId, btn) {
  document.querySelectorAll('.page-section').forEach(p => p.classList.remove('active-page'));
  document.querySelectorAll('.nav-link').forEach(b => b.classList.remove('active'));

  const targetPage = document.getElementById(`page-${pageId}`);
  if (targetPage) targetPage.classList.add('active-page');
  if (btn) btn.classList.add('active');

  const dropdown = document.getElementById('user-dropdown-menu');
  if (dropdown) dropdown.classList.remove('show');

  if (pageId === 'cart') updateCartUI();
  if (pageId === 'my-orders') fetchCustomerOrders();
  if (pageId === 'shipper') fetchShipperOrders();
  if (pageId === 'merchant') fetchMerchantProducts();
  if (pageId === 'account') fetchUserProfile();
}

// 2. CHECK ĐĂNG NHẬP
function checkLoginState() {
  const token = localStorage.getItem('token');
  const rolesRaw = localStorage.getItem('roles');
  const username = localStorage.getItem('username');

  const guestZone = document.getElementById('guest-zone');
  const userZone = document.getElementById('user-zone');
  
  const menuMerchant = document.getElementById('menu-merchant');
  const menuShipper = document.getElementById('menu-shipper');
  
  const dropMenuCart = document.getElementById('drop-menu-cart');
  const dropMenuOrders = document.getElementById('drop-menu-orders');

  let roles = [];
  try { roles = JSON.parse(rolesRaw) || []; } catch (e) { roles = rolesRaw ? rolesRaw.split(',') : []; }

  if (token) {
    if (guestZone) guestZone.style.display = 'none';
    if (userZone) userZone.style.display = 'block';
    
    const welcomeMsg = document.getElementById('welcome-msg');
    if (welcomeMsg) welcomeMsg.innerText = username || 'Tài khoản';

    renderRoleBadges(roles);

    if (menuMerchant) menuMerchant.style.display = roles.includes('merchant') ? 'inline-block' : 'none';
    if (menuShipper) menuShipper.style.display = roles.includes('shipper') ? 'inline-block' : 'none';

    if (dropMenuCart) dropMenuCart.style.display = roles.includes('user') ? 'flex' : 'none';
    if (dropMenuOrders) dropMenuOrders.style.display = roles.includes('user') ? 'flex' : 'none';
  } else {
    if (guestZone) guestZone.style.display = 'block';
    if (userZone) userZone.style.display = 'none';
    
    if (menuMerchant) menuMerchant.style.display = 'none';
    if (menuShipper) menuShipper.style.display = 'none';
  }
}

function renderRoleBadges(roles) {
  const container = document.getElementById('user-roles-badges');
  if (!container) return;
  container.innerHTML = '';

  const roleMap = { 'user': 'Khách Hàng', 'merchant': 'Doanh Nghiệp', 'shipper': 'Shipper' };
  roles.forEach(r => {
    if (roleMap[r]) container.innerHTML += `<span class="role-badge role-${r}">${roleMap[r]}</span>`;
  });
}

function getRoleName(roles) {
  if (!roles) return 'Khách Hàng';
  if (typeof roles === 'string') roles = roles.split(',');
  const map = { 'user': 'Khách Hàng', 'merchant': 'Doanh Nghiệp', 'shipper': 'Shipper' };
  return roles.map(r => map[r] || r).join(' + ');
}

// 3. QUẢN LÝ HỒ SƠ TÀI KHOẢN
async function fetchUserProfile() {
  const token = localStorage.getItem('token');
  if (!token) return;

  try {
    const res = await fetch('/api/account/profile', { headers: { 'Authorization': `Bearer ${token}` } });
    if (!res.ok) return;
    const user = await res.json();

    const accUsername = document.getElementById('acc-username');
    const accRole = document.getElementById('acc-role');
    const accEmail = document.getElementById('acc-email');
    const accPhone = document.getElementById('acc-phone');
    const checkoutPhone = document.getElementById('checkout-phone');

    if (accUsername) accUsername.value = user.username || '';
    if (accRole) accRole.value = getRoleName(user.role);
    if (accEmail) accEmail.value = user.email || '';
    if (accPhone) accPhone.value = user.phone || '';
    if (user.phone && checkoutPhone) checkoutPhone.value = user.phone;
  } catch (err) {
    console.error('Lỗi lấy hồ sơ cá nhân:', err);
  }
}

async function handleUpdateProfile(e) {
  e.preventDefault();
  const token = localStorage.getItem('token');
  if (!token) return showToast('Phiên đăng nhập đã hết hạn!', 'error');

  const email = document.getElementById('acc-email').value.trim();
  const phone = document.getElementById('acc-phone').value.trim();

  try {
    const res = await fetch('/api/account/profile', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
      body: JSON.stringify({ email, phone })
    });

    const data = await res.json();
    if (res.ok) {
      showToast('Đã lưu thông tin cá nhân thành công!', 'success');
      fetchUserProfile();
    } else {
      showToast(data.error || 'Cập nhật thất bại!', 'error');
    }
  } catch (err) {
    showToast('Không thể kết nối đến máy chủ!', 'error');
  }
}

async function handleChangePassword(e) {
  e.preventDefault();
  const token = localStorage.getItem('token');
  if (!token) return showToast('Bạn chưa đăng nhập!', 'error');

  const currentPassword = document.getElementById('acc-current-password').value.trim();
  const newPassword = document.getElementById('acc-new-password').value.trim();
  const confirmPassword = document.getElementById('acc-confirm-password').value.trim();

  if (newPassword !== confirmPassword) {
    return showToast('Mật khẩu mới và Mật khẩu xác nhận không trùng khớp!', 'error');
  }

  try {
    const res = await fetch('/api/change-password', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
      body: JSON.stringify({ currentPassword, newPassword })
    });

    const data = await res.json();
    if (!res.ok) return showToast(data.error || 'Đổi mật khẩu thất bại!', 'error');

    showToast('Đổi mật khẩu thành công!', 'success');
    document.getElementById('acc-current-password').value = '';
    document.getElementById('acc-new-password').value = '';
    document.getElementById('acc-confirm-password').value = '';
  } catch (err) {
    showToast('Không thể kết nối đến máy chủ!', 'error');
  }
}

// 4. HIỂN THỊ SẢN PHẨM
async function fetchProducts() {
  const searchInput = document.getElementById('search-input');
  const search = searchInput ? searchInput.value : '';
  try {
    const res = await fetch(`${API_URL}?search=${encodeURIComponent(search)}&category=${encodeURIComponent(currentCategory)}`);
    const products = await res.json();
    renderProducts(products, 'product-grid');
  } catch (err) {
    console.error('Lỗi tải sản phẩm:', err);
  }
}

async function fetchMerchantProducts() {
  try {
    const res = await fetch(API_URL);
    const products = await res.json();
    renderProducts(products, 'merchant-product-grid');
  } catch (err) {
    console.error('Lỗi tải sản phẩm merchant:', err);
  }
}

function renderProducts(products, containerId) {
  const grid = document.getElementById(containerId);
  if (!grid) return;
  
  grid.innerHTML = '';
  const rolesRaw = localStorage.getItem('roles');
  let roles = [];
  try { roles = JSON.parse(rolesRaw) || []; } catch(e) { roles = rolesRaw ? rolesRaw.split(',') : []; }

  if (products.length === 0) {
    grid.innerHTML = `<p style="grid-column: 1/-1; text-align: center; color: var(--text-muted);">Không có sản phẩm nào.</p>`;
    return;
  }

  products.forEach(p => {
    const defaultImg = 'https://images.unsplash.com/photo-1526738549149-8e07eca6c147?w=500';
    let actionBtn = '';

    if (containerId === 'product-grid' && (roles.length === 0 || roles.includes('user'))) {
      actionBtn = `<button class="btn-primary" style="width:100%; margin-top:10px;" onclick="addToCart(${p.id}, '${escapeHtml(p.name)}', ${p.price})"><i class="fa-solid fa-cart-plus"></i> Thêm Vào Giỏ</button>`;
    } else if (containerId === 'merchant-product-grid' && roles.includes('merchant')) {
      actionBtn = `
        <div style="display: flex; gap: 8px; margin-top: 10px;">
          <button class="btn-primary" style="flex: 1; background: #f59e0b;" onclick="openEditProductModal(${p.id}, '${escapeHtml(p.name)}', '${p.category}', ${p.price}, '${escapeHtml(p.image_url || '')}', '${escapeHtml(p.description || '')}')">
            <i class="fa-solid fa-pen-to-square"></i> Sửa
          </button>
          <button class="btn-delete" style="flex: 1;" onclick="deleteProduct(${p.id})">
            <i class="fa-solid fa-trash"></i> Xóa
          </button>
        </div>
      `;
    }

    grid.innerHTML += `
      <div class="product-card">
        <img src="${p.image_url || defaultImg}" class="card-img" alt="${p.name}" onerror="this.src='${defaultImg}'">
        <div class="card-body">
          <span class="badge">${p.category}</span>
          <h3 class="card-title">${p.name}</h3>
          <div class="card-price">${Number(p.price).toLocaleString('vi-VN')} đ</div>
          <p class="card-desc">${p.description || 'Chưa có mô tả.'}</p>
          ${actionBtn}
        </div>
      </div>
    `;
  });
}

// 5. GIỎ HÀNG
function addToCart(id, name, price) {
  const existing = cart.find(item => item.id === id);
  if (existing) existing.quantity += 1;
  else cart.push({ id, name, price, quantity: 1 });

  const totalQty = cart.reduce((s, i) => s + i.quantity, 0);
  const cartCount = document.getElementById('cart-count');
  const cartBadgeCount = document.getElementById('cart-badge-count');
  
  if (cartCount) cartCount.innerText = totalQty;
  if (cartBadgeCount) {
    cartBadgeCount.innerText = totalQty;
    cartBadgeCount.style.display = totalQty > 0 ? 'inline-block' : 'none';
  }
  
  showToast(`Đã thêm "${name}" vào giỏ hàng!`, 'info');
}

function updateCartUI() {
  const list = document.getElementById('cart-items-list');
  if (!list) return;
  
  list.innerHTML = '';
  let total = 0;

  if (cart.length === 0) {
    list.innerHTML = '<p style="text-align:center; color: var(--text-muted);">Giỏ hàng đang trống!</p>';
  } else {
    cart.forEach(item => {
      total += item.price * item.quantity;
      list.innerHTML += `
        <div class="cart-item-row">
          <div><b>${item.name}</b> (${Number(item.price).toLocaleString('vi-VN')} đ)</div>
          <div>Số lượng: <b>${item.quantity}</b></div>
        </div>
      `;
    });
  }
  const cartTotal = document.getElementById('cart-total-price');
  if (cartTotal) cartTotal.innerText = total.toLocaleString('vi-VN');
}

async function handleCheckout(e) {
  e.preventDefault();
  const token = localStorage.getItem('token');
  if (!token) return showToast('Bạn cần đăng nhập để đặt hàng!', 'error');
  if (cart.length === 0) return showToast('Giỏ hàng trống!', 'error');

  const orderData = {
    customer_name: document.getElementById('checkout-name').value,
    phone: document.getElementById('checkout-phone').value,
    address: document.getElementById('checkout-address').value,
    payment_method: document.getElementById('checkout-payment').value,
    items: cart
  };

  const res = await fetch('/api/orders', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
    body: JSON.stringify(orderData)
  });

  if (res.ok) {
    showToast('Đặt hàng thành công!', 'success');
    cart = [];
    const cartCount = document.getElementById('cart-count');
    const cartBadgeCount = document.getElementById('cart-badge-count');
    if (cartCount) cartCount.innerText = 0;
    if (cartBadgeCount) cartBadgeCount.style.display = 'none';

    switchPage('my-orders');
  } else {
    const err = await res.json();
    showToast(err.error || 'Đặt hàng thất bại', 'error');
  }
}

// 6. ĐƠN HÀNG KHÁCH HÀNG
async function fetchCustomerOrders() {
  const token = localStorage.getItem('token');
  if (!token) return;

  const res = await fetch('/api/orders/my-orders', { headers: { 'Authorization': `Bearer ${token}` } });
  const orders = await res.json();
  const tbody = document.getElementById('customer-orders-list');
  if (!tbody) return;
  tbody.innerHTML = '';

  if (orders.length === 0) {
    tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;">Chưa có đơn hàng nào.</td></tr>`;
    return;
  }

  orders.forEach(o => {
    tbody.innerHTML += `
      <tr>
        <td>#${o.id}</td>
        <td>${new Date(o.created_at).toLocaleString('vi-VN')}</td>
        <td><b>${Number(o.total_amount).toLocaleString('vi-VN')} đ</b></td>
        <td>${o.payment_method}</td>
        <td><span class="status-badge status-${o.status}">${getStatusLabel(o.status)}</span></td>
      </tr>
    `;
  });
}

// 7. SHIPPER
async function fetchShipperOrders() {
  const token = localStorage.getItem('token');
  if (!token) return;

  const res = await fetch('/api/orders/shipper', { headers: { 'Authorization': `Bearer ${token}` } });
  const orders = await res.json();
  const tbody = document.getElementById('shipper-order-list');
  if (!tbody) return;
  tbody.innerHTML = '';

  if (orders.length === 0) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;">Không có đơn hàng nào cần giao.</td></tr>`;
    return;
  }

  orders.forEach(o => {
    let actionBtn = '';
    if (o.status === 'PENDING') {
      actionBtn = `<button class="btn-primary" onclick="updateOrderStatus(${o.id}, 'SHIPPING')">Nhận Giao Đơn</button>`;
    } else if (o.status === 'SHIPPING') {
      actionBtn = `<button class="btn-primary" style="background:#10b981;" onclick="updateOrderStatus(${o.id}, 'DELIVERED')">Xác Nhận Đã Giao</button>`;
    } else {
      actionBtn = `<i>Hoàn tất</i>`;
    }

    tbody.innerHTML += `
      <tr>
        <td>#${o.id}</td>
        <td><b>${o.customer_name}</b></td>
        <td>${o.phone}<br><small>${o.address}</small></td>
        <td><b>${Number(o.total_amount).toLocaleString('vi-VN')} đ</b></td>
        <td>${o.payment_method}</td>
        <td><span class="status-badge status-${o.status}">${getStatusLabel(o.status)}</span></td>
        <td>${actionBtn}</td>
      </tr>
    `;
  });
}

async function updateOrderStatus(orderId, status) {
  const token = localStorage.getItem('token');
  const res = await fetch(`/api/orders/${orderId}/status`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
    body: JSON.stringify({ status })
  });

  if (res.ok) {
    showToast('Cập nhật trạng thái đơn hàng thành công!', 'success');
    fetchShipperOrders();
  }
}

function getStatusLabel(status) {
  if (status === 'PENDING') return 'Chờ Shipper';
  if (status === 'SHIPPING') return 'Đang Giao';
  if (status === 'DELIVERED') return 'Đã Giao';
  if (status === 'CANCELLED') return 'Đã Hủy';
  return status;
}

// 8. ĐĂNG BÀI / CHỈNH SỬA SẢN PHẨM MỚI
function openProductModal() { 
  document.getElementById('p-id').value = '';
  document.getElementById('p-name').value = '';
  document.getElementById('p-price').value = '';
  document.getElementById('p-image-file').value = '';
  document.getElementById('p-image-base64').value = '';
  document.getElementById('image-preview-box').style.display = 'none';
  document.getElementById('p-desc').value = '';

  document.getElementById('product-modal-title').innerText = 'Đăng Bài Sản Phẩm Mới';
  document.getElementById('p-submit-btn').innerText = 'Đăng Bài Ngay';
  document.getElementById('product-modal').style.display = 'flex'; 
}

function openEditProductModal(id, name, category, price, imageUrl, description) {
  document.getElementById('p-id').value = id;
  document.getElementById('p-name').value = name;
  document.getElementById('p-category').value = category;
  document.getElementById('p-price').value = price;
  document.getElementById('p-image-file').value = '';
  document.getElementById('p-image-base64').value = imageUrl;

  const previewBox = document.getElementById('image-preview-box');
  const previewImg = document.getElementById('image-preview-img');

  if (imageUrl) {
    previewImg.src = imageUrl;
    previewBox.style.display = 'block';
  } else {
    previewBox.style.display = 'none';
  }

  document.getElementById('p-desc').value = description;
  document.getElementById('product-modal-title').innerText = 'Chỉnh Sửa Sản Phẩm';
  document.getElementById('p-submit-btn').innerText = 'Cập Nhật Sản Phẩm';
  document.getElementById('product-modal').style.display = 'flex';
}

async function handleProductSubmit(e) {
  e.preventDefault();
  const token = localStorage.getItem('token');
  const id = document.getElementById('p-id').value;

  const payload = {
    name: document.getElementById('p-name').value,
    category: document.getElementById('p-category').value,
    price: Number(document.getElementById('p-price').value),
    image_url: document.getElementById('p-image-base64').value, // Nhận dữ liệu chuỗi ảnh từ máy
    description: document.getElementById('p-desc').value,
  };

  const method = id ? 'PUT' : 'POST';
  const url = id ? `${API_URL}/${id}` : API_URL;

  const res = await fetch(url, {
    method,
    headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
    body: JSON.stringify(payload)
  });

  if (res.ok) {
    showToast(id ? 'Cập nhật sản phẩm thành công!' : 'Đăng sản phẩm thành công!', 'success');
    closeModal('product-modal');
    fetchMerchantProducts();
  } else {
    const data = await res.json();
    showToast(data.error || 'Có lỗi xảy ra!', 'error');
  }
}

async function deleteProduct(id) {
  const token = localStorage.getItem('token');
  if (confirm('Bạn có chắc muốn xóa sản phẩm này?')) {
    const res = await fetch(`${API_URL}/${id}`, {
      method: 'DELETE',
      headers: { 'Authorization': `Bearer ${token}` }
    });
    if (res.ok) {
      showToast('Đã xóa sản phẩm thành công!', 'success');
      fetchMerchantProducts();
    }
  }
}

// 9. AUTHENTICATION & MODAL
async function handleAuthSubmit(e) {
  e.preventDefault();
  const mode = document.getElementById('auth-mode').value;
  const username = document.getElementById('auth-username').value.trim();
  const password = document.getElementById('auth-password').value.trim();

  const selectedRoles = Array.from(document.querySelectorAll('.auth-role-cb:checked')).map(cb => cb.value);

  if (mode === 'register' && selectedRoles.length === 0) {
    return showToast('Vui lòng chọn ít nhất 1 vai trò cho tài khoản!', 'error');
  }

  const endpoint = mode === 'login' ? '/api/login' : '/api/register';
  const body = mode === 'login' 
    ? { username, password } 
    : { username, password, roles: selectedRoles };

  try {
    const res = await fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    });

    const data = await res.json();
    if (res.ok) {
      if (mode === 'login') {
        showToast('Đăng nhập thành công!', 'success');
        localStorage.setItem('token', data.token);
        localStorage.setItem('roles', JSON.stringify(data.roles));
        localStorage.setItem('username', data.username);
        checkLoginState();
        closeModal('auth-modal');
        switchPage('home', document.querySelector('.nav-link'));
      } else {
        showToast('Đăng ký tài khoản thành công!', 'success');
        openAuthModal('login');
      }
    } else {
      showToast(data.error || 'Thao tác thất bại!', 'error');
    }
  } catch (err) {
    showToast('Không thể kết nối đến máy chủ!', 'error');
  }
}

function openAuthModal(mode) {
  document.getElementById('auth-mode').value = mode;
  const roleGroup = document.getElementById('role-select-group');
  if (roleGroup) roleGroup.style.display = mode === 'register' ? 'block' : 'none';
  document.getElementById('auth-title').innerText = mode === 'login' ? 'Đăng Nhập' : 'Đăng Ký Tài Khoản';
  document.getElementById('auth-modal').style.display = 'flex';
}

function closeModal(id) { 
  document.getElementById(id).style.display = 'none'; 
}

function logout() {
  localStorage.clear();
  cart = [];
  checkLoginState();
  showToast('Đã đăng xuất tài khoản!', 'info');
  switchPage('home', document.querySelector('.nav-link'));
}

function filterCategory(cat, btn) {
  currentCategory = cat;
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  fetchProducts();
}

function escapeHtml(text) { 
  return text.replace(/'/g, "\\'").replace(/"/g, '&quot;'); 
}