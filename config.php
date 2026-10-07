<?php
// =============================================================
// config.php - shared setup, loaded at the top of every page
//   1. Starts the session (the cart is stored in $_SESSION)
//   2. Connects to the SQLite database using PDO
//   3. Creates the tables and seeds the products on first run
//   4. Small helper functions used by all pages
// =============================================================

session_start();
date_default_timezone_set('Asia/Manila');

// Highest quantity allowed for one product in the cart
const MAX_QTY = 99;

// ---------- 1. Database connection ----------
// The database file is created automatically inside the "data" folder.
$dataFolder = __DIR__ . '/data';
if (!is_dir($dataFolder)) {
    mkdir($dataFolder);
}

$pdo = new PDO('sqlite:' . $dataFolder . '/bakery.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);      // show errors as exceptions
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); // rows come back as ['column' => value]
$pdo->exec('PRAGMA foreign_keys = ON');

// ---------- 2. Create the tables (only if they do not exist yet) ----------
$pdo->exec('CREATE TABLE IF NOT EXISTS products (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    name     TEXT NOT NULL,
    category TEXT NOT NULL,
    price    REAL NOT NULL
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS orders (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    reference     TEXT NOT NULL UNIQUE,
    total         REAL NOT NULL,
    amount_paid   REAL NOT NULL,
    change_amount REAL NOT NULL,
    created_at    TEXT NOT NULL
)');

// order_items keeps a copy of the name and price, so old receipts
// stay correct even if a product price changes later.
$pdo->exec('CREATE TABLE IF NOT EXISTS order_items (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id     INTEGER NOT NULL REFERENCES orders(id),
    product_id   INTEGER NOT NULL REFERENCES products(id),
    product_name TEXT NOT NULL,
    unit_price   REAL NOT NULL,
    quantity     INTEGER NOT NULL,
    subtotal     REAL NOT NULL
)');

// ---------- 3. Seed the products on first run ----------
$stmt = $pdo->prepare('SELECT COUNT(*) FROM products');
$stmt->execute();

if ((int) $stmt->fetchColumn() === 0) {
    $seedProducts = [
        ['Pandesal',             'Breads',           5.00],
        ['Cheese Bread',         'Breads',          15.00],
        ['Monay',                'Breads',          10.00],
        ['Spanish Bread',        'Breads',          12.00],
        ['Ube Cheese Pandesal',  'Breads',          20.00],
        ['Ensaymada',            'Pastries',        25.00],
        ['Cinnamon Roll',        'Pastries',        45.00],
        ['Banana Bread',         'Pastries',        35.00],
        ['Brownies',             'Cakes & Desserts', 30.00],
        ['Egg Pie (slice)',      'Cakes & Desserts', 35.00],
        ['Chocolate Cake Slice', 'Cakes & Desserts', 65.00],
        ['Buko Pie (slice)',     'Cakes & Desserts', 40.00],
    ];

    $insert = $pdo->prepare('INSERT INTO products (name, category, price) VALUES (?, ?, ?)');
    foreach ($seedProducts as $product) {
        $insert->execute($product);
    }
}

// ---------- 4. The cart ----------
// The cart is an array in the session: [product_id => quantity]
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ---------- 5. Helper functions ----------

// Escape text before printing it in HTML (prevents XSS)
function e($text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

// Format a number as Philippine pesos, e.g. 1250 -> "₱1,250.00"
function peso($amount): string
{
    return '₱' . number_format((float) $amount, 2);
}

// Go to another page and stop running this one
function redirect(string $page): void
{
    header('Location: ' . $page);
    exit;
}

// Save a one-time message to show on the next page (type: success or error)
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

// Read the one-time message and delete it so it shows only once
function getFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

// Find one product by its id. Returns null if it does not exist.
function findProduct(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, name, category, price FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    return $product ?: null;
}

// Find one saved order by its id. Returns null if it does not exist.
function findOrder(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    return $order ?: null;
}

// The order that was just paid (saved in the session by payment.php).
// Returns null when there is none, e.g. after "New order" was clicked.
function getLastOrder(PDO $pdo): ?array
{
    $orderId = $_SESSION['last_order_id'] ?? null;
    return $orderId === null ? null : findOrder($pdo, (int) $orderId);
}

// Check that a value is a whole number from 1 to 99.
// Returns the number, or false if it is not valid.
function validQuantity($value)
{
    return filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => MAX_QTY],
    ]);
}

// Build the list of cart items using the CURRENT prices from the database
function getCartItems(PDO $pdo): array
{
    $items = [];

    foreach ($_SESSION['cart'] as $productId => $quantity) {
        $product = findProduct($pdo, (int) $productId);

        // If a product no longer exists, drop it from the cart
        if ($product === null) {
            unset($_SESSION['cart'][$productId]);
            continue;
        }

        $product['quantity'] = $quantity;
        $product['subtotal'] = round($product['price'] * $quantity, 2);
        $items[] = $product;
    }

    return $items;
}

// Add up all the subtotals to get the grand total
function getCartTotal(array $items): float
{
    $total = 0;
    foreach ($items as $item) {
        $total += $item['subtotal'];
    }
    return round($total, 2);
}

// Total number of pieces in the cart (shown in the navigation badge)
function cartCount(): int
{
    return array_sum($_SESSION['cart']);
}
