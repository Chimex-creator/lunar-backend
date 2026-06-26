<?php
// C:\xampp\htdocs\php-backend\index.php
// Full Lunar Store API — v2

// ============================================
// 1. CORS HEADERS
// ============================================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================
// 2. LOAD .ENV
// ============================================
$env_path = __DIR__ . '/.env';
if (file_exists($env_path)) {
    foreach (file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        list($k, $v) = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v);
    }
}

// ============================================
// 3. DATABASE CONNECTION
// ============================================
$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? $_ENV['MYSQLHOST'] ?? getenv('MYSQLHOST') ?? $_ENV['MYSQL_HOST'] ?? getenv('MYSQL_HOST') ?? 'localhost';
$db   = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?? $_ENV['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE') ?? $_ENV['MYSQL_DATABASE'] ?? getenv('MYSQL_DATABASE') ?? 'lunar_store';
$user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?? $_ENV['MYSQLUSER'] ?? getenv('MYSQLUSER') ?? $_ENV['MYSQL_USER'] ?? getenv('MYSQL_USER') ?? 'root';
$pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?? $_ENV['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD') ?? $_ENV['MYSQL_PASSWORD'] ?? getenv('MYSQL_PASSWORD') ?? '';
$port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? $_ENV['MYSQLPORT'] ?? getenv('MYSQLPORT') ?? $_ENV['MYSQL_PORT'] ?? getenv('MYSQL_PORT') ?? '3306';

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // ── CREATE ALL TABLES FIRST (safe on fresh DB) ──────────────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('user','admin') DEFAULT 'user',
            avatar VARCHAR(255) DEFAULT NULL,
            bio TEXT DEFAULT NULL,
            notifications TEXT DEFAULT NULL,
            is_blocked TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            description TEXT NULL,
            image_url VARCHAR(255) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            description TEXT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            stock INT NOT NULL DEFAULT 0,
            image_url VARCHAR(255) NOT NULL,
            is_featured TINYINT(1) DEFAULT 0,
            is_new_arrival TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS discounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) UNIQUE NOT NULL,
            type ENUM('percent','fixed') NOT NULL,
            value DECIMAL(10,2) NOT NULL,
            expires_at DATE NOT NULL,
            usage_limit INT NULL,
            used_count INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            who_can_use VARCHAR(20) DEFAULT 'all',
            one_time_use TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NULL,
            customer_name VARCHAR(255) NULL,
            total_amount DECIMAL(10,2) NOT NULL,
            status ENUM('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
            shipping_address TEXT NOT NULL,
            phone VARCHAR(20) NULL,
            discount_code VARCHAR(50) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS addresses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            address_line1 VARCHAR(255) NOT NULL,
            address_line2 VARCHAR(255) NULL,
            city VARCHAR(100) NOT NULL,
            state VARCHAR(100) NOT NULL,
            postal_code VARCHAR(20) NULL,
            country VARCHAR(100) NOT NULL,
            is_default TINYINT(1) DEFAULT 0,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS order_status_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            status VARCHAR(50) NOT NULL,
            changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            notes TEXT NULL,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS newsletters (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) UNIQUE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contact_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            subscribed_newsletter TINYINT(1) DEFAULT 0,
            status ENUM('Unread','Read','Replied') DEFAULT 'Unread',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS vip_consultations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            guest_name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            requested_service VARCHAR(255) NOT NULL,
            requested_date DATE DEFAULT NULL,
            status ENUM('Pending','Contacted','Completed') DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // ── SELF-HEALING: add missing columns to existing tables ─────────────────
    $columns = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('phone', $columns))         $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL AFTER email");
    if (!in_array('avatar', $columns))        $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL AFTER role");
    if (!in_array('bio', $columns))           $pdo->exec("ALTER TABLE users ADD COLUMN bio TEXT DEFAULT NULL AFTER avatar");
    if (!in_array('notifications', $columns)) $pdo->exec("ALTER TABLE users ADD COLUMN notifications TEXT DEFAULT NULL AFTER bio");

    $prod_cols = $pdo->query("DESCRIBE products")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('is_new_arrival', $prod_cols)) $pdo->exec("ALTER TABLE products ADD COLUMN is_new_arrival TINYINT(1) DEFAULT 0 AFTER is_featured");

    $disc_cols = $pdo->query("DESCRIBE discounts")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('who_can_use', $disc_cols))  $pdo->exec("ALTER TABLE discounts ADD COLUMN who_can_use VARCHAR(20) DEFAULT 'all'");
    if (!in_array('one_time_use', $disc_cols)) $pdo->exec("ALTER TABLE discounts ADD COLUMN one_time_use TINYINT(1) DEFAULT 0");

    $order_cols = $pdo->query("DESCRIBE orders")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('discount_code', $order_cols)) $pdo->exec("ALTER TABLE orders ADD COLUMN discount_code VARCHAR(50) DEFAULT NULL");

    $contact_cols = $pdo->query("DESCRIBE contact_messages")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('status', $contact_cols)) $pdo->exec("ALTER TABLE contact_messages ADD COLUMN status ENUM('Unread','Read','Replied') DEFAULT 'Unread' AFTER subscribed_newsletter");

    // ── SEED DEFAULT DATA ─────────────────────────────────────────────────────
    $ck_welcome = $pdo->prepare("SELECT id FROM discounts WHERE code = 'WELCOME10'");
    $ck_welcome->execute();
    if (!$ck_welcome->fetch()) {
        $pdo->exec("INSERT INTO discounts (code, type, value, expires_at, usage_limit, used_count, is_active, who_can_use, one_time_use) VALUES ('WELCOME10', 'percent', 10.00, '2030-12-31', 1000, 0, 1, 'new', 1)");
    }

    $ck_summer = $pdo->prepare("SELECT id FROM discounts WHERE code = 'SUMMER25'");
    $ck_summer->execute();
    if (!$ck_summer->fetch()) {
        $pdo->exec("INSERT INTO discounts (code, type, value, expires_at, usage_limit, used_count, is_active, who_can_use, one_time_use) VALUES ('SUMMER25', 'percent', 25.00, '2030-12-31', 1000, 0, 1, 'existing', 1)");
    }

    $ck_user = $pdo->prepare("SELECT id FROM users WHERE email = 'testexisting@lunar.com'");
    $ck_user->execute();
    if (!$ck_user->fetch()) {
        $hash = password_hash('password123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (name, email, password, role, avatar, bio, notifications, is_blocked, created_at) VALUES ('Test Existing', 'testexisting@lunar.com', ?, 'user', NULL, NULL, NULL, 0, DATE_SUB(NOW(), INTERVAL 31 DAY))")
            ->execute([$hash]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'DB connection failed: ' . $e->getMessage()]));
}

// ============================================
// 4. HELPERS
// ============================================
function ok($data, $code = 200) {
    http_response_code($code);
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit();
}
function err($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit();
}

function mapProduct($product) {
    if (!$product) return $product;
    $image = $product['image_url'] ?? '';
    $baseUrl = 'http://localhost/php-backend/uploads/';
    if (!empty($image) && !str_starts_with($image, 'http://') && !str_starts_with($image, 'https://')) {
        if (str_starts_with($image, 'uploads/')) {
            $image = 'http://localhost/php-backend/' . $image;
        } else {
            $image = $baseUrl . $image;
        }
    }
    $product['image'] = $image;
    $product['image_url'] = $image;
    return $product;
}

function mapProducts($products) {
    if (!is_array($products)) return $products;
    foreach ($products as &$p) {
        $p = mapProduct($p);
    }
    return $products;
}

function getToken() {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (preg_match('/Bearer\s+(.+)$/i', $auth, $m)) return $m[1];
    return null;
}

function parseToken($token) {
    if (!$token) return null;
    $data = json_decode(base64_decode($token), true);
    if (!$data || !isset($data['user_id'], $data['expires']) || $data['expires'] < time()) return null;
    return $data;
}

function requireAuth() {
    $data = parseToken(getToken());
    if (!$data) err('Unauthorized. Please log in.', 401);
    return $data;
}

function requireAdmin() {
    $data = requireAuth();
    if (($data['role'] ?? '') !== 'admin') err('Forbidden. Admin access only.', 403);
    return $data;
}

// ============================================
// 5. PARSE REQUEST URI
// ============================================
$method = $_SERVER['REQUEST_METHOD'];
$uri = strtok($_SERVER['REQUEST_URI'], '?'); // strip query string
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Strip leading parts: php-backend / api
$parts = array_values(array_filter(explode('/', $uri), fn($p) => $p !== ''));
while (count($parts) > 0 && in_array($parts[0], ['php-backend', 'api', 'index.php'])) {
    array_shift($parts);
}

// Map parts to readable variables
$seg0 = $parts[0] ?? ''; // e.g. auth, products, admin
$seg1 = $parts[1] ?? ''; // e.g. login, 5, dashboard
$seg2 = $parts[2] ?? ''; // e.g. stock, status, enable
$seg3 = $parts[3] ?? ''; // e.g. sub-action

// ============================================
// 6. ROUTE DISPATCH
// ============================================

// --- ROOT ---
if ($seg0 === '') {
    ok(['message' => 'Lunar Store API v2.0 — All systems operational.']);
}

// ============================================
// AUTH ROUTES: /api/auth/...
// ============================================
if ($seg0 === 'auth') {

    // POST /api/auth/register
    if ($seg1 === 'register' && $method === 'POST') {
        if (empty($input['email']) || empty($input['password']) || empty($input['name'])) {
            err('Name, email and password are required.');
        }
        $ck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $ck->execute([$input['email']]);
        if ($ck->fetch()) err('This email is already registered.');

        $hash = password_hash($input['password'], PASSWORD_DEFAULT);
        $role = $input['role'] ?? 'user';
        $pdo->prepare("INSERT INTO users (name, email, password, role, is_blocked) VALUES (?,?,?,?,0)")
            ->execute([$input['name'], $input['email'], $hash, $role]);
        $uid = $pdo->lastInsertId();

        $token = base64_encode(json_encode([
            'user_id' => $uid, 'email' => $input['email'],
            'role' => $role, 'expires' => time() + 604800
        ]));
        ok(['user' => ['id' => $uid, 'name' => $input['name'], 'email' => $input['email'], 'role' => $role], 'token' => $token], 201);
    }

    // POST /api/auth/login
    if ($seg1 === 'login' && $method === 'POST') {
        if (empty($input['email']) || empty($input['password'])) err('Email and password required.');

        $st = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $st->execute([$input['email']]);
        $u = $st->fetch();

        if (!$u || !password_verify($input['password'], $u['password'])) err('Invalid email or password.');
        if ($u['is_blocked']) err('Your account has been suspended. Contact support.');

        unset($u['password']);
        $token = base64_encode(json_encode([
            'user_id' => $u['id'], 'email' => $u['email'],
            'role' => $u['role'], 'expires' => time() + 604800
        ]));
        ok(['user' => $u, 'token' => $token]);
    }

    // GET /api/auth/me
    if ($seg1 === 'me' && $method === 'GET') {
        $tkn = requireAuth();
        $st = $pdo->prepare("SELECT id, name, email, phone, role, avatar, bio, notifications, is_blocked, created_at as joined_date FROM users WHERE id = ?");
        $st->execute([$tkn['user_id']]);
        $u = $st->fetch();
        if (!$u) err('User not found.', 404);
        if ($u['avatar']) {
            $u['avatar_url'] = 'http://localhost/php-backend/uploads/profile/' . $u['avatar'];
        } else {
            $u['avatar_url'] = null;
        }
        ok($u);
    }

    // PUT /api/auth/profile — update profile details
    if ($seg1 === 'profile' && $method === 'PUT') {
        $tkn = requireAuth();
        if (empty($input['name']) || empty($input['email'])) {
            err('Name and email are required.');
        }
        $ck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $ck->execute([$input['email'], $tkn['user_id']]);
        if ($ck->fetch()) err('This email is already registered by another user.');

        $st = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, bio = ?, notifications = ? WHERE id = ?");
        $st->execute([
            $input['name'],
            $input['email'],
            $input['phone'] ?? null,
            $input['bio'] ?? null,
            isset($input['notifications']) ? (is_array($input['notifications']) ? json_encode($input['notifications']) : $input['notifications']) : null,
            $tkn['user_id']
        ]);

        $st_u = $pdo->prepare("SELECT id, name, email, phone, role, avatar, bio, notifications, is_blocked, created_at as joined_date FROM users WHERE id = ?");
        $st_u->execute([$tkn['user_id']]);
        $u = $st_u->fetch();
        if ($u['avatar']) {
            $u['avatar_url'] = 'http://localhost/php-backend/uploads/profile/' . $u['avatar'];
        } else {
            $u['avatar_url'] = null;
        }
        ok($u);
    }

    // POST /api/auth/avatar — upload avatar
    if ($seg1 === 'avatar' && $method === 'POST') {
        $tkn = requireAuth();
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            err('No file uploaded or upload error occurred.');
        }
        $file = $_FILES['avatar'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        if (!in_array($file['type'], $allowedTypes)) {
            err('Only JPG, PNG, GIF images are allowed.');
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            err('File size must be less than 2MB.');
        }
        
        $uploadDir = __DIR__ . '/uploads/profile/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_' . $tkn['user_id'] . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $st = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $st->execute([$filename, $tkn['user_id']]);
            $avatar_url = 'http://localhost/php-backend/uploads/profile/' . $filename;
            ok(['avatar_url' => $avatar_url, 'avatar' => $filename]);
        } else {
            err('Failed to save uploaded file.');
        }
    }

    // POST /api/auth/change-password — change password
    if ($seg1 === 'change-password' && $method === 'POST') {
        $tkn = requireAuth();
        if (empty($input['current_password']) || empty($input['new_password'])) {
            err('Current password and new password are required.');
        }
        $st = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $st->execute([$tkn['user_id']]);
        $hash = $st->fetchColumn();
        if (!$hash || !password_verify($input['current_password'], $hash)) {
            err('Invalid current password.');
        }
        $newHash = password_hash($input['new_password'], PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $tkn['user_id']]);
        ok(['message' => 'Password updated successfully.']);
    }

    err('Auth endpoint not found.', 404);
}

// ============================================
// CATEGORIES: /api/categories
// ============================================
if ($seg0 === 'categories' && $method === 'GET') {
    ok($pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll());
}

// ============================================
// PRODUCTS: /api/products[/featured | /category/{id} | /{id}]
// ============================================
if ($seg0 === 'products') {
    if ($method !== 'GET') err('Method not allowed.', 405);

    // /api/products/featured
    if ($seg1 === 'featured') {
        ok(mapProducts($pdo->query("SELECT * FROM products WHERE is_featured = 1 ORDER BY id DESC LIMIT 12")->fetchAll()));
    }

    // /api/products/new
    if ($seg1 === 'new') {
        ok(mapProducts($pdo->query("SELECT * FROM products WHERE is_new_arrival = 1 ORDER BY created_at DESC LIMIT 8")->fetchAll()));
    }

    // /api/products/category/{category_id}
    if ($seg1 === 'category' && $seg2 !== '') {
        $st = $pdo->prepare("SELECT * FROM products WHERE category_id = ? ORDER BY id DESC");
        $st->execute([$seg2]);
        ok(mapProducts($st->fetchAll()));
    }

    // /api/products/{id}
    if ($seg1 !== '' && is_numeric($seg1)) {
        $st = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $st->execute([$seg1]);
        $p = $st->fetch();
        if (!$p) err('Product not found.', 404);
        ok(mapProduct($p));
    }

    // /api/products  (optional ?category= or ?filter= filter)
    $cat = $_GET['category'] ?? null;
    $filter = $_GET['filter'] ?? null;
    if ($cat) {
        $st = $pdo->prepare("SELECT * FROM products WHERE category_id = ? ORDER BY id DESC");
        $st->execute([$cat]);
        ok(mapProducts($st->fetchAll()));
    }
    if ($filter === 'new') {
        ok(mapProducts($pdo->query("SELECT * FROM products WHERE is_new_arrival = 1 ORDER BY created_at DESC LIMIT 8")->fetchAll()));
    }
    if ($filter === 'featured') {
        ok(mapProducts($pdo->query("SELECT * FROM products WHERE is_featured = 1 ORDER BY id DESC LIMIT 12")->fetchAll()));
    }
    ok(mapProducts($pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll()));
}

// ============================================
// ORDERS: /api/orders
// ============================================
if ($seg0 === 'orders') {
    $tkn = requireAuth();

    // POST /api/orders — place new order
    if ($method === 'POST') {
        if (empty($input['total_amount']) || empty($input['shipping_address'])) {
            err('Order total and shipping address are required.');
        }
        $pdo->beginTransaction();
        try {
            $discount_code = !empty($input['discount_code']) ? strtoupper(trim($input['discount_code'])) : null;
            if ($discount_code) {
                // Increment used_count in transaction
                $st_disc = $pdo->prepare("UPDATE discounts SET used_count = used_count + 1 WHERE code = ? AND is_active = 1");
                $st_disc->execute([$discount_code]);
            }

            $st = $pdo->prepare(
                "INSERT INTO orders (customer_id, customer_name, total_amount, status, shipping_address, phone, discount_code)
                 VALUES (?,?,?,'pending',?,?,?)"
            );
            $st->execute([
                $tkn['user_id'],
                $input['customer_name'] ?? '',
                $input['total_amount'],
                $input['shipping_address'],
                $input['phone'] ?? null,
                $discount_code
            ]);
            $order_id = $pdo->lastInsertId();

            if (!empty($input['items']) && is_array($input['items'])) {
                $si = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?,?,?,?)");
                $ss = $pdo->prepare("UPDATE products SET stock = CASE WHEN stock >= ? THEN stock - ? ELSE 0 END WHERE id = ?");
                foreach ($input['items'] as $item) {
                    $si->execute([$order_id, $item['product_id'], $item['quantity'], $item['price']]);
                    $ss->execute([$item['quantity'], $item['quantity'], $item['product_id']]);
                }
            }
            $pdo->commit();
            ok(['order_id' => $order_id], 201);
        } catch (Exception $e) {
            $pdo->rollBack();
            err('Failed to create order: ' . $e->getMessage());
        }
    }

    // GET /api/orders — customer's own orders
    if ($method === 'GET') {
        $st = $pdo->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC");
        $st->execute([$tkn['user_id']]);
        ok($st->fetchAll());
    }

    err('Method not allowed.', 405);
}

// ============================================
// DISCOUNTS: /api/discounts
// ============================================
if ($seg0 === 'discounts') {
    if ($seg1 === 'validate' && $method === 'POST') {
        $tkn = requireAuth();
        if (empty($input['code'])) {
            err('Promo code is required.');
        }

        $code = strtoupper(trim($input['code']));
        $st = $pdo->prepare("SELECT * FROM discounts WHERE code = ?");
        $st->execute([$code]);
        $disc = $st->fetch();

        if (!$disc || intval($disc['is_active']) === 0) {
            err('Invalid promo code. Please try again.');
        }

        $today = date('Y-m-d');
        if ($disc['expires_at'] < $today) {
            err('This code has expired.');
        }

        if ($disc['usage_limit'] !== null && intval($disc['used_count']) >= intval($disc['usage_limit'])) {
            err('This code has reached its usage limit.');
        }

        // Get user's registration date
        $ust = $pdo->prepare("SELECT created_at FROM users WHERE id = ?");
        $ust->execute([$tkn['user_id']]);
        $user_created = $ust->fetchColumn();

        $days_registered = (time() - strtotime($user_created)) / 86400;

        if ($disc['who_can_use'] === 'new') {
            if ($days_registered > 30) {
                err('This code is for new customers only.');
            }
        } elseif ($disc['who_can_use'] === 'existing') {
            if ($days_registered <= 30) {
                err('This code is for existing customers only.');
            }
        }

        if (intval($disc['one_time_use']) === 1) {
            $ost = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND discount_code = ?");
            $ost->execute([$tkn['user_id'], $disc['code']]);
            $used_before = intval($ost->fetchColumn());
            if ($used_before > 0) {
                err('You have already used this code.');
            }
        }

        ok([
            'code' => $disc['code'],
            'type' => $disc['type'],
            'value' => floatval($disc['value'])
        ]);
    }
}

// ============================================
// CART: /api/cart (mock — frontend handles cart client-side)
// ============================================
if ($seg0 === 'cart') {
    if ($method === 'GET') ok(['items' => []]);
    ok(['message' => 'Cart synced.']);
}

// ============================================
// ADMIN ROUTES: /api/admin/...
// ============================================
if ($seg0 === 'admin') {
    requireAdmin();

    // --- GET /api/admin/dashboard ---
    if ($seg1 === 'dashboard' && $method === 'GET') {
        $total_orders = intval($pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn());
        $total_revenue = floatval($pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders")->fetchColumn());
        $total_users = intval($pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn());
        $total_products = intval($pdo->query("SELECT COUNT(*) FROM products WHERE stock > 0")->fetchColumn());
        
        $today = date('Y-m-d');
        $month = date('Y-m');
        
        $today_orders = intval($pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = '$today'")->fetchColumn());
        $today_revenue = floatval($pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE DATE(created_at) = '$today'")->fetchColumn());
        $month_orders = intval($pdo->query("SELECT COUNT(*) FROM orders WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month'")->fetchColumn());
        $month_revenue = floatval($pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month'")->fetchColumn());

        $summary = [
            'total_orders' => $total_orders,
            'total_revenue' => $total_revenue,
            'total_users' => $total_users,
            'total_products' => $total_products,
            'today_orders' => $today_orders,
            'today_revenue' => $today_revenue,
            'month_orders' => $month_orders,
            'month_revenue' => $month_revenue
        ];

        $pending = intval($pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn());
        $processing = intval($pdo->query("SELECT COUNT(*) FROM orders WHERE status='processing'")->fetchColumn());
        $shipped = intval($pdo->query("SELECT COUNT(*) FROM orders WHERE status='shipped'")->fetchColumn());
        $delivered = intval($pdo->query("SELECT COUNT(*) FROM orders WHERE status='delivered'")->fetchColumn());
        $cancelled = intval($pdo->query("SELECT COUNT(*) FROM orders WHERE status='cancelled'")->fetchColumn());

        $order_status = [
            'pending' => $pending,
            'processing' => $processing,
            'shipped' => $shipped,
            'delivered' => $delivered,
            'cancelled' => $cancelled
        ];

        $inv_total = intval($pdo->query("SELECT COUNT(*) FROM products")->fetchColumn());
        $low_stock = intval($pdo->query("SELECT COUNT(*) FROM products WHERE stock > 0 AND stock <= 5")->fetchColumn());
        $out_stock = intval($pdo->query("SELECT COUNT(*) FROM products WHERE stock = 0")->fetchColumn());
        
        $low_stock_products = $pdo->query("SELECT id, name, stock, price FROM products WHERE stock > 0 AND stock <= 5 ORDER BY stock ASC LIMIT 10")->fetchAll();
        foreach ($low_stock_products as &$item) {
            $item['id'] = intval($item['id']);
            $item['stock'] = intval($item['stock']);
            $item['price'] = floatval($item['price']);
        }

        $inventory = [
            'total_products' => $inv_total,
            'low_stock_count' => $low_stock,
            'out_of_stock_count' => $out_stock,
            'low_stock_products' => $low_stock_products
        ];

        $best_sellers = $pdo->query("
            SELECT oi.product_id, p.name AS product_name, SUM(oi.quantity) AS total_sold, SUM(oi.quantity * oi.price) AS total_revenue
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            JOIN orders o ON oi.order_id = o.id
            WHERE o.status = 'delivered'
            GROUP BY oi.product_id, p.name
            ORDER BY total_sold DESC
            LIMIT 5
        ")->fetchAll();
        foreach ($best_sellers as &$bs) {
            $bs['product_id'] = intval($bs['product_id']);
            $bs['total_sold'] = intval($bs['total_sold']);
            $bs['total_revenue'] = floatval($bs['total_revenue']);
        }

        $recent_orders = $pdo->query("
            SELECT id, CONCAT('LNR-', id) AS order_number, customer_name, total_amount, status, created_at AS date
            FROM orders
            ORDER BY id DESC
            LIMIT 5
        ")->fetchAll();
        foreach ($recent_orders as &$ro) {
            $ro['id'] = intval($ro['id']);
            $ro['total_amount'] = floatval($ro['total_amount']);
        }

        ok([
            'summary' => $summary,
            'order_status' => $order_status,
            'inventory' => $inventory,
            'best_sellers' => $best_sellers,
            'recent_orders' => $recent_orders
        ]);
    }

    // ---- ADMIN PRODUCTS ----
    if ($seg1 === 'products') {

        // GET /api/admin/products
        if ($method === 'GET' && $seg2 === '') {
            ok(mapProducts($pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll()));
        }

        // POST /api/admin/products
        if ($method === 'POST' && $seg2 === '') {
            if (empty($input['name']) || !isset($input['price']) || !isset($input['stock']) || empty($input['category_id'])) {
                err('Name, price, stock and category_id are required.');
            }
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $input['name'])));
            $img  = $input['image_url'] ?? 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?w=800&auto=format&fit=crop&q=80';
            $st = $pdo->prepare(
                "INSERT INTO products (category_id, name, slug, description, price, stock, image_url, is_featured, is_new_arrival)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            );
            $st->execute([
                $input['category_id'], $input['name'], $slug,
                $input['description'] ?? '',
                $input['price'], $input['stock'], $img,
                $input['is_featured'] ?? 0,
                $input['is_new_arrival'] ?? 0
            ]);
            ok(['id' => $pdo->lastInsertId()], 201);
        }

        // PUT /api/admin/products/{id}/stock
        if ($method === 'PUT' && is_numeric($seg2) && $seg3 === 'stock') {
            $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?")->execute([$input['stock'], $seg2]);
            ok(['message' => 'Stock updated.']);
        }

        // PUT /api/admin/products/{id}
        if ($method === 'PUT' && is_numeric($seg2) && $seg3 === '') {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $input['name'])));
            $st = $pdo->prepare(
                "UPDATE products SET category_id=?, name=?, slug=?, description=?, price=?, stock=?, image_url=?, is_featured=?, is_new_arrival=? WHERE id=?"
            );
            $st->execute([
                $input['category_id'], $input['name'], $slug,
                $input['description'] ?? '',
                $input['price'], $input['stock'],
                $input['image_url'] ?? '',
                $input['is_featured'] ?? 0,
                $input['is_new_arrival'] ?? 0,
                $seg2
            ]);
            ok(['message' => 'Product updated.']);
        }

        // DELETE /api/admin/products/{id}
        if ($method === 'DELETE' && is_numeric($seg2)) {
            $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$seg2]);
            ok(['message' => 'Product deleted.']);
        }
    }

    // ---- ADMIN CUSTOMERS ----
    if ($seg1 === 'customers') {

        // GET /api/admin/customers
        if ($method === 'GET' && $seg2 === '') {
            $st = $pdo->query("
                SELECT u.id, u.name, u.email, u.phone, u.role, u.avatar, u.is_blocked, u.created_at as joined_date, COUNT(o.id) as order_count
                FROM users u
                LEFT JOIN orders o ON u.id = o.customer_id
                WHERE u.role = 'user'
                GROUP BY u.id
                ORDER BY u.id DESC
            ");
            $res = $st->fetchAll();
            foreach ($res as &$c) {
                $c['id'] = intval($c['id']);
                $c['is_blocked'] = (bool)intval($c['is_blocked']);
                $c['order_count'] = intval($c['order_count']);
                if ($c['avatar']) {
                    $c['avatar_url'] = 'http://localhost/php-backend/uploads/profile/' . $c['avatar'];
                } else {
                    $c['avatar_url'] = null;
                }
            }
            ok($res);
        }

        // POST /api/admin/customers/{id}/block  OR  /unblock
        if ($method === 'POST' && is_numeric($seg2) && in_array($seg3, ['block', 'unblock'])) {
            $blocked = ($seg3 === 'block') ? 1 : 0;
            $pdo->prepare("UPDATE users SET is_blocked = ? WHERE id = ? AND role = 'user'")->execute([$blocked, $seg2]);
            ok(['message' => "User $seg3ed."]);
        }

        // DELETE /api/admin/customers/{id}
        if ($method === 'DELETE' && is_numeric($seg2)) {
            $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'user'")->execute([$seg2]);
            ok(['message' => 'Customer deleted.']);
        }
    }

    // ---- ADMIN ORDERS ----
    if ($seg1 === 'orders') {

        // GET /api/admin/orders
        if ($method === 'GET' && $seg2 === '') {
            $st = $pdo->query(
                "SELECT o.*, COALESCE(o.customer_name, u.name, 'Guest') as customer_name FROM orders o
                 LEFT JOIN users u ON o.customer_id = u.id ORDER BY o.id DESC"
            );
            ok($st->fetchAll());
        }

        // PUT /api/admin/orders/{id}/status
        if ($method === 'PUT' && is_numeric($seg2) && $seg3 === 'status') {
            if (empty($input['status'])) err('Status is required.');
            $status = strtolower(trim($input['status']));
            $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$status, $seg2]);
            $pdo->prepare("INSERT INTO order_status_history (order_id, status) VALUES (?,?)")->execute([$seg2, $status]);
            ok(['message' => 'Order status updated.']);
        }
    }

    // ---- ADMIN DISCOUNTS ----
    if ($seg1 === 'discounts') {
        $today = date('Y-m-d');

        // GET /api/admin/discounts
        if ($method === 'GET' && $seg2 === '') {
            $st = $pdo->query(
                "SELECT *, (expires_at < '$today') as is_expired,
                 CASE WHEN usage_limit IS NULL THEN NULL ELSE (usage_limit - used_count) END as remaining_uses
                 FROM discounts ORDER BY id DESC"
            );
            ok(['discounts' => $st->fetchAll()]);
        }

        // POST /api/admin/discounts  — create
        if ($method === 'POST' && $seg2 === '') {
            if (empty($input['code']) || empty($input['type']) || !isset($input['value']) || empty($input['expires_at'])) {
                err('Code, type, value and expires_at are required.');
            }
            $st = $pdo->prepare(
                "INSERT INTO discounts (code, type, value, expires_at, usage_limit, used_count, is_active, who_can_use, one_time_use) VALUES (?,?,?,?,?,0,?,?,?)"
            );
            $st->execute([
                strtoupper($input['code']),
                $input['type'],
                $input['value'],
                $input['expires_at'],
                $input['usage_limit'] ?? null,
                $input['is_active'] ?? 1,
                $input['who_can_use'] ?? 'all',
                $input['one_time_use'] ?? 0
            ]);
            ok(['id' => $pdo->lastInsertId()], 201);
        }

        // POST /api/admin/discounts/{id}/enable  OR  /disable
        if ($method === 'POST' && is_numeric($seg2) && in_array($seg3, ['enable', 'disable'])) {
            $active = ($seg3 === 'enable') ? 1 : 0;
            $pdo->prepare("UPDATE discounts SET is_active = ? WHERE id = ?")->execute([$active, $seg2]);
            ok(['message' => "Discount code $seg3d."]);
        }

        // DELETE /api/admin/discounts/{id}
        if ($method === 'DELETE' && is_numeric($seg2)) {
            $pdo->prepare("DELETE FROM discounts WHERE id = ?")->execute([$seg2]);
            ok(['message' => 'Discount deleted.']);
        }
    }

    // ---- ADMIN VIP CONSULTATIONS ----
    if ($seg1 === 'vip-consultations') {
        if ($method === 'GET' && $seg2 === '') {
            $st = $pdo->query("SELECT * FROM vip_consultations ORDER BY id DESC");
            ok($st->fetchAll());
        }
        if ($method === 'PUT' && is_numeric($seg2) && $seg3 === 'status') {
            if (empty($input['status'])) err('Status is required.');
            $pdo->prepare("UPDATE vip_consultations SET status = ? WHERE id = ?")->execute([$input['status'], $seg2]);
            ok(['message' => 'Status updated.']);
        }
        if ($method === 'DELETE' && is_numeric($seg2)) {
            $pdo->prepare("DELETE FROM vip_consultations WHERE id = ?")->execute([$seg2]);
            ok(['message' => 'Consultation deleted.']);
        }
    }

    // ---- ADMIN CONTACT MESSAGES ----
    if ($seg1 === 'contact-messages') {
        if ($method === 'GET' && $seg2 === '') {
            $st = $pdo->query("SELECT * FROM contact_messages ORDER BY id DESC");
            ok($st->fetchAll());
        }
        if ($method === 'PUT' && is_numeric($seg2) && $seg3 === 'status') {
            if (empty($input['status'])) err('Status is required.');
            $pdo->prepare("UPDATE contact_messages SET status = ? WHERE id = ?")->execute([$input['status'], $seg2]);
            ok(['message' => 'Status updated.']);
        }
        if ($method === 'DELETE' && is_numeric($seg2)) {
            $pdo->prepare("DELETE FROM contact_messages WHERE id = ?")->execute([$seg2]);
            ok(['message' => 'Message deleted.']);
        }
    }

    // ---- ADMIN NEWSLETTER SUBSCRIBERS ----
    if ($seg1 === 'newsletter-subscribers') {
        if ($method === 'GET' && $seg2 === '') {
            $st = $pdo->query("SELECT * FROM newsletters ORDER BY id DESC");
            ok($st->fetchAll());
        }
        if ($method === 'DELETE' && is_numeric($seg2)) {
            $pdo->prepare("DELETE FROM newsletters WHERE id = ?")->execute([$seg2]);
            ok(['message' => 'Subscriber deleted.']);
        }
    }

    err('Admin endpoint not found.', 404);
}

// ============================================
// NEWSLETTER: /api/newsletter
// ============================================
if ($seg0 === 'newsletter' && $method === 'POST') {
    if (empty($input['email'])) {
        err('Email address is required.');
    }
    try {
        $st = $pdo->prepare("INSERT INTO newsletters (email) VALUES (?)");
        $st->execute([$input['email']]);
        ok(['message' => 'Subscribed successfully!']);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            ok(['message' => 'Subscribed successfully!']);
        }
        err('Subscription failed: ' . $e->getMessage());
    }
}

// ============================================
// CONTACT: /api/contact
// ============================================
if ($seg0 === 'contact' && $method === 'POST') {
    if (empty($input['name']) || empty($input['email']) || empty($input['message']) || empty($input['subject'])) {
        err('Name, email, subject and message are required.');
    }
    $phone = $input['phone'] ?? null;
    $subscribe_newsletter = isset($input['subscribe_newsletter']) && $input['subscribe_newsletter'] ? 1 : 0;
    
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("
            INSERT INTO contact_messages (name, email, phone, subject, message, subscribed_newsletter)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $st->execute([
            $input['name'],
            $input['email'],
            $phone,
            $input['subject'],
            $input['message'],
            $subscribe_newsletter
        ]);
        
        if ($subscribe_newsletter) {
            $st_news = $pdo->prepare("INSERT IGNORE INTO newsletters (email) VALUES (?)");
            $st_news->execute([$input['email']]);
        }
        
        $pdo->commit();
        ok(['message' => 'Your message has been sent successfully!']);
    } catch (Exception $e) {
        $pdo->rollBack();
        err('Failed to send message: ' . $e->getMessage());
    }
}

// ============================================
// VIP CONSULTATIONS: /api/vip-consultations
// ============================================
if ($seg0 === 'vip-consultations' && $method === 'POST') {
    if (empty($input['guest_name']) || empty($input['email']) || empty($input['requested_service'])) {
        err('Guest name, email, and requested service are required.');
    }
    $requested_date = $input['requested_date'] ?? null;
    try {
        $st = $pdo->prepare("
            INSERT INTO vip_consultations (guest_name, email, requested_service, requested_date, status)
            VALUES (?, ?, ?, ?, 'Pending')
        ");
        $st->execute([
            $input['guest_name'],
            $input['email'],
            $input['requested_service'],
            $requested_date
        ]);
        ok(['message' => 'Request received! We\'ll contact you soon.']);
    } catch (Exception $e) {
        err('Failed to save VIP consultation request: ' . $e->getMessage());
    }
}

// ============================================
// SEED ROUTE: /api/seed
// ============================================
if ($seg0 === 'seed') {
    try {
        // Truncate all tables
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
        foreach (['order_items','orders','discounts','products','categories','addresses','order_status_history','users','newsletters','contact_messages','vip_consultations'] as $t) {
            $pdo->exec("TRUNCATE TABLE $t;");
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1;");

        // Users
        $pdo->prepare("INSERT INTO users (name,email,password,role,is_blocked) VALUES (?,?,?,?,0)")
            ->execute(['Admin User','admin@lunar.com', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);
        $pdo->prepare("INSERT INTO users (name,email,password,role,is_blocked) VALUES (?,?,?,?,0)")
            ->execute(['Customer User','user@lunar.com', password_hash('user123', PASSWORD_DEFAULT), 'user']);
        $pdo->prepare("INSERT INTO users (name,email,password,role,is_blocked) VALUES (?,?,?,?,0)")
            ->execute(['Jane Doe','jane@lunar.com', password_hash('user123', PASSWORD_DEFAULT), 'user']);

        // Categories
        $cats = [
            [1,'Clothing','clothing','Meticulously curated silk evening dresses, hand-tailored cashmeres, and premium couture.','https://images.unsplash.com/photo-1490481651871-ab68de25d43d?w=800&auto=format&fit=crop&q=80'],
            [2,'Handbags','handbags','Handcrafted top-grain Italian leather bags and pristine exotic clutches.','https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=800&auto=format&fit=crop&q=80'],
            [3,'Jewelry','jewelry','Pristine ethically sourced diamonds, raw platinum settings, and timeless heirloom jewels.','https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=800&auto=format&fit=crop&q=80'],
            [4,'Shoes','shoes','Fine bespoke footwear, luxury calfskin loafers, and elegant pumps.','https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&auto=format&fit=crop&q=80'],
            [5,'Perfumes','perfumes','Olfactory masterpieces infused with royal oud, ambergris, and neroli blossoms.','https://images.unsplash.com/photo-1541643600914-78b084683601?w=800&auto=format&fit=crop&q=80'],
            [6,'Accessories','accessories','Legendary mechanical timepieces, silk pocket squares, and premium sunglasses.','https://images.unsplash.com/photo-1522312346375-d1a52e2b99b3?w=800&auto=format&fit=crop&q=80'],
        ];
        $sc = $pdo->prepare("INSERT INTO categories (id,name,slug,description,image_url) VALUES (?,?,?,?,?)");
        foreach ($cats as $c) $sc->execute($c);

        // Products — 10 per category = 60 products
        $products = [
            // Clothing
            [1,'Midnight Silk Gown','midnight-silk-gown','Flowing midnight-blue pure silk evening gown with hand-sewn crystal embellishments.',2499.99,15,'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?w=800&auto=format&fit=crop&q=80',1,1],
            [1,'Ivory Cashmere Wrap','ivory-cashmere-wrap','Ultra-soft Mongolian cashmere wrap in pristine ivory with silk lining.',1899.99,20,'https://images.unsplash.com/photo-1539109136881-3be0616acf4b?w=800&auto=format&fit=crop&q=80',1,0],
            [1,'Emerald Velvet Blazer','emerald-velvet-blazer','Rich emerald velvet blazer with gold button details and satin lapels.',1299.99,12,'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=800&auto=format&fit=crop&q=80',0,1],
            [1,'Champagne Satin Dress','champagne-satin-dress','Bias-cut champagne satin dress perfect for cocktail events.',1599.99,18,'https://images.unsplash.com/photo-1496747611176-843222e1e57c?w=800&auto=format&fit=crop&q=80',0,0],
            [1,'Obsidian Wool Coat','obsidian-wool-coat','Double-breasted obsidian black Italian wool overcoat with horn buttons.',2299.99,8,'https://images.unsplash.com/photo-1434389677669-e08b4cac3105?w=800&auto=format&fit=crop&q=80',1,0],
            [1,'Ruby Red Evening Cape','ruby-red-evening-cape','Dramatic floor-length cape in deep ruby red with fox-fur trim.',3499.99,5,'https://images.unsplash.com/photo-1551232864-3f0890e580d9?w=800&auto=format&fit=crop&q=80',0,1],
            [1,'Pearl White Tuxedo','pearl-white-tuxedo','Custom-fit pearl white tuxedo with black satin piping.',1899.99,10,'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800&auto=format&fit=crop&q=80',0,0],
            [1,'Cobalt Linen Suit','cobalt-linen-suit','Lightweight cobalt blue Italian linen summer suit.',1449.99,14,'https://images.unsplash.com/photo-1583744946564-b52ac1c389c8?w=800&auto=format&fit=crop&q=80',0,1],
            [1,'Golden Thread Kurta','golden-thread-kurta','Hand-embroidered golden thread kurta on finest cotton.',899.99,22,'https://images.unsplash.com/photo-1596755094514-f87e34085b2c?w=800&auto=format&fit=crop&q=80',0,0],
            [1,'Onyx Leather Jacket','onyx-leather-jacket','Butter-soft onyx lambskin leather jacket with quilted shoulders.',2199.99,7,'https://images.unsplash.com/photo-1562157873-818bc0726f68?w=800&auto=format&fit=crop&q=80',1,0],
            // Handbags
            [2,'Milano Leather Tote','milano-leather-tote','Full-grain Italian calfskin tote with suede interior lining.',1899.99,20,'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=800&auto=format&fit=crop&q=80',1,1],
            [2,'Crystal Evening Clutch','crystal-evening-clutch','Hand-set Swarovski crystal evening clutch with chain strap.',1299.99,15,'https://images.unsplash.com/photo-1566150905458-1bf1fc113f0d?w=800&auto=format&fit=crop&q=80',1,0],
            [2,'Bordeaux Crossbody','bordeaux-crossbody','Bordeaux pebbled leather crossbody with adjustable gold chain.',999.99,25,'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=800&auto=format&fit=crop&q=80',0,1],
            [2,'Ivory Python Clutch','ivory-python-clutch','Genuine ivory-tone python clutch with magnetic clasp.',2499.99,6,'https://images.unsplash.com/photo-1590874103328-eac38a683ce7?w=800&auto=format&fit=crop&q=80',0,0],
            [2,'Noir Structured Bag','noir-structured-bag','Architectural noir leather structured handbag with brass hardware.',1699.99,12,'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=800&auto=format&fit=crop&q=80',1,0],
            [2,'Caramel Saddle Bag','caramel-saddle-bag','Artisan-crafted caramel saddle bag with hand-stitched details.',1199.99,18,'https://images.unsplash.com/photo-1591561954557-26941169b49e?w=800&auto=format&fit=crop&q=80',0,1],
            [2,'Silver Chain Minaudiere','silver-chain-minaudiere','Art Deco silver-plated minaudière with silk tassel.',899.99,10,'https://images.unsplash.com/photo-1575032617751-6ddec2089882?w=800&auto=format&fit=crop&q=80',0,0],
            [2,'Forest Suede Hobo','forest-suede-hobo','Deep forest green Italian suede hobo bag.',1399.99,14,'https://images.unsplash.com/photo-1594223274512-ad4803739b7c?w=800&auto=format&fit=crop&q=80',0,0],
            [2,'Blush Quilted Flap','blush-quilted-flap','Blush quilted lambskin flap bag with turn-lock closure.',1599.99,9,'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=800&auto=format&fit=crop&q=80',0,1],
            [2,'Ebony Briefcase','ebony-briefcase','Premium ebony full-grain leather executive briefcase.',2199.99,7,'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=800&auto=format&fit=crop&q=80',1,0],
            // Jewelry
            [3,'Celestial Diamond Ring','celestial-diamond-ring','2-carat VVS1 brilliant-cut diamond in platinum cathedral setting.',12999.99,5,'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=800&auto=format&fit=crop&q=80',1,1],
            [3,'Sapphire Eternity Band','sapphire-eternity-band','Channel-set Ceylon sapphire eternity band in 18k white gold.',4999.99,8,'https://images.unsplash.com/photo-1605100804763-247f67b3557e?w=800&auto=format&fit=crop&q=80',1,0],
            [3,'Pearl Cascade Necklace','pearl-cascade-necklace','Japanese Akoya pearl cascade necklace with diamond clasp.',3499.99,10,'https://images.unsplash.com/photo-1515562141589-67f0d93bbb48?w=800&auto=format&fit=crop&q=80',0,1],
            [3,'Emerald Drop Earrings','emerald-drop-earrings','Colombian emerald drop earrings with pavé diamond halos.',6999.99,6,'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?w=800&auto=format&fit=crop&q=80',0,0],
            [3,'Gold Chain Bracelet','gold-chain-bracelet','Hand-forged 22k gold chunky chain bracelet.',2999.99,12,'https://images.unsplash.com/photo-1611591437281-460bfbe1220a?w=800&auto=format&fit=crop&q=80',1,0],
            [3,'Ruby Heart Pendant','ruby-heart-pendant','Burmese ruby heart pendant on 18k rose gold chain.',5499.99,7,'https://images.unsplash.com/photo-1602751584552-8ba73aad10e1?w=800&auto=format&fit=crop&q=80',0,1],
            [3,'Platinum Cuff Links','platinum-cuff-links','Solid platinum cuff links with black onyx inlay.',1899.99,15,'https://images.unsplash.com/photo-1573408301185-9146fe634ad0?w=800&auto=format&fit=crop&q=80',0,0],
            [3,'Tanzanite Cocktail Ring','tanzanite-cocktail-ring','Exceptional 5ct tanzanite cocktail ring with diamond shoulders.',8999.99,4,'https://images.unsplash.com/photo-1603561591411-07134e71a2a9?w=800&auto=format&fit=crop&q=80',0,0],
            [3,'Diamond Tennis Bracelet','diamond-tennis-bracelet','5-carat total diamond tennis bracelet in 18k white gold.',7999.99,6,'https://images.unsplash.com/photo-1611591437281-460bfbe1220a?w=800&auto=format&fit=crop&q=80',1,1],
            [3,'Opal Dangle Earrings','opal-dangle-earrings','Australian fire opal dangle earrings with diamond accents.',3299.99,9,'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?w=800&auto=format&fit=crop&q=80',0,0],
            // Shoes
            [4,'Venetian Calfskin Loafers','venetian-calfskin-loafers','Hand-lasted calfskin penny loafers with leather sole.',899.99,20,'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&auto=format&fit=crop&q=80',1,1],
            [4,'Scarlet Stiletto Pumps','scarlet-stiletto-pumps','Patent leather scarlet stiletto pumps with 100mm heel.',749.99,15,'https://images.unsplash.com/photo-1596703263926-eb0762ee17e4?w=800&auto=format&fit=crop&q=80',1,0],
            [4,'Midnight Oxford Brogues','midnight-oxford-brogues','Full-brogue Oxford shoes in midnight navy Cordovan leather.',1199.99,12,'https://images.unsplash.com/photo-1614252369475-531eba835eb1?w=800&auto=format&fit=crop&q=80',0,1],
            [4,'Champagne Slingbacks','champagne-slingbacks','Champagne satin slingback heels with crystal buckle.',699.99,18,'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&auto=format&fit=crop&q=80',0,0],
            [4,'Cognac Chelsea Boots','cognac-chelsea-boots','Premium cognac suede Chelsea boots with Goodyear welt.',999.99,10,'https://images.unsplash.com/photo-1608256246200-53e635b5b65f?w=800&auto=format&fit=crop&q=80',1,0],
            [4,'Ivory Bridal Flats','ivory-bridal-flats','Hand-beaded ivory silk bridal ballet flats.',599.99,22,'https://images.unsplash.com/photo-1596703263926-eb0762ee17e4?w=800&auto=format&fit=crop&q=80',0,1],
            [4,'Obsidian Monk Straps','obsidian-monk-straps','Double monk strap shoes in obsidian black box calf.',1099.99,8,'https://images.unsplash.com/photo-1614252369475-531eba835eb1?w=800&auto=format&fit=crop&q=80',0,0],
            [4,'Rose Gold Sandals','rose-gold-sandals','Metallic rose gold strappy heeled sandals.',649.99,16,'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&auto=format&fit=crop&q=80',0,0],
            [4,'Mahogany Riding Boots','mahogany-riding-boots','Tall mahogany leather riding boots with brass zippers.',1499.99,6,'https://images.unsplash.com/photo-1608256246200-53e635b5b65f?w=800&auto=format&fit=crop&q=80',0,1],
            [4,'Silver Mule Slides','silver-mule-slides','Hammered silver leather mule slides with padded insole.',549.99,20,'https://images.unsplash.com/photo-1596703263926-eb0762ee17e4?w=800&auto=format&fit=crop&q=80',1,0],
            // Perfumes
            [5,'Royal Oud Elixir','royal-oud-elixir','Rare aged oud wood blended with Damask rose and saffron.',899.99,25,'https://images.unsplash.com/photo-1541643600914-78b084683601?w=800&auto=format&fit=crop&q=80',1,1],
            [5,'Neroli Blossom','neroli-blossom','Fresh Tunisian neroli with bergamot and white musk.',449.99,30,'https://images.unsplash.com/photo-1592945403244-b3fbafd7f539?w=800&auto=format&fit=crop&q=80',1,0],
            [5,'Midnight Amber','midnight-amber','Rich amber resin with vanilla, tonka bean, and labdanum.',599.99,20,'https://images.unsplash.com/photo-1587017539504-67cfbddac569?w=800&auto=format&fit=crop&q=80',0,1],
            [5,'Velvet Orchid','velvet-orchid','Exotic orchid petals with dark chocolate and patchouli.',749.99,15,'https://images.unsplash.com/photo-1541643600914-78b084683601?w=800&auto=format&fit=crop&q=80',0,0],
            [5,'Citrus Royale','citrus-royale','Sparkling Calabrian bergamot with yuzu and cedarwood.',399.99,35,'https://images.unsplash.com/photo-1592945403244-b3fbafd7f539?w=800&auto=format&fit=crop&q=80',1,0],
            [5,'Iris Absolute','iris-absolute','Precious Florentine iris root with suede and ambrette.',1299.99,8,'https://images.unsplash.com/photo-1587017539504-67cfbddac569?w=800&auto=format&fit=crop&q=80',0,1],
            [5,'Sandalwood Dream','sandalwood-dream','Mysore sandalwood with cardamom and creamy coconut.',549.99,22,'https://images.unsplash.com/photo-1541643600914-78b084683601?w=800&auto=format&fit=crop&q=80',0,0],
            [5,'Jasmine Absolute','jasmine-absolute','Indian jasmine sambac with tuberose and ylang ylang.',699.99,18,'https://images.unsplash.com/photo-1592945403244-b3fbafd7f539?w=800&auto=format&fit=crop&q=80',0,0],
            [5,'Leather Oud Intense','leather-oud-intense','Dark leather accord with Laotian oud and smoky incense.',999.99,10,'https://images.unsplash.com/photo-1587017539504-67cfbddac569?w=800&auto=format&fit=crop&q=80',1,1],
            [5,'Rose de Mai','rose-de-mai','Grasse rose absolute with peony petals and pink pepper.',849.99,12,'https://images.unsplash.com/photo-1541643600914-78b084683601?w=800&auto=format&fit=crop&q=80',0,0],
            // Accessories
            [6,'Heritage Chronograph','heritage-chronograph','Swiss-made automatic chronograph with sapphire crystal.',4999.99,10,'https://images.unsplash.com/photo-1522312346375-d1a52e2b99b3?w=800&auto=format&fit=crop&q=80',1,1],
            [6,'Aviator Sunglasses','aviator-sunglasses','Titanium frame aviator sunglasses with polarized lenses.',599.99,25,'https://images.unsplash.com/photo-1511499767150-a48a237f0083?w=800&auto=format&fit=crop&q=80',1,0],
            [6,'Silk Pocket Square Set','silk-pocket-square-set','Set of 4 hand-rolled Italian silk pocket squares.',299.99,30,'https://images.unsplash.com/photo-1522312346375-d1a52e2b99b3?w=800&auto=format&fit=crop&q=80',0,1],
            [6,'Alligator Belt','alligator-belt','Genuine Louisiana alligator belt with palladium buckle.',899.99,12,'https://images.unsplash.com/photo-1511499767150-a48a237f0083?w=800&auto=format&fit=crop&q=80',0,0],
            [6,'Cashmere Scarf','cashmere-scarf','Mongolian cashmere scarf in herringbone weave.',449.99,20,'https://images.unsplash.com/photo-1522312346375-d1a52e2b99b3?w=800&auto=format&fit=crop&q=80',1,0],
            [6,'Titanium Card Holder','titanium-card-holder','Brushed titanium minimalist card holder with RFID blocking.',199.99,40,'https://images.unsplash.com/photo-1511499767150-a48a237f0083?w=800&auto=format&fit=crop&q=80',0,1],
            [6,'Gold Tie Bar','gold-tie-bar','18k gold vermeil tie bar with brushed finish.',349.99,18,'https://images.unsplash.com/photo-1522312346375-d1a52e2b99b3?w=800&auto=format&fit=crop&q=80',0,0],
            [6,'Leather Travel Case','leather-travel-case','Full-grain leather watch travel case for 4 timepieces.',699.99,15,'https://images.unsplash.com/photo-1511499767150-a48a237f0083?w=800&auto=format&fit=crop&q=80',0,0],
            [6,'Carbon Fiber Wallet','carbon-fiber-wallet','Genuine carbon fiber slim bifold wallet with RFID protection.',279.99,28,'https://images.unsplash.com/photo-1522312346375-d1a52e2b99b3?w=800&auto=format&fit=crop&q=80',0,1],
            [6,'Sapphire Cufflinks','sapphire-cufflinks','Sterling silver cufflinks with cabochon blue sapphires.',799.99,10,'https://images.unsplash.com/photo-1511499767150-a48a237f0083?w=800&auto=format&fit=crop&q=80',1,0],
        ];
        $sp = $pdo->prepare("INSERT INTO products (category_id, name, slug, description, price, stock, image_url, is_featured, is_new_arrival) VALUES (?,?,?,?,?,?,?,?,?)");
        foreach ($products as $p) $sp->execute($p);

        // Discounts
        $pdo->exec("INSERT INTO discounts (code, type, value, expires_at, usage_limit, used_count, is_active, who_can_use, one_time_use) VALUES ('WELCOME10', 'percent', 10.00, '2030-12-31', 1000, 0, 1, 'new', 1)");
        $pdo->exec("INSERT INTO discounts (code, type, value, expires_at, usage_limit, used_count, is_active, who_can_use, one_time_use) VALUES ('SUMMER25', 'percent', 25.00, '2030-12-31', 1000, 0, 1, 'existing', 1)");
        $pdo->exec("INSERT INTO discounts (code, type, value, expires_at, usage_limit, used_count, is_active, who_can_use, one_time_use) VALUES ('LUXE50', 'fixed', 50.00, '2030-12-31', 500, 0, 1, 'all', 0)");

        ok(['message' => 'Database seeded successfully! Created 3 users, 6 categories, 60 products, and 3 discount codes.']);
    } catch (Exception $e) {
        err('Seeding failed: ' . $e->getMessage(), 500);
    }
}

// ============================================
// FALLBACK 404
// ============================================
err('Endpoint not found: /' . implode('/', array_filter([$seg0, $seg1, $seg2])), 404);