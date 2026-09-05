<?php
/**
 * RSS 2.0 订阅输出（规范修正版）
 * 规范要点：
 *  - XML 文本一律只转义 & < >（不再用 HTML 转义，避免 &amp;amp; 双重转义）
 *  - CDATA 内容安全处理 "]]>"（拆分为 ]]]]><![CDATA[>）
 *  - 提供全文 content:encoded、作者 dc:creator、分类/标签、更新时间与 atom:link
 */
require_once __DIR__ . '/includes/functions.php';

function qm_xml($s) {
    return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], (string)$s);
}

function qm_cdata($s) {
    return str_replace(']]>', ']]]]><![CDATA[>', (string)$s);
}

$posts = load_posts(['status' => 1]);
$posts = array_slice($posts, 0, 20);
$config = load_config();
$siteTitle = $config['site_title'] ?? '我的轻墨博客';
$siteDesc = $config['site_description'] ?? '';
$siteUrl = site_base_url();

header('Content-Type: application/rss+xml; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0"
     xmlns:content="http://purl.org/rss/1.0/modules/content/"
     xmlns:dc="http://purl.org/dc/elements/1.1/"
     xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title><?php echo qm_xml($siteTitle); ?></title>
    <link><?php echo qm_xml($siteUrl); ?></link>
    <description><?php echo qm_xml($siteDesc); ?></description>
    <language>zh-CN</language>
    <generator>Qingmo/<?php echo qm_xml(QM_VERSION); ?></generator>
    <lastBuildDate><?php echo date('r'); ?></lastBuildDate>
    <atom:link href="<?php echo qm_xml($siteUrl); ?>/rss.php" rel="self" type="application/rss+xml"/>
    <?php
    $cats = load_categories();
    $catMap = array_column($cats, 'name', 'id');
    foreach ($posts as $post):
        $postId = (int)$post['id'];
        $link = $siteUrl . '/index.php?page=post&id=' . $postId; // 原始 &，输出时统一 qm_xml
        $author = trim((string)($post['author_name'] ?? ''));
        $catName = isset($catMap[$post['category_id'] ?? 0]) ? (string)$catMap[$post['category_id'] ?? 0] : '';
        $contentFull = (string)($post['content'] ?? '');
        $excerpt = ($post['summary'] ?? '') !== '' ? (string)$post['summary'] : make_summary($contentFull, 300);
        $excerpt = html_entity_decode(strip_tags($excerpt), ENT_QUOTES, 'UTF-8');
        $created = (int)($post['created_at'] ?? time());
        $updated = (int)($post['updated_at'] ?? $created);
    ?>
    <item>
        <title><?php echo qm_xml($post['title'] ?? ''); ?></title>
        <link><?php echo qm_xml($link); ?></link>
        <guid isPermaLink="true"><?php echo qm_xml($link); ?></guid>
        <?php if ($author !== ''): ?>
        <dc:creator><?php echo qm_xml($author); ?></dc:creator>
        <?php endif; ?>
        <?php if ($catName !== ''): ?>
        <category><?php echo qm_xml($catName); ?></category>
        <?php endif; ?>
        <?php foreach (($post['tags'] ?? []) as $tag): if (trim((string)$tag) === '') continue; ?>
        <category><?php echo qm_xml($tag); ?></category>
        <?php endforeach; ?>
        <pubDate><?php echo date('r', $created); ?></pubDate>
        <?php if ($updated !== $created): ?>
        <dc:date><?php echo gmdate('Y-m-d\TH:i:s\Z', $updated); ?></dc:date>
        <?php endif; ?>
        <description><![CDATA[<?php echo qm_cdata($excerpt); ?>]]></description>
        <?php if ($contentFull !== ''): ?>
        <content:encoded><![CDATA[<?php echo qm_cdata($contentFull); ?>]]></content:encoded>
        <?php endif; ?>
    </item>
    <?php endforeach; ?>
</channel>
</rss>
