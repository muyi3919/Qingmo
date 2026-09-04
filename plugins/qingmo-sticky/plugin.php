<?php
/**
 * 插件：文章置顶
 * 功能：
 *   1) 后台「文章管理」每行出现「置顶 / 取消置顶」按钮（经 qm_post_row_actions）；
 *   2) 切换请求在 qm_admin_posts_head 处理（鉴权 + CSRF）；
 *   3) 置顶文章经 qm_posts_list 排到所有列表最前（同一批内保持原时间倒序）。
 *
 * 依赖核心 v2.0+ 的钩子：qm_posts_list、qm_post_row_actions、qm_admin_posts_head。
 */
if (!defined('QM_BOOT')) { exit('Access denied'); }

// ---------- 1. 排序：置顶在前 ----------
add_filter('qm_posts_list', 'qm_sticky_sort_posts', 10, 2);
function qm_sticky_sort_posts($posts, $filter) {
    $sticky = [];
    $normal = [];
    foreach ($posts as $post) {
        if (!empty($post['sticky'])) {
            $sticky[] = $post;
        } else {
            $normal[] = $post;
        }
    }
    return array_merge($sticky, $normal);
}

// ---------- 2. 后台行操作按钮 ----------
add_filter('qm_post_row_actions', 'qm_sticky_row_actions', 10, 3);
function qm_sticky_row_actions($actions, $post, $token) {
    $isSticky = !empty($post['sticky']);
    $label = $isSticky ? '取消置顶' : '置顶';
    $color = $isSticky ? '#c62828' : '#2e7d32';
    $actions .= ' | <a href="index.php?page=posts&sticky_toggle=' . (int)$post['id']
        . '&token=' . e($token) . '" style="color:' . $color . '"'
        . ($isSticky ? ' title="当前置顶中"' : '') . '>' . $label . '</a>';
    return $actions;
}

// ---------- 3. 处理切换请求（位于文章管理页顶部动作） ----------
add_action('qm_admin_posts_head', 'qm_sticky_handle_toggle');
function qm_sticky_handle_toggle() {
    if (!isset($_GET['sticky_toggle'])) return;
    if (!isset($_GET['token']) || !verify_csrf($_GET['token'] ?? '')) {
        die('安全验证失败');
    }
    $id = (int)$_GET['sticky_toggle'];
    $post = load_post($id);
    if (!$post) {
        die('文章不存在');
    }
    $post['sticky'] = empty($post['sticky']) ? 1 : 0;
    save_post($id, $post);

    $query = ['page' => 'posts'];
    if (isset($_GET['page_num'])) $query['page_num'] = (int)$_GET['page_num'];
    header('Location: index.php?' . http_build_query($query));
    exit;
}
