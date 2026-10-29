<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$user = require_user();
if (!in_array($user['role'], ['admin', 'boss'], true)
    && !in_array('motos', $user['permissions'], true)
    && !in_array('processos', $user['permissions'], true)) {
    json_response(['ok' => false, 'error' => 'Sem acesso à biblioteca de imagens.'], 403);
}

$uploadDirectory = dirname(__DIR__) . '/uploads/bikes';
if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
    throw new RuntimeException('Não foi possível criar a pasta da biblioteca de imagens.');
}

function bike_image_library(string $directory): array
{
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $entries = [];
    foreach (scandir($directory) ?: [] as $name) {
        if ($name === '.' || $name === '..' || $name === '.htaccess') continue;
        $path = $directory . '/' . $name;
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!is_file($path) || !in_array($extension, $allowed, true)) continue;
        $entries[] = [
            'name' => $name,
            'url' => 'uploads/bikes/' . rawurlencode($name),
            'size' => filesize($path) ?: 0,
            'uploadedAt' => date(DATE_ATOM, filemtime($path) ?: time()),
        ];
    }
    usort($entries, static fn(array $a, array $b): int => strcmp($b['uploadedAt'], $a['uploadedAt']));
    return $entries;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_response(['ok' => true, 'images' => bike_image_library($uploadDirectory)]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Método não permitido.'], 405);
}

verify_csrf();
if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
    json_response(['ok' => false, 'error' => 'Selecione uma imagem.'], 422);
}

$file = $_FILES['image'];
$error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($error !== UPLOAD_ERR_OK) {
    $message = match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'A imagem excede o limite permitido pelo servidor.',
        UPLOAD_ERR_NO_FILE => 'Selecione uma imagem.',
        default => 'O upload da imagem falhou.',
    };
    json_response(['ok' => false, 'error' => $message], 422);
}

$temporary = (string)($file['tmp_name'] ?? '');
$size = (int)($file['size'] ?? 0);
if ($size < 1 || $size > 5 * 1024 * 1024) {
    json_response(['ok' => false, 'error' => 'A imagem deve ter no máximo 5 MB.'], 422);
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary) ?: '';
$extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!isset($extensions[$mime]) || @getimagesize($temporary) === false) {
    json_response(['ok' => false, 'error' => 'Formato inválido. Utilize JPG, PNG ou WebP.'], 422);
}

$base = pathinfo((string)($file['name'] ?? 'moto'), PATHINFO_FILENAME);
$base = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base) ?: 'moto';
$base = strtolower(trim((string)preg_replace('/[^a-zA-Z0-9]+/', '-', $base), '-')) ?: 'moto';
$name = substr($base, 0, 70) . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.' . $extensions[$mime];
$destination = $uploadDirectory . '/' . $name;
if (!move_uploaded_file($temporary, $destination)) {
    throw new RuntimeException('Não foi possível guardar a imagem carregada.');
}
chmod($destination, 0644);

json_response([
    'ok' => true,
    'image' => [
        'name' => $name,
        'url' => 'uploads/bikes/' . rawurlencode($name),
        'size' => filesize($destination) ?: $size,
        'uploadedAt' => date(DATE_ATOM),
    ],
    'images' => bike_image_library($uploadDirectory),
], 201);
