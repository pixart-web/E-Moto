#!/bin/sh
set -eu
php /var/www/html/tests/integration/seed-and-backup.php
BACKUP="$(cat /tmp/emoto-backup-path)"
php /var/www/html/tools/verify-backup.php "$BACKUP"
FAILURE="$(cat /tmp/emoto-failure-path)"
if php /var/www/html/tools/restore-backup.php "$FAILURE" --confirm=RESTORE; then echo "ERRO: restauro inválido aceite"; exit 1; else echo "OK: falha de restauro detetada e revertida"; fi
php -r 'require "/var/www/html/api/bootstrap.php"; $v=json_decode($pdo->query("SELECT setting_value FROM app_settings WHERE setting_key=\"state_clients\"")->fetchColumn(),true); if(count($v)!==1)exit(1); echo "OK: dados preservados após falha\n";'
php -r 'require "/var/www/html/api/bootstrap.php"; $pdo->exec("UPDATE app_settings SET setting_value=\"[]\" WHERE setting_key=\"state_clients\"");'
php /var/www/html/tools/restore-backup.php "$BACKUP" --confirm=RESTORE
php -r 'require "/var/www/html/api/bootstrap.php"; $v=json_decode($pdo->query("SELECT setting_value FROM app_settings WHERE setting_key=\"state_clients\"")->fetchColumn(),true); if(count($v)!==1||($v[0]["uid"]??"")!=="client-test-100")exit(1); echo "OK: relações e IDs restaurados\n";'
STATUS="$(curl -s -o /tmp/unauthorized.json -w '%{http_code}' http://localhost/api/backups.php)"
test "$STATUS" = "401" && echo "OK: utilizador não autenticado bloqueado"
LOGIN="$(curl -s -H 'Content-Type: application/json' -d '{"username":"employee_test","password":"AuditTest!26"}' http://localhost/api/auth.php)"
TOKEN="$(php -r '$v=json_decode(stream_get_contents(STDIN),true);echo $v["sessionToken"]??"";' <<EOF
$LOGIN
EOF
)"
STATUS="$(curl -s -o /tmp/forbidden.json -w '%{http_code}' -H "X-Session-Token: $TOKEN" http://localhost/api/backups.php)"
test "$STATUS" = "403" && echo "OK: funcionário sem permissão bloqueado"
AGENDA_LOGIN="$(curl -s -H 'Content-Type: application/json' -d '{"username":"agenda_test","password":"AuditTest!26"}' http://localhost/api/auth.php)"
AGENDA_TOKEN="$(printf '%s' "$AGENDA_LOGIN" | php -r '$v=json_decode(stream_get_contents(STDIN),true);echo $v["sessionToken"]??"";')"
curl -s -H "X-Session-Token: $AGENDA_TOKEN" http://localhost/api/state.php > /tmp/agenda-state.json
php -r '$v=json_decode(file_get_contents("/tmp/agenda-state.json"),true); if(count($v["state"]["clients"]??[])!==1||count($v["state"]["processes"]??[])!==0)exit(1); echo "OK: Oficina recebe clientes dependentes sem expor processos\n";'
ADMIN_LOGIN="$(curl -s -H 'Content-Type: application/json' -d '{"username":"admin_test","password":"AuditTest!26"}' http://localhost/api/auth.php)"
ADMIN_TOKEN="$(printf '%s' "$ADMIN_LOGIN" | php -r '$v=json_decode(stream_get_contents(STDIN),true);echo $v["sessionToken"]??"";')"
STATUS="$(curl -s -o /tmp/cheque-mismatch.json -w '%{http_code}' -H 'Content-Type: application/json' -H "X-Session-Token: $ADMIN_TOKEN" -d '{"processId":"MG-T001","stockId":"201"}' http://localhost/api/cheque-notifications.php)"
test "$STATUS" = "422" && echo "OK: cheque de outra moto rejeitado"
STATUS="$(curl -s -o /tmp/cheque-match.json -w '%{http_code}' -H 'Content-Type: application/json' -H "X-Session-Token: $ADMIN_TOKEN" -d '{"processId":"MG-T001","stockId":"200"}' http://localhost/api/cheque-notifications.php)"
test "$STATUS" = "200" && echo "OK: cheque associado colocado na fila"
STATUS="$(curl -s -o /tmp/cheque-repeat.json -w '%{http_code}' -H 'Content-Type: application/json' -H "X-Session-Token: $ADMIN_TOKEN" -d '{"processId":"MG-T001","stockId":"200"}' http://localhost/api/cheque-notifications.php)"
test "$STATUS" = "200" && echo "OK: repetição do aviso aceite sem duplicar"
php -r 'require "/var/www/html/api/bootstrap.php"; $q=json_decode($pdo->query("SELECT setting_value FROM app_settings WHERE setting_key=\"state_chequeEmailQueue\"")->fetchColumn(),true); if(count($q)!==1)exit(1); echo "OK: fila de cheques idempotente\n";'
