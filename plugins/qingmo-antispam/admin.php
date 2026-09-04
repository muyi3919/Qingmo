<?php
/**
 * 插件设置页：反垃圾评论
 */
if (!defined('QM_BOOT')) { exit('Access denied'); }

$ok = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $err = '安全验证失败，请刷新页面重试。';
    } else {
        $data = qm_asp_data();
        $data['config'] = [
            'enabled'      => (int)($_POST['enabled'] ?? 0),
            'min_len'      => max(1, (int)($_POST['min_len'] ?? 2)),
            'min_interval' => max(0, (int)($_POST['min_interval'] ?? 10)),
            'max_per_hour' => max(1, (int)($_POST['max_per_hour'] ?? 10)),
            'max_links'    => max(0, (int)($_POST['max_links'] ?? 3)),
            'word_list'    => trim($_POST['word_list'] ?? ''),
            'email_black'  => trim($_POST['email_black'] ?? ''),
            'name_black'   => trim($_POST['name_black'] ?? ''),
        ];
        save_data(DATA_DIR . '/plugin_antispam.php', $data);
        $ok = '设置已保存。';
    }
}

$cfg = qm_asp_data()['config'];
?>

<?php if ($ok): ?>
    <div class="msg success"><?php echo e($ok); ?></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="msg error"><?php echo e($err); ?></div>
<?php endif; ?>

<form method="post" class="admin-form" style="max-width:640px;">
    <?php csrf_field(); ?>

    <label>启用反垃圾校验</label>
    <select name="enabled">
        <option value="1" <?php echo ($cfg['enabled'] ?? 1) == 1 ? 'selected' : ''; ?>>开启</option>
        <option value="0" <?php echo ($cfg['enabled'] ?? 1) == 0 ? 'selected' : ''; ?>>关闭</option>
    </select>

    <div style="display:flex;gap:14px;flex-wrap:wrap;">
        <div style="flex:0 0 130px;">
            <label>最短内容（字）</label>
            <input type="number" name="min_len" value="<?php echo e($cfg['min_len'] ?? 2); ?>">
        </div>
        <div style="flex:0 0 140px;">
            <label>提交间隔（秒）</label>
            <input type="number" name="min_interval" value="<?php echo e($cfg['min_interval'] ?? 10); ?>">
        </div>
        <div style="flex:0 0 140px;">
            <label>每小时上限（条/IP）</label>
            <input type="number" name="max_per_hour" value="<?php echo e($cfg['max_per_hour'] ?? 10); ?>">
        </div>
        <div style="flex:0 0 140px;">
            <label>链接数量上限</label>
            <input type="number" name="max_links" value="<?php echo e($cfg['max_links'] ?? 3); ?>">
        </div>
    </div>

    <label>内容关键词黑名单（每行一个；留空用内置默认）</label>
    <textarea name="word_list" style="height:110px;" placeholder="每行一个关键词，如：代开发票&#10;博彩&#10;加微信"><?php echo e($cfg['word_list'] ?? ''); ?></textarea>

    <label>邮箱黑名单（子串，每行一个；留空用内置默认）</label>
    <textarea name="email_black" style="height:70px;" placeholder="mailinator&#10;guerrillamail"><?php echo e($cfg['email_black'] ?? ''); ?></textarea>

    <label>昵称黑名单（子串，每行一个）</label>
    <textarea name="name_black" style="height:60px;"><?php echo e($cfg['name_black'] ?? ''); ?></textarea>

    <p><input type="submit" value="保存设置"></p>
</form>
