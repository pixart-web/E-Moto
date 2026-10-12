<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acesso reservado ao Cron.');
}

require dirname(__DIR__) . '/api/bootstrap.php';

$backupDir = dirname(__DIR__, 2) . '/e-motogreen-backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true) && !is_dir($backupDir)) {
    throw new RuntimeException('Não foi possível criar a pasta privada de backups.');
}

$lock = fopen($backupDir . '/backup.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit("Já existe uma cópia de segurança em execução.\n");
}

$databaseName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
$tablesStatement = $pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME');
$tablesStatement->execute([$databaseName, 'BASE TABLE']);
$tableNames = array_column($tablesStatement->fetchAll(), 'TABLE_NAME');
$excludedTables = ['user_sessions'];
$backup = [
    'format' => 'e-motogreen-backup-v1',
    'createdAt' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
    'database' => $databaseName,
    'excludedTables' => $excludedTables,
    'tables' => [],
];

foreach ($tableNames as $tableName) {
    if (in_array($tableName, $excludedTables, true)) continue;
    if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName)) throw new RuntimeException('Nome de tabela inválido.');
    $quotedTable = '`' . $tableName . '`';
    $createRow = $pdo->query('SHOW CREATE TABLE ' . $quotedTable)->fetch();
    $backup['tables'][$tableName] = [
        'createSql' => $createRow['Create Table'] ?? array_values($createRow ?: [])[1] ?? '',
        'rows' => $pdo->query('SELECT * FROM ' . $quotedTable)->fetchAll(),
    ];
}

$json = json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
$filename = 'e-motogreen-' . date('Y-m-d_H-i-s') . '.json';
$temporary = $backupDir . '/.' . $filename . '.tmp';
$destination = $backupDir . '/' . $filename;
if (file_put_contents($temporary, $json, LOCK_EX) === false) throw new RuntimeException('Não foi possível escrever o backup.');
chmod($temporary, 0600);
if (!rename($temporary, $destination)) throw new RuntimeException('Não foi possível finalizar o backup.');

$retentionLimit = new DateTimeImmutable('-30 days');
foreach (glob($backupDir . '/e-motogreen-*.json') ?: [] as $oldBackup) {
    $modifiedAt = DateTimeImmutable::createFromFormat('U', (string)filemtime($oldBackup));
    if ($modifiedAt && $modifiedAt < $retentionLimit) unlink($oldBackup);
}

flock($lock, LOCK_UN);
fclose($lock);
echo "Backup criado: {$destination}\n";

