<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$currentUser = require_user();
if (($currentUser['role'] ?? '') !== 'admin') {
    json_response(['ok' => false, 'error' => 'Acesso reservado a administradores.'], 403);
}

$allowedPermissions = ['dashboard','processos','clientes','motos','catalogo','encomendas','agenda','testdrives','financiamentos','equipa'];
$normalizePermissions = static function (mixed $value) use ($allowedPermissions): array {
    if (!is_array($value)) return [];
    return array_values(array_intersect($allowedPermissions, array_map('strval', $value)));
};
$serializeUser = static function (array $user): array {
    return [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => $user['role'],
        'active' => (bool)$user['active'],
        'permissions' => $user['permissions'] ? (json_decode((string)$user['permissions'], true) ?: []) : [],
        'lastLoginAt' => $user['last_login_at'],
    ];
};

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $users = $pdo->query('SELECT id, name, username, email, role, permissions, active, last_login_at FROM users ORDER BY name')->fetchAll();
    json_response(['ok' => true, 'users' => array_map($serializeUser, $users)]);
}

$input = json_input();
verify_csrf($input);
$name = trim((string)($input['name'] ?? ''));
$username = trim((string)($input['username'] ?? ''));
$email = trim((string)($input['email'] ?? ''));
$role = (string)($input['role'] ?? 'employee');
$permissions = in_array($role, ['admin','boss'], true) ? [] : $normalizePermissions($input['permissions'] ?? []);
$password = (string)($input['password'] ?? '');
if ($name === '' || $username === '' || !in_array($role, ['admin','employee','boss'], true)) {
    json_response(['ok' => false, 'error' => 'Preencha corretamente o nome, username e role.'], 422);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['ok' => false, 'error' => 'O email indicado não é válido.'], 422);
}

try {
    if ($method === 'POST') {
        if (strlen($password) < 8) json_response(['ok' => false, 'error' => 'A password deve ter pelo menos 8 caracteres.'], 422);
        $statement = $pdo->prepare('INSERT INTO users (name, username, email, password_hash, role, permissions, active) VALUES (?, ?, ?, ?, ?, ?, 1)');
        $statement->execute([$name, $username, $email ?: null, password_hash($password, PASSWORD_DEFAULT), $role, in_array($role, ['admin','boss'], true) ? null : json_encode($permissions)]);
        json_response(['ok' => true, 'message' => 'Utilizador criado com sucesso.']);
    }
    if ($method === 'PUT') {
        $id = (int)($input['id'] ?? 0);
        $active = !empty($input['active']) ? 1 : 0;
        if ($id < 1) json_response(['ok' => false, 'error' => 'Utilizador inválido.'], 422);
        if ($id === (int)$currentUser['id'] && ($role !== 'admin' || !$active)) {
            json_response(['ok' => false, 'error' => 'Não pode retirar o seu próprio acesso de administrador.'], 422);
        }
        $parameters = [$name, $username, $email ?: null, $role, in_array($role, ['admin','boss'], true) ? null : json_encode($permissions), $active];
        $sql = 'UPDATE users SET name = ?, username = ?, email = ?, role = ?, permissions = ?, active = ?';
        if ($password !== '') {
            if (strlen($password) < 8) json_response(['ok' => false, 'error' => 'A password deve ter pelo menos 8 caracteres.'], 422);
            $sql .= ', password_hash = ?';
            $parameters[] = password_hash($password, PASSWORD_DEFAULT);
        }
        $sql .= ' WHERE id = ?';
        $parameters[] = $id;
        $pdo->prepare($sql)->execute($parameters);
        if ($password !== '') $pdo->prepare('DELETE FROM user_sessions WHERE user_id = ?')->execute([$id]);
        json_response(['ok' => true, 'message' => 'Utilizador atualizado com sucesso.']);
    }
} catch (PDOException $exception) {
    if ((string)$exception->getCode() === '23000') json_response(['ok' => false, 'error' => 'Esse username já está a ser utilizado.'], 409);
    error_log('E-Motogreen admin users failed: ' . $exception->getMessage());
    json_response(['ok' => false, 'error' => 'Não foi possível guardar o utilizador.'], 500);
}

json_response(['ok' => false, 'error' => 'Método não permitido.'], 405);
