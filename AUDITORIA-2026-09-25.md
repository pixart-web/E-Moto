# Auditoria funcional — 25/09/2026

Foram novamente verificados os fluxos de autenticação, permissões, clientes, processos, checklist, pagamentos, financiamento, stock, encomendas, oficina, férias e Test Drives.

## Integridade confirmada

- marcar um processo como Entregue não o apaga da base de dados;
- apenas o retira das listas operacionais de processos ativos;
- entrega, stock e moto do cliente ficam associados pelo cliente e pela moto correta;
- a data da compra fica guardada na moto do cliente;
- o contador do Dashboard utiliza a data efetiva da entrega;
- os dados anteriores não são reinicializados nem substituídos pela atualização;
- Test Drives utilizam UIDs próprios e sincronização registo a registo;
- alteração ou eliminação de Test Drive não interfere com Oficina, Clientes ou Processos;
- intervalo da Oficina está coerente entre calendário, formulário e clique numa hora;
- permissões de Test Drives podem ser ligadas/desligadas por funcionário;
- Admin e Boss mantêm o acesso operacional previsto.

## Testes executados

- entrega e arquivo visual do processo;
- contador semanal de entregas;
- criação da data de compra na ficha do cliente;
- remoção do valor `null`;
- calendário da Oficina até às 20:00;
- limite de hora no formulário de Oficina;
- criação de Test Drive com todos os campos e modelos pedidos;
- persistência do contacto em formato telefone ou email;
- navegação geral e ligações Processo ↔ Stock ↔ Cliente ↔ Oficina.

Os testes principais passaram. Para produção, deve continuar ativo o backup diário e deve ser feito um backup manual antes de substituir os ficheiros.
