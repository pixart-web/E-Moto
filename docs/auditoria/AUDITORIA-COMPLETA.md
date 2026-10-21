# Auditoria completa

Branch: `audit/emoto-integrity-backup`. Base auditada: `f7ac2e0`. A produção e a base de dados real não foram alteradas.

## Arquitetura confirmada

- Frontend monolítico em `app.js`, `index.html` e `styles.css`.
- Backend PHP 8.2 com PDO/MySQL, sessões por token e cookie, CSRF e autorização por painel.
- Estado funcional persistido sobretudo como arrays JSON em `app_settings` (`state_clients`, `state_processes`, `state_stockBikes`, etc.).
- Documentos de processos em tabela própria e ficheiros privados.
- Relatórios e alertas por scripts CLI/cron.
- Aplicação Android é um invólucro nativo para a plataforma/agenda live.

## Resultados principais

| ID | Gravidade | Evidência | Estado |
|---|---|---|---|
| AUD-001 | Alta | O backup antigo gerava apenas JSON, sem ZIP, manifesto, ficheiros ou verificação | Corrigido em branch |
| AUD-002 | Média | `api/admin-users.php` omitia `contabilidade` das permissões atribuíveis | Corrigido e coberto por teste estático |
| AUD-003 | Alta | Restauro integral não existia | Implementada ferramenta CLI com validação, confirmação e backup pré-restauro; requer teste isolado real |
| AUD-004 | Média | Funções antigas de recuperação de clientes específicos permanecem em `app.js` | Não são chamadas; remoção proposta, não executada para preservar compatibilidade |
| AUD-005 | Alta | Um único documento JS com várias sobreposições de funções aumenta risco de regressão | Pendente; refatoração faseada requer aprovação |
| AUD-006 | Média | Não existe suíte funcional automatizada nem ambiente MySQL de teste no repositório | Criado teste estático inicial; integração real pendente |

Não foi encontrada evidência suficiente para declarar cobertura integral ou ausência de bugs.
