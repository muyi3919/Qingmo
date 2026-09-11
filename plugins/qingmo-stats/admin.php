<?php
/**
 * 插件设置页 + 统计报表：访问统计
 */
if (!defined('QM_BOOT')) { exit('Access denied'); }

$ok = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_stats'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $ok = '';
        $err = '安全验证失败，请刷新页面重试。';
    } else {
        qm_stats_save(array_merge(qm_stats_data(), ['config' => [
            'enabled'     => (int)($_POST['enabled'] ?? 0),
            'count_admin' => (int)($_POST['count_admin'] ?? 0),
            'sidebar'     => (int)($_POST['sidebar'] ?? 0),
            'keep_days'   => max(7, (int)($_POST['keep_days'] ?? 120)),
        ]]));
        $ok = '设置已保存。';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_stats'])) {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        qm_stats_save(['config' => qm_stats_data()['config']]);
        $ok = '统计数据已清空。';
    }
}

$data   = qm_stats_data();
$config = $data['config'] ?? [];
$today  = qm_stats_today();
$yest   = qm_stats_yesterday();
$d30    = qm_stats_range_total(30);
$online = qm_stats_online_count();
$days   = qm_stats_days(30);
$maxPv  = 1;
foreach ($days as $v) { $maxPv = max($maxPv, (int)$v['pv']); }
$refs   = qm_stats_top($data['refs'] ?? [], 10);
$posts  = qm_stats_top_posts(10);
$osTop  = qm_stats_top($data['os'] ?? [], 8);
$brTop  = qm_stats_top($data['browsers'] ?? [], 8);
$recent = array_slice($data['recent'] ?? [], 0, 20);
$sumRef = array_sum($refs) ?: 1;
$sumOs  = array_sum($osTop) ?: 1;
$sumBr  = array_sum($brTop) ?: 1;
?>

<?php if (!empty($ok)): ?><div class="msg success"><?php echo e($ok); ?></div><?php endif; ?>
<?php if (!empty($err)): ?><div class="msg error"><?php echo e($err); ?></div><?php endif; ?>

<!-- ============ 概览 ============ -->
<div style="display:flex;flex-wrap:wrap;gap:10px;margin:10px 0 18px;">
    <?php
    $cards = [
        ['今日访问', (int)$today['pv'], (int)$today['uv'] . ' 人'],
        ['昨日访问', (int)$yest['pv'], (int)$yest['uv'] . ' 人'],
        ['近 30 天', (int)$d30['pv'], (int)$d30['uv'] . ' 人'],
        ['总访问', (int)$data['pv'], '起于 ' . ($data['since'] ? date('Y-m-d', (int)$data['since']) : '—')],
        ['当前在线', $online, '近 5 分钟'],
    ];
    foreach ($cards as $c): ?>
        <div style="flex:1 1 150px;background:#fff;border:1px solid #e6e6e6;border-radius:10px;padding:12px 14px;">
            <div style="font-size:12px;color:#888;"><?php echo e($c[0]); ?></div>
            <div style="font-size:24px;font-weight:700;color:#333;line-height:1.3;"><?php echo (int)$c[1]; ?></div>
            <div style="font-size:12px;color:#aaa;"><?php echo e($c[2]); ?></div>
        </div>
    <?php endforeach; ?>
</div>

<!-- ============ 近 30 天趋势 ============ -->
<h3>近 30 天访问趋势</h3>
<div style="display:flex;align-items:flex-end;gap:3px;height:120px;background:#fafafa;border:1px solid #eee;border-radius:10px;padding:10px;">
    <?php foreach ($days as $day => $v):
        $h = (int)round(((int)$v['pv'] / $maxPv) * 90);
        if ((int)$v['pv'] > 0 && $h < 3) $h = 3;
    ?>
        <div title="<?php echo e($day . '：PV ' . (int)$v['pv'] . ' / UV ' . (int)$v['uv']); ?>"
             style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;height:100%;">
            <div style="height:<?php echo $h; ?>px;background:#4a90d9;border-radius:2px 2px 0 0;"></div>
        </div>
    <?php endforeach; ?>
</div>
<div style="display:flex;justify-content:space-between;font-size:11px;color:#aaa;margin-top:4px;">
    <span><?php echo date('n月j日', time() - 29 * 86400); ?></span>
    <span>今天 <?php echo date('n月j日'); ?>（柱子高度 = 当天 PV，悬停看 UV）</span>
</div>

<!-- ============ 来源 / 热门文章 ============ -->
<div style="display:flex;flex-wrap:wrap;gap:18px;margin-top:22px;">
    <div style="flex:1 1 320px;">
        <h3>访问来源 Top 10</h3>
        <?php if ($refs): ?>
            <table class="admin-table">
                <tr><th>来源</th><th>次数</th><th style="width:40%;">占比</th></tr>
                <?php foreach ($refs as $k => $n): ?>
                <tr>
                    <td><?php echo e($k); ?></td>
                    <td><?php echo (int)$n; ?></td>
                    <td>
                        <div style="background:#eee;border-radius:4px;height:10px;">
                            <div style="width:<?php echo (int)round($n / $sumRef * 100); ?>%;background:#4a90d9;height:10px;border-radius:4px;"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?><p style="color:#888;">暂无数据。</p><?php endif; ?>
    </div>

    <div style="flex:1 1 320px;">
        <h3>热门文章 Top 10</h3>
        <?php if ($posts): ?>
            <table class="admin-table">
                <tr><th>文章</th><th>浏览</th></tr>
                <?php foreach ($posts as $p): ?>
                <tr>
                    <td><a href="../index.php?page=post&id=<?php echo $p['id']; ?>" target="_blank"><?php echo e($p['title']); ?></a></td>
                    <td><?php echo $p['hits']; ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?><p style="color:#888;">暂无数据（文章页被访问后开始统计）。</p><?php endif; ?>
    </div>
</div>

<!-- ============ 系统 / 浏览器 ============ -->
<div style="display:flex;flex-wrap:wrap;gap:18px;margin-top:22px;">
    <div style="flex:1 1 320px;">
        <h3>操作系统占比</h3>
        <?php foreach ($osTop as $k => $n): ?>
            <div style="margin-bottom:6px;font-size:13px;">
                <div style="display:flex;justify-content:space-between;"><span><?php echo e($k); ?></span><span style="color:#888;"><?php echo (int)$n; ?>（<?php echo round($n / $sumOs * 100, 1); ?>%）</span></div>
                <div style="background:#eee;border-radius:4px;height:8px;"><div style="width:<?php echo (int)round($n / $sumOs * 100); ?>%;background:#67b168;height:8px;border-radius:4px;"></div></div>
            </div>
        <?php endforeach; ?>
        <?php if (!$osTop): ?><p style="color:#888;">暂无数据。</p><?php endif; ?>
    </div>

    <div style="flex:1 1 320px;">
        <h3>浏览器占比</h3>
        <?php foreach ($brTop as $k => $n): ?>
            <div style="margin-bottom:6px;font-size:13px;">
                <div style="display:flex;justify-content:space-between;"><span><?php echo e($k); ?></span><span style="color:#888;"><?php echo (int)$n; ?>（<?php echo round($n / $sumBr * 100, 1); ?>%）</span></div>
                <div style="background:#eee;border-radius:4px;height:8px;"><div style="width:<?php echo (int)round($n / $sumBr * 100); ?>%;background:#d98b4a;height:8px;border-radius:4px;"></div></div>
            </div>
        <?php endforeach; ?>
        <?php if (!$brTop): ?><p style="color:#888;">暂无数据。</p><?php endif; ?>
    </div>
</div>

<!-- ============ 最近访问 ============ -->
<h3 style="margin-top:22px;">最近访问（最新 20 条）</h3>
<?php if ($recent): ?>
    <table class="admin-table">
        <tr><th>时间</th><th>页面</th><th>系统</th><th>浏览器</th><th>来源</th><th>访客</th></tr>
        <?php foreach ($recent as $r): ?>
        <tr>
            <td><?php echo format_date($r['t'] ?? 0); ?></td>
            <td><?php echo e($r['path'] ?? ''); ?></td>
            <td><?php echo e($r['os'] ?? ''); ?></td>
            <td><?php echo e($r['browser'] ?? ''); ?></td>
            <td><?php echo e($r['ref'] ?? ''); ?></td>
            <td style="color:#aaa;font-size:12px;">#<?php echo e($r['visitor'] ?? ''); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
<?php else: ?><p style="color:#888;">还没有访问记录。</p><?php endif; ?>

<!-- ============ 设置 ============ -->
<h3 style="margin-top:22px;">统计设置</h3>
<form method="post" class="admin-form" style="max-width:560px;">
    <?php csrf_field(); ?>
    <label>开启访问统计</label>
    <select name="enabled">
        <option value="1" <?php echo ($config['enabled'] ?? 1) == 1 ? 'selected' : ''; ?>>开启</option>
        <option value="0" <?php echo ($config['enabled'] ?? 1) == 0 ? 'selected' : ''; ?>>关闭（停止采集）</option>
    </select>

    <label>是否统计管理员自己的访问</label>
    <select name="count_admin">
        <option value="0" <?php echo ($config['count_admin'] ?? 0) == 0 ? 'selected' : ''; ?>>不统计（推荐）</option>
        <option value="1" <?php echo ($config['count_admin'] ?? 0) == 1 ? 'selected' : ''; ?>>统计</option>
    </select>

    <label>侧栏「站点统计」小部件</label>
    <select name="sidebar">
        <option value="1" <?php echo ($config['sidebar'] ?? 1) == 1 ? 'selected' : ''; ?>>显示</option>
        <option value="0" <?php echo ($config['sidebar'] ?? 1) == 0 ? 'selected' : ''; ?>>不显示</option>
    </select>

    <label>数据保留天数</label>
    <input type="number" name="keep_days" min="7" max="3650" value="<?php echo (int)($config['keep_days'] ?? 120); ?>">
    <p style="font-size:12px;color:#888;margin:2px 0 0;">超过天数的每日数据会自动清理；总访问量与来源/系统占比为累计值。</p>

    <p><input type="submit" name="save_stats" value="保存设置"></p>
</form>

<form method="post" class="admin-form" style="max-width:560px;margin-top:10px;">
    <?php csrf_field(); ?>
    <p style="font-size:13px;color:#a33;">清空会删除全部统计数据（不影响文章、评论、留言）。</p>
    <p><input type="submit" name="reset_stats" value="清空统计数据" onclick="return confirm('确定清空全部统计数据？')"></p>
</form>
