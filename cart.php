<?php
// =============================================================
// cart.php - CART / ORDER SUMMARY SCREEN
// Part 1 (POST): changes the cart -> add, increase, decrease,
//                remove, clear, or new_order. Then redirects back.
// Part 2 (GET):  shows the cart table with the grand total.
// =============================================================
require __DIR__ . '/config.php';

// ---------- Part 1: handle cart actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $productId = (int) ($_POST['product_id'] ?? 0);

    // After the action, go back to the product page (same category tab)
    // or stay on the cart page
    $backPage = 'cart.php';
    if (($_POST['back'] ?? '') === 'index') {
        $backPage = 'index.php';
        $category = $_POST['category'] ?? '';
        if ($category !== '') {
            $backPage .= '?category=' . urlencode($category);
        }
    }

    // How many of this product are already in the cart (0 if none)
    $currentQty = $_SESSION['cart'][$productId] ?? 0;

    if ($action === 'add') {
        // Add a product (or more of it) to the cart
        $product  = findProduct($pdo, $productId);
        $quantity = validQuantity($_POST['quantity'] ?? '');

        if ($product === null) {
            setFlash('error', 'Product not found.');
        } elseif ($quantity === false) {
            setFlash('error', 'Quantity must be a whole number from 1 to ' . MAX_QTY . '.');
        } elseif ($currentQty + $quantity > MAX_QTY) {
            setFlash('error', 'You can only have up to ' . MAX_QTY . ' of ' . $product['name']
                . ' (you already have ' . $currentQty . ' in the cart).');
        } else {
            $_SESSION['cart'][$productId] = $currentQty + $quantity;
            setFlash('success', 'Added ' . $quantity . ' × ' . $product['name'] . ' to the cart.');
        }
    } elseif ($action === 'increase') {
        // Add 1 to the quantity
        if ($currentQty === 0) {
            setFlash('error', 'That item is not in the cart.');
        } elseif ($currentQty + 1 > MAX_QTY) {
            setFlash('error', 'Quantity cannot be more than ' . MAX_QTY . '.');
        } else {
            $_SESSION['cart'][$productId] = $currentQty + 1;
        }
    } elseif ($action === 'decrease') {
        // Subtract 1 from the quantity. If it reaches 0, remove the item.
        if ($currentQty === 0) {
            setFlash('error', 'That item is not in the cart.');
        } elseif ($currentQty - 1 < 1) {
            unset($_SESSION['cart'][$productId]);
            setFlash('success', 'Item removed from the cart.');
        } else {
            $_SESSION['cart'][$productId] = $currentQty - 1;
        }
    } elseif ($action === 'remove') {
        // Remove one item completely
        unset($_SESSION['cart'][$productId]);
        setFlash('success', 'Item removed from the cart.');
    } elseif ($action === 'clear') {
        // Empty the whole cart
        $_SESSION['cart'] = [];
        setFlash('success', 'The cart has been cleared.');
    } elseif ($action === 'new_order') {
        // Start a new transaction: clear the cart and forget the last
        // payment and receipt, then go back to the product list
        $_SESSION['cart'] = [];
        unset($_SESSION['last_order_id']);
        setFlash('success', 'Previous transaction cleared. Ready for a new order.');
        $backPage = 'index.php';
    } else {
        setFlash('error', 'Unknown action.');
    }

    // Redirect so refreshing the page does not repeat the action
    redirect($backPage);
}

// ---------- Part 2: show the cart ----------
$cartItems = getCartItems($pdo);
$cartTotal = getCartTotal($cartItems);

$pageTitle   = 'Cart';
$activePage  = 'cart';
$currentStep = 2;
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Order summary</h1>
    <p class="muted">Check the items, adjust the quantities, then proceed to payment.</p>
</div>

<?php if (empty($cartItems)): ?>

    <div class="card empty-state">
        <p class="empty-icon">🧺</p>
        <h2>Your cart is empty</h2>
        <p class="muted">Add some bakery items first.</p>
        <a href="index.php" class="btn btn-primary">Browse products</a>
    </div>

<?php else: ?>

    <div class="card">
        <div class="table-wrap">
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="num">Unit price</th>
                        <th class="center">Quantity</th>
                        <th class="num">Subtotal</th>
                        <th class="center">Remove</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cartItems as $item): ?>
                        <tr>
                            <td>
                                <strong><?= e($item['name']) ?></strong>
                                <small class="muted block"><?= e($item['category']) ?></small>
                            </td>
                            <td class="num"><?= e(peso($item['price'])) ?></td>
                            <td class="center">
                                <div class="stepper">
                                    <form method="post" action="cart.php">
                                        <input type="hidden" name="action" value="decrease">
                                        <input type="hidden" name="product_id" value="<?= e($item['id']) ?>">
                                        <button type="submit" class="btn btn-icon" aria-label="Decrease quantity">−</button>
                                    </form>
                                    <span class="qty"><?= e($item['quantity']) ?></span>
                                    <form method="post" action="cart.php">
                                        <input type="hidden" name="action" value="increase">
                                        <input type="hidden" name="product_id" value="<?= e($item['id']) ?>">
                                        <button type="submit" class="btn btn-icon" aria-label="Increase quantity">+</button>
                                    </form>
                                </div>
                            </td>
                            <td class="num"><strong><?= e(peso($item['subtotal'])) ?></strong></td>
                            <td class="center">
                                <form method="post" action="cart.php">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="product_id" value="<?= e($item['id']) ?>">
                                    <button type="submit" class="btn btn-ghost-danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="items-row">
                        <td colspan="3" class="num">Total items</td>
                        <td class="num"><?= e(cartCount()) ?> pcs</td>
                        <td></td>
                    </tr>
                    <tr class="grand-row">
                        <td colspan="3" class="num">Grand total</td>
                        <td class="num"><span class="price-lg"><?= e(peso($cartTotal)) ?></span></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="actions-bar">
        <form method="post" action="cart.php" onsubmit="return confirm('Clear all items from the cart?');">
            <input type="hidden" name="action" value="clear">
            <button type="submit" class="btn btn-ghost-danger">Clear cart</button>
        </form>
        <div class="actions-right">
            <a href="index.php" class="btn btn-secondary">Add more items</a>
            <a href="payment.php" class="btn btn-primary">Proceed to payment</a>
        </div>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
