# Integridade dos dados

O estado de negócio continua em documentos JSON dentro de `app_settings`. As escritas usam transação, `SELECT ... FOR UPDATE`, `_version` otimista e tombstones. Isto reduz perda de atualização, mas não oferece chaves estrangeiras entre clientes, processos, stock e agenda.

Riscos remanescentes:

- relações são validadas por código e podem divergir;
- montantes usam números JavaScript e JSON, não `DECIMAL` relacional;
- atualizações compostas entre recursos distintos não são uma única transação;
- filas locais dependem do browser até sincronização bem-sucedida.

Recomendação futura: migração faseada para tabelas relacionais, em modo dual-write, reconciliação por UID, comparação de contagens/hash e rollback para JSON. Não foi iniciada nesta intervenção.
