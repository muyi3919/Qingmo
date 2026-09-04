<?php
/**
 * 后台页面鉴权守卫
 * - 经 admin/index.php 路由包含时（QM_ADMIN_LOADED 已定义）直接放行；
 * - 直接访问某个后台页面文件时，自行完成初始化与登录校验。
 */
if (!defined('QM_ADMIN_LOADED')) {
    require_once dirname(__DIR__) . '/includes/functions.php';
    qm_session_start();
    require_login();
}
