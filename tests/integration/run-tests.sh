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
