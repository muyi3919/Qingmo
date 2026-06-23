<?php
$adminPageTitle = '站点设置';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $msg = '安全验证失败，请刷新页面重试。';
    } else {
    $config = [
        'site_title' => trim($_POST['site_title'] ?? ''),
        'site_description' => trim($_POST['site_description'] ?? ''),
        'site_url' => trim($_POST['site_url'] ?? ''),
        'posts_per_page' => (int)($_POST['posts_per_page'] ?? 10),
        'allow_comments' => (int)($_POST['allow_comments'] ?? 1),
        'comment_moderation' => (int)($_POST['comment_moderation'] ?? 0),
    ];
        save_config($config);
        $msg = '设置已保存。';
    }
}

$settings = load_config();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?php echo e($adminPageTitle); ?> - 轻墨 · 后台</title>
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
            <a href="index.php?logout=1">退出</a>
        </div>
    </div>
    
    <h2><?php echo e($adminPageTitle); ?></h2>
    
    <?php if ($msg): ?>
        <div class="msg success"><?php echo e($msg); ?></div>
    <?php endif; ?>
    
    <form method="post" class="admin-form">
        <?php csrf_field(); ?>
        <label>站点标题</label>
        <input type="text" name="site_title" value="<?php echo e($settings['site_title'] ?? ''); ?>" required>
        
        <label>站点描述</label>
        <input type="text" name="site_description" value="<?php echo e($settings['site_description'] ?? ''); ?>">
        
        <label>站点URL</label>
        <input type="text" name="site_url" value="<?php echo e($settings['site_url'] ?? ''); ?>" placeholder="http://example.com">
        
        <label>每页文章数</label>
        <input type="number" name="posts_per_page" value="<?php echo e($settings['posts_per_page'] ?? 10); ?>">
        
        <label>允许评论</label>
        <select name="allow_comments">
            <option value="1" <?php echo ($settings['allow_comments'] ?? '1') == '1' ? 'selected' : ''; ?>>是</option>
            <option value="0" <?php echo ($settings['allow_comments'] ?? '1') == '0' ? 'selected' : ''; ?>>否</option>
        </select>
        
        <label>评论需审核</label>
        <select name="comment_moderation">
            <option value="1" <?php echo ($settings['comment_moderation'] ?? '0') == '1' ? 'selected' : ''; ?>>是</option>
            <option value="0" <?php echo ($settings['comment_moderation'] ?? '0') == '0' ? 'selected' : ''; ?>>否</option>
        </select>
        
        <p><input type="submit" value="保存设置"></p>
    </form>
</div>
</body>
</html>
