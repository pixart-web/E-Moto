<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acesso reservado ao Cron.');
}

require dirname(__DIR__) . '/api/bootstrap.php';
require dirname(__DIR__) . '/api/smtp-lib.php';

$locked = (int)$pdo->query("SELECT GET_LOCK('emotogreen_pending_cheque_emails', 0)")->fetchColumn() === 1;
if (!$locked) exit("Outro envio de cheques ainda está em execução.\n");

try {
    $rows = $pdo->query("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('state_processes','state_stockBikes')")->fetchAll();
    $state = [];
    foreach ($rows as $row) $state[substr((string)$row['setting_key'], 6)] = json_decode((string)$row['setting_value'], true) ?: [];
    $processes = $state['processes'] ?? [];
    $bikes = $state['stockBikes'] ?? [];
    $queueStatement = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key='state_chequeEmailQueue' LIMIT 1");
    $queueStatement->execute();
    $queue = json_decode((string)($queueStatement->fetchColumn() ?: '[]'), true);
    $queue = is_array($queue) ? $queue : [];
    $saveQueue = $pdo->prepare("INSERT INTO app_settings(setting_key,setting_value) VALUES('state_chequeEmailQueue',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    $save = $pdo->prepare("INSERT INTO app_settings(setting_key,setting_value) VALUES('state_stockBikes',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    $e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $sent = 0; $failed = 0;

    foreach ($queue as $queueIndex => $job) {
        if (($job['status'] ?? '') !== 'pending') continue;
        $lastAttempt = !empty($job['lastAttemptAt']) ? strtotime((string)$job['lastAttemptAt']) : false;
        if ($lastAttempt && $lastAttempt > time() - 300) continue;
        $due = !empty($job['dueDate']) ? new DateTimeImmutable((string)$job['dueDate']) : new DateTimeImmutable('tomorrow');
        $subject = 'Cheque ' . ($job['chequeNumber'] ?? '') . ' a vencer no dia útil seguinte';
        $html = '<html><body style="font-family:Arial,sans-serif;color:#173027"><h2>Cobrança de cheque a vencer</h2><p>Foi pedida a matrícula. O cheque número <b>'.$e($job['chequeNumber']??'').'</b>, associado à encomenda <b>'.$e($job['reference']??'').'</b>, vai vencer no dia útil seguinte: <b>'.$e($due->format('d/m/Y')).'</b>.</p><p><b>Valor:</b> '.$e(number_format((float)($job['chequeAmount']??0),2,',','.')).' €<br><b>Moto:</b> '.$e(trim(($job['brand']??'').' '.($job['model']??''))).'<br><b>Cliente:</b> '.$e($job['client']??'').'<br><b>Processo:</b> '.$e($job['processId']??'').'<br><b>Prazo original:</b> '.$e($job['chequeTerm']??0).' dias<br><b>Nova data de vencimento:</b> '.$e($due->format('d/m/Y')).'</p><p>Na Contabilidade, o cheque passa para o estado <b>A vencer</b>. Depois da data indicada, passa automaticamente para <b>Vencido</b>.</p></body></html>';
        $queue[$queueIndex]['lastAttemptAt'] = date(DATE_ATOM);
        $queue[$queueIndex]['attempts'] = (int)($job['attempts'] ?? 0) + 1;
        try {
            smtp_send_html($config, ['contabilidade@motogreen.pt'], $subject, $html);
            $queue[$queueIndex]['status'] = 'sent';
            $queue[$queueIndex]['sentAt'] = date(DATE_ATOM);
            unset($queue[$queueIndex]['error']);
            foreach ($bikes as $bikeIndex => $queuedBike) {
                if ((string)($queuedBike['id'] ?? '') !== (string)($job['stockId'] ?? '')) continue;
                $bikes[$bikeIndex]['chequeCollectedEarly'] = true;
                $bikes[$bikeIndex]['chequeNotificationPending'] = false;
                $bikes[$bikeIndex]['chequeNotificationSentAt'] = date(DATE_ATOM);
                unset($bikes[$bikeIndex]['chequeNotificationError']);
                break;
            }
            $save->execute([json_encode($bikes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
            $sent++;
        } catch (Throwable $exception) {
            $queue[$queueIndex]['error'] = $exception->getMessage();
            $failed++;
        }
        $saveQueue->execute([json_encode($queue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
    }

    foreach ($bikes as $index => $bike) {
        $hasQueueJob = false;
        foreach ($queue as $job) {
            if ((string)($job['stockId'] ?? '') === (string)($bike['id'] ?? '') && (string)($job['chequeNumber'] ?? '') === (string)($bike['chequeNumber'] ?? '')) { $hasQueueJob = true; break; }
        }
        if ($hasQueueJob) continue;
        if (empty($bike['chequeNotificationPending']) || empty($bike['chequeNumber'])) continue;
        $lastAttempt = !empty($bike['chequeNotificationLastAttemptAt']) ? strtotime((string)$bike['chequeNotificationLastAttemptAt']) : false;
        if ($lastAttempt && $lastAttempt > time() - 300) continue;
        $process = null;
        foreach ($processes as $candidate) {
            if ((string)($candidate['id'] ?? '') === (string)($bike['processId'] ?? '')) { $process = $candidate; break; }
        }
        $due = !empty($bike['chequeDueDate']) ? new DateTimeImmutable((string)$bike['chequeDueDate']) : new DateTimeImmutable('tomorrow');
        $processId = (string)($process['id'] ?? $bike['processId'] ?? '');
        $subject = 'Cheque ' . $bike['chequeNumber'] . ' a vencer no dia útil seguinte';
        $html = '<html><body style="font-family:Arial,sans-serif;color:#173027"><h2>Cobrança de cheque a vencer</h2><p>Foi pedida a matrícula. O cheque número <b>'.$e($bike['chequeNumber']).'</b>, associado à encomenda <b>'.$e($bike['reference']??'').'</b>, vai vencer no dia útil seguinte: <b>'.$e($due->format('d/m/Y')).'</b>.</p><p><b>Moto:</b> '.$e(($bike['brand']??'').' '.($bike['model']??'')).'<br><b>Cliente:</b> '.$e($process['client']??'').'<br><b>Processo:</b> '.$e($processId).'<br><b>Prazo original:</b> '.$e($bike['chequeTerm']??0).' dias<br><b>Nova data de vencimento:</b> '.$e($due->format('d/m/Y')).'</p><p>Na Contabilidade, o cheque passa para o estado <b>A vencer</b>. Depois da data indicada, passa automaticamente para <b>Vencido</b>.</p></body></html>';
        $bikes[$index]['chequeNotificationLastAttemptAt'] = date(DATE_ATOM);
        try {
            smtp_send_html($config, ['contabilidade@motogreen.pt'], $subject, $html);
            $bikes[$index]['chequeCollectedEarly'] = true;
            $bikes[$index]['chequeNotificationPending'] = false;
            $bikes[$index]['chequeNotificationSentAt'] = date(DATE_ATOM);
            unset($bikes[$index]['chequeNotificationError']);
            $status = 'sent'; $error = null; $sent++;
        } catch (Throwable $exception) {
            $bikes[$index]['chequeCollectedEarly'] = false;
            $bikes[$index]['chequeNotificationPending'] = true;
            $bikes[$index]['chequeNotificationError'] = $exception->getMessage();
            $status = 'failed'; $error = $exception->getMessage(); $failed++;
        }
        $save->execute([json_encode($bikes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
        try {
            $log = $pdo->prepare("INSERT INTO cheque_notification_logs(stock_id,process_id,cheque_number,notification_type,status,error_message,sent_by,sent_at) VALUES(?,?,?,'early_collection',?,?,NULL,?)");
            $log->execute([(string)($bike['id']??''), $processId, (string)$bike['chequeNumber'], $status, $error, $status === 'sent' ? date('Y-m-d H:i:s') : null]);
        } catch (Throwable $logError) { error_log('E-Motogreen cheque cron log: '.$logError->getMessage()); }
    }

    echo "Avisos de cheques enviados: {$sent}; falhados: {$failed}.\n";
} finally {
    $pdo->query("SELECT RELEASE_LOCK('emotogreen_pending_cheque_emails')");
}
