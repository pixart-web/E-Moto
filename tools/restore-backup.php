<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/api/bootstrap.php';
require dirname(__DIR__).'/api/backup-lib.php';
$path=$argv[1]??'';$confirmed=in_array('--confirm=RESTORE',$argv,true);
if($path===''||!is_file($path)){fwrite(STDERR,"Uso: php tools/restore-backup.php backup.zip [--confirm=RESTORE]\n");exit(2);}
$validation=backup_validate($path);
if(!$validation['valid']){fwrite(STDERR,"Backup inválido: ".implode('; ',$validation['errors'])."\n");exit(1);}
$snapshot=$validation['database'];$manifest=$validation['manifest'];
$database=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
$available=$pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE=?');$available->execute([$database,'BASE TABLE']);$available=array_flip(array_column($available->fetchAll(),'TABLE_NAME'));
foreach(array_keys($snapshot['tables']) as $table)if(!isset($available[$table])){fwrite(STDERR,"Tabela incompatível ou inexistente: {$table}\n");exit(1);}
echo "Backup: ".($manifest['backupId']??'')."\nCriado: ".($manifest['createdAt']??'')."\nTabelas: ".count($snapshot['tables'])."\n";
if(!$confirmed){echo "Validação concluída. Nenhum dado foi alterado. Para restaurar, repita com --confirm=RESTORE\n";exit(0);}
$safety=backup_create($pdo,0,'pre-restauro');echo "Backup de segurança criado: {$safety['name']}\n";
$zip=new ZipArchive();$zip->open($path);
$private=dirname(__DIR__).'/private';$live=$private.'/documents';$staging=$private.'/restore-staging-'.bin2hex(random_bytes(5));$previous=$private.'/documents-before-restore-'.date('YmdHis');
if(!mkdir($staging,0700,true)&&!is_dir($staging))throw new RuntimeException('Não foi possível criar staging.');
foreach(($manifest['entries']??[]) as $entry=>$metadata){if(!str_starts_with($entry,'files/documents/'))continue;$name=basename($entry);$content=$zip->getFromName($entry);if($content===false||file_put_contents($staging.'/'.$name,$content,LOCK_EX)===false)throw new RuntimeException('Falha no staging de documentos.');chmod($staging.'/'.$name,0600);}
$zip->close();$swapped=false;
try{
 $pdo->beginTransaction();$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
 if(is_dir($live)&&!rename($live,$previous))throw new RuntimeException('Não foi possível preservar os documentos atuais.');
 if(!rename($staging,$live))throw new RuntimeException('Não foi possível ativar os documentos restaurados.');$swapped=true;
 foreach($snapshot['tables'] as $table=>$tableData){if(!preg_match('/^[A-Za-z0-9_]+$/',$table))throw new RuntimeException('Tabela inválida.');$quoted='`'.$table.'`';$pdo->exec('DELETE FROM '.$quoted);foreach(($tableData['rows']??[]) as $row){if(!$row)continue;$columns=array_keys($row);foreach($columns as $column)if(!preg_match('/^[A-Za-z0-9_]+$/',$column))throw new RuntimeException('Coluna inválida.');$sql='INSERT INTO '.$quoted.' (`'.implode('`,`',$columns).'`) VALUES ('.implode(',',array_fill(0,count($columns),'?')).')';$pdo->prepare($sql)->execute(array_values($row));}}
 $pdo->exec('SET FOREIGN_KEY_CHECKS=1');$pdo->commit();
 foreach($snapshot['tables'] as $table=>$tableData){$count=(int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();if($count!==(int)$tableData['count'])throw new RuntimeException("Contagem divergente após restauro: {$table}");}
 echo "Restauro concluído e contagens validadas. Documentos anteriores preservados temporariamente em {$previous}\n";
}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();try{$pdo->exec('SET FOREIGN_KEY_CHECKS=1');}catch(Throwable){}if($swapped){@rename($live,$staging);if(is_dir($previous))@rename($previous,$live);}fwrite(STDERR,"Restauro revertido: {$error->getMessage()}\n");exit(1);}
