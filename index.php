<?php
require_once 'includes/functions.php';
session_start();

// 未安装时自动跳转
if (!file_exists(DATA_DIR . '/config.php') && basename($_SERVER['PHP_SELF']) !== 'install.php') {
    header('Location: install.php');
    exit;
}

$page = $_GET['page'] ?? 'home';

switch ($page) {
    case 'home':
    case 'archive':
        // 首页/文章列表
        $perPage = (int)get_setting('posts_per_page', 10);
        $p = (int)($_GET['page_num'] ?? 1);
        $posts = load_posts(['status' => 1]);
        $total = count($posts);
        $pg = paginate($total, $p, $perPage);
        $posts = array_slice($posts, $pg['offset'], $pg['perPage']);
        
        $pageTitle = $page === 'archive' ? '文章归档' : '首页';
        include 'includes/header.php';
        
        echo '<div class="post-list">';
        if ($page === 'archive') {
            echo '<h2>文章归档</h2>';
        }
        foreach ($posts as $post):
            $cats = load_categories();
            $catMap = array_column($cats, 'name', 'id');
            $tags = get_post_tags($post['id']);
        ?>
            <div class="post-item">
                <h2><a href="index.php?page=post&id=<?php echo $post['id']; ?>"><?php echo e($post['title']); ?></a></h2>
                <div class="post-meta">
                    发表于 <?php echo format_date($post['created_at']); ?> | 
                    分类：<a href="index.php?page=category&id=<?php echo $post['category_id']; ?>"><?php echo e($catMap[$post['category_id']] ?? '未分类'); ?></a> | 
                    评论：<?php echo $post['comment_count']; ?> | 
                    阅读：<?php echo $post['view_count']; ?>
                </div>
                <div class="post-summary">
                    <?php echo $post['summary'] ?: make_summary($post['content'], 200); ?>
                </div>
                <?php if ($tags): ?>
                <div class="tag-list">
                    标签：
                    <?php foreach ($tags as $i => $tag): ?>
                        <a href="index.php?page=tag&slug=<?php echo $tag['slug']; ?>"><?php echo e($tag['name']); ?></a><?php if ($i < count($tags) - 1) echo ', '; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; 
        echo pagination_html($pg, 'index.php?page=' . $page);
        echo '</div>';
        
        include 'includes/footer.php';
        break;

    case 'post':
        // 文章详情
        $id = (int)($_GET['id'] ?? 0);
        $post = load_post($id);
        if (!$post || ($post['status'] ?? 0) != 1) {
            echo '<p>文章不存在。</p>';
            exit;
        }
        
        // 更新阅读数
        $post['view_count'] = ($post['view_count'] ?? 0) + 1;
        save_post($id, $post);
        
        $tags = get_post_tags($id);
        $cats = load_categories();
        $catMap = array_column($cats, 'name', 'id');
        $pageTitle = $post['title'];
        
        // 处理评论提交
        $msg = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && get_setting('allow_comments', '1') == '1') {
            $name = trim($_POST['author_name'] ?? '');
            $email = trim($_POST['author_email'] ?? '');
            $url = trim($_POST['author_url'] ?? '');
            $content = trim($_POST['content'] ?? '');
            
            if ($name && $content) {
            if (!verify_csrf($_POST['csrf_token'] ?? '')) {
                $msg = '安全验证失败，请刷新页面重试。';
            } else {
                $status = get_setting('comment_moderation', '0') == '1' ? 0 : 1;
                add_comment([
                    'post_id' => $id,
                    'author_name' => $name,
                    'author_email' => $email,
                    'author_url' => $url,
                    'content' => $content,
                    'status' => $status,
                ]);
                $post['comment_count'] = ($post['comment_count'] ?? 0) + 1;
                save_post($id, $post);
                }
                $_SESSION['flash_msg'] = $status ? '评论提交成功！' : '评论已提交，等待审核。';
                header('Location: index.php?page=post&id=' . $id);
                exit;
            } else {
                $msg = '请填写昵称和评论内容。';
            }
        }
        
        if (isset($_SESSION['flash_msg'])) {
            $msg = $_SESSION['flash_msg'];
            unset($_SESSION['flash_msg']);
        }
        
        // 获取已审核评论
        $comments = array_filter(load_comments($id), function($c) { return $c['status'] == 1; });
        usort($comments, function($a, $b) {
            return ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0);
        });
        
        include 'includes/header.php';
        ?>
        <div class="post-content">
            <h1><?php echo e($post['title']); ?></h1>
            <div class="post-meta">
                作者：<?php echo e($post['author_name'] ?? '佚名'); ?> | 
                发表于 <?php echo format_date($post['created_at']); ?> | 
                分类：<a href="index.php?page=category&id=<?php echo $post['category_id']; ?>"><?php echo e($catMap[$post['category_id']] ?? '未分类'); ?></a> | 
                阅读：<?php echo $post['view_count']; ?> | 
                评论：<?php echo $post['comment_count']; ?>
            </div>
            <div class="content">
                <?php echo $post['content']; ?>
            </div>
            <?php if ($tags): ?>
            <div class="tag-list">
                <strong>标签：</strong>
                <?php foreach ($tags as $i => $tag): ?>
                    <a href="index.php?page=tag&slug=<?php echo $tag['slug']; ?>"><?php echo e($tag['name']); ?></a><?php if ($i < count($tags) - 1) echo ', '; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="comment-list">
            <h3>评论 (<?php echo count($comments); ?>)</h3>
            <?php if ($msg): ?>
                <div class="msg <?php echo (strpos($msg, '成功') !== false || strpos($msg, '审核') !== false) ? 'success' : 'error'; ?>">
                    <?php echo e($msg); ?>
                </div>
            <?php endif; ?>
            <?php foreach ($comments as $c): ?>
                <div class="comment-item">
                    <div class="comment-meta">
                        <strong><?php echo e($c['author_name']); ?></strong> 
                        <?php if ($c['author_url']): ?>
                            (<a href="<?php echo e($c['author_url']); ?>" target="_blank">主页</a>)
                        <?php endif; ?>
                        发表于 <?php echo format_date($c['created_at']); ?>
                    </div>
                    <div class="comment-content">
                        <?php echo nl2br(e($c['content'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (get_setting('allow_comments', '1') == '1'): ?>
            <div class="comment-form">
                <h4>发表评论</h4>
                <form method="post" action="">
                    <?php csrf_field(); ?>
                    <label>昵称 *</label>
                    <input type="text" name="author_name" required>
                    <label>邮箱</label>
                    <input type="email" name="author_email">
                    <label>个人主页</label>
                    <input type="text" name="author_url" placeholder="http://">
                    <label>内容 *</label>
                    <textarea name="content" required></textarea>
                    <input type="submit" value="提交评论">
                </form>
            </div>
            <?php else: ?>
                <p>评论已关闭。</p>
            <?php endif; ?>
        </div>
        <?php
        include 'includes/footer.php';
        break;

    case 'category':
        // 分类文章
        $id = (int)($_GET['id'] ?? 0);
        $cats = load_categories();
        $cat = null;
        foreach ($cats as $c) {
            if ($c['id'] == $id) { $cat = $c; break; }
        }
        if (!$cat) {
            echo '<p>分类不存在。</p>';
            exit;
        }
        
        $perPage = (int)get_setting('posts_per_page', 10);
        $p = (int)($_GET['page_num'] ?? 1);
        $posts = load_posts(['status' => 1, 'category_id' => $id]);
        $total = count($posts);
        $pg = paginate($total, $p, $perPage);
        $posts = array_slice($posts, $pg['offset'], $pg['perPage']);
        
        $pageTitle = '分类：' . $cat['name'];
        include 'includes/header.php';
        
        echo '<h2>分类：' . e($cat['name']) . '</h2>';
        echo '<p>' . e($cat['description']) . '</p>';
        echo '<div class="post-list">';
        foreach ($posts as $post):
        ?>
            <div class="post-item">
                <h2><a href="index.php?page=post&id=<?php echo $post['id']; ?>"><?php echo e($post['title']); ?></a></h2>
                <div class="post-meta">
                    发表于 <?php echo format_date($post['created_at']); ?> | 评论：<?php echo $post['comment_count']; ?> | 阅读：<?php echo $post['view_count']; ?>
                </div>
                <div class="post-summary">
                    <?php echo $post['summary'] ?: make_summary($post['content'], 200); ?>
                </div>
            </div>
        <?php endforeach; 
        echo pagination_html($pg, 'index.php?page=category&id=' . $id);
        echo '</div>';
        
        include 'includes/footer.php';
        break;

    case 'tag':
        // 标签文章
        $slug = $_GET['slug'] ?? '';
        $tag = find_tag($slug);
        if (!$tag) {
            echo '<p>标签不存在。</p>';
            exit;
        }
        
        $perPage = (int)get_setting('posts_per_page', 10);
        $p = (int)($_GET['page_num'] ?? 1);
        $posts = load_posts_by_tag($slug);
        $total = count($posts);
        $pg = paginate($total, $p, $perPage);
        $posts = array_slice($posts, $pg['offset'], $pg['perPage']);
        
        $pageTitle = '标签：' . $tag['name'];
        include 'includes/header.php';
        
        echo '<h2>标签：' . e($tag['name']) . '</h2>';
        echo '<div class="post-list">';
        foreach ($posts as $post):
        ?>
            <div class="post-item">
                <h2><a href="index.php?page=post&id=<?php echo $post['id']; ?>"><?php echo e($post['title']); ?></a></h2>
                <div class="post-meta">
                    发表于 <?php echo format_date($post['created_at']); ?> | 评论：<?php echo $post['comment_count']; ?> | 阅读：<?php echo $post['view_count']; ?>
                </div>
                <div class="post-summary">
                    <?php echo $post['summary'] ?: make_summary($post['content'], 200); ?>
                </div>
            </div>
        <?php endforeach; 
        echo pagination_html($pg, 'index.php?page=tag&slug=' . urlencode($slug));
        echo '</div>';
        
        include 'includes/footer.php';
        break;

    case 'about':
        $about = load_about();
        $pageTitle = $about['title'] ?? '关于';
        include 'includes/header.php';
        echo '<div class="about-page">';
        echo '<h2>' . e($about['title'] ?? '关于') . '</h2>';
        echo $about['content'] ?? '';
        echo '</div>';
        include 'includes/footer.php';
        break;

    case 'search':
        $keyword = trim($_GET['q'] ?? '');
        $pageTitle = '搜索：' . $keyword;
        include 'includes/header.php';

        echo '<h2>搜索：' . e($keyword) . '</h2>';

        if (empty($keyword)) {
            echo '<p>请输入搜索关键词。</p>';
        } else {
            $results = search_posts($keyword);
            $total = count($results);

            if ($total == 0) {
                echo '<p>没有找到相关文章。</p>';
            } else {
                echo '<p>找到 ' . $total . ' 篇相关文章：</p>';
                echo '<div class="post-list">';
                foreach ($results as $post):
                    $cats = load_categories();
                    $catMap = array_column($cats, 'name', 'id');
                ?>
                    <div class="post-item">
                        <h2><a href="index.php?page=post&id=<?php echo $post['id']; ?>"><?php echo e($post['title']); ?></a></h2>
                        <div class="post-meta">
                            发表于 <?php echo format_date($post['created_at']); ?> | 
                            分类：<a href="index.php?page=category&id=<?php echo $post['category_id']; ?>"><?php echo e($catMap[$post['category_id']] ?? '未分类'); ?></a> | 
                            评论：<?php echo $post['comment_count']; ?> | 
                            阅读：<?php echo $post['view_count']; ?>
                        </div>
                        <div class="post-summary">
                            <?php echo $post['summary'] ?: make_summary($post['content'], 200); ?>
                        </div>
                    </div>
                <?php endforeach;
                echo '</div>';
            }
        }

        include 'includes/footer.php';
        break;

    default:
        header('Location: index.php');
        exit;
}
