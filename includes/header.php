<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    // 深色模式：尽早应用，避免闪烁（支持 data-theme 的主题生效）
    $qmActiveTheme = get_active_theme();
    $qmThemeSupportsDark = (int)(get_theme_info($qmActiveTheme)['dark_support'] ?? 0) === 1;
    if ($qmThemeSupportsDark):
    ?>
    <script>
    (function () {
        try {
            var t = localStorage.getItem('qm-theme');
            if (!t) t = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', t);
        } catch (e) {}
    })();
    </script>
    <?php endif; ?>
    <title><?php echo isset($pageTitle) ? e($pageTitle) . ' - ' : ''; ?><?php echo e(get_setting('site_title')); ?></title>
    <meta name="description" content="<?php echo e(get_setting('site_description')); ?>">
    <link rel="stylesheet" href="<?php echo e(theme_css_url()); ?>?v=<?php echo QM_VERSION; ?>">
    <?php
    // 主题/插件可注入额外 <head> 内容（附带当前页上下文，便于 SEO/分享类插件使用）
    $qmScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $qmCurUrl = $qmScheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    do_action('qm_head', [
        'title' => isset($pageTitle) ? $pageTitle : null,
        'page'  => $_GET['page'] ?? '',
        'id'    => (int)($_GET['id'] ?? 0),
        'url'   => $qmCurUrl,
    ]);
    ?>
</head>
<body class="theme-<?php echo e($qmActiveTheme); ?>">
<div class="container">
    <div class="header">
        <h1 class="site-title"><a href="index.php"><?php echo e(get_setting('site_title')); ?></a></h1>
        <p class="desc"><?php echo e(get_setting('site_description')); ?></p>
        <nav class="nav">
            <a href="index.php">首页</a><span class="nav-sep">|</span>
            <a href="index.php?page=archive">文章归档</a><span class="nav-sep">|</span>
            <a href="index.php?page=links">友链</a><span class="nav-sep">|</span>
            <a href="index.php?page=about">关于</a>
            <?php if (is_logged_in()): ?>
            <span class="nav-sep">|</span><a href="admin/">管理后台</a>
            <?php endif; ?>
            <?php if ($qmThemeSupportsDark): ?>
            <button type="button" id="themeToggle" class="theme-toggle" title="切换深色/浅色" aria-label="切换深色/浅色">🌓</button>
            <?php endif; ?>
        </nav>
        <form class="search-box" action="index.php" method="get" role="search">
            <input type="hidden" name="page" value="search">
            <input type="text" class="search-input" name="q" placeholder="搜索文章..." value="<?php echo e($_GET['q'] ?? ''); ?>">
            <button type="submit" class="search-btn">搜索</button>
        </form>
    </div>
    <div class="main">
