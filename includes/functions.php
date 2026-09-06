<?php
/**
 * 纯文件数据层 - 无需数据库
 * 所有数据以 PHP return 数组格式存储在 data/ 目录下
 */

if (!defined('QM_BOOT')) {
    define('QM_BOOT', true);
    define('QM_VERSION', '2.5.2');                // 系统版本号（在线更新比对用）
    define('ROOT_DIR', dirname(__DIR__));          // 站点根目录
    define('INCLUDES_DIR', __DIR__);               // includes/
    define('DATA_DIR', ROOT_DIR . '/data');
    define('POSTS_DIR', DATA_DIR . '/posts');
    define('THEMES_DIR', ROOT_DIR . '/themes');    // 主题目录
    define('PLUGINS_DIR', ROOT_DIR . '/plugins');  // 插件目录
}

// mbstring 兼容（某些 PHP 环境未启用 mbstring）
if (!function_exists('mb_strlen')) {
    function mb_strlen($str, $encoding = null) { return strlen($str); }
    function mb_substr($str, $start, $length = null, $encoding = null) {
        return $length === null ? substr($str, $start) : substr($str, $start, $length);
    }
    function mb_strtolower($str, $encoding = null) { return strtolower($str); }
    function mb_strtoupper($str, $encoding = null) { return strtoupper($str); }
}

/**
 * 统一会话启动（设置安全 Cookie 参数）
 */
function qm_session_start() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    if (!headers_sent()) {
        @session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    @session_start();
}

// 请求内数据缓存（避免同一请求中反复 include 同一数据文件，提升速度、降低内存）
$GLOBALS['_qm_data_cache'] = [];
$GLOBALS['_qm_hooks'] = ['actions' => [], 'filters' => []];

// 邮件发送（SMTP 客户端 + mail() 回退）
require_once __DIR__ . '/mailer.php';

// 评论表情包（内置默认组 + qm_emotion_list 过滤器）
require_once __DIR__ . '/emotions.php';

// ---------- 钩子系统（动作 + 过滤器） ----------
if (empty($GLOBALS['_qm_hooks'])) {
    $GLOBALS['_qm_hooks'] = ['actions' => [], 'filters' => []];
}

/**
 * 注册动作钩子回调
 * @param string   $hook     钩子名
 * @param callable $callback 回调
 * @param int      $priority 优先级（小先执行）
 */
function add_action($hook, $callback, $priority = 10) {
    if (!isset($GLOBALS['_qm_hooks']['actions'][$hook][$priority])) {
        $GLOBALS['_qm_hooks']['actions'][$hook][$priority] = [];
    }
    $GLOBALS['_qm_hooks']['actions'][$hook][$priority][] = $callback;
}

/**
 * 触发动作钩子
 */
function do_action($hook, ...$args) {
    if (empty($GLOBALS['_qm_hooks']['actions'][$hook])) return;
    ksort($GLOBALS['_qm_hooks']['actions'][$hook]);
    foreach ($GLOBALS['_qm_hooks']['actions'][$hook] as $callbacks) {
        foreach ($callbacks as $cb) {
            if (is_callable($cb)) call_user_func_array($cb, $args);
        }
    }
}

/**
 * 注册过滤器钩子回调
 */
function add_filter($hook, $callback, $priority = 10) {
    if (!isset($GLOBALS['_qm_hooks']['filters'][$hook][$priority])) {
        $GLOBALS['_qm_hooks']['filters'][$hook][$priority] = [];
    }
    $GLOBALS['_qm_hooks']['filters'][$hook][$priority][] = $callback;
}

/**
 * 应用过滤器（依次变换值）
 * @param mixed $value 初始值
 * @return mixed 最终值
 */
function apply_filters($hook, $value, ...$args) {
    if (empty($GLOBALS['_qm_hooks']['filters'][$hook])) return $value;
    ksort($GLOBALS['_qm_hooks']['filters'][$hook]);
    foreach ($GLOBALS['_qm_hooks']['filters'][$hook] as $callbacks) {
        foreach ($callbacks as $cb) {
            if (is_callable($cb)) $value = call_user_func_array($cb, array_merge([$value], $args));
        }
    }
    return $value;
}

// ---------- 主题系统 ----------

/**
 * 获取当前启用主题名（白名单安全校验，非法则回退 default）
 */
function get_active_theme() {
    $theme = get_setting('theme', 'default');
    return preg_match('/^[a-zA-Z0-9_\-]{1,64}$/', $theme) ? $theme : 'default';
}

/**
 * 主题目录是否存在
 */
function theme_exists($theme) {
    return is_dir(THEMES_DIR . '/' . $theme);
}

/**
 * 读取主题元信息（themes/<name>/theme.php 返回数组），失败给默认值
 */
function get_theme_info($theme) {
    if (!preg_match('/^[a-zA-Z0-9_\-]{1,64}$/', $theme) || !theme_exists($theme)) {
        return ['name' => '默认主题', 'slug' => 'default', 'version' => '1.0.0', 'description' => '系统内置默认主题', 'author' => 'Qingmo'];
    }
    $file = THEMES_DIR . '/' . $theme . '/theme.php';
    $info = file_exists($file) ? (include $file) : [];
    if (!is_array($info)) $info = [];
    $info['slug'] = $theme;
    $info['name'] = $info['name'] ?? $theme;
    $info['version'] = $info['version'] ?? '1.0.0';
    $info['description'] = $info['description'] ?? '';
    $info['author'] = $info['author'] ?? '';
    $info['has_style'] = file_exists(THEMES_DIR . '/' . $theme . '/style.css');
    return $info;
}

/**
 * 扫描 themes/ 下所有主题
 */
function list_themes() {
    $themes = [];
    if (is_dir(THEMES_DIR)) {
        foreach (glob(THEMES_DIR . '/*', GLOB_ONLYDIR) as $dir) {
            $slug = basename($dir);
            if (!preg_match('/^[a-zA-Z0-9_\-]{1,64}$/', $slug)) continue;
            $themes[] = get_theme_info($slug);
        }
    }
    usort($themes, function ($a, $b) { return strcmp($a['name'], $b['name']); });
    return $themes;
}

/**
 * 前台主题 CSS 地址（theme 自带 style.css 优先，否则回退 assets/style.css）
 */
function theme_css_url() {
    $theme = get_active_theme();
    if ($theme !== 'default' && theme_exists($theme) && file_exists(THEMES_DIR . '/' . $theme . '/style.css')) {
        return 'themes/' . $theme . '/style.css';
    }
    return 'assets/style.css';
}

// ---------- 插件系统 ----------

/**
 * 读取插件元信息（plugins/<slug>/plugin.json）
 */
function get_plugin_info($slug) {
    if (!preg_match('/^[a-zA-Z0-9_\-]{1,64}$/', $slug)) return null;
    $file = PLUGINS_DIR . '/' . $slug . '/plugin.json';
    if (!file_exists($file)) return null;
    $info = json_decode((string)file_get_contents($file), true);
    if (!is_array($info)) $info = [];
    $info['slug'] = $slug;
    $info['name'] = $info['name'] ?? $slug;
    $info['version'] = $info['version'] ?? '1.0.0';
    $info['description'] = $info['description'] ?? '';
    $info['author'] = $info['author'] ?? '';
    $info['has_entry'] = file_exists(PLUGINS_DIR . '/' . $slug . '/plugin.php');
    return $info;
}

/**
 * 扫描 plugins/ 下所有插件
 */
function list_plugins() {
    $plugins = [];
    if (is_dir(PLUGINS_DIR)) {
        foreach (glob(PLUGINS_DIR . '/*', GLOB_ONLYDIR) as $dir) {
            $slug = basename($dir);
            $info = get_plugin_info($slug);
            if ($info === null) continue;
            $plugins[] = $info;
        }
    }
    usort($plugins, function ($a, $b) { return strcmp($a['name'], $b['name']); });
    return $plugins;
}

/**
 * 获取已启用的插件 slug 列表
 */
function get_active_plugins() {
    $list = get_setting('active_plugins', []);
    return is_array($list) ? array_values(array_filter($list, 'is_string')) : [];
}

/**
 * 插件是否启用
 */
function is_plugin_active($slug) {
    return in_array($slug, get_active_plugins(), true);
}

/**
 * 加载全部已启用插件（前台与后台入口统一调用）
 * 插件主文件 plugins/<slug>/plugin.php 会被 include，可在其中注册钩子。
 */
function load_active_plugins() {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;
    foreach (get_active_plugins() as $slug) {
        if (!preg_match('/^[a-zA-Z0-9_\-]{1,64}$/', $slug)) continue;
        $entry = PLUGINS_DIR . '/' . $slug . '/plugin.php';
        if (file_exists($entry)) include_once $entry;
    }
}

// 在入口 require functions 后统一加载插件
load_active_plugins();

/**
 * 重算某篇文章的已审核评论数并写回
 */
function recount_comment_count($post_id) {
    $post = load_post($post_id);
    if (!$post) return;
    $approved = array_filter(load_comments($post_id), function ($c) { return (int)($c['status'] ?? 0) === 1; });
    $post['comment_count'] = count($approved);
    save_post($post_id, $post);
}

/**
 * 站点基础 URL（优先后台设置的 site_url，否则按当前请求自动推断）
 */
function site_base_url() {
    $url = trim((string)get_setting('site_url', ''));
    if ($url !== '') return rtrim($url, '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/**
 * 渲染文章页脚：标题 / 链接 / 作者 / 使用协议
 * 作者名与使用协议可在后台「站点设置」中配置；返回 HTML，插件可用
 * apply_filters('qm_post_footer', ...) 进一步改写。
 *
 * @param array|null $post 文章数据
 */
function render_post_footer($post = null) {
    if (!is_array($post) || (int)get_setting('show_post_footer', 1) !== 1) return '';

    $id = (int)($post['id'] ?? 0);
    if ($id <= 0) return '';

    $title = (string)($post['title'] ?? '');
    $link = site_base_url() . '/index.php?page=post&id=' . $id;

    // 作者：优先后台设置，其次文章作者，最后站点标题
    $author = trim((string)get_setting('author_name', ''));
    if ($author === '') $author = trim((string)($post['author_name'] ?? ''));
    if ($author === '') $author = trim((string)get_setting('site_title', ''));

    // 使用协议：后台设置
    $agreement = trim((string)get_setting('usage_agreement', ''));

    $rows = '';
    if ($title !== '') {
        $rows .= '<p class="pf-title"><span class="pf-label">标题：</span>' . e($title) . '</p>';
    }
    $rows .= '<p class="pf-link"><span class="pf-label">链接：</span><a href="' . e($link) . '">' . e($link) . '</a></p>';
    if ($author !== '') {
        $rows .= '<p class="pf-author"><span class="pf-label">作者：</span>' . e($author) . '</p>';
    }
    if ($agreement !== '') {
        $rows .= '<p class="pf-license"><span class="pf-label">使用协议：</span>' . nl2br(e($agreement)) . '</p>';
    }
    if ($rows === '') return '';

    $html = '<div class="post-footer">' . $rows . '</div>';
    return apply_filters('qm_post_footer', $html, $post);
}

/**
 * 加载数据文件（同一请求内重复加载走内存缓存）
 */
function load_data($file, $default = []) {
    $key = realpath($file) !== false ? realpath($file) : $file;
    if (array_key_exists($key, $GLOBALS['_qm_data_cache'])) {
        return $GLOBALS['_qm_data_cache'][$key];
    }
    $data = file_exists($file) ? (include $file) : $default;
    $GLOBALS['_qm_data_cache'][$key] = $data;
    return $data;
}

/**
 * 保存数据到文件（保存后同步刷新内存缓存）
 */
function save_data($file, $data) {
    $dir = dirname($file);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $content = "<?php\n// 数据文件，由系统自动生成\nreturn " . var_export($data, true) . ";\n";
    file_put_contents($file, $content, LOCK_EX);
    $key = realpath($file) !== false ? realpath($file) : $file;
    $GLOBALS['_qm_data_cache'][$key] = $data;
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
    // 插件扩展点：文章列表排序前的整体调整（例如置顶插件把 sticky 文章提到前面）
    return apply_filters('qm_posts_list', $posts, $filter);
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

/**
 * 加载友链列表
 */
function load_links() {
    return load_data(DATA_DIR . '/links.php', []);
}

/**
 * 保存友链列表
 */
function save_links($links) {
    save_data(DATA_DIR . '/links.php', array_values($links));
}

/**
 * 添加友链
 * @return int 新友链 ID
 */
function add_link($data) {
    $links = load_links();
    $id = count($links) > 0 ? max(array_column($links, 'id')) + 1 : 1;
    $data['id'] = $id;
    $data['created_at'] = time();
    $links[] = $data;
    save_links($links);
    return $id;
}

/**
 * 更新友链
 */
function update_link($id, $data) {
    $links = load_links();
    foreach ($links as &$link) {
        if ((int)$link['id'] === (int)$id) {
            foreach ($data as $k => $v) {
                $link[$k] = $v;
            }
            break;
        }
    }
    unset($link);
    save_links($links);
}

/**
 * 删除友链
 */
function delete_link($id) {
    $links = load_links();
    $links = array_filter($links, function ($l) use ($id) { return (int)$l['id'] !== (int)$id; });
    save_links($links);
}

// ---------- Markdown → HTML（轻量渲染，支持常用语法） ----------

/**
 * URL 安全校验：只允许 http/https/mailto 与站内相对地址，禁止 javascript: 等危险协议
 */
function qm_is_safe_url($url) {
    $url = trim((string)$url);
    if ($url === '') return false;
    if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url)) {
        return in_array(strtolower(parse_url($url, PHP_URL_SCHEME)), ['http', 'https', 'mailto'], true);
    }
    // 无协议（相对路径 / 站内地址）允许，但开头不能是反斜杠/控制字符
    return !preg_match('#^(\\\\|[^/])#', $url) || strpos($url, '/') === 0;
}

/**
 * 行内 Markdown 处理（内部统一转义，避免 XSS 与二次转义）
 */
function md_inline($text) {
    $text = htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    // 行内代码（内容已被转义）
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
    // 图片 ![alt](url)（仅允许安全协议；尺寸交由 CSS 控制：评论内缩略、正文限宽）
    $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/', function ($m) {
        return qm_is_safe_url($m[2])
            ? '<img class="qm-img" src="' . $m[2] . '" alt="' . $m[1] . '" loading="lazy">'
            : $m[0];
    }, $text);
    // 链接 [text](url)（仅允许安全协议）
    $text = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/', function ($m) {
        return qm_is_safe_url($m[2])
            ? '<a href="' . $m[2] . '" target="_blank" rel="noopener nofollow">' . $m[1] . '</a>'
            : $m[0];
    }, $text);
    // 粗体 **text** / 下划线加粗 __text__
    $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/__([^_]+)__/', '<strong>$1</strong>', $text);
    // 斜体 *text*（避开粗体边界）
    $text = preg_replace('/(^|[^*])\*([^*\n]+)\*(?!\*)/', '$1<em>$2</em>', $text);
    // 删除线 ~~text~~
    $text = preg_replace('/~~([^~]+)~~/', '<del>$1</del>', $text);
    return $text;
}

/**
 * 极简 Markdown → HTML
 * 支持：# 标题 / > 引用 / - 无序 / 1. 有序 / ``` 代码块 / 段落 / 行内样式
 * 服务端保存文章时使用；正文最终仍以 HTML 存储，前台渲染逻辑不变。
 */
function md_to_html($text) {
    $text = (string)$text;
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = explode("\n", $text);
    $count = count($lines);

    $html = '';
    $listType = null; // 'ul' | 'ol' | null
    $para = [];       // 段落缓冲
    $i = 0;

    $closeList = function () use (&$html, &$listType) {
        if ($listType) { $html .= "</{$listType}>\n"; $listType = null; }
    };
    $flushPara = function () use (&$html, &$para) {
        if ($para) {
            // 段内软换行：每行单独行内渲染后用 <br> 连接（类似 GFM）
            $joined = implode('<br>', array_map('md_inline', $para));
            $html .= '<p>' . $joined . "</p>\n";
            $para = [];
        }
    };

    while ($i < $count) {
        $raw = rtrim($lines[$i]);

        // 代码围栏 ```lang
        if (preg_match('/^```\s*([\w+-]*)\s*$/', $raw, $m)) {
            $lang = $m[1];
            $buf = [];
            $i++;
            while ($i < $count && !preg_match('/^```\s*$/', rtrim($lines[$i]))) {
                $buf[] = $lines[$i];
                $i++;
            }
            $i++; // 跳过闭合围栏
            $code = htmlspecialchars(implode("\n", $buf), ENT_QUOTES, 'UTF-8');
            $langAttr = $lang !== '' ? ' class="language-' . e($lang) . '"' : '';
            $html .= "<pre><code{$langAttr}>" . $code . "</code></pre>\n";
            continue;
        }

        if ($raw === '') {
            $closeList(); $flushPara();
            $i++;
            continue;
        }

        // 标题
        if (preg_match('/^(#{1,6})\s+(.+)$/', $raw, $m)) {
            $closeList(); $flushPara();
            $level = strlen($m[1]);
            $html .= '<h' . $level . '>' . md_inline($m[2]) . "</h{$level}>\n";
            $i++;
            continue;
        }
        // 分割线
        if (preg_match('/^(\s*[-*_]\s*){3,}$/', $raw)) {
            $closeList(); $flushPara();
            $html .= "<hr>\n";
            $i++;
            continue;
        }

        // 引用：收集连续 > 行
        if (preg_match('/^>\s?(.*)$/', $raw)) {
            $closeList(); $flushPara();
            $qbuf = [];
            while ($i < $count && preg_match('/^>\s?(.*)$/', rtrim($lines[$i]), $qm)) {
                $qbuf[] = md_inline($qm[1]);
                $i++;
            }
            $html .= '<blockquote><p>' . implode("<br>\n", $qbuf) . "</p></blockquote>\n";
            continue;
        }

        // 列表（连续同类条目）
        $isUl = preg_match('/^\s*[-*+]\s+(.*)$/', $raw, $lm);
        $isOl = !$isUl && preg_match('/^\s*\d+[.)]\s+(.*)$/', $raw, $lm);
        if ($isUl || $isOl) {
            $flushPara();
            $type = $isUl ? 'ul' : 'ol';
            if ($listType !== $type) { $closeList(); $html .= "<{$type}>\n"; $listType = $type; }
            while ($i < $count) {
                $cur = rtrim($lines[$i]);
                $mm = [];
                if ($type === 'ul' && preg_match('/^\s*[-*+]\s+(.*)$/', $cur, $mm)) {
                    $html .= '<li>' . md_inline($mm[1]) . "</li>\n";
                    $i++;
                    continue;
                }
                if ($type === 'ol' && preg_match('/^\s*\d+[.)]\s+(.*)$/', $cur, $mm)) {
                    $html .= '<li>' . md_inline($mm[1]) . "</li>\n";
                    $i++;
                    continue;
                }
                break;
            }
            continue;
        }

        // 普通段落（可多行合并）
        $closeList();
        $para[] = $raw;
        $i++;
    }
    $closeList();
    $flushPara();

    return $html;
}

// ---------- 评论树与通知 ----------

/**
 * 将某篇文章的已审核评论整理成树（parent_id 嵌套）
 * @return array{top: array, by_id: array}
 */
function build_comment_tree($post_id) {
    $flat = array_values(array_filter(load_comments($post_id), function ($c) {
        return (int)($c['status'] ?? 0) === 1;
    }));
    // 旧的先显示（时间正序），回复紧随其后
    usort($flat, function ($a, $b) {
        if (($a['created_at'] ?? 0) === ($b['created_at'] ?? 0)) {
            return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
        }
        return ($a['created_at'] ?? 0) <=> ($b['created_at'] ?? 0);
    });

    $byId = [];
    foreach ($flat as $c) {
        $c['children'] = [];
        $byId[(int)$c['id']] = $c;
    }
    $top = [];
    foreach ($byId as $id => &$c) {
        $pid = (int)($c['parent_id'] ?? 0);
        if ($pid && isset($byId[$pid]) && $pid !== $id) {
            $byId[$pid]['children'][] = &$c;
        } else {
            $top[] = &$c;
        }
    }
    unset($c);
    return ['top' => $top, 'by_id' => $byId];
}

/**
 * 递归删除评论及其全部子回复（返回受影响 id 数量）
 */
function delete_comment_tree($comments, $id, &$deleted = []) {
    $deleted[] = (int)$id;
    foreach ($comments as $c) {
        if ((int)($c['parent_id'] ?? 0) === (int)$id) {
            delete_comment_tree($comments, (int)$c['id'], $deleted);
        }
    }
    return $deleted;
}

/**
 * 新评论管理员邮件通知（SMTP / PHP mail，后台可开关）
 */
function qm_notify_new_comment($comment, $post) {
    if ((int)get_setting('comment_email_on', 0) !== 1) return false;
    $to = trim((string)get_setting('notify_email', ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $site = get_setting('site_title', '我的博客');
    $subject = '[' . $site . '] 新评论：' . ($comment['author_name'] ?? '');
    $statusTxt = (int)($comment['status'] ?? 0) === 1 ? '已发布' : '待审核';
    $postTitle = $post['title'] ?? '未知文章';
    $postUrl = site_base_url() . '/index.php?page=post&id=' . (int)($post['id'] ?? 0);
    $adminUrl = site_base_url() . '/admin/';
    $body  = "站点：{$site}\n文章：{$postTitle}\n评论者：{$comment['author_name']} ({$comment['author_email']})\n状态：{$statusTxt}\n\n评论内容：\n" . ($comment['content'] ?? '') . "\n\n查看：{$postUrl}\n管理后台：{$adminUrl}";
    $html  = '<p><strong>' . qm_text_to_html($site) . '</strong> 收到一条新评论：</p>'
        . '<p><strong>文章：</strong><a href="' . e($postUrl) . '">' . qm_text_to_html($postTitle) . '</a></p>'
        . '<p><strong>评论者：</strong>' . qm_text_to_html($comment['author_name'] . ' (' . $comment['author_email'] . ')') . '</p>'
        . '<p><strong>状态：</strong>' . e($statusTxt) . '</p>'
        . '<div style="border:1px solid #ddd;background:#fafafa;padding:10px;">' . qm_text_to_html($comment['content'] ?? '') . '</div>'
        . '<p><a href="' . e($adminUrl) . '">进入管理后台</a></p>';
    return qm_send_mail($to, $subject, $body, $html);
}

/**
 * 收集某篇文章出现过的评论者（昵称小写 → 邮箱），用于 @ 提及
 */
function qm_collect_commenters($post_id) {
    $commenters = [];
    foreach (load_comments($post_id) as $c) {
        $name = trim((string)($c['author_name'] ?? ''));
        $email = trim((string)($c['author_email'] ?? ''));
        if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
        $key = mb_strtolower($name);
        if (!isset($commenters[$key])) $commenters[$key] = ['name' => $name, 'email' => $email];
    }
    return $commenters;
}

/**
 * 评论 @ 提及提醒：解析评论内容里的 @昵称，给对应评论者发邮件
 */
function qm_notify_mentions($comment, $post, $commenters = null) {
    if ((int)get_setting('at_notify_on', 1) !== 1) return;
    $commenters = $commenters !== null ? $commenters : qm_collect_commenters((int)($post['id'] ?? 0));
    $content = (string)($comment['content'] ?? '');
    $poster = trim((string)($comment['author_name'] ?? ''));
    $sent = [];

    // 匹配 @后 1-24 位的中英文、数字、下划线（含中文昵称）
    if (!preg_match_all('/@([\p{L}\p{N}_\-]{1,24})/u', $content, $m)) return;
    foreach (array_unique($m[1]) as $token) {
        $key = mb_strtolower(trim($token, '-_ '));
        if ($key === '') continue;
        if (!isset($commenters[$key])) continue;
        $target = $commenters[$key];
        if (mb_strtolower($poster) === $key) continue;            // @ 自己不发
        if (isset($sent[$target['email']])) continue;             // 同一人只发一封
        $sent[$target['email']] = true;

        $site = get_setting('site_title', '我的博客');
        $subject = '@' . $comment['author_name'] . ' 在《' . ($post['title'] ?? '') . '》提到了你';
        $postUrl = site_base_url() . '/index.php?page=post&id=' . (int)($post['id'] ?? 0);
        $body  = "你好，{$target['name']}：\n\n"
            . $comment['author_name'] . ' 在文章《' . ($post['title'] ?? '') . '》的评论中提到了你。\n\n'
            . "Ta 的评论：\n" . $content . "\n\n查看：{$postUrl}";
        $html  = '<p>你好，' . qm_text_to_html($target['name']) . '：</p>'
            . '<p>' . qm_text_to_html($comment['author_name']) . ' 在文章《'
            . '<a href="' . e($postUrl) . '">' . qm_text_to_html($post['title'] ?? '') . '</a>》的评论中提到了你：</p>'
            . '<div style="border:1px solid #ddd;background:#fafafa;padding:10px;">' . qm_text_to_html($content) . '</div>';
        qm_send_mail($target['email'], $subject, $body, $html);
    }
}

/**
 * “有人回复了你的评论”提醒（点“回复”按钮产生 parent_id 时触发）
 */
function qm_notify_reply($comment, $post) {
    $parentId = (int)($comment['parent_id'] ?? 0);
    if ($parentId <= 0) return;
    $poster = trim((string)($comment['author_name'] ?? ''));
    $target = null;
    foreach (load_comments((int)($post['id'] ?? 0)) as $c) {
        if ((int)($c['id'] ?? 0) === $parentId) {
            $target = $c;
            break;
        }
    }
    if (!$target || !is_array($target)) return;
    if ((int)get_setting('at_notify_on', 1) !== 1) return; // 与 @提及共用同一个开关
    if (mb_strtolower((string)($target['author_name'] ?? '')) === mb_strtolower($poster)) return; // 回复自己
    $to = trim((string)($target['author_email'] ?? ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return;

    $replyContent = trim((string)($comment['content'] ?? ''));
    $site = get_setting('site_title', '我的博客');
    $postTitle = (string)($post['title'] ?? '');
    $postUrl = site_base_url() . '/index.php?page=post&id=' . (int)($post['id'] ?? 0);
    $subject = '[' . $site . '] ' . $poster . ' 回复了你在《' . $postTitle . '》的评论';
    $body  = "你好，{$target['author_name']}：\n\n"
        . $poster . " 回复了你在《{$postTitle}》的评论。\n\nTa 说：\n" . $replyContent
        . "\n\n查看：{$postUrl}";
    $html  = '<p>你好，' . qm_text_to_html($target['author_name']) . '：</p>'
        . '<p>' . qm_text_to_html($poster) . ' 回复了你在文章《'
        . '<a href="' . e($postUrl) . '">' . qm_text_to_html($postTitle) . '</a>》中的评论：</p>'
        . '<div style="border:1px solid #ddd;background:#fafafa;padding:10px;">' . qm_text_to_html($replyContent) . '</div>';
    qm_send_mail($to, $subject, $body, $html);
}

/**
 * 评论者头像地址（Cravatar / Gravatar 镜像）
 * 仅使用邮箱 md5，不暴露邮箱本身
 */
function qm_avatar_url($email = '', $size = 40) {
    $hash = md5(strtolower(trim((string)$email)));
    return 'https://cravatar.com/avatar/' . $hash . '?s=' . (int)$size . '&d=mp';
}

/**
 * 获取友链列表（应用排序：name 按名称 / random 每次随机）
 */
function get_links_ordered() {
    $links = load_links();
    if ((string)get_setting('links_order', 'name') === 'random') {
        shuffle($links);
        return $links;
    }
    usort($links, function ($a, $b) {
        return strcmp(mb_strtolower((string)($a['name'] ?? '')), mb_strtolower((string)($b['name'] ?? '')));
    });
    return $links;
}

/**
 * 上次邮件发送失败的具体原因（后台测试邮件展示用）
 */
function qm_mail_last_error() {
    return isset($GLOBALS['qm_mail_error']) ? (string)$GLOBALS['qm_mail_error'] : '';
}
