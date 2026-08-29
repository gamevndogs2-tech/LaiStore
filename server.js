const express = require('express');
const sqlite3 = require('sqlite3').verbose();
const fs = require('fs');
const path = require('path');
const cors = require('cors');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');

const app = express();
const PORT = 3000;
const JWT_SECRET = 'laistore_secret_key_123';
const SQL_FILE_PATH = path.join(__dirname, 'laistore.sql');

app.use(cors());
app.use(express.json({ limit: '50mb' }));
app.use(express.static('public'));

// 1. KẾT NỐI DATABASE VÀ ĐỌC FILE .SQL VÀO BỘ NHỚ
const db = new sqlite3.Database(':memory:');

// Lấy dữ liệu từ file .sql nạp vào Database
function loadDatabaseFromSQL() {
  if (fs.existsSync(SQL_FILE_PATH)) {
    const sqlContent = fs.readFileSync(SQL_FILE_PATH, 'utf8');
    db.exec(sqlContent, (err) => {
      if (err) console.error('Lỗi nạp file SQL:', err.message);
      else console.log('✅ Đã nạp thành công dữ liệu từ file laistore.sql!');
    });
  }
}

// Lưu toàn bộ Database hiện tại ghi đè lại file .sql
function saveDatabaseToSQL() {
  let dumpSQL = '';
  const tables = ['users', 'products', 'orders', 'order_items'];

  db.serialize(() => {
    // Tạo lại cấu trúc bảng
    dumpSQL += `CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE NOT NULL, password TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'user', email TEXT DEFAULT '', phone TEXT DEFAULT '', created_at DATETIME DEFAULT CURRENT_TIMESTAMP);\n`;
    dumpSQL += `CREATE TABLE IF NOT EXISTS products (id INTEGER PRIMARY KEY AUTOINCREMENT, merchant_id INTEGER, name TEXT NOT NULL, category TEXT DEFAULT 'Khác', price INTEGER NOT NULL, description TEXT, image_url TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);\n`;
    dumpSQL += `CREATE TABLE IF NOT EXISTS orders (id INTEGER PRIMARY KEY AUTOINCREMENT, customer_id INTEGER NOT NULL, shipper_id INTEGER, customer_name TEXT NOT NULL, phone TEXT NOT NULL, address TEXT NOT NULL, total_amount INTEGER NOT NULL, payment_method TEXT DEFAULT 'COD', status TEXT DEFAULT 'PENDING', created_at DATETIME DEFAULT CURRENT_TIMESTAMP);\n`;
    dumpSQL += `CREATE TABLE IF NOT EXISTS order_items (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL, product_id INTEGER NOT NULL, quantity INTEGER NOT NULL, price INTEGER NOT NULL);\n\n`;

    let completed = 0;
    tables.forEach((table) => {
      db.all(`SELECT * FROM ${table}`, [], (err, rows) => {
        if (!err && rows) {
          rows.forEach((row) => {
            const keys = Object.keys(row).join(', ');
            const values = Object.values(row)
              .map((val) => (val === null ? 'NULL' : `'${String(val).replace(/'/g, "''")}'`))
              .join(', ');
            dumpSQL += `INSERT INTO ${table} (${keys}) VALUES (${values});\n`;
          });
        }
        completed++;
        if (completed === tables.length) {
          fs.writeFileSync(SQL_FILE_PATH, dumpSQL, 'utf8');
        }
      });
    });
  });
}

// Nạp dữ liệu lúc khởi động
loadDatabaseFromSQL();

// MIDDLEWARES
function authenticateToken(req, res, next) {
  const authHeader = req.headers['authorization'];
  const token = authHeader && authHeader.split(' ')[1];
  if (!token) return res.status(401).json({ error: 'Bạn cần đăng nhập!' });

  jwt.verify(token, JWT_SECRET, (err, user) => {
    if (err) return res.status(403).json({ error: 'Token hết hạn hoặc không hợp lệ!' });
    req.user = user;
    next();
  });
}

function authorizeRoles(...allowedRoles) {
  return (req, res, next) => {
    const userRoles = req.user.role ? req.user.role.split(',') : [];
    if (!allowedRoles.some((r) => userRoles.includes(r))) {
      return res.status(403).json({ error: 'Không có quyền thực hiện!' });
    }
    next();
  };
}

// API XÁC THỰC
app.post('/api/register', async (req, res) => {
  const { username, password, roles } = req.body;
  if (!username || !password) return res.status(400).json({ error: 'Vui lòng nhập đầy đủ thông tin!' });

  const validRoles = ['user', 'merchant', 'shipper'];
  let selectedRoles = Array.isArray(roles) ? roles.filter((r) => validRoles.includes(r)) : ['user'];
  const roleString = selectedRoles.join(',');

  try {
    const hashedPassword = await bcrypt.hash(password.trim(), 10);
    db.run(
      'INSERT INTO users (username, password, role) VALUES (?, ?, ?)',
      [username.trim(), hashedPassword, roleString],
      function (err) {
        if (err) return res.status(400).json({ error: 'Tên tài khoản đã tồn tại!' });
        saveDatabaseToSQL(); // Ghi lại vào file .sql
        res.json({ message: 'Đăng ký thành công!' });
      }
    );
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.post('/api/login', (req, res) => {
  const { username, password } = req.body;
  db.get('SELECT * FROM users WHERE LOWER(username) = LOWER(?)', [username.trim()], async (err, user) => {
    if (err || !user) return res.status(400).json({ error: 'Tài khoản không tồn tại!' });

    const validPassword = await bcrypt.compare(password.trim(), user.password);
    if (!validPassword) return res.status(400).json({ error: 'Mật khẩu không đúng!' });

    const token = jwt.sign({ id: user.id, username: user.username, role: user.role }, JWT_SECRET, { expiresIn: '1d' });
    res.json({ message: 'Thành công!', token, username: user.username, roles: user.role.split(',') });
  });
});

app.get('/api/account/profile', authenticateToken, (req, res) => {
  db.get('SELECT id, username, role, email, phone FROM users WHERE id = ?', [req.user.id], (err, user) => {
    res.json(user || {});
  });
});

app.put('/api/account/profile', authenticateToken, (req, res) => {
  const { email, phone } = req.body;
  db.run('UPDATE users SET email = ?, phone = ? WHERE id = ?', [email || '', phone || '', req.user.id], () => {
    saveDatabaseToSQL();
    res.json({ message: 'Cập nhật thành công!' });
  });
});

app.post('/api/change-password', authenticateToken, (req, res) => {
  const { currentPassword, newPassword } = req.body;
  db.get('SELECT * FROM users WHERE id = ?', [req.user.id], async (err, user) => {
    const isValid = await bcrypt.compare(currentPassword.trim(), user.password);
    if (!isValid) return res.status(400).json({ error: 'Mật khẩu cũ không đúng!' });

    const hashedNew = await bcrypt.hash(newPassword.trim(), 10);
    db.run('UPDATE users SET password = ? WHERE id = ?', [hashedNew, req.user.id], () => {
      saveDatabaseToSQL();
      res.json({ message: 'Đổi mật khẩu thành công!' });
    });
  });
});

// API SẢN PHẨM
app.get('/api/products', (req, res) => {
  const { search, category } = req.query;
  let query = 'SELECT * FROM products WHERE 1=1';
  const params = [];

  if (search) { query += ' AND name LIKE ?'; params.push(`%${search}%`); }
  if (category && category !== 'All') { query += ' AND category = ?'; params.push(category); }
  query += ' ORDER BY id DESC';

  db.all(query, params, (err, rows) => {
    res.json(rows || []);
  });
});

app.post('/api/products', authenticateToken, authorizeRoles('merchant'), (req, res) => {
  const { name, category, price, description, image_url } = req.body;
  db.run(
    'INSERT INTO products (merchant_id, name, category, price, description, image_url) VALUES (?, ?, ?, ?, ?, ?)',
    [req.user.id, name, category || 'Khác', price, description || '', image_url || ''],
    function (err) {
      if (err) return res.status(500).json({ error: err.message });
      saveDatabaseToSQL();
      res.json({ message: 'Đăng sản phẩm thành công!', id: this.lastID });
    }
  );
});

app.put('/api/products/:id', authenticateToken, authorizeRoles('merchant'), (req, res) => {
  const { name, category, price, description, image_url } = req.body;
  db.run(
    'UPDATE products SET name = ?, category = ?, price = ?, description = ?, image_url = ? WHERE id = ? AND merchant_id = ?',
    [name, category, price, description || '', image_url || '', req.params.id, req.user.id],
    function (err) {
      if (this.changes === 0) return res.status(403).json({ error: 'Không có quyền chỉnh sửa!' });
      saveDatabaseToSQL();
      res.json({ message: 'Cập nhật thành công!' });
    }
  );
});

app.delete('/api/products/:id', authenticateToken, authorizeRoles('merchant'), (req, res) => {
  db.run('DELETE FROM products WHERE id = ? AND merchant_id = ?', [req.params.id, req.user.id], function (err) {
    if (this.changes === 0) return res.status(403).json({ error: 'Không có quyền xóa!' });
    saveDatabaseToSQL();
    res.json({ message: 'Xóa thành công!' });
  });
});

// API ĐƠN HÀNG
app.post('/api/orders', authenticateToken, authorizeRoles('user'), (req, res) => {
  const { customer_name, phone, address, payment_method, items } = req.body;
  if (!items || items.length === 0) return res.status(400).json({ error: 'Giỏ hàng trống!' });

  const total_amount = items.reduce((sum, item) => sum + item.price * item.quantity, 0);

  db.run(
    'INSERT INTO orders (customer_id, customer_name, phone, address, total_amount, payment_method) VALUES (?, ?, ?, ?, ?, ?)',
    [req.user.id, customer_name, phone, address, total_amount, payment_method || 'COD'],
    function (err) {
      if (err) return res.status(500).json({ error: err.message });

      const orderId = this.lastID;
      const stmt = db.prepare('INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)');
      items.forEach((item) => stmt.run([orderId, item.id, item.quantity, item.price]));
      stmt.finalize();

      saveDatabaseToSQL();
      res.json({ message: 'Đặt hàng thành công!' });
    }
  );
});

app.get('/api/orders/my-orders', authenticateToken, authorizeRoles('user'), (req, res) => {
  db.all('SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC', [req.user.id], (err, rows) => {
    res.json(rows || []);
  });
});

app.get('/api/orders/shipper', authenticateToken, authorizeRoles('shipper'), (req, res) => {
  db.all('SELECT * FROM orders ORDER BY id DESC', [], (err, rows) => {
    res.json(rows || []);
  });
});

app.put('/api/orders/:id/status', authenticateToken, authorizeRoles('shipper'), (req, res) => {
  const { status } = req.body;
  db.run('UPDATE orders SET status = ?, shipper_id = ? WHERE id = ?', [status, req.user.id, req.params.id], () => {
    saveDatabaseToSQL();
    res.json({ message: 'Cập nhật trạng thái thành công!' });
  });
});

// KHỞI CHẠY SERVER
app.listen(PORT, () => {
  console.log(`=================================`);
  console.log(`🚀 LaiStore Server đang chạy tại: http://localhost:${PORT}`);
  console.log(`📄 Database File dạng SQL: laistore.sql`);
  console.log(`=================================`);
});