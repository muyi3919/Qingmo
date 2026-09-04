<?php
/**
 * 插件设置页：SEO 优化
 */
if (!defined('QM_BOOT')) { exit('Access denied'); }

$ok = '';
$err = '';
$sitemapMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $err = '安全验证失败，请刷新页面重试。';
    } else {
        if (isset($_POST['save_seo'])) {
            qm_seo_save([
                'enabled'    => (int)($_POST['enabled'] ?? 1),
                'canonical'  => (int)($_POST['canonical'] ?? 0),
                'og'         => (int)($_POST['og'] ?? 0),
                'twitter'    => (int)($_POST['twitter'] ?? 0),
                'jsonld'     => (int)($_POST['jsonld'] ?? 0),
                'default_desc' => trim($_POST['default_desc'] ?? ''),
            ]);
            $ok = '设置已保存。';
        }
        if (isset($_POST['make_sitemap'])) {
            $n = qm_seo_generate_sitemap();
            if ($n === false) {
                $err = 'sitemap.xml 写入失败，请检查站点根目录是否可写。';
            } else {
                $sitemapMsg = 'sitemap.xml 已生成，共收录 ' . $n . ' 个地址：' . e(site_base_url() . '/sitemap.xml');
            }
        }
    }
}

$cfg = load_data(DATA_DIR . '/plugin_seo.php', []);
?>

<?php if ($ok): ?>
    <div class="msg success"><?php echo e($ok); ?></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="msg error"><?php echo e($err); ?></div>
<?php endif; ?>
<?php if ($sitemapMsg): ?>
    <div class="msg" style="background:#eef6ff;border-color:#9cc5f5;"><?php echo $sitemapMsg; ?></div>
<?php endif; ?>

<form method="post" class="admin-form" style="max-width:620px;">
    <?php csrf_field(); ?>
    <label>启用 SEO 输出</label>
    <select name="enabled">
        <option value="1" <?php echo ($cfg['enabled'] ?? 1) == 1 ? 'selected' : ''; ?>>开启</option>
        <option value="0" <?php echo ($cfg['enabled'] ?? 1) == 0 ? 'selected' : ''; ?>>关闭</option>
    </select>

    <div style="display:flex;gap:24px;flex-wrap:wrap;margin-top:8px;">
        <label><input type="checkbox" name="canonical" value="1" <?php echo ($cfg['canonical'] ?? 1) == 1 ? 'checked' : ''; ?>> canonical</label>
        <label><input type="checkbox" name="og" value="1" <?php echo ($cfg['og'] ?? 1) == 1 ? 'checked' : ''; ?>> Open Graph</label>
        <label><input type="checkbox" name="twitter" value="1" <?php echo ($cfg['twitter'] ?? 1) == 1 ? 'checked' : ''; ?>> Twitter Card</label>
        <label><input type="checkbox" name="jsonld" value="1" <?php echo ($cfg['jsonld'] ?? 1) == 1 ? 'checked' : ''; ?>> 文章 JSON-LD</label>
    </div>

    <label>默认页面描述（文章页自动取摘要/正文前 150 字）</label>
    <textarea name="default_desc" style="height:70px;" placeholder="站点的一句话描述"><?php echo e($cfg['default_desc'] ?? ''); ?></textarea>

    <p><input type="submit" name="save_seo" value="保存设置"></p>
</form>

<form method="post" class="admin-form" style="max-width:620px;margin-top:18px;">
    <?php csrf_field(); ?>
    <p style="font-size:13px;color:#666;">生成 <code>sitemap.xml</code> 到站点根目录（含首页/归档/友链/关于/RSS/分类/全部文章）。</p>
    <p><input type="submit" name="make_sitemap" value="生成 sitemap.xml"></p>
</form>
