<?php
declare(strict_types=1);

function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}
function json_input(): array {
    if (isset($GLOBALS['emotogreen_json_input_override']) && is_array($GLOBALS['emotogreen_json_input_override'])) {
        return $GLOBALS['emotogreen_json_input_override'];
    }
    try { $input = json_decode(file_get_contents('php://input') ?: '{}', true, 512, JSON_THROW_ON_ERROR); }
    catch (JsonException) { json_response(['ok'=>false,'error'=>'JSON inválido.'],400); }
    if (!is_array($input)) json_response(['ok'=>false,'error'=>'Pedido inválido.'],400);
    return $input;
}
set_exception_handler(static function(Throwable $error): void {
    error_log('E-Motogreen: '.$error->getMessage());
    if (PHP_SAPI === 'cli') { fwrite(STDERR, "Erro: ".$error->getMessage()."\n"); exit(1); }
    json_response(['ok'=>false,'error'=>'Erro interno. Consulte os registos do servidor.'],500);
});
$configPath = dirname(__DIR__).'/private/config.php';
if (!is_file($configPath)) throw new RuntimeException('Crie private/config.php a partir de config.example.php.');
$config = require $configPath;
date_default_timezone_set($config['app']['timezone'] ?? 'Europe/Lisbon');
$db = $config['database'] ?? [];
$pdo = new PDO('mysql:host='.($db['host']??'localhost').';port='.($db['port']??3306).';dbname='.($db['name']??'').';charset=utf8mb4', $db['user']??'', $db['password']??'', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
if (PHP_SAPI !== 'cli') {
    session_name('emotogreen');
    session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax','path'=>'/']);
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function request_session_token(): string {
    $token = $_SERVER['HTTP_X_SESSION_TOKEN'] ?? '';
    return is_string($token) ? trim($token) : '';
}
function token_user(): ?array {
    global $pdo;
    $token = request_session_token();
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    $query = $pdo->prepare('SELECT u.id,u.name,u.username,u.email,u.role,u.permissions,u.active FROM user_sessions s JOIN users u ON u.id=s.user_id WHERE s.token_hash=? AND s.expires_at>NOW() LIMIT 1');
    $query->execute([hash('sha256', $token)]);
    $user = $query->fetch();
    if (!$user || !$user['active']) return null;
    $user['id'] = (int)$user['id'];
    $user['permissions'] = json_decode($user['permissions'] ?? '[]', true) ?: [];
    $pdo->prepare('UPDATE user_sessions SET last_used_at=NOW() WHERE token_hash=?')->execute([hash('sha256', $token)]);
    return $user;
}
function verify_csrf(array $input = []): void {
    if (token_user() !== null) return;
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'],$token)) json_response(['ok'=>false,'error'=>'Sessão inválida. Volte a iniciar sessão.'],403);
}
function require_user(): array {
    global $pdo;
    $tokenUser = token_user();
    if ($tokenUser !== null) return $tokenUser;
    if (empty($_SESSION['user']['id'])) json_response(['ok'=>false,'error'=>'Inicie sessão.'],401);
    $query=$pdo->prepare('SELECT id,name,username,email,role,permissions,active FROM users WHERE id=?');
    $query->execute([$_SESSION['user']['id']]);$user=$query->fetch();
    if (!$user || !$user['active']) json_response(['ok'=>false,'error'=>'Sessão expirada.'],401);
    $user['id']=(int)$user['id'];$user['permissions']=json_decode($user['permissions']??'[]',true)?:[];
    $_SESSION['user']=$user;return $user;
}
function require_panel(array $user,string $panel): void {
    if (in_array($user['role'],['admin','boss'],true) || ($user['role']==='employee' && in_array($panel,$user['permissions'],true))) return;
    json_response(['ok'=>false,'error'=>'Sem acesso a este módulo.'],403);
}
