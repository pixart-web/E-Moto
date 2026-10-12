SET NAMES utf8mb4;

ALTER TABLE users ADD COLUMN permissions JSON NULL AFTER role;

UPDATE users
SET role = 'admin', permissions = NULL
WHERE LOWER(username) = 'tiago';

UPDATE users
SET role = 'employee',
    permissions = JSON_ARRAY('dashboard','processos','clientes','motos','catalogo','encomendas','agenda','financiamentos','equipa')
WHERE LOWER(username) <> 'tiago';
