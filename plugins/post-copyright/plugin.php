<?php
/**
 * 插件：文章版权尾巴
 * 演示过滤器钩子：apply_filters('qm_post_content', $content, $post)
 * 在每篇已发布文章正文末尾追加版权提示（仅单篇文章页触发该钩子）。
 * 注：若后台「站点设置 → 文章页脚设置」已开启内置文章页脚，本插件自动停用，
 * 避免两段版权信息重复。
 */
if (!defined('QM_BOOT')) { exit('Access denied'); }

add_filter('qm_post_content', function ($content, $post) {
    if ((int)get_setting('show_post_footer', 1) === 1) return $content; // 内置页脚已开启
    $site = get_setting('site_title', '本站');
    $tail = '<div class="qm-plugin-copyright" style="margin-top:20px;padding:8px 12px;'
        . 'border:1px dashed #ccc;font-size:13px;color:#666;">'
        . '本文由 <strong>' . e($site) . '</strong> 发布，转载请注明出处。</div>';
    return $content . $tail;
}, 10, 2);
