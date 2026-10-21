<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_response(['ok' => false, 'error' => 'Método não permitido.'], 405);
}

$user = require_user();
require_panel($user, 'agenda');

$date = trim((string)($_GET['date'] ?? date('Y-m-d')));
$parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
    json_response(['ok' => false, 'error' => 'Data inválida.'], 422);
}

$statement = $pdo->query("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('state_bookings', 'state_officeBlocks', 'state_officeNotes')");
$settings = [];
foreach ($statement->fetchAll() as $row) {
    $decoded = json_decode((string)$row['setting_value'], true);
    $settings[(string)$row['setting_key']] = is_array($decoded) ? $decoded : [];
}

$bookings = [];
foreach ($settings['state_bookings'] ?? [] as $booking) {
    if (!is_array($booking) || (string)($booking['date'] ?? '') !== $date) continue;
    $bookings[] = [
        'id' => $booking['id'] ?? '',
        'date' => $date,
        'time' => (string)($booking['time'] ?? ''),
        'duration' => max(15, min(720, (int)($booking['duration'] ?? 60))),
        'client' => (string)($booking['client'] ?? ''),
        'bike' => (string)($booking['bike'] ?? ''),
        'service' => (string)($booking['service'] ?? ''),
        'technician' => (string)($booking['technician'] ?? ''),
        'completed' => !empty($booking['completed']),
        'scheduledBy' => (string)($booking['scheduledBy'] ?? ''),
    ];
}
usort($bookings, static fn(array $a, array $b): int => strcmp($a['time'], $b['time']));

$blocks = [];
foreach ($settings['state_officeBlocks'] ?? [] as $block) {
    if (!is_array($block) || (string)($block['date'] ?? '') !== $date) continue;
    $allDay = in_array((string)($block['mode'] ?? $block['period'] ?? ''), ['all', 'day'], true);
    $blocks[] = [
        'id' => $block['uid'] ?? $block['id'] ?? '',
        'date' => $date,
        'startTime' => $allDay ? '08:00' : (string)($block['startTime'] ?? '08:00'),
        'endTime' => $allDay ? '20:00' : (string)($block['endTime'] ?? '20:00'),
        'reason' => (string)($block['reason'] ?? 'Agenda indisponível'),
    ];
}

$notes = [];
foreach ($settings['state_officeNotes'] ?? [] as $note) {
    if (!is_array($note) || (string)($note['date'] ?? '') !== $date) continue;
    $notes[] = [
        'id' => $note['uid'] ?? $note['id'] ?? '',
        'text' => (string)($note['text'] ?? ''),
        'createdBy' => (string)($note['createdBy'] ?? ''),
    ];
}

json_response([
    'ok' => true,
    'date' => $date,
    'serverTime' => date(DATE_ATOM),
    'viewer' => ['name' => (string)$user['name']],
    'bookings' => $bookings,
    'blocks' => $blocks,
    'notes' => $notes,
]);
