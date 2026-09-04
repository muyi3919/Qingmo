<?php
require_once __DIR__ . '/_guard.php';
$adminPageTitle = '文章管理';

$msg = '';

// 插件扩展点：文章管理页顶部动作（用于置顶切换等请求，鉴权后、输出前执行）
do_action('qm_admin_posts_head');

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
    <?php foreach ($posts as $p):
        // 插件扩展点：每行操作区（编辑/删除 之后可追加，如「置顶」）
        $rowActions = '<a href="index.php?page=post-edit&id=' . $p['id'] . '">编辑</a> | '
            . '<a href="index.php?page=posts&delete=' . $p['id'] . '&token=' . $token . '" onclick="return confirm(\'确定删除？\')" style="color:red">删除</a>';
        $rowActions = apply_filters('qm_post_row_actions', $rowActions, $p, $token);
    ?>
    <tr>
        <td><?php echo $p['id']; ?></td>
        <td>
            <?php if (!empty($p['sticky'])): ?><strong style="color:#c62828;" title="置顶中">[置顶]</strong> <?php endif; ?>
            <a href="../index.php?page=post&id=<?php echo $p['id']; ?>" target="_blank"><?php echo e($p['title']); ?></a>
        </td>
        <td><?php echo e($catMap[$p['category_id']] ?? '未分类'); ?></td>
        <td><?php echo ($p['status'] ?? 0) ? '已发布' : '草稿'; ?></td>
        <td><?php echo $p['view_count'] ?? 0; ?></td>
        <td><?php echo $p['comment_count'] ?? 0; ?></td>
        <td><?php echo format_date($p['created_at']); ?></td>
        <td><?php echo $rowActions; ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<?php echo pagination_html($pg, 'index.php?page=posts'); ?>

<?php include __DIR__ . '/_footer.php'; ?>
