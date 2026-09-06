<?php
require_once '../includes/functions.php';
qm_session_start();

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = '安全验证失败，请刷新页面重试。';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $users = load_users();
        foreach ($users as $u) {
            if ($u['username'] === $username && password_verify($password, $u['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $u['id'];
                $_SESSION['admin_username'] = $u['username'];
                header('Location: index.php');
                exit;
            }
        }
        $error = '用户名或密码错误。';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 · <?php echo e(get_setting('site_title')); ?></title>
    <style>
        /* 整体重置 + 基础 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            background: #eef2f7;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            /* 去掉渐变背景，改用柔和纯色 + 微纹理 */
            background-color: #e9edf2;
            background-image: radial-gradient(circle at 10% 30%, rgba(255,255,255,0.4) 0%, transparent 30%),
                              radial-gradient(circle at 90% 70%, rgba(200,210,230,0.3) 0%, transparent 40%);
        }

        /* 登录卡片 — 更干净、朴素的风格 */
        .login-card {
            background: #ffffff;
            border-radius: 28px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.06), 0 4px 16px rgba(0, 0, 0, 0.03);
            width: 100%;
            max-width: 400px;
            padding: 2.8rem 2.2rem;
            transition: box-shadow 0.2s;
            border: 1px solid rgba(255, 255, 255, 0.5);
            backdrop-filter: none; /* 去掉毛玻璃，回归正常 */
        }

        .login-card:hover {
            box-shadow: 0 20px 48px rgba(0, 0, 0, 0.08);
        }

        /* 标题 — 更随意，不追求“设计感” */
        .login-title {
            font-size: 1.8rem;
            font-weight: 500;
            letter-spacing: -0.02em;
            color: #1f2a3a;
            margin-bottom: 2rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e6ecf3;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .login-title span {
            background: #dce3ec;
            padding: 0.1rem 0.6rem;
            border-radius: 40px;
            font-size: 0.9rem;
            font-weight: 400;
            color: #3a4a5e;
            margin-left: auto;
        }

        /* 错误信息 — 更接地气 */
        .msg-error {
            background: #fdf0ed;
            color: #b33a34;
            padding: 0.8rem 1rem;
            border-radius: 12px;
            font-size: 0.95rem;
            margin-bottom: 1.8rem;
            border-left: 4px solid #d45a52;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .msg-error svg {
            flex-shrink: 0;
        }

        /* 表单元素 — 去掉“高级感”，回归基础 */
        .form-group {
            margin-bottom: 1.4rem;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: #2c3e4f;
            margin-bottom: 0.3rem;
            letter-spacing: 0.3px;
        }

        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 0.75rem 0.9rem;
            border: 1px solid #d5dde6;
            border-radius: 12px;
            font-size: 1rem;
            background: #f8faff;
            transition: 0.15s ease;
            color: #1a2633;
            font-family: inherit;
        }

        .form-group input:focus {
            outline: none;
            border-color: #7c8ba0;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(60, 80, 110, 0.08);
        }

        .form-group input::placeholder {
            color: #a7b6c9;
            font-weight: 300;
            font-size: 0.95rem;
        }

        /* 提交按钮 — 简单、有质感但不花哨 */
        .btn-submit {
            width: 100%;
            padding: 0.8rem 1.2rem;
            background: #1f2a3a;
            color: white;
            border: none;
            border-radius: 40px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: 0.15s ease;
            margin-top: 0.3rem;
            letter-spacing: 0.3px;
            font-family: inherit;
            background: #2a3a4e;
        }

        .btn-submit:hover {
            background: #1d2b3d;
            transform: scale(1.01);
            box-shadow: 0 6px 14px rgba(30, 40, 60, 0.15);
        }

        .btn-submit:active {
            transform: scale(0.98);
        }

        /* 底部链接 — 朴素 */
        .login-footer {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e3e9f2;
            text-align: center;
            font-size: 0.9rem;
        }

        .login-footer a {
            color: #3b5a78;
            text-decoration: none;
            font-weight: 450;
            transition: 0.1s;
        }

        .login-footer a:hover {
            color: #1a2a3e;
            text-decoration: underline;
        }

        /* 小屏适配 */
        @media (max-width: 460px) {
            .login-card {
                padding: 2rem 1.5rem;
                border-radius: 24px;
            }
            .login-title {
                font-size: 1.6rem;
            }
        }

        /* 工具类 */
        .flex-center {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .gap-1 { gap: 0.4rem; }
    </style>
</head>
<body>
<div class="login-card">
    <!-- 标题区 —— 放弃“轻墨·管理后台”这种文案，更自然 -->
    <div class="login-title">
        登录
        <span>后台</span>
    </div>

    <!-- 错误提示 —— 如果存在 -->
    <?php if ($error): ?>
        <div class="msg-error">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            <span><?php echo e($error); ?></span>
        </div>
    <?php endif; ?>

    <!-- 登录表单 —— 没有多余动画，没有过度设计 -->
    <form method="post">
        <?php csrf_field(); ?>

        <div class="form-group">
            <label for="username">用户名</label>
            <input type="text" id="username" name="username" required autofocus placeholder="输入用户名">
        </div>

        <div class="form-group">
            <label for="password">密码</label>
            <input type="password" id="password" name="password" required placeholder="输入密码">
        </div>

        <button type="submit" class="btn-submit">进入后台</button>
    </form>

    <div class="login-footer">
        <a href="../index.php">← 返回网站首页</a>
    </div>
</div>
</body>
</html>
