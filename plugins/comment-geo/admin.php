<?php
/**
 * 插件设置页：评论归属地
 */
if (!defined('QM_BOOT')) { exit('Access denied'); }

$ok = '';
$err = '';
$lookupResult = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $err = '安全验证失败，请刷新页面重试。';
    } else {
        if (isset($_POST['save_geo'])) {
            $data = qm_geo_data();
            $data['config'] = [
                'online'     => (int)($_POST['online'] ?? 0),
                'ip_fallback'=> (int)($_POST['ip_fallback'] ?? 0),
                'cache_days' => max(0, (int)($_POST['cache_days'] ?? 30)),
            ];
            qm_geo_save($data);
            $ok = '设置已保存。';
        }
        if (isset($_POST['test_ip_btn'])) {
            $testIp = trim($_POST['test_ip'] ?? '');
            if ($testIp === '' || !filter_var($testIp, FILTER_VALIDATE_IP)) {
                $err = '请输入有效的 IP 地址。';
            } else {
                $found = qm_geo_lookup($testIp);
                $lookupResult = $found === null
                    ? '查询失败（网络不可用或该地址无归属地信息）。'
                    : '查询结果：' . implode(' · ', array_filter([
                        $found['country'], $found['region'], $found['city'],
                    ]));
            }
        }
    }
}

$config = qm_geo_data()['config'];
?>

<?php if ($ok): ?>
    <div class="msg success"><?php echo e($ok); ?></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="msg error"><?php echo e($err); ?></div>
<?php endif; ?>
<?php if ($lookupResult): ?>
    <div class="msg" style="background:#eef6ff;border-color:#9cc5f5;"><?php echo e($lookupResult); ?></div>
<?php endif; ?>

<form method="post" class="admin-form" style="max-width:560px;">
    <?php csrf_field(); ?>
    <label>在线查询归属地（ip-api.com）</label>
    <select name="online">
        <option value="1" <?php echo ($config['online'] ?? 1) == 1 ? 'selected' : ''; ?>>开启</option>
        <option value="0" <?php echo ($config['online'] ?? 1) == 0 ? 'selected' : ''; ?>>关闭（仅记录 IP）</option>
    </select>
    <p style="font-size:12px;color:#888;margin:2px 0 0;">开启时按评论者 IP 在线查归属地，结果本地缓存，避免反复请求。</p>

    <label>查不到归属地时显示 IP 前缀</label>
    <select name="ip_fallback">
        <option value="1" <?php echo ($config['ip_fallback'] ?? 1) == 1 ? 'selected' : ''; ?>>显示（例如 IP 114.114.*）</option>
        <option value="0" <?php echo ($config['ip_fallback'] ?? 1) == 0 ? 'selected' : ''; ?>>不显示</option>
    </select>

    <label>缓存天数</label>
    <input type="number" name="cache_days" min="0" max="365" value="<?php echo e($config['cache_days'] ?? 30); ?>">
    <p style="font-size:12px;color:#888;margin:2px 0 0;">同一 IP 在此天数内不再重复查询（0 表示永不缓存）。</p>

    <p><input type="submit" name="save_geo" value="保存设置"></p>
</form>

<h3 style="margin-top:22px;">测试查询</h3>
<form method="post" class="admin-form" style="max-width:560px;">
    <?php csrf_field(); ?>
    <label>输入一个公网 IP 测试归属地接口</label>
    <input type="text" name="test_ip" placeholder="例如 8.8.8.8 / 114.114.114.114">
    <p><input type="submit" name="test_ip_btn" value="查询测试"></p>
</form>
