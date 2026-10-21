# Plano de deploy e rollback

1. Criar backup completo da produção e descarregá-lo para localização externa.
2. Testar o pacote numa cópia isolada com PHP 8.2, PDO MySQL e ZipArchive.
3. Confirmar contagens de `users`, `app_settings`, documentos, sessões e logs.
4. Testar login dos três roles e todos os painéis.
5. Testar criação/edição concorrente de cliente, processo, stock e oficina.
6. Criar e descarregar backup pelo painel; validar por CLI.
7. Restaurar esse ZIP numa segunda instalação e comparar inventário, IDs e hashes.
8. Só depois agendar janela de atualização e colocar os ficheiros.

Rollback: retirar a versão nova, repor os ficheiros anteriores e, apenas se os dados tiverem sido alterados, restaurar o backup pré-deploy validado. Não executar scripts SQL globais nem limpar `app_settings`.
