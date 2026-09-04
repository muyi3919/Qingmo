<?php
/**
 * 插件：评论归属地（轻墨版 Easy Location）
 * 依赖核心钩子（v2.2+）：
 *   - qm_comment_data   （过滤器）评论写入前：补记录 IP，在线查询归属地
 *   - qm_comment_meta   （动作）   每条评论展示归属地徽标
 * 设置项保存在 data/plugin_comment_geo.php。
 */
if (!defined('QM_BOOT')) { exit('Access denied'); }

/* ---------- 数据与配置 ---------- */
function qm_geo_data() {
    return load_data(DATA_DIR . '/plugin_comment_geo.php', ['config' => [], 'ips' => []]);
}

function qm_geo_save($data) {
    save_data(DATA_DIR . '/plugin_comment_geo.php', $data);
}

function qm_geo_config($key, $default = null) {
    $d = qm_geo_data();
    return $d['config'][$key] ?? $default;
}

/* ---------- 在线查询（ip-api.com） ---------- */
function qm_geo_lookup($ip) {
    if (!filter_var($ip, FILTER_VALIDATE_IP) || $ip === '127.0.0.1' || $ip === '::1') {
        return null; // 本地地址没有归属地
    }
    $url = 'http://ip-api.com/json/' . rawurlencode($ip)
        . '?fields=status,country,countryCode,regionName,city,isp&lang=zh-CN';
    $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) return null;
    $json = json_decode($body, true);
    if (!is_array($json) || ($json['status'] ?? '') !== 'success') return null;
    return [
        'country' => (string)($json['country'] ?? ''),
        'cc'      => strtoupper((string)($json['countryCode'] ?? '')),
        'region'  => (string)($json['regionName'] ?? ''),
        'city'    => (string)($json['city'] ?? ''),
        'isp'     => (string)($json['isp'] ?? ''),
    ];
}

/**
 * 国家码 → 国旗 emoji
 */
function qm_geo_flag($cc) {
    if ($cc === '' || strlen($cc) !== 2 || !function_exists('mb_chr')) return '';
    $flag = '';
    foreach (str_split(strtoupper($cc)) as $ch) {
        $flag .= mb_chr(ord($ch) + 127397, 'UTF-8');
    }
    return $flag;
}

/* ---------- 1. 提交评论时：记 IP + 尽力查询归属地 ---------- */
add_filter('qm_comment_data', 'qm_geo_on_submit', 10, 2);
function qm_geo_on_submit($comment, $post) {
    if ($comment === false) return $comment;
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $comment['ip'] = $ip;

    $cacheDays = max(0, (int)qm_geo_config('cache_days', 30));
    $data = qm_geo_data();
    $ips = $data['ips'];
    $geo = null;

    if ($ip !== '' && isset($ips[$ip])) {
        if ($cacheDays <= 0 || time() - (int)($ips[$ip]['t'] ?? 0) < $cacheDays * 86400) {
            $geo = $ips[$ip];
        } else {
            unset($ips[$ip]); // 缓存过期
        }
    }

    if ($geo === null && (int)qm_geo_config('online', 1) === 1 && $ip !== '') {
        $found = qm_geo_lookup($ip);
        if ($found !== null) {
            $geo = $found;
            $ips[$ip] = $found + ['t' => time()];
            qm_geo_save(['config' => qm_geo_data()['config'], 'ips' => $ips]);
        }
    }

    if ($geo !== null) {
        $comment['geo_country'] = $geo['country'] ?? '';
        $comment['geo_cc']      = $geo['cc'] ?? '';
        $comment['geo_region']  = $geo['region'] ?? '';
        $comment['geo_city']    = $geo['city'] ?? '';
        $comment['geo_isp']     = $geo['isp'] ?? '';
    }
    return $comment;
}

/* ---------- 2. 渲染归属地徽标 ---------- */
add_action('qm_comment_meta', 'qm_geo_render');
function qm_geo_render($comment) {
    if (!is_array($comment)) return;

    // 优先用地区信息（含国旗与运营商），无结果再退 IP 前缀
    $cc    = strtoupper((string)($comment['geo_cc'] ?? ''));
    $country = trim((string)($comment['geo_country'] ?? ''));
    $region  = trim((string)($comment['geo_region'] ?? ''));
    $city    = trim((string)($comment['geo_city'] ?? ''));
    $isp     = trim((string)($comment['geo_isp'] ?? ''));

    $label = '';
    if ($country !== '' || $region !== '' || $city !== '') {
        $flag = qm_geo_flag($cc);
        $bits = [];
        if ($flag !== '') $bits[] = $flag;
        if ($country !== '') $bits[] = $country;
        if ($region !== '' && $region !== $country) $bits[] = $region;
        if ($city !== '' && $city !== $region) $bits[] = $city;
        $label = implode(' ', $bits);
        if ($isp !== '') $label .= ' · ' . $isp;
    }

    if ($label === '' && (int)qm_geo_config('ip_fallback', 1) === 1) {
        $ip = (string)($comment['ip'] ?? '');
        if ($ip === '127.0.0.1' || $ip === '::1') {
            $label = '本机';
        } elseif (filter_var($ip, FILTER_VALIDATE_IP)) {
            $masked = preg_replace('/\.\d+\.\d+$/', '', $ip);
            if ($masked === null || $masked === '') $masked = $ip;
            $label = 'IP ' . $masked . '.*';
        }
    }
    if ($label === '') return;

    $title = isset($comment['ip']) ? ' title="' . e((string)$comment['ip']) . '"' : '';
    echo '<span class="qm-comment-geo"' . $title . ' style="display:inline-block;margin:2px 0;font-size:12px;'
        . 'color:#666;background:rgba(120,160,220,.12);border-radius:4px;padding:0 6px;cursor:help;">📍 '
        . e($label) . '</span>';
}
