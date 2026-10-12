<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$currentUser = require_user();
if (($currentUser['role'] ?? '') !== 'boss') {
    json_response(['ok' => false, 'error' => 'Acesso reservado ao Boss.'], 403);
}

$users = $pdo->query('SELECT id, name, username, role FROM users WHERE active = 1 ORDER BY name')->fetchAll();
json_response([
    'ok' => true,
    'users' => array_map(static fn(array $user): array => [
        'id' => (int)$user['id'],
        'name' => (string)$user['name'],
        'username' => (string)$user['username'],
        'role' => (string)$user['role'],
    ], $users),
]);
