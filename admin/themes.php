<?php
require_once __DIR__ . '/_guard.php';
$adminPageTitle = '主题管理';

$msg = '';
$err = '';

// 启用主题
if (isset($_GET['activate'])) {
    if (!isset($_GET['token']) || !verify_csrf($_GET['token'] ?? '')) {
        die('安全验证失败');
    }
    $slug = $_GET['activate'];
    if (!preg_match('/^[a-zA-Z0-9_\-]{1,64}$/', $slug) || !theme_exists($slug)) {
        $err = '主题不存在或名称不合法。';
    } else {
        $config = load_config();
        $config['theme'] = $slug;
        save_config($config);
        $msg = '已启用主题：「' . get_theme_info($slug)['name'] . '」。';
    }
}

$current = get_active_theme();
$themes = list_themes();
$token = csrf_token();

include __DIR__ . '/_header.php';
?>

<?php if ($msg): ?>
    <div class="msg success"><?php echo e($msg); ?></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="msg error"><?php echo e($err); ?></div>
<?php endif; ?>

<?php if (empty($themes)): ?>
    <p>未找到任何主题。请将主题文件夹放入 <code>themes/</code> 目录。</p>
<?php else: ?>
    <table class="admin-table">
        <tr><th>主题</th><th>说明</th><th>版本</th><th>作者</th><th>状态 / 操作</th></tr>
        <?php foreach ($themes as $t): ?>
        <tr>
            <td>
                <strong><?php echo e($t['name']); ?></strong>
                <?php if ($t['has_style']): ?>
                    <br><a href="../themes/<?php echo e($t['slug']); ?>/style.css" target="_blank">查看样式表</a>
                <?php else: ?>
                    <br><span style="color:#888;">（使用默认 assets/style.css）</span>
                <?php endif; ?>
            </td>
            <td><?php echo e($t['description']); ?></td>
            <td><?php echo e($t['version']); ?></td>
            <td><?php echo e($t['author']); ?></td>
            <td>
                <?php if ($t['slug'] === $current): ?>
                    <strong style="color:#2e7d32;">当前启用</strong>
                <?php else: ?>
                    <a href="index.php?page=themes&activate=<?php echo e($t['slug']); ?>&token=<?php echo $token; ?>">启用</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <p style="font-size:13px;color:#666;">
        安装新主题：将主题文件夹（含 <code>theme.php</code> 与 <code>style.css</code>）放入站点根目录
        <code>themes/</code> 下即可在此页启用。
    </p>
<?php endif; ?>

<?php include __DIR__ . '/_footer.php'; ?>
