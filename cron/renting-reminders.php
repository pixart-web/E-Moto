<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acesso reservado ao Cron.');
}

require dirname(__DIR__) . '/api/bootstrap.php';
require dirname(__DIR__) . '/api/smtp-lib.php';

$isTest = in_array('--test', $argv ?? [], true);
$pickupDate = (new DateTimeImmutable('today'))->modify('+2 days')->format('Y-m-d');
$reportType = $isTest ? 'renting_reminder_test' : 'renting_reminder';
$recipient = 'l.dalogli@motogreen.pt';

if (!$isTest) {
    $alreadySent = $pdo->prepare("SELECT COUNT(*) FROM scheduled_report_runs WHERE report_date = ? AND report_type = 'renting_reminder' AND status = 'sent'");
    $alreadySent->execute([$pickupDate]);
    if ((int)$alreadySent->fetchColumn() > 0) exit("Aviso de renting já enviado para {$pickupDate}.\n");
}

$statement = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'state_testDrives' LIMIT 1");
$statement->execute();
$appointments = json_decode((string)($statement->fetchColumn() ?: '[]'), true);
$appointments = is_array($appointments) ? $appointments : [];
$rentings = array_values(array_filter($appointments, static function ($item) use ($pickupDate): bool {
    return is_array($item)
        && ($item['type'] ?? '') === 'renting'
        && ($item['pickupDate'] ?? $item['date'] ?? '') === $pickupDate;
}));

if (!$rentings) exit("Sem rentings para avisar em {$pickupDate}.\n");

$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$formatDate = static function ($value): string {
    $date = DateTimeImmutable::createFromFormat('Y-m-d', (string)$value);
    return $date ? $date->format('d/m/Y') : (string)$value;
};
$rows = '';
foreach ($rentings as $renting) {
    $rows .= '<tr>'
        . '<td><b>' . $e($renting['name'] ?? '') . '</b></td>'
        . '<td>' . $e($renting['motorcycle'] ?? '') . '</td>'
        . '<td>' . $e($formatDate($renting['pickupDate'] ?? $renting['date'] ?? '')) . '<br><small>' . $e($renting['pickupTime'] ?? 'Hora não indicada') . '</small></td>'
        . '<td>' . $e($formatDate($renting['returnDate'] ?? '')) . '<br><small>' . $e($renting['returnTime'] ?? 'Hora não indicada') . '</small></td>'
        . '<td>' . $e($renting['phone'] ?? $renting['contact'] ?? '') . '<br><small>' . $e($renting['email'] ?? '') . '</small></td>'
        . '</tr>';
}

$label = $formatDate($pickupDate);
$html = '<!doctype html><html><head><meta charset="utf-8"><style>'
    . 'body{font-family:Arial,sans-serif;color:#173027;background:#f4f7f5;padding:24px}.wrap{max-width:900px;margin:auto;background:#fff;border-radius:14px;padding:28px}'
    . 'h1{margin:0;color:#0d563e}p,small{color:#68756e}table{width:100%;border-collapse:collapse;margin-top:24px}th{text-align:left;background:#0d563e;color:#fff;padding:10px;font-size:12px}td{padding:10px;border-bottom:1px solid #e5eae7;font-size:12px;vertical-align:top}'
    . '</style></head><body><div class="wrap"><h1>E-Motogreen · Aviso de Renting</h1><p>Existem <b>' . count($rentings) . '</b> levantamento(s) de renting agendado(s) para daqui a dois dias: <b>' . $e($label) . '</b>.</p>'
    . '<table><thead><tr><th>Cliente</th><th>Moto</th><th>Levantamento</th><th>Entrega</th><th>Contacto</th></tr></thead><tbody>' . $rows . '</tbody></table>'
    . '<p><small>Mensagem automática enviada pelo E-Motogreen.</small></p></div></body></html>';

$status = 'failed';
$error = null;
try {
    smtp_send_html($config, [$recipient], 'E-Motogreen — Renting daqui a 2 dias — ' . $label, $html);
    $status = 'sent';
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

$log = $pdo->prepare('INSERT INTO scheduled_report_runs (report_date, report_type, status, recipients, error_message, sent_at) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status=VALUES(status), recipients=VALUES(recipients), error_message=VALUES(error_message), sent_at=VALUES(sent_at)');
$log->execute([$pickupDate, $reportType, $status, $recipient, $error, $status === 'sent' ? date('Y-m-d H:i:s') : null]);
if ($error !== null) throw new RuntimeException($error);
echo "Aviso de renting enviado para {$recipient} ({$pickupDate}).\n";
