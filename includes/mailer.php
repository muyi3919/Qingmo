<?php
/**
 * 轻量 SMTP 客户端（无依赖）与统一发信封装
 * 支持：ssl / tls(STARTTLS) / 明文，AUTH LOGIN / PLAIN，text + html 双部分
 */

/**
 * 极简 SMTP 客户端
 */
class QmSmtp {
    private $host;
    private $port;
    private $secure;   // ssl | tls | none
    private $user;
    private $pass;
    private $timeout;
    private $conn = null;
    public $lastError = '';
    public $lastReply = '';

    public function __construct($host, $port = 587, $secure = 'tls', $user = '', $pass = '', $timeout = 15) {
        $this->host = $host;
        $this->port = (int)$port;
        $this->secure = $secure;
        $this->user = $user;
        $this->pass = $pass;
        $this->timeout = (int)$timeout;
    }

    private function readReply($expect = null) {
        $lines = [];
        while (($line = fgets($this->conn, 8192)) !== false) {
            $lines[] = trim($line);
            if (strlen(trim($line)) >= 4 && trim($line)[3] === ' ') break; // 最后一行 "250 ..."
        }
        $code = $lines ? (int)substr($lines[count($lines) - 1], 0, 3) : 0;
        $this->lastReply = implode("\n", $lines);
        if ($expect !== null) {
            $expects = is_array($expect) ? $expect : [$expect];
            if (!in_array($code, $expects, true)) {
                $this->lastError = 'SMTP 期望 ' . implode('/', $expects) . '，收到 ' . $code . '：' . $this->lastReply;
                return false;
            }
        }
        return $lines ? $lines[count($lines) - 1] : '';
    }

    private function send($data) {
        if (!is_resource($this->conn)) {
            $this->lastError = 'SMTP 连接不可用，无法发送命令';
            return false;
        }
        fwrite($this->conn, $data . "\r\n");
        return true;
    }

    public function connect() {
        $scheme = $this->secure === 'ssl' ? 'ssl://' : 'tcp://';
        $ctx = stream_context_create([
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true],
        ]);
        $this->conn = @stream_socket_client(
            $scheme . $this->host . ':' . $this->port,
            $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $ctx
        );
        if (!$this->conn) {
            $this->lastError = '无法连接 SMTP 服务器：' . $errstr;
            return false;
        }
        stream_set_timeout($this->conn, $this->timeout);
        if ($this->readReply(220) === false) return false;

        // EHLO
        $this->send('EHLO ' . (gethostname() ?: 'localhost'));
        if ($this->readReply(250) === false) {
            // 回退 HELO
            $this->send('HELO ' . (gethostname() ?: 'localhost'));
            if ($this->readReply(250) === false) return false;
        }

        // STARTTLS 升级
        if ($this->secure === 'tls') {
            $this->send('STARTTLS');
            if ($this->readReply(220) === false) return false;
            $ok = @stream_socket_enable_crypto($this->conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$ok) {
                $this->lastError = 'STARTTLS 加密握手失败';
                return false;
            }
            $this->send('EHLO ' . (gethostname() ?: 'localhost'));
            if ($this->readReply(250) === false) return false;
        }
        return true;
    }

    public function login() {
        if (!is_resource($this->conn)) {
            $this->lastError = 'SMTP 尚未连接，无法登录';
            return false;
        }
        if ($this->user === '') return true; // 无需认证
        $this->send('AUTH LOGIN');
        if ($this->readReply(334) === false) return false;
        $this->send(base64_encode($this->user));
        if ($this->readReply(334) === false) return false;
        $this->send(base64_encode($this->pass));
        if ($this->readReply(235) === false) return false;
        return true;
    }

    public function sendMail($from, $fromName, $to, $subject, $textBody, $htmlBody = null) {
        if ($fromName !== '') {
            $fromHeader = '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>';
        } else {
            $fromHeader = '<' . $from . '>';
        }
        if (is_array($to)) $to = array_values($to);

        $this->send('MAIL FROM:<' . $from . '>');
        if ($this->readReply(250) === false) return false;
        foreach ((array)$to as $rcpt) {
            $this->send('RCPT TO:<' . $rcpt . '>');
            if ($this->readReply([250, 251]) === false) return false;
        }
        $this->send('DATA');
        if ($this->readReply(354) === false) return false;

        $boundary = 'qm_' . bin2hex(random_bytes(8));
        $msg = 'From: ' . $fromHeader . "\r\n";
        $msg .= 'To: ' . implode(',', array_map(function ($t) { return '<' . $t . '>'; }, (array)$to)) . "\r\n";
        $msg .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
        $msg .= 'Date: ' . date('r') . "\r\n";
        $msg .= 'MIME-Version: 1.0' . "\r\n";
        $msg .= 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . "\r\n";
        $msg .= "X-Mailer: Qingmo/2.0\r\n\r\n";

        $msg .= '--' . $boundary . "\r\n";
        $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $msg .= chunk_split(base64_encode($textBody), 76, "\r\n") . "\r\n";

        if ($htmlBody !== null) {
            $msg .= '--' . $boundary . "\r\n";
            $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
            $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $msg .= chunk_split(base64_encode($htmlBody), 76, "\r\n") . "\r\n";
        }
        $msg .= '--' . $boundary . "--\r\n";

        // 逐行发送，行首 "." 需转义为 ".."
        $raw = preg_replace("/\r\n\./", "\r\n..", $msg);
        $raw = str_replace("\n", "\r\n", $raw);
        // 确保以 . 结束
        if (substr($raw, -1) !== "\n") $raw .= "\r\n";
        fwrite($this->conn, $raw . "\r\n.\r\n");
        if ($this->readReply(250) === false) return false;

        return true;
    }

    public function quit() {
        if ($this->conn) {
            @fwrite($this->conn, "QUIT\r\n");
            @fclose($this->conn);
        }
    }
}

/**
 * 统一发送邮件
 * 优先使用 SMTP 配置（mailer_mode = smtp 且已填 host）；否则回退 PHP mail()
 *
 * @param string|array $to      收件人
 * @param string       $subject 主题
 * @param string       $text    纯文本正文
 * @param string|null  $html    HTML 正文（可选）
 * @return bool 是否成功
 */
function qm_send_mail($to, $subject, $text, $html = null) {
    $mode = get_setting('mailer_mode', 'php');
    $host = trim((string)get_setting('smtp_host', ''));
    $from = trim((string)get_setting('smtp_from', ''));
    if ($from === '') $from = trim((string)get_setting('notify_email', ''));
    if ($from === '') $from = 'no-reply@localhost';
    $fromName = trim((string)get_setting('smtp_from_name', ''));
    if ($fromName === '') $fromName = get_setting('site_title', '本站');

    if ($mode === 'smtp' && $host !== '') {
        $smtp = new QmSmtp(
            $host,
            (int)get_setting('smtp_port', 587),
            (string)get_setting('smtp_secure', 'tls'),
            (string)get_setting('smtp_user', ''),
            (string)get_setting('smtp_pass', '')
        );
        if (!$smtp->connect()) return false;
        if (!$smtp->login()) {
            $smtp->quit();
            return false;
        }
        $ok = $smtp->sendMail($from, $fromName, $to, $subject, $text, $html);
        $smtp->quit();
        return (bool)$ok;
    }

    // 回退 PHP mail()
    if (!function_exists('mail')) return false;
    $subjectEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = 'From: ' . $fromName . ' <' . $from . ">\r\n"
        . 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
        . 'MIME-Version: 1.0' . "\r\n";
    $text = str_replace("\n.", "\n..", $text); // 防邮件注入
    return @mail(is_array($to) ? implode(',', $to) : $to, $subjectEnc, $text, $headers);
}

/**
 * 把纯文本正文转成简单 HTML（换行→<br>、URL→链接），用于邮件
 */
function qm_text_to_html($text) {
    $text = htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('#(https?://[^\s<]+)#', '<a href="$1">$1</a>', $text);
    return nl2br($text);
}
