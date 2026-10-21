<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(1);
require dirname(__DIR__).'/api/backup-lib.php';
$path=$argv[1]??'';
if($path===''){fwrite(STDERR,"Uso: php tools/verify-backup.php /caminho/backup.zip\n");exit(2);}
$result=backup_validate($path);
echo json_encode(['valid'=>$result['valid'],'errors'=>$result['errors'],'manifest'=>$result['manifest']??null],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
exit($result['valid']?0:1);
