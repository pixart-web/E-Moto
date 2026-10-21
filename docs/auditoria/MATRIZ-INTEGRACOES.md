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
