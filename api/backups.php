<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require __DIR__.'/backup-lib.php';
$user=require_user();
if (($user['role']??'')!=='admin') json_response(['ok'=>false,'error'=>'Acesso reservado a administradores.'],403);
$method=$_SERVER['REQUEST_METHOD']??'GET';
if ($method==='GET' && isset($_GET['download'])) {
    $name=backup_safe_name((string)$_GET['download']);$path=backup_directory().'/'.$name;
    if (!is_file($path)) json_response(['ok'=>false,'error'=>'Backup não encontrado.'],404);
    header('Content-Type: application/zip');header('Content-Length: '.filesize($path));header('Content-Disposition: attachment; filename="'.$name.'"');header('X-Content-Type-Options: nosniff');
    $stream=fopen($path,'rb');while(!feof($stream)){echo fread($stream,1024*1024);flush();}fclose($stream);exit;
}
if ($method==='GET') json_response(['ok'=>true,'backups'=>backup_list()]);
$input=json_input();verify_csrf($input);
if ($method==='POST') json_response(['ok'=>true,'backup'=>backup_create($pdo,(int)$user['id'],'manual')],201);
if ($method==='DELETE') {
    $name=backup_safe_name((string)($input['name']??''));$items=backup_list();
    if (count(array_filter($items,static fn($item)=>$item['verified']))<=1) json_response(['ok'=>false,'error'=>'Não é permitido eliminar a única cópia válida.'],422);
    $path=backup_directory().'/'.$name;if(!is_file($path))json_response(['ok'=>false,'error'=>'Backup não encontrado.'],404);
    if(!unlink($path))throw new RuntimeException('Não foi possível eliminar o backup.');json_response(['ok'=>true]);
}
json_response(['ok'=>false,'error'=>'Método não permitido.'],405);
