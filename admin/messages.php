<?php
require_once __DIR__ . '/_guard.php';
$adminPageTitle = '留言板管理';

$msg = '';

// 通过审核
if (isset($_GET['approve'])) {
    if (!isset($_GET['token']) || !verify_csrf($_GET['token'] ?? '')) {
        die('安全验证失败');
    }
    approve_message((int)$_GET['approve']);
    $msg = '留言已通过审核。';
}

// 删除
if (isset($_GET['delete'])) {
    if (!isset($_GET['token']) || !verify_csrf($_GET['token'] ?? '')) {
        die('安全验证失败');
    }
    delete_message((int)$_GET['delete']);
    $msg = '留言已删除。';
}

$status = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;
$messages = load_messages($status);
usort($messages, function ($a, $b) {
    return ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0);
});

$token = csrf_token();
$filterQuery = $status === null ? '' : '&status=' . $status;

include __DIR__ . '/_header.php';
?>

<p>
    <a href="index.php?page=messages">全部</a> |
    <a href="index.php?page=messages&status=1">已通过</a> |
    <a href="index.php?page=messages&status=0">待审核</a>
    <span style="color:#888;font-size:12px;">（前台页面：<a href="../index.php?page=guestbook" target="_blank">留言板</a>）</span>
</p>

<?php if ($msg): ?>
    <div class="msg success"><?php echo e($msg); ?></div>
<?php endif; ?>

<table class="admin-table">
    <tr>
        <th>ID</th><th>留言者</th><th>邮箱</th><th>内容</th><th>归属地</th><th>设备</th><th>状态</th><th>时间</th><th>操作</th>
    </tr>
    <?php foreach ($messages as $m): ?>
    <tr>
        <td><?php echo (int)$m['id']; ?></td>
        <td><?php echo e($m['author_name'] ?? ''); ?></td>
        <td><?php echo e($m['author_email'] ?? ''); ?></td>
        <td>
            <?php echo e(make_summary($m['content'] ?? '', 50)); ?>
            <?php if (!empty($m['author_url'])): ?>
                <br><span style="color:#888;font-size:12px;"><?php echo e($m['author_url']); ?></span>
            <?php endif; ?>
        </td>
        <td style="font-size:12px;">
            <?php
            $geoBits = array_filter([
                (string)($m['geo_country'] ?? ''),
                (string)($m['geo_region'] ?? ''),
                (string)($m['geo_city'] ?? ''),
            ], function ($v) { return $v !== ''; });
            echo $geoBits ? e(implode(' ', $geoBits)) : '<span style="color:#bbb">—</span>';
            ?>
        </td>
        <td style="font-size:12px;">
            <?php
            $uaBits = array_filter([
                (string)($m['ua_os'] ?? ''),
                trim((string)($m['ua_browser'] ?? '') . ' ' . (string)($m['ua_version'] ?? '')),
            ], function ($v) { return $v !== ''; });
            echo $uaBits ? e(implode(' · ', $uaBits)) : '<span style="color:#bbb">—</span>';
            ?>
        </td>
        <td><?php echo (int)($m['status'] ?? 0) ? '已通过' : '<span style="color:red">待审核</span>'; ?></td>
        <td><?php echo format_date($m['created_at'] ?? 0); ?></td>
        <td>
            <?php if (!(int)($m['status'] ?? 0)): ?>
            <a href="index.php?page=messages&approve=<?php echo (int)$m['id']; ?>&token=<?php echo $token . $filterQuery; ?>">通过</a> |
            <?php endif; ?>
            <a href="index.php?page=messages&delete=<?php echo (int)$m['id']; ?>&token=<?php echo $token . $filterQuery; ?>" onclick="return confirm('确定删除这条留言？')" style="color:red">删除</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<?php if (!$messages): ?>
    <p style="color:#888;">还没有留言。</p>
<?php endif; ?>

<?php include __DIR__ . '/_footer.php'; ?>
