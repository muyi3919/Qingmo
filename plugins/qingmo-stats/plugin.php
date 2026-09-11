<?php
/**
 * 插件：访问统计（类 WP Statistics 轻量版）
 * - 前台 qm_footer 钩子采集：PV、UV（IP+UA 哈希去重）、来源、系统/浏览器、热门文章、在线人数
 * - 后台设置页同时是「统计报表」：今日/昨日/近 30 天、趋势图、来源 Top、热门文章、系统与浏览器占比、最近访问
 * - 可选侧栏小部件（qm_sidebar）：总访问、今日、在线、热门文章
 * 隐私：不保存原始 IP，只保存不可逆哈希；可按设置排除管理员访问。
 */
if (!defined('QM_BOOT')) { exit('Access denied'); }

/* ================= 配置 ================= */
function qm_stats_data() {
    return load_data(DATA_DIR . '/plugin_qingmo_stats.php', [
        'config' => [], 'pv' => 0, 'since' => 0, 'daily' => [],
        'uv_seen' => [], 'refs' => [], 'posts' => [], 'os' => [], 'browsers' => [],
        'recent' => [], 'online' => [], 'salt' => '',
    ]);
}

function qm_stats_save($data) {
    return save_data(DATA_DIR . '/plugin_qingmo_stats.php', $data);
}

function qm_stats_cfg($key, $default = null) {
    $d = qm_stats_data();
    return $d['config'][$key] ?? $default;
}

/* ================= 采集 ================= */
add_action('qm_footer', 'qm_stats_track');
function qm_stats_track() {
    // 只统计前台 GET 页面
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;
    if ((int)qm_stats_cfg('enabled', 1) !== 1) return;

    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    // 机器人/接口请求不计入
    if ($ua === '' || preg_match('/bot|spider|crawler|slurp|bingpreview|curl|wget|python-requests|headless|powershell|httpclient|go-http/i', $ua)) return;

    // 排除管理员（可选）
    if (!(int)qm_stats_cfg('count_admin', 0) && function_exists('is_logged_in') && is_logged_in()) return;

    $page = (string)($_GET['page'] ?? 'home');
    if (!in_array($page, ['home', 'archive', 'post', 'category', 'tag', 'date', 'links', 'about', 'guestbook', 'search'], true)) return;

    $data = qm_stats_data();
    if ($data['salt'] === '') {
        $data['salt'] = bin2hex(random_bytes(8));
    }
    $salt = (string)$data['salt'];

    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $visitor = substr(hash('sha256', $salt . '|' . $ip . '|' . $ua), 0, 16);
    $day = date('Y-m-d');
    $now = time();

    // ---- PV ----
    if (empty($data['since'])) $data['since'] = $now;
    $data['pv'] = (int)$data['pv'] + 1;
    if (!isset($data['daily'][$day])) $data['daily'][$day] = ['pv' => 0, 'uv' => 0];
    $data['daily'][$day]['pv']++;

    // ---- UV（当天同一访客只算一次）----
    if (!isset($data['uv_seen'][$day])) $data['uv_seen'][$day] = [];
    if (!isset($data['uv_seen'][$day][$visitor])) {
        $data['uv_seen'][$day][$visitor] = 1;
        $data['daily'][$day]['uv']++;
    }

    // ---- 来源 ----
    $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $refKey = '直接访问';
    if ($ref !== '') {
        $host = parse_url($ref, PHP_URL_HOST);
        $selfHost = parse_url(site_base_url(), PHP_URL_HOST);
        if ($host && $selfHost && strcasecmp($host, $selfHost) !== 0) {
            $refKey = $host;
        } elseif ($host) {
            $refKey = '站内跳转';
        }
    }
    $data['refs'][$refKey] = (int)($data['refs'][$refKey] ?? 0) + 1;

    // ---- 系统 / 浏览器 ----
    $osName = '未知系统';
    $brName = '未知浏览器';
    if (function_exists('qm_ua_parse')) {
        $p = qm_ua_parse($ua);
        $osName = $p['os'] !== '' ? $p['os'] : $osName;
        $brName = $p['browser'] !== '' ? $p['browser'] : $brName;
    }
    $data['os'][$osName] = (int)($data['os'][$osName] ?? 0) + 1;
    $data['browsers'][$brName] = (int)($data['browsers'][$brName] ?? 0) + 1;

    // ---- 热门文章 ----
    $pid = (int)($_GET['id'] ?? 0);
    if ($page === 'post' && $pid > 0) {
        $data['posts'][(string)$pid] = (int)($data['posts'][(string)$pid] ?? 0) + 1;
    }

    // ---- 在线（近 5 分钟）----
    $data['online'][$visitor] = $now;
    foreach ($data['online'] as $k => $t) {
        if ($now - (int)$t > 300) unset($data['online'][$k]);
    }

    // ---- 最近访问 ----
    $path = 'index.php?page=' . $page;
    if ($page === 'post' && $pid > 0) $path .= '&id=' . $pid;
    array_unshift($data['recent'], [
        't' => $now, 'path' => $path, 'os' => $osName, 'browser' => $brName,
        'ref' => $refKey, 'visitor' => $visitor,
    ]);
    if (count($data['recent']) > 60) $data['recent'] = array_slice($data['recent'], 0, 60);

    // ---- 清理过期数据 ----
    $keepDays = max(7, (int)qm_stats_cfg('keep_days', 120));
    $limit = date('Y-m-d', $now - $keepDays * 86400);
    foreach ($data['daily'] as $d => $v) {
        if ($d < $limit) unset($data['daily'][$d]);
    }
    foreach ($data['uv_seen'] as $d => $v) {
        if ($d < $limit) unset($data['uv_seen'][$d]);
    }

    qm_stats_save($data);
}

/* ================= 统计查询辅助 ================= */
function qm_stats_today() {
    $d = qm_stats_data();
    $day = date('Y-m-d');
    return $d['daily'][$day] ?? ['pv' => 0, 'uv' => 0];
}

function qm_stats_yesterday() {
    $d = qm_stats_data();
    $day = date('Y-m-d', time() - 86400);
    return $d['daily'][$day] ?? ['pv' => 0, 'uv' => 0];
}

function qm_stats_online_count() {
    $d = qm_stats_data();
    $now = time();
    $n = 0;
    foreach (($d['online'] ?? []) as $t) {
        if ($now - (int)$t <= 300) $n++;
    }
    return $n;
}

/**
 * 近 N 天序列（补齐缺失日期）
 */
function qm_stats_days($days = 30) {
    $d = qm_stats_data();
    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $day = date('Y-m-d', time() - $i * 86400);
        $out[$day] = $d['daily'][$day] ?? ['pv' => 0, 'uv' => 0];
    }
    return $out;
}

function qm_stats_range_total($days = 30) {
    $sum = ['pv' => 0, 'uv' => 0];
    foreach (qm_stats_days($days) as $v) {
        $sum['pv'] += (int)$v['pv'];
        $sum['uv'] += (int)$v['uv'];
    }
    return $sum;
}

function qm_stats_top($map, $limit = 10) {
    $map = is_array($map) ? $map : [];
    arsort($map);
    return array_slice($map, 0, $limit, true);
}

/**
 * 热门文章（ID → 标题）
 */
function qm_stats_top_posts($limit = 10) {
    $d = qm_stats_data();
    $map = qm_stats_top($d['posts'] ?? [], $limit);
    $out = [];
    foreach ($map as $pid => $hits) {
        $p = load_post((int)$pid);
        if (!$p) continue;
        $out[] = ['id' => (int)$pid, 'title' => $p['title'], 'hits' => (int)$hits];
    }
    return $out;
}

/* ================= 侧栏小部件 ================= */
add_action('qm_sidebar', 'qm_stats_widget');
function qm_stats_widget() {
    if ((int)qm_stats_cfg('sidebar', 1) !== 1) return;
    if ((int)qm_stats_cfg('enabled', 1) !== 1) return;
    $d = qm_stats_data();
    $today = qm_stats_today();
    $top = qm_stats_top_posts(5);
    ?>
    <div class="box">
        <h3>站点统计</h3>
        <ul style="font-size:13px;line-height:1.9;">
            <li>总访问：<?php echo (int)$d['pv']; ?> 次</li>
            <li>今日：<?php echo (int)$today['pv']; ?> 次 · <?php echo (int)$today['uv']; ?> 人</li>
            <li>当前在线：<?php echo qm_stats_online_count(); ?> 人</li>
        </ul>
        <?php if ($top): ?>
        <h3 style="margin-top:10px;">热门文章</h3>
        <ul style="font-size:13px;line-height:1.9;">
            <?php foreach ($top as $t): ?>
            <li><a href="index.php?page=post&id=<?php echo $t['id']; ?>"><?php echo e($t['title']); ?></a>
                <span style="color:#aaa;font-size:12px;">(<?php echo $t['hits']; ?>)</span></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <?php
}
