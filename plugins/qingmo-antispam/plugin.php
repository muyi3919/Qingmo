<?php
/**
 * 插件：反垃圾评论
 * 挂在 qm_comment_data（评论写入前过滤器）上做纯服务端校验，命中返回 false 并给出原因。
 * 校验项：提交间隔 / 按 IP 限频 / 链接数量上限 / 关键词·邮箱·昵称黑名单 / 最小长度
 */
if (!defined('QM_BOOT')) { exit('Access denied'); }

/* ---------- 数据 ---------- */
function qm_asp_data() {
    return load_data(DATA_DIR . '/plugin_antispam.php', ['config' => [], 'hits' => []]);
}

function qm_asp_cfg($key, $default = null) {
    return qm_asp_data()['config'][$key] ?? $default;
}

function qm_asp_words($key, $defaults) {
    $raw = trim((string)qm_asp_cfg($key, ''));
    if ($raw === '') return $defaults;
    return array_values(array_filter(array_map('trim', preg_split('/[\r\n,，]+/', $raw)), 'strlen'));
}

function qm_asp_reject($msg) {
    $GLOBALS['qm_comment_error'] = $msg;
    return false;
}

/* ---------- 校验 ---------- */
add_filter('qm_comment_data', 'qm_asp_check', 10, 2);
function qm_asp_check($comment, $post) {
    if ($comment === false) return $comment;
    if ((int)qm_asp_cfg('enabled', 1) !== 1) return $comment;

    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $content = trim((string)($comment['content'] ?? ''));
    $email   = mb_strtolower(trim((string)($comment['author_email'] ?? '')));
    $name    = mb_strtolower(trim((string)($comment['author_name'] ?? '')));

    // 1) 提交间隔
    $minInterval = max(0, (int)qm_asp_cfg('min_interval', 10));
    // 2) 每小时限条数
    $maxPerHour = max(1, (int)qm_asp_cfg('max_per_hour', 10));

    if ($ip !== '') {
        $data = qm_asp_data();
        $hits = $data['hits'];
        $now = time();
        // 只保留最近 2 小时记录
        foreach ($hits as $k => $v) {
            $hits[$k] = array_values(array_filter($v, function ($t) use ($now) { return $now - $t < 7200; }));
            if (empty($hits[$k])) unset($hits[$k]);
        }
        $recent = $hits[$ip] ?? [];

        if ($minInterval > 0 && $recent) {
            $last = max($recent);
            if ($now - $last < $minInterval) {
                return qm_asp_reject('评论太频繁了，请间隔至少 ' . $minInterval . ' 秒再试。');
            }
        }
        if (count($recent) >= $maxPerHour) {
            return qm_asp_reject('该 IP 评论过于频繁，请稍后再试。');
        }
        $recent[] = $now;
        $hits[$ip] = $recent;
        $data['hits'] = $hits;
        save_data(DATA_DIR . '/plugin_antispam.php', $data);
    }

    // 3) 最小长度
    $minLen = max(1, (int)qm_asp_cfg('min_len', 2));
    if (mb_strlen($content) < $minLen) {
        return qm_asp_reject('评论内容太短（至少 ' . $minLen . ' 个字符）。');
    }

    // 4) 链接数量上限
    $maxLinks = max(0, (int)qm_asp_cfg('max_links', 3));
    if (preg_match_all('#https?://#i', $content, $m) > $maxLinks) {
        return qm_asp_reject('评论中链接过多，请精简后再提交。');
    }

    // 5) 内容关键词黑名单
    $defaultWords = ['代开发票', '博彩', '赌博', '兼职日结', '刷单', '加微信', '加qq', '加qq群', '违禁品', '办证', '套现'];
    foreach (qm_asp_words('word_list', $defaultWords) as $w) {
        if ($w !== '' && mb_strpos(mb_strtolower($content), mb_strtolower($w)) !== false) {
            return qm_asp_reject('评论内容疑似广告或违规，未通过校验。');
        }
    }

    // 6) 邮箱黑名单（子串匹配）
    foreach (qm_asp_words('email_black', ['spam', 'mailinator', 'yopmail', 'guerrillamail']) as $w) {
        if ($w !== '' && mb_strpos($email, mb_strtolower($w)) !== false) {
            return qm_asp_reject('该邮箱不被接受，如有疑问请联系站长。');
        }
    }

    // 7) 昵称黑名单（子串匹配）
    foreach (qm_asp_words('name_black', []) as $w) {
        if ($w !== '' && mb_strpos($name, mb_strtolower($w)) !== false) {
            return qm_asp_reject('该昵称不被接受，请换一个。');
        }
    }

    return $comment;
}
