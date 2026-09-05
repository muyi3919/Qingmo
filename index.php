<?php
require_once 'includes/functions.php';
qm_session_start();

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
                        <a href="index.php?page=tag&slug=<?php echo urlencode($tag['slug']); ?>"><?php echo e($tag['name']); ?></a><?php if ($i < count($tags) - 1) echo ', '; ?>
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
            http_response_code(404);
            $pageTitle = '文章不存在';
            include 'includes/header.php';
            echo '<div class="post-content"><h1>文章不存在</h1><p>你要找的文章可能已被删除或尚未发布。</p><p><a href="index.php">返回首页</a></p></div>';
            include 'includes/footer.php';
            exit;
        }

        // 更新阅读数（仅 GET 正常浏览计一次）
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $post['view_count'] = ($post['view_count'] ?? 0) + 1;
            save_post($id, $post);
        }
        
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
            $parentId = (int)($_POST['parent_id'] ?? 0);
            
            if ($name === '' || $email === '' || $content === '') {
                $msg = '请填写昵称、邮箱和评论内容。';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $msg = '邮箱格式不正确。';
            } else {
                if (!verify_csrf($_POST['csrf_token'] ?? '')) {
                    $msg = '安全验证失败，请刷新页面重试。';
                } else {
                    $status = get_setting('comment_moderation', '0') == '1' ? 0 : 1;
                    $newComment = [
                        'post_id' => $id,
                        'parent_id' => $parentId > 0 ? $parentId : 0,
                        'author_name' => $name,
                        'author_email' => $email,
                        'author_url' => $url,
                        'content' => $content,
                        'status' => $status,
                    ];
                    // 插件扩展点：写入前的评论数据（归属地插件在此补 IP/地区、反垃圾插件可校验拦截）
                    $newComment = apply_filters('qm_comment_data', $newComment, $post);
                    if ($newComment === false) {
                        // 插件可通过 $GLOBALS['qm_comment_error'] 提供具体拒绝原因
                        $rejectMsg = isset($GLOBALS['qm_comment_error'])
                            ? (string)$GLOBALS['qm_comment_error']
                            : '评论未通过校验，请检查后重试。';
                        $_SESSION['flash_msg'] = $rejectMsg;
                        header('Location: index.php?page=post&id=' . $id);
                        exit;
                    }
                    add_comment($newComment);
                    // 重算已审核评论数，保证前后台计数一致
                    $post = load_post($id);
                    recount_comment_count($id);
                    // 管理员邮件通知（后台开启时）
                    qm_notify_new_comment($newComment, $post);
                    // @提及提醒：给评论中 @ 到的历史评论者发邮件
                    qm_notify_mentions($newComment, $post);
                    // 回复提醒：点“回复”时给被回复的评论者发邮件
                    qm_notify_reply($newComment, $post);
                    // 用 cookie 记住访客昵称/邮箱/主页，方便下次留言
                    $guestProfile = base64_encode(json_encode(['n' => $name, 'e' => $email, 'u' => $url], JSON_UNESCAPED_UNICODE));
                    @setcookie('qm_guest', $guestProfile, time() + 31536000, '/', '', false, true);
                    $_SESSION['flash_msg'] = $status ? '评论提交成功！' : '评论已提交，等待审核。';
                    header('Location: index.php?page=post&id=' . $id);
                    exit;
                }
            }
        }
        
        if (isset($_SESSION['flash_msg'])) {
            $msg = $_SESSION['flash_msg'];
            unset($_SESSION['flash_msg']);
        }
        
        // 获取已审核评论（按 parent_id 组装成回复树）
        $commentTree = build_comment_tree($id);
        $comments = $commentTree['by_id'];
        $totalComments = count($comments);
        
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
                <?php echo apply_filters('qm_post_content', $post['content'], $post); ?>
            </div>
            <?php if ($tags): ?>
            <div class="tag-list">
                <strong>标签：</strong>
                <?php foreach ($tags as $i => $tag): ?>
                    <a href="index.php?page=tag&slug=<?php echo urlencode($tag['slug']); ?>"><?php echo e($tag['name']); ?></a><?php if ($i < count($tags) - 1) echo ', '; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php echo render_post_footer($post); ?>
        </div>
        
        <div class="comment-list">
            <h3>评论 (<?php echo $totalComments; ?>)</h3>
            <?php if ($msg): ?>
                <div class="msg <?php echo (strpos($msg, '成功') !== false || strpos($msg, '审核') !== false) ? 'success' : 'error'; ?>">
                    <?php echo e($msg); ?>
                </div>
            <?php endif; ?>

            <?php
            // @昵称 → 对应评论锚点（用于高亮与定位）
            $commentAnchor = [];
            foreach ($commentTree['by_id'] as $cc) {
                $k = mb_strtolower((string)($cc['author_name'] ?? ''));
                if ($k !== '' && !isset($commentAnchor[$k])) $commentAnchor[$k] = (int)$cc['id'];
            }
            $highlightAt = function ($text) use ($commentAnchor) {
                return preg_replace_callback('/@([\p{L}\p{N}_\-]{1,24})/u', function ($mm) use ($commentAnchor) {
                    $key = mb_strtolower($mm[1]);
                    if (isset($commentAnchor[$key])) {
                        return '<a class="at-mention" href="#comment-' . $commentAnchor[$key] . '">@' . $mm[1] . '</a>';
                    }
                    return $mm[0];
                }, $text);
            };
            // 递归渲染评论（含子回复）
            $renderComment = function ($node, $depth = 0) use (&$renderComment, $highlightAt) {
                if ($depth > 3) return; // 超过 3 层不再展开
                $parentId = (int)($node['parent_id'] ?? 0);
                ?>
                <div class="comment-item comment-depth-<?php echo min($depth, 3); ?><?php echo $parentId ? ' is-reply' : ''; ?>" id="comment-<?php echo (int)$node['id']; ?>">
                    <div class="comment-meta">
                        <?php if ($parentId): ?><span class="reply-badge">回复</span><?php endif; ?>
                        <strong><?php echo e($node['author_name']); ?></strong>
                        <?php if (!empty($node['author_url'])): ?>
                            (<a href="<?php echo e($node['author_url']); ?>" target="_blank" rel="noopener nofollow">主页</a>)
                        <?php endif; ?>
                        发表于 <?php echo format_date($node['created_at']); ?>
                    </div>
                    <?php do_action('qm_comment_meta', $node); // 插件扩展点：评论归属地等小徽标 ?>
                    <div class="comment-content">
                        <?php echo $highlightAt(qm_emotions_render_html(nl2br(e($node['content'])))); ?>
                    </div>
                    <?php if (get_setting('allow_comments', '1') == '1'): ?>
                        <button type="button" class="reply-btn" data-id="<?php echo (int)$node['id']; ?>" data-name="<?php echo e($node['author_name']); ?>">↩ 回复</button>
                    <?php endif; ?>
                    <?php if (!empty($node['children'])): ?>
                        <div class="comment-children">
                            <?php foreach ($node['children'] as $child) $renderComment($child, $depth + 1); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php
            };
            if ($totalComments > 0):
                foreach ($commentTree['top'] as $topNode) $renderComment($topNode, 0);
            else:
            ?>
                <p class="comment-empty">还没有评论，来抢沙发吧~</p>
            <?php endif; ?>

            <?php if (get_setting('allow_comments', '1') == '1'): ?>
            <?php
            // 从 cookie 读取上次填写的访客信息
            $qmGuest = ['n' => '', 'e' => '', 'u' => ''];
            if (!empty($_COOKIE['qm_guest'])) {
                $g = json_decode(base64_decode((string)$_COOKIE['qm_guest']), true);
                if (is_array($g)) $qmGuest = $g + $qmGuest;
            }
            ?>
            <div class="comment-form" id="commentFormBox">
                <h4 id="commentFormTitle">发表评论</h4>
                <form method="post" action="" id="commentForm">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="parent_id" id="commentParentId" value="0">
                    <label>昵称 *</label>
                    <input type="text" name="author_name" value="<?php echo e($qmGuest['n']); ?>" required>
                    <label>邮箱 *（必填，仅用于接收回复/提及通知，不会公开展示）</label>
                    <input type="email" name="author_email" value="<?php echo e($qmGuest['e']); ?>" required>
                    <label>个人主页</label>
                    <input type="text" name="author_url" value="<?php echo e($qmGuest['u']); ?>" placeholder="http://">
                    <label>内容 *</label>
                    <?php echo qm_emotion_picker_html(); ?>
                    <textarea name="content" id="commentContent" required placeholder="友善发言，理性讨论~"></textarea>
                    <input type="submit" value="提交评论">
                    <button type="button" id="commentCancelReply" style="display:none;">取消回复</button>
                </form>
            </div>
            <?php else: ?>
                <p>评论已关闭。</p>
            <?php endif; ?>
        </div>

        <script>
        (function () {
            var form = document.getElementById('commentForm');
            if (!form) return;
            var parentId = document.getElementById('commentParentId');
            var title = document.getElementById('commentFormTitle');
            var cancelBtn = document.getElementById('commentCancelReply');
            var contentInput = document.getElementById('commentContent');
            var replyToName = '';

            document.querySelectorAll('.reply-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    parentId.value = btn.getAttribute('data-id');
                    replyToName = btn.getAttribute('data-name') || '';
                    title.textContent = '回复 @' + replyToName;
                    cancelBtn.style.display = 'inline-block';
                    document.getElementById('commentFormBox').scrollIntoView({ behavior: 'smooth', block: 'center' });
                    contentInput.focus();
                });
            });
            cancelBtn.addEventListener('click', function () {
                parentId.value = '0';
                replyToName = '';
                title.textContent = '发表评论';
                cancelBtn.style.display = 'none';
            });
            // 表情包：可折叠，点击按钮展开/收起面板
            var emotionToggle = document.getElementById('emotionToggle');
            var emotionPanel = document.getElementById('emotionPanel');
            if (emotionToggle && emotionPanel) {
                emotionToggle.addEventListener('click', function () {
                    var hidden = emotionPanel.style.display === 'none';
                    emotionPanel.style.display = hidden ? '' : 'none';
                    emotionToggle.setAttribute('aria-expanded', hidden ? 'true' : 'false');
                });
                // 表情包：text 直接插入；sticker 插入 :code:（展示时替换为图片）
                emotionPanel.addEventListener('click', function (e) {
                    var btn = e.target.closest ? e.target.closest('button.em') : null;
                    if (!btn) return;
                    var ins = btn.getAttribute('data-kind') === 'sticker'
                        ? ':' + btn.getAttribute('data-code') + ':'
                        : btn.getAttribute('data-val');
                    if (ins == null) return;
                    var s = contentInput.selectionStart, end = contentInput.selectionEnd;
                    var v = contentInput.value;
                    contentInput.value = v.slice(0, s) + ins + v.slice(end);
                    contentInput.focus();
                    contentInput.setSelectionRange(s + ins.length, s + ins.length);
                });
            }
        })();
        </script>
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
            http_response_code(404);
            $pageTitle = '分类不存在';
            include 'includes/header.php';
            echo '<h2>分类不存在</h2><p>该分类可能已被删除。</p><p><a href="index.php">返回首页</a></p>';
            include 'includes/footer.php';
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
            http_response_code(404);
            $pageTitle = '标签不存在';
            include 'includes/header.php';
            echo '<h2>标签不存在</h2><p>没有找到这个标签下的文章。</p><p><a href="index.php">返回首页</a></p>';
            include 'includes/footer.php';
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

    case 'links':
        // 友情链接页
        $pageTitle = '友情链接';
        $links = load_links();
        include 'includes/header.php';
        echo '<div class="links-page">';
        echo '<h2>友情链接</h2>';
        if (empty($links)) {
            echo '<p>暂无友情链接，欢迎联系站长交换友链。</p>';
        } else {
            echo '<p class="links-count">共收录 ' . count($links) . ' 个站点：</p>';
            echo '<div class="friend-links">';
            foreach ($links as $fl):
                ?>
                <div class="friend-link">
                    <a class="friend-link-name" href="<?php echo e($fl['url']); ?>" target="_blank" rel="noopener nofollow"><?php echo e($fl['name']); ?></a>
                    <?php if (!empty($fl['description'])): ?>
                        <p class="friend-link-desc"><?php echo e($fl['description']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach;
            echo '</div>';
        }
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
