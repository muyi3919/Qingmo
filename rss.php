<?php
/**
 * RSS 订阅输出（v2.2 增强版）
 * - 输出全文（content:encoded）与摘要两套
 * - 补充作者、分类、标签、更新时间、atom:link/self
 */
require_once __DIR__ . '/includes/functions.php';

$posts = load_posts(['status' => 1]);
$posts = array_slice($posts, 0, 20);
$config = load_config();
$siteTitle = $config['site_title'] ?? '我的轻墨博客';
$siteDesc = $config['site_description'] ?? '';
$siteUrl = site_base_url();

header('Content-Type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<rss version="2.0"
     xmlns:content="http://purl.org/rss/1.0/modules/content/"
     xmlns:dc="http://purl.org/dc/elements/1.1/"
     xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title><?php echo e($siteTitle); ?></title>
    <link><?php echo e($siteUrl); ?></link>
    <atom:link href="<?php echo e($siteUrl); ?>/rss.php" rel="self" type="application/rss+xml"/>
    <description><?php echo e($siteDesc); ?></description>
    <language>zh-CN</language>
    <generator>Qingmo/<?php echo e(QM_VERSION); ?></generator>
    <lastBuildDate><?php echo date('r'); ?></lastBuildDate>
    <?php
    $cats = load_categories();
    $catMap = array_column($cats, 'name', 'id');
    foreach ($posts as $post):
        $link = $siteUrl . '/index.php?page=post&amp;id=' . (int)$post['id'];
        $author = (string)($post['author_name'] ?? '');
        $catName = isset($catMap[$post['category_id'] ?? 0]) ? $catMap[$post['category_id'] ?? 0] : '';
        $excerpt = ($post['summary'] ?? '') !== '' ? (string)$post['summary'] : make_summary($post['content'], 300);
        $excerpt = strip_tags($excerpt);
        $contentFull = (string)($post['content'] ?? '');
    ?>
    <item>
        <title><?php echo e($post['title']); ?></title>
        <link><?php echo e($link); ?></link>
        <guid isPermaLink="true"><?php echo e($link); ?></guid>
        <?php if ($author !== ''): ?>
        <dc:creator><?php echo e($author); ?></dc:creator>
        <?php endif; ?>
        <?php if ($catName !== ''): ?>
        <category><?php echo e($catName); ?></category>
        <?php endif; ?>
        <?php foreach (($post['tags'] ?? []) as $tag): if ($tag === '') continue; ?>
        <category><?php echo e($tag); ?></category>
        <?php endforeach; ?>
        <pubDate><?php echo date('r', (int)($post['created_at'] ?? time())); ?></pubDate>
        <?php if (!empty($post['updated_at']) && (int)$post['updated_at'] !== (int)($post['created_at'] ?? 0)): ?>
        <dc:date><?php echo date('c', (int)$post['updated_at']); ?></dc:date>
        <?php endif; ?>
        <description><![CDATA[<?php echo $excerpt; ?>]]></description>
        <?php if ($contentFull !== ''): ?>
        <content:encoded><![CDATA[<?php echo $contentFull; ?>]]></content:encoded>
        <?php endif; ?>
    </item>
    <?php endforeach; ?>
</channel>
</rss>
