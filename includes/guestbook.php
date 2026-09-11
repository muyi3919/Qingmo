<?php
/**
 * 留言板（Guestbook）
 * 数据保存在 data/messages.php；与评论完全独立，但复用同一套校验、表情、
 * Markdown 渲染以及插件钩子（qm_comment_data / qm_comment_meta），
 * 所以归属地、设备信息、反垃圾等插件同样对留言生效。
 */
if (!defined('QM_BOOT')) { exit('Access denied'); }

/* ---------- 数据读写 ---------- */
function guestbook_file() {
    return DATA_DIR . '/messages.php';
}

/**
 * 读取留言（$status 为 null 时返回全部）
 */
function load_messages($status = null) {
    $list = load_data(guestbook_file(), []);
    if (!is_array($list)) $list = [];
    if ($status !== null) {
        $list = array_filter($list, function ($m) use ($status) {
            return (int)($m['status'] ?? 0) === (int)$status;
        });
    }
    return array_values($list);
}

function save_messages($list) {
    return save_data(guestbook_file(), array_values($list));
}

function count_messages($status = 1) {
    return count(load_messages($status));
}

/**
 * 新增留言（返回是否写入成功）
 */
function add_message($data) {
    $list = load_messages();
    $data['id'] = count($list) > 0 ? max(array_column($list, 'id')) + 1 : 1;
    $data['created_at'] = time();
    $list[] = $data;
    return save_messages($list);
}

function delete_message($id) {
    $id = (int)$id;
    $tree = qm_build_message_tree(load_messages());
    $doomed = [];
    $collect = function ($node) use (&$collect, &$doomed) {
        $doomed[] = (int)$node['id'];
        foreach (($node['children'] ?? []) as $child) $collect($child);
    };
    if (isset($tree['by_id'][$id])) $collect($tree['by_id'][$id]);
    if (!$doomed) return false;
    $list = load_messages();
    $kept = array_filter($list, function ($m) use ($doomed) {
        return !in_array((int)$m['id'], $doomed, true);
    });
    return save_messages(array_values($kept));
}

function approve_message($id) {
    $id = (int)$id;
    $list = load_messages();
    $found = false;
    foreach ($list as &$m) {
        if ((int)$m['id'] === $id) { $m['status'] = 1; $found = true; }
    }
    unset($m);
    save_messages($list);
    return $found;
}

/* ---------- 留言列表渲染（支持楼中楼回复） ---------- */
/**
 * 把留言列表组装成回复树：['top'=>[...], 'by_id'=>[...]]
 */
function qm_build_message_tree($messages) {
    $byId = [];
    foreach ($messages as $m) {
        $m['children'] = [];
        $byId[(int)$m['id']] = $m;
    }
    $top = [];
    foreach ($byId as $id => $m) {
        $pid = (int)($m['parent_id'] ?? 0);
        if ($pid > 0 && isset($byId[$pid])) {
            $byId[$pid]['children'][] = &$byId[$id];
        } else {
            $top[] = &$byId[$id];
        }
    }
    // 顶层与子层都按时间倒序（新的在前）
    $sortRec = function (&$nodes) use (&$sortRec) {
        usort($nodes, function ($a, $b) {
            return ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0);
        });
        foreach ($nodes as &$n) {
            if (!empty($n['children'])) $sortRec($n['children']);
        }
    };
    $sortRec($top);
    return ['top' => $top, 'by_id' => $byId];
}

/**
 * 统计某条留言下所有子孙数量
 */
function qm_count_message_kids($nodes) {
    $n = 0;
    foreach ($nodes as $c) {
        $n += 1 + qm_count_message_kids($c['children'] ?? []);
    }
    return $n;
}

function qm_render_message_items($messages) {
    $tree = qm_build_message_tree($messages);
    if (!$tree['top']) return '';

    $render = function ($m, $depth = 0) use (&$render) {
        $content = (string)($m['content'] ?? '');
        $content = preg_replace('/(<img class="qm-img")/', '$1 style="max-width:240px;height:auto;vertical-align:middle;"', md_to_html($content));
        $content = qm_emotions_render_html($content);
        $avatar = qm_avatar_url((string)($m['author_email'] ?? ''), 40);
        $parentId = (int)($m['parent_id'] ?? 0);

        $out = '<div class="comment-item message-item comment-depth-' . min($depth, 6) . ($parentId ? ' is-reply' : '') . '" id="message-' . (int)$m['id'] . '">' . "\n";
        $out .= '<div class="comment-meta">'
            . '<img class="comment-avatar" src="' . e($avatar) . '" alt="" width="26" height="26" loading="lazy"'
            . ' style="width:26px;height:26px;border-radius:50%;vertical-align:middle;margin-right:6px;">'
            . ($parentId ? '<span class="reply-badge">回复</span>' : '')
            . '<strong>' . e($m['author_name'] ?? '匿名') . '</strong>';
        if (!empty($m['author_url'])) {
            $out .= '(<a href="' . e($m['author_url']) . '" target="_blank" rel="noopener nofollow">主页</a>)';
        }
        $out .= ' 留言于 ' . format_date($m['created_at'] ?? time()) . '</div>' . "\n";

        ob_start();
        do_action('qm_comment_meta', $m); // 归属地 / 设备信息等插件徽标
        $out .= ob_get_clean();

        $out .= '<div class="comment-content">' . $content . '</div>' . "\n";
        if (get_setting('allow_comments', '1') == '1') {
            $out .= '<button type="button" class="reply-btn" data-id="' . (int)$m['id'] . '" data-name="' . e($m['author_name'] ?? '') . '">↩ 回复</button>' . "\n";
        }
        if (!empty($m['children'])) {
            $kids = qm_count_message_kids($m['children']);
            $out .= '<button type="button" class="qm-replies-toggle" data-target="mkids-' . (int)$m['id'] . '" data-count="' . $kids . '" aria-expanded="false"'
                . ' style="margin:6px 0 2px;border:1px dashed #ccc;background:transparent;font-size:12px;padding:2px 12px;border-radius:999px;cursor:pointer;color:#888;">展开回复（' . $kids . ' 条）</button>' . "\n";
            $out .= '<div class="comment-children" id="mkids-' . (int)$m['id'] . '" style="display:none;">' . "\n";
            foreach ($m['children'] as $child) $out .= $render($child, $depth + 1);
            $out .= '</div>' . "\n";
        }
        $out .= '</div>' . "\n";
        return $out;
    };

    $html = '';
    foreach ($tree['top'] as $t) $html .= $render($t, 0);
    return $html;
}

/* ---------- 站长邮件提醒 ---------- */
function qm_notify_new_message($message) {
    if ((int)get_setting('comment_email_on', 0) !== 1) return false;
    $to = trim((string)get_setting('notify_email', ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $site = get_setting('site_title', '我的博客');
    $url  = site_base_url() . '/index.php?page=guestbook';
    $statusTxt = (int)($message['status'] ?? 0) === 1 ? '已发布' : '待审核';
    $subject = '[' . $site . '] 新留言：' . ($message['author_name'] ?? '');
    $body = "站点：{$site}\n留言者：{$message['author_name']} ({$message['author_email']})\n状态：{$statusTxt}\n\n留言内容：\n"
        . ($message['content'] ?? '') . "\n\n查看留言板：{$url}\n管理后台：" . site_base_url() . '/admin/';
    $html = '<p><strong>' . e($site) . '</strong> 收到一条新留言：</p>'
        . '<p><strong>留言者：</strong>' . e($message['author_name'] . ' (' . $message['author_email'] . ')') . '</p>'
        . '<p><strong>状态：</strong>' . e($statusTxt) . '</p>'
        . '<div style="border:1px solid #ddd;background:#fafafa;padding:10px;">' . e($message['content'] ?? '') . '</div>'
        . '<p><a href="' . e($url) . '">打开留言板</a></p>';
    return qm_send_mail($to, $subject, $body, $html);
}

/* ---------- 留言表单（复用评论表单的 id，便于共用样式与表情面板） ---------- */
function qm_guestbook_form_html() {
    $guest = ['n' => '', 'e' => '', 'u' => ''];
    if (!empty($_COOKIE['qm_guest'])) {
        $g = json_decode(base64_decode((string)$_COOKIE['qm_guest']), true);
        if (is_array($g)) $guest = $g + $guest;
    }
    $tips = function_exists('qm_comment_tips') ? qm_comment_tips() : ['说点什么吧'];
    $tip = $tips[array_rand($tips)] . '（支持md格式哦）';
    ?>
    <div class="comment-form" id="commentFormBox">
        <h4 id="commentFormTitle">写留言</h4>
        <form method="post" action="index.php?page=guestbook" id="commentForm">
            <?php csrf_field(); ?>
            <input type="hidden" name="parent_id" id="commentParentId" value="0">
            <label>昵称 *</label>
            <input type="text" name="author_name" value="<?php echo e($guest['n']); ?>" required>
            <label>邮箱 *（必填，仅用于通知，不会公开展示）</label>
            <input type="email" name="author_email" value="<?php echo e($guest['e']); ?>" required>
            <label>个人主页</label>
            <input type="text" name="author_url" value="<?php echo e($guest['u']); ?>" placeholder="http://">
            <label>内容 *</label>
            <?php echo qm_emotion_picker_html(); ?>
            <textarea name="content" id="commentContent" required placeholder="<?php echo e($tip); ?>"></textarea>
            <input type="submit" value="发布留言">
            <button type="button" id="commentCancelReply" style="display:none;">取消回复</button>
        </form>
    </div>
    <?php
}
