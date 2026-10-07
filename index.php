<?php
// =============================================================
// index.php - PRODUCT LIST / ORDERING SCREEN
// Left side: category tabs and the product cards with an
//            "Add to cart" form.
// Right side: a summary of the current order.
// The "Add to cart" form is sent to cart.php, which updates the cart.
// =============================================================
require __DIR__ . '/config.php';

// ---------- Category tabs ----------
// Get the list of categories (in the order they were added)
$stmt = $pdo->prepare('SELECT category FROM products GROUP BY category ORDER BY MIN(id)');
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// The selected tab comes from the URL, e.g. index.php?category=Breads
// Only accept a category that really exists. Anything else means "All".
$selectedCategory = $_GET['category'] ?? '';
if (!in_array($selectedCategory, $categories, true)) {
    $selectedCategory = '';
}

// ---------- Load the products ----------
if ($selectedCategory === '') {
    $stmt = $pdo->prepare('SELECT id, name, category, price FROM products ORDER BY id');
    $stmt->execute();
} else {
    $stmt = $pdo->prepare('SELECT id, name, category, price FROM products WHERE category = ? ORDER BY id');
    $stmt->execute([$selectedCategory]);
}
$products = $stmt->fetchAll();

// Load the cart for the side panel
$cartItems = getCartItems($pdo);
$cartTotal = getCartTotal($cartItems);

// One emoji per category, to make the cards easier to scan
$categoryIcons = [
    'Breads'           => '🍞',
    'Pastries'         => '🥐',
    'Cakes & Desserts' => '🍰',
];

$pageTitle   = 'Order';
$activePage  = 'order';
$currentStep = 1;
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Product list</h1>
    <p class="muted">Choose a category, set the quantity, and tap "Add to cart". Adding the same item again increases its quantity.</p>
</div>

<div class="pos-layout">

    <section aria-label="Products">

        <!-- ===== Category tabs (plain links with ?category=...) ===== -->
        <nav class="tabs" aria-label="Categories">
            <a href="index.php" class="tab <?= $selectedCategory === '' ? 'active' : '' ?>">All</a>
            <?php foreach ($categories as $category): ?>
                <a href="index.php?category=<?= e(urlencode($category)) ?>"
                   class="tab <?= $selectedCategory === $category ? 'active' : '' ?>">
                    <?= e($categoryIcons[$category] ?? '🧁') ?> <?= e($category) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- ===== Product grid ===== -->
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <?php $inCart = $_SESSION['cart'][$product['id']] ?? 0; ?>
                <article class="card product-card">
                    <div class="product-top">
                        <span class="product-icon"><?= e($categoryIcons[$product['category']] ?? '🧁') ?></span>
                        <?php if ($inCart > 0): ?>
                            <span class="in-cart">In cart: <?= e($inCart) ?></span>
                        <?php endif; ?>
                    </div>

                    <span class="product-category"><?= e($product['category']) ?></span>
                    <h3 class="product-name"><?= e($product['name']) ?></h3>
                    <p class="price"><?= e(peso($product['price'])) ?></p>

                    <form method="post" action="cart.php" class="add-form">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
                        <input type="hidden" name="back" value="index">
                        <input type="hidden" name="category" value="<?= e($selectedCategory) ?>">

                        <label class="sr-only" for="qty-<?= e($product['id']) ?>">Quantity</label>
                        <input type="number" id="qty-<?= e($product['id']) ?>" name="quantity"
                               value="1" min="1" max="<?= e(MAX_QTY) ?>" class="qty-input">
                        <button type="submit" class="btn btn-primary">Add to cart</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ===== Sticky cart panel (right) ===== -->
    <aside class="card cart-panel" aria-label="Current order">
        <div class="panel-head">
            <h2>Current order</h2>
            <span class="muted"><?= e(cartCount()) ?> item(s)</span>
        </div>

        <?php if (empty($cartItems)): ?>
            <div class="panel-empty">
                <p class="empty-icon">🧺</p>
                <p class="muted">No items yet. Add a product to start the order.</p>
            </div>
        <?php else: ?>
            <ul class="mini-cart">
                <?php foreach ($cartItems as $item): ?>
                    <li>
                        <span>
                            <?= e($item['name']) ?>
                            <small><?= e($item['quantity']) ?> × <?= e(peso($item['price'])) ?></small>
                        </span>
                        <strong><?= e(peso($item['subtotal'])) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="total-row">
                <span>Total</span>
                <strong class="price-lg"><?= e(peso($cartTotal)) ?></strong>
            </div>

            <div class="panel-actions">
                <a href="cart.php" class="btn btn-secondary btn-block">Review cart</a>
                <a href="payment.php" class="btn btn-primary btn-block">Checkout</a>
            </div>
        <?php endif; ?>
    </aside>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
