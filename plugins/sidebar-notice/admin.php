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
        $position = in_array($_POST['position'] ?? 'top', ['top', 'bottom'], true)
            ? $_POST['position'] : 'top';
        qm_notice_set([
            'title'    => trim($_POST['notice_title'] ?? '公告'),
            'text'     => trim($_POST['notice_text'] ?? ''),
            'position' => $position,
            'updated_at' => time(),
        ]);
        $ok = '已保存。';
    }
}

$currentTitle = qm_notice_get('title', '公告');
$current = qm_notice_get('text', '');
$currentPos = (string)qm_notice_get('position', 'top');
?>

<?php if ($ok): ?>
    <div class="msg success"><?php echo e($ok); ?></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="msg error"><?php echo e($err); ?></div>
<?php endif; ?>

<form method="post" class="admin-form" style="max-width:560px;">
    <?php csrf_field(); ?>

    <label>公告标题</label>
    <input type="text" name="notice_title" value="<?php echo e($currentTitle); ?>">

    <label>公告内容（留空则不显示小部件）</label>
    <textarea name="notice_text" style="height:120px;" placeholder="例如：本站近期进行系统升级，如有不便敬请谅解。"><?php echo e($current); ?></textarea>

    <label>显示位置</label>
    <select name="position">
        <option value="top" <?php echo $currentPos === 'top' ? 'selected' : ''; ?>>侧栏顶部（分类之前，推荐）</option>
        <option value="bottom" <?php echo $currentPos === 'bottom' ? 'selected' : ''; ?>>侧栏底部（全部小部件之后）</option>
    </select>

    <p><input type="submit" value="保存"></p>
</form>
