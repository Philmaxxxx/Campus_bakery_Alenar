<?php
// =============================================================
// payment_success.php - PAYMENT CONFIRMATION SCREEN
// Shown right after a successful payment. Loads the order that
// was just paid and shows the total, the amount paid, and the
// computed change (Change = Amount Paid - Total Amount).
// =============================================================
require __DIR__ . '/config.php';

// Load the order that was just paid
$order = getLastOrder($pdo);

// No paid order (or "New order" was already clicked): go back
if ($order === null) {
    setFlash('error', 'There is no completed payment to show. Please start a new order.');
    redirect('index.php');
}

$pageTitle   = 'Payment successful';
$activePage  = '';
$currentStep = 4;
require __DIR__ . '/includes/header.php';
?>

<div class="card success-card">
    <div class="success-icon" aria-hidden="true">✓</div>
    <h1>Payment successful</h1>
    <p class="muted">
        Reference <strong class="reference"><?= e($order['reference']) ?></strong>
    </p>

    <dl class="summary-list">
        <dt>Total</dt>
        <dd><?= e(peso($order['total'])) ?></dd>
        <dt>Amount paid</dt>
        <dd><?= e(peso($order['amount_paid'])) ?></dd>
    </dl>

    <!-- The computed change, shown large: change = amount paid - total -->
    <div class="change-box">
        <span class="label">Change</span>
        <span class="change-amount"><?= e(peso($order['change_amount'])) ?></span>
    </div>

    <div class="success-actions">
        <a href="receipt.php" class="btn btn-primary btn-lg">View receipt</a>
        <!-- New order: clears the cart, payment, and receipt of this transaction -->
        <form method="post" action="cart.php">
            <input type="hidden" name="action" value="new_order">
            <button type="submit" class="btn btn-secondary btn-lg btn-block">New order</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
