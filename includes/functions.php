<?php
/**
 * 纯文件数据层 - 无需数据库
 * 所有数据以 PHP return 数组格式存储在 data/ 目录下
 */

define('DATA_DIR', __DIR__ . '/../data');
define('POSTS_DIR', DATA_DIR . '/posts');

/**
 * 加载数据文件
 */
function load_data($file, $default = []) {
    if (!file_exists($file)) return $default;
    return include $file;
}

/**
 * 保存数据到文件
 */
function save_data($file, $data) {
    $dir = dirname($file);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $content = "<?php\n// 数据文件，由系统自动生成\nreturn " . var_export($data, true) . ";\n";
    file_put_contents($file, $content, LOCK_EX);
}

/**
 * 获取下一个文章ID
 */
function next_post_id() {
    $file = DATA_DIR . '/counter.php';
    $id = load_data($file, 0) + 1;
    save_data($file, $id);
    return $id;
}

/**
 * 加载所有文章
 */
function load_posts($filter = []) {
    if (!is_dir(POSTS_DIR)) return [];
    $posts = [];
    foreach (glob(POSTS_DIR . '/*.php') as $file) {
        $post = load_data($file);
        if (!is_array($post)) continue;
        // 过滤
        if (isset($filter['status']) && ($post['status'] ?? 1) != $filter['status']) continue;
        if (isset($filter['category_id']) && ($post['category_id'] ?? 0) != $filter['category_id']) continue;
        $posts[] = $post;
    }
    // 按时间倒序
    usort($posts, function($a, $b) {
        return ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0);
    });
    return $posts;
}

/**
 * 加载单篇文章
 */
function load_post($id) {
    $file = POSTS_DIR . '/' . (int)$id . '.php';
    return load_data($file, null);
}

/**
 * 保存文章
 */
function save_post($id, $data) {
    if (!isset($data['id'])) $data['id'] = (int)$id;
    $file = POSTS_DIR . '/' . (int)$id . '.php';
    save_data($file, $data);
}

/**
 * 删除文章
 */
function delete_post($id) {
    $file = POSTS_DIR . '/' . (int)$id . '.php';
    if (file_exists($file)) unlink($file);
    // 删除相关评论
    $comments = load_comments();
    $comments = array_filter($comments, function($c) use ($id) {
        return $c['post_id'] != $id;
    });
    save_comments(array_values($comments));
}

/**
 * 加载所有评论
 */
function load_comments($post_id = null) {
    $comments = load_data(DATA_DIR . '/comments.php', []);
    if ($post_id !== null) {
        return array_filter($comments, function($c) use ($post_id) {
            return $c['post_id'] == $post_id;
        });
    }
    return $comments;
}

/**
 * 保存评论
 */
function save_comments($comments) {
    save_data(DATA_DIR . '/comments.php', $comments);
}

/**
 * 添加评论
 */
function add_comment($data) {
    $comments = load_comments();
    $data['id'] = count($comments) > 0 ? max(array_column($comments, 'id')) + 1 : 1;
    $data['created_at'] = time();
    $comments[] = $data;
    save_comments($comments);
    return $data['id'];
}

/**
 * 加载分类
 */
function load_categories() {
    return load_data(DATA_DIR . '/categories.php', []);
}

/**
 * 保存分类
 */
function save_categories($cats) {
    save_data(DATA_DIR . '/categories.php', $cats);
}

/**
 * 加载用户
 */
function load_users() {
    return load_data(DATA_DIR . '/users.php', []);
}

/**
 * 保存用户
 */
function save_users($users) {
    save_data(DATA_DIR . '/users.php', $users);
}

/**
 * 加载配置
 */
function load_config() {
    return load_data(DATA_DIR . '/config.php', []);
}

/**
 * 保存配置
 */
function save_config($config) {
    save_data(DATA_DIR . '/config.php', $config);
}

/**
 * 获取设置值
 */
function get_setting($name, $default = '') {
    $config = load_config();
    return $config[$name] ?? $default;
}

/**
 * 转义HTML输出
 */
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * 格式化日期
 */
function format_date($timestamp, $format = 'Y-m-d H:i') {
    if (is_numeric($timestamp)) {
        return date($format, (int)$timestamp);
    }
    return $timestamp;
}

/**
 * 生成摘要
 */
function make_summary($content, $length = 200) {
    $text = strip_tags($content);
    if (mb_strlen($text) > $length) {
        return mb_substr($text, 0, $length) . '...';
    }
    return $text;
}

/**
 * 分页
 */
function paginate($total, $page, $perPage) {
    $totalPages = max(1, ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    return [
        'page' => $page,
        'perPage' => $perPage,
        'totalPages' => $totalPages,
        'offset' => $offset,
        'hasPrev' => $page > 1,
        'hasNext' => $page < $totalPages
    ];
}

/**
 * 生成简单分页链接
 */
function pagination_html($paginate, $baseUrl) {
    $html = '<div class="pagination">';
    if ($paginate['hasPrev']) {
        $html .= '<a href="' . $baseUrl . '&page_num=' . ($paginate['page'] - 1) . '">« 上一页</a> ';
    }
    $html .= ' 第 ' . $paginate['page'] . ' / ' . $paginate['totalPages'] . ' 页 ';
    if ($paginate['hasNext']) {
        $html .= '<a href="' . $baseUrl . '&page_num=' . ($paginate['page'] + 1) . '">下一页 »</a>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * 检查是否已登录
 */
function is_logged_in() {
    return isset($_SESSION['admin_id']) && $_SESSION['admin_id'] > 0;
}

/**
 * 要求登录
 */
function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * 生成slug
 */
function slugify($str) {
    $str = preg_replace('/[^\w\x{4e00}-\x{9fff}]+/u', '-', $str);
    $str = trim($str, '-');
    if (empty($str)) $str = 'post-' . time();
    return mb_strtolower($str);
}

/**
 * 统计分类文章数
 */
function count_category_posts($cat_id) {
    $posts = load_posts(['status' => 1]);
    $count = 0;
    foreach ($posts as $p) {
        if (($p['category_id'] ?? 0) == $cat_id) $count++;
    }
    return $count;
}

/**
 * 刷新所有分类计数
 */
function refresh_all_categories() {
    $cats = load_categories();
    foreach ($cats as &$cat) {
        $cat['post_count'] = count_category_posts($cat['id']);
    }
    save_categories($cats);
}

/**
 * 获取文章的标签列表
 */
function get_post_tags($post_id) {
    $post = load_post($post_id);
    if (!$post) return [];
    $tags = [];
    foreach ($post['tags'] ?? [] as $name) {
        $tags[] = ['id' => slugify($name), 'name' => $name, 'slug' => slugify($name)];
    }
    return $tags;
}

/**
 * 设置文章标签
 */
function set_post_tags($post_id, $tag_string) {
    $post = load_post($post_id);
    if (!$post) return;
    $tags = array_filter(array_map('trim', explode(',', $tag_string)));
    $post['tags'] = array_values($tags);
    save_post($post_id, $post);
}

/**
 * 按标签查找文章
 */
function load_posts_by_tag($tag_slug) {
    $posts = load_posts(['status' => 1]);
    return array_filter($posts, function($p) use ($tag_slug) {
        foreach ($p['tags'] ?? [] as $tag) {
            if (slugify($tag) === $tag_slug) return true;
        }
        return false;
    });
}

/**
 * 获取所有标签
 */
function load_all_tags() {
    $posts = load_posts();
    $tags = [];
    foreach ($posts as $p) {
        foreach ($p['tags'] ?? [] as $tag) {
            $slug = slugify($tag);
            if (!isset($tags[$slug])) {
                $tags[$slug] = ['name' => $tag, 'slug' => $slug, 'count' => 0];
            }
            $tags[$slug]['count']++;
        }
    }
    return $tags;
}

/**
 * 查找标签（按ID/Slug）
 */
function find_tag($tag_id) {
    $tags = load_all_tags();
    if (isset($tags[$tag_id])) return $tags[$tag_id];
    foreach ($tags as $t) {
        if (slugify($t['name']) === $tag_id) return $t;
    }
    return null;
}


/**
 * 生成 CSRF Token
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 验证 CSRF Token
 */
function verify_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

/**
 * 输出 CSRF input 字段
 */
function csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">' . "\n";
}

/**
 * 搜索文章
 */
function search_posts($keyword) {
    $posts = load_posts(['status' => 1]);
    $keyword = mb_strtolower(trim($keyword));
    if (empty($keyword)) return [];

    return array_filter($posts, function($p) use ($keyword) {
        $title = mb_strtolower(strip_tags($p['title'] ?? ''));
        $content = mb_strtolower(strip_tags($p['content'] ?? ''));
        $summary = mb_strtolower(strip_tags($p['summary'] ?? ''));
        $tags = array_map('mb_strtolower', $p['tags'] ?? []);

        return str_contains($title, $keyword) 
            || str_contains($content, $keyword)
            || str_contains($summary, $keyword)
            || in_array($keyword, $tags);
    });
}

/**
 * 加载关于页面内容
 */
function load_about() {
    $file = DATA_DIR . '/about.php';
    if (!file_exists($file)) {
        return [
            'title' => '关于本站',
            'content' => '<p>这是一个使用 PHP 纯文件搭建的朴素博客系统。</p><p>设计风格模仿 2000 年代早期的互联网文字博客，没有花哨的视觉效果，只有简洁的阅读体验。</p><p>如果你怀念那个时代的互联网，希望这里能带给你一些熟悉的感觉。</p><p>本站使用 <strong>轻墨</strong> 系统驱动。</p>',
        ];
    }
    return load_data($file, []);
}

/**
 * 保存关于页面内容
 */
function save_about($data) {
    save_data(DATA_DIR . '/about.php', $data);
}
