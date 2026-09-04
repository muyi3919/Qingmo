<?php
require_once __DIR__ . '/_guard.php';
$adminPageTitle = '分类管理';

$msg = '';
$err = '';

// 添加/编辑
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $err = '安全验证失败，请刷新页面重试。';
    } else {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $id = (int)($_POST['id'] ?? 0);

        if (empty($name)) {
            $err = '请输入分类名称。';
        } else {
            if (empty($slug)) $slug = slugify($name);
            $cats = load_categories();
            if ($id) {
                foreach ($cats as &$cat) {
                    if ($cat['id'] == $id) {
                        $cat['name'] = $name;
                        $cat['slug'] = $slug;
                        $cat['description'] = $desc;
                    }
                }
                unset($cat);
                $msg = '分类已更新。';
            } else {
                $newId = count($cats) > 0 ? max(array_column($cats, 'id')) + 1 : 1;
                $cats[] = ['id' => $newId, 'name' => $name, 'slug' => $slug, 'description' => $desc, 'post_count' => 0];
                $msg = '分类已添加。';
            }
            save_categories($cats);
            refresh_all_categories();
        }
    }
}

// 删除
if (isset($_GET['delete'])) {
    if (!isset($_GET['token']) || !verify_csrf($_GET['token'] ?? '')) {
        die('安全验证失败');
    }
    $delId = (int)$_GET['delete'];
    $posts = load_posts();
    $hasPost = false;
    foreach ($posts as $p) {
        if (($p['category_id'] ?? 0) == $delId) { $hasPost = true; break; }
    }
    if ($hasPost) {
        $err = '该分类下还有文章，无法删除。';
    } else {
        $cats = load_categories();
        $cats = array_filter($cats, function ($c) use ($delId) { return $c['id'] != $delId; });
        save_categories(array_values($cats));
        $msg = '分类已删除。';
    }
}

$categories = load_categories();
$edit = null;
if (isset($_GET['edit'])) {
    foreach ($categories as $c) {
        if ($c['id'] == (int)$_GET['edit']) { $edit = $c; break; }
    }
}
$token = csrf_token();

include __DIR__ . '/_header.php';
?>

<?php if ($msg): ?>
    <div class="msg success"><?php echo e($msg); ?></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="msg error"><?php echo e($err); ?></div>
<?php endif; ?>

<form method="post" class="admin-form" style="margin-bottom: 20px;">
    <?php csrf_field(); ?>
    <input type="hidden" name="id" value="<?php echo $edit ? $edit['id'] : ''; ?>">
    <label>分类名称</label>
    <input type="text" name="name" value="<?php echo $edit ? e($edit['name']) : ''; ?>" required>
    <label>Slug（URL别名）</label>
    <input type="text" name="slug" value="<?php echo $edit ? e($edit['slug']) : ''; ?>">
    <label>描述</label>
    <input type="text" name="description" value="<?php echo $edit ? e($edit['description']) : ''; ?>">
    <p><input type="submit" value="<?php echo $edit ? '保存修改' : '添加分类'; ?>"></p>
</form>

<table class="admin-table">
    <tr><th>ID</th><th>名称</th><th>Slug</th><th>描述</th><th>文章数</th><th>操作</th></tr>
    <?php foreach ($categories as $cat): ?>
    <tr>
        <td><?php echo $cat['id']; ?></td>
        <td><?php echo e($cat['name']); ?></td>
        <td><?php echo e($cat['slug']); ?></td>
        <td><?php echo e($cat['description']); ?></td>
        <td><?php echo $cat['post_count']; ?></td>
        <td>
            <a href="index.php?page=categories&edit=<?php echo $cat['id']; ?>">编辑</a> |
            <a href="index.php?page=categories&delete=<?php echo $cat['id']; ?>&token=<?php echo $token; ?>" onclick="return confirm('确定删除？')" style="color:red">删除</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<?php include __DIR__ . '/_footer.php'; ?>
