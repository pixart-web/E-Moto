# Backup e restauro

## Backup

O módulo Admin cria ZIPs fora de `public_html`, contendo `manifest.json`, `database.json` e documentos privados. Sessões e tentativas de login são excluídas. Cada entrada tem SHA-256 e tamanho. O ZIP só é publicado após validação.

Cron recomendado:

```text
0 2 * * * /usr/local/bin/php /home/emotomotogreen/public_html/cron/daily-backup.php >> /home/emotomotogreen/backup.log 2>&1
```

Configurar `backups.retention_count` no `private/config.php`. A retenção nunca fica abaixo de duas cópias e a única cópia válida não pode ser eliminada pela interface.

## Verificação e restauro CLI

```text
/usr/local/bin/php tools/verify-backup.php /caminho/emoto-backup-....zip
/usr/local/bin/php tools/restore-backup.php /caminho/emoto-backup-....zip
/usr/local/bin/php tools/restore-backup.php /caminho/emoto-backup-....zip --confirm=RESTORE
```

Sem `--confirm=RESTORE`, a ferramenta apenas valida e mostra o plano. Com confirmação, cria primeiro um backup `pre-restauro`, usa staging para documentos, transação para dados e valida contagens. Deve ser testada numa instalação isolada antes de qualquer uso em produção.

Não são incluídos segredos. Numa recuperação total, recriar `private/config.php` por canal seguro.

## Teste isolado

Instalar/iniciar Docker Desktop com contentores Linux e executar os três comandos da secção Docker em `TESTES-REGRESSAO.md`. Todos os dados, emails, passwords e documentos definidos em `tests/integration/` são artificiais. O comando final `down -v` elimina os volumes de teste.
