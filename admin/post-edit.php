<?php
require_once __DIR__ . '/_guard.php';
$adminPageTitle = isset($_GET['id']) ? '编辑文章' : '写文章';

$id = (int)($_GET['id'] ?? 0);
$post = ['title' => '', 'slug' => '', 'content' => '', 'md_source' => '', 'format' => 'md', 'summary' => '', 'category_id' => '', 'status' => 1, 'tags' => []];
$msg = '';
$err = '';

if ($id) {
    $dbPost = load_post($id);
    if ($dbPost) $post = $dbPost;
}

// 当前编辑格式：md（Markdown）| html（富文本/源码，二者均为 HTML）
$isMd = ($post['format'] ?? '') === 'md';
if (($post['format'] ?? '') === '' && ($post['md_source'] ?? '') !== '') $isMd = true;
if (!$id && ($post['format'] ?? '') === '') $isMd = true; // 新文章默认 Markdown

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $err = '安全验证失败，请刷新页面重试。';
    } else {
        $title = trim($_POST['title'] ?? '');
        $format = ($_POST['editor_format'] ?? 'md') === 'html' ? 'html' : 'md';
        $raw = (string)($_POST['content'] ?? '');       // md 源 或 html
        $rawMd = trim($_POST['md_source'] ?? '');
        if ($format !== 'md') $rawMd = '';

        if ($title === '') {
            $err = '请输入标题。';
        } elseif ($raw === '' && $rawMd === '') {
            $err = '请输入内容。';
        } else {
            if ($format === 'md') {
                $mdSource = $rawMd !== '' ? $rawMd : $raw;
                $htmlContent = md_to_html($mdSource);
            } else {
                $mdSource = '';
                $htmlContent = $raw;
            }

            $data = [
                'title' => $title,
                'slug' => trim($_POST['slug'] ?? ''),
                'content' => $htmlContent,
                'md_source' => $mdSource,
                'format' => $format,
                'summary' => trim($_POST['summary'] ?? ''),
                'category_id' => (int)($_POST['category_id'] ?? 0),
                'status' => (int)($_POST['status'] ?? 1),
                'tags' => array_values(array_filter(array_map('trim', explode(',', trim($_POST['tags'] ?? ''))))),
            ];
            if ($data['slug'] === '') $data['slug'] = slugify($title);

            if ($id) {
                $data['author_id'] = $post['author_id'] ?? $_SESSION['admin_id'];
                $data['author_name'] = $post['author_name'] ?? $_SESSION['admin_username'];
                $data['view_count'] = $post['view_count'] ?? 0;
                $data['comment_count'] = $post['comment_count'] ?? 0;
                $data['created_at'] = $post['created_at'] ?? time();
                $data['updated_at'] = time();
                save_post($id, $data);
                $msg = '文章已更新。';
            } else {
                $id = next_post_id();
                $data['id'] = $id;
                $data['author_id'] = $_SESSION['admin_id'];
                $data['author_name'] = $_SESSION['admin_username'];
                $data['view_count'] = 0;
                $data['comment_count'] = 0;
                $data['created_at'] = time();
                $data['updated_at'] = time();
                save_post($id, $data);
                $msg = '文章已发布。';
            }

            refresh_all_categories();
            $post = load_post($id);
            $isMd = $format === 'md';
        }
    }
}

$categories = load_categories();
$tagStr = implode(', ', $post['tags'] ?? []);

// 编辑器初值
$mdValue = $isMd ? (string)($post['md_source'] ?? '') : '';
if ($mdValue === '' && !empty($post['md_source'])) $mdValue = (string)$post['md_source'];
$htmlValue = (string)($post['content'] ?? '');

include __DIR__ . '/_header.php';
?>

<?php if ($msg): ?>
    <div class="msg success"><?php echo e($msg); ?></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="msg error"><?php echo e($err); ?></div>
<?php endif; ?>

<form method="post" class="admin-form qm-editor-form" id="postForm">
    <?php csrf_field(); ?>
    <input type="hidden" name="editor_format" id="editorFormat" value="<?php echo $isMd ? 'md' : 'html'; ?>">
    <input type="hidden" name="content" id="contentFinal">
    <input type="hidden" name="md_source" id="mdSourceFinal">

    <label>标题</label>
    <input type="text" name="title" value="<?php echo e($post['title']); ?>" required>

    <div style="display:flex;flex-wrap:wrap;gap:14px;">
        <div style="flex:1;min-width:240px;">
            <label>Slug（URL别名，留空自动生成）</label>
            <input type="text" name="slug" value="<?php echo e($post['slug']); ?>">
        </div>
        <div style="flex:1;min-width:180px;">
            <label>分类</label>
            <select name="category_id">
                <option value="">-- 选择分类 --</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo ($post['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                    <?php echo e($cat['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:0 0 auto;">
            <label>状态</label>
            <select name="status">
                <option value="1" <?php echo ($post['status'] ?? 1) == 1 ? 'selected' : ''; ?>>已发布</option>
                <option value="0" <?php echo ($post['status'] ?? 1) == 0 ? 'selected' : ''; ?>>草稿</option>
            </select>
        </div>
    </div>

    <label>标签（用逗号分隔）</label>
    <input type="text" name="tags" value="<?php echo e($tagStr); ?>" placeholder="PHP, 博客, 教程">

    <label>摘要（留空自动截取正文）</label>
    <textarea name="summary" style="height: 60px;"><?php echo e($post['summary']); ?></textarea>

    <!-- ======================= 编辑器 ======================= -->
    <label>正文</label>
    <div class="qm-editor" id="qmEditor">
        <div class="qm-editor-toolbar">
            <span class="qm-mode-group">
                <button type="button" class="qm-mode-btn <?php echo $isMd ? 'active' : ''; ?>" data-view="md">Markdown</button>
                <button type="button" class="qm-mode-btn <?php echo !$isMd ? 'active' : ''; ?>" data-view="rich">可视化</button>
                <button type="button" class="qm-mode-btn" data-view="source">HTML</button>
            </span>
            <!-- Markdown 工具栏 -->
            <span class="qm-tools qm-md-tools" style="<?php echo $isMd ? '' : 'display:none'; ?>">
                <button type="button" data-md="# ">H1</button>
                <button type="button" data-md="## ">H2</button>
                <button type="button" data-md="**加粗**">加粗</button>
                <button type="button" data-md="*斜体*">斜体</button>
                <button type="button" data-md="- 列表项">列表</button>
                <button type="button" data-md="[链接文字](https://)">链接</button>
                <button type="button" data-md="```php\n代码\n```">代码</button>
                <button type="button" class="qm-img-btn">🖼 图片</button>
                <button type="button" class="qm-quote-btn">❝ 引用</button>
            </span>
            <!-- 可视化（富文本）工具栏 -->
            <span class="qm-tools qm-rich-tools" style="<?php echo $isMd ? 'display:none' : ''; ?>">
                <button type="button" data-cmd="bold" title="加粗"><b>B</b></button>
                <button type="button" data-cmd="italic" title="斜体"><i>I</i></button>
                <button type="button" data-cmd="underline" title="下划线"><u>U</u></button>
                <button type="button" data-cmd="strikeThrough" title="删除线"><s>S</s></button>
                <span class="sep">|</span>
                <button type="button" data-cmd="formatBlock" data-val="H2">H2</button>
                <button type="button" data-cmd="formatBlock" data-val="H3">H3</button>
                <button type="button" data-cmd="insertUnorderedList">• 列表</button>
                <button type="button" data-cmd="insertOrderedList">1. 列表</button>
                <button type="button" data-cmd="formatBlock" data-val="BLOCKQUOTE">❝ 引用</button>
                <span class="sep">|</span>
                <button type="button" data-cmd="createLink">🔗 链接</button>
                <button type="button" class="qm-img-btn-rich">🖼 图片</button>
                <button type="button" data-cmd="insertHTML" data-html="<pre><code>在此输入代码</code></pre>">代码</button>
            </span>
            <span class="qm-view-group">
                <button type="button" class="qm-preview-btn" style="display:none;">👁 预览</button>
            </span>
        </div>

        <div class="qm-editor-body">
            <textarea id="mdArea" class="qm-editor-area" placeholder="在这里输入 Markdown…（支持拖拽/粘贴图片上传）" spellcheck="false"><?php echo e($mdValue); ?></textarea>
            <div id="richArea" class="qm-rich-area" contenteditable="true" data-placeholder="在这里直接书写或粘贴富文本…"></div>
            <textarea id="sourceArea" class="qm-editor-area" placeholder="HTML 源码…" spellcheck="false" style="font-family:Consolas,Menlo,monospace;"></textarea>
            <div class="qm-preview" id="mdPreview" style="display:none;"></div>
        </div>

        <input type="file" id="qmImageInput" accept="image/*" multiple style="display:none">
        <div class="qm-drop-hint">将图片拖到此处上传</div>
    </div>
    <p style="font-size:12px;color:#888;margin:4px 0 0;">提示：可视化模式粘贴时如需保留格式请用 Ctrl+Shift+V；正文以 HTML 存储。</p>

    <p style="margin-top: 15px;">
        <input type="submit" value="<?php echo $id ? '保存修改' : '发布文章'; ?>">
        <a href="index.php?page=posts" style="margin-left: 10px;">返回列表</a>
    </p>
</form>

<style>
.qm-editor { border:1px solid #ccc; background:#fff; }
.qm-editor-toolbar { display:flex; flex-wrap:wrap; gap:4px; align-items:center; padding:6px 8px; background:#f3f3f3; border-bottom:1px solid #ccc; }
.qm-editor-toolbar button { border:1px solid #aaa; background:#fff; border-radius:3px; padding:3px 10px; font-size:12px; cursor:pointer; font-family:inherit; min-width:26px; }
.qm-editor-toolbar button:hover { background:#e2e8f0; }
.qm-mode-group .qm-mode-btn.active { background:#2e5c8a; color:#fff; border-color:#2e5c8a; }
.qm-mode-group, .qm-tools, .qm-view-group { display:inline-flex; flex-wrap:wrap; gap:4px; align-items:center; }
.qm-mode-group { margin-right:8px; }
.qm-tools .sep { color:#999; margin:0 3px; }
.qm-view-group { margin-left:auto; }
.qm-editor-body { position:relative; }
.qm-editor-area {
    width:100%; min-height:360px; border:0; padding:12px; display:none;
    font-family:Consolas,Menlo,"Courier New",monospace; font-size:13px; line-height:1.7; resize:vertical; box-sizing:border-box;
}
.qm-rich-area {
    width:100%; min-height:360px; padding:12px; display:none; overflow:auto;
    font-family:"PingFang SC","Microsoft YaHei",Arial,sans-serif; font-size:14px; line-height:1.8;
    outline:none; box-sizing:border-box;
}
.qm-rich-area:empty::before { content:attr(data-placeholder); color:#aaa; }
.qm-rich-area img { max-width:100%; }
.qm-preview { width:100%; min-height:360px; padding:12px; overflow:auto; display:none; border:0; box-sizing:border-box; font-size:14px; line-height:1.8; }
.qm-preview img { max-width:100%; }
.qm-preview pre { background:#f6f8fa; border:1px solid #e1e4e8; padding:10px; overflow:auto; }
.qm-preview code { background:#f6f8fa; padding:1px 5px; }
.qm-preview pre code { background:none; padding:0; }
.qm-preview blockquote { border-left:4px solid #ddd; margin:8px 0; padding:2px 12px; color:#666; }
.qm-drop-hint { display:none; position:absolute; inset:0; z-index:5; background:rgba(46,92,138,.85); color:#fff; font-size:15px; align-items:center; justify-content:center; pointer-events:none; }
.qm-drop-hint.show { display:flex; }
</style>
<script>
(function () {
    'use strict';
    var form = document.getElementById('postForm');
    var formatInput = document.getElementById('editorFormat');
    var mdArea = document.getElementById('mdArea');
    var richArea = document.getElementById('richArea');
    var sourceArea = document.getElementById('sourceArea');
    var mdPreview = document.getElementById('mdPreview');
    var fileInput = document.getElementById('qmImageInput');
    var viewBtns = document.querySelectorAll('.qm-mode-btn');
    var mdTools = document.querySelector('.qm-md-tools');
    var richTools = document.querySelector('.qm-rich-tools');
    var previewBtn = document.querySelector('.qm-preview-btn');
    var dropHint = document.querySelector('.qm-drop-hint');
    var csrf = (form.querySelector('input[name="csrf_token"]') || {}).value || '';
    var uploadUrl = 'upload.php';

    // 视图状态
    var state = { md: '', html: '' };
    var currentView = <?php echo $isMd ? "'md'" : "'rich'"; ?>;
    mdArea.value = <?php echo json_encode($mdValue); ?>;
    state.md = mdArea.value;
    state.html = <?php echo json_encode($htmlValue); ?>;

    /* ---------- Markdown 预览 ---------- */
    function escapeHtml(s) {
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function mdInline(t) {
        t = escapeHtml(t);
        t = t.replace(/`([^`]+)`/g, '<code>$1</code>');
        t = t.replace(/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/g, '<img src="$2" alt="$1" loading="lazy">');
        t = t.replace(/\[([^\]]+)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/g, '<a href="$2" target="_blank" rel="noopener nofollow">$1</a>');
        t = t.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        t = t.replace(/__([^_]+)__/g, '<strong>$1</strong>');
        t = t.replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>');
        t = t.replace(/~~([^~]+)~~/g, '<del>$1</del>');
        return t;
    }
    function mdToHtml(text) {
        text = String(text).replace(/\r\n?/g, '\n');
        var lines = text.split('\n');
        var out = '', para = [], listType = null, i = 0;
        function flushPara() { if (para.length) { out += '<p>' + mdInline(para.join('\n')) + '</p>\n'; para = []; } }
        function closeList() { if (listType) { out += '</' + listType + '>\n'; listType = null; } }
        while (i < lines.length) {
            var raw = lines[i].replace(/\s+$/, '');
            var fm = raw.match(/^```\s*([\w+-]*)\s*$/);
            if (fm) {
                closeList(); flushPara();
                var buf = [], lang = fm[1]; i++;
                while (i < lines.length && !/^```\s*$/.test(lines[i])) { buf.push(lines[i]); i++; }
                i++;
                out += '<pre><code' + (lang ? ' class="language-' + lang + '"' : '') + '>' + escapeHtml(buf.join('\n')) + '</code></pre>\n';
                continue;
            }
            if (raw === '') { closeList(); flushPara(); i++; continue; }
            var hm = raw.match(/^(#{1,6})\s+(.+)$/);
            if (hm) { closeList(); flushPara(); out += '<h' + hm[1].length + '>' + mdInline(hm[2]) + '</h' + hm[1].length + '>\n'; i++; continue; }
            if (/^(\s*[-*_]\s*){3,}$/.test(raw)) { closeList(); flushPara(); out += '<hr>\n'; i++; continue; }
            if (/^>\s?/.test(raw)) {
                closeList(); flushPara();
                var qb = [];
                while (i < lines.length && /^>\s?(.*)$/.test(lines[i])) { var qm = lines[i].match(/^>\s?(.*)$/); qb.push(mdInline(qm[1])); i++; }
                out += '<blockquote><p>' + qb.join('<br>\n') + '</p></blockquote>\n';
                continue;
            }
            var ulm = raw.match(/^\s*[-*+]\s+(.*)$/);
            var olm = !ulm && raw.match(/^\s*\d+[.)]\s+(.*)$/);
            if (ulm || olm) {
                flushPara();
                var type = ulm ? 'ul' : 'ol';
                if (listType !== type) { closeList(); out += '<' + type + '>\n'; listType = type; }
                while (i < lines.length) {
                    var cur = lines[i].replace(/\s+$/, '');
                    var mm = (type === 'ul') ? cur.match(/^\s*[-*+]\s+(.*)$/) : cur.match(/^\s*\d+[.)]\s+(.*)$/);
                    if (!mm) break;
                    out += '<li>' + mdInline(mm[1]) + '</li>\n'; i++;
                }
                continue;
            }
            closeList(); para.push(raw); i++;
        }
        closeList(); flushPara();
        return out;
    }

    /* ---------- 视图切换 ---------- */
    function syncState() {
        if (currentView === 'md') state.md = mdArea.value;
        else if (currentView === 'rich') state.html = richArea.innerHTML;
        else if (currentView === 'source') state.html = sourceArea.value;
    }
    function applyView(view) {
        syncState();
        currentView = view;
        // 更新可见输入框内容
        if (view === 'md') { mdArea.value = state.md; }
        if (view === 'rich') { richArea.innerHTML = state.html; }
        if (view === 'source') { sourceArea.value = state.html; }

        mdArea.style.display = view === 'md' ? 'block' : 'none';
        richArea.style.display = view === 'rich' ? 'block' : 'none';
        sourceArea.style.display = view === 'source' ? 'block' : 'none';
        mdPreview.style.display = 'none';
        previewBtn.style.display = view === 'md' ? '' : 'none';

        mdTools.style.display = view === 'md' ? '' : 'none';
        richTools.style.display = view === 'rich' ? '' : 'none';
        viewBtns.forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-view') === view); });
        formatInput.value = (view === 'md') ? 'md' : 'html';
        if (view === 'rich') richArea.focus();
    }
    viewBtns.forEach(function (btn) {
        btn.addEventListener('click', function () { applyView(btn.getAttribute('data-view')); });
    });

    /* ---------- Markdown 预览 ---------- */
    previewBtn.addEventListener('click', function () {
        if (mdPreview.style.display === 'none') { mdPreview.innerHTML = mdToHtml(mdArea.value); mdPreview.style.display = 'block'; mdArea.style.display = 'none'; }
        else { mdPreview.style.display = 'none'; mdArea.style.display = 'block'; }
    });
    mdArea.addEventListener('input', function () { if (mdPreview.style.display !== 'none') mdPreview.innerHTML = mdToHtml(mdArea.value); });

    /* ---------- Markdown 语法按钮 ---------- */
    mdTools.querySelectorAll('[data-md]').forEach(function (b) {
        b.addEventListener('click', function () {
            var snip = b.getAttribute('data-md').replace(/\\n/g, '\n');
            insertInto(mdArea, snip, false);
            if (mdPreview.style.display !== 'none') mdPreview.innerHTML = mdToHtml(mdArea.value);
        });
    });
    mdTools.querySelector('.qm-quote-btn').addEventListener('click', function () {
        var el = mdArea, s = el.selectionStart, e = el.selectionEnd;
        var sel = el.value.substring(s, e) || '引用内容';
        var rep = '> ' + sel.split('\n').join('\n> ');
        el.value = el.value.slice(0, s) + rep + el.value.slice(e);
        el.focus(); el.setSelectionRange(s, s + rep.length);
        state.md = el.value;
    });
    function insertInto(el, text, keepSelEnd) {
        var s = el.selectionStart, e = el.selectionEnd;
        el.value = el.value.slice(0, s) + text + el.value.slice(e);
        var pos = keepSelEnd ? s + text.length : s;
        el.focus(); el.setSelectionRange(pos, pos);
        state.md = el.value;
    }

    /* ---------- 富文本命令 ---------- */
    document.querySelectorAll('.qm-rich-tools [data-cmd]').forEach(function (b) {
        b.addEventListener('click', function () {
            richArea.focus();
            var cmd = b.getAttribute('data-cmd');
            if (cmd === 'createLink') {
                var url = prompt('输入链接地址（http:// 或 https://）：');
                if (url) document.execCommand('createLink', false, url);
            } else if (cmd === 'formatBlock') {
                document.execCommand('formatBlock', false, '<' + b.getAttribute('data-val') + '>');
            } else if (cmd === 'insertHTML') {
                document.execCommand('insertHTML', false, b.getAttribute('data-html'));
            } else {
                document.execCommand(cmd, false, null);
            }
            state.html = richArea.innerHTML;
        });
    });
    richArea.addEventListener('input', function () { state.html = richArea.innerHTML; });
    richArea.addEventListener('blur', function () { state.html = richArea.innerHTML; });
    sourceArea.addEventListener('input', function () { state.html = sourceArea.value; });

    // 粘贴净化（普通粘贴去掉网页格式，避免脏样式）
    richArea.addEventListener('paste', function (e) {
        if (e.shiftKey) return; // 按住 Shift 保留格式
        var html = e.clipboardData.getData('text/html');
        var text = e.clipboardData.getData('text/plain');
        if (!text) return;
        e.preventDefault();
        if (html) {
            var tmp = document.createElement('div');
            tmp.innerHTML = html;
            // 剥离 script/style 与内联样式
            tmp.querySelectorAll('script,style,link,meta').forEach(function (n) { n.remove(); });
            tmp.querySelectorAll('*').forEach(function (n) {
                n.removeAttribute('style'); n.removeAttribute('class'); n.removeAttribute('id');
            });
            var frag = tmp.innerHTML;
            document.execCommand('insertHTML', false, frag || escapeHtml(text));
        } else {
            document.execCommand('insertText', false, text);
        }
        state.html = richArea.innerHTML;
    });

    /* ---------- 图片上传（md / 富文本通用） ---------- */
    function pickImage() { fileInput.click(); }
    document.querySelector('.qm-img-btn').addEventListener('click', pickImage);
    document.querySelector('.qm-img-btn-rich').addEventListener('click', pickImage);
    fileInput.addEventListener('change', function () {
        uploadImages(Array.prototype.slice.call(fileInput.files));
        fileInput.value = '';
    });
    var wrap = document.querySelector('.qm-editor');
    ['dragover', 'dragenter'].forEach(function (ev) { wrap.addEventListener(ev, function (e) { e.preventDefault(); dropHint.classList.add('show'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { wrap.addEventListener(ev, function (e) { e.preventDefault(); dropHint.classList.remove('show'); }); });
    wrap.addEventListener('drop', function (e) {
        if (e.dataTransfer && e.dataTransfer.files) uploadImages(Array.prototype.slice.call(e.dataTransfer.files));
    });
    [mdArea, richArea].forEach(function (el) {
        el.addEventListener('paste', function (e) {
            if (currentView === 'source') return;
            var items = e.clipboardData && e.clipboardData.items;
            if (!items) return;
            var files = [];
            Array.prototype.forEach.call(items, function (it) {
                if (it.type && it.type.indexOf('image/') === 0) { var f = it.getAsFile(); if (f) files.push(f); }
            });
            if (files.length) { e.preventDefault(); uploadImages(files); }
        });
    });

    function uploadImages(files) {
        files.forEach(function (file) {
            if (file.type.indexOf('image/') !== 0) { alert('仅支持图片文件'); return; }
            var fd = new FormData();
            fd.append('image', file);
            fd.append('csrf_token', csrf);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', uploadUrl, true);
            xhr.onload = function () {
                if (xhr.status === 200) {
                    try {
                        var res = JSON.parse(xhr.responseText);
                        if (res.ok) insertImage(res.url, file.name);
                        else alert(res.error || '上传失败');
                    } catch (err) { alert('上传响应异常'); }
                } else { alert('上传失败（HTTP ' + xhr.status + '）'); }
            };
            xhr.onerror = function () { alert('网络错误，上传失败'); };
            xhr.send(fd);
        });
    }
    function insertImage(url, name) {
        var clean = (name || 'image').replace(/\.[^.]+$/, '');
        if (currentView === 'md') {
            var snip = '![' + clean + '](' + url + ')';
            insertInto(mdArea, snip, false);
            if (mdPreview.style.display !== 'none') mdPreview.innerHTML = mdToHtml(mdArea.value);
        } else if (currentView === 'rich') {
            richArea.focus();
            document.execCommand('insertHTML', false, '<img src="' + url + '" alt="' + clean + '" style="max-width:100%">');
            state.html = richArea.innerHTML;
        } else {
            var s = sourceArea.selectionStart, e = sourceArea.selectionEnd;
            var img = '<img src="' + url + '" alt="' + clean + '" style="max-width:100%">';
            sourceArea.value = sourceArea.value.slice(0, s) + img + sourceArea.value.slice(e);
            sourceArea.focus();
            state.html = sourceArea.value;
        }
    }

    /* ---------- 提交 ---------- */
    form.addEventListener('submit', function () {
        syncState();
        if (currentView === 'md') {
            formatInput.value = 'md';
            document.getElementById('mdSourceFinal').value = state.md;
            document.getElementById('contentFinal').value = state.md;
        } else {
            formatInput.value = 'html';
            document.getElementById('mdSourceFinal').value = '';
            document.getElementById('contentFinal').value = state.html;
        }
    });

    // 初始显示
    applyView(currentView);
})();
</script>

<?php include __DIR__ . '/_footer.php'; ?>
