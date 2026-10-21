<?php
declare(strict_types=1);

function backup_directory(): string {
    $directory = dirname(__DIR__, 2) . '/e-motogreen-backups';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Não foi possível criar a pasta privada de backups.');
    return $directory;
}

function backup_safe_name(string $name): string {
    $name = basename($name);
    if (!preg_match('/^emoto-backup-\d{8}-\d{6}-[a-f0-9]{8}\.zip$/', $name)) throw new RuntimeException('Nome de backup inválido.');
    return $name;
}

function backup_create(PDO $pdo, int $userId, string $type = 'manual'): array {
    if (!class_exists(ZipArchive::class)) throw new RuntimeException('A extensão PHP ZipArchive não está disponível.');
    $directory = backup_directory();
    $lock = fopen($directory . '/backup.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Já existe uma operação de backup em execução.');
    $id = bin2hex(random_bytes(4));
    $name = 'emoto-backup-' . date('Ymd-His') . '-' . $id . '.zip';
    $temporary = $directory . '/.' . $name . '.tmp';
    $destination = $directory . '/' . $name;
    try {
        $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        $tablesQuery = $pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE=? ORDER BY TABLE_NAME');
        $tablesQuery->execute([$database, 'BASE TABLE']);
        $tables = array_column($tablesQuery->fetchAll(), 'TABLE_NAME');
        $excluded = ['user_sessions', 'login_attempts'];
        $snapshot = ['format'=>'e-motogreen-backup-v2','createdAt'=>(new DateTimeImmutable())->format(DATE_ATOM),'applicationVersion'=>backup_app_version(),'schema'=>$database,'excludedTables'=>$excluded,'tables'=>[]];
        $pdo->beginTransaction();
        try {
            foreach ($tables as $table) {
                if (in_array($table, $excluded, true)) continue;
                if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) throw new RuntimeException('Nome de tabela inválido.');
                $quoted = '`' . $table . '`';
                $create = $pdo->query('SHOW CREATE TABLE ' . $quoted)->fetch();
                $rows = $pdo->query('SELECT * FROM ' . $quoted)->fetchAll();
                $snapshot['tables'][$table] = ['createSql'=>$create['Create Table'] ?? array_values($create ?: [])[1] ?? '', 'count'=>count($rows), 'rows'=>$rows];
            }
            $pdo->commit();
        } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
        $databaseJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
        $entries = ['database.json'=>['sha256'=>hash('sha256',$databaseJson),'size'=>strlen($databaseJson)]];
        $files = [];
        $documentRoot = dirname(__DIR__) . '/private/documents';
        if (is_dir($documentRoot)) {
            foreach (new DirectoryIterator($documentRoot) as $file) {
                if (!$file->isFile() || $file->isLink()) continue;
                $files[] = ['path'=>$file->getPathname(),'entry'=>'files/documents/'.$file->getFilename()];
                $entries['files/documents/'.$file->getFilename()] = ['sha256'=>hash_file('sha256',$file->getPathname()),'size'=>$file->getSize()];
            }
        }
        $manifest = ['format'=>'e-motogreen-manifest-v2','backupId'=>$id,'createdAt'=>$snapshot['createdAt'],'type'=>$type,'applicationVersion'=>$snapshot['applicationVersion'],'createdBy'=>$userId,'entries'=>$entries,'inventory'=>['tables'=>array_map(static fn($v)=>$v['count'],$snapshot['tables']),'documents'=>count($files)]];
        $manifestJson = json_encode($manifest, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
        $zip = new ZipArchive();
        if ($zip->open($temporary, ZipArchive::CREATE|ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Não foi possível criar o ZIP.');
        $zip->addFromString('manifest.json', $manifestJson);
        $zip->addFromString('database.json', $databaseJson);
        foreach ($files as $file) $zip->addFile($file['path'], $file['entry']);
        if (!$zip->close()) throw new RuntimeException('Não foi possível finalizar o ZIP.');
        chmod($temporary, 0600);
        $validation = backup_validate($temporary);
        if (!$validation['valid']) throw new RuntimeException('O backup criado não passou na verificação: '.implode('; ', $validation['errors']));
        if (!rename($temporary, $destination)) throw new RuntimeException('Não foi possível publicar o backup validado.');
        return ['name'=>$name,'size'=>filesize($destination),'createdAt'=>$snapshot['createdAt'],'type'=>$type,'version'=>$snapshot['applicationVersion'],'verified'=>true,'inventory'=>$manifest['inventory']];
    } finally {
        if (is_file($temporary)) @unlink($temporary);
        if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
    }
}

function backup_app_version(): string {
    $path = dirname(__DIR__) . '/version.json';
    $data = is_file($path) ? json_decode((string)file_get_contents($path), true) : [];
    return (string)($data['version'] ?? 'unknown');
}

function backup_validate(string $path): array {
    $errors=[];
    if (!is_file($path) || filesize($path) < 1) return ['valid'=>false,'errors'=>['Ficheiro vazio ou inexistente.']];
    if (filesize($path) > 1024*1024*1024) return ['valid'=>false,'errors'=>['Backup excede 1 GB.']];
    $zip=new ZipArchive();
    if ($zip->open($path)!==true) return ['valid'=>false,'errors'=>['ZIP inválido.']];
    try {
        if ($zip->numFiles > 20000) $errors[]='Número excessivo de ficheiros.';
        foreach (['manifest.json','database.json'] as $required) if ($zip->locateName($required, ZipArchive::FL_NOCASE)===false) $errors[]="Falta {$required}.";
        $manifest=json_decode((string)$zip->getFromName('manifest.json'),true);
        if (!is_array($manifest)||($manifest['format']??'')!=='e-motogreen-manifest-v2') $errors[]='Manifesto incompatível.';
        $total=0;
        foreach (($manifest['entries']??[]) as $entry=>$metadata) {
            if (!is_string($entry)||str_contains($entry,'..')||str_starts_with($entry,'/')||preg_match('/^[A-Za-z]:/', $entry)) { $errors[]='Caminho inseguro no ZIP.'; continue; }
            $content=$zip->getFromName($entry);
            if ($content===false) { $errors[]="Falta {$entry}."; continue; }
            $total+=strlen($content);
            if ($total>2*1024*1024*1024) { $errors[]='Conteúdo descomprimido excessivo.'; break; }
            if (!hash_equals((string)($metadata['sha256']??''),hash('sha256',$content))) $errors[]="Checksum inválido: {$entry}.";
        }
        $database=json_decode((string)$zip->getFromName('database.json'),true);
        if (!is_array($database)||($database['format']??'')!=='e-motogreen-backup-v2'||!is_array($database['tables']??null)) $errors[]='Snapshot da base de dados incompatível.';
        return ['valid'=>!$errors,'errors'=>$errors,'manifest'=>$manifest,'database'=>$database];
    } finally { $zip->close(); }
}

function backup_list(): array {
    $result=[];
    foreach (glob(backup_directory().'/emoto-backup-*.zip')?:[] as $path) {
        $validation=backup_validate($path);
        $manifest=$validation['manifest']??[];
        $result[]=['name'=>basename($path),'size'=>filesize($path),'createdAt'=>$manifest['createdAt']??date(DATE_ATOM,filemtime($path)),'type'=>$manifest['type']??'desconhecido','version'=>$manifest['applicationVersion']??'unknown','verified'=>$validation['valid'],'errors'=>$validation['errors']];
    }
    usort($result,static fn($a,$b)=>strcmp($b['createdAt'],$a['createdAt']));
    return $result;
}
