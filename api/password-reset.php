<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/smtp-lib.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = json_input();

if ($method === 'POST') {
    $username = trim((string)($input['username'] ?? ''));
    $genericMessage = 'Se a conta existir e tiver email associado, receberá as instruções de recuperação.';
    if ($username === '') json_response(['ok' => true, 'message' => $genericMessage]);

    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $limit = $pdo->prepare('SELECT COUNT(*) FROM password_reset_requests WHERE requested_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) AND (request_ip = ? OR LOWER(username) = LOWER(?))');
    $limit->execute([$ip, $username]);
    if ((int)$limit->fetchColumn() >= 5) json_response(['ok' => true, 'message' => $genericMessage]);

    $statement = $pdo->prepare('SELECT id, name, username, email FROM users WHERE LOWER(username) = LOWER(?) AND active = 1 LIMIT 1');
    $statement->execute([$username]);
    $user = $statement->fetch();
    if ($user && filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
        $token = bin2hex(random_bytes(32));
        $pdo->prepare('DELETE FROM password_reset_requests WHERE user_id = ? OR expires_at <= NOW()')->execute([$user['id']]);
        $pdo->prepare('INSERT INTO password_reset_requests (user_id, username, token_hash, request_ip, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 60 MINUTE))')->execute([$user['id'], $user['username'], hash('sha256', $token), $ip]);
        $baseUrl = rtrim((string)($config['app']['url'] ?? 'https://e-moto.motogreen.pt'), '/');
        $link = $baseUrl . '/?reset=' . rawurlencode($token) . '&user=' . rawurlencode((string)$user['username']);
        $html = '<h2>Reposição de password</h2><p>Olá ' . htmlspecialchars((string)$user['name'], ENT_QUOTES, 'UTF-8') . ',</p><p>Foi pedida a reposição da password da sua conta E-Motogreen.</p><p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">Definir uma nova password</a></p><p>Este link é válido durante 60 minutos e só pode ser utilizado uma vez. Se não fez este pedido, ignore este email.</p>';
        smtp_send_html($config, [(string)$user['email']], 'Reposição de password — E-Motogreen', $html);
    }
    json_response(['ok' => true, 'message' => $genericMessage]);
}

if ($method === 'PUT') {
    $username = trim((string)($input['username'] ?? ''));
    $token = trim((string)($input['token'] ?? ''));
    $newPassword = (string)($input['newPassword'] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $token) || strlen($newPassword) < 8) json_response(['ok' => false, 'error' => 'Pedido de recuperação inválido.'], 422);
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('SELECT r.id, r.user_id FROM password_reset_requests r JOIN users u ON u.id = r.user_id WHERE LOWER(r.username) = LOWER(?) AND r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > NOW() AND u.active = 1 LIMIT 1 FOR UPDATE');
        $statement->execute([$username, hash('sha256', $token)]);
        $request = $statement->fetch();
        if (!$request) {
            $pdo->rollBack();
            json_response(['ok' => false, 'error' => 'O link é inválido ou já expirou. Peça uma nova recuperação.'], 422);
        }
        $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?')->execute([password_hash($newPassword, PASSWORD_DEFAULT), $request['user_id']]);
        $pdo->prepare('UPDATE password_reset_requests SET used_at = NOW() WHERE id = ?')->execute([$request['id']]);
        $pdo->prepare('DELETE FROM user_sessions WHERE user_id = ?')->execute([$request['user_id']]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    json_response(['ok' => true, 'message' => 'Password atualizada com sucesso.']);
}

json_response(['ok' => false, 'error' => 'Método não permitido.'], 405);
