<?php
/**
 * 插件：页脚备案信息
 * 演示：动作钩子 do_action('qm_footer') + 自带后台设置页（admin.php）
 * 备案文字通过 插件市场 → 设置 填写，保存在 data/plugin_footer_beian.php。
 */
if (!defined('QM_BOOT')) { exit('Access denied'); }

/**
 * 读取备案数据
 */
function qm_beian_get($key, $default = '') {
    $data = load_data(DATA_DIR . '/plugin_footer_beian.php', []);
    return $data[$key] ?? $default;
}

/**
 * 保存备案数据
 */
function qm_beian_set($data) {
    save_data(DATA_DIR . '/plugin_footer_beian.php', $data);
}

add_action('qm_footer', function () {
    $text = trim((string)qm_beian_get('text', ''));
    if ($text === '') return; // 未填写则不显示
    echo '<p class="qm-plugin-beian" style="font-size:12px;color:#999;">' . e($text) . '</p>';
});
