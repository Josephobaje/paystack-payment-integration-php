<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Support\Money;

$reference = (string) ($_GET['reference'] ?? $_GET['trxref'] ?? '');
$result = null;
$error = null;
try {
    $payments = payments() ?? throw new RuntimeException('Payments are not configured');
    $result = $payments->confirm($reference);
} catch (Throwable $e) {
    error_log('Paystack verify failed: ' . $e->getMessage());
    $error = 'We could not confirm this payment yet. If you were debited, it will be confirmed automatically shortly.';
}
$paid = $result && $result['status'] === 'paid';
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Payment <?= $paid ? 'successful' : 'status' ?></title><link rel="stylesheet" href="style.css"></head>
<body>
<header><div><h1>JO Digital Services</h1><small>Payment status</small></div></header>
<main>
  <div class="result">
    <?php if ($paid): $o = $result['order']; ?>
      <div class="icon">✅</div>
      <h2>Payment successful</h2>
      <p><?= e(Money::format((int) $o['amount_kobo'], $o['currency'])) ?> for <strong><?= e($o['product']) ?></strong> via <?= e($o['channel']) ?>.</p>
      <p>Reference: <code><?= e($o['reference']) ?></code>. A receipt has been sent to <?= e($o['email']) ?>.</p>
    <?php elseif ($result): ?>
      <div class="icon">❌</div><h2>Payment not completed</h2>
      <p><?= e($result['order']['gateway_response'] ?? 'The payment was not successful.') ?></p>
    <?php else: ?>
      <div class="icon">⏳</div><h2>Confirming payment</h2><p><?= e($error) ?></p>
    <?php endif; ?>
    <p><a href="index.php">Back to shop</a></p>
  </div>
</main>
</body>
</html>
