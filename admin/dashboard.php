<?php
$adminPageTitle = '仪表盘';

// 统计
$posts = load_posts();
$published = array_filter($posts, function($p) { return ($p['status'] ?? 0) == 1; });
$comments = load_comments();
$pending = array_filter($comments, function($c) { return $c['status'] == 0; });

$latestPosts = array_slice($posts, 0, 5);
$latestComments = array_slice($comments, 0, 5);

// 按时间排序
usort($latestComments, function($a, $b) {
    return ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0);
});

// 获取文章标题映射
$postTitles = [];
foreach ($posts as $p) {
    $postTitles[$p['id']] = $p['title'];
}
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
            <a href="index.php?page=about-edit">关于页面</a> | <a href="index.php?page=password">修改密码</a> | <a href="index.php?logout=1">退出</a>
        </div>
    </div>
    
    <h2><?php echo e($adminPageTitle); ?></h2>
    
    <table class="admin-table" style="width: 60%; margin-bottom: 20px;">
        <tr><th colspan="2">站点统计</th></tr>
        <tr><td>文章总数</td><td><?php echo count($posts); ?></td></tr>
        <tr><td>已发布</td><td><?php echo count($published); ?></td></tr>
        <tr><td>评论总数</td><td><?php echo count($comments); ?></td></tr>
        <tr><td>待审核评论</td><td><?php echo count($pending) ? '<strong style="color:red">' . count($pending) . '</strong>' : 0; ?></td></tr>
    </table>
    
    <h3>最新文章</h3>
    <table class="admin-table">
        <tr><th>标题</th><th>状态</th><th>发布时间</th><th>操作</th></tr>
        <?php foreach ($latestPosts as $p): ?>
        <tr>
            <td><a href="../index.php?page=post&id=<?php echo $p['id']; ?>" target="_blank"><?php echo e($p['title']); ?></a></td>
            <td><?php echo ($p['status'] ?? 0) ? '已发布' : '草稿'; ?></td>
            <td><?php echo format_date($p['created_at']); ?></td>
            <td><a href="index.php?page=post-edit&id=<?php echo $p['id']; ?>">编辑</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <h3>最新评论</h3>
    <table class="admin-table">
        <tr><th>评论者</th><th>内容</th><th>文章</th><th>状态</th><th>时间</th></tr>
        <?php foreach ($latestComments as $c): ?>
        <tr>
            <td><?php echo e($c['author_name']); ?></td>
            <td><?php echo e(make_summary($c['content'], 40)); ?></td>
            <td><a href="../index.php?page=post&id=<?php echo $c['post_id']; ?>" target="_blank"><?php echo e($postTitles[$c['post_id']] ?? '未知'); ?></a></td>
            <td><?php echo $c['status'] ? '已审核' : '<span style="color:red">待审核</span>'; ?></td>
            <td><?php echo format_date($c['created_at']); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>
