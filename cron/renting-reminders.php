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
$recipient = 'l.dalogli@motogreen.pt';

$statement = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'state_testDrives' LIMIT 1");
$statement->execute();
$appointments = json_decode((string)($statement->fetchColumn() ?: '[]'), true);
$appointments = is_array($appointments) ? $appointments : [];
$rentings = array_values(array_filter($appointments, static function ($item) use ($pickupDate): bool {
    if (!is_array($item)) return false;
    $type = $item['type'] ?? '';
    $startDate = $type === 'event' ? ($item['eventStartDate'] ?? $item['date'] ?? '') : ($item['pickupDate'] ?? $item['date'] ?? '');
    return in_array($type, ['renting', 'event'], true) && $startDate === $pickupDate;
}));

if (!$isTest && $rentings) {
    $alreadySent = $pdo->prepare("SELECT COUNT(*) FROM scheduled_report_runs WHERE report_date = ? AND report_type = ? AND status = 'sent'");
    $rentings = array_values(array_filter($rentings, static function (array $item) use ($alreadySent, $pickupDate): bool {
        $identity = (string)($item['uid'] ?? $item['id'] ?? '');
        $type = 'agenda_reminder_' . substr(hash('sha256', $identity), 0, 24);
        $alreadySent->execute([$pickupDate, $type]);
        return (int)$alreadySent->fetchColumn() === 0;
    }));
}

if (!$rentings) exit("Sem rentings ou eventos para avisar em {$pickupDate}.\n");

$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$formatDate = static function ($value): string {
    $date = DateTimeImmutable::createFromFormat('Y-m-d', (string)$value);
    return $date ? $date->format('d/m/Y') : (string)$value;
};
$rows = '';
foreach ($rentings as $renting) {
    $isEvent = ($renting['type'] ?? '') === 'event';
    $rows .= '<tr>'
        . '<td><b>' . ($isEvent ? 'Evento' : 'Renting') . '</b></td>'
        . '<td><b>' . $e($isEvent ? ($renting['eventName'] ?? '') : ($renting['name'] ?? '')) . '</b><br><small>' . $e($isEvent ? ($renting['locality'] ?? '') : ($renting['motorcycle'] ?? '')) . '</small></td>'
        . '<td>' . $e($formatDate($isEvent ? ($renting['eventStartDate'] ?? '') : ($renting['pickupDate'] ?? $renting['date'] ?? ''))) . '<br><small>' . $e($isEvent ? ($renting['eventStartTime'] ?? 'Hora não indicada') : ($renting['pickupTime'] ?? 'Hora não indicada')) . '</small></td>'
        . '<td>' . $e($formatDate($isEvent ? ($renting['eventEndDate'] ?? '') : ($renting['returnDate'] ?? ''))) . '<br><small>' . $e($isEvent ? ($renting['eventEndTime'] ?? 'Hora não indicada') : ($renting['returnTime'] ?? 'Hora não indicada')) . '</small></td>'
        . '<td>' . ($isEvent ? '—' : $e($renting['phone'] ?? $renting['contact'] ?? '') . '<br><small>' . $e($renting['email'] ?? '') . '</small>') . '</td>'
        . '</tr>';
}

$label = $formatDate($pickupDate);
$html = '<!doctype html><html><head><meta charset="utf-8"><style>'
    . 'body{font-family:Arial,sans-serif;color:#173027;background:#f4f7f5;padding:24px}.wrap{max-width:900px;margin:auto;background:#fff;border-radius:14px;padding:28px}'
    . 'h1{margin:0;color:#0d563e}p,small{color:#68756e}table{width:100%;border-collapse:collapse;margin-top:24px}th{text-align:left;background:#0d563e;color:#fff;padding:10px;font-size:12px}td{padding:10px;border-bottom:1px solid #e5eae7;font-size:12px;vertical-align:top}'
    . '</style></head><body><div class="wrap"><h1>E-Motogreen · Aviso da Agenda</h1><p>Existem <b>' . count($rentings) . '</b> renting(s) ou evento(s) com início daqui a dois dias: <b>' . $e($label) . '</b>.</p>'
    . '<table><thead><tr><th>Tipo</th><th>Detalhes</th><th>Início</th><th>Fim</th><th>Contacto</th></tr></thead><tbody>' . $rows . '</tbody></table>'
    . '<p><small>Mensagem automática enviada pelo E-Motogreen.</small></p></div></body></html>';

$status = 'failed';
$error = null;
try {
    smtp_send_html($config, [$recipient], 'E-Motogreen — Agenda daqui a 2 dias — ' . $label, $html);
    $status = 'sent';
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

$log = $pdo->prepare('INSERT INTO scheduled_report_runs (report_date, report_type, status, recipients, error_message, sent_at) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status=VALUES(status), recipients=VALUES(recipients), error_message=VALUES(error_message), sent_at=VALUES(sent_at)');
foreach ($rentings as $item) {
    $identity = (string)($item['uid'] ?? $item['id'] ?? '');
    $reportType = ($isTest ? 'agenda_test_' : 'agenda_reminder_') . substr(hash('sha256', $identity), 0, 24);
    $log->execute([$pickupDate, $reportType, $status, $recipient, $error, $status === 'sent' ? date('Y-m-d H:i:s') : null]);
}
if ($error !== null) throw new RuntimeException($error);
echo "Aviso da Agenda enviado para {$recipient} ({$pickupDate}).\n";
