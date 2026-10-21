# Estabilização funcional

Estado em 21/10/2026. Nenhuma alteração foi aplicada à produção.

| Módulo | Estado | Evidência / próximo passo |
|---|---|---|
| Login e sessões | Ainda não testado E2E | Casos Playwright e HTTP preparados |
| Permissões | Corrigido, aguardando validação | deny-by-default e Contabilidade atribuível |
| Clientes | Ainda não testado integralmente | E2E de refresh e propagação para Oficina preparado |
| Processos | Corrigido, aguardando validação | AUD-008 preço/base/acessórios; cenário venda pendente |
| Retomas | Ainda não testado integralmente | dados sintéticos e cálculo preparados |
| Stock | Ainda não testado integralmente | relação processo/cliente coberta no E2E preparado |
| Oficina | Ainda não testado integralmente | propagação de cliente preparada; PDF/conclusão pendentes |
| Agenda | Ainda não testado | Test Drive/Renting/Evento requerem E2E adicional |
| Encomendas | Ainda não testado | margens, métodos e persistência pendentes |
| Contabilidade | Ainda não testado | cheque/vencimento/email/exportações pendentes |
| Equipa | Ainda não testado | visibilidade Boss/Funcionário pendente |
| Concorrência | Teste preparado | duas sessões e HTTP 409; execução pendente |
| Android | Validado apenas estaticamente | domínio e recursos PT/IT; build real pendente |

## Implementação efetiva do frontend

Foram inventariadas 151 ocorrências de atribuições de handlers, listeners e wrappers. Entre as redefinições diretas atualmente presentes contam-se `openProcessEditor` (6), `bindAgendaCalendar` (4), `calendarDay` (4), `detail` (4), `openStockDataEditor` (2) e `monthCalendarView` (3). O inventário com linhas de origem está em `app-js-handlers-inventory.txt`.

As redefinições formam cadeias de wrappers e a última atribuição é a implementação pública efetiva. Não foram removidas nesta fase. Cada consolidação futura deve ser feita por domínio e acompanhada por E2E equivalente.

## Impedimento

O Docker Engine continua indisponível. Assim, os estados “Validado com testes executados” ainda não podem ser atribuídos aos fluxos reais. O CRM não está pronto para integração enquanto a suíte isolada não for executada e os cenários A–H não forem concluídos.

## Evidência executada nesta fase

- `node --check app.js`: concluído sem erros.
- `node --check tests/e2e/stability.spec.js`: concluído sem erros.
- `node tests/static-regression.mjs`: `Static regression checks: OK`.
- `docker compose -f docker-compose.audit.yml config --quiet`: configuração válida.
- `git diff --check`: sem erros de whitespace (apenas avisos de normalização LF/CRLF).
- `docker version`: cliente 29.7.2 encontrado; servidor indisponível em `dockerDesktopLinuxEngine`.
- PHP, MySQL e MariaDB não estão instalados no host, pelo que não existe uma segunda via local para executar a integração.

## Como desbloquear a validação

Instalar ou iniciar o Docker Desktop com contentores Linux e aguardar até `docker info` apresentar a secção `Server`. Depois executar os quatro comandos documentados em `TESTES-REGRESSAO.md`. O ambiente usa apenas a base `emoto_audit`, utilizadores `admin_test`/`employee_test`, endereços `.invalid` e volumes Docker isolados.
