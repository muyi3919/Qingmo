<?php
require_once __DIR__ . '/_guard.php';
$adminPageTitle = '友链管理';

$msg = '';
$err = '';

// 新增 / 编辑
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $err = '安全验证失败，请刷新页面重试。';
    } else {
        $name = trim($_POST['name'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $cat = trim($_POST['category'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        $id = (int)($_POST['id'] ?? 0);

        if ($name === '') {
            $err = '请输入站点名称。';
        } elseif ($url === '' || !preg_match('#^https?://#i', $url)) {
            $err = '请输入以 http:// 或 https:// 开头的有效网址。';
        } else {
            $data = ['name' => $name, 'url' => $url, 'description' => $desc, 'category' => $cat, 'icon' => $icon];
            if ($id) {
                update_link($id, $data);
                $msg = '友链已更新。';
            } else {
                add_link($data);
                $msg = '友链已添加。';
            }
        }
    }
}

// 删除
if (isset($_GET['delete'])) {
    if (!isset($_GET['token']) || !verify_csrf($_GET['token'] ?? '')) {
        die('安全验证失败');
    }
    delete_link((int)$_GET['delete']);
    $msg = '友链已删除。';
}

$links = load_links();
usort($links, function ($a, $b) {
    return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
});

$edit = null;
if (isset($_GET['edit'])) {
    foreach ($links as $l) {
        if ((int)$l['id'] === (int)$_GET['edit']) { $edit = $l; break; }
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

<form method="post" class="admin-form" style="margin-bottom: 22px;">
    <?php csrf_field(); ?>
    <input type="hidden" name="id" value="<?php echo $edit ? $edit['id'] : ''; ?>">
    <label>站点名称 *</label>
    <input type="text" name="name" value="<?php echo $edit ? e($edit['name']) : ''; ?>" required placeholder="例如：某某的博客">
    <label>站点地址 *（需带 http:// 或 https://）</label>
    <input type="url" name="url" value="<?php echo $edit ? e($edit['url']) : ''; ?>" required placeholder="https://example.com">
    <label>分类</label>
    <input type="text" name="category" value="<?php echo $edit ? e($edit['category'] ?? '') : ''; ?>" placeholder="例如：博客 / 朋友 / 其它（留空归入“其它”）">
    <label>图标地址（可选，展示在链接前的圆形小图）</label>
    <input type="url" name="icon" value="<?php echo $edit ? e($edit['icon'] ?? '') : ''; ?>" placeholder="https://example.com/favicon.png">
    <label>一句话介绍</label>
    <input type="text" name="description" value="<?php echo $edit ? e($edit['description'] ?? '') : ''; ?>" placeholder="可选，前台卡片中展示">
    <p><input type="submit" value="<?php echo $edit ? '保存修改' : '添加友链'; ?>"></p>
</form>

<table class="admin-table">
    <tr><th>ID</th><th>名称</th><th>分类</th><th>网址</th><th>介绍</th><th>操作</th></tr>
    <?php foreach ($links as $l): ?>
    <tr>
        <td><?php echo $l['id']; ?></td>
        <td>
            <?php if (!empty($l['icon'])): ?><img src="<?php echo e($l['icon']); ?>" alt="" style="width:16px;height:16px;vertical-align:-3px;margin-right:4px;" onerror="this.style.display='none'"><?php endif; ?>
            <?php echo e($l['name']); ?>
        </td>
        <td><?php echo e($l['category'] ?? '其它'); ?></td>
        <td><a href="<?php echo e($l['url']); ?>" target="_blank" rel="noopener nofollow"><?php echo e($l['url']); ?></a></td>
        <td><?php echo e($l['description'] ?? ''); ?></td>
        <td>
            <a href="index.php?page=links&edit=<?php echo $l['id']; ?>">编辑</a> |
            <a href="index.php?page=links&delete=<?php echo $l['id']; ?>&token=<?php echo $token; ?>" onclick="return confirm('确定删除该友链？')" style="color:red">删除</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<?php if (empty($links)): ?>
    <p style="color:#888;">暂无友链，先添加一条试试吧。</p>
<?php endif; ?>

<p style="font-size:13px;color:#666;margin-top:14px;">
    前台效果见 <a href="../index.php?page=links" target="_blank">友情链接页 →</a>；排序可在「站点设置 → 友链排序」选择按名称或随机；侧边栏展示前 10 条。
</p>

<?php include __DIR__ . '/_footer.php'; ?>
