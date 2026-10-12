<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acesso reservado ao Cron.');
}

require dirname(__DIR__) . '/api/bootstrap.php';
require dirname(__DIR__) . '/api/smtp-lib.php';

$isTest = in_array('--test', $argv ?? [], true);
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$reportType = $isTest ? 'pending_orders_test' : 'pending_orders';
$recipients = ['l.dalogli@motogreen.pt', 'loja@motogreen.pt'];

if (!$isTest) {
    $alreadySent = $pdo->prepare("SELECT COUNT(*) FROM scheduled_report_runs WHERE report_date = ? AND report_type = 'pending_orders' AND status = 'sent'");
    $alreadySent->execute([$today]);
    if ((int)$alreadySent->fetchColumn() > 0) exit("Relatório de encomendas pendentes já enviado hoje.\n");
}

$statement = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'state_orders' LIMIT 1");
$statement->execute();
$orders = json_decode((string)($statement->fetchColumn() ?: '[]'), true);
$orders = is_array($orders) ? $orders : [];
$orders = array_values(array_filter($orders, static fn($order): bool => ($order['status'] ?? '') !== 'Enviada'));
usort($orders, static fn($a, $b): int => strcmp((string)($a['orderDate'] ?? ''), (string)($b['orderDate'] ?? '')));

$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$money = static fn($value): string => number_format((float)$value, 2, ',', '.') . ' €';
$date = static function ($value): string {
    if (!$value) return 'Não registada';
    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', (string)$value);
    return $parsed ? $parsed->format('d/m/Y') : (string)$value;
};

$rows = '';
foreach ($orders as $order) {
    $rows .= '<tr>'
        . '<td><b>' . $e(($order['number'] ?? '') ?: 'Sem número') . '</b></td>'
        . '<td>' . $e($date($order['orderDate'] ?? '')) . '</td>'
        . '<td>' . $e($order['clientName'] ?? 'Não indicado') . '</td>'
        . '<td><b>' . $e($order['product'] ?? 'Não indicado') . '</b><br><small>Ref.: ' . $e($order['reference'] ?? 'Sem referência') . '</small></td>'
        . '<td>' . $e($order['supplier'] ?? 'Não indicado') . '</td>'
        . '<td>' . $e($order['status'] ?? 'Processar') . '</td>'
        . '<td><b>' . $e($money($order['total'] ?? 0)) . '</b></td>'
        . '<td>' . $e($order['notes'] ?? '') . '</td>'
        . '</tr>';
}
if ($rows === '') $rows = '<tr><td colspan="8">Não existem encomendas pendentes.</td></tr>';

$label = (new DateTimeImmutable('today'))->format('d/m/Y');
$html = '<!doctype html><html><head><meta charset="utf-8"><style>'
    . 'body{font-family:Arial,sans-serif;color:#173027;background:#f4f7f5;padding:24px}.wrap{max-width:1100px;margin:auto;background:#fff;border-radius:14px;padding:28px}'
    . 'h1{margin:0;color:#0d563e}p,small{color:#68756e}table{width:100%;border-collapse:collapse;margin-top:24px}th{text-align:left;background:#0d563e;color:#fff;padding:10px;font-size:12px}td{padding:10px;border-bottom:1px solid #e5eae7;font-size:12px;vertical-align:top}'
    . '</style></head><body><div class="wrap"><h1>E-Motogreen · Encomendas Pendentes</h1><p>Relatório diário de <b>' . $e($label) . '</b> · ' . count($orders) . ' encomenda(s) pendente(s).</p>'
    . '<table><thead><tr><th>Número</th><th>Data</th><th>Cliente</th><th>Produto / Ref.</th><th>Fornecedor</th><th>Estado</th><th>Valor</th><th>Notas</th></tr></thead><tbody>' . $rows . '</tbody></table>'
    . '<p><small>Mensagem automática enviada pelo E-Motogreen.</small></p></div></body></html>';

$status = 'failed';
$error = null;
try {
    smtp_send_html($config, $recipients, 'E-Motogreen — Encomendas Pendentes — ' . $label, $html);
    $status = 'sent';
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

$log = $pdo->prepare('INSERT INTO scheduled_report_runs (report_date, report_type, status, recipients, error_message, sent_at) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status=VALUES(status), recipients=VALUES(recipients), error_message=VALUES(error_message), sent_at=VALUES(sent_at)');
$log->execute([$today, $reportType, $status, implode(',', $recipients), $error, $status === 'sent' ? date('Y-m-d H:i:s') : null]);
if ($error !== null) throw new RuntimeException($error);
echo ($isTest ? 'Email de teste' : 'Relatório') . ' de encomendas pendentes enviado para ' . implode(', ', $recipients) . "\n";
