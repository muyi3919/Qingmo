<?php
$adminPageTitle = '评论管理';

$msg = '';

// 审核
if (isset($_GET['approve'])) {
    if (!isset($_GET['token']) || !verify_csrf($_GET['token'] ?? '')) {
        die('安全验证失败');
    }
    $id = (int)$_GET['approve'];
    $comments = load_comments();
    foreach ($comments as &$c) {
        if ($c['id'] == $id) $c['status'] = 1;
    }
    save_comments($comments);
    $msg = '评论已通过审核。';
}

// 删除
if (isset($_GET['delete'])) {
    if (!isset($_GET['token']) || !verify_csrf($_GET['token'] ?? '')) {
        die('安全验证失败');
    }
    $id = (int)$_GET['delete'];
    $comments = load_comments();
    $postId = null;
    foreach ($comments as $c) {
        if ($c['id'] == $id) { $postId = $c['post_id']; break; }
    }
    $comments = array_filter($comments, function($c) use ($id) { return $c['id'] != $id; });
    save_comments(array_values($comments));
    
    if ($postId) {
        $post = load_post($postId);
        if ($post) {
            $approved = array_filter(load_comments($postId), function($c) { return $c['status'] == 1; });
            $post['comment_count'] = count($approved);
            save_post($postId, $post);
        }
    }
    $msg = '评论已删除。';
}

$status = isset($_GET['status']) ? (int)$_GET['status'] : null;
$comments = load_comments();
if ($status !== null) {
    $comments = array_filter($comments, function($c) use ($status) { return $c['status'] == $status; });
}
usort($comments, function($a, $b) {
    return ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0);
});

$posts = load_posts();
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
            <a href="index.php?logout=1">退出</a>
        </div>
    </div>
    
    <h2><?php echo e($adminPageTitle); ?></h2>
    <p>
        <a href="index.php?page=comments">全部</a> | 
        <a href="index.php?page=comments&status=1">已审核</a> | 
        <a href="index.php?page=comments&status=0">待审核</a>
    </p>
    
    <?php if ($msg): ?>
        <div class="msg success"><?php echo e($msg); ?></div>
    <?php endif; ?>
    
    <table class="admin-table">
        <tr>
            <th>ID</th><th>评论者</th><th>邮箱</th><th>内容</th><th>文章</th><th>状态</th><th>时间</th><th>操作</th>
        </tr>
        <?php foreach ($comments as $c): ?>
        <tr>
            <td><?php echo $c['id']; ?></td>
            <td><?php echo e($c['author_name']); ?></td>
            <td><?php echo e($c['author_email']); ?></td>
            <td><?php echo e(make_summary($c['content'], 50)); ?></td>
            <td><a href="../index.php?page=post&id=<?php echo $c['post_id']; ?>" target="_blank"><?php echo e($postTitles[$c['post_id']] ?? '未知'); ?></a></td>
            <td><?php echo $c['status'] ? '已审核' : '<span style="color:red">待审核</span>'; ?></td>
            <td><?php echo format_date($c['created_at']); ?></td>
            <td>
                <?php if (!$c['status']): ?>
                <a href="index.php?page=comments&approve=<?php echo $c['id']; ?>&status=<?php echo $status ?? ''; ?>">通过</a> | 
                <?php endif; ?>
                <a href="index.php?page=comments&delete=<?php echo $c['id']; ?>&status=<?php echo $status ?? ''; ?>" onclick="return confirm('确定删除？')" style="color:red">删除</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>
