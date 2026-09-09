<?php
require_once __DIR__ . '/_guard.php';
$adminPageTitle = '评论管理';

$msg = '';

// 审核
if (isset($_GET['approve'])) {
    if (!isset($_GET['token']) || !verify_csrf($_GET['token'] ?? '')) {
        die('安全验证失败');
    }
    $id = (int)$_GET['approve'];
    $comments = load_comments();
    $approvedComment = null;
    foreach ($comments as &$c) {
        if ($c['id'] == $id && (int)$c['status'] !== 1) {
            $c['status'] = 1;
            $approvedComment = $c;
        }
    }
    unset($c);
    save_comments($comments);
    if ($approvedComment) {
        recount_comment_count((int)$approvedComment['post_id']);
        $approvedPost = load_post((int)$approvedComment['post_id']);
        if ($approvedPost) {
            qm_notify_mentions($approvedComment, $approvedPost);
            qm_notify_reply($approvedComment, $approvedPost);
        }
    }
    $msg = '评论已通过审核。';
}

// 删除（含其全部子回复）
if (isset($_GET['delete'])) {
    if (!isset($_GET['token']) || !verify_csrf($_GET['token'] ?? '')) {
        die('安全验证失败');
    }
    $id = (int)$_GET['delete'];
    $all = load_comments();
    $postId = null;
    foreach ($all as $c) {
        if ((int)$c['id'] === $id) { $postId = (int)$c['post_id']; break; }
    }
    $toDelete = delete_comment_tree($all, $id);
    $kept = array_filter($all, function ($c) use ($toDelete) {
        return !in_array((int)$c['id'], $toDelete, true);
    });
    save_comments(array_values($kept));

    if ($postId) {
        recount_comment_count($postId);
    }
    $msg = '评论及其子回复已删除（共 ' . count($toDelete) . ' 条）。';
}

$status = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;
$comments = load_comments();
if ($status !== null) {
    $comments = array_filter($comments, function ($c) use ($status) { return $c['status'] == $status; });
}
usort($comments, function ($a, $b) {
    return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
});

$posts = load_posts();
$postTitles = [];
foreach ($posts as $p) {
    $postTitles[$p['id']] = $p['title'];
}
$token = csrf_token();
$filterQuery = $status === null ? '' : '&status=' . $status;

include __DIR__ . '/_header.php';
?>

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
        <td><?php echo e(make_summary($c['content'], 50)); ?>
            <?php if (!empty($c['parent_id'])): ?><br><span style="color:#888;font-size:12px;">↩ 回复 #<?php echo (int)$c['parent_id']; ?></span><?php endif; ?>
        </td>
        <td><a href="../index.php?page=post&id=<?php echo $c['post_id']; ?>" target="_blank"><?php echo e($postTitles[$c['post_id']] ?? '未知'); ?></a></td>
        <td><?php echo $c['status'] ? '已审核' : '<span style="color:red">待审核</span>'; ?></td>
        <td><?php echo format_date($c['created_at']); ?></td>
        <td>
            <?php if (!$c['status']): ?>
            <a href="index.php?page=comments&approve=<?php echo $c['id']; ?>&token=<?php echo $token . $filterQuery; ?>">通过</a> |
            <?php endif; ?>
            <a href="index.php?page=comments&delete=<?php echo $c['id']; ?>&token=<?php echo $token . $filterQuery; ?>" onclick="return confirm('确定删除？')" style="color:red">删除</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<?php include __DIR__ . '/_footer.php'; ?>
