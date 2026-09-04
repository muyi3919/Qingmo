<?php
/**
 * 插件设置页（通用）
 * 用法：plugins/<slug>/admin.php 存在且插件已启用时，插件市场列表会给出「设置」入口。
 * 插件自身负责输出表单、处理 POST（务必用 csrf_field()/verify_csrf()）。
 */
require_once __DIR__ . '/_guard.php';

$slug = $_GET['slug'] ?? '';
if (!preg_match('/^[a-zA-Z0-9_\-]{1,64}$/', $slug)) {
    die('参数不合法');
}
if (!is_plugin_active($slug)) {
    die('该插件未启用，无法进行设置。');
}
$info = get_plugin_info($slug);
if ($info === null) {
    die('插件不存在。');
}
$adminEntry = PLUGINS_DIR . '/' . $slug . '/admin.php';
if (!file_exists($adminEntry)) {
    die('该插件未提供设置页面。');
}

$adminPageTitle = '插件设置：' . $info['name'];

include __DIR__ . '/_header.php';
?>
<p style="font-size:13px;color:#666;margin-bottom:14px;">
    <?php echo e($info['description'] ?? ''); ?>
    <span style="margin-left:8px;">插件 slug：<code><?php echo e($slug); ?></code></span>
</p>
<?php
include $adminEntry;
include __DIR__ . '/_footer.php';
