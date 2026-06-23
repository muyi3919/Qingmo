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
                <li><a href="admin/login.php">后台登录</a></li>
            </ul>
        </div>
    </div>
    <div class="clear"></div>
    <div class="footer">
        <p>Powered by <strong>2000 Blog</strong> &copy; <?php echo date('Y'); ?> | 朴素博客系统</p>
    </div>
</div>
</body>
</html>
