# Matriz de integrações

| Origem | Operação | Destino/efeito | Validação necessária |
|---|---|---|---|
| Cliente | editar moto | Oficina e histórico usam cliente/moto por `clientId` | E2E com edição após marcação |
| Processo | associar stock | `stockBikeId`, `processId` e `clientId` coerentes | concorrência entre dois utilizadores |
| Processo | retoma | cria moto usada em stock e abate `tradeInValue` | valores decimais e edição |
| Checklist | matrícula pedida | cheque associado passa a vencer e entra na fila de email | MySQL+SMTP isolado |
| Checklist | moto preparada | estado pronta para entrega | regressão dos estados |
| Entrega | finalizar processo | arquivo e data de compra no cliente | contagem Dashboard |
| Oficina | nova marcação | cliente existente ou criação; histórico por `clientId` | telefone duplicado |
| Stock | recebida | ativa dados de pagamento/cheque | pronto pagamento vs cheque |
| Ausência | criar/ler | Boss vê todos; restantes apenas próprios | backend já filtra |
| Backup | criar | BD + documentos + manifesto/checksums | restauro isolado obrigatório |

## Cenários funcionais obrigatórios

| Cenário | Entidades | Resultado esperado | Estado |
|---|---|---|---|
| A Cliente e venda | cliente→processo→stock→pagamentos→entrega | processo arquivado, stock entregue, compra no cliente e dashboard atualizado | Preparado parcialmente; não executado |
| B Retoma | processo→moto usada→stock→valores | uma única retoma e dívida correta após edição | Dados/teste financeiro preparados; não executado |
| C Oficina | cliente→moto→marcação→conclusão→PDF | ligação por UID/clientId e histórico coerente | Propagação preparada; não executado |
| D Encomendas/stock | encomenda→custos→receção→processo | margens e estados coerentes | Não testado |
| E Contabilidade | processo→cheque→matrícula→vencimento | estado, total, email e exportação coerentes | Não testado |
| F Agenda | Test Drive/Renting/Evento | edição, intervalo, sessão e lembrete coerentes | Não testado |
| G Equipa/permissões | Admin/Boss/Funcionário/inválido | backend e frontend negam/permitem igualmente | Casos preparados parcialmente |
| H Concorrência | duas sessões | 409 em versão antiga, sem ressurreição | Playwright preparado; não executado |
