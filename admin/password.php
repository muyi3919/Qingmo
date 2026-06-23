<?php
$adminPageTitle = '修改密码';

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $err = '安全验证失败，请刷新页面重试。';
    } else {
        $old = trim($_POST['old_password'] ?? '');
        $new = trim($_POST['new_password'] ?? '');
        $confirm = trim($_POST['confirm_password'] ?? '');

        if (empty($old) || empty($new) || empty($confirm)) {
            $err = '请填写所有字段。';
        } elseif ($new !== $confirm) {
            $err = '两次输入的新密码不一致。';
        } elseif (strlen($new) < 6) {
            $err = '新密码至少需要6位。';
        } else {
            $users = load_users();
            $found = false;
            foreach ($users as &$u) {
                if ($u['id'] == $_SESSION['admin_id']) {
                    if (!password_verify($old, $u['password'])) {
                        $err = '当前密码错误。';
                    } else {
                        $u['password'] = password_hash($new, PASSWORD_DEFAULT);
                        $found = true;
                    }
                    break;
                }
            }
            if ($found && empty($err)) {
                save_users($users);
                $msg = '密码修改成功！';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?php echo e($adminPageTitle); ?> - 轻墨</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="container">
    <div class="header">
        <h1>轻墨 · 后台</h1>
        <div class="nav">
            <a href="index.php">仪表盘</a> | 
            <a href="index.php?page=posts">文章管理</a> | 
            <a href="index.php?page=categories">分类管理</a> | 
            <a href="index.php?page=comments">评论管理</a> | 
            <a href="index.php?page=settings">站点设置</a> | 
            <a href="index.php?page=about-edit">关于页面</a> | 
            <a href="index.php?page=password">修改密码</a> | 
            <a href="index.php?logout=1">退出</a>
        </div>
    </div>

    <h2><?php echo e($adminPageTitle); ?></h2>

    <?php if ($msg): ?>
        <div class="msg success"><?php echo e($msg); ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
        <div class="msg error"><?php echo e($err); ?></div>
    <?php endif; ?>

    <form method="post" class="admin-form" style="max-width:400px;">
        <?php csrf_field(); ?>
        <label>当前密码</label>
        <input type="password" name="old_password" required>

        <label>新密码</label>
        <input type="password" name="new_password" required>

        <label>确认新密码</label>
        <input type="password" name="confirm_password" required>

        <p><input type="submit" value="修改密码"></p>
    </form>
</div>
</body>
</html>
