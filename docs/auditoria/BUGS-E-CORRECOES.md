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

### AUD-009 — pedido de matrícula podia alterar o cheque de outra moto

Causa: `cheque-notifications.php` aceitava qualquer `stockId` explícito sem validar a relação com o processo. Correção: a moto tem agora de corresponder simultaneamente ao ID pedido e ao `processId`, `processUid` ou `stockBikeId` do processo. Stock e fila são gravados numa transação, a fila é idempotente e partilha o bloqueio do cron. Testes HTTP de moto incorreta, repetição e fila única foram preparados.

### AUD-010 — valor do cheque ausente na receção

Causa: uma redefinição posterior de `openChequeArrivalModal` substituía a versão que continha `chequeAmount`. Correção: a implementação efetiva volta a apresentar, validar e persistir o valor, removendo-o quando o método não é cheque.

### AUD-011 — implementação base do cargo Boss inconsistente

Causa: o backend e a listagem reconheciam `boss`, mas a implementação base do seletor permitia apenas Funcionário e Admin. Um wrapper posterior reparava o campo em tempo de execução, deixando o comportamento dependente da ordem do ficheiro. Correção: a implementação base passou a incluir Boss e a ocultar permissões individuais para cargos de acesso global; o wrapper compatível permanece sem alterar dados.

### AUD-012 — painéis interligados recebiam estado incompleto

Causa: o backend associava cada recurso JSON a um único menu. Um funcionário com Oficina, mas sem Clientes, não recebia a lista necessária para marcar clientes existentes; Processos/Financiamentos tinham o mesmo problema com Stock e Catálogo. Correção: matrizes distintas de leitura e escrita por dependência, mantendo os menus ocultos e autorizando apenas as operações exigidas pelos fluxos permitidos.

### AUD-013 — retoma podia ser associada ao processo errado

Causa: o listener da retoma usava `processes[0]` depois do submit. Se a criação fosse recusada (por exemplo, telefone duplicado), a microtarefa podia alterar o primeiro processo existente. Correção: o listener guarda o conjunto anterior e só cria a retoma se encontrar um novo processo por identidade de objeto. A retoma recebe ainda UID e ligação estável ao UID do processo.

### AUD-014 — relações dependiam apenas de IDs editáveis/concorrentes

Causa: Stock↔Processo e Processo↔Cliente dependiam sobretudo dos IDs visíveis. Uma colisão resolvida no servidor podia deixar outro recurso com o ID antigo. Correção incremental e compatível: novos vínculos guardam também `processUid`/`clientUid`; a leitura reconcilia IDs atuais pelo UID, mantendo fallback para registos antigos.

### AUD-015 — acessórios somados duas vezes em processos legados

Causa: em processos sem `basePrice`, o campo histórico `price` já contém o total acordado, mas `processTotal()` e o relatório diário adicionavam novamente `accessories`. Correção: registos modernos continuam a calcular `basePrice + accessories`; o fallback legado usa apenas `price`. Os dados persistidos não são reescritos.

## Identificado e pendente

- O frontend contém funções específicas de recuperação histórica e várias redefinições de funções. Estão atualmente inativas ou dependentes da ordem do ficheiro. A remoção/refatoração exige testes funcionais equivalentes.
- A reconciliação automática de clientes/marcações e IDs merece testes com cópias anonimizadas da base real.
