<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$user = require_user();
$permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
if (!in_array(($user['role'] ?? ''), ['admin','boss'], true) && !array_intersect(['processos','contabilidade'], $permissions)) {
    json_response(['ok'=>false,'error'=>'Sem permissão para alterar cheques.'],403);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_response(['ok'=>false,'error'=>'Método não permitido.'],405);
$input = json_input();
verify_csrf($input);
$processId = trim((string)($input['processId'] ?? ''));
$stockId = trim((string)($input['stockId'] ?? ''));
if ($processId === '') json_response(['ok'=>false,'error'=>'Processo inválido.'],422);

$queueLock = (int)$pdo->query("SELECT GET_LOCK('emotogreen_pending_cheque_emails', 5)")->fetchColumn() === 1;
if (!$queueLock) json_response(['ok'=>false,'error'=>'O envio de cheques está em processamento. Tente novamente dentro de alguns segundos.'],503);
register_shutdown_function(static function () use ($pdo): void {
    try { $pdo->query("SELECT RELEASE_LOCK('emotogreen_pending_cheque_emails')"); }
    catch (Throwable) { /* A ligação pode já estar encerrada. */ }
});

$pdo->beginTransaction();
try {
    $readSetting = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1 FOR UPDATE');
    $readSetting->execute(['state_processes']);
    $processes = json_decode((string)($readSetting->fetchColumn() ?: '[]'), true);
    $processes = is_array($processes) ? $processes : [];
    $readSetting->execute(['state_stockBikes']);
    $stock = json_decode((string)($readSetting->fetchColumn() ?: '[]'), true);
    $stock = is_array($stock) ? $stock : [];
    $readSetting->execute(['state_chequeEmailQueue']);
    $queue = json_decode((string)($readSetting->fetchColumn() ?: '[]'), true);
    $queue = is_array($queue) ? $queue : [];

    $process = null;
    foreach ($processes as $candidate) {
        if ((string)($candidate['id'] ?? '') === $processId) { $process = $candidate; break; }
    }
    if (!$process) {
        $pdo->rollBack();
        json_response(['ok'=>false,'error'=>'Processo não encontrado.'],404);
    }

    $bike = null;
    $bikeIndex = null;
    foreach ($stock as $index => $candidate) {
        $sameStock = $stockId !== '' && (string)($candidate['id'] ?? '') === $stockId;
        $linkedByProcessId = (string)($candidate['processId'] ?? '') === $processId;
        $linkedByProcessUid = !empty($process['uid']) && (string)($candidate['processUid'] ?? '') === (string)$process['uid'];
        $linkedByStock = (string)($process['stockBikeId'] ?? '') !== '' && (string)($candidate['id'] ?? '') === (string)$process['stockBikeId'];
        $isLinked = $linkedByProcessId || $linkedByProcessUid || $linkedByStock;
        if (($stockId !== '' && $sameStock && $isLinked) || ($stockId === '' && $isLinked)) {
            $bike = $candidate;
            $bikeIndex = $index;
            break;
        }
    }
    if ($bikeIndex === null || empty($bike['chequeNumber'])) {
        $pdo->rollBack();
        json_response(['ok'=>false,'error'=>'Não foi encontrado um cheque da moto associada a este processo.'],422);
    }

    $lisbon = new DateTimeZone('Europe/Lisbon');
    $nextBusinessDay = new DateTimeImmutable('tomorrow', $lisbon);
    while (in_array((int)$nextBusinessDay->format('N'), [6,7], true)) $nextBusinessDay = $nextBusinessDay->modify('+1 day');
    $dueDate = $nextBusinessDay->format('Y-m-d');
    $updatedAt = (new DateTimeImmutable('now', $lisbon))->format(DateTimeInterface::ATOM);
    $stock[$bikeIndex]['chequeDueDate'] = $dueDate;
    $stock[$bikeIndex]['processId'] = $processId;
    if (!empty($process['uid'])) $stock[$bikeIndex]['processUid'] = $process['uid'];
    $stock[$bikeIndex]['chequeCollectionTriggeredAt'] = $updatedAt;
    $stock[$bikeIndex]['chequeStateUpdatedAt'] = $updatedAt;
    $stock[$bikeIndex]['chequeNotificationPending'] = true;

    $alreadyQueued = false;
    foreach ($queue as $queued) {
        if (($queued['status'] ?? '') === 'pending'
            && (string)($queued['stockId'] ?? '') === (string)($bike['id'] ?? '')
            && (string)($queued['processId'] ?? '') === $processId
            && (string)($queued['dueDate'] ?? '') === $dueDate) {
            $alreadyQueued = true;
            break;
        }
    }
    if (!$alreadyQueued) {
        $queue[] = [
            'id'=>bin2hex(random_bytes(16)), 'status'=>'pending',
            'stockId'=>(string)($bike['id'] ?? $stockId), 'processId'=>$processId,
            'chequeNumber'=>(string)$bike['chequeNumber'], 'chequeAmount'=>(float)($bike['chequeAmount'] ?? 0),
            'reference'=>(string)($bike['reference'] ?? $bike['invoiceNumber'] ?? ''),
            'brand'=>(string)($bike['brand'] ?? ''), 'model'=>(string)($bike['model'] ?? ''),
            'client'=>(string)($process['client'] ?? ''), 'chequeTerm'=>(int)($bike['chequeTerm'] ?? 0),
            'dueDate'=>$dueDate, 'createdAt'=>$updatedAt, 'attempts'=>0,
        ];
    }
    $queue = array_slice($queue, -500);

    $save = $pdo->prepare('INSERT INTO app_settings(setting_key,setting_value,updated_by) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)');
    $save->execute(['state_stockBikes', json_encode($stock, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR), $user['id']]);
    $save->execute(['state_chequeEmailQueue', json_encode($queue, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR), $user['id']]);
    try {
        $log = $pdo->prepare("INSERT INTO cheque_notification_logs(stock_id,process_id,cheque_number,notification_type,status,error_message,sent_by,sent_at) VALUES(?,?,?,'early_collection',?,NULL,?,NULL)");
        $log->execute([(string)($bike['id'] ?? $stockId), $processId, $bike['chequeNumber'], $alreadyQueued ? 'already_queued' : 'queued', $user['id']]);
    } catch (Throwable $logError) {
        error_log('E-Motogreen cheque queue log: '.$logError->getMessage());
    }
    $pdo->commit();
    json_response(['ok'=>true,'status'=>$alreadyQueued?'already_queued':'queued','dueDate'=>$dueDate,'chequeStateUpdatedAt'=>$updatedAt]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $error;
}
