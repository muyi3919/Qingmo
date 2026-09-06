<?php
/**
 * 评论表情包（轻墨版，风格参考 Argon 的 argon_emotion_list）
 *
 * 表情列表通过过滤器钩子暴露：
 *   add_filter('qm_emotion_list', 'your_callback');
 * 回调收到一个“表情组数组”，应修改后返回。
 *
 * 结构（与 Argon 一致）：
 * EmotionArray = [ Group, Group, ... ]
 * Group  = [ 'groupname' => 组名(必须), 'description' => 说明(可选), 'list' => [Emotion, ...](必须) ]
 * Emotion= text 型: [ 'type'=>'text', 'text'=>颜文字/emoji, 'title'=>可选 ]
 *        | sticker 型: [ 'type'=>'sticker', 'src'=>图片地址, 'code'=>代码(不带冒号), 'title'=>可选 ]
 * sticker 用法：评论里输入 :code:，输出时会被替换成该图片。
 */

/**
 * 内置默认表情组（颜文字 / Emoji / B站小黄脸，图片随系统内置在 assets/emotions/bilibili）
 */
function qm_emotion_defaults() {
    $groups = [
        [
            'groupname' => '颜文字',
            'list' => [
                ['type' => 'text', 'text' => '(´▽`)', 'title' => '开心'],
                ['type' => 'text', 'text' => '(￣▽￣)~*', 'title' => '得意'],
                ['type' => 'text', 'text' => '(｡•̀ᴗ-)✧', 'title' => '俏皮'],
                ['type' => 'text', 'text' => '(≧∇≦)ﾉ', 'title' => '欢呼'],
                ['type' => 'text', 'text' => 'ヾ(≧▽≦*)o', 'title' => '兴奋'],
                ['type' => 'text', 'text' => '(・ω・)ノ', 'title' => '打招呼'],
                ['type' => 'text', 'text' => '(●ˇ∀ˇ●)', 'title' => '害羞'],
                ['type' => 'text', 'text' => '_〆(・∀・)', 'title' => '留言'],
                ['type' => 'text', 'text' => '(´；ω；`)', 'title' => '泪目'],
                ['type' => 'text', 'text' => '(╯°□°)╯︵ ┻━┻', 'title' => '掀桌'],
            ],
        ],
        [
            'groupname' => 'Emoji',
            'list' => [
                ['type' => 'text', 'text' => '😀'], ['type' => 'text', 'text' => '😂'],
                ['type' => 'text', 'text' => '😍'], ['type' => 'text', 'text' => '😭'],
                ['type' => 'text', 'text' => '😅'], ['type' => 'text', 'text' => '🤔'],
                ['type' => 'text', 'text' => '😘'], ['type' => 'text', 'text' => '😎'],
                ['type' => 'text', 'text' => '👍'], ['type' => 'text', 'text' => '🙏'],
                ['type' => 'text', 'text' => '🎉'], ['type' => 'text', 'text' => '💪'],
                ['type' => 'text', 'text' => '🔥'], ['type' => 'text', 'text' => '❤️'],
            ],
        ],
    ];
    // 内置 B站小黄脸表情（194 枚，图片版权归 Bilibili；图片随系统发布）
    $biliMap = include __DIR__ . '/emotion-bilibili.php';
    if (is_array($biliMap) && $biliMap) {
        $bili = [];
        foreach ($biliMap as $entry) {
            $code = (string)($entry[0] ?? '');
            $file = (string)($entry[1] ?? '');
            if ($code === '' || $file === '') continue;
            $bili[] = [
                'type' => 'sticker',
                'code' => $code,
                'src'  => site_base_url() . '/assets/emotions/bilibili/' . rawurlencode($file),
                'title' => $code,
            ];
        }
        if ($bili) {
            $groups[] = [
                'groupname' => 'B站',
                'description' => '内置 B站小黄脸 × ' . count($bili),
                'list' => $bili,
            ];
        }
    }
    return $groups;
}

/**
 * 获取最终表情列表（先内置，再交给 qm_emotion_list 过滤器扩展/覆盖）
 */
function qm_get_emotions() {
    static $list = null;
    if ($list === null) {
        $list = apply_filters('qm_emotion_list', qm_emotion_defaults());
        if (!is_array($list)) $list = qm_emotion_defaults();
    }
    return $list;
}

/**
 * 输出表情选择器 HTML（可折叠，默认收起）
 * 布局：顶部一排「分类名」tab（颜文字 / Emoji / 各插件表情组），下方只显示当前组的表情，
 * 组再多也不会横向铺开撑爆面板。样式全部内联，主题无关。
 */
function qm_emotion_picker_html() {
    $groups = [];
    foreach (qm_get_emotions() as $group) {
        if (!is_array($group) || empty($group['list']) || !is_array($group['list'])) continue;
        $name = (string)($group['groupname'] ?? '');
        if ($name === '') continue;
        $groups[] = ['name' => $name, 'list' => $group['list'], 'description' => (string)($group['description'] ?? '')];
    }
    $count = count($groups);
    if ($count === 0) return '';

    $html = '<button type="button" class="emotion-toggle" id="emotionToggle" '
        . 'aria-expanded="false" style="border:1px solid #ccc;background:#fff;border-radius:999px;'
        . 'padding:2px 12px;font-size:12px;cursor:pointer;margin-bottom:6px;color:#555;">😊 表情</button>';
    $html .= '<div class="emoji-bar emotion-panel" id="emotionPanel" style="display:none;max-width:380px;'
        . 'border:1px solid #e2e2e2;border-radius:10px;background:#fff;padding:8px;box-shadow:0 2px 8px rgba(0,0,0,.06);">';

    // 顶部：分类名 tab 一行
    $html .= '<div class="emotion-tabs" role="tablist" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;">';
    $tabBase = 'type="button" class="emotion-tab" role="tab"';
    foreach ($groups as $gi => $group) {
        $isFirst = ($gi === 0);
        $html .= '<button ' . $tabBase . ' data-group="' . $gi . '" aria-selected="' . ($isFirst ? 'true' : 'false') . '"'
            . ' style="font-size:12px;line-height:1;padding:5px 11px;border-radius:999px;cursor:pointer;border:1px solid '
            . ($isFirst ? '#3a3a3a' : '#d8d8d8') . ';background:' . ($isFirst ? '#3a3a3a' : '#fff') . ';color:'
            . ($isFirst ? '#fff' : '#777') . ';">' . e($group['name']) . '</button>';
    }
    $html .= '</div>';

    // 下方：每个组一个表情区，默认只显示第一组
    $pageLayout = 'display:flex;flex-wrap:wrap;gap:5px;align-items:center;max-height:200px;overflow-y:auto;padding:2px;';
    foreach ($groups as $gi => $group) {
        $desc = $group['description'];
        $show = ($gi === 0);
        $html .= '<div class="emotion-page" data-group="' . $gi . '" role="tabpanel" style="' . $pageLayout
            . ($show ? '' : 'display:none;') . '">';
        foreach ($group['list'] as $em) {
            if (!is_array($em)) continue;
            $type = (string)($em['type'] ?? 'text');
            $title = (string)($em['title'] ?? '');
            $ttl = $title !== '' ? ' title="' . e($title) . '"' : '';
            $btnStyle = 'border:0;background:transparent;cursor:pointer;border-radius:6px;padding:2px;line-height:1;';
            if ($type === 'sticker') {
                $src = (string)($em['src'] ?? '');
                $code = (string)($em['code'] ?? '');
                if ($src === '' || $code === '') continue;
                $html .= '<button type="button" class="em" data-kind="sticker" data-code="' . e($code) . '" style="' . $btnStyle . '"' . $ttl . '>'
                    . '<img src="' . e($src) . '" alt=":' . e($code) . ':" style="width:26px;height:26px;object-fit:contain;vertical-align:middle;pointer-events:none;display:block;">'
                    . '</button>';
            } else {
                $text = (string)($em['text'] ?? '');
                if ($text === '') continue;
                $html .= '<button type="button" class="em" data-kind="text" data-val="' . e($text) . '" style="' . $btnStyle . 'font-size:16px;"' . $ttl . '>' . e($text) . '</button>';
            }
        }
        if ($desc !== '') {
            $html .= '<span style="font-size:11px;color:#bbb;width:100%;">' . e($desc) . '</span>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * 把评论内容里的 :code: 图片表情代码替换为图片（仅替换已知 code）
 */
function qm_emotions_render_html($html) {
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (qm_get_emotions() as $group) {
            if (empty($group['list']) || !is_array($group['list'])) continue;
            foreach ($group['list'] as $em) {
                if (($em['type'] ?? '') !== 'sticker') continue;
                $code = (string)($em['code'] ?? '');
                $src = (string)($em['src'] ?? '');
                if ($code !== '' && $src !== '') $map[$code] = $em;
            }
        }
    }
    if (!$map) return $html;
    return preg_replace_callback('/:([A-Za-z0-9_\-]{1,32}):/', function ($m) use ($map) {
        $code = $m[1];
        if (!isset($map[$code])) return $m[0];
        $title = (string)($map[$code]['title'] ?? '');
        $alt = ':' . $code . ':';
        return '<img src="' . e($map[$code]['src']) . '" alt="' . e($alt) . '" title="' . e($title) . '"'
            . ' style="max-width:64px;max-height:64px;vertical-align:middle;" loading="lazy">';
    }, (string)$html);
}
