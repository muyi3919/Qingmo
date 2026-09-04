<?php
/**
 * 插件设置页：侧栏公告
 * 由 admin/plugin-settings.php 在登录后 include；自行处理表单与保存。
 */
if (!defined('QM_BOOT')) { exit('Access denied'); }

$err = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $err = '安全验证失败，请刷新页面重试。';
    } else {
        qm_notice_set([
            'text' => trim($_POST['notice_text'] ?? ''),
            'updated_at' => time(),
        ]);
        $ok = '已保存。';
    }
}

$current = qm_notice_get('text', '');
?>

<?php if ($ok): ?>
    <div class="msg success"><?php echo e($ok); ?></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="msg error"><?php echo e($err); ?></div>
<?php endif; ?>

<form method="post" class="admin-form" style="max-width:560px;">
    <?php csrf_field(); ?>
    <label>公告内容（留空则不显示小部件）</label>
    <textarea name="notice_text" style="height:120px;" placeholder="例如：本站近期进行系统升级，如有不便敬请谅解。"><?php echo e($current); ?></textarea>
    <p><input type="submit" value="保存"></p>
</form>
