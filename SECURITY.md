# Segurança

Não publique os arquivos `config/app.local.php` e `config/database.local.php`. Eles são criados na instalação e podem conter configurações privadas e credenciais do banco de dados.

Para produção:

- use HTTPS;
- mantenha PHP e MySQL/MariaDB atualizados;
- utilize uma senha exclusiva para o banco;
- mantenha backups fora da pasta pública;
- revise o painel de auditoria;
- teste o scanner com os aparelhos realmente usados pela escola antes de uso oficial.

Se encontrar uma falha de segurança, evite publicar credenciais ou dados escolares em uma issue pública.
