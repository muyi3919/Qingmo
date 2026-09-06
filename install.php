<?php
/**
 * 轻墨 - 纯文件博客系统安装脚本
 * 运行一次后请删除此文件
 */

require_once __DIR__ . '/includes/functions.php';

// 检查是否已安装
if (file_exists(DATA_DIR . '/config.php')) {
    die('<h2>数据已存在</h2><p>如需重新安装，请先删除 data/ 目录下的所有文件。</p>');
}

// 确保目录存在
if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
if (!is_dir(POSTS_DIR)) mkdir(POSTS_DIR, 0755, true);

// 1. 站点配置
save_config([
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
]);

// 2. 管理员账号（密码: admin）
save_users([
    [
        'id' => 1,
        'username' => 'admin',
        'password' => password_hash('admin', PASSWORD_DEFAULT),
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
    'title' => '欢迎来到我的2000年博客',
    'slug' => 'welcome-to-my-blog',
    'content' => "<p>大家好！这是我的第一个博客。</p>\n<p>在这个朴素的文字空间里，我会分享一些生活随笔和技术笔记。没有花哨的特效，只有真诚的文字。</p>\n<p>如果你也是那个年代过来的人，一定会怀念这种简单纯粹的阅读体验。</p>",
    'summary' => '这是我的第一篇博客文章，欢迎来访。',
    'category_id' => 2,
    'author_id' => 1,
    'author_name' => 'admin',
    'status' => 1,
    'view_count' => 0,
    'comment_count' => 2,
    'tags' => ['博客', '开篇'],
    'created_at' => $now - 86400,
    'updated_at' => $now - 86400,
]);

save_post(2, [
    'id' => 2,
    'title' => '今天开始学PHP',
    'slug' => 'start-learning-php',
    'content' => "<p>PHP 是一种很好的 Web 开发语言。今天我开始学习如何用它来做动态网站。</p>\n<p>目前我已经学会了基本的语法和数据库连接。下一步准备写一个留言板程序。</p>\n<p>希望以后能做出更多有趣的东西！</p>",
    'summary' => '记录我开始学习PHP的第一天。',
    'category_id' => 3,
    'author_id' => 1,
    'author_name' => 'admin',
    'status' => 1,
    'view_count' => 0,
    'comment_count' => 0,
    'tags' => ['PHP', '学习'],
    'created_at' => $now - 43200,
    'updated_at' => $now - 43200,
]);

// 5. 评论
save_comments([
    [
        'id' => 1,
        'post_id' => 1,
        'author_name' => '访客小王',
        'author_email' => 'xiaowang@example.com',
        'author_url' => '',
        'content' => '博客很好看！加油更新！',
        'status' => 1,
        'created_at' => $now - 36000,
    ],
    [
        'id' => 2,
        'post_id' => 1,
        'author_name' => '网友阿明',
        'author_email' => 'aming@example.com',
        'author_url' => '',
        'content' => '这种风格很怀旧，喜欢。',
        'status' => 1,
        'created_at' => $now - 18000,
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
save_data(DATA_DIR . '/counter.php', 2);

// 刷新分类计数
refresh_all_categories();

// 完成
echo '<h2>安装成功！</h2>';
echo '<p>数据文件已创建。</p>';
echo '<p><strong>重要：</strong>请立即删除 install.php 文件！</p>';
echo '<p>默认管理员账号：<strong>admin</strong> / 密码：<strong>admin</strong></p>';
echo '<p><a href="index.php">前往首页（安装文件已自动标记，请手动删除install.php）</a> | <a href="admin/login.php">进入后台</a></p>';
