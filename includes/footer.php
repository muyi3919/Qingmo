    </div>
    <div class="sidebar">
        <div class="box">
            <h3>分类</h3>
            <ul>
            <?php
            $cats = load_categories();
            usort($cats, function($a, $b) { return ($b['post_count'] ?? 0) <=> ($a['post_count'] ?? 0); });
            foreach ($cats as $cat):
            ?>
                <li><a href="index.php?page=category&id=<?php echo $cat['id']; ?>"><?php echo e($cat['name']); ?></a> (<?php echo $cat['post_count'] ?? 0; ?>)</li>
            <?php endforeach; ?>
            </ul>
        </div>
        <div class="box">
            <h3>最新评论</h3>
            <ul>
            <?php
            $comments = load_comments();
            $comments = array_filter($comments, function($c) { return ($c['status'] ?? 0) == 1; });
            usort($comments, function($a, $b) { return ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0); });
            $comments = array_slice($comments, 0, 5);
            $allPosts = load_posts();
            $postTitles = array_column($allPosts, 'title', 'id');
            foreach ($comments as $c):
            ?>
                <li><?php echo e($c['author_name']); ?> 在 <a href="index.php?page=post&id=<?php echo $c['post_id']; ?>"><?php echo e($postTitles[$c['post_id']] ?? '未知'); ?></a> 中说：<?php echo e(make_summary($c['content'], 30)); ?></li>
            <?php endforeach; ?>
            </ul>
        </div>
        <div class="box">
            <h3>链接</h3>
            <ul>
                <li><a href="index.php">首页</a></li>
                <li><a href="index.php?page=links">友情链接</a></li>
                <li><a href="admin/login.php">后台登录</a></li>
                <?php do_action('qm_sidebar_links'); ?>
            </ul>
        </div>
        <?php
        // 友链小部件（有数据才显示）
        $sidebarLinks = load_links();
        if ($sidebarLinks):
        ?>
        <div class="box">
            <h3>友情链接</h3>
            <ul class="friend-links-sidebar">
                <?php foreach (array_slice($sidebarLinks, 0, 10) as $fl): ?>
                    <li><a href="<?php echo e($fl['url']); ?>" target="_blank" rel="noopener nofollow"><?php echo e($fl['name']); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <?php do_action('qm_sidebar'); ?>
    </div>
    <div class="clear"></div>
    <div class="footer">
        <p>Powered by <strong>轻墨 (Qingmo)</strong> &copy; <?php echo date('Y'); ?> | <a href="rss.php">RSS</a> | <a href="atom.php">Atom</a></p>
        <?php do_action('qm_footer'); ?>
        <?php
        // 后台「站点设置 → 页脚自定义」内容（信任管理员，支持 HTML）
        $qmFooterExtra = trim((string)get_setting('footer_extra', ''));
        if ($qmFooterExtra !== '') echo $qmFooterExtra;
        ?>
    </div>
</div>
<?php if (!empty($qmThemeSupportsDark)): ?>
<script>
(function () {
    var btn = document.getElementById('themeToggle');
    if (!btn) return;
    var root = document.documentElement;
    btn.addEventListener('click', function () {
        var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-theme', next);
        try { localStorage.setItem('qm-theme', next); } catch (e) {}
    });
})();
</script>
<?php endif; ?>
</body>
</html>
