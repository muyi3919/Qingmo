<?php
require_once __DIR__ . '/_guard.php';
$adminPageTitle = '站点设置';

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $err = '安全验证失败，请刷新页面重试。';
    } else {
        $config = load_config();
        $config['site_title'] = trim($_POST['site_title'] ?? '');
        $config['site_description'] = trim($_POST['site_description'] ?? '');
        $config['site_url'] = trim($_POST['site_url'] ?? '');
        $config['posts_per_page'] = max(1, (int)($_POST['posts_per_page'] ?? 10));
        $config['allow_comments'] = (int)($_POST['allow_comments'] ?? 0);
        $config['comment_moderation'] = (int)($_POST['comment_moderation'] ?? 0);
        // 文章页脚相关设置
        $config['show_post_footer'] = (int)($_POST['show_post_footer'] ?? 1);
        $config['author_name'] = trim($_POST['author_name'] ?? '');
        $config['usage_agreement'] = trim($_POST['usage_agreement'] ?? '');
        // 评论邮件通知
        $config['comment_email_on'] = (int)($_POST['comment_email_on'] ?? 0);
        $config['notify_email'] = trim($_POST['notify_email'] ?? '');
        $config['at_notify_on'] = (int)($_POST['at_notify_on'] ?? 1);
        // 页脚自定义内容（信任管理员输入，支持 HTML）
        $config['footer_extra'] = trim($_POST['footer_extra'] ?? '');
        // 友链排序
        $config['links_order'] = in_array($_POST['links_order'] ?? 'name', ['name', 'random'], true)
            ? $_POST['links_order'] : 'name';
        // 邮件发送方式：php=PHP mail() / smtp=SMTP
        $config['mailer_mode'] = ($_POST['mailer_mode'] ?? 'php') === 'smtp' ? 'smtp' : 'php';
        $config['smtp_host'] = trim($_POST['smtp_host'] ?? '');
        $config['smtp_port'] = (int)($_POST['smtp_port'] ?? 587);
        $config['smtp_secure'] = in_array($_POST['smtp_secure'] ?? 'tls', ['ssl', 'tls', 'none'], true)
            ? $_POST['smtp_secure'] : 'tls';
        $config['smtp_user'] = trim($_POST['smtp_user'] ?? '');
        if ($_POST['smtp_pass'] ?? '' !== '') {
            $config['smtp_pass'] = (string)$_POST['smtp_pass']; // 留空则保留原密码
        }
        if (!isset($config['smtp_pass'])) $config['smtp_pass'] = '';
        $config['smtp_from'] = trim($_POST['smtp_from'] ?? '');
        $config['smtp_from_name'] = trim($_POST['smtp_from_name'] ?? '');
        // 保留 theme / active_plugins 等其它键
        if (empty($config['theme'])) $config['theme'] = 'default';
        if (!isset($config['active_plugins'])) $config['active_plugins'] = [];
        save_config($config);
        $msg = '设置已保存。';

        // 发送测试邮件
        $testTo = trim($_POST['test_email'] ?? '');
        if (isset($_POST['send_test']) && $testTo !== '') {
            if (!filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
                $err = '测试邮箱格式不正确。';
            } else {
                $ok = qm_send_mail(
                    $testTo,
                    '[' . ($config['site_title'] ?? '轻墨') . '] 邮件测试',
                    "这是一封来自 {$config['site_title']} 的测试邮件，说明邮件发送功能配置正确。",
                    '<p>这是一封来自 <strong>' . qm_text_to_html($config['site_title'] ?? '轻墨') . '</strong> 的测试邮件，说明邮件发送功能配置正确。</p>'
                );
                if ($ok) {
                    $msg = '设置已保存，且测试邮件发送成功（收件人：' . e($testTo) . '）。';
                } else {
                    $mailErr = qm_mail_last_error();
                    $err = '测试邮件发送失败。'
                        . ($mailErr !== '' ? '<br>具体原因：' . e($mailErr) : '')
                        . '<br>常见排查：确认服务器能连通 SMTP 服务器与端口；端口 587 用 STARTTLS、465 用 SSL；账号与发件人一致，密码使用“授权码”（QQ/163 需在邮箱后台开启 SMTP）。';
                }
            }
        }
    }
}

$settings = load_config();

include __DIR__ . '/_header.php';
?>

<?php if ($msg): ?>
    <div class="msg success"><?php echo e($msg); ?></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="msg error"><?php echo e($err); ?></div>
<?php endif; ?>

<form method="post" class="admin-form">
    <?php csrf_field(); ?>
    <label>站点标题</label>
    <input type="text" name="site_title" value="<?php echo e($settings['site_title'] ?? ''); ?>" required>

    <label>站点描述</label>
    <input type="text" name="site_description" value="<?php echo e($settings['site_description'] ?? ''); ?>">

    <label>站点URL</label>
    <input type="text" name="site_url" value="<?php echo e($settings['site_url'] ?? ''); ?>" placeholder="http://example.com">

    <label>每页文章数</label>
    <input type="number" name="posts_per_page" min="1" max="100" value="<?php echo e($settings['posts_per_page'] ?? 10); ?>">

    <label>允许评论</label>
    <select name="allow_comments">
        <option value="1" <?php echo ($settings['allow_comments'] ?? '1') == '1' ? 'selected' : ''; ?>>是</option>
        <option value="0" <?php echo ($settings['allow_comments'] ?? '1') == '0' ? 'selected' : ''; ?>>否</option>
    </select>

    <label>评论需审核</label>
    <select name="comment_moderation">
        <option value="1" <?php echo ($settings['comment_moderation'] ?? '0') == '1' ? 'selected' : ''; ?>>是</option>
        <option value="0" <?php echo ($settings['comment_moderation'] ?? '0') == '0' ? 'selected' : ''; ?>>否</option>
    </select>

    <h3 style="margin-top:24px;border-bottom:1px solid #ccc;padding-bottom:4px;">文章页脚设置</h3>

    <label>显示文章页脚</label>
    <select name="show_post_footer">
        <option value="1" <?php echo ($settings['show_post_footer'] ?? 1) == '1' ? 'selected' : ''; ?>>是</option>
        <option value="0" <?php echo ($settings['show_post_footer'] ?? 1) == '0' ? 'selected' : ''; ?>>否</option>
    </select>

    <label>作者署名（显示在文章页脚）</label>
    <input type="text" name="author_name" value="<?php echo e($settings['author_name'] ?? ''); ?>" placeholder="例如：张三">
    <p style="font-size:12px;color:#888;margin:2px 0 0;">留空则自动使用文章作者/站点标题。</p>

    <label>使用协议（显示在文章页脚）</label>
    <textarea name="usage_agreement" style="height:80px;" placeholder="例如：本站文章采用 CC BY-NC-SA 4.0 协议，转载需注明出处。"><?php echo e($settings['usage_agreement'] ?? ''); ?></textarea>

    <h3 style="margin-top:24px;border-bottom:1px solid #ccc;padding-bottom:4px;">评论邮件通知</h3>

    <label>有新评论时邮件通知</label>
    <select name="comment_email_on">
        <option value="0" <?php echo ($settings['comment_email_on'] ?? 0) == '0' ? 'selected' : ''; ?>>否</option>
        <option value="1" <?php echo ($settings['comment_email_on'] ?? 0) == '1' ? 'selected' : ''; ?>>是</option>
    </select>

    <label>@ 提及提醒</label>
    <select name="at_notify_on">
        <option value="1" <?php echo ($settings['at_notify_on'] ?? 1) == '1' ? 'selected' : ''; ?>>开启</option>
        <option value="0" <?php echo ($settings['at_notify_on'] ?? 1) == '0' ? 'selected' : ''; ?>>关闭</option>
    </select>
    <p style="font-size:12px;color:#888;margin:2px 0 0;">访客评论中 @历史评论者昵称时，自动发邮件提醒对方（需开启邮件发送）。</p>

    <label>通知收件邮箱（站长）</label>
    <input type="email" name="notify_email" value="<?php echo e($settings['notify_email'] ?? ''); ?>" placeholder="admin@example.com">

    <h3 style="margin-top:24px;border-bottom:1px solid #ccc;padding-bottom:4px;">页脚自定义</h3>

    <label>页脚自定义内容（支持 HTML，显示在所有页面底部）</label>
    <textarea name="footer_extra" style="height:90px;" placeholder="例如：&lt;script async src=&quot;https://...&quot;&gt;&lt;/script&gt; 或版权/备案文字、备案号跳链等"><?php echo e($settings['footer_extra'] ?? ''); ?></textarea>
    <p style="font-size:12px;color:#888;margin:2px 0 0;">适合放统计代码、备案号、自定义版权行；内容仅在管理员可编辑，因此视为可信输入。</p>

    <h3 style="margin-top:24px;border-bottom:1px solid #ccc;padding-bottom:4px;">友链</h3>

    <label>友链排序</label>
    <select name="links_order">
        <option value="name" <?php echo ($settings['links_order'] ?? 'name') === 'name' ? 'selected' : ''; ?>>按名称排序</option>
        <option value="random" <?php echo ($settings['links_order'] ?? 'name') === 'random' ? 'selected' : ''; ?>>每次刷新随机排序</option>
    </select>

    <h3 style="margin-top:24px;border-bottom:1px solid #ccc;padding-bottom:4px;">邮件发送方式（SMTP）</h3>

    <label>发送方式</label>
    <select name="mailer_mode" id="mailerMode">
        <option value="smtp" <?php echo ($settings['mailer_mode'] ?? 'php') === 'smtp' ? 'selected' : ''; ?>>SMTP（推荐，可发 HTML 邮件）</option>
        <option value="php" <?php echo ($settings['mailer_mode'] ?? 'php') !== 'smtp' ? 'selected' : ''; ?>>PHP mail()（依赖服务器自带发信）</option>
    </select>

    <div id="smtpFields">
        <label>SMTP 服务器</label>
        <input type="text" name="smtp_host" value="<?php echo e($settings['smtp_host'] ?? ''); ?>" placeholder="smtp.qq.com / smtp.gmail.com">
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <div style="flex:0 0 120px;">
                <label>端口</label>
                <input type="number" name="smtp_port" value="<?php echo e($settings['smtp_port'] ?? 587); ?>">
            </div>
            <div style="flex:0 0 130px;">
                <label>加密方式</label>
                <select name="smtp_secure">
                    <option value="tls" <?php echo ($settings['smtp_secure'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>STARTTLS（587）</option>
                    <option value="ssl" <?php echo ($settings['smtp_secure'] ?? 'tls') === 'ssl' ? 'selected' : ''; ?>>SSL（465）</option>
                    <option value="none" <?php echo ($settings['smtp_secure'] ?? 'tls') === 'none' ? 'selected' : ''; ?>>明文（测试用）</option>
                </select>
            </div>
        </div>
        <label>用户名</label>
        <input type="text" name="smtp_user" value="<?php echo e($settings['smtp_user'] ?? ''); ?>" autocomplete="off" placeholder="完整邮箱地址或账号">
        <label>密码 / 授权码</label>
        <input type="password" name="smtp_pass" autocomplete="new-password" placeholder="留空则保持不变">
        <label>发件人地址</label>
        <input type="email" name="smtp_from" value="<?php echo e($settings['smtp_from'] ?? ''); ?>" placeholder="留空则用通知邮箱">
        <label>发件人名称</label>
        <input type="text" name="smtp_from_name" value="<?php echo e($settings['smtp_from_name'] ?? ''); ?>" placeholder="留空则用站点标题">
    </div>

    <label style="margin-top:12px;">测试收件邮箱</label>
    <input type="email" name="test_email" value="" placeholder="test@example.com">
    <p style="font-size:12px;color:#888;margin:2px 0 8px;">点击下方「保存并发送测试邮件」会先保存以上设置，再向该地址发一封测试信。</p>

    <p>
        <input type="submit" name="save" value="保存设置">
        <input type="submit" name="send_test" value="保存并发送测试邮件" style="margin-left:6px;">
    </p>
</form>

<p style="margin-top:20px;color:#666;font-size:13px;">
    主题与插件请在「<a href="index.php?page=themes">主题</a> / <a href="index.php?page=plugins">插件</a>」页面管理。
</p>

<?php include __DIR__ . '/_footer.php'; ?>
