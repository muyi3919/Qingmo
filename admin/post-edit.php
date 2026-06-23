<?php
$adminPageTitle = isset($_GET['id']) ? '编辑文章' : '写文章';

$id = (int)($_GET['id'] ?? 0);
$post = ['title' => '', 'slug' => '', 'content' => '', 'summary' => '', 'category_id' => '', 'status' => 1, 'tags' => []];
$msg = '';
$err = '';

if ($id) {
    $dbPost = load_post($id);
    if ($dbPost) $post = $dbPost;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $err = '安全验证失败，请刷新页面重试。';
    } else {
    $data = [
        'id' => $id,
        'title' => trim($_POST['title'] ?? ''),
        'slug' => trim($_POST['slug'] ?? ''),
        'content' => trim($_POST['content'] ?? ''),
        'summary' => trim($_POST['summary'] ?? ''),
        'category_id' => (int)($_POST['category_id'] ?? 0),
        'status' => (int)($_POST['status'] ?? 1),
        'tags' => array_filter(array_map('trim', explode(',', trim($_POST['tags'] ?? '')))),
    ];
    
    if (empty($data['title'])) {
        $err = '请输入标题。';
    } elseif (empty($data['content'])) {
        $err = '请输入内容。';
    } else {
        if (empty($data['slug'])) $data['slug'] = slugify($data['title']);
        
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
        }
        $post = load_post($id);
    }
}

$categories = load_categories();
$tagStr = implode(', ', $post['tags'] ?? []);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?php echo e($adminPageTitle); ?> - 轻墨 · 后台</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="container">
    <div class="header">
        <h1>轻墨 · 后台</h1>
        <div class="nav">
            <a href="index.php">仪表盘</a> | 
            <a href="index.php?page=posts">文章管理</a> | 
            <a href="index.php?page=categories">分类管理</a> | 
            <a href="index.php?page=comments">评论管理</a> | 
            <a href="index.php?page=settings">站点设置</a> | 
            <a href="index.php?logout=1">退出</a>
        </div>
    </div>
    
    <h2><?php echo e($adminPageTitle); ?></h2>
    
    <?php if ($msg): ?>
        <div class="msg success"><?php echo e($msg); ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
        <div class="msg error"><?php echo e($err); ?></div>
    <?php endif; ?>
    
    <form method="post" class="admin-form">
        <?php csrf_field(); ?>
        <label>标题</label>
        <input type="text" name="title" value="<?php echo e($post['title']); ?>" required>
        
        <label>Slug（URL别名，留空自动生成）</label>
        <input type="text" name="slug" value="<?php echo e($post['slug']); ?>">
        
        <label>分类</label>
        <select name="category_id">
            <option value="">-- 选择分类 --</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?php echo $cat['id']; ?>" <?php echo ($post['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                <?php echo e($cat['name']); ?>
            </option>
            <?php endforeach; ?>
        </select>
        
        <label>标签（用逗号分隔）</label>
        <input type="text" name="tags" value="<?php echo e($tagStr); ?>" placeholder="PHP, 博客, 教程">
        
        <label>摘要（留空自动截取正文）</label>
        <textarea name="summary" style="height: 60px;"><?php echo e($post['summary']); ?></textarea>
        
        <label>正文（支持 HTML）</label>
        <textarea name="content" required><?php echo e($post['content']); ?></textarea>
        
        <label>状态</label>
        <select name="status">
            <option value="1" <?php echo ($post['status'] ?? 1) == 1 ? 'selected' : ''; ?>>已发布</option>
            <option value="0" <?php echo ($post['status'] ?? 1) == 0 ? 'selected' : ''; ?>>草稿</option>
        </select>
        
        <p style="margin-top: 15px;">
            <input type="submit" value="<?php echo $id ? '保存修改' : '发布文章'; ?>">
            <a href="index.php?page=posts" style="margin-left: 10px;">返回列表</a>
        </p>
    </form>
</div>
</body>
</html>
