<?php
/**
 * 插件：页脚备案信息
 * 演示动作钩子：do_action('qm_footer')
 * 在下方 $text 处修改要显示的内容。
 */
if (!defined('QM_BOOT')) { exit('Access denied'); }

$beian_text = '© ' . date('Y') . ' 轻墨博客 · 示例备案号：京ICP备00000000号-1';

add_action('qm_footer', function () use ($beian_text) {
    echo '<p class="qm-plugin-beian" style="font-size:12px;color:#999;">' . e($beian_text) . '</p>';
});
