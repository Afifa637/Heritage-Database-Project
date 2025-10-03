<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isVisitorLoggedIn() {
    return !empty($_SESSION['visitor_id']);
}

function requireVisitorLogin() {
    if (!isVisitorLoggedIn()) {
        header("Location: /visitor/login.php");
        exit;
    }
}

function isAdminLoggedIn() {
    return !empty($_SESSION['admin_logged_in']);
}

function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header("Location: /admin/login.php");
        exit;
    }
}
?>
