const express = require('express');
const Database = require('better-sqlite3');
const cors = require('cors');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');

const app = express();
const PORT = 3000;
const JWT_SECRET = 'laistore_secret_key_123';

app.use(cors());
app.use(express.json());
app.use(express.static('public'));

const db = new Database('laistore.db');

// Khởi tạo Bảng dữ liệu (users, products, orders, order_items)
db.exec(`
  CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'user',
    email TEXT DEFAULT '',
    phone TEXT DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  );

  CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    merchant_id INTEGER,
    name TEXT NOT NULL,
    category TEXT DEFAULT 'Khác',
    price INTEGER NOT NULL,
    description TEXT,
    image_url TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (merchant_id) REFERENCES users(id)
  );

  CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    shipper_id INTEGER,
    customer_name TEXT NOT NULL,
    phone TEXT NOT NULL,
    address TEXT NOT NULL,
    total_amount INTEGER NOT NULL,
    payment_method TEXT DEFAULT 'COD',
    status TEXT DEFAULT 'PENDING',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (shipper_id) REFERENCES users(id)
  );

  CREATE TABLE IF NOT EXISTS order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL,
    price INTEGER NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
  );
`);

// Middlewares kiểm tra Token & Phân quyền
function authenticateToken(req, res, next) {
  const authHeader = req.headers['authorization'];
  const token = authHeader && authHeader.split(' ')[1];
  if (!token) return res.status(401).json({ error: 'Bạn cần đăng nhập!' });

  jwt.verify(token, JWT_SECRET, (err, user) => {
    if (err) return res.status(403).json({ error: 'Token không hợp lệ hoặc hết hạn!' });
    req.user = user;
    next();
  });
}

function authorizeRoles(...allowedRoles) {
  return (req, res, next) => {
    const userRoles = req.user.role ? req.user.role.split(',') : [];
    const hasPermission = allowedRoles.some(r => userRoles.includes(r));
    if (!hasPermission) {
      return res.status(403).json({ error: 'Tài khoản của bạn không có quyền thực hiện thao tác này!' });
    }
    next();
  };
}

// ================= API XÁC THỰC & TÀI KHOẢN =================
app.post('/api/register', async (req, res) => {
  let { username, password, roles } = req.body;

  if (!username || !password) {
    return res.status(400).json({ error: 'Vui lòng nhập đầy đủ Tên đăng nhập và Mật khẩu!' });
  }

  username = username.trim();
  password = password.trim();

  const validRoles = ['user', 'merchant', 'shipper'];
  let selectedRoles = Array.isArray(roles) 
    ? roles.filter(r => validRoles.includes(r))
    : ['user'];

  if (selectedRoles.length === 0) selectedRoles = ['user'];
  const roleString = selectedRoles.join(',');

  try {
    const hashedPassword = await bcrypt.hash(password, 10);
    const stmt = db.prepare('INSERT INTO users (username, password, role) VALUES (?, ?, ?)');
    stmt.run(username, hashedPassword, roleString);

    res.json({ message: 'Đăng ký tài khoản thành công!' });
  } catch (err) {
    if (err.code === 'SQLITE_CONSTRAINT_UNIQUE' || err.message.includes('UNIQUE constraint failed')) {
      return res.status(400).json({ error: 'Tên tài khoản này đã tồn tại!' });
    }
    res.status(500).json({ error: `Lỗi máy chủ: ${err.message}` });
  }
});

app.post('/api/login', async (req, res) => {
  let { username, password } = req.body;

  if (!username || !password) {
    return res.status(400).json({ error: 'Vui lòng nhập tên tài khoản và mật khẩu!' });
  }

  username = username.trim();
  password = password.trim();

  try {
    const user = db.prepare('SELECT * FROM users WHERE LOWER(username) = LOWER(?)').get(username);
    
    if (!user) {
      return res.status(400).json({ error: 'Tài khoản không tồn tại!' });
    }

    const validPassword = await bcrypt.compare(password, user.password);
    if (!validPassword) {
      return res.status(400).json({ error: 'Mật khẩu không đúng!' });
    }

    const userRoles = user.role ? user.role.split(',') : ['user'];

    const token = jwt.sign(
      { id: user.id, username: user.username, role: user.role },
      JWT_SECRET,
      { expiresIn: '1d' }
    );

    res.json({
      message: 'Đăng nhập thành công!',
      token,
      username: user.username,
      roles: userRoles
    });
  } catch (err) {
    res.status(500).json({ error: `Lỗi máy chủ: ${err.message}` });
  }
});

app.get('/api/account/profile', authenticateToken, (req, res) => {
  const user = db.prepare('SELECT id, username, role, email, phone FROM users WHERE id = ?').get(req.user.id);
  if (!user) return res.status(404).json({ error: 'Không tìm thấy thông tin người dùng!' });
  res.json(user);
});

app.put('/api/account/profile', authenticateToken, (req, res) => {
  const { email, phone } = req.body;
  db.prepare('UPDATE users SET email = ?, phone = ? WHERE id = ?').run(email || '', phone || '', req.user.id);
  res.json({ message: 'Cập nhật thông tin thành công!' });
});

app.post('/api/change-password', authenticateToken, async (req, res) => {
  let { currentPassword, newPassword } = req.body;

  if (!currentPassword || !newPassword) {
    return res.status(400).json({ error: 'Vui lòng nhập đầy đủ thông tin!' });
  }

  if (newPassword.length < 6) {
    return res.status(400).json({ error: 'Mật khẩu mới phải có ít nhất 6 ký tự!' });
  }

  try {
    const user = db.prepare('SELECT * FROM users WHERE id = ?').get(req.user.id);
    const validPassword = await bcrypt.compare(currentPassword.trim(), user.password);
    
    if (!validPassword) {
      return res.status(400).json({ error: 'Mật khẩu hiện tại không chính xác!' });
    }

    const hashedNewPassword = await bcrypt.hash(newPassword.trim(), 10);
    db.prepare('UPDATE users SET password = ? WHERE id = ?').run(hashedNewPassword, req.user.id);

    res.json({ message: 'Đổi mật khẩu thành công!' });
  } catch (err) {
    res.status(500).json({ error: `Lỗi máy chủ: ${err.message}` });
  }
});

// ================= API SẢN PHẨM =================
app.get('/api/products', (req, res) => {
  const { search, category } = req.query;
  let query = 'SELECT * FROM products WHERE 1=1';
  const params = [];

  if (search) { query += ' AND name LIKE ?'; params.push(`%${search}%`); }
  if (category && category !== 'All') { query += ' AND category = ?'; params.push(category); }
  query += ' ORDER BY id DESC';

  res.json(db.prepare(query).all(...params));
});

app.post('/api/products', authenticateToken, authorizeRoles('merchant'), (req, res) => {
  const { name, category, price, description, image_url } = req.body;
  const stmt = db.prepare('INSERT INTO products (merchant_id, name, category, price, description, image_url) VALUES (?, ?, ?, ?, ?, ?)');
  const info = stmt.run(req.user.id, name, category || 'Khác', price, description || '', image_url || '');
  res.json({ message: 'Đăng sản phẩm thành công', id: info.lastInsertRowid });
});

app.put('/api/products/:id', authenticateToken, authorizeRoles('merchant'), (req, res) => {
  const { name, category, price, description, image_url } = req.body;
  const stmt = db.prepare('UPDATE products SET name = ?, category = ?, price = ?, description = ?, image_url = ? WHERE id = ? AND merchant_id = ?');
  const info = stmt.run(name, category, price, description || '', image_url || '', req.params.id, req.user.id);
  
  if (info.changes === 0) return res.status(403).json({ error: 'Không tìm thấy sản phẩm hoặc bạn không có quyền sửa!' });
  res.json({ message: 'Cập nhật sản phẩm thành công!' });
});

app.delete('/api/products/:id', authenticateToken, authorizeRoles('merchant'), (req, res) => {
  const stmt = db.prepare('DELETE FROM products WHERE id = ? AND merchant_id = ?');
  const info = stmt.run(req.params.id, req.user.id);
  if (info.changes === 0) return res.status(403).json({ error: 'Không thể xóa sản phẩm của người khác!' });
  res.json({ message: 'Đã xóa thành công!' });
});

// ================= API THANH TOÁN & ĐƠN HÀNG =================
app.post('/api/orders', authenticateToken, authorizeRoles('user'), (req, res) => {
  const { customer_name, phone, address, payment_method, items } = req.body;
  if (!items || items.length === 0) return res.status(400).json({ error: 'Giỏ hàng trống!' });

  let total_amount = items.reduce((sum, item) => sum + (item.price * item.quantity), 0);

  const insertOrder = db.prepare(`
    INSERT INTO orders (customer_id, customer_name, phone, address, total_amount, payment_method)
    VALUES (?, ?, ?, ?, ?, ?)
  `);
  
  const insertItem = db.prepare(`
    INSERT INTO order_items (order_id, product_id, quantity, price)
    VALUES (?, ?, ?, ?)
  `);

  const createTransaction = db.transaction(() => {
    const info = insertOrder.run(req.user.id, customer_name, phone, address, total_amount, payment_method);
    const orderId = info.lastInsertRowid;
    for (const item of items) {
      insertItem.run(orderId, item.id, item.quantity, item.price);
    }
    return orderId;
  });

  const orderId = createTransaction();
  res.json({ message: 'Đặt hàng thành công!', orderId });
});

app.get('/api/orders/my-orders', authenticateToken, authorizeRoles('user'), (req, res) => {
  const orders = db.prepare('SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC').all(req.user.id);
  res.json(orders);
});

app.get('/api/orders/shipper', authenticateToken, authorizeRoles('shipper'), (req, res) => {
  const orders = db.prepare('SELECT * FROM orders WHERE shipper_id IS NULL OR shipper_id = ? ORDER BY id DESC').all(req.user.id);
  res.json(orders);
});

app.put('/api/orders/:id/status', authenticateToken, authorizeRoles('shipper'), (req, res) => {
  const { status } = req.body;
  const orderId = req.params.id;

  const order = db.prepare('SELECT * FROM orders WHERE id = ?').get(orderId);
  if (!order) return res.status(404).json({ error: 'Không tìm thấy đơn hàng' });

  const stmt = db.prepare('UPDATE orders SET status = ?, shipper_id = ? WHERE id = ?');
  stmt.run(status, req.user.id, orderId);

  res.json({ message: 'Cập nhật trạng thái thành công!' });
});

app.listen(PORT, () => {
  console.log(`🚀 LaiStore Server đang chạy tại: http://localhost:${PORT}`);
});