<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$user = require_user();
require_panel($user, 'processos');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$processId = trim((string)($_GET['process'] ?? $_POST['process'] ?? ''));
if ($processId === '') json_response(['ok' => false, 'error' => 'Processo inválido.'], 422);

$storage = dirname(__DIR__) . '/private/documents';
if (!is_dir($storage) && !mkdir($storage, 0700, true) && !is_dir($storage)) throw new RuntimeException('Não foi possível criar a pasta segura de documentos.');

if ($method === 'GET' && isset($_GET['download'])) {
    $id = (int)$_GET['download'];
    $statement = $pdo->prepare('SELECT id, original_name, stored_name, mime_type, file_size FROM process_documents WHERE id = ? AND process_id = ? LIMIT 1');
    $statement->execute([$id, $processId]);
    $document = $statement->fetch();
    $file = $document ? $storage . '/' . basename((string)$document['stored_name']) : '';
    if (!$document || !is_file($file)) json_response(['ok' => false, 'error' => 'Documento não encontrado.'], 404);
    header('Content-Type: ' . $document['mime_type']);
    header('Content-Length: ' . (string)$document['file_size']);
    header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode((string)$document['original_name']));
    header('X-Content-Type-Options: nosniff');
    readfile($file);
    exit;
}

if ($method === 'GET') {
    $statement = $pdo->prepare('SELECT id, original_name AS name, mime_type AS type, file_size AS size, created_at AS created FROM process_documents WHERE process_id = ? ORDER BY created_at DESC, id DESC');
    $statement->execute([$processId]);
    json_response(['ok' => true, 'documents' => $statement->fetchAll()]);
}

if ($method === 'POST') {
    verify_csrf($_POST);
    $files = $_FILES['files'] ?? null;
    if (!$files || !is_array($files['name'] ?? null)) json_response(['ok' => false, 'error' => 'Selecione pelo menos um documento.'], 422);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $created = 0;
    foreach ($files['name'] as $index => $originalName) {
        $error = (int)($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        $temporary = (string)($files['tmp_name'][$index] ?? '');
        $size = (int)($files['size'][$index] ?? 0);
        if ($error !== UPLOAD_ERR_OK || $size < 1 || $size > 5 * 1024 * 1024 || !is_uploaded_file($temporary)) continue;
        $mime = (string)$finfo->file($temporary);
        if (!isset($allowed[$mime])) continue;
        $storedName = bin2hex(random_bytes(24)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($temporary, $storage . '/' . $storedName)) throw new RuntimeException('Não foi possível guardar o documento.');
        $safeName = mb_substr(basename((string)$originalName), 0, 255);
        $statement = $pdo->prepare('INSERT INTO process_documents (process_id, original_name, stored_name, mime_type, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)');
        try {
            $statement->execute([$processId, $safeName, $storedName, $mime, $size, $user['id']]);
            $created++;
        } catch (Throwable $error) {
            @unlink($storage . '/' . $storedName);
            throw $error;
        }
    }
    json_response(['ok' => true, 'created' => $created]);
}

json_response(['ok' => false, 'error' => 'Método não permitido.'], 405);
