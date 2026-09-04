<?php
require_once __DIR__ . '/_guard.php';
$adminPageTitle = '修改密码';

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $err = '安全验证失败，请刷新页面重试。';
    } else {
        $old = trim($_POST['old_password'] ?? '');
        $new = trim($_POST['new_password'] ?? '');
        $confirm = trim($_POST['confirm_password'] ?? '');

        if ($old === '' || $new === '' || $confirm === '') {
            $err = '请填写所有字段。';
        } elseif ($new !== $confirm) {
            $err = '两次输入的新密码不一致。';
        } elseif (strlen($new) < 6) {
            $err = '新密码至少需要6位。';
        } else {
            $users = load_users();
            $found = false;
            foreach ($users as &$u) {
                if ($u['id'] == $_SESSION['admin_id']) {
                    if (!password_verify($old, $u['password'])) {
                        $err = '当前密码错误。';
                    } else {
                        $u['password'] = password_hash($new, PASSWORD_DEFAULT);
                        $found = true;
                    }
                    break;
                }
            }
            unset($u);
            if ($found && $err === '') {
                save_users($users);
                $msg = '密码修改成功！';
            }
        }
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

<form method="post" class="admin-form" style="max-width:400px;">
    <?php csrf_field(); ?>
    <label>当前密码</label>
    <input type="password" name="old_password" required>

    <label>新密码</label>
    <input type="password" name="new_password" required>

    <label>确认新密码</label>
    <input type="password" name="confirm_password" required>

    <p><input type="submit" value="修改密码"></p>
</form>

<?php include __DIR__ . '/_footer.php'; ?>
