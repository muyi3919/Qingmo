<?php
// php tests/regression.php — isolated storage; never touches the blog data.
if (isset($argv[1])) {
    define('QM_BOOT', true);
    define('QM_VERSION', 'test');
    define('ROOT_DIR', $argv[2]);
    define('DATA_DIR', ROOT_DIR . '/data');
    define('POSTS_DIR', DATA_DIR . '/posts');
    define('THEMES_DIR', ROOT_DIR . '/themes');
    define('PLUGINS_DIR', ROOT_DIR . '/plugins');
    require dirname(__DIR__) . '/includes/functions.php';
    if ($argv[1] === 'worker') {
        $id = next_post_id();
        save_post($id, ['title' => 'Concurrent', 'status' => 1]);
        add_comment(['post_id' => 1, 'parent_id' => 0, 'status' => 1]);
        exit;
    }
    function check($ok, $label) {
        if (!$ok) throw new RuntimeException($label);
        echo "PASS $label\n";
    }
    check(qm_password_error('abc123') === '', 'six character password accepted');
    check(qm_password_error('abc12') !== '', 'short password rejected');
    check(qm_password_error('中文密码') !== '', 'password minimum counts characters');
    check(qm_password_error('中文密码测试') === '', 'six Unicode characters accepted');
    check(qm_password_error(str_repeat('a', 73)) !== '', 'bcrypt truncation prevented');
    check(count(load_posts()) === 12, 'concurrent post IDs remain unique');
    check(count(load_comments()) === 12, 'concurrent comments are retained');
    check(count(array_unique(array_column(load_comments(), 'id'))) === 12, 'comment IDs remain unique');
    $hash = password_hash('test-password-long', PASSWORD_DEFAULT);
    save_users([['id' => 1, 'password' => $hash]]);
    $_SESSION = ['admin_id' => 1, 'auth_version' => hash('sha256', $hash)];
    check(is_logged_in(), 'valid session accepted');
    save_users([['id' => 1, 'password' => password_hash('replacement-password', PASSWORD_DEFAULT)]]);
    check(!is_logged_in(), 'password change revokes old session');
    $_SESSION = ['admin_id' => 1];
    check(!is_logged_in(), 'legacy session requires login');
    $node = ['id' => 1, 'parent_id' => 0, 'author_name' => 'Guest', 'author_url' => 'javascript:alert(1)', 'content' => 'Hello', 'created_at' => time(), 'children' => []];
    $html = qm_render_comment_items(['top' => [$node], 'by_id' => [1 => $node]], 1);
    check(strpos($html, 'javascript:') === false, 'unsafe homepage URL omitted');
    save_data(DATA_DIR . '/atomic.php', ['value' => 1]);
    save_data(DATA_DIR . '/atomic.php', ['value' => 2]);
    check((include DATA_DIR . '/atomic.php')['value'] === 2, 'atomic replacement stores complete PHP');
    exit;
}
$root = sys_get_temp_dir() . '/qingmo-test-' . bin2hex(random_bytes(8));
mkdir($root, 0700, true);
$processes = [];
try {
    for ($i = 0; $i < 12; $i++) {
        $processes[] = proc_open([PHP_BINARY, __FILE__, 'worker', $root], [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR], $pipes);
        fclose($pipes[0]);
    }
    foreach ($processes as $p) {
        if (proc_close($p) !== 0) throw new RuntimeException('Worker failed');
    }
    $p = proc_open([PHP_BINARY, __FILE__, 'verify', $root], [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR], $pipes);
    fclose($pipes[0]);
    if (proc_close($p) !== 0) throw new RuntimeException('Verification failed');
} finally {
    // Delete only the unique test directory created above.
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    rmdir($root);
}
