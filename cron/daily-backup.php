<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Acesso reservado ao Cron.'); }
require dirname(__DIR__).'/api/bootstrap.php';
require dirname(__DIR__).'/api/backup-lib.php';
$result=backup_create($pdo,0,'automatico');
$backups=backup_list();
$keep=max(2,(int)($config['backups']['retention_count']??30));
$verified=array_values(array_filter($backups,static fn($item)=>$item['verified']));
foreach (array_slice($verified,$keep) as $old) {
    if (count($verified)<=1) break;
    $path=backup_directory().'/'.backup_safe_name($old['name']);
    if (is_file($path)&&unlink($path)) array_pop($verified);
}
echo 'Backup criado e validado: '.$result['name'].PHP_EOL;

