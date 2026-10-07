<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Support\Money;

$config = app_config();
$token = csrf_token();
$error = $_GET['error'] ?? null;
$mode = $config['secret'] ? (str_starts_with($config['secret'], 'sk_live_') ? 'LIVE' : 'TEST MODE') : 'NOT CONFIGURED';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Checkout | Paystack Payment Integration Demo</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
  <div><h1>JO Digital Services</h1><small>Paystack payment integration demo (PHP 8)</small></div>
  <span class="badge <?= $mode === 'NOT CONFIGURED' ? 'warn' : '' ?>"><?= e($mode) ?></span>
</header>
<main>
  <?php if (!$config['secret']): ?>
    <div class="notice">Add your <strong>PAYSTACK_SECRET_KEY</strong> (sk_test_...) to <code>.env</code> to enable payments. Get test keys at dashboard.paystack.com → Settings → API Keys.</div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="notice error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" action="pay.php">
    <input type="hidden" name="csrf" value="<?= e($token) ?>">
    <div class="grid">
      <?php $first = true; foreach (PRODUCTS as $id => $p): ?>
        <label class="card">
          <input type="radio" name="product" value="<?= e($id) ?>" <?= $first ? 'checked' : '' ?>>
          <h3><?= e($p['name']) ?></h3>
          <p><?= e($p['desc']) ?></p>
          <div class="price"><?= e(Money::format($p['price'])) ?></div>
        </label>
      <?php $first = false; endforeach; ?>
    </div>
    <div class="row">
      <input type="email" name="email" placeholder="Your email for the receipt" required>
      <button type="submit">Pay securely with Paystack</button>
    </div>
  </form>

  <?php $recent = orders()->latest(5); if ($recent): ?>
    <table>
      <tr><th>Reference</th><th>Product</th><th>Amount</th><th>Status</th><th>Channel</th></tr>
      <?php foreach ($recent as $o): ?>
        <tr>
          <td><code><?= e($o['reference']) ?></code></td>
          <td><?= e($o['product']) ?></td>
          <td><?= e(Money::format((int) $o['amount_kobo'], $o['currency'])) ?></td>
          <td class="status <?= e($o['status']) ?>"><?= e($o['status']) ?></td>
          <td><?= e($o['channel'] ?? '-') ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</main>
<footer>Built by Joseph Obaje · Payments processed by Paystack · Card, bank transfer, USSD</footer>
</body>
</html>
