<?php
// =============================================================
// includes/header.php - shared top part of every page
// Prints the <head>, the navigation bar, the progress steps,
// and the flash message.
// Each page sets $pageTitle, $activePage and $currentStep
// before including this file.
// =============================================================
$flash = getFlash();

// The 4 steps of an order, shown as a progress bar at the top
$steps = [1 => 'Order', 2 => 'Cart', 3 => 'Payment', 4 => 'Receipt'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · Campus Bakery POS</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🥐</text></svg>">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header class="site-header no-print">
    <div class="header-inner">
        <a href="index.php" class="brand">
            <span class="brand-logo">🥐</span>
            <span class="brand-text">
                <strong>Campus Bakery</strong>
                <small>Point of Sale</small>
            </span>
        </a>

        <nav class="nav">
            <a href="index.php" class="<?= $activePage === 'order' ? 'active' : '' ?>">Order</a>
            <a href="cart.php" class="<?= $activePage === 'cart' ? 'active' : '' ?>">
                Cart <span class="badge"><?= e(cartCount()) ?></span>
            </a>
        </nav>
    </div>
</header>

<main class="container">

<!-- Progress steps: finished steps get a check mark, the current step is highlighted -->
<ol class="steps no-print">
    <?php foreach ($steps as $number => $label): ?>
        <?php
        if ($number < $currentStep) {
            $state = 'done';
        } elseif ($number === $currentStep) {
            $state = 'current';
        } else {
            $state = 'todo';
        }
        ?>
        <li class="step step-<?= e($state) ?>">
            <span class="step-dot"><?= $state === 'done' ? '✓' : e($number) ?></span>
            <span class="step-label"><?= e($label) ?></span>
        </li>
    <?php endforeach; ?>
</ol>

<?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> no-print" role="alert">
        <?= e($flash['message']) ?>
    </div>
<?php endif; ?>
