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
$host = $_ENV['DB_HOST'] ?? 'localhost';
$db   = $_ENV['DB_NAME'] ?? 'lunar_store';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // Self-healing database check & columns creation
    $columns = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('phone', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL AFTER email");
    }
    if (!in_array('avatar', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL AFTER role");
    }
    if (!in_array('bio', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN bio TEXT DEFAULT NULL AFTER avatar");
    }

    // Self-healing check for products table
    $prod_cols = $pdo->query("DESCRIBE products")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('is_new_arrival', $prod_cols)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN is_new_arrival TINYINT(1) DEFAULT 0 AFTER is_featured");
    }

    // Self-healing check for discounts table
    $disc_cols = $pdo->query("DESCRIBE discounts")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('who_can_use', $disc_cols)) {
        $pdo->exec("ALTER TABLE discounts ADD COLUMN who_can_use VARCHAR(20) DEFAULT 'all'");
    }
    if (!in_array('one_time_use', $disc_cols)) {
        $pdo->exec("ALTER TABLE discounts ADD COLUMN one_time_use TINYINT(1) DEFAULT 0");
    }

    // Self-healing check for orders table
    $order_cols = $pdo->query("DESCRIBE orders")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('discount_code', $order_cols)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN discount_code VARCHAR(50) DEFAULT NULL");
    }
    if (!in_array('notifications', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN notifications TEXT DEFAULT NULL AFTER bio");
    }

    // Self-healing table creation for newsletters and contact messages
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
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Check contact_messages columns for status
    $contact_cols = $pdo->query("DESCRIBE contact_messages")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('status', $contact_cols)) {
        $pdo->exec("ALTER TABLE contact_messages ADD COLUMN status ENUM('Unread', 'Read', 'Replied') DEFAULT 'Unread' AFTER subscribed_newsletter");
    }

    // Create vip_consultations table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS vip_consultations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            guest_name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            requested_service VARCHAR(255) NOT NULL,
            requested_date DATE DEFAULT NULL,
            status ENUM('Pending', 'Contacted', 'Completed') DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Ensure WELCOME10 exists in discounts table
    $ck_welcome = $pdo->prepare("SELECT id FROM discounts WHERE code = 'WELCOME10'");
    $ck_welcome->execute();
    if (!$ck_welcome->fetch()) {
        $pdo->exec("INSERT INTO discounts (code, type, value, expires_at, usage_limit, used_count, is_active, who_can_use, one_time_use) VALUES ('WELCOME10', 'percent', 10.00, '2030-12-31', 1000, 0, 1, 'new', 1)");
    }

    // Ensure SUMMER25 exists in discounts table
    $ck_summer = $pdo->prepare("SELECT id FROM discounts WHERE code = 'SUMMER25'");
    $ck_summer->execute();
    if (!$ck_summer->fetch()) {
        $pdo->exec("INSERT INTO discounts (code, type, value, expires_at, usage_limit, used_count, is_active, who_can_use, one_time_use) VALUES ('SUMMER25', 'percent', 25.00, '2030-12-31', 1000, 0, 1, 'existing', 1)");
    }

    // Ensure Test Existing user exists
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
// FALLBACK 404
// ============================================
err('Endpoint not found: /' . implode('/', array_filter([$seg0, $seg1, $seg2])), 404);