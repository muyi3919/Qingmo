<?php
// Local development only: php -S 127.0.0.1:8080 local-router.php
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$path = str_replace('\\', '/', $path);
if (preg_match('#(^|/)(\.|\.\.|data|includes|tests)(/|$)|(^|/)\.[^/]*|^/local-router\.php$#i', $path)) {
    http_response_code(403);
    exit('Forbidden');
}
return false;
