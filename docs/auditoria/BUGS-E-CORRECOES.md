# Bugs e correções

## Corrigido

### AUD-002 — permissão de Contabilidade

Causa: `contabilidade` existia no frontend, mas não em `$allowedPermissions` de `api/admin-users.php`. Impacto: um Admin não conseguia atribuir corretamente este painel a funcionários. Correção: inclusão na lista validada pelo backend. Teste: `tests/static-regression.mjs`.

### AUD-001 — backup incompleto

Causa: o cron guardava apenas linhas SQL serializadas num JSON. Não incluía documentos, inventário nem integridade criptográfica. Correção: ZIP v2 privado com snapshot, documentos, manifesto, SHA-256 e validação antes de publicação.

### AUD-007 — role desconhecido com acesso visual permissivo

Causa: a condição de fallback no frontend permitia todos os painéis não administrativos a qualquer role diferente de `employee`. Correção: política deny-by-default; apenas Admin, Boss e Funcionário reconhecidos passam na navegação. O backend já fazia negação por omissão.

## Identificado e pendente

- O frontend contém funções específicas de recuperação histórica e várias redefinições de funções. Estão atualmente inativas ou dependentes da ordem do ficheiro. A remoção/refatoração exige testes funcionais equivalentes.
- A reconciliação automática de clientes/marcações e IDs merece testes com cópias anonimizadas da base real.
