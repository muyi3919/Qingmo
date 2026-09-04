<?php
/**
 * 后台图片上传接口（文章编辑器使用）
 * 鉴权：需管理员登录 + CSRF；输出 JSON：{ok:true,url:"..."} 或 {ok:false,error:"..."}
 */
require_once __DIR__ . '/../includes/functions.php';
qm_session_start();

if (!is_logged_in()) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => '未登录'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => '安全验证失败'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (empty($_FILES['image']) || ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => '未收到图片文件'], JSON_UNESCAPED_UNICODE);
    exit;
}

$file = $_FILES['image'];
// 扩展名白名单
$allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!isset($allowed[$ext])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => '仅支持 jpg/png/gif/webp 图片'], JSON_UNESCAPED_UNICODE);
    exit;
}
// 真实类型校验
$info = @getimagesize($file['tmp_name']);
if ($info === false) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => '文件不是有效图片'], JSON_UNESCAPED_UNICODE);
    exit;
}
// 大小限制：php 默认 upload_max_filesize 之内，另设 8MB 上限
if ($file['size'] > 8 * 1024 * 1024) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => '图片不能超过 8MB'], JSON_UNESCAPED_UNICODE);
    exit;
}

$subDir = date('Ym');
$dir = ROOT_DIR . '/assets/uploads/' . $subDir;
if (!is_dir($dir)) mkdir($dir, 0755, true);

$name = date('dHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$dest = $dir . '/' . $name;
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => '保存失败，请检查目录权限'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'url' => site_base_url() . '/assets/uploads/' . $subDir . '/' . $name,
    'path' => 'assets/uploads/' . $subDir . '/' . $name,
], JSON_UNESCAPED_UNICODE);
