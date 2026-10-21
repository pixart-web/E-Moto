<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$user = require_user();
$permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
if (!in_array(($user['role'] ?? ''), ['admin','boss'], true) && !array_intersect(['processos','contabilidade'], $permissions)) {
    json_response(['ok'=>false,'error'=>'Sem permissão para alterar cheques.'],403);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_response(['ok'=>false,'error'=>'Método não permitido.'],405);
$input = json_input(); verify_csrf($input);
$processId = trim((string)($input['processId'] ?? ''));
$stockId = (string)($input['stockId'] ?? '');
$force = !empty($input['force']);
$settings = $pdo->query("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('state_processes','state_stockBikes')")->fetchAll();
$state=[]; foreach($settings as $row) $state[substr($row['setting_key'],6)] = json_decode((string)$row['setting_value'],true) ?: [];
$process=null; foreach($state['processes']??[] as $candidate) if((string)($candidate['id']??'')===$processId){$process=$candidate;break;}
$bike=null; $bikeIndex=null; foreach($state['stockBikes']??[] as $index=>$candidate){$sameStock=$stockId!==''&&(string)($candidate['id']??'')===$stockId;$linkedByProcess=(string)($candidate['processId']??'')===$processId;$linkedByStock=(string)($process['stockBikeId']??'')!==''&&(string)($candidate['id']??'')===(string)$process['stockBikeId'];if($sameStock||($stockId===''&&($linkedByProcess||$linkedByStock))){$bike=$candidate;$bikeIndex=$index;break;}}
if(!$process||!$bike||empty($bike['chequeNumber'])) json_response(['ok'=>false,'error'=>'Não foi encontrado um cheque associado a este processo.'],422);
$lisbon=new DateTimeZone('Europe/Lisbon');
$nextBusinessDay=new DateTimeImmutable('tomorrow',$lisbon);
while(in_array((int)$nextBusinessDay->format('N'),[6,7],true))$nextBusinessDay=$nextBusinessDay->modify('+1 day');
$dueDate=$nextBusinessDay->format('Y-m-d');
$state['stockBikes'][$bikeIndex]['chequeDueDate']=$dueDate;
$state['stockBikes'][$bikeIndex]['processId']=$processId;
$chequeStateUpdatedAt=(new DateTimeImmutable('now',$lisbon))->format(DateTimeInterface::ATOM);
$state['stockBikes'][$bikeIndex]['chequeCollectionTriggeredAt']=$chequeStateUpdatedAt;
$state['stockBikes'][$bikeIndex]['chequeStateUpdatedAt']=$chequeStateUpdatedAt;
$state['stockBikes'][$bikeIndex]['chequeNotificationPending']=true;
$saveState=$pdo->prepare("INSERT INTO app_settings(setting_key,setting_value) VALUES('state_stockBikes',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
$saveState->execute([json_encode($state['stockBikes'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
$queueStatement=$pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key='state_chequeEmailQueue' LIMIT 1");
$queueStatement->execute();
$queue=json_decode((string)($queueStatement->fetchColumn()?:'[]'),true);
$queue=is_array($queue)?$queue:[];
$queue[]= [
    'id'=>bin2hex(random_bytes(16)),
    'status'=>'pending',
    'stockId'=>(string)($bike['id']??$stockId),
    'processId'=>$processId,
    'chequeNumber'=>(string)$bike['chequeNumber'],
    'chequeAmount'=>(float)($bike['chequeAmount']??0),
    'reference'=>(string)($bike['reference']??$bike['invoiceNumber']??''),
    'brand'=>(string)($bike['brand']??''),
    'model'=>(string)($bike['model']??''),
    'client'=>(string)($process['client']??''),
    'chequeTerm'=>(int)($bike['chequeTerm']??0),
    'dueDate'=>$dueDate,
    'createdAt'=>$chequeStateUpdatedAt,
    'attempts'=>0
];
$queue=array_slice($queue,-500);
$saveQueue=$pdo->prepare("INSERT INTO app_settings(setting_key,setting_value) VALUES('state_chequeEmailQueue',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
$saveQueue->execute([json_encode($queue,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
try{$log=$pdo->prepare("INSERT INTO cheque_notification_logs(stock_id,process_id,cheque_number,notification_type,status,error_message,sent_by,sent_at) VALUES(?,?,?,'early_collection','queued',NULL,?,NULL)");$log->execute([$stockId,$processId,$bike['chequeNumber'],$user['id']]);}catch(Throwable $logError){error_log('E-Motogreen cheque queue log: '.$logError->getMessage());}
json_response(['ok'=>true,'status'=>'queued','dueDate'=>$dueDate,'chequeStateUpdatedAt'=>$chequeStateUpdatedAt]);
