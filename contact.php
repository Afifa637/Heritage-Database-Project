<?php
// contact.php
session_start();
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
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Contact</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container"><a class="navbar-brand" href="/">Heritage Explorer</a></div>
    </nav>
    <div class="container py-4">
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['flash_success']);
                                                unset($_SESSION['flash_success']); ?></div>
        <?php endif; ?>
        <div class="card p-3">
            <h4>Contact Us</h4>
            <form method="post">
                <div class="mb-2"><input class="form-control" name="name" placeholder="Your name"></div>
                <div class="mb-2"><input class="form-control" name="email" placeholder="Your email" required></div>
                <div class="mb-2"><textarea class="form-control" name="message" rows="5" placeholder="Message" required></textarea></div>
                <div><button class="btn btn-primary">Send</button></div>
            </form>
        </div>
    </div>
</body>

</html>