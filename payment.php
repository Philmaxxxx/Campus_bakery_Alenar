<?php
// =============================================================
// payment.php - PAYMENT SCREEN
// Shows the order summary, the total due, quick-cash buttons,
// and a cash input.
//  - Quick-cash button: only fills in the cash amount.
//  - "Pay now": validates the cash. If it is enough, saves the order
//    and its items in one database transaction, clears the cart,
//    and opens the payment success screen. If not, shows an error
//    and keeps the cart.
// =============================================================
require __DIR__ . '/config.php';

$cartItems = getCartItems($pdo);
$cartTotal = getCartTotal($cartItems);

// An empty cart cannot be paid
if (empty($cartItems)) {
    setFlash('error', 'The cart is empty. Add products before paying.');
    redirect('index.php');
}

// Amounts shown as quick-cash buttons
$quickAmounts = [100, 200, 500, 1000];

$error     = '';
$shortBy   = 0;   // how much is still owed when the cash is not enough
$cashInput = '';

// ---------- A. Quick-cash button was clicked: fill in the amount ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick'])) {
    $quick = $_POST['quick'];

    if ($quick === 'exact') {
        $cashInput = number_format($cartTotal, 2, '.', '');
    } elseif (in_array((int) $quick, $quickAmounts, true)) {
        $cashInput = number_format((int) $quick, 2, '.', '');
    }
}

// ---------- B. "Pay now" was clicked: validate and save ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cash'])) {
    $cashInput = is_string($_POST['cash']) ? trim($_POST['cash']) : '';

    // Validate the cash amount. Each problem has its own message.
    if ($cashInput === '') {
        // 1. Blank
        $error = 'The cash amount is blank. Please enter the cash received.';
    } elseif (!is_numeric($cashInput)) {
        // 2. Not a number (letters or symbols, e.g. "abc" or "12a")
        $error = 'The cash amount must be a number (for example 150 or 150.50). Letters and symbols are not allowed.';
    } elseif ((float) $cashInput < 0) {
        // 3. Negative
        $error = 'The cash amount cannot be negative.';
    } elseif ((float) $cashInput == 0) {
        // 4. Zero
        $error = 'The cash amount must be greater than zero.';
    } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $cashInput)) {
        // 5. Only plain amounts with up to 2 decimal places (rejects 1e3 or 10.555)
        $error = 'Enter a plain peso amount with at most 2 decimal places, for example 150.50.';
    } elseif (round((float) $cashInput, 2) < $cartTotal) {
        // 6. Insufficient

        $shortBy = round($cartTotal - (float) $cashInput, 2);
        $error   = 'Insufficient cash. The payment was rejected.';
    }

    // Cash is valid and enough: compute the change and save the order
    if ($error === '') {
        $amountPaid = round((float) $cashInput, 2);
        $change     = round($amountPaid - $cartTotal, 2);

        try {
            // A transaction makes sure the order and all its items are saved together
            $pdo->beginTransaction();

            // 1. Make the reference: TXN-YYYYMMDD-0001 (counts today's orders + 1)
            $today = date('Ymd');
            $stmt  = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE reference LIKE ?');
            $stmt->execute(['TXN-' . $today . '-%']);
            $nextNumber = (int) $stmt->fetchColumn() + 1;
            $reference  = sprintf('TXN-%s-%04d', $today, $nextNumber); // %04d = 4 digits with leading zeros

            // 2. Save the order
            $stmt = $pdo->prepare('INSERT INTO orders (reference, total, amount_paid, change_amount, created_at)
                                   VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$reference, $cartTotal, $amountPaid, $change, date('Y-m-d H:i:s')]);
            $orderId = (int) $pdo->lastInsertId();

            // 3. Save each item of the order
            $stmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, subtotal)
                                   VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($cartItems as $item) {
                $stmt->execute([$orderId, $item['id'], $item['name'], $item['price'], $item['quantity'], $item['subtotal']]);
            }

            $pdo->commit();

            // 4. Clear the cart, remember this order for the confirmation
            //    and receipt screens, then show the payment confirmation
            $_SESSION['cart']          = [];
            $_SESSION['last_order_id'] = $orderId;
            redirect('payment_success.php');
        } catch (PDOException $ex) {
            // Something failed: undo everything, keep the cart
            $pdo->rollBack();
            $error = 'The order could not be saved. Please try again.';
        }
    }
}

$pageTitle   = 'Payment';
$activePage  = 'cart';
$currentStep = 3;
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Payment</h1>
    <p class="muted">Enter the cash received from the customer, then tap "Pay now".</p>
</div>

<div class="checkout-layout">

    <!-- ===== Order summary ===== -->
    <section class="card">
        <div class="panel-head">
            <h2>Order summary</h2>
            <span class="muted"><?= e(cartCount()) ?> item(s)</span>
        </div>
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
        <a href="cart.php" class="link">← Edit cart</a>
    </section>

    <!-- ===== Payment form ===== -->
    <section class="card pay-card">
        <p class="label">Total due</p>
        <p class="total-due"><?= e(peso($cartTotal)) ?></p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error" role="alert">
                <strong><?= e($error) ?></strong>
                <?php if ($shortBy > 0): ?>
                    <dl class="alert-details">
                        <dt>Total due</dt>
                        <dd><?= e(peso($cartTotal)) ?></dd>
                        <dt>Amount entered</dt>
                        <dd><?= e(peso($cashInput)) ?></dd>
                        <dt>Still owed</dt>
                        <dd><strong><?= e(peso($shortBy)) ?></strong></dd>
                    </dl>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Quick-cash buttons: each one only fills in the amount below -->
        <p class="label">Quick cash</p>
        <form method="post" action="payment.php" class="quick-cash">
            <button type="submit" name="quick" value="exact" class="btn btn-chip">Exact amount</button>
            <?php foreach ($quickAmounts as $amount): ?>
                <button type="submit" name="quick" value="<?= e($amount) ?>" class="btn btn-chip">
                    <?= e('₱' . number_format($amount)) ?>
                </button>
            <?php endforeach; ?>
        </form>

        <form method="post" action="payment.php">
            <label for="cash" class="label">Cash amount (₱)</label>
            <!-- type="text" so the server-side validation can be tested -->
            <input type="text" id="cash" name="cash" inputmode="decimal" autocomplete="off"
                   class="cash-input <?= $error !== '' ? 'has-error' : '' ?>"
                   placeholder="0.00" value="<?= e($cashInput) ?>" autofocus>
            <button type="submit" class="btn btn-primary btn-block btn-lg">Pay now</button>
        </form>
    </section>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
