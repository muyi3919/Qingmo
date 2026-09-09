<?php
/**
 * 轻墨 - 纯文件博客系统安装脚本
 * 运行一次后请删除此文件
 */

require_once __DIR__ . '/includes/functions.php';

// 检查是否已安装
if (file_exists(DATA_DIR . '/config.php')) {
    $installState = 'installed';
    require __DIR__ . '/includes/install-view.php';
    exit;
}

qm_session_start();
$installError = '';
$installUser = trim((string)($_POST['username'] ?? ''));
$installPass = (string)($_POST['password'] ?? '');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) $installError = '安全验证失败，请重试。';
    elseif (!preg_match('/^[A-Za-z0-9_\-]{3,32}$/', $installUser)) $installError = '用户名需要3至32位字母、数字、下划线或短横线。';
    elseif (qm_password_error($installPass) !== '') $installError = qm_password_error($installPass);
    elseif ($installPass !== (string)($_POST['confirm'] ?? '')) $installError = '两次密码不一致。';
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || $installError !== '') {
    header('Content-Type: text/html; charset=utf-8');
    $installState = 'form';
    require __DIR__ . '/includes/install-view.php';
    exit;
}

// 确保目录存在
if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
if (!is_dir(POSTS_DIR)) mkdir(POSTS_DIR, 0755, true);

// 1. 站点配置
$initialConfig = [
    'site_title' => '我的轻墨博客',
    'site_description' => '一个轻量纯粹的博客空间',
    'site_url' => '',
    'posts_per_page' => 10,
    'allow_comments' => 1,
    'comment_moderation' => 0,
    'theme' => 'default',           // 默认主题
    'active_plugins' => [],         // 默认不启用任何插件
    'show_post_footer' => 1,        // 文章页脚开关
    'author_name' => '',            // 页脚作者署名（留空则用文章作者）
    'usage_agreement' => '本站文章采用知识共享「署名-非商业性使用-相同方式共享 4.0 国际」许可协议，转载需注明作者与出处。',
    'comment_email_on' => 0,        // 评论邮件通知开关
    'notify_email' => '',           // 通知收件邮箱
    'at_notify_on' => 1,            // @ 提及提醒开关
    'mailer_mode' => 'php',         // php | smtp
    'smtp_host' => '',              // SMTP 服务器
    'smtp_port' => 587,
    'smtp_secure' => 'tls',         // ssl | tls | none
    'smtp_user' => '',
    'smtp_pass' => '',
    'smtp_from' => '',
    'smtp_from_name' => '',
];

// 2. 用户自行设置的管理员账号
save_users([
    [
        'id' => 1,
        'username' => $installUser,
        'password' => password_hash($installPass, PASSWORD_DEFAULT),
        'email' => 'admin@example.com',
        'created_at' => time(),
    ]
]);

// 3. 分类
save_categories([
    ['id' => 1, 'name' => '未分类', 'slug' => 'uncategorized', 'description' => '默认分类', 'post_count' => 0],
    ['id' => 2, 'name' => '生活随笔', 'slug' => 'life', 'description' => '记录生活中的点点滴滴', 'post_count' => 0],
    ['id' => 3, 'name' => '技术笔记', 'slug' => 'tech', 'description' => '技术学习与分享', 'post_count' => 0],
]);

// 4. 文章
$now = time();

save_post(1, [
    'id' => 1,
    'title' => 'Hello world!',
    'slug' => 'hello-world',
    'content' => '<p>欢迎使用 Qingmo（轻墨）！这是你的第一篇文章，也是这段书写旅程的开始。</p><p>Qingmo 是一个基于 PHP 的轻量博客系统，使用文件保存数据，无需配置数据库。你可以在这里记录生活、分享知识，也可以留下一闪而过的灵感。</p><p>从后台开始，你可以使用 Markdown 或可视化编辑器书写文章，管理分类、标签和评论，选择喜欢的主题，并通过插件扩展博客功能。</p><p>这篇文章可以随时编辑或删除。准备好后，写下属于你的第一段文字吧。</p><p>了解项目、反馈问题或参与贡献：<a href="https://github.com/muyi3919/Qingmo" target="_blank" rel="noopener">Qingmo on GitHub</a>。</p>',
    'summary' => '欢迎使用 Qingmo（轻墨），一个无需数据库的轻量 PHP 博客系统。从这里开始，记录生活、分享知识，好好书写。',
    'category_id' => 1,
    'author_id' => 1,
    'author_name' => $installUser,
    'status' => 1,
    'view_count' => 0,
    'comment_count' => 1,
    'tags' => ['博客', '开篇'],
    'created_at' => $now,
    'updated_at' => $now,
]);

// 5. 评论
save_comments([
    [
        'id' => 1,
        'post_id' => 1,
        'parent_id' => 0,
        'author_name' => 'Qingmo',
        'author_email' => 'shuzhishaoju@gmail.com',
        'author_url' => 'https://github.com/muyi3919/Qingmo',
        'content' => '感谢使用 Qingmo，愿每一笔墨都轻而不浮，重而不滞。好好书写吧。 —— Qingmo 团队',
        'status' => 1,
        'created_at' => $now,
    ],
]);

// 5.5 友链示例（可在后台「友链」中管理）
save_links([
    ['id' => 1, 'name' => '轻墨项目主页', 'url' => 'https://kina.ink', 'category' => '朋友', 'icon' => 'https://kina.ink/favicon.ico', 'description' => '轻墨(Qingmo)纯文件博客系统的作者主页。', 'created_at' => time()],
    ['id' => 2, 'name' => 'PHP 官方手册', 'url' => 'https://www.php.net/manual/zh/', 'category' => '资源', 'icon' => 'https://www.php.net/favicon.ico', 'description' => '学习 PHP 必不可少的官方中文手册。', 'created_at' => time()],
    ['id' => 3, 'name' => 'MDN Web Docs', 'url' => 'https://developer.mozilla.org/zh-CN/', 'category' => '资源', 'icon' => 'https://developer.mozilla.org/favicon-48x48.png', 'description' => 'Web 开发权威文档，HTML/CSS/JS 一站查。', 'created_at' => time()],
]);

// 6. 关于页面
save_about([
    'title' => '关于本站',
    'content' => '<p>这是一个使用轻墨搭建的朴素博客。</p><p>没有花哨的视觉效果，只有简洁的阅读体验。</p><p>如果你怀念那个时代的互联网，希望这里能带给你一些熟悉的感觉。</p>',
]);

// 7. 计数器
save_data(DATA_DIR . '/counter.php', 1);

// 刷新分类计数
refresh_all_categories();

// 完成
save_config($initialConfig);
$installState = 'success';
require __DIR__ . '/includes/install-view.php';
