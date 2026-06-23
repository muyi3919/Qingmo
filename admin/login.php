<?php
require_once '../includes/functions.php';
session_start();

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = '安全验证失败，请刷新页面重试。';
    } else {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    $users = load_users();
    foreach ($users as $u) {
        if ($u['username'] === $username && password_verify($password, $u['password'])) {
            $_SESSION['admin_id'] = $u['id'];
            $_SESSION['admin_username'] = $u['username'];
            header('Location: index.php');
            exit;
        }
    }
    $error = '用户名或密码错误。';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>登录 - <?php echo e(get_setting('site_title')); ?></title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="admin-login">
    <h2>轻墨 · 管理后台</h2>
    <?php if ($error): ?>
        <div class="msg error"><?php echo e($error); ?></div>
    <?php endif; ?>
    <form method="post">
        <?php csrf_field(); ?>
        <label>用户名</label>
        <input type="text" name="username" required autofocus>
        <label>密码</label>
        <input type="password" name="password" required>
        <p style="margin-top: 10px;"><input type="submit" value="登录"></p>
    </form>
    <p style="font-size: 12px; color: #666;"><a href="../index.php">返回首页</a></p>
</div>
</body>
</html>
