<?php
// =============================================================
// receipt.php - RECEIPT SCREEN
// Loads the order that was just paid and its items, then shows
// a printable, paper-style digital receipt.
// =============================================================
require __DIR__ . '/config.php';

// Load the order that was just paid
$order = getLastOrder($pdo);

// No paid order (or "New order" was already clicked): go back
if ($order === null) {
    setFlash('error', 'There is no receipt to show. Please start a new order.');
    redirect('index.php');
}

// Load the items of this order
$stmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
$stmt->execute([$order['id']]);
$orderItems = $stmt->fetchAll();

$pageTitle   = 'Receipt ' . $order['reference'];
$activePage  = '';
$currentStep = 4;
require __DIR__ . '/includes/header.php';
?>

<div class="receipt">
    <div class="receipt-head">
        <p class="receipt-logo">🥐</p>
        <h1>Campus Bakery</h1>
        <p>Point of Sale · Official Receipt</p>
    </div>

    <dl class="receipt-meta">
        <dt>Reference</dt>
        <dd><strong><?= e($order['reference']) ?></strong></dd>
        <dt>Date</dt>
        <dd><?= e(date('F j, Y', strtotime($order['created_at']))) ?></dd>
        <dt>Time</dt>
        <dd><?= e(date('g:i A', strtotime($order['created_at']))) ?></dd>
    </dl>

    <table class="receipt-table">
        <thead>
            <tr>
                <th>Item</th>
                <th class="center">Qty</th>
                <th class="num">Price</th>
                <th class="num">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orderItems as $item): ?>
                <tr>
                    <td><?= e($item['product_name']) ?></td>
                    <td class="center"><?= e($item['quantity']) ?></td>
                    <td class="num"><?= e(peso($item['unit_price'])) ?></td>
                    <td class="num"><?= e(peso($item['subtotal'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <dl class="receipt-totals">
        <dt class="grand">TOTAL</dt>
        <dd class="grand"><?= e(peso($order['total'])) ?></dd>
        <dt>Amount paid</dt>
        <dd><?= e(peso($order['amount_paid'])) ?></dd>
        <dt>Change</dt>
        <dd><strong><?= e(peso($order['change_amount'])) ?></strong></dd>
    </dl>

    <p class="receipt-thanks">Thank you for your purchase!<br><small>Please come again.</small></p>
</div>

<div class="receipt-actions no-print">
    <button type="button" class="btn btn-secondary btn-lg" onclick="window.print()">Print receipt</button>
    <!-- New order: clears the cart, payment, and receipt of this transaction -->
    <form method="post" action="cart.php">
        <input type="hidden" name="action" value="new_order">
        <button type="submit" class="btn btn-primary btn-lg btn-block">New order</button>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
