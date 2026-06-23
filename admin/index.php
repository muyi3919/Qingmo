<?php
require_once '../includes/functions.php';
session_start();
require_login();

$page = $_GET['page'] ?? 'dashboard';
$allowed = ['dashboard', 'posts', 'post-edit', 'categories', 'comments', 'settings', 'password', 'about-edit'];
if (!in_array($page, $allowed)) {
    $page = 'dashboard';
}

// 注销
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

include $page . '.php';
