<?php
declare(strict_types=1);

// Compatibilidade com proxies/CDNs que bloqueiam PUT ou inspecionam o JSON funcional.
$wrapper = json_decode(file_get_contents('php://input') ?: '{}', true);
if (is_array($wrapper) && isset($wrapper['encoded'])) {
    $decoded = base64_decode((string)$wrapper['encoded'], true);
    $payload = $decoded === false ? null : json_decode($decoded, true);
    if (!is_array($payload)) {
        http_response_code(422);
        header('Content-Type: application/json; charset=UTF-8');
        exit(json_encode(['ok' => false, 'error' => 'Conteúdo de sincronização inválido.']));
    }
    $GLOBALS['emotogreen_json_input_override'] = $payload;
}
$_SERVER['REQUEST_METHOD'] = 'PUT';
require __DIR__ . '/state.php';
