<?php
/**
 * 系统更新（在线更新）
 * - 检查更新：读取 GitHub 仓库 main 分支最新提交
 * - 立即更新：下载仓库 zip 并覆盖站点文件（自动排除 data/、assets/uploads/、.git）
 * - 更新源可改为自定义 zip 直链（内网/镜像场景）
 */
require_once __DIR__ . '/_guard.php';
$adminPageTitle = '系统更新';

$msg = '';
$err = '';
$info = '';

/* ---------- 小工具 ---------- */
function qm_ota_http_get($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Qingmo-OTA/2.2',
        ]);
        $body = curl_exec($ch);
        $ok = curl_errno($ch) === 0;
        curl_close($ch);
        return $ok ? $body : false;
    }
    $ctx = stream_context_create(['http' => ['timeout' => 60, 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    return $body === false ? false : $body;
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

/* ---------- 配置 ---------- */
$otaRepo   = trim((string)get_setting('ota_repo', 'muyi3919/Qingmo'));
$otaBranch = trim((string)get_setting('ota_branch', 'main'));
$otaZipUrl = trim((string)get_setting('ota_zip_url', '')); // 自定义 zip 直链（可选）

/* ---------- 处理动作 ---------- */
if (isset($_GET['action'])) {
    if (!isset($_GET['token']) || !verify_csrf($_GET['token'] ?? '')) {
        die('安全验证失败');
    }
    $action = $_GET['action'];

    if ($action === 'check') {
        if ($otaZipUrl !== '') {
            $info = '当前使用自定义 zip 直链，无法比对版本；可直接点「立即更新」覆盖站点文件。';
        } else {
            $api = 'https://api.github.com/repos/' . rawurlencode($otaRepo) . '/commits/' . rawurlencode($otaBranch);
            $json = qm_ota_http_get($api);
            if ($json === false) {
                $err = '无法连接 GitHub API（网络不可用或仓库地址有误）。';
            } else {
                $data = json_decode($json, true);
                if (!isset($data['sha'])) {
                    $err = '读取仓库信息失败，请检查仓库名（格式：owner/repo）。';
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

    if ($action === 'update') {
        set_time_limit(0);
        $tmpDir = DATA_DIR . '/_ota';
        if (is_dir($tmpDir)) qm_ota_rmdir($tmpDir);
        mkdir($tmpDir, 0755, true);

        $zipUrl = $otaZipUrl !== ''
            ? $otaZipUrl
            : 'https://codeload.github.com/' . rawurlencode($otaRepo) . '/zip/refs/heads/' . rawurlencode($otaBranch);

        $bin = qm_ota_http_get($zipUrl);
        if ($bin === false || strlen($bin) < 1000) {
            $err = '更新包下载失败，请稍后重试（网络不可用或地址有误）。';
        } elseif (!class_exists('ZipArchive')) {
            $err = '服务器未启用 Zip 扩展，无法解压更新。';
        } else {
            $zipFile = $tmpDir . '/update.zip';
            file_put_contents($zipFile, $bin);
            $za = new ZipArchive();
            if ($za->open($zipFile) !== true) {
                $err = '更新包不是有效的 zip 文件。';
            } else {
                $extract = $tmpDir . '/x';
                mkdir($extract, 0755, true);
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
                    if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);
                    if (@copy($file->getPathname(), $dest)) $copied++;
                }
                qm_ota_rmdir($tmpDir);

                $cfg = load_config();
                $cfg['ota_last_update'] = time();
                save_config($cfg);
                $msg = '更新完成：共写入 ' . $copied . ' 个文件（data/ 与上传文件已自动跳过）。建议 Ctrl+F5 强刷缓存。';
            }
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
    提示：更新会覆盖 includes/admin/index 等核心文件；第三方改动请提前备份。自定义主题/插件若不在仓库中不会受影响（仅覆盖仓库内含的同名文件）。
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
