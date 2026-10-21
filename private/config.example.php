<?php
declare(strict_types=1);

// Exemplo apenas. Não substitua o private/config.php funcional do servidor.
return [
    'app' => [
        'url' => 'https://e-moto.motogreen.pt',
        'timezone' => 'Europe/Lisbon',
    ],
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'NOME_DA_BASE_DE_DADOS',
        'user' => 'UTILIZADOR_DA_BASE_DE_DADOS',
        'password' => 'PASSWORD_DA_BASE_DE_DADOS',
        'charset' => 'utf8mb4',
    ],
    'smtp' => [
        'host' => 'mail.motogreen.pt',
        'port' => 465,
        'encryption' => 'ssl',
        'username' => 'marketing@motogreen.pt',
        'password' => 'PASSWORD_DO_EMAIL',
        'from_email' => 'marketing@motogreen.pt',
        'from_name' => 'E-Motogreen',
    ],
    'backups' => [
        // Nunca definir abaixo de 2. Recomenda-se também uma cópia externa ao servidor.
        'retention_count' => 30,
    ],
];
