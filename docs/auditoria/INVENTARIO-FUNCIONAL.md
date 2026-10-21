# Inventário funcional

- Autenticação, recuperação e alteração de password; sessões simultâneas.
- Utilizadores Admin, Boss e Funcionário; permissões por painel.
- Dashboard com processos, entregas, oficina e stock.
- Clientes: pesquisa, edição, eliminação, várias motos e histórico de oficina/compra.
- Processos: clientes novos/existentes, pagamentos, acessórios, retoma, financiamento, checklist, documentos, stock e arquivo.
- Catálogo, stock, variantes, encomenda/receção, VIN, fatura, cheques e destino.
- Oficina: vistas diária/semanal/mensal, bloqueios, notas, cliente novo/existente, conclusão, fatura e PDF.
- Agenda: Test Drive, Renting e Evento, intervalos e lembretes.
- Encomendas: dados comerciais, custos, pagamentos, lucro, margem e PDF.
- Contabilidade: cheques, vencimentos, filtros, totais e exportações.
- Equipa: férias, faltas, notas e visibilidade por role.
- Relatórios cron: operação diária, encomendas, cheques, renting/eventos e backups.
- Android: seleção Plataforma/Agenda Live em PT e IT.
- Novo: Backups e Recuperação para Admin.

## Ações, persistência e endpoints

| Módulo | Ações principais | Estado persistido / endpoints |
|---|---|---|
| Clientes | listar, pesquisar, paginar, criar, editar, apagar, adicionar/editar/remover motos, abrir histórico | `state_clients`; `state-write.php`, `state-delete.php` |
| Processos | criar, editar, cancelar, apagar, checklist, pagamentos, retoma, financiamento, documentos, associar stock, arquivar | `state_processes`; `documents.php`; stock/clientes relacionados |
| Stock | criar nova/usada, editar dados/VIN/variante/fatura, associar cliente/processo, receber, apagar, pesquisar/paginar | `state_stockBikes` |
| Catálogo | marca, modelo e variantes; adicionar e remover | `state_bikeCatalog` |
| Oficina | vistas dia/semana/mês, criar/editar/apagar marcação, concluir, fatura, notas, bloqueios e PDF | `state_bookings`, `state_officeBlocks`, `state_officeNotes` |
| Agenda | criar/editar/apagar Test Drive, Renting e Evento; intervalos no calendário | `state_testDrives`; cron `renting-reminders.php` |
| Encomendas | criar, editar, apagar, estado, custos, vendas, portes, comissão, margem e PDF | `state_orders`; cron de pendentes |
| Contabilidade | filtros de cheques, vencimentos, totais, CSV/PDF e aviso antecipado | `state_stockBikes`, `cheque_notification_logs`, endpoint/cron de cheques |
| Equipa | férias, faltas, notas, visibilidade e eliminação | `state_absences`, `team-users.php` |
| Administração | utilizadores, role, permissões, password e ativação | tabelas `users`, `user_sessions`; `admin-users.php`, `auth.php` |

Todas as escritas remotas dos recursos JSON passam por `_version`, transação e tombstones no backend. Operações que alteram vários recursos continuam a ser chamadas separadas e são um risco de atomicidade a testar.
