<?php
if (!defined('QM_BOOT')) { http_response_code(403); exit; }
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>安装 · 轻墨</title>
    <link rel="stylesheet" href="assets/install.css?v=1">
</head>
<body>
<main class="setup-card">
    <div class="brand"><span class="brand-mark" aria-hidden="true">墨</span> 轻墨 <span class="brand-en">QINGMO</span></div>
    <?php if ($installState === 'form'): ?>
        <p class="eyebrow">从这里，写下第一篇。</p>
        <h1>欢迎来到轻墨</h1>
        <p class="intro">创建管理员账号，开启属于你的博客。</p>
        <?php if ($installError !== ''): ?><div class="notice error" role="alert"><?php echo e($installError); ?></div><?php endif; ?>
        <form method="post">
            <?php csrf_field(); ?>
            <label for="username">管理员用户名</label>
            <input id="username" name="username" required minlength="3" maxlength="32" pattern="[A-Za-z0-9_\-]{3,32}" autocomplete="username" placeholder="给自己取个名字" aria-describedby="username-help" value="<?php echo e($installUser); ?>">
            <p class="help" id="username-help">3–32位字母、数字、下划线或短横线。</p>
            <label for="password">密码</label>
            <input id="password" type="password" name="password" required minlength="6" autocomplete="new-password" placeholder="至少6个字符" aria-describedby="password-help">
            <p class="help" id="password-help">建议组合使用字母、数字和符号。</p>
            <label for="confirm">确认密码</label>
            <input id="confirm" type="password" name="confirm" required minlength="6" autocomplete="new-password" placeholder="再输入一次密码">
            <button type="submit">创建我的博客 <span aria-hidden="true">→</span></button>
        </form>
        <p class="footnote">轻量、纯粹，专注每一次表达。</p>
    <?php else: ?>
        <div class="success-mark" aria-hidden="true">✓</div>
        <h1><?php echo $installState === 'success' ? '你的博客准备好了' : '轻墨已经安装完成'; ?></h1>
        <p class="intro"><?php echo $installState === 'success' ? '使用刚才创建的账号登录，开始写作吧。' : '使用已有的管理员账号登录，即可继续管理博客。'; ?></p>
        <?php if ($installState === 'success'): ?><div class="notice">管理员账号：<strong><?php echo e($installUser); ?></strong></div><?php endif; ?>
        <a class="primary-link" href="admin/login.php">进入后台 <span aria-hidden="true">→</span></a>
        <a class="back-link" href="index.php">查看我的博客</a>
    <?php endif; ?>
</main>
</body>
</html>
