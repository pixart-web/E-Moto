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

## Ambiente Docker isolado preparado

O ficheiro `docker-compose.audit.yml` cria PHP 8.2/Apache e MariaDB 11.4, apenas em `127.0.0.1:18080`, com volumes exclusivos e credenciais sintéticas. Executar:

```text
docker compose -f docker-compose.audit.yml up -d --build
docker compose -f docker-compose.audit.yml exec app sh /var/www/html/tests/integration/run-tests.sh
docker compose -f docker-compose.audit.yml --profile e2e run --rm e2e
docker compose -f docker-compose.audit.yml down -v
```

A suíte prepara clientes, motos, stock, processo com retoma, oficina, renting, encomenda, ausência, cheque e documento sintéticos. Testa criação, manifesto, checksums, documento, corrupção, path traversal, falha transacional/rollback, restauro confirmado, IDs/relações e bloqueio de utilizadores não autorizados.

A suíte Playwright valida persistência após refresh, propagação cliente→oficina, permissões frontend/backend, conflito otimista entre duas sessões e coerência processo→stock→cliente→retoma.

Em 21/10/2026 a execução ficou bloqueada: a CLI Docker 29.7.2 existe, mas não há motor Docker acessível nem executável Docker Desktop encontrado. Nenhum contentor foi iniciado.
