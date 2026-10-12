# Auditoria técnica do E-Motogreen

Data: 23/09/2026

## Âmbito analisado

- autenticação e sessões simultâneas;
- clientes e prevenção de duplicados;
- processos, checklist, financiamento e retomas;
- stock, encomendas e associação ao cliente/processo;
- agenda, histórico de oficina e edição de marcações;
- férias, faltas e notas;
- permissões Admin, Boss e Funcionário;
- relatórios, documentos, recuperação de password e backups;
- sincronização entre utilizadores e risco de perda de dados.

## Problemas críticos corrigidos

1. O navegador podia voltar a introduzir processos antigos guardados localmente. A base de dados do servidor passou a ser a autoridade única.
2. Dois processos com o mesmo número podiam ser confundidos. Os processos passam a ter uma identidade interna única, além do número MG visível.
3. A edição da data/hora de uma marcação podia criar outra marcação em vez de atualizar a existente. A correspondência passa a usar UID/ID estável.
4. Stock, Encomendas e Ausências eram substituídos como blocos completos. Passaram a ser fundidos registo a registo dentro de transações MySQL.
5. As eliminações de stock, encomendas, marcações e ausências passam a ser explícitas no servidor, evitando reaparecimentos posteriores.
6. Ligações Cliente → Processo → Stock → Oficina são reconciliadas por ID e, quando necessário, por telefone/nome.
7. As recuperações especiais Daniel/IEDA passam a executar uma única vez e ficam marcadas na ficha do cliente; uma eliminação posterior já não recria o processo.
8. A moto associada a um cliente através do stock passa a guardar também variante, VIN, cor e ligação `stockBikeId`.
9. Novos clientes recebem identificadores não sequenciais, reduzindo colisões quando dois utilizadores trabalham ao mesmo tempo.
10. O cargo Boss foi acrescentado corretamente ao editor de utilizadores.
11. Foram retiradas passwords predefinidas do JavaScript público. A autenticação real permanece no servidor com hashes seguros.
12. A recuperação de password foi implementada no servidor com token aleatório, validade de 60 minutos, utilização única e invalidação das sessões antigas.
13. O upload/listagem/download de JPG, PNG e PDF dos processos foi implementado. Os ficheiros ficam na pasta privada e são validados pelo conteúdo real e tamanho.

## Validações executadas

- navegação em todos os painéis sem erros JavaScript;
- rejeição de processo antigo existente apenas no armazenamento local;
- criação de processo por financiamento com moto em stock ou por encomenda;
- associação e libertação de moto entre processo, stock e cliente;
- atualização da moto do cliente refletida na Agenda da Oficina;
- recuperação de clientes e ligação correta do histórico de oficina;
- checklist bloqueada após validação e registo do utilizador;
- edição de checklist através do editor;
- retoma editável e integrada no stock;
- receção de moto, cheque, pagamento antecipado, fatura e VIN;
- ausências do Boss e notas no calendário;
- variantes na edição de stock e ficha do cliente.

Resultado da bateria principal: todos os testes acima passaram.

## Instalação desta revisão

Substituir no servidor:

- `app.js`
- `index.html`
- `api/state.php`
- `api/password-reset.php`
- `api/documents.php`

Executar uma vez no phpMyAdmin:

- `database/update-password-reset.sql`
- `database/update-process-documents.sql`

Não apagar nem substituir `private/config.php` no servidor.

## Recomendação estrutural seguinte

O sistema está agora protegido contra os erros de sincronização identificados. Para uma evolução de longo prazo, recomenda-se migrar gradualmente os arrays JSON de `app_settings` para tabelas relacionais próprias (`clients`, `client_bikes`, `processes`, `stock_bikes`, `bookings`, `orders`, `absences`). Isso permitirá chaves estrangeiras, histórico de alterações, pesquisas mais rápidas e recuperação granular. Esta migração deve ser feita com cópia integral da base de dados e ambiente de testes; não deve ser improvisada diretamente na instalação em produção.

## Operação segura

- manter o cron de backup diário ativo;
- confirmar periodicamente que os ficheiros de backup são criados e podem ser restaurados;
- testar esta revisão primeiro após um backup manual;
- usar sempre HTTPS;
- não colocar `private/config.php` ou backups dentro de repositórios públicos.
