<?php
require_once '../includes/functions.php';
qm_session_start();
require_login();

// 标记：以下页面通过路由加载（页面文件可直接访问时的鉴权见各页面顶部）
if (!defined('QM_ADMIN_LOADED')) {
    define('QM_ADMIN_LOADED', true);
}

// 注销
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

$page = $_GET['page'] ?? 'dashboard';
$allowed = ['dashboard', 'posts', 'post-edit', 'categories', 'comments', 'links', 'themes', 'plugins', 'settings', 'password', 'about-edit', 'update'];
if (!in_array($page, $allowed)) {
    $page = 'dashboard';
}

include $page . '.php';
