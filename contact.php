<?php
session_start();
include __DIR__ . '/includes/headerFooter.php';
require_once __DIR__ . '/includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if (!$email || !$message) {
        $_SESSION['flash_error'] = 'Please provide email and message.';
        header('Location: contact.php');
        exit;
    }
    $stmt = $pdo->prepare('INSERT INTO contacts (name, email, message) VALUES (?, ?, ?)');
    $stmt->execute([$name, $email, $message]);
    $_SESSION['flash_success'] = 'Message received. Thank you!';
    header('Location: contact.php');
    exit;
}
?>
<div class="container py-4">
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm p-4 mb-4" style="border-radius:10px;">
        <h4>📬 Contact Us</h4>
        <form method="post" class="mt-3">
            <div class="mb-3"><input class="form-control" name="name" placeholder="Your name"></div>
            <div class="mb-3"><input class="form-control" name="email" placeholder="Your email" required></div>
            <div class="mb-3"><textarea class="form-control" name="message" rows="5" placeholder="Message" required></textarea></div>
            <div><button class="btn btn-primary">Send Message</button></div>
        </form>
    </div>
</div>

<footer class="bg-dark text-white text-center py-3 mt-4">
  <div class="container position-relative">
    <p class="mb-0">&copy; <?= date('Y') ?> Heritage Explorer</p>
    <a href="/Heritage-Database-Project/admin/login.php" 
       class="text-white-50 small position-absolute bottom-0 end-0 me-2 mb-1"
       style="font-size: 0.75rem; text-decoration: none;">
       Admin Login
    </a>
  </div>
</footer>
</body>
</html>
