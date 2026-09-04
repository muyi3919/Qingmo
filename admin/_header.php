<?php
/** 后台公共头部：请先设置 $adminPageTitle 再 include 本文件 */
if (!isset($adminPageTitle)) $adminPageTitle = '管理后台';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($adminPageTitle); ?> - 轻墨 · 后台</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="container">
    <div class="header">
        <h1>轻墨 · 后台</h1>
        <div class="nav">
            <a href="index.php">仪表盘</a> |
            <a href="index.php?page=posts">文章管理</a> |
            <a href="index.php?page=categories">分类管理</a> |
            <a href="index.php?page=comments">评论管理</a> |
            <a href="index.php?page=links">友链</a> |
            <a href="index.php?page=themes">主题市场</a> |
            <a href="index.php?page=plugins">插件市场</a> |
            <a href="index.php?page=settings">站点设置</a> |
            <a href="index.php?page=about-edit">关于</a> |
            <a href="index.php?page=password">修改密码</a> |
            <a href="index.php?logout=1">退出</a>
        </div>
    </div>
    <h2><?php echo e($adminPageTitle); ?></h2>
