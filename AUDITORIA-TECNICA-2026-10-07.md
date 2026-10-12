# Auditoria técnica do E-Motogreen

Data: 07/10/2026

## Âmbito

- autenticação, sessões simultâneas e permissões;
- clientes, motos e prevenção de associações incorretas;
- processos, checklist, arquivo e stock;
- Agenda da Oficina, bloqueios e histórico;
- Contabilidade, cheques, notificações e relatórios;
- persistência e sincronização entre utilizadores;
- validação estática de JavaScript e estrutura HTML.

## Correções efetuadas nesta auditoria

1. Um bloqueio horário deixou de aplicar a cor de bloqueio ao dia completo. Apenas o intervalo selecionado aparece assinalado. O fundo integral só é usado no bloqueio das 09:00 às 20:00.
2. Foram desativadas as recuperações temporárias de processos específicos, evitando que rotinas antigas possam voltar a criar processos eliminados.
3. A associação automática entre uma moto de stock e um processo passou a exigir correspondência inequívoca da moto. Já não associa apenas porque o cliente tem um único processo pendente.
4. A ligação de marcações a clientes passou a dar prioridade ao ID e telefone. Um nome igual já não sobrepõe um telefone diferente.
5. A mesma proteção foi aplicada no servidor, impedindo que o carregamento ou gravação volte a ligar uma marcação ao cliente errado pelo nome.
6. Roles desconhecidos deixam de receber acesso por omissão.
7. Apenas o Boss pode eliminar ausências de outros utilizadores. Admin e Funcionário ficam limitados às próprias ausências.

## Validações executadas

- sintaxe integral do `app.js` com `node --check`;
- inexistência de IDs HTML estáticos duplicados;
- migração da checklist antiga para o novo passo “Documentação enviada”;
- separação de processos ativos e finalizados;
- cálculo de cheques Pendentes, A vencer e Vencidos;
- contagem diária até ao vencimento;
- exportação CSV/PDF segundo o filtro atual;
- alteração para o dia útil seguinte ao pedir matrícula;
- bloqueios de dia inteiro e intervalos horários sem sobreposição;
- prevenção de marcações em intervalos bloqueados;
- persistência por UID e eliminação explícita no servidor;
- filtragem de férias e faltas segundo o cargo.

## Resultado

Não foram encontrados erros de sintaxe JavaScript nem conflitos de IDs HTML. As falhas de associação e permissões identificadas foram corrigidas. Esta revisão não apaga nem recria dados existentes.

## Limitações e recomendações

- A base de dados funcional continua armazenada em arrays JSON dentro de `app_settings`. Funciona para a dimensão atual, mas duas pessoas a editar exatamente o mesmo registo ao mesmo tempo continuam sujeitas à regra “última gravação vence”. A evolução recomendada é migrar gradualmente para tabelas relacionais.
- “Dia útil seguinte” considera segunda a sexta-feira. Feriados nacionais ou municipais exigem um calendário de feriados próprio.
- A auditoria local não substitui um teste final no servidor: após o upload, deve testar login simultâneo, criação de uma marcação, bloqueio horário e envio SMTP.
- Manter o backup diário da base de dados e confirmar periodicamente uma restauração de teste.
