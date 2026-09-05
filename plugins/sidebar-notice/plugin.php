<?php
/**
 * 插件：侧栏公告
 * 公告框默认显示在**侧栏最顶部**（qm_sidebar_top，分类之前），也支持放底部（qm_sidebar）。
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

/** 渲染公告小部件 */
function qm_notice_render() {
    $text = trim((string)qm_notice_get('text', ''));
    if ($text === '') return; // 未填写则不显示
    echo '<div class="box qm-plugin-notice">'
        . '<h3>📢 ' . e((string)qm_notice_get('title', '公告')) . '</h3>'
        . '<p style="font-size:13px;">' . nl2br(e($text)) . '</p>'
        . '</div>';
}

// 顶部（默认，分类之前）：只有位置设为 top 才输出
add_action('qm_sidebar_top', function () {
    if ((string)qm_notice_get('position', 'top') !== 'top') return;
    qm_notice_render();
});

// 底部（全部小部件之后）
add_action('qm_sidebar', function () {
    if ((string)qm_notice_get('position', 'top') !== 'bottom') return;
    qm_notice_render();
});
