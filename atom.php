<?php
/**
 * Atom 1.0 订阅源（规范实现）
 * 地址：/atom.php
 * 命名空间 https://validator.w3.org/feed/docs/atom.html
 */
require_once __DIR__ . '/includes/functions.php';

function qm_atom_esc($s) {
    return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], (string)$s);
}

function qm_atom_cdata($s) {
    return str_replace(']]>', ']]]]><![CDATA[>', (string)$s);
}

$posts = load_posts(['status' => 1]);
$posts = array_slice($posts, 0, 20);
$config = load_config();
$siteTitle = $config['site_title'] ?? '我的轻墨博客';
$siteDesc  = $config['site_description'] ?? '';
$siteUrl   = rtrim(site_base_url(), '/');

// feed 更新时间 = 最近文章时间或当前时间
$feedUpdated = 0;
foreach ($posts as $post) {
    $t = (int)($post['updated_at'] ?? $post['created_at'] ?? 0);
    if ($t > $feedUpdated) $feedUpdated = $t;
}
if ($feedUpdated === 0) $feedUpdated = time();

$cats = load_categories();
$catMap = array_column($cats, 'name', 'id');
$authorName = trim((string)get_setting('author_name', ''));
if ($authorName === '') $authorName = trim((string)get_setting('site_title', '轻墨'));

header('Content-Type: application/atom+xml; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<feed xmlns="http://www.w3.org/2005/Atom">
    <title><?php echo qm_atom_esc($siteTitle); ?></title>
    <subtitle><?php echo qm_atom_esc($siteDesc); ?></subtitle>
    <id><?php echo qm_atom_esc($siteUrl . '/'); ?></id>
    <link href="<?php echo qm_atom_esc($siteUrl); ?>" rel="alternate" type="text/html"/>
    <link href="<?php echo qm_atom_esc($siteUrl); ?>/atom.php" rel="self" type="application/atom+xml"/>
    <updated><?php echo gmdate('Y-m-d\TH:i:s\Z', $feedUpdated); ?></updated>
    <author>
        <name><?php echo qm_atom_esc($authorName); ?></name>
    </author>
    <generator uri="https://github.com/muyi3919/Qingmo" version="<?php echo qm_atom_esc(QM_VERSION); ?>">Qingmo</generator>
    <?php foreach ($posts as $post):
        $postId = (int)$post['id'];
        $url = $siteUrl . '/index.php?page=post&id=' . $postId;
        $created = (int)($post['created_at'] ?? time());
        $updated = (int)($post['updated_at'] ?? $created);
        $contentFull = (string)($post['content'] ?? '');
        $excerpt = ($post['summary'] ?? '') !== '' ? (string)$post['summary'] : make_summary($contentFull, 300);
        $excerpt = html_entity_decode(strip_tags($excerpt), ENT_QUOTES, 'UTF-8');
        $catName = isset($catMap[$post['category_id'] ?? 0]) ? (string)$catMap[$post['category_id'] ?? 0] : '';
        $entryAuthor = trim((string)($post['author_name'] ?? ''));
        if ($entryAuthor === '') $entryAuthor = $authorName;
    ?>
    <entry>
        <title><?php echo qm_atom_esc($post['title'] ?? ''); ?></title>
        <link href="<?php echo qm_atom_esc($url); ?>" rel="alternate" type="text/html"/>
        <id><?php echo qm_atom_esc($url); ?></id>
        <published><?php echo gmdate('Y-m-d\TH:i:s\Z', $created); ?></published>
        <updated><?php echo gmdate('Y-m-d\TH:i:s\Z', $updated); ?></updated>
        <author>
            <name><?php echo qm_atom_esc($entryAuthor); ?></name>
        </author>
        <?php if ($catName !== ''): ?>
        <category term="<?php echo qm_atom_esc($catName); ?>"/>
        <?php endif; ?>
        <?php foreach (($post['tags'] ?? []) as $tag): if (trim((string)$tag) === '') continue; ?>
        <category term="<?php echo qm_atom_esc($tag); ?>"/>
        <?php endforeach; ?>
        <summary type="html"><![CDATA[<?php echo qm_atom_cdata('<p>' . $excerpt . '</p>'); ?>]]></summary>
        <?php if ($contentFull !== ''): ?>
        <content type="html"><![CDATA[<?php echo qm_atom_cdata($contentFull); ?>]]></content>
        <?php endif; ?>
    </entry>
    <?php endforeach; ?>
</feed>
