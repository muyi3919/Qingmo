<?php
/** 后台公共头部（侧边栏布局版）：请先设置 $adminPageTitle 再 include 本文件 */
if (!isset($adminPageTitle)) $adminPageTitle = '管理后台';
$qmPage = $_GET['page'] ?? 'dashboard';
$qmMenu = [
    'dashboard'  => ['仪表盘'],
    'posts'      => ['文章管理'],
    'categories' => ['分类管理'],
    'comments'   => ['评论管理'],
    'messages'   => ['留言板管理'],
    'links'      => ['友链'],
    'themes'     => ['主题市场'],
    'plugins'    => ['插件市场'],
    'settings'   => ['站点设置'],
    'update'     => ['系统更新'],
    'about-edit' => ['关于'],
    'password'   => ['修改密码'],
];
if (!isset($qmMenu[$qmPage])) $qmPage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($adminPageTitle); ?> - 轻墨 · 后台</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-side">
        <div class="admin-brand">轻墨 <span>管理后台</span></div>
        <nav class="admin-menu">
            <?php foreach ($qmMenu as $key => $label): ?>
                <a href="index.php?page=<?php echo $key; ?>" class="admin-menu-item<?php echo $key === $qmPage ? ' active' : ''; ?>"><?php echo e($label[0]); ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="admin-side-extra">
            <a href="../index.php" target="_blank">↗ 查看前台</a>
            <a href="index.php?logout=1" class="danger">退出登录</a>
        </div>
    </aside>
    <main class="admin-main">
        <div class="admin-topbar"><h2><?php echo e($adminPageTitle); ?></h2></div>
        <div class="admin-content">
