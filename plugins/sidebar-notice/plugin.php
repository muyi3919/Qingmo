<?php
/**
 * 插件：侧栏公告
 * 演示：动作钩子 do_action('qm_sidebar') + 自带后台设置页（admin.php）
 * 公告内容通过 插件市场 → 设置 填写，保存在 data/plugin_sidebar_notice.php。
 */
if (!defined('QM_BOOT')) { exit('Access denied'); }

function qm_notice_get($key, $default = '') {
    $data = load_data(DATA_DIR . '/plugin_sidebar_notice.php', []);
    return $data[$key] ?? $default;
}

function qm_notice_set($data) {
    save_data(DATA_DIR . '/plugin_sidebar_notice.php', $data);
}

add_action('qm_sidebar', function () {
    $text = trim((string)qm_notice_get('text', ''));
    if ($text === '') return; // 未填写则不显示
    echo '<div class="box qm-plugin-notice">'
        . '<h3>公告</h3>'
        . '<p style="font-size:13px;">' . nl2br(e($text)) . '</p>'
        . '</div>';
});
