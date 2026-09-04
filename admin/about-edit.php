<?php
require_once __DIR__ . '/_guard.php';
$adminPageTitle = '编辑关于页面';

$msg = '';
$err = '';

$about = load_about();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $err = '安全验证失败，请刷新页面重试。';
    } else {
        $data = [
            'title' => trim($_POST['title'] ?? '关于'),
            'content' => trim($_POST['content'] ?? ''),
        ];
        if ($data['content'] === '') {
            $err = '内容不能为空。';
        } else {
            save_about($data);
            $msg = '关于页面已保存。';
            $about = $data;
        }
    }
}

include __DIR__ . '/_header.php';
?>

<?php if ($msg): ?>
    <div class="msg success"><?php echo e($msg); ?></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="msg error"><?php echo e($err); ?></div>
<?php endif; ?>

<form method="post" class="admin-form">
    <?php csrf_field(); ?>
    <label>页面标题</label>
    <input type="text" name="title" value="<?php echo e($about['title'] ?? '关于'); ?>" required>

    <label>页面内容（支持 HTML）</label>
    <textarea name="content" required style="height:300px;"><?php echo e($about['content'] ?? ''); ?></textarea>

    <p><input type="submit" value="保存"></p>
</form>

<p style="margin-top:20px;"><a href="../index.php?page=about" target="_blank">预览效果 →</a></p>

<?php include __DIR__ . '/_footer.php'; ?>
