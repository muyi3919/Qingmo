<?php
/**
 * RSS 订阅输出
 */
require_once __DIR__ . '/includes/functions.php';

$posts = load_posts(['status' => 1]);
$posts = array_slice($posts, 0, 20);
$config = load_config();
$siteTitle = $config['site_title'] ?? '我的轻墨博客';
$siteDesc = $config['site_description'] ?? '';
$siteUrl = !empty($config['site_url']) ? rtrim($config['site_url'], '/') : 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

header('Content-Type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<rss version="2.0">
<channel>
    <title><?php echo e($siteTitle); ?></title>
    <link><?php echo e($siteUrl); ?></link>
    <description><?php echo e($siteDesc); ?></description>
    <language>zh-CN</language>
    <lastBuildDate><?php echo date('r'); ?></lastBuildDate>
    <generator>轻墨</generator>
    <?php foreach ($posts as $post): ?>
    <item>
        <title><?php echo e($post['title']); ?></title>
        <link><?php echo e($siteUrl); ?>/index.php?page=post&amp;id=<?php echo $post['id']; ?></link>
        <guid><?php echo e($siteUrl); ?>/index.php?page=post&amp;id=<?php echo $post['id']; ?></guid>
        <pubDate><?php echo date('r', $post['created_at']); ?></pubDate>
        <description><![CDATA[<?php echo $post['summary'] ?: make_summary($post['content'], 300); ?>]]></description>
    </item>
    <?php endforeach; ?>
</channel>
</rss>
