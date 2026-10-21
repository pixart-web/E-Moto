# Testes funcionais E2E

Esta suíte corre exclusivamente sobre o ambiente sintético de `docker-compose.audit.yml`.

```text
docker compose -f docker-compose.audit.yml up -d --build
docker compose -f docker-compose.audit.yml exec app sh /var/www/html/tests/integration/run-tests.sh
docker compose -f docker-compose.audit.yml --profile e2e run --rm e2e
docker compose -f docker-compose.audit.yml down -v
```

Não apontar `BASE_URL` para produção. A suíte cria e altera registos e pressupõe os dados sintéticos carregados por `seed-and-backup.php`.
