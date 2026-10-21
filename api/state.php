<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$user = require_user();
$allowed = ['clients', 'processes', 'bookings', 'officeBlocks', 'officeNotes', 'testDrives', 'orders', 'stockBikes', 'absences', 'bikeCatalog'];
$resourceWritePanels = [
    'clients'=>['clientes','processos','agenda'],
    'processes'=>['processos','financiamentos'],
    'bookings'=>['agenda'], 'officeBlocks'=>['agenda'], 'officeNotes'=>['agenda'],
    'testDrives'=>['testdrives'], 'orders'=>['encomendas'],
    'stockBikes'=>['motos','processos','financiamentos'],
    'absences'=>['equipa'], 'bikeCatalog'=>['catalogo'],
];
$resourceReadPanels = [
    'clients'=>['clientes','processos','agenda','motos','financiamentos'],
    'processes'=>['processos','financiamentos','contabilidade','motos'],
    'bookings'=>['agenda','clientes'], 'officeBlocks'=>['agenda'], 'officeNotes'=>['agenda'],
    'testDrives'=>['testdrives'], 'orders'=>['encomendas'],
    'stockBikes'=>['motos','processos','financiamentos','contabilidade'],
    'absences'=>['equipa'], 'bikeCatalog'=>['catalogo','motos','processos'],
];
$userPermissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
$hasResourcePermission = static function (string $resource, array $matrix) use ($user, $userPermissions): bool {
    if (in_array(($user['role'] ?? ''), ['admin','boss'], true)) return true;
    if (($user['role'] ?? '') !== 'employee') return false;
    return (bool)array_intersect($matrix[$resource] ?? [], $userPermissions);
};
$canAccess = static fn(string $resource): bool => $hasResourcePermission($resource, $resourceWritePanels);
$canRead = static fn(string $resource): bool => $hasResourcePermission($resource, $resourceReadPanels);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$readResource = static function (PDO $pdo, string $resource, bool $forUpdate = false): array {
    $sql = 'SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $statement = $pdo->prepare($sql);
    $statement->execute(['state_' . $resource]);
    $decoded = json_decode((string)($statement->fetchColumn() ?: '[]'), true);
    return is_array($decoded) ? $decoded : [];
};
$phoneKey = static fn(mixed $value): string => preg_replace('/^351/', '', preg_replace('/\D+/', '', (string)$value) ?? '') ?? '';
$nameKey = static fn(mixed $value): string => strtolower(trim(preg_replace('/\s+/', ' ', (string)$value) ?? ''));
$freshId = static function (): int {
    return (int)floor(microtime(true) * 1000000) + random_int(0, 99);
};
$freshProcessId = static function (array $records): string {
    $used = [];
    $maximum = 26066;
    foreach ($records as $record) {
        $id = (string)($record['id'] ?? '');
        $used[$id] = true;
        if (preg_match('/^MG-(\d+)$/', $id, $match)) $maximum = max($maximum, (int)$match[1]);
    }
    do { $candidate = 'MG-' . (++$maximum); } while (isset($used[$candidate]));
    return $candidate;
};

if ($method === 'GET') {
    $statement = $pdo->query("SELECT setting_key, setting_value FROM app_settings WHERE setting_key LIKE 'state_%'");
    $state = array_fill_keys($allowed, []);
    $initialized = false;
    foreach ($statement->fetchAll() as $row) {
        $key = substr($row['setting_key'], 6);
        if (in_array($key, $allowed, true)) $initialized = true;
        if (in_array($key, $allowed, true) && $canRead($key)) {
            $decoded = json_decode((string)$row['setting_value'], true);
            $decoded = is_array($decoded) ? $decoded : [];
            if ($key !== 'bikeCatalog') {
                foreach ($decoded as &$record) {
                    if (is_array($record) && empty($record['_version'])) $record['_version'] = 1;
                }
                unset($record);
            }
            if ($key === 'absences' && ($user['role'] ?? '') !== 'boss') {
                $userName = trim((string)($user['name'] ?? ''));
                $decoded = array_values(array_filter($decoded, static fn(array $absence): bool => trim((string)($absence['employee'] ?? '')) === $userName));
            }
            $state[$key] = $decoded;
        }
    }
    if (!empty($state['clients'])) {
        $findClient = static function (array $record) use (&$state, $phoneKey, $nameKey): ?array {
            $recordId = (string)($record['clientId'] ?? '');
            $recordUid = trim((string)($record['clientUid'] ?? ''));
            $recordPhone = $phoneKey($record['phone'] ?? '');
            foreach ($state['clients'] as $client) {
                if ($recordUid !== '' && $recordUid === (string)($client['uid'] ?? '')) return $client;
                if ($recordId !== '' && $recordId === (string)($client['id'] ?? '')) return $client;
                $clientPhone = $phoneKey($client['phone'] ?? '');
                if ($recordPhone !== '' && $recordPhone === $clientPhone) return $client;
            }
            return null;
        };
        foreach ($state['processes'] as &$process) {
            $client = $findClient($process);
            if ($client) {
                $process['clientId'] = $client['id'];
                if (!empty($client['uid'])) $process['clientUid'] = $client['uid'];
            }
        }
        unset($process);
        foreach ($state['bookings'] as &$booking) {
            $client = $findClient($booking);
            if ($client) {
                $booking['clientId'] = $client['id'];
                if (!empty($client['uid'])) $booking['clientUid'] = $client['uid'];
            }
        }
        unset($booking);
        foreach ($state['stockBikes'] as &$bike) {
            if (!empty($bike['processId']) || !empty($bike['processUid'])) {
                foreach ($state['processes'] as $process) {
                    $sameProcessUid = !empty($bike['processUid']) && (string)($process['uid'] ?? '') === (string)$bike['processUid'];
                    $sameProcessId = !empty($bike['processId']) && (string)($process['id'] ?? '') === (string)$bike['processId'];
                    if (($sameProcessUid || $sameProcessId) && !empty($process['clientId'])) {
                        $bike['processId'] = $process['id'];
                        if (!empty($process['uid'])) $bike['processUid'] = $process['uid'];
                        $bike['clientId'] = $process['clientId'];
                        if (!empty($process['clientUid'])) $bike['clientUid'] = $process['clientUid'];
                        break;
                    }
                }
            }
        }
        unset($bike);
    }
    json_response(['ok' => true, 'initialized' => $initialized, 'state' => $state]);
}

if ($method === 'PUT') {
    $input = json_input();
    verify_csrf($input);
    $resource = (string)($input['resource'] ?? '');
    $data = $input['data'] ?? null;
    $partial = !empty($input['partial']);
    if (!in_array($resource, $allowed, true) || !is_array($data)) {
        json_response(['ok' => false, 'error' => 'Recurso inválido.'], 422);
    }
    if (!$canAccess($resource)) {
        json_response(['ok' => false, 'error' => 'Sem permissão para alterar este módulo.'], 403);
    }
    if ($resource === 'absences' && ($user['role'] ?? '') !== 'boss') {
        $userName = trim((string)($user['name'] ?? ''));
        $data = array_values(array_filter($data, static fn(array $absence): bool => trim((string)($absence['employee'] ?? '')) === $userName));
        $existingStatement = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'state_absences' LIMIT 1");
        $existingStatement->execute();
        $existing = json_decode((string)($existingStatement->fetchColumn() ?: '[]'), true);
        $existing = is_array($existing) ? $existing : [];
        $otherUsers = array_values(array_filter($existing, static fn(array $absence): bool => trim((string)($absence['employee'] ?? '')) !== $userName));
        $data = array_merge($otherUsers, $data);
    }
    $pdo->beginTransaction();
    try {
        if (in_array($resource, ['clients','bookings','officeBlocks','officeNotes','testDrives','processes','orders','stockBikes','absences'], true)) {
            $existing = $readResource($pdo, $resource, true);
            $tombstoneKey = 'state_deleted_' . $resource;
            $tombstoneStatement = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1 FOR UPDATE');
            $tombstoneStatement->execute([$tombstoneKey]);
            $tombstones = json_decode((string)($tombstoneStatement->fetchColumn() ?: '[]'), true);
            $tombstones = is_array($tombstones) ? $tombstones : [];
            foreach ($data as $incoming) {
                if (!is_array($incoming)) continue;
                $incomingUid = trim((string)($incoming['uid'] ?? ''));
                $incomingId = trim((string)($incoming['id'] ?? ''));
                $wasDeleted = false;
                foreach ($tombstones as $deleted) {
                    if (($incomingUid !== '' && $incomingUid === (string)($deleted['uid'] ?? '')) || ($incomingUid === '' && $incomingId !== '' && $incomingId === (string)($deleted['id'] ?? ''))) {
                        $wasDeleted = true;
                        break;
                    }
                }
                if ($wasDeleted) continue;
                $match = null;
                foreach ($existing as $index => $stored) {
                    if (!is_array($stored)) continue;
                    $storedUid = trim((string)($stored['uid'] ?? ''));
                    if ($incomingUid !== '' && $incomingUid === $storedUid) { $match = $index; break; }
                    if ($resource === 'clients') {
                        $incomingPhone = $phoneKey($incoming['phone'] ?? '');
                        $storedPhone = $phoneKey($stored['phone'] ?? '');
                        $samePhone = $incomingPhone !== '' && $incomingPhone === $storedPhone;
                        $sameId = (string)($incoming['id'] ?? '') !== '' && (string)($incoming['id'] ?? '') === (string)($stored['id'] ?? '');
                        if ($samePhone || $sameId) { $match = $index; break; }
                    } elseif ($resource === 'bookings') {
                        $sameId = (string)($incoming['id'] ?? '') !== '' && (string)($incoming['id'] ?? '') === (string)($stored['id'] ?? '');
                        if ($sameId) { $match = $index; break; }
                    } elseif ($resource === 'testDrives') {
                        $sameId = (string)($incoming['id'] ?? '') !== '' && (string)($incoming['id'] ?? '') === (string)($stored['id'] ?? '');
                        if ($sameId) { $match = $index; break; }
                    } elseif ($resource === 'processes') {
                        $sameUid = $incomingUid !== '' && $incomingUid === $storedUid;
                        $sameClientId = (string)($incoming['clientId'] ?? '') !== '' && (string)($incoming['clientId'] ?? '') === (string)($stored['clientId'] ?? '');
                        $sameClientName = $nameKey($incoming['client'] ?? '') !== '' && $nameKey($incoming['client'] ?? '') === $nameKey($stored['client'] ?? '');
                        $sameLegacyProcess = (string)($incoming['id'] ?? '') !== '' && (string)($incoming['id'] ?? '') === (string)($stored['id'] ?? '') && ($sameClientId || $sameClientName);
                        if ($sameUid || $sameLegacyProcess) { $match = $index; break; }
                    } elseif ((string)($incoming['id'] ?? '') !== '' && (string)($incoming['id'] ?? '') === (string)($stored['id'] ?? '')) {
                        $match = $index;
                        break;
                    }
                }
                if ($match !== null) {
                    $storedVersion = max(1, (int)($existing[$match]['_version'] ?? 1));
                    $incomingVersion = max(1, (int)($incoming['_version'] ?? 1));
                    if ($partial && $incomingVersion !== $storedVersion) {
                        $storedComparable = $existing[$match];
                        $incomingComparable = array_replace($existing[$match], $incoming);
                        unset($storedComparable['_version'], $incomingComparable['_version']);
                        if ($storedComparable === $incomingComparable) continue;
                        $pdo->rollBack();
                        json_response(['ok' => false, 'error' => 'Este registo foi alterado por outro utilizador. Atualize a página antes de repetir.', 'resource' => $resource], 409);
                    }
                    if (isset($existing[$match]['id'])) $incoming['id'] = $existing[$match]['id'];
                    if (!empty($existing[$match]['uid'])) $incoming['uid'] = $existing[$match]['uid'];
                    if ($resource === 'stockBikes') {
                        $storedChequeVersion = (string)($existing[$match]['chequeStateUpdatedAt'] ?? '');
                        $incomingChequeVersion = (string)($incoming['chequeStateUpdatedAt'] ?? '');
                        if ($storedChequeVersion !== '' && ($incomingChequeVersion === '' || strcmp($incomingChequeVersion, $storedChequeVersion) < 0)) {
                            foreach (['chequeDueDate','chequeCollectedEarly','chequeCollectionTriggeredAt','chequeStateUpdatedAt','chequeNotificationPending','chequeNotificationError'] as $field) {
                                if (array_key_exists($field, $existing[$match])) $incoming[$field] = $existing[$match][$field];
                                else unset($incoming[$field]);
                            }
                        }
                    }
                    $incoming['_version'] = $storedVersion + 1;
                    $existing[$match] = array_replace($existing[$match], $incoming);
                    continue;
                }
                $idCollision = false;
                foreach ($existing as $stored) {
                    if ((string)($incoming['id'] ?? '') !== '' && (string)($incoming['id'] ?? '') === (string)($stored['id'] ?? '')) { $idCollision = true; break; }
                }
                if ($idCollision || !isset($incoming['id'])) $incoming['id'] = $resource === 'processes' ? $freshProcessId($existing) : $freshId();
                if (empty($incoming['uid'])) $incoming['uid'] = rtrim($resource, 's') . '-' . bin2hex(random_bytes(12));
                $incoming['_version'] = 1;
                $existing[] = $incoming;
            }
            $data = array_values($existing);
        }
        if ($resource === 'bookings') {
            $serverClients = $readResource($pdo, 'clients');
            foreach ($data as &$booking) {
                $bookingPhone = $phoneKey($booking['phone'] ?? '');
                foreach ($serverClients as $client) {
                    $samePhone = $bookingPhone !== '' && $bookingPhone === $phoneKey($client['phone'] ?? '');
                    if ($samePhone) { $booking['clientId'] = $client['id']; break; }
                }
            }
            unset($booking);
        }
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $statement = $pdo->prepare('INSERT INTO app_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)');
        $statement->execute(['state_' . $resource, $encoded, $user['id']]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    json_response(['ok' => true, 'resource' => $resource, 'data' => $data, 'updatedAt' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM)]);
}

if ($method === 'DELETE') {
    $input = json_input();
    verify_csrf($input);
    $resource = (string)($input['resource'] ?? '');
    $id = (string)($input['id'] ?? '');
    $uid = trim((string)($input['uid'] ?? ''));
    $version = max(1, (int)($input['version'] ?? 1));
    if (!in_array($resource, ['clients','bookings','officeBlocks','officeNotes','testDrives','processes','orders','stockBikes','absences'], true) || $id === '') json_response(['ok' => false,'error' => 'Pedido inválido.'], 422);
    if (!$canAccess($resource)) json_response(['ok' => false, 'error' => 'Sem permissão para alterar este módulo.'], 403);
    $pdo->beginTransaction();
    try {
        $data = $readResource($pdo, $resource, true);
        foreach ($data as $item) {
            $isTarget = $uid !== '' ? (string)($item['uid'] ?? '') === $uid : (string)($item['id'] ?? '') === $id;
            if ($isTarget && max(1, (int)($item['_version'] ?? 1)) !== $version) {
                $pdo->rollBack();
                json_response(['ok' => false, 'error' => 'Este registo foi alterado por outro utilizador. Atualize antes de eliminar.'], 409);
            }
        }
        if ($resource === 'absences' && ($user['role'] ?? '') !== 'boss') {
            $userName = trim((string)($user['name'] ?? ''));
            foreach ($data as $item) {
                $isTarget = $uid !== '' ? (string)($item['uid'] ?? '') === $uid : (string)($item['id'] ?? '') === $id;
                if ($isTarget && trim((string)($item['employee'] ?? '')) !== $userName) {
                    $pdo->rollBack();
                    json_response(['ok' => false, 'error' => 'Só pode eliminar as suas próprias ausências.'], 403);
                }
            }
        }
        $data = array_values(array_filter($data, static function (array $item) use ($resource, $id, $uid): bool {
            if ($uid !== '') return (string)($item['uid'] ?? '') !== $uid;
            return (string)($item['id'] ?? '') !== $id;
        }));
        $tombstoneKey = 'state_deleted_' . $resource;
        $tombstoneStatement = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1 FOR UPDATE');
        $tombstoneStatement->execute([$tombstoneKey]);
        $tombstones = json_decode((string)($tombstoneStatement->fetchColumn() ?: '[]'), true);
        $tombstones = is_array($tombstones) ? $tombstones : [];
        $tombstones[] = ['id' => $id, 'uid' => $uid, 'deletedAt' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM), 'deletedBy' => $user['id']];
        $tombstones = array_slice($tombstones, -2000);
        $saveTombstones = $pdo->prepare('INSERT INTO app_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)');
        $saveTombstones->execute([$tombstoneKey, json_encode($tombstones, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $user['id']]);
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $statement = $pdo->prepare('INSERT INTO app_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)');
        $statement->execute(['state_' . $resource, $encoded, $user['id']]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    json_response(['ok' => true, 'resource' => $resource, 'data' => $data]);
}

json_response(['ok' => false, 'error' => 'Método não permitido.'], 405);
