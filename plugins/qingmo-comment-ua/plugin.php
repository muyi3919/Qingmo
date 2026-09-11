<?php
/**
 * 插件：评论设备信息（UA）
 * 依赖核心钩子（v2.2+）：
 *   - qm_comment_data （过滤器）评论写入前：记录并解析来访者 User-Agent
 *   - qm_comment_meta （动作）   每条评论展示「系统 · 浏览器 版本」徽标
 * 设置项保存在 data/plugin_comment_ua.php。
 *
 * 只做「文本匹配」级别的识别，不追求 100% 精确：未知设备会显示「其他」。
 */
if (!defined('QM_BOOT')) { exit('Access denied'); }

/* ---------- 配置 ---------- */
function qm_ua_data() {
    return load_data(DATA_DIR . '/plugin_comment_ua.php', ['config' => []]);
}

function qm_ua_save($data) {
    save_data(DATA_DIR . '/plugin_comment_ua.php', $data);
}

function qm_ua_config($key, $default = null) {
    $d = qm_ua_data();
    return $d['config'][$key] ?? $default;
}

/* ---------- UA 解析 ---------- */
/**
 * 解析 User-Agent → ['os'=>, 'browser'=>, 'version'=>, 'device'=>, 'raw'=>]
 */
function qm_ua_parse($ua) {
    $ua = trim((string)$ua);
    $res = ['os' => '未知系统', 'browser' => '未知浏览器', 'version' => '', 'device' => '桌面', 'raw' => $ua];
    if ($ua === '') return $res;

    /* 系统 / 平台 */
    if (preg_match('/Windows NT 10\.0/i', $ua))      $res['os'] = 'Windows 10/11';
    elseif (preg_match('/Windows NT 6\.3/i', $ua))   $res['os'] = 'Windows 8.1';
    elseif (preg_match('/Windows NT 6\.1/i', $ua))   $res['os'] = 'Windows 7';
    elseif (preg_match('/Windows NT 6\.0/i', $ua))   $res['os'] = 'Windows Vista';
    elseif (preg_match('/Windows NT 5\.1/i', $ua))   $res['os'] = 'Windows XP';
    elseif (preg_match('/Windows/i', $ua))           $res['os'] = 'Windows';
    elseif (preg_match('/iPhone|iPad|iPod/i', $ua))  $res['os'] = 'iOS';
    elseif (preg_match('/Android[ \/]([\d.]+)/i', $ua, $m)) {
        $res['os'] = 'Android ' . rtrim($m[1], '.');
    } elseif (preg_match('/Android/i', $ua))         $res['os'] = 'Android';
    elseif (preg_match('/HarmonyOS/i', $ua))         $res['os'] = 'HarmonyOS';
    elseif (preg_match('/Mac OS X[ \/]([\d_\.]+)/i', $ua, $m)) {
        $res['os'] = 'macOS ' . str_replace('_', '.', rtrim($m[1], '.'));
    } elseif (preg_match('/Macintosh|Mac OS X/i', $ua)) $res['os'] = 'macOS';
    elseif (preg_match('/CrOS/i', $ua))              $res['os'] = 'ChromeOS';
    elseif (preg_match('/Ubuntu/i', $ua))            $res['os'] = 'Ubuntu';
    elseif (preg_match('/Linux/i', $ua))             $res['os'] = 'Linux';
    elseif (preg_match('/FreeBSD/i', $ua))           $res['os'] = 'FreeBSD';

    /* iOS 版本号可以从 CPU OS 里取 */
    if ($res['os'] === 'iOS' && preg_match('/OS (\d+[_\d]*) like Mac OS X/i', $ua, $m)) {
        $res['os'] = 'iOS ' . str_replace('_', '.', rtrim($m[1], '.'));
    }

    /* 移动 / 平板 / 桌面 */
    if (preg_match('/iPad|Tablet/i', $ua))            $res['device'] = '平板';
    elseif (preg_match('/Mobile|Android|iPhone|iPod/i', $ua)) $res['device'] = '手机';
    elseif (preg_match('/bot|spider|crawler|slurp|bingpreview|curl|wget|python|java\//i', $ua)) {
        $res['device'] = '机器人';
    }

    /* 浏览器（顺序敏感：先特殊内核，再到通用） */
    $uaTests = [
        ['MicroMessenger/([\d.]+)',  '微信内置浏览器'],
        ['QQBrowser/([\d.]+)',       'QQ 浏览器'],
        ['MQQBrowser/([\d.]+)',      'QQ 浏览器'],
        ['UCBrowser/([\d.]+)',       'UC 浏览器'],
        ['Quark/([\d.]+)',           '夸克浏览器'],
        ['HuaweiBrowser/([\d.]+)',   '华为浏览器'],
        ['HeyTapBrowser/([\d.]+)',   'OPPO 浏览器'],
        ['MiuiBrowser/([\d.]+)',     '小米浏览器'],
        ['VivoBrowser/([\d.]+)',     'vivo 浏览器'],
        ['SamsungBrowser/([\d.]+)',  '三星浏览器'],
        ['EdgA?/([\d.]+)',           'Edge'],
        ['OPR/([\d.]+)',             'Opera'],
        ['Firefox/([\d.]+)',         'Firefox'],
        ['FxiOS/([\d.]+)',           'Firefox'],
        ['CriOS/([\d.]+)',           'Chrome'],
        ['Chrome/([\d.]+)',          'Chrome'],
        ['Version/([\d.]+).*Safari', 'Safari'],
        ['MSIE ([\d.]+)',            'IE'],
        ['Trident/.*rv:([\d.]+)',    'IE'],
        ['curl/([\d.]+)',            'curl'],
        ['python-requests/([\d.]+)', 'Python'],
    ];
    foreach ($uaTests as $t) {
        if (preg_match('#' . $t[0] . '#i', $ua, $m)) {
            $res['browser'] = $t[1];
            $res['version'] = rtrim($m[1], '.');
            break;
        }
    }
    // 版本只保留主版本号，避免太长
    if ($res['version'] !== '' && preg_match('/^(\d+)/', $res['version'], $m)) {
        $res['version'] = $m[1];
    }
    return $res;
}

/* ---------- 1. 提交评论时记录并解析 UA ---------- */
add_filter('qm_comment_data', 'qm_ua_on_submit', 10, 2);
function qm_ua_on_submit($comment, $post) {
    if ($comment === false) return $comment;
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua === '') return $comment;
    $p = qm_ua_parse($ua);
    $comment['ua_os']      = $p['os'];
    $comment['ua_browser'] = $p['browser'];
    $comment['ua_version'] = $p['version'];
    $comment['ua_device']  = $p['device'];
    if ((int)qm_ua_config('store_raw', 1) === 1) {
        $comment['ua_raw'] = mb_substr($ua, 0, 300);
    }
    return $comment;
}

/* ---------- 1.5 Font Awesome（可选，CDN 加载） ---------- */
add_action('qm_head', 'qm_ua_head');
function qm_ua_head($ctx = null) {
    if ((int)qm_ua_config('fa_cdn', 1) !== 1) return;
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css" crossorigin="anonymous">' . "\n";
}

/**
 * 系统 → Font Awesome 图标类
 */
function qm_ua_os_icon($os) {
    $os = (string)$os;
    if (stripos($os, 'Windows') === 0) return 'fa-brands fa-windows';
    if (stripos($os, 'macOS') === 0 || stripos($os, 'iOS') === 0) return 'fa-brands fa-apple';
    if (stripos($os, 'Android') === 0) return 'fa-brands fa-android';
    if (stripos($os, 'Ubuntu') === 0) return 'fa-brands fa-ubuntu';
    if (stripos($os, 'Linux') === 0 || stripos($os, 'FreeBSD') === 0) return 'fa-brands fa-linux';
    if (stripos($os, 'ChromeOS') === 0) return 'fa-brands fa-chrome';
    if (stripos($os, 'HarmonyOS') === 0) return 'fa-solid fa-mobile-screen-button';
    return 'fa-solid fa-desktop';
}

/**
 * 浏览器 → Font Awesome 图标类
 */
function qm_ua_browser_icon($browser) {
    $b = (string)$browser;
    $map = [
        'Chrome'        => 'fa-brands fa-chrome',
        'Edge'          => 'fa-brands fa-edge',
        'Firefox'       => 'fa-brands fa-firefox-browser',
        'Safari'        => 'fa-brands fa-safari',
        'Opera'         => 'fa-brands fa-opera',
        'IE'            => 'fa-brands fa-internet-explorer',
        '微信内置浏览器' => 'fa-brands fa-weixin',
        'QQ 浏览器'      => 'fa-brands fa-qq',
        '三星浏览器'     => 'fa-solid fa-mobile-screen-button',
        'UC 浏览器'      => 'fa-solid fa-mobile-screen-button',
        '夸克浏览器'     => 'fa-solid fa-mobile-screen-button',
        '华为浏览器'     => 'fa-solid fa-mobile-screen-button',
        'OPPO 浏览器'    => 'fa-solid fa-mobile-screen-button',
        '小米浏览器'     => 'fa-solid fa-mobile-screen-button',
        'vivo 浏览器'    => 'fa-solid fa-mobile-screen-button',
        'curl'          => 'fa-solid fa-terminal',
        'Python'        => 'fa-brands fa-python',
    ];
    if (isset($map[$b])) return $map[$b];
    if (stripos($b, '浏览器') !== false) return 'fa-solid fa-globe';
    return 'fa-solid fa-globe';
}

/* ---------- 2. 渲染设备徽标 ---------- */
add_action('qm_comment_meta', 'qm_ua_render');
function qm_ua_render($comment) {
    if (!is_array($comment)) return;
    $os      = trim((string)($comment['ua_os'] ?? ''));
    $browser = trim((string)($comment['ua_browser'] ?? ''));
    $ver     = trim((string)($comment['ua_version'] ?? ''));
    $device  = trim((string)($comment['ua_device'] ?? ''));
    if ($os === '' && $browser === '') return;

    $useFa = ((int)qm_ua_config('fa_cdn', 1) === 1) && ((int)qm_ua_config('fa_icons', 1) === 1);

    $label = '';
    if ($useFa) {
        $icons = '';
        if ($os !== '') {
            $icons .= '<i class="' . qm_ua_os_icon($os) . '" aria-hidden="true"></i>';
        }
        if ($browser !== '') {
            $icons .= '<i class="' . qm_ua_browser_icon($browser) . '" aria-hidden="true" style="margin-left:4px;"></i>';
        }
        $label = $icons . ' ' . e(($os !== '' ? $os : '未知系统')
            . ($browser !== '' ? ' · ' . $browser . ($ver !== '' ? ' ' . $ver : '') : ''));
    } else {
        $icon = '💻';
        if ($device === '手机') $icon = '📱';
        elseif ($device === '平板') $icon = '📲';
        elseif ($device === '机器人') $icon = '🤖';
        $label = $icon . ' ' . e(($os !== '' ? $os : '未知系统')
            . ($browser !== '' ? ' · ' . $browser . ($ver !== '' ? ' ' . $ver : '') : ''));
    }

    $raw = (string)($comment['ua_raw'] ?? '');
    $title = ($raw !== '' && (int)qm_ua_config('show_raw', 1) === 1)
        ? ' title="' . e($raw) . '"'
        : '';

    echo '<span class="qm-comment-ua"' . $title . ' style="display:inline-block;margin:2px 0;font-size:12px;'
        . 'color:#5b6b7c;background:rgba(120,200,160,.16);border-radius:4px;padding:0 6px;'
        . ($title !== '' ? 'cursor:help;' : '') . '">' . $label . '</span>';
}
