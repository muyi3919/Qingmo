<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? e($pageTitle) . ' - ' : ''; ?><?php echo e(get_setting('site_title')); ?></title>
    <meta name="description" content="<?php echo e(get_setting('site_description')); ?>">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container">
    <div class="header">
        <h1><a href="index.php"><?php echo e(get_setting('site_title')); ?></a></h1>
        <p class="desc"><?php echo e(get_setting('site_description')); ?></p>
        <div class="nav">
            <a href="index.php">首页</a> | 
            <a href="index.php?page=archive">文章归档</a> | 
            <a href="index.php?page=about">关于</a>
            <?php if (is_logged_in()): ?>
             | <a href="admin/">管理后台</a>
            <?php endif; ?>
        </div>
        <div class="search-box" style="margin-top:8px;">
            <form action="index.php" method="get" style="display:inline;">
                <input type="hidden" name="page" value="search">
                <input type="text" name="q" placeholder="搜索文章..." style="width:160px;padding:2px 5px;border:1px solid #999;font-family:inherit;font-size:12px;">
                <input type="submit" value="搜索" style="padding:2px 8px;font-size:12px;">
            </form>
        </div>
    </div>
    <div class="main">
