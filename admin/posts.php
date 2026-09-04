<?php
require_once __DIR__ . '/_guard.php';
$adminPageTitle = '文章管理';

$msg = '';

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
$token = csrf_token();

include __DIR__ . '/_header.php';
?>

<p><a href="index.php?page=post-edit" class="btn-link">[写文章]</a></p>

<?php if ($msg): ?>
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
            <a href="index.php?page=posts&delete=<?php echo $p['id']; ?>&token=<?php echo $token; ?>" onclick="return confirm('确定删除？')" style="color:red">删除</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<?php echo pagination_html($pg, 'index.php?page=posts'); ?>

<?php include __DIR__ . '/_footer.php'; ?>
