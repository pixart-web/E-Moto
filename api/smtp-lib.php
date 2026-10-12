<?php
declare(strict_types=1);

function smtp_expect($socket, array $codes): string
{
    $response = '';
    do {
        $line = fgets($socket, 515);
        if ($line === false) throw new RuntimeException('O servidor SMTP terminou a ligação.');
        $response .= $line;
    } while (isset($line[3]) && $line[3] === '-');
    if (!in_array((int)substr($response, 0, 3), $codes, true)) throw new RuntimeException('SMTP: ' . trim($response));
    return $response;
}

function smtp_run($socket, string $command, array $codes): string
{
    if (fwrite($socket, $command . "\r\n") === false) throw new RuntimeException('Não foi possível escrever na ligação SMTP.');
    return smtp_expect($socket, $codes);
}

function smtp_send_html(array $config, array $recipients, string $subject, string $html): void
{
    $smtp = is_array($config['smtp'] ?? null) ? $config['smtp'] : [];
    foreach (['host','port','username','password','from_email'] as $key) if (empty($smtp[$key])) throw new RuntimeException("Configuração SMTP em falta: {$key}.");
    $host = (($smtp['encryption'] ?? 'ssl') === 'ssl' ? 'ssl://' : '') . $smtp['host'];
    $socket = stream_socket_client($host . ':' . (int)$smtp['port'], $number, $message, 25, STREAM_CLIENT_CONNECT);
    if (!$socket) throw new RuntimeException("Falha na ligação SMTP ({$number}): {$message}");
    stream_set_timeout($socket, 25);
    smtp_expect($socket, [220]);
    smtp_run($socket, 'EHLO ' . (gethostname() ?: 'e-moto.motogreen.pt'), [250]);
    smtp_run($socket, 'AUTH LOGIN', [334]);
    smtp_run($socket, base64_encode((string)$smtp['username']), [334]);
    smtp_run($socket, base64_encode((string)$smtp['password']), [235]);
    smtp_run($socket, 'MAIL FROM:<' . $smtp['from_email'] . '>', [250]);
    foreach ($recipients as $recipient) smtp_run($socket, 'RCPT TO:<' . $recipient . '>', [250,251]);
    smtp_run($socket, 'DATA', [354]);
    $headers = ['Date: '.date(DATE_RFC2822),'From: '.($smtp['from_name']??'E-Motogreen').' <'.$smtp['from_email'].'>','To: '.implode(', ',$recipients),'Subject: =?UTF-8?B?'.base64_encode($subject).'?=','MIME-Version: 1.0','Content-Type: text/html; charset=UTF-8','Content-Transfer-Encoding: 8bit'];
    fwrite($socket, implode("\r\n",$headers)."\r\n\r\n".preg_replace('/(?m)^\./','..',$html)."\r\n.\r\n");
    smtp_expect($socket, [250]);
    smtp_run($socket, 'QUIT', [221]);
    fclose($socket);
}
