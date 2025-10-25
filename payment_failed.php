<?php
require_once __DIR__ . '/includes/headerFooter.php';
?>
<div class="text-center py-5">
  <div class="card mx-auto shadow" style="max-width:500px;">
    <div class="card-body">
      <h2 class="text-danger">❌ Payment Failed</h2>
      <p>There was an error while processing your payment.</p>
      <a href="payment_process.php?booking_id=<?= htmlspecialchars($_GET['booking_id'] ?? '') ?>" class="btn btn-outline-primary mt-3">Try Again</a>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/headerFooter.php'; ?>
