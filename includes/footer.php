    </div>
    <div class="sidebar">
        <?php do_action('qm_sidebar_top'); // 侧栏顶部（分类之前），公告类小部件常用 ?>
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
        <?php
        // 友链小部件（有数据才显示；排序：名称 / 随机）
        $sidebarLinks = array_slice(get_links_ordered(), 0, 10);
        if ($sidebarLinks):
        ?>
        <div class="box">
            <h3>友情链接</h3>
            <ul class="friend-links-sidebar">
                <?php foreach ($sidebarLinks as $fl): ?>
                    <li>
                        <?php if (!empty($fl['icon'])): ?>
                            <img src="<?php echo e($fl['icon']); ?>" alt="" style="width:16px;height:16px;border-radius:3px;vertical-align:-3px;margin-right:4px;" onerror="this.style.display='none'">
                        <?php endif; ?>
                        <a href="<?php echo e($fl['url']); ?>" target="_blank" rel="noopener nofollow"><?php echo e($fl['name']); ?></a>
                    </li>
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
<script>
// 轻墨：免刷新自动更新（新评论 ~12s / 新文章 ~45s 自动上屏，无需手动刷新）
(function () {
    if (window.__qmAutoRefresh) return;
    window.__qmAutoRefresh = true;
    var pageHidden = false;
    document.addEventListener('visibilitychange', function () {
        pageHidden = document.hidden;
    });

    function poll(url, onGot) {
        if (pageHidden) { onGot({}); return; }
        fetch(url, { cache: 'no-store', credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) { onGot(d && d.ok ? d : {}); })
            .catch(function () { onGot({}); });
    }

    // ---------- 新评论自动上屏 ----------
    var area = document.getElementById('qmCommentArea');
    if (area && area.getAttribute('data-post')) {
        var postId = area.getAttribute('data-post');
        var lastCount = parseInt(area.getAttribute('data-count') || '0', 10);
        var busy = false;
        function refreshComments() {
            if (busy) return;
            busy = true;
            poll('index.php?page=ajax&act=comments&id=' + encodeURIComponent(postId), function (d) {
                busy = false;
                if (typeof d.count === 'number' && d.count !== lastCount && typeof d.html === 'string') {
                    // 记住用户正展开着哪些楼，刷新后原样展开
                    var openKids = [];
                    area.querySelectorAll('.qm-replies-toggle[aria-expanded="true"]').forEach(function (b) {
                        openKids.push(b.getAttribute('data-target'));
                    });
                    area.innerHTML = d.html;
                    lastCount = d.count;
                    openKids.forEach(function (id) {
                        var b = area.querySelector('.qm-replies-toggle[data-target="' + id + '"]');
                        var box = document.getElementById(id);
                        if (b && box) {
                            b.setAttribute('aria-expanded', 'true');
                            box.style.display = '';
                        }
                    });
                }
            });
        }
        setInterval(refreshComments, 12000);
    }

    // ---------- 留言板新留言自动上屏 ----------
    var gbArea = document.getElementById('qmGuestbookArea');
    if (gbArea) {
        var gbLast = parseInt(gbArea.getAttribute('data-count') || '0', 10);
        var gbBusy = false;
        function refreshGuestbook() {
            if (gbBusy) return;
            gbBusy = true;
            poll('index.php?page=ajax&act=messages', function (d) {
                gbBusy = false;
                if (typeof d.count === 'number' && d.count !== gbLast && typeof d.html === 'string') {
                    gbArea.innerHTML = d.html;
                    gbLast = d.count;
                }
            });
        }
        setInterval(refreshGuestbook, 15000);
    }

    // ---------- 新文章自动上屏（首页/归档第 1 页内） ----------
    var list = document.getElementById('qmPostList');
    var listInner = document.getElementById('qmPostListInner');
    if (list && listInner) {
        var route = list.getAttribute('data-page') || 'home';
        var num = parseInt(list.getAttribute('data-num') || '1', 10);
        var lastTotal = -1;
        var busy2 = false;
        function refreshPosts() {
            if (busy2) return;
            busy2 = true;
            poll('index.php?page=ajax&act=posts&route=' + encodeURIComponent(route) + '&num=' + num, function (d) {
                busy2 = false;
                if (typeof d.total === 'number') {
                    if (lastTotal === -1) { lastTotal = d.total; return; }
                    if (d.total !== lastTotal && typeof d.html === 'string') {
                        listInner.innerHTML = d.html;
                        lastTotal = d.total;
                    }
                }
            });
        }
        setInterval(refreshPosts, 45000);
    }
})();
</script>
<script>
// 轻墨：图片点击放大（评论区/正文），支持 ‹ › 翻页、Esc / 点空白 / ✕ 关闭
(function () {
    if (window.__qmZoomLoaded) return;
    window.__qmZoomLoaded = true;

    function isZoomable(im) {
        if (im.closest('a, button, label, .emotion-panel')) return false;
        if (im.classList.contains('comment-avatar')) return false;
        if (im.classList.contains('qm-emoticon')) return false; // emoji 表情不弹灯箱
        return !!im.closest('.comment-content, .post-content .content');
    }
    function collectFrom(im) {
        var scope = im.closest('.comment-item, .post-content') || document;
        var others = Array.prototype.slice.call(scope.querySelectorAll('img')).filter(function (x) {
            return x !== im && isZoomable(x);
        });
        others.unshift(im);
        return others;
    }

    var ov = null, cur = [], idx = 0;

    function render() {
        var big = ov.querySelector('.qm-zoom-img');
        var cap = ov.querySelector('.qm-zoom-cap');
        var c = cur[idx];
        big.src = c.currentSrc || c.src;
        var t = (c.getAttribute('alt') || '').trim() || (c.getAttribute('title') || '').trim();
        cap.textContent = cur.length > 1 ? (t ? t + '　' : '') + (idx + 1) + ' / ' + cur.length : t;
        ov.querySelector('.qm-zoom-prev').style.visibility = cur.length > 1 ? 'visible' : 'hidden';
        ov.querySelector('.qm-zoom-next').style.visibility = cur.length > 1 ? 'visible' : 'hidden';
    }
    function close() {
        if (!ov) return;
        document.removeEventListener('keydown', onKey, true);
        ov.remove();
        ov = null;
        document.body.style.overflow = '';
    }
    function step(d) {
        idx = (idx + d + cur.length) % cur.length;
        render();
    }
    function onKey(e) {
        if (!ov) return;
        if (e.key === 'Escape') close();
        else if (e.key === 'ArrowLeft') step(-1);
        else if (e.key === 'ArrowRight') step(1);
    }
    function open(list, start) {
        cur = list;
        idx = start;
        if (!ov) {
            ov = document.createElement('div');
            ov.className = 'qm-zoom';
            ov.style.cssText = 'position:fixed;left:0;top:0;right:0;bottom:0;z-index:2147483000;'
                + 'background:rgba(0,0,0,.92);display:flex;align-items:center;justify-content:center;flex-direction:column;';
            ov.innerHTML = '<img class="qm-zoom-img" alt="" style="max-width:92vw;max-height:84vh;object-fit:contain;'
                + 'border-radius:6px;box-shadow:0 8px 40px rgba(0,0,0,.6);background:#111;">'
                + '<div class="qm-zoom-cap" style="color:#ccc;font-size:13px;margin-top:12px;max-width:88vw;text-align:center;word-break:break-all;"></div>'
                + '<button type="button" class="qm-zoom-close" aria-label="关闭" style="position:absolute;top:14px;right:18px;'
                + 'background:none;border:0;color:#fff;font-size:28px;cursor:pointer;line-height:1;">✕</button>'
                + '<button type="button" class="qm-zoom-prev" aria-label="上一张" style="position:absolute;left:14px;top:50%;'
                + 'transform:translateY(-50%);background:rgba(255,255,255,.14);color:#fff;border:0;border-radius:50%;'
                + 'width:44px;height:44px;font-size:20px;cursor:pointer;">‹</button>'
                + '<button type="button" class="qm-zoom-next" aria-label="下一张" style="position:absolute;right:14px;top:50%;'
                + 'transform:translateY(-50%);background:rgba(255,255,255,.14);color:#fff;border:0;border-radius:50%;'
                + 'width:44px;height:44px;font-size:20px;cursor:pointer;">›</button>';
            ov.addEventListener('click', function (e) { if (e.target === ov) close(); });
            ov.querySelector('.qm-zoom-close').addEventListener('click', function (e) { e.stopPropagation(); close(); });
            ov.querySelector('.qm-zoom-prev').addEventListener('click', function (e) { e.stopPropagation(); step(-1); });
            ov.querySelector('.qm-zoom-next').addEventListener('click', function (e) { e.stopPropagation(); step(1); });
            document.body.appendChild(ov);
            document.body.style.overflow = 'hidden';
            document.addEventListener('keydown', onKey, true);
        }
        render();
    }

    document.addEventListener('click', function (e) {
        var im = e.target;
        if (!im || im.tagName !== 'IMG' || !isZoomable(im)) return;
        e.preventDefault();
        e.stopPropagation();
        open(collectFrom(im), 0);
    }, true);
})();
</script>
</body>
</html>
