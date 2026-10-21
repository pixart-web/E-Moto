# Testes de regressão

## Executados

- `node --check app.js` — sintaxe JavaScript.
- `node tests/static-regression.mjs` — retoma, concorrência, tombstones, checksums, diretório privado, permissão contabilística e ausência de passwords reais no exemplo.
- `git diff --check` — whitespace e conflitos.

## Não executados

- PHP lint: runtime PHP não está instalado neste computador.
- MySQL, SMTP e cron: não existe ambiente isolado configurado.
- Backup/restauro real: requer base de teste e extensão ZipArchive.
- Android build: depende de SDK/JDK local compatível.
- E2E multiutilizador: requer servidor de teste e dois browsers.

Estes itens impedem classificar a intervenção como integralmente validada.
