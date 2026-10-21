# Bugs e correções

## Corrigido

### AUD-002 — permissão de Contabilidade

Causa: `contabilidade` existia no frontend, mas não em `$allowedPermissions` de `api/admin-users.php`. Impacto: um Admin não conseguia atribuir corretamente este painel a funcionários. Correção: inclusão na lista validada pelo backend. Teste: `tests/static-regression.mjs`.

### AUD-001 — backup incompleto

Causa: o cron guardava apenas linhas SQL serializadas num JSON. Não incluía documentos, inventário nem integridade criptográfica. Correção: ZIP v2 privado com snapshot, documentos, manifesto, SHA-256 e validação antes de publicação.

### AUD-007 — role desconhecido com acesso visual permissivo

Causa: a condição de fallback no frontend permitia todos os painéis não administrativos a qualquer role diferente de `employee`. Correção: política deny-by-default; apenas Admin, Boss e Funcionário reconhecidos passam na navegação. O backend já fazia negação por omissão.

### AUD-008 — edição do preço do processo não atualizava o valor efetivo

Causa: processos recentes guardam `basePrice`, `price` e `accessories`. O editor alterava `price`, enquanto `processTotal()` continuava a usar o `basePrice` antigo. Uma segunda sobreposição do editor substituía ainda o preço base legado pelo total e podia voltar a somar os acessórios. Impacto: total e dívida podiam ignorar a edição ou somar acessórios duas vezes. Correção: ficou uma única camada financeira no editor: mostra o preço base, grava `basePrice` e submete `price = basePrice + accessories`. Regressão estática adicionada; E2E preparado e pendente do motor Docker.

## Identificado e pendente

- O frontend contém funções específicas de recuperação histórica e várias redefinições de funções. Estão atualmente inativas ou dependentes da ordem do ficheiro. A remoção/refatoração exige testes funcionais equivalentes.
- A reconciliação automática de clientes/marcações e IDs merece testes com cópias anonimizadas da base real.
