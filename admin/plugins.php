<?php
require_once __DIR__ . '/_guard.php';
$adminPageTitle = '插件管理';

$msg = '';
$err = '';

// 启用 / 停用
if (isset($_GET['toggle'])) {
    if (!isset($_GET['token']) || !verify_csrf($_GET['token'] ?? '')) {
        die('安全验证失败');
    }
    $slug = $_GET['toggle'];
    $info = get_plugin_info($slug);
    if ($info === null) {
        $err = '插件不存在或名称不合法。';
    } else {
        $config = load_config();
        $actives = isset($config['active_plugins']) && is_array($config['active_plugins'])
            ? $config['active_plugins'] : [];
        if (is_plugin_active($slug)) {
            $actives = array_values(array_diff($actives, [$slug]));
            $msg = '已停用插件：「' . $info['name'] . '」。';
        } else {
            $actives[] = $slug;
            $msg = '已启用插件：「' . $info['name'] . '」。';
        }
        $config['active_plugins'] = $actives;
        save_config($config);
    }
}

$plugins = list_plugins();
$token = csrf_token();

include __DIR__ . '/_header.php';
?>

<?php if ($msg): ?>
    <div class="msg success"><?php echo e($msg); ?></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="msg error"><?php echo e($err); ?></div>
<?php endif; ?>

<?php if (empty($plugins)): ?>
    <p>未找到任何插件。请将插件文件夹放入 <code>plugins/</code> 目录。</p>
<?php else: ?>
    <table class="admin-table">
        <tr><th>插件</th><th>说明</th><th>版本</th><th>作者</th><th>状态 / 操作</th></tr>
        <?php foreach ($plugins as $p):
            $hasSettings = file_exists(PLUGINS_DIR . '/' . $p['slug'] . '/admin.php');
        ?>
        <tr>
            <td><strong><?php echo e($p['name']); ?></strong></td>
            <td><?php echo e($p['description']); ?></td>
            <td><?php echo e($p['version']); ?></td>
            <td><?php echo e($p['author']); ?></td>
            <td>
                <?php if (is_plugin_active($p['slug'])): ?>
                    <strong style="color:#2e7d32;">已启用</strong> |
                    <a href="index.php?page=plugins&toggle=<?php echo e($p['slug']); ?>&token=<?php echo $token; ?>" style="color:#c62828;">停用</a>
                <?php else: ?>
                    <span style="color:#999;">未启用</span> |
                    <a href="index.php?page=plugins&toggle=<?php echo e($p['slug']); ?>&token=<?php echo $token; ?>">启用</a>
                <?php endif; ?>
                <?php if ($hasSettings): ?>
                    | <a href="plugin-settings.php?slug=<?php echo urlencode($p['slug']); ?>">设置</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <p style="font-size:13px;color:#666;">
        安装新插件：将插件文件夹（含 <code>plugin.json</code> 与 <code>plugin.php</code>）放入站点根目录
        <code>plugins/</code> 下即可在此页启用。插件可在 <code>plugin.php</code> 中通过
        <code>add_action()</code> / <code>add_filter()</code> 扩展系统；
        若插件自带 <code>admin.php</code>，启用后「设置」一列会提供独立的设置页面。
    </p>
<?php endif; ?>

<?php include __DIR__ . '/_footer.php'; ?>
