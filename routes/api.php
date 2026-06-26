<?php

// ===================== ADMIN DASHBOARD ENDPOINT =====================
// Add this route to your existing routes/api.php file

$app->get('/admin/dashboard', function ($request, $response) {
    $db = getDbConnection();
    
    try {
        // 1. Get summary stats
        $summary = getDashboardSummary($db);
        
        // 2. Get order status breakdown
        $orderStatus = getOrderStatusBreakdown($db);
        
        // 3. Get inventory stats
        $inventory = getInventoryStats($db);
        
        // 4. Get best sellers
        $bestSellers = getBestSellers($db);
        
        // 5. Get recent orders
        $recentOrders = getRecentOrders($db);
        
        return jsonResponse($response, 200, [
            'status' => 'success',
            'data' => [
                'summary' => $summary,
                'order_status' => $orderStatus,
                'inventory' => $inventory,
                'best_sellers' => $bestSellers,
                'recent_orders' => $recentOrders,
            ]
        ]);
        
    } catch (Exception $e) {
        return jsonResponse($response, 500, [
            'status' => 'error',
            'message' => 'Failed to fetch dashboard data: ' . $e->getMessage()
        ]);
    }
});

// ===================== PRODUCTS ENDPOINT =====================
$app->get('/products', function ($request, $response) {
    $db = getDbConnection();
    try {
        $stmt = $db->query("SELECT * FROM products ORDER BY id DESC");
        $products = $stmt->fetchAll();
        
        $baseUrl = 'http://localhost/php-backend/uploads/';
        foreach ($products as &$product) {
            if (isset($product['image_url'])) {
                $product['image'] = $product['image_url'];
            }
            if (!empty($product['image']) && !str_starts_with($product['image'], 'http')) {
                $product['image'] = $baseUrl . $product['image'];
            }
        }
        
        return jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $products
        ]);
    } catch (Exception $e) {
        return jsonResponse($response, 500, [
            'status' => 'error',
            'message' => 'Failed to fetch products: ' . $e->getMessage()
        ]);
    }
});

// ===================== HELPER FUNCTIONS =====================

function getDashboardSummary($db) {
    $today = date('Y-m-d');
    $firstDayOfMonth = date('Y-m-01');
    
    // Total orders (excluding cancelled)
    $stmt = $db->query("SELECT COUNT(*) as count FROM orders WHERE status != 'cancelled'");
    $totalOrders = $stmt->fetch()['count'] ?? 0;
    
    // Total revenue
    $stmt = $db->query("SELECT SUM(total_amount) as total FROM orders WHERE status != 'cancelled'");
    $totalRevenue = $stmt->fetch()['total'] ?? 0;
    
    // Total users
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $totalUsers = $stmt->fetch()['count'] ?? 0;
    
    // Total products
    $stmt = $db->query("SELECT COUNT(*) as count FROM products");
    $totalProducts = $stmt->fetch()['count'] ?? 0;
    
    // Today's orders
    $stmt = $db->query("SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = '$today' AND status != 'cancelled'");
    $todayOrders = $stmt->fetch()['count'] ?? 0;
    
    // Today's revenue
    $stmt = $db->query("SELECT SUM(total_amount) as total FROM orders WHERE DATE(created_at) = '$today' AND status != 'cancelled'");
    $todayRevenue = $stmt->fetch()['total'] ?? 0;
    
    // Month orders
    $stmt = $db->query("SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) >= '$firstDayOfMonth' AND status != 'cancelled'");
    $monthOrders = $stmt->fetch()['count'] ?? 0;
    
    // Month revenue
    $stmt = $db->query("SELECT SUM(total_amount) as total FROM orders WHERE DATE(created_at) >= '$firstDayOfMonth' AND status != 'cancelled'");
    $monthRevenue = $stmt->fetch()['total'] ?? 0;
    
    return [
        'total_orders' => (int)$totalOrders,
        'total_revenue' => (float)$totalRevenue,
        'total_users' => (int)$totalUsers,
        'total_products' => (int)$totalProducts,
        'today_orders' => (int)$todayOrders,
        'today_revenue' => (float)$todayRevenue,
        'month_orders' => (int)$monthOrders,
        'month_revenue' => (float)$monthRevenue,
    ];
}

function getOrderStatusBreakdown($db) {
    $statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    $result = [];
    
    foreach ($statuses as $status) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM orders WHERE status = ?");
        $stmt->execute([$status]);
        $result[$status] = (int)($stmt->fetch()['count'] ?? 0);
    }
    
    return $result;
}

function getInventoryStats($db) {
    // Total products
    $stmt = $db->query("SELECT COUNT(*) as count FROM products");
    $totalProducts = $stmt->fetch()['count'] ?? 0;
    
    // Low stock products
    $stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE stock <= 5 AND stock > 0");
    $lowStockCount = $stmt->fetch()['count'] ?? 0;
    
    // Out of stock
    $stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE stock = 0");
    $outOfStockCount = $stmt->fetch()['count'] ?? 0;
    
    // Low stock product details
    $stmt = $db->query("SELECT id, name, stock, price FROM products WHERE stock <= 5 AND stock > 0 ORDER BY stock ASC LIMIT 10");
    $lowStockProducts = $stmt->fetchAll();
    
    return [
        'total_products' => (int)$totalProducts,
        'low_stock_count' => (int)$lowStockCount,
        'out_of_stock_count' => (int)$outOfStockCount,
        'low_stock_products' => $lowStockProducts,
    ];
}

function getBestSellers($db) {
    $stmt = $db->query("
        SELECT 
            p.id as product_id,
            p.name as product_name,
            SUM(oi.quantity) as total_sold,
            SUM(oi.quantity * oi.price) as total_revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.status != 'cancelled'
        GROUP BY oi.product_id
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    return $stmt->fetchAll();
}

function getRecentOrders($db) {
    $stmt = $db->query("
        SELECT 
            id,
            CONCAT('LNR-', id) as order_number,
            customer_name,
            total_amount,
            status,
            created_at as date
        FROM orders 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    return $stmt->fetchAll();
}

// ===================== JSON RESPONSE HELPER =====================

function jsonResponse($response, $statusCode, $data) {
    $response->getBody()->write(json_encode($data));
    return $response
        ->withStatus($statusCode)
        ->withHeader('Content-Type', 'application/json');
}

// ===================== DATABASE CONNECTION =====================

function getDbConnection() {
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $dbname = $_ENV['DB_NAME'] ?? 'lunar_store';
    $user = $_ENV['DB_USER'] ?? 'root';
    $pass = $_ENV['DB_PASSWORD'] ?? '';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception('Database connection failed: ' . $e->getMessage());
    }
}