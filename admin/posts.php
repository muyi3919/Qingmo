<?php
$adminPageTitle = '文章管理';

// 删除
if (isset($_GET['delete'])) {
    if (!isset($_GET['token']) || !verify_csrf($_GET['token'] ?? '')) {
        die('安全验证失败');
    }
    $id = (int)$_GET['delete'];
    delete_post($id);
    refresh_all_categories();
    $msg = '文章已删除。';
}

$posts = load_posts();
$perPage = 15;
$p = (int)($_GET['page_num'] ?? 1);
$total = count($posts);
$pg = paginate($total, $p, $perPage);
$posts = array_slice($posts, $pg['offset'], $pg['perPage']);

$cats = load_categories();
$catMap = array_column($cats, 'name', 'id');
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
    
    <h2><?php echo e($adminPageTitle); ?> <a href="index.php?page=post-edit" style="font-size: 13px; font-weight: normal;">[写文章]</a></h2>
    
    <?php if (isset($msg)): ?>
        <div class="msg success"><?php echo e($msg); ?></div>
    <?php endif; ?>
    
    <table class="admin-table">
        <tr>
            <th>ID</th><th>标题</th><th>分类</th><th>状态</th><th>阅读</th><th>评论</th><th>发布时间</th><th>操作</th>
        </tr>
        <?php foreach ($posts as $p): ?>
        <tr>
            <td><?php echo $p['id']; ?></td>
            <td><a href="../index.php?page=post&id=<?php echo $p['id']; ?>" target="_blank"><?php echo e($p['title']); ?></a></td>
            <td><?php echo e($catMap[$p['category_id']] ?? '未分类'); ?></td>
            <td><?php echo ($p['status'] ?? 0) ? '已发布' : '草稿'; ?></td>
            <td><?php echo $p['view_count'] ?? 0; ?></td>
            <td><?php echo $p['comment_count'] ?? 0; ?></td>
            <td><?php echo format_date($p['created_at']); ?></td>
            <td>
                <a href="index.php?page=post-edit&id=<?php echo $p['id']; ?>">编辑</a> | 
                <a href="index.php?page=posts&delete=<?php echo $p['id']; ?>" onclick="return confirm('确定删除？')" style="color:red">删除</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <?php echo pagination_html($pg, 'index.php?page=posts'); ?>
</div>
</body>
</html>
