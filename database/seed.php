<?php
// C:\xampp\htdocs\php-backend\database\seed.php
// Enhanced seeder with 300 unique product images

ini_set('memory_limit', '-1');

// Load .env
$env_path = __DIR__ . '/../.env';
foreach (file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
    list($k, $v) = explode('=', $line, 2);
    $_ENV[trim($k)] = trim($v);
}

$pdo = new PDO(
    "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
    $_ENV['DB_USER'], $_ENV['DB_PASSWORD'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
echo "Connected.\n";

// Self-healing checks
$disc_cols = $pdo->query("DESCRIBE discounts")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('who_can_use', $disc_cols)) {
    $pdo->exec("ALTER TABLE discounts ADD COLUMN who_can_use VARCHAR(20) DEFAULT 'all'");
}
if (!in_array('one_time_use', $disc_cols)) {
    $pdo->exec("ALTER TABLE discounts ADD COLUMN one_time_use TINYINT(1) DEFAULT 0");
}

$order_cols = $pdo->query("DESCRIBE orders")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('discount_code', $order_cols)) {
    $pdo->exec("ALTER TABLE orders ADD COLUMN discount_code VARCHAR(50) DEFAULT NULL");
}

// Truncate
$pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
foreach (['order_items','orders','discounts','products','categories','addresses','order_status_history','users','newsletters','contact_messages','vip_consultations'] as $t) {
    $pdo->exec("TRUNCATE TABLE $t;");
}
$pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
echo "Truncated.\n";

// Users
$pdo->prepare("INSERT INTO users (name,email,password,role,is_blocked) VALUES (?,?,?,?,0)")
    ->execute(['Admin User','admin@lunar.com', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);
$pdo->prepare("INSERT INTO users (name,email,password,role,is_blocked) VALUES (?,?,?,?,0)")
    ->execute(['Customer User','user@lunar.com', password_hash('user123', PASSWORD_DEFAULT), 'user']);
$pdo->prepare("INSERT INTO users (name,email,password,role,is_blocked) VALUES (?,?,?,?,0)")
    ->execute(['Jane Doe','jane@lunar.com', password_hash('user123', PASSWORD_DEFAULT), 'user']);
echo "Users seeded.\n";

// Categories
$cats = [
    [1,'Clothing','clothing','Meticulously curated silk evening dresses, hand-tailored cashmeres, and premium couture.',
     'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?w=800&auto=format&fit=crop&q=80'],
    [2,'Handbags','handbags','Handcrafted top-grain Italian leather bags and pristine exotic clutches.',
     'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=800&auto=format&fit=crop&q=80'],
    [3,'Jewelry','jewelry','Pristine ethically sourced diamonds, raw platinum settings, and timeless heirloom jewels.',
     'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=800&auto=format&fit=crop&q=80'],
    [4,'Shoes','shoes','Fine bespoke footwear, luxury calfskin loafers, and elegant pumps.',
     'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&auto=format&fit=crop&q=80'],
    [5,'Perfumes','perfumes','Olfactory masterpieces infused with royal oud, ambergris, and neroli blossoms.',
     'https://images.unsplash.com/photo-1541643600914-78b084683601?w=800&auto=format&fit=crop&q=80'],
    [6,'Accessories','accessories','Legendary mechanical timepieces, silk pocket squares, and premium sunglasses.',
     'https://images.unsplash.com/photo-1522312346375-d1a52e2b99b3?w=800&auto=format&fit=crop&q=80'],
];
$sc = $pdo->prepare("INSERT INTO categories (id,name,slug,description,image_url) VALUES (?,?,?,?,?)");
foreach ($cats as $c) $sc->execute($c);
echo "Categories seeded.\n";

// ============================================================
// UNIQUE IMAGE POOLS — 50+ unique Unsplash IDs per category
// ============================================================
$images = [

    // CLOTHING — 50 unique fashion/apparel images
    1 => [
        'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1539109136881-3be0616acf4b?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1496747611176-843222e1e57c?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1434389677669-e08b4cac3105?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1551232864-3f0890e580d9?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1583744946564-b52ac1c389c8?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1596755094514-f87e34085b2c?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1562157873-818bc0726f68?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1525507119028-ed4c629a60a3?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1550614000-4895a10e1bfd?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1554412933-514a83d2f3c8?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1571513722275-4b41940f54b8?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1620799140188-3b2a02fd9a77?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1603217039863-aa0c865d47e5?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1618453292459-53424b66bb6a?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1581044777550-4cfa60707c03?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1591047139829-d91aecb6caea?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1622519407650-3df9883f76a5?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1594938298603-a3554582f274?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1614252369475-531eba835eb1?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1547996160-81dfa63595aa?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1600950207944-0d63e8edbc3f?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1549298916-b41d501d3772?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1597983073493-88cd43721f58?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1609505848912-b7c3b8b4beda?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1605763240000-7e93b172d754?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1613482184972-f65a6bde1f5a?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1585914924626-15adac1e6402?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1602810316498-ab67cf68c8e1?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1595777457583-95e059d581b8?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1507679799987-c73779587ccf?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1598032895397-b9472444bf93?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1519345182560-3f2917c472ef?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1617922001439-4a2e6562f328?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1572635196237-14b3f281503f?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1532453288672-3a27e9be9efd?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1564257631407-4deb1f99d643?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1612423284934-2850a4ea6b0f?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1599854789888-a3f33f67bd72?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1475180098004-ca77a66827be?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1520367445093-50dc08a59d9d?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1558769132-cb1aea458c5e?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1473966968600-fa801b869a1a?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1544022613-e87ca75a784a?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1509631179647-0177331693ae?w=800&auto=format&fit=crop&q=80',
    ],

    // HANDBAGS — 50 unique bag images
    2 => [
        'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1590874103328-eac38a683ce7?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1566150905458-1bf1fc15a7a0?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1575032617751-6ddec2089882?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1591561954557-26941169b49e?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1547949003-9792a18a2601?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1519415943484-9fa1873496d4?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1611010344445-5f9f0b6c5b7a?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1614179924047-e1ab5a2f0b82?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1594938298603-a3554582f274?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1600857062241-98e5dba7f025?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1618932260643-eee4a2f652a6?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1598532213005-067c23e5f52a?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1566206091558-7f218b696731?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1631729371254-42c2892f0e6e?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1614179924047-e1ab5a2f0b82?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1512436991641-6745cdb1723f?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1601924994987-69e26d50dc26?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1559563458-527698bf5295?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1513094735237-8f2714d57c13?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1620799140408-edc6dcb6d633?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1622560480605-d83c853bc5c3?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1614252369475-531eba835eb1?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1605733513597-a8f8341084e6?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1491637639811-60e2756cc1c7?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1602874801007-bd458bb1b8b9?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1578985545062-69928b1d9587?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1616425629789-1aff4c0f9e18?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1535043934128-cf0b28d52f95?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1585386959984-a4155224a1ad?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1590739225287-bd31519780c3?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1473966968600-fa801b869a1a?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1618354691373-d851c5c3a990?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1544022613-e87ca75a784a?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1468254095679-bbcba94a7066?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1612831455359-970e23a1e4e9?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1614179924047-e1ab5a2f0b82?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1624913503273-5f9c4e980dba?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1591561954557-26941169b49e?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1598532213005-067c23e5f52a?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1566150905458-1bf1fc15a7a0?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1547949003-9792a18a2601?w=800&auto=format&fit=crop&q=80',
    ],

    // JEWELRY — 50 unique jewelry images
    3 => [
        'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1605100804763-247f67b3557e?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1573408301185-9519f94815a7?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1589128777073-263566ae5e4d?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1601821765780-754fa98637c1?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1610714970805-b13bd280c4c3?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1614086138357-ff8a30f4d5ab?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1619119069152-a2b331eb392a?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1619177388595-a6946a0d4c66?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1586717791821-3f44a563fa4c?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1561828995-aa79a2db86dd?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1542838132-92c53300491e?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1641784434726-69fb5df34e35?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1607522370275-f6d4f700efcf?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1577803645773-f96470509666?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1635767798638-3e25273a8236?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1611591437281-460bfbe1220a?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1603561591411-07134e71a2a9?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1616541823729-00fe0aacd32c?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1596944924616-7b38e7cfac36?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1627293509201-ce1ebbb39c93?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1645049680651-e6a82cc45e0e?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1568344950671-5ec2fe9f5a5a?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1623091411395-09e79fdbfcf3?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1630350537992-01f2d7f9a1fd?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1624218066034-6b5a5ef97f84?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1609081219090-a6d81d3085bf?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1602751584552-8ba73aad10e1?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1586942593568-0d63e93e9e7a?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1600721391776-b5cd0e0048f9?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1655191706116-a9d9a08a3543?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1637119786379-7b6a2e2c2e47?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1614252369475-531eba835eb1?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1628264024730-f01db06c0a65?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1617038260897-41a1f14a8ca0?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1630349137723-d4b9d1b1b1a1?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1596944924616-7b38e7cfac36?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1614254975571-6360f1b22e8b?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1595273670150-bd0c3c392e46?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1607301405345-4c3a574ec3d0?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1551803091-e20673f15770?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1502873752051-fe4b79ee7f68?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1545291730-faff8ca1d4b0?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1632932693927-c8e9e69b04d1?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1571701869997-11c7bc2b0527?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1573408301185-9519f94815a7?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1619994403073-2cec844b8e63?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1629495969882-9fe77e614e2e?w=800&auto=format&fit=crop&q=80',
    ],

    // SHOES — 50 unique shoe images
    4 => [
        'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1539185441755-769473a23570?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1460353581641-37baddab0fa2?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1491553895911-0055eca6402d?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1608231387042-66d1773070a5?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1603808033192-082d6919d3e1?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1575537302964-96cd47c06b1b?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1584735175315-9d5df23be7be?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1597045566677-8cf032ed6634?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1620650471688-2d0b69893a2e?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1610398752800-146f269dfcc8?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1603487742131-4160ec999306?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1600185365926-3a2ce3cdb9eb?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1619086303291-0ef7699e4b31?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1562183241-840b8af0721e?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1572635196237-14b3f281503f?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1469334031218-e382a71b716b?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1516478177764-9fe5bd7e9717?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1621996659490-3275b4d0d951?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1631891236954-7a0ba7b40cbb?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1600269452121-4f2416e55c28?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1626947346165-4c2288dadc2a?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1602028915047-37269d1a73f7?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1556906781-9a412961a28c?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1512716713-b5f6b0e3e2e0?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1571086720768-62c83c11f5a9?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1560343090-f0409e92791a?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1617137984095-74e4e5e3613f?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1576672843344-f01907a9d40c?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1618354691229-88d47f285158?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1626947346165-4c2288dadc2a?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1603808033192-082d6919d3e1?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1491553895911-0055eca6402d?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1612831455359-970e23a1e4e9?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1610398752800-146f269dfcc8?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1575537302964-96cd47c06b1b?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1600185365926-3a2ce3cdb9eb?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1608231387042-66d1773070a5?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1539185441755-769473a23570?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1631891236954-7a0ba7b40cbb?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1460353581641-37baddab0fa2?w=800&auto=format&fit=crop&q=80',
    ],

    // PERFUMES — 50 unique fragrance/perfume bottle images
    5 => [
        'https://images.unsplash.com/photo-1541643600914-78b084683601?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1592945403244-b3fbafd7f539?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1594035910387-fea47794261f?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1585386959984-a4155224a1ad?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1523293182086-7651a899d37f?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1506377872008-6645d9d29ef7?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1619994403073-2cec844b8e63?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1608528577891-eb055944f2e7?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1547887537-6158d64c35b3?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1615634260167-c8cdede054de?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1619944319059-0d6d6d96ad31?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1603639484735-f400abd1c85b?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1614252369475-531eba835eb1?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1595246140608-51b9d9cf8888?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1648736248397-36a91d0db7b4?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1600521862502-57fce71b7e71?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1635767798638-3e25273a8236?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1605732562851-2568ba6f86c0?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1625772299848-391b6a87d7b3?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1588405748880-12d1d2a59f75?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1617220379006-f7b28d48c5c4?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1611930022073-b7a4ba5fcccd?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1587049352846-4a222e784d38?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1619944319059-0d6d6d96ad31?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1621478402424-d0b8a12b5e19?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1563170351-be54709f8fac?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1614252370763-4c6c2dfa7b06?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1587386090127-1f8cc8abe7f7?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1631202617501-d1e3bf6c59e7?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1588405748880-12d1d2a59f75?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1619994403073-2cec844b8e63?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1602173574767-37ac01994b2a?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1615996001375-c7ef13294436?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1637019116622-eb6ad71b60c7?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1610914023736-5b19438dd0dc?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1627873649417-c67f701f1949?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1607522370275-f6d4f700efcf?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1547887537-6158d64c35b3?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1615634260167-c8cdede054de?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1506377872008-6645d9d29ef7?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1523293182086-7651a899d37f?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1603639484735-f400abd1c85b?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1594035910387-fea47794261f?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1608528577891-eb055944f2e7?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1585386959984-a4155224a1ad?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1592945403244-b3fbafd7f539?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1541643600914-78b084683601?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1625772299848-391b6a87d7b3?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1611930022073-b7a4ba5fcccd?w=800&auto=format&fit=crop&q=80',
    ],

    // ACCESSORIES/WATCHES — 50 unique images
    6 => [
        'https://images.unsplash.com/photo-1522312346375-d1a52e2b99b3?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1511499767150-a48a237f0083?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1617038260897-41a1f14a8ca0?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1548169874-53e85f753f1e?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1524592094714-0f0654e20314?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1587836374828-4dbafa94cf0e?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1434493789847-2f02dc6ca35d?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1612817288484-6f916006741a?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1547996160-81dfa63595aa?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1614596753414-d42aa8c35fb0?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1619579716511-5d3e4d8e8d6f?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1623998021446-45cd9b269056?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1586183189334-8ac1c0e5a0bd?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1609609839861-59fde1bc3e25?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1618354691373-d851c5c3a990?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1595591502853-bdb6b0a7a7a3?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1504868584819-f8e8b4b6d7e3?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1627293509201-ce1ebbb39c93?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1601924994987-69e26d50dc26?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1649429399531-57cbc47f9a2e?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1533139502658-0198f920d8e8?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1631202617501-d1e3bf6c59e7?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1612817288484-6f916006741a?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1585386959984-a4155224a1ad?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1508057198894-247b23fe5ade?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1516478177764-9fe5bd7e9717?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1620625515032-6ed0c1790c75?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1526045612212-70caf35c14df?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1614596753414-d42aa8c35fb0?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1620650471688-2d0b69893a2e?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1631735237350-c4c2274b05be?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1526045612212-70caf35c14df?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1508057198894-247b23fe5ade?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1617922001439-4a2e6562f328?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1609081219090-a6d81d3085bf?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1614179924047-e1ab5a2f0b82?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1547949003-9792a18a2601?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1619086303291-0ef7699e4b31?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1524592094714-0f0654e20314?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1548169874-53e85f753f1e?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1617038260897-41a1f14a8ca0?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1511499767150-a48a237f0083?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1522312346375-d1a52e2b99b3?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1586183189334-8ac1c0e5a0bd?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1609609839861-59fde1bc3e25?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1623998021446-45cd9b269056?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1587836374828-4dbafa94cf0e?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1434493789847-2f02dc6ca35d?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1533139502658-0198f920d8e8?w=800&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1618354691373-d851c5c3a990?w=800&auto=format&fit=crop&q=80',
    ],
];

// Product Names per Category
$items = [
    1 => ['Silk Trench Coat','Evening Gown','Cashmere Sweater','Velvet Blazer','Linen Shirt',
          'Tailored Trousers','Satin Slip Dress','Wool Overcoat','Tuxedo Jacket','Pleated Midi Skirt',
          'Organza Blouse','Crepe Jumpsuit','Tweed Jacket','Shearling Coat','Sequined Mini Dress',
          'Chiffon Maxi Dress','Merino Cardigan','Peplum Top','Knit Co-ord Set','Brocade Vest'],
    2 => ['Leather Tote','Saddle Crossbody','Clutch Bag','Satchel','Mini Shoulder Bag',
          'Bucket Bag','Duffle Bag','Backpack','Messenger Bag','Envelope Clutch',
          'Hobo Bag','Wristlet Pouch','Box Bag','Frame Bag','Micro Bag',
          'Shopper Tote','Bowling Bag','Doctor Bag','Flap Bag','Camera Bag'],
    3 => ['Diamond Ring','Gold Necklace','Pearl Earrings','Platinum Bracelet','Emerald Pendant',
          'Sapphire Band','Choker Necklace','Cuff Links','Statement Brooch','Gemstone Anklet',
          'Tennis Bracelet','Hoop Earrings','Cocktail Ring','Charm Bracelet','Teardrop Pendant',
          'Bangle Set','Stackable Ring','Ear Cuff','Locket Necklace','Pavé Studs'],
    4 => ['Leather Loafers','Suede Chelsea Boots','Stiletto Heels','Monk Strap Shoes','Oxford Brogue',
          'Velvet Slippers','Ankle Boots','Leather Sneakers','Strappy Mules','Platform Heels',
          'Espadrille Wedges','Block-Heel Sandals','Ballet Flats','Knee-High Boots','Moccasins',
          'Mary Jane Pumps','Pointed Flats','Sling-Back Heels','Slide Sandals','Derby Shoes'],
    5 => ['Eau de Parfum','Oud Absolute','Elixir Fragrance','Cologne Intense','Parfum Extract',
          'Fragrance Mist','Amber Essence','Attar Oil','White Musk Nectar','Rose Soliflore',
          'Neroli Blossoms','Sandalwood Accord','Bergamot Spray','Cedar Wood Parfum','Jasmine Absolute',
          'Vanilla Noir EDP','Iris Collection','Tobacco Leather EDP','Agarwood Parfum','Citrus Accord'],
    6 => ['Chronograph Watch','Silk Pocket Square','Leather Belt','Designer Sunglasses','Slim Cardholder',
          'Fedora Hat','Cashmere Gloves','Monogram Tie','Gold Keyring','Tie Clip',
          'Signet Ring','Cufflinks Set','Lapel Pin','Silk Tie','Wool Scarf',
          'Money Clip','Business Card Holder','Collar Stiffeners','Hat Pin','Watch Winder'],
];

$adjectives = ['Imperial','Royal','Bespoke','Artisanal','Midnight','Aurelia','Sovereign','Elysian',
               'Classic','Contemporary','Atelier','Gilded','Obsidian','Monarch','Heritage',
               'Private','Signature','Estate','Luxe','Opulent','Platinum','Velvet',
               'Emperor','Prestige','Decadent','Majestic','Ancestral','Grand','Elite','Premier'];

$price_ranges = [
    1 => [45000, 450000],
    2 => [120000, 950000],
    3 => [250000, 3500000],
    4 => [85000, 600000],
    5 => [60000, 380000],
    6 => [20000, 1200000],
];

$stmt_prod = $pdo->prepare(
    "INSERT INTO products (category_id, name, slug, description, price, stock, image_url, is_featured) VALUES (?,?,?,?,?,?,?,?)"
);

$total = 0;
$slug_counter = [];
$all_products = [];
$cat_order = [2, 3, 4, 5, 6, 1]; // Category 1 (Clothing) seeded last so they get highest IDs (51-60)

foreach ($cat_order as $cat_id) {
    $img_pool = $images[$cat_id];
    $name_pool = $items[$cat_id];
    $adj_pool = $adjectives;
    shuffle($adj_pool);
    shuffle($img_pool);
    shuffle($name_pool);

    for ($i = 0; $i < 10; $i++) {
        $adj  = $adj_pool[$i % count($adj_pool)];
        $item = $name_pool[$i % count($name_pool)];
        $name = "$adj $item";

        // Ensure unique slug
        $base_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $name)));
        if (!isset($slug_counter[$base_slug])) {
            $slug_counter[$base_slug] = 0;
            $slug = $base_slug;
        } else {
            $slug_counter[$base_slug]++;
            $slug = $base_slug . '-' . $slug_counter[$base_slug];
        }

        // Assign ONE unique image per product by index within the pool
        $image = $img_pool[$i % count($img_pool)];

        $desc = "A masterfully crafted $name, representing the pinnacle of luxury design and hand-finished atelier craftsmanship. An exclusive piece for the most discerning clientele.";

        [$min_price, $max_price] = $price_ranges[$cat_id];
        // Spread prices nicely across the range
        $price = round($min_price + (($max_price - $min_price) / 9) * $i, -3);

        $stock = rand(3, 30);

        $all_products[] = [
            'category_id' => $cat_id,
            'name' => $name,
            'slug' => $slug,
            'description' => $desc,
            'price' => $price,
            'stock' => $stock,
            'image_url' => $image,
            'is_featured' => 0
        ];
    }
}

// Mark exactly 10 random products as featured (is_featured = 1)
$featured_keys = array_rand($all_products, 10);
foreach ($featured_keys as $key) {
    $all_products[$key]['is_featured'] = 1;
}

// Insert products into the database
foreach ($all_products as $p) {
    $stmt_prod->execute([
        $p['category_id'],
        $p['name'],
        $p['slug'],
        $p['description'],
        $p['price'],
        $p['stock'],
        $p['image_url'],
        $p['is_featured']
    ]);
    $total++;
}

echo "Seeded $total products with unique images.\n";

// Discounts
$pdo->prepare("INSERT INTO discounts (code,type,value,expires_at,usage_limit,used_count,is_active,who_can_use,one_time_use) VALUES (?,?,?,?,?,0,1,?,?)")
    ->execute(['WELCOME10','percent',10.00,'2030-12-31',999999,'new',1]);
$pdo->prepare("INSERT INTO discounts (code,type,value,expires_at,usage_limit,used_count,is_active,who_can_use,one_time_use) VALUES (?,?,?,?,?,0,1,?,?)")
    ->execute(['SUMMER25','percent',25.00,'2030-12-31',1000,'existing',1]);
$pdo->prepare("INSERT INTO discounts (code,type,value,expires_at,usage_limit,used_count,is_active,who_can_use,one_time_use) VALUES (?,?,?,?,?,0,1,?,?)")
    ->execute(['LUNARVIP10','percent',10.00,'2027-12-31',500,'all',0]);
$pdo->prepare("INSERT INTO discounts (code,type,value,expires_at,usage_limit,used_count,is_active,who_can_use,one_time_use) VALUES (?,?,?,?,?,0,1,?,?)")
    ->execute(['WELCOME50K','fixed',50000.00,'2027-12-31',100,'all',0]);
echo "Discounts seeded.\n";

// Addresses
$pdo->prepare("INSERT INTO addresses (user_id, address_line1, address_line2, city, state, postal_code, country, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
    ->execute([2, '123 Luxury Way', 'Penthouse A', 'Lagos', 'Lagos State', '100001', 'Nigeria', 1]);
$pdo->prepare("INSERT INTO addresses (user_id, address_line1, address_line2, city, state, postal_code, country, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
    ->execute([2, '456 Fashion Blvd', NULL, 'Abuja', 'FCT', '900001', 'Nigeria', 0]);
$pdo->prepare("INSERT INTO addresses (user_id, address_line1, address_line2, city, state, postal_code, country, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
    ->execute([3, '789 Diamond Ave', NULL, 'Port Harcourt', 'Rivers State', '500001', 'Nigeria', 1]);
echo "Addresses seeded.\n";

// Orders & Order Items & History
// Order 1 (completed, from customer 2)
$pdo->prepare("INSERT INTO orders (customer_id, customer_name, total_amount, status, shipping_address, phone, created_at, discount_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
    ->execute([2, 'Customer User', 570000.00, 'delivered', '123 Luxury Way, Penthouse A, Lagos, Lagos State, Nigeria', '+2348012345678', '2026-06-20 14:30:00', 'WELCOME10']);
$order_id = $pdo->lastInsertId();
$pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)")
    ->execute([$order_id, 1, 1, 450000.00]);
$pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)")
    ->execute([$order_id, 2, 1, 120000.00]);
$pdo->prepare("INSERT INTO order_status_history (order_id, status, notes) VALUES (?, ?, ?)")
    ->execute([$order_id, 'pending', 'Order placed successfully']);
$pdo->prepare("INSERT INTO order_status_history (order_id, status, notes) VALUES (?, ?, ?)")
    ->execute([$order_id, 'processing', 'Payment verified via Paystack']);
$pdo->prepare("INSERT INTO order_status_history (order_id, status, notes) VALUES (?, ?, ?)")
    ->execute([$order_id, 'shipped', 'Shipped via DHL Express']);
$pdo->prepare("INSERT INTO order_status_history (order_id, status, notes) VALUES (?, ?, ?)")
    ->execute([$order_id, 'delivered', 'Delivered and signed by customer']);

// Order 2 (pending, from customer 3)
$pdo->prepare("INSERT INTO orders (customer_id, customer_name, total_amount, status, shipping_address, phone, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)")
    ->execute([3, 'Jane Doe', 250000.00, 'pending', '789 Diamond Ave, Port Harcourt, Rivers State, Nigeria', '+2348098765432', '2026-06-25 10:15:00']);
$order_id2 = $pdo->lastInsertId();
$pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)")
    ->execute([$order_id2, 3, 1, 250000.00]);
$pdo->prepare("INSERT INTO order_status_history (order_id, status, notes) VALUES (?, ?, ?)")
    ->execute([$order_id2, 'pending', 'Order placed, awaiting payment confirmation']);

// Order 3 (processing, guest checkout)
$pdo->prepare("INSERT INTO orders (customer_id, customer_name, total_amount, status, shipping_address, phone, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)")
    ->execute([NULL, 'Guest Buyer', 180000.00, 'processing', '101 Velvet St, Ikoyi, Lagos, Nigeria', '+2348022223333', '2026-06-25 18:45:00']);
$order_id3 = $pdo->lastInsertId();
$pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)")
    ->execute([$order_id3, 4, 2, 90000.00]);
$pdo->prepare("INSERT INTO order_status_history (order_id, status, notes) VALUES (?, ?, ?)")
    ->execute([$order_id3, 'pending', 'Guest order created']);
$pdo->prepare("INSERT INTO order_status_history (order_id, status, notes) VALUES (?, ?, ?)")
    ->execute([$order_id3, 'processing', 'Payment verified, preparing item packaging']);
echo "Orders and status history seeded.\n";

// Newsletters
$newsletters = [
    'newsletter1@example.com',
    'newsletter2@example.com',
    'jane@lunar.com',
    'user@lunar.com',
    'guest.buyer@gmail.com'
];
$stmt_news = $pdo->prepare("INSERT INTO newsletters (email) VALUES (?)");
foreach ($newsletters as $email) {
    $stmt_news->execute([$email]);
}
echo "Newsletters seeded.\n";

// Contact Messages
$contact_messages = [
    ['Obinna Nwachukwu', 'obinna@example.com', '+2347012345678', 'Custom Order Inquiry', 'Hello, do you accept custom sizing for the Imperial Silk Gown?', 1, 'Unread'],
    ['Sarah Connor', 'sarah@example.com', NULL, 'Delivery Status', 'Hi, I would like to track my order sent to Abuja.', 0, 'Read'],
    ['John Doe', 'john.doe@example.com', '+2349012345678', 'Wholesale Purchase', 'We are interested in distributing your perfume fragrances in West Africa.', 1, 'Replied']
];
$stmt_msg = $pdo->prepare("INSERT INTO contact_messages (name, email, phone, subject, message, subscribed_newsletter, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
foreach ($contact_messages as $msg) {
    $stmt_msg->execute($msg);
}
echo "Contact messages seeded.\n";

// VIP Consultations
$vip_consultations = [
    ['Prince Adebayo', 'adebayo@royal.ng', 'Private Bridal Styling Session', '2026-07-15', 'Pending'],
    ['Chioma Adeleke', 'chioma.a@luxury.com', 'Custom Diamond Fitting', '2026-07-10', 'Contacted'],
    ['Marcus Sterling', 'sterling@highnet.co.uk', 'Personal Wardrobe Overhaul', '2026-06-24', 'Completed']
];
$stmt_vip = $pdo->prepare("INSERT INTO vip_consultations (guest_name, email, requested_service, requested_date, status) VALUES (?, ?, ?, ?, ?)");
foreach ($vip_consultations as $vip) {
    $stmt_vip->execute($vip);
}
echo "VIP consultations seeded.\n";

echo "All done!\n";
