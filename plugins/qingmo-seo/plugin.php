<?php
/**
 * 插件：SEO 优化
 * 依赖前台 qm_head 钩子（v2.2 起附带页面上下文：title/page/id/url）。
 * 功能：
 *   - canonical
 *   - Open Graph（og:title/type/url/description/站点名）
 *   - Twitter Card
 *   - 文章页 JSON-LD（BlogPosting）
 * 设置页可生成 sitemap.xml（收录首页/归档/关于/友链/分类/全部文章）。
 */
if (!defined('QM_BOOT')) { exit('Access denied'); }

function qm_seo_cfg($key, $default = null) {
    $d = load_data(DATA_DIR . '/plugin_seo.php', []);
    return $d[$key] ?? $default;
}

function qm_seo_save($arr) {
    save_data(DATA_DIR . '/plugin_seo.php', $arr);
}

function qm_seo_desc($content, $len = 150) {
    $t = trim(str_replace(["\n", "\r", '  '], ' ', strip_tags((string)$content)));
    if ($t === '') return '';
    return mb_strlen($t) > $len ? mb_substr($t, 0, $len) . '…' : $t;
}

function qm_seo_first_image($html) {
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', (string)$html, $m)) {
        return $m[1];
    }
    return '';
}

add_action('qm_head', 'qm_seo_head', 5);
function qm_seo_head($ctx = []) {
    if (!is_array($ctx) || (int)qm_seo_cfg('enabled', 1) !== 1) return;
    $siteTitle = get_setting('site_title', '我的博客');
    $pageTitle = (string)($ctx['title'] ?? '');
    $title = $pageTitle !== '' ? $pageTitle . ' - ' . $siteTitle : $siteTitle;
    $url = (string)($ctx['url'] ?? site_base_url());

    // 文章页上下文
    $post = null;
    $ogType = 'website';
    $desc = (string)qm_seo_cfg('default_desc', get_setting('site_description', ''));
    $image = '';
    if (($ctx['page'] ?? '') === 'post' && (int)($ctx['id'] ?? 0) > 0) {
        $p = load_post((int)$ctx['id']);
        if ($p && ($p['status'] ?? 0) == 1) {
            $post = $p;
            $ogType = 'article';
            $sum = (string)($p['summary'] ?? '');
            $desc = $sum !== '' ? $sum : qm_seo_desc($p['content'] ?? '', 150);
            $image = qm_seo_first_image($p['content'] ?? '');
        }
    }

    if ((int)qm_seo_cfg('canonical', 1) === 1) {
        echo '<link rel="canonical" href="' . e($url) . "\">\n";
    }

    if ((int)qm_seo_cfg('og', 1) === 1) {
        echo '<meta property="og:site_name" content="' . e($siteTitle) . "\">\n";
        echo '<meta property="og:title" content="' . e($title) . "\">\n";
        echo '<meta property="og:type" content="' . $ogType . "\">\n";
        echo '<meta property="og:url" content="' . e($url) . "\">\n";
        if ($desc !== '') echo '<meta property="og:description" content="' . e($desc) . "\">\n";
        if ($image !== '') echo '<meta property="og:image" content="' . e($image) . "\">\n";
    }

    if ((int)qm_seo_cfg('twitter', 1) === 1) {
        echo '<meta name="twitter:card" content="summary">' . "\n";
        echo '<meta name="twitter:title" content="' . e($title) . "\">\n";
        if ($desc !== '') echo '<meta name="twitter:description" content="' . e($desc) . "\">\n";
        if ($image !== '') echo '<meta name="twitter:image" content="' . e($image) . "\">\n";
    }

    if ($post !== null && (int)qm_seo_cfg('jsonld', 1) === 1) {
        $created = date('c', (int)($post['created_at'] ?? time()));
        $updated = date('c', (int)($post['updated_at'] ?? $post['created_at'] ?? time()));
        $author = (string)($post['author_name'] ?? '佚名');
        $ld = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => (string)($post['title'] ?? ''),
            'description' => $desc,
            'image' => $image,
            'datePublished' => $created,
            'dateModified' => $updated,
            'author' => ['@type' => 'Person', 'name' => $author],
            'mainEntityOfPage' => $url,
        ];
        if ($image === '') unset($ld['image']);
        echo '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";
    }
}

/**
 * 生成 sitemap.xml（收录静态页 + 分类 + 文章）
 */
function qm_seo_generate_sitemap() {
    $base = site_base_url();
    $urls = [];
    $now = date('c');

    foreach ([
        '' => $now, '?page=archive' => $now, '?page=links' => $now, '?page=about' => $now,
    ] as $q => $lm) {
        $urls[] = ['loc' => $base . '/index.php' . $q, 'lastmod' => $lm];
    }
    $urls[] = ['loc' => $base . '/rss.php', 'lastmod' => $now];

    foreach (load_categories() as $cat) {
        $urls[] = ['loc' => $base . '/index.php?page=category&id=' . (int)$cat['id'], 'lastmod' => $now];
    }
    foreach (load_posts(['status' => 1]) as $post) {
        $urls[] = [
            'loc' => $base . '/index.php?page=post&id=' . (int)$post['id'],
            'lastmod' => date('c', (int)($post['updated_at'] ?? $post['created_at'] ?? time())),
        ];
    }

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as $u) {
        $xml .= "  <url>\n    <loc>" . htmlspecialchars($u['loc'], ENT_QUOTES, 'UTF-8') . "</loc>\n";
        $xml .= "    <lastmod>" . $u['lastmod'] . "</lastmod>\n  </url>\n";
    }
    $xml .= '</urlset>' . "\n";

    $dest = ROOT_DIR . '/sitemap.xml';
    return @file_put_contents($dest, $xml) !== false ? count($urls) : false;
}
