# E-Moto

CRM online da Motogreen para gestão de clientes, processos de compra, stock de motos, oficina, financiamentos, encomendas, equipa, férias e contabilidade.

## Requisitos

- PHP 8.2 ou superior
- MySQL/MariaDB
- Extensão `mysqli` ou PDO MySQL
- HTTPS
- Cron Jobs do cPanel para relatórios e backups

## Instalação

1. Copiar os ficheiros para a pasta `public_html` do subdomínio.
2. Criar `private/config.php` a partir de `private/config.example.php` e preencher apenas no servidor.
3. Executar os scripts necessários da pasta `database/` no phpMyAdmin.
4. Configurar os Cron Jobs descritos nos ficheiros `CONFIGURAR-*.txt`.

## Segurança

O ficheiro `private/config.php`, uploads, backups, logs e ficheiros ZIP estão excluídos do Git. Nunca devem ser enviados para um repositório público.

## Versão

Consultar `version.json`.
