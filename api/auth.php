<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $tokenUser = token_user();
    if ($tokenUser !== null) json_response(['ok' => true, 'authenticated' => true, 'user' => $tokenUser, 'csrf' => $_SESSION['csrf']]);
    if (empty($_SESSION['user'])) json_response(['ok' => false, 'authenticated' => false], 401);
    $statement = $pdo->prepare('SELECT id, name, username, email, role, permissions, active FROM users WHERE id = ? LIMIT 1');
    $statement->execute([$_SESSION['user']['id']]);
    $freshUser = $statement->fetch();
    if (!$freshUser || !$freshUser['active']) {
        $_SESSION = [];
        json_response(['ok' => false, 'authenticated' => false], 401);
    }
    $_SESSION['user'] = [
        'id' => (int)$freshUser['id'],
        'name' => $freshUser['name'],
        'username' => $freshUser['username'],
        'email' => $freshUser['email'],
        'role' => $freshUser['role'],
        'permissions' => $freshUser['permissions'] ? (json_decode((string)$freshUser['permissions'], true) ?: []) : [],
    ];
    json_response([
        'ok' => true,
        'authenticated' => true,
        'user' => $_SESSION['user'],
        'csrf' => $_SESSION['csrf'],
    ]);
}

if ($method === 'POST') {
    $input = json_input();
    $username = trim((string)($input['username'] ?? ''));
    $password = (string)($input['password'] ?? '');
    $statement = $pdo->prepare('SELECT id, name, username, email, password_hash, role, permissions, active FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1');
    $statement->execute([$username]);
    $user = $statement->fetch();
    if (!$user || !$user['active'] || !password_verify($password, $user['password_hash'])) {
        usleep(250000);
        json_response(['ok' => false, 'error' => 'Utilizador ou palavra-passe incorretos.'], 401);
    }
    session_regenerate_id(true);
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => $user['role'],
        'permissions' => $user['permissions'] ? (json_decode((string)$user['permissions'], true) ?: []) : [],
    ];
    $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
    $sessionToken = bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO user_sessions (user_id, token_hash, expires_at, user_agent, ip_address) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), ?, ?)')->execute([$user['id'], hash('sha256', $sessionToken), substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)]);
    $pdo->prepare('DELETE FROM user_sessions WHERE expires_at <= NOW()')->execute();
    json_response(['ok' => true, 'user' => $_SESSION['user'], 'csrf' => $_SESSION['csrf'], 'sessionToken' => $sessionToken]);
}

if ($method === 'PUT') {
    $input = json_input();
    $user = require_user();
    verify_csrf($input);
    $currentPassword = (string)($input['currentPassword'] ?? '');
    $newPassword = (string)($input['newPassword'] ?? '');
    if (strlen($newPassword) < 8) {
        json_response(['ok' => false, 'error' => 'A nova password deve ter pelo menos 8 caracteres.'], 422);
    }
    $statement = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? AND active = 1 LIMIT 1');
    $statement->execute([$user['id']]);
    $passwordHash = $statement->fetchColumn();
    if (!$passwordHash || !password_verify($currentPassword, (string)$passwordHash)) {
        usleep(250000);
        json_response(['ok' => false, 'error' => 'A password atual está incorreta.'], 403);
    }
    if (password_verify($newPassword, (string)$passwordHash)) {
        json_response(['ok' => false, 'error' => 'A nova password deve ser diferente da atual.'], 422);
    }
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?')->execute([$newHash, $user['id']]);
    json_response(['ok' => true, 'message' => 'Password alterada com sucesso.']);
}

if ($method === 'DELETE') {
    $input = json_input();
    require_user();
    verify_csrf($input);
    $token = request_session_token();
    if ($token !== '') $pdo->prepare('DELETE FROM user_sessions WHERE token_hash = ?')->execute([hash('sha256', $token)]);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
    }
    session_destroy();
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'Método não permitido.'], 405);
