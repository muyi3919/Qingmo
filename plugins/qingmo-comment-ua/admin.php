<?php
/**
 * 插件设置页：评论设备信息（UA）
 */
if (!defined('QM_BOOT')) { exit('Access denied'); }

$ok = '';
$err = '';
$testResult = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $err = '安全验证失败，请刷新页面重试。';
    } else {
        if (isset($_POST['save_ua'])) {
            qm_ua_save(['config' => [
                'show_raw'  => (int)($_POST['show_raw'] ?? 0),
                'store_raw' => (int)($_POST['store_raw'] ?? 0),
                'fa_cdn'    => (int)($_POST['fa_cdn'] ?? 0),
                'fa_icons'  => (int)($_POST['fa_icons'] ?? 0),
            ]]);
            $ok = '设置已保存。';
        }
        if (isset($_POST['test_ua_btn'])) {
            $sample = trim($_POST['test_ua'] ?? '');
            if ($sample === '') {
                $err = '请输入一段 User-Agent 再测试。';
            } else {
                $p = qm_ua_parse($sample);
                $testResult = '识别结果：' . $p['device'] . ' / ' . $p['os'] . ' / '
                    . $p['browser'] . ($p['version'] !== '' ? ' ' . $p['version'] : '');
            }
        }
    }
}

$config = qm_ua_data()['config'] ?? [];
$myUa = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
?>

<?php if ($ok): ?><div class="msg success"><?php echo e($ok); ?></div><?php endif; ?>
<?php if ($err): ?><div class="msg error"><?php echo e($err); ?></div><?php endif; ?>
<?php if ($testResult): ?>
    <div class="msg" style="background:#eef6ff;border-color:#9cc5f5;"><?php echo e($testResult); ?></div>
<?php endif; ?>

<form method="post" class="admin-form" style="max-width:560px;">
    <?php csrf_field(); ?>
    <label>系统 / 浏览器图标</label>
    <select name="fa_icons">
        <option value="1" <?php echo ($config['fa_icons'] ?? 1) == 1 ? 'selected' : ''; ?>>Font Awesome 图标（如 <i class="fa-brands fa-chrome"></i> / <i class="fa-brands fa-windows"></i>）</option>
        <option value="0" <?php echo ($config['fa_icons'] ?? 1) == 0 ? 'selected' : ''; ?>>用 emoji（💻 📱 🤖）</option>
    </select>

    <label>Font Awesome 样式表</label>
    <select name="fa_cdn">
        <option value="1" <?php echo ($config['fa_cdn'] ?? 1) == 1 ? 'selected' : ''; ?>>由本插件从 CDN 加载（jsdelivr，需访客能联网）</option>
        <option value="0" <?php echo ($config['fa_cdn'] ?? 1) == 0 ? 'selected' : ''; ?>>不加载（主题/自己已引入 FA，或想用 emoji 图标）</option>
    </select>
    <p style="font-size:12px;color:#888;margin:2px 0 0;">关闭 CDN 时会自动退回 emoji 图标，不影响功能。</p>

    <label>悬停显示原始 UA</label>
    <select name="show_raw">
        <option value="1" <?php echo ($config['show_raw'] ?? 1) == 1 ? 'selected' : ''; ?>>显示（鼠标悬停徽标可见完整 UA）</option>
        <option value="0" <?php echo ($config['show_raw'] ?? 1) == 0 ? 'selected' : ''; ?>>不显示</option>
    </select>

    <label>是否保存原始 UA</label>
    <select name="store_raw">
        <option value="1" <?php echo ($config['store_raw'] ?? 1) == 1 ? 'selected' : ''; ?>>保存（便于排查，最多 300 字符）</option>
        <option value="0" <?php echo ($config['store_raw'] ?? 1) == 0 ? 'selected' : ''; ?>>不保存（只留系统/浏览器/版本）</option>
    </select>
    <p style="font-size:12px;color:#888;margin:2px 0 0;">徽标只展示系统与浏览器版本；原始 UA 仅用于悬停查看或排查问题。</p>

    <p><input type="submit" name="save_ua" value="保存设置"></p>
</form>

<h3 style="margin-top:22px;">识别测试</h3>
<form method="post" class="admin-form" style="max-width:560px;">
    <?php csrf_field(); ?>
    <label>粘贴一段 User-Agent 测试识别效果</label>
    <input type="text" name="test_ua" value="<?php echo e($myUa); ?>" style="width:100%;">
    <p style="font-size:12px;color:#888;margin:2px 0 0;">默认填的是你当前浏览器（后台）的 UA，直接点「测试识别」即可。</p>
    <p><input type="submit" name="test_ua_btn" value="测试识别"></p>
</form>
