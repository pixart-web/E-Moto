<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acesso reservado ao Cron.');
}

require dirname(__DIR__) . '/api/bootstrap.php';

function smtpRead($socket, array $codes): string
{
    $response = '';
    do {
        $line = fgets($socket, 515);
        if ($line === false) throw new RuntimeException('O servidor SMTP terminou a ligação.');
        $response .= $line;
    } while (isset($line[3]) && $line[3] === '-');
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $codes, true)) throw new RuntimeException('SMTP: ' . trim($response));
    return $response;
}

function smtpCommand($socket, string $command, array $codes): string
{
    if (fwrite($socket, $command . "\r\n") === false) throw new RuntimeException('Não foi possível escrever na ligação SMTP.');
    return smtpRead($socket, $codes);
}

function smtpSend(array $smtp, array $recipients, string $subject, string $html): void
{
    foreach (['host','port','username','password','from_email'] as $key) {
        if (empty($smtp[$key]) || str_starts_with((string)$smtp[$key], 'COLOCAR_')) throw new RuntimeException("Configuração SMTP em falta: {$key}.");
    }
    $host = (($smtp['encryption'] ?? 'ssl') === 'ssl' ? 'ssl://' : '') . $smtp['host'];
    $socket = stream_socket_client($host . ':' . (int)$smtp['port'], $errorNumber, $errorMessage, 25, STREAM_CLIENT_CONNECT);
    if (!$socket) throw new RuntimeException("Falha na ligação SMTP ({$errorNumber}): {$errorMessage}");
    stream_set_timeout($socket, 25);
    smtpRead($socket, [220]);
    $hostname = gethostname() ?: 'e-moto.motogreen.pt';
    smtpCommand($socket, 'EHLO ' . $hostname, [250]);
    smtpCommand($socket, 'AUTH LOGIN', [334]);
    smtpCommand($socket, base64_encode((string)$smtp['username']), [334]);
    smtpCommand($socket, base64_encode((string)$smtp['password']), [235]);
    smtpCommand($socket, 'MAIL FROM:<' . $smtp['from_email'] . '>', [250]);
    foreach ($recipients as $recipient) smtpCommand($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
    smtpCommand($socket, 'DATA', [354]);
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = [
        'Date: ' . date(DATE_RFC2822),
        'From: ' . ($smtp['from_name'] ?? 'E-Motogreen') . ' <' . $smtp['from_email'] . '>',
        'To: ' . implode(', ', $recipients),
        'Subject: ' . $encodedSubject,
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@motogreen.pt>',
    ];
    $message = implode("\r\n", $headers) . "\r\n\r\n" . preg_replace('/(?m)^\./', '..', $html);
    fwrite($socket, $message . "\r\n.\r\n");
    smtpRead($socket, [250]);
    smtpCommand($socket, 'QUIT', [221]);
    fclose($socket);
}

$isTest = in_array('--test', $argv ?? [], true);
$lisbon = new DateTimeZone('Europe/Lisbon');
$generatedAt = new DateTimeImmutable('now', $lisbon);
$reportDate = $generatedAt->modify('+1 day')->format('Y-m-d');
if (!$isTest) {
    $alreadySent = $pdo->prepare("SELECT COUNT(*) FROM scheduled_report_runs WHERE report_date = ? AND report_type = 'daily_overview' AND status = 'sent'");
    $alreadySent->execute([$reportDate]);
    if ((int)$alreadySent->fetchColumn() > 0) exit("Relatório já enviado para {$reportDate}.\n");
}

$statement = $pdo->query("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('state_bookings','state_officeNotes','state_processes','state_stockBikes','state_clients')");
$state = [];
foreach ($statement->fetchAll() as $row) $state[substr($row['setting_key'], 6)] = json_decode((string)$row['setting_value'], true) ?: [];
$bookings = array_values(array_filter($state['bookings'] ?? [], static fn($b) => ($b['date'] ?? '') === $reportDate));
usort($bookings, static fn($a, $b) => strcmp((string)($a['time'] ?? ''), (string)($b['time'] ?? '')));
$officeNotes = array_values(array_filter($state['officeNotes'] ?? [], static fn($note) => ($note['date'] ?? '') === $reportDate));
$processes = array_values(array_filter($state['processes'] ?? [], static fn($p) => !in_array($p['status'] ?? '', ['Entregue','Cancelado'], true)));
$orderedBikes = array_values(array_filter($state['stockBikes'] ?? [], static fn($bike) => in_array($bike['status'] ?? '', ['Encomendada','Em transporte'], true)));
$recipients = $pdo->query('SELECT email FROM email_report_recipients WHERE active = 1 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
if (!$recipients) throw new RuntimeException('Não existem destinatários ativos.');

$e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$bookingRows = $bookings ? implode('', array_map(static fn($b) => '<tr><td><b>'.$e($b['time']??'').'</b></td><td>'.$e($b['client']??'').'<br><small>'.$e($b['phone']??'').'</small></td><td>'.$e($b['bike']??'').'</td><td>'.$e($b['service']??'').'</td><td>'.$e($b['technician']??'').'</td></tr>', $bookings)) : '<tr><td colspan="5">Sem marcações para o dia seguinte.</td></tr>';
$officeNoteRows = $officeNotes ? implode('', array_map(static fn($note) => '<tr><td>'.$e($note['text']??'').'</td><td>'.$e($note['createdBy']??'').'</td></tr>', $officeNotes)) : '<tr><td colspan="2">Sem notas para este dia.</td></tr>';
$processRows = $processes ? implode('', array_map(static function($p) use ($e) { $total=array_key_exists('basePrice',$p)&&is_numeric($p['basePrice'])?(float)$p['basePrice']+(float)($p['accessories']??0):(float)($p['price']??0); $missing=max(0,$total-(float)($p['paid']??0)-(float)($p['tradeInValue']??0)); return '<tr><td><b>'.$e($p['id']??'').'</b><br><small>'.$e($p['client']??'').'</small></td><td>'.$e($p['bike']??'').'</td><td>'.$e($p['status']??'').'</td><td>'.number_format($missing,2,',','.').' €</td><td>'.$e($p['owner']??'').'</td></tr>'; }, $processes)) : '<tr><td colspan="5">Sem processos ativos.</td></tr>';
$stockRows = $orderedBikes ? implode('', array_map(static function($bike) use ($e, $processes, $state) { $process = null; foreach ($processes as $candidate) if (($candidate['id'] ?? null) === ($bike['processId'] ?? null)) { $process = $candidate; break; } $client=null; foreach ($state['clients']??[] as $candidate) if ((string)($candidate['id']??'')===(string)($bike['clientId']??'')) {$client=$candidate;break;} $destination = $process ? 'Cliente: '.($process['client'] ?? '').' · Processo '.($process['id'] ?? '') : ($client ? 'Cliente: '.($client['name']??'') : 'Loja / sem cliente'); $delivery=!empty($bike['expectedDeliveryDate'])?(new DateTimeImmutable((string)$bike['expectedDeliveryDate']))->format('d/m/Y'):'Por definir'; return '<tr><td><b>'.$e(($bike['brand']??'').' '.($bike['model']??'')).'</b><br><small>'.$e($bike['reference']??'').'</small></td><td>'.$e($bike['color']??'').'</td><td>'.$e($bike['status']??'').'<br><small>Entrega prevista: '.$e($delivery).'</small></td><td>'.$e($destination).'</td></tr>'; }, $orderedBikes)) : '<tr><td colspan="4">Sem motos encomendadas ou em transporte.</td></tr>';
$label = (new DateTimeImmutable($reportDate))->format('d/m/Y');
$html = '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:Arial,sans-serif;color:#173027;background:#f4f7f5;padding:24px}.wrap{max-width:1000px;margin:auto;background:#fff;border-radius:14px;padding:28px}h1{margin:0;color:#0d563e}h2{margin-top:30px;font-size:18px}p,small{color:#68756e}table{width:100%;border-collapse:collapse}th{text-align:left;background:#0d563e;color:#fff;padding:10px;font-size:12px}td{padding:10px;border-bottom:1px solid #e5eae7;font-size:12px}.notes th{background:#8a6500}.notes td:first-child{white-space:pre-wrap;background:#fff9df}</style></head><body><div class="wrap"><h1>E-Motogreen · Plano do dia seguinte</h1><p>Enviado na véspera, em <b>'.$e($generatedAt->format('d/m/Y H:i')).'</b>, para preparar a oficina de <b>'.$label.'</b>.</p><h2>Agenda da oficina — '.$label.'</h2><table><thead><tr><th>Hora</th><th>Cliente</th><th>Moto</th><th>Serviço</th><th>Responsável</th></tr></thead><tbody>'.$bookingRows.'</tbody></table><h2>Notas para a Oficina — '.$label.'</h2><table class="notes"><thead><tr><th>Detalhes para o mecânico</th><th>Registado por</th></tr></thead><tbody>'.$officeNoteRows.'</tbody></table><h2>Processos de motos</h2><table><thead><tr><th>Processo / Cliente</th><th>Moto</th><th>Estado</th><th>Em falta</th><th>Responsável</th></tr></thead><tbody>'.$processRows.'</tbody></table><h2>Motos encomendadas</h2><table><thead><tr><th>Moto / Referência</th><th>Cor</th><th>Estado</th><th>Destino</th></tr></thead><tbody>'.$stockRows.'</tbody></table><p><small>Mensagem automática enviada pelo E-Motogreen.</small></p></div></body></html>';

$status = 'failed'; $error = null;
try {
    smtpSend($config['smtp'] ?? [], $recipients, 'E-Motogreen — Plano da Oficina para amanhã — '.$label, $html);
    $status = 'sent';
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}
$runType = $isTest ? 'daily_overview_test' : 'daily_overview';
$log = $pdo->prepare('INSERT INTO scheduled_report_runs (report_date, report_type, status, recipients, error_message, sent_at) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status=VALUES(status), recipients=VALUES(recipients), error_message=VALUES(error_message), sent_at=VALUES(sent_at)');
$log->execute([$reportDate, $runType, $status, implode(',', $recipients), $error, $status === 'sent' ? date('Y-m-d H:i:s') : null]);
if ($error !== null) throw new RuntimeException($error);
echo ($isTest ? 'Email de teste' : 'Relatório') . ' enviado para ' . implode(', ', $recipients) . "\n";
