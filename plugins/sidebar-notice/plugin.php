<?php
/**
 * 插件：侧栏公告
 * 演示动作钩子：do_action('qm_sidebar')（位于侧边栏所有小部件之后）
 */
if (!defined('QM_BOOT')) { exit('Access denied'); }

$notice_text = '这是侧栏公告插件的示例内容，可在此修改公告文字。';

add_action('qm_sidebar', function () use ($notice_text) {
    echo '<div class="box qm-plugin-notice">'
        . '<h3>公告</h3>'
        . '<p style="font-size:13px;">' . e($notice_text) . '</p>'
        . '</div>';
});
