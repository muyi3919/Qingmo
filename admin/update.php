<?php
/**
 * 系统更新（在线更新）
 * - 检查更新：读取 GitHub 仓库指定分支最新提交
 * - 立即更新：下载仓库 zip 并覆盖站点文件（自动排除 data/、assets/uploads/、.git）
 * - 更新源可改为自定义 zip 直链（内网/镜像场景）
 *
 * 说明（v2.3.2+）：
 *  - 临时目录使用系统 temp，不依赖 data/ 建子目录；
 *  - owner/repo 必须拆成两段分别 URL 编码（不能整段编码，否则 / 变 %2F 会 404）；
 *  - 各类网络/权限错误给出具体提示。
 */
require_once __DIR__ . '/_guard.php';
$adminPageTitle = '系统更新';

$msg = '';
$err = '';
$info = '';

/* ================= 小工具 ================= */

/**
 * 解析并校验 owner/repo
 */
function qm_ota_repo_parts($repo) {
    $repo = trim((string)$repo);
    $parts = explode('/', $repo, 2);
    if (count($parts) !== 2) return null;
    list($owner, $name) = $parts;
    if ($owner === '' || $name === '') return null;
    if (!preg_match('/^[A-Za-z0-9_.\-]{1,64}$/', $owner)) return null;
    if (!preg_match('/^[A-Za-z0-9_.\-]{1,100}$/', $name)) return null;
    return [$owner, $name];
}

function qm_ota_http($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Qingmo-OTA/2.3',
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        return ['ok' => $errno === 0, 'code' => $code, 'body' => $body === false ? '' : $body, 'error' => $error];
    }
    $ctx = stream_context_create(['http' => ['timeout' => 60, 'ignore_errors' => true, 'follow_location' => 1]]);
    $body = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) { $code = (int)$m[1]; }
        }
    }
    return ['ok' => $body !== false, 'code' => $code, 'body' => $body === false ? '' : $body, 'error' => $body === false ? 'file_get_contents 失败' : ''];
}

function qm_ota_rmdir($dir) {
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    foreach ($items as $it) {
        if ($it === '.' || $it === '..') continue;
        $path = $dir . '/' . $it;
        is_dir($path) ? qm_ota_rmdir($path) : @unlink($path);
    }
    @rmdir($dir);
}

/**
 * 执行一次在线更新：成功返回 ''（并把提示写入 $GLOBALS['_qm_ota_msg']），失败返回错误文案
 */
function qm_ota_run_update($otaRepo, $otaBranch, $otaZipUrl) {
    $tmpRoot = rtrim(sys_get_temp_dir(), '/\\');
    $tmpDir = $tmpRoot . '/qingmo_ota_' . substr(md5(ROOT_DIR), 0, 10);
    if (is_dir($tmpDir)) qm_ota_rmdir($tmpDir);
    @mkdir($tmpDir, 0755, true);
    if (!is_dir($tmpDir) || !is_writable($tmpDir)) {
        return '无法在系统临时目录创建更新工作区（' . $tmpDir . '），请检查 PHP sys_get_temp_dir 是否可写。';
    }
    if (!class_exists('ZipArchive')) {
        return '服务器未启用 Zip 扩展，无法解压更新。';
    }

    // 决定下载地址（自定义直链 or GitHub 仓库 zip，owner/repo 分段编码）
    if ($otaZipUrl !== '') {
        $zipUrl = $otaZipUrl;
    } else {
        $parts = qm_ota_repo_parts($otaRepo);
        if ($parts === null) {
            return '仓库地址不合法，格式应为：owner/repo（例如 muyi3919/Qingmo）。';
        }
        $zipUrl = 'https://codeload.github.com/' . rawurlencode($parts[0]) . '/' . rawurlencode($parts[1])
            . '/zip/refs/heads/' . rawurlencode($otaBranch);
    }

    $res = qm_ota_http($zipUrl);
    if (!$res['ok']) {
        return '更新包下载失败：' . $res['error']
            . '（请确认服务器能访问 codeload.github.com；网络受限时请改用「自定义更新包直链」）。';
    }
    if ($res['code'] !== 200 || strlen($res['body']) < 1000) {
        return '更新包下载失败：HTTP ' . $res['code'] . '（地址有误、仓库私有或网络受限，可改用自定义直链）。';
    }

    $zipFile = $tmpDir . '/update.zip';
    if (file_put_contents($zipFile, $res['body']) === false) {
        return '更新包写入临时文件失败，请检查服务器 temp 目录权限。';
    }

    $za = new ZipArchive();
    if ($za->open($zipFile) !== true) {
        qm_ota_rmdir($tmpDir);
        return '更新包不是有效的 zip 文件。';
    }
    $extract = $tmpDir . '/x';
    @mkdir($extract, 0755, true);
    if (!is_dir($extract)) {
        $za->close();
        qm_ota_rmdir($tmpDir);
        return '无法创建解压目录，请检查服务器 temp 目录权限。';
    }
    $za->extractTo($extract);
    $za->close();

    // zip 内若只有唯一顶层文件夹（GitHub 的 {repo}-{branch}）则取其内容
    $base = $extract;
    $tops = array_values(array_diff(scandir($extract), ['.', '..']));
    if (count($tops) === 1 && is_dir($extract . '/' . $tops[0])) {
        $base = $extract . '/' . $tops[0];
    }

    // 递归覆盖（排除 data/、assets/uploads/、.git）
    $copied = 0;
    $failed = 0;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        $rel = substr($file->getPathname(), strlen($base) + 1);
        $rel = str_replace('\\', '/', $rel);
        $segs = explode('/', $rel);
        if (in_array($segs[0] ?? '', ['data', '.git'], true)
            || (($segs[0] ?? '') === 'assets' && ($segs[1] ?? '') === 'uploads')) {
            continue;
        }
        $dest = ROOT_DIR . '/' . $rel;
        if (!is_dir(dirname($dest))) @mkdir(dirname($dest), 0755, true);
        if (@copy($file->getPathname(), $dest)) $copied++; else $failed++;
    }
    qm_ota_rmdir($tmpDir);

    $cfg = load_config();
    $cfg['ota_last_update'] = time();
    save_config($cfg);

    if ($copied === 0) {
        return '更新未能写入任何文件，请检查站点根目录（' . ROOT_DIR . '）的写权限。';
    }
    $GLOBALS['_qm_ota_msg'] = '更新完成：写入 ' . $copied . ' 个文件'
        . ($failed > 0 ? '，失败 ' . $failed . ' 个（多为目录无写权限）' : '')
        . '（data/ 与上传文件已自动跳过）。建议 Ctrl+F5 强刷缓存。';
    return '';
}

/* ================= 配置 ================= */
$otaRepo   = trim((string)get_setting('ota_repo', 'muyi3919/Qingmo'));
$otaBranch = trim((string)get_setting('ota_branch', 'main'));
$otaZipUrl = trim((string)get_setting('ota_zip_url', ''));

/* ================= 处理动作 ================= */
if (isset($_GET['action'])) {
    if (!isset($_GET['token']) || !verify_csrf($_GET['token'] ?? '')) {
        die('安全验证失败');
    }
    $action = $_GET['action'];

    if ($action === 'check') {
        if ($otaZipUrl !== '') {
            $info = '当前使用自定义 zip 直链，无法比对版本；可直接点「立即更新」覆盖站点文件。';
        } else {
            $parts = qm_ota_repo_parts($otaRepo);
            if ($parts === null) {
                $err = '仓库地址不合法，格式应为：owner/repo（例如 muyi3919/Qingmo）。';
            } else {
                // 注意：owner 与 repo 必须分开编码
                $api = 'https://api.github.com/repos/' . rawurlencode($parts[0]) . '/' . rawurlencode($parts[1])
                    . '/commits/' . rawurlencode($otaBranch);
                $res = qm_ota_http($api);
                if (!$res['ok']) {
                    $err = '无法连接 GitHub API：' . e($res['error'])
                        . '（请确认服务器能访问 api.github.com；若网络受限，可在下方改用「自定义更新包直链」）。';
                } elseif ($res['code'] !== 200) {
                    $hint = '';
                    $j = json_decode($res['body'], true);
                    if (is_array($j) && !empty($j['message'])) {
                        $hint = ' —— ' . (string)$j['message'];
                    }
                    if ($res['code'] === 404) {
                        $hint .= '（仓库或分支不存在，或仓库为私有：请检查 owner/repo、分支名；在线更新仅支持公开仓库）';
                    } elseif ($res['code'] === 403) {
                        $hint .= '（GitHub API 访问受限/限流，稍后再试，或改用自定义直链）';
                    }
                    $err = 'GitHub API 返回 HTTP ' . $res['code'] . $hint;
                } else {
                    $data = json_decode($res['body'], true);
                    if (!is_array($data) || !isset($data['sha'])) {
                        $err = '读取仓库信息失败：响应不是有效的提交数据（请检查仓库名，格式：owner/repo）。';
                    } else {
                        $sha = substr((string)$data['sha'], 0, 7);
                        $message = trim(explode("\n", (string)($data['commit']['message'] ?? ''))[0]);
                        $lastSha = (string)get_setting('ota_last_sha', '');
                        if ($lastSha !== '' && $lastSha !== $sha) {
                            $info = '发现新版本：' . e($message) . '（' . $sha . '）。当前记录的上次提交：' . e($lastSha);
                        } else {
                            $info = '仓库最新提交：' . e($message) . '（' . $sha . '）。';
                        }
                        $cfg = load_config();
                        $cfg['ota_last_sha'] = $sha;
                        save_config($cfg);
                    }
                }
            }
        }
    }

    if ($action === 'update') {
        set_time_limit(0);
        $runErr = qm_ota_run_update($otaRepo, $otaBranch, $otaZipUrl);
        if ($runErr === '') {
            $msg = $GLOBALS['_qm_ota_msg'] ?? '更新完成。';
            unset($GLOBALS['_qm_ota_msg']);
        } else {
            $err = $runErr;
        }
    }
}

$token = csrf_token();
$current = QM_VERSION;
$lastSha = (string)get_setting('ota_last_sha', '');
$lastUpdate = (int)get_setting('ota_last_update', 0);

/* ---------- 保存更新源设置 ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $err = '安全验证失败，请刷新页面重试。';
    } else {
        $cfg = load_config();
        $cfg['ota_repo'] = trim($_POST['ota_repo'] ?? 'muyi3919/Qingmo');
        $cfg['ota_branch'] = trim($_POST['ota_branch'] ?? 'main');
        $cfg['ota_zip_url'] = trim($_POST['ota_zip_url'] ?? '');
        save_config($cfg);
        $otaRepo = $cfg['ota_repo'];
        $otaBranch = $cfg['ota_branch'];
        $otaZipUrl = $cfg['ota_zip_url'];
        $msg = '更新源设置已保存。';
    }
}

include __DIR__ . '/_header.php';
?>

<?php if ($msg): ?>
    <div class="msg success"><?php echo e($msg); ?></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="msg error"><?php echo e($err); ?></div>
<?php endif; ?>
<?php if ($info): ?>
    <div class="msg" style="background:#eef6ff;border-color:#9cc5f5;"><?php echo $info; ?></div>
<?php endif; ?>

<table class="admin-table" style="width:70%;margin-bottom:18px;">
    <tr><th style="width:40%;">当前版本</th><td>v<?php echo e($current); ?></td></tr>
    <tr><th>更新源</th><td>
        <?php if ($otaZipUrl !== ''): ?>
            自定义 zip 直链（<code><?php echo e($otaZipUrl); ?></code>）
        <?php else: ?>
            GitHub 仓库 <code><?php echo e($otaRepo); ?></code>（分支 <code><?php echo e($otaBranch); ?></code>）
        <?php endif; ?>
    </td></tr>
    <tr><th>上次记录的最新提交</th><td><?php echo $lastSha !== '' ? e($lastSha) : '—'; ?></td></tr>
    <tr><th>上次更新时间</th><td><?php echo $lastUpdate ? format_date($lastUpdate) : '—'; ?></td></tr>
</table>

<p>
    <a class="btn" href="index.php?page=update&action=check&token=<?php echo $token; ?>">检查更新</a>
    <a class="btn" href="index.php?page=update&action=update&token=<?php echo $token; ?>" style="margin-left:8px;" onclick="return confirm('将从更新源拉取最新代码并覆盖站点文件（自动跳过 data/ 与上传目录）。确认继续？')">立即更新</a>
</p>
<p style="font-size:12px;color:#888;margin-top:6px;">
    提示：更新会覆盖 includes/admin/index 等核心文件，请先自行备份第三方改动；自定义主题/插件若不在仓库中不会受影响。若 GitHub 在你的服务器网络受限，请使用下方「自定义更新包直链」（可配合国内镜像）。若“更新完成但写入 0 个文件”，说明站点根目录对 PHP 无写权限，请把根目录属主调整为 Web 用户（如 www）后再试。
</p>

<form method="post" class="admin-form" style="max-width:560px;margin-top:20px;">
    <?php csrf_field(); ?>
    <label>GitHub 仓库（owner/repo）</label>
    <input type="text" name="ota_repo" value="<?php echo e($otaRepo); ?>" placeholder="muyi3919/Qingmo">
    <label>分支</label>
    <input type="text" name="ota_branch" value="<?php echo e($otaBranch); ?>" placeholder="main">
    <label>自定义更新包直链（可选，填了则忽略上方仓库）</label>
    <input type="text" name="ota_zip_url" value="<?php echo e($otaZipUrl); ?>" placeholder="https://example.com/qingmo-latest.zip">
    <p><input type="submit" value="保存更新源"></p>
</form>

<?php include __DIR__ . '/_footer.php'; ?>
