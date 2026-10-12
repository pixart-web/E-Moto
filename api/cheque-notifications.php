<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/smtp-lib.php';
$user = require_user();
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
$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$subject='Cheque '.$bike['chequeNumber'].' a vencer no dia útil seguinte';
$html='<html><body style="font-family:Arial,sans-serif;color:#173027"><h2>Cobrança de cheque a vencer</h2><p>Foi pedida a matrícula. O cheque número <b>'.$e($bike['chequeNumber']).'</b>, associado à encomenda <b>'.$e($bike['reference']??'').'</b>, vai vencer no dia útil seguinte: <b>'.$e($nextBusinessDay->format('d/m/Y')).'</b>.</p><p><b>Moto:</b> '.$e(($bike['brand']??'').' '.($bike['model']??'')).'<br><b>Cliente:</b> '.$e($process['client']??'').'<br><b>Processo:</b> '.$e($processId).'<br><b>Prazo original:</b> '.$e($bike['chequeTerm']??0).' dias<br><b>Nova data de vencimento:</b> '.$e($nextBusinessDay->format('d/m/Y')).'</p><p>Na Contabilidade, o cheque passa para o estado <b>A vencer</b>. Depois da data indicada, passa automaticamente para <b>Vencido</b>.</p></body></html>';
try{smtp_send_html($config,['contabilidade@motogreen.pt'],$subject,$html);$status='sent';$error=null;$state['stockBikes'][$bikeIndex]['chequeCollectedEarly']=true;$state['stockBikes'][$bikeIndex]['chequeNotificationPending']=false;unset($state['stockBikes'][$bikeIndex]['chequeNotificationError']);$saveState->execute([json_encode($state['stockBikes'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);}catch(Throwable $exception){$status='failed';$error=$exception->getMessage();$state['stockBikes'][$bikeIndex]['chequeCollectedEarly']=false;$state['stockBikes'][$bikeIndex]['chequeNotificationError']=$error;$saveState->execute([json_encode($state['stockBikes'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);}
try{$log=$pdo->prepare("INSERT INTO cheque_notification_logs(stock_id,process_id,cheque_number,notification_type,status,error_message,sent_by,sent_at) VALUES(?,?,?,'early_collection',?,?,?,?)");$log->execute([$stockId,$processId,$bike['chequeNumber'],$status,$error,$user['id'],$status==='sent'?date('Y-m-d H:i:s'):null]);}catch(Throwable $logError){error_log('E-Motogreen cheque log: '.$logError->getMessage());}
if($error)json_response(['ok'=>false,'error'=>$error,'dueDate'=>$dueDate,'chequeStateUpdatedAt'=>$chequeStateUpdatedAt],502);json_response(['ok'=>true,'status'=>'sent','dueDate'=>$dueDate,'chequeStateUpdatedAt'=>$chequeStateUpdatedAt]);
