<?php
/**
 * 插件设置页：页脚备案信息
 * 由 admin/plugin-settings.php 在登录后 include；自行处理表单与保存。
 */
if (!defined('QM_BOOT')) { exit('Access denied'); }

$err = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $err = '安全验证失败，请刷新页面重试。';
    } else {
        qm_beian_set([
            'text' => trim($_POST['beian_text'] ?? ''),
            'updated_at' => time(),
        ]);
        $ok = '已保存。';
    }
}

$current = qm_beian_get('text', '');
?>

<?php if ($ok): ?>
    <div class="msg success"><?php echo e($ok); ?></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="msg error"><?php echo e($err); ?></div>
<?php endif; ?>

<form method="post" class="admin-form" style="max-width:560px;">
    <?php csrf_field(); ?>
    <label>页脚文字（备案号 / 版权声明，留空则不显示）</label>
    <textarea name="beian_text" style="height:80px;" placeholder="例如：© 2025 我的博客 · 京ICP备00000000号-1"><?php echo e($current); ?></textarea>
    <p style="font-size:12px;color:#888;margin:0 0 10px;">显示在页面底部「Powered by 轻墨」一行的下方。</p>
    <p><input type="submit" value="保存"></p>
</form>
