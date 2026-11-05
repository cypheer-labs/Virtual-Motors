# Modelo seguro de publicação por FTP no VS Code

Este modelo é para a extensão `liximomo.vscode-sftp` e parte de um host que aceita FTP na porta 21. O protocolo FTP simples não cifra usuário, senha nem arquivos durante o transporte. Se a hospedagem oferecer FTPS, confirme os parâmetros com o provedor antes de ativar `secure`; não troque o protocolo por SFTP sem suporte do servidor.

## Configuração

1. Copie `sftp.json.example` para `.vscode/sftp.json` na raiz do projeto.
2. Preencha `host`, `username` e `remotePath` localmente. Não adicione `password`: a extensão pode solicitá-la ao conectar.
3. Mantenha `.vscode/` no `.gitignore`. Nunca envie `sftp.json` ao GitHub.
4. Primeiro publique somente um arquivo não sensível e confirme o diretório remoto correto.
5. Para atualizações, prefira `SFTP: Upload Active File` após revisar o arquivo. Mantenha `uploadOnSave` desligado.

`syncOption.delete` está definido como `false` para impedir que o comando Sync apague arquivos que existam apenas no servidor. Evite comandos de exclusão remota e comandos que ignorem a lista `ignore`. Os arquivos de configuração e dumps locais também estão excluídos de transferências normais. A cópia do projeto no servidor deve manter sua configuração de banco já existente; não baixe nem publique essa configuração sem necessidade.

## Configuração do banco

Guarde os arquivos reais de produção fora do Git. Para novas configurações, copie `config/database.config.php.example` para `config/database.config.php` (remova `.example`) e preencha as credenciais. A aplicação precisa carregar a configuração de produção que a hospedagem já usa; este modelo de FTP não cria nem corrige a conexão do banco.

A lista `ignore` ajuda a evitar uploads locais acidentais, mas não substitui backups do servidor. O perfil limita o modo de operação configurando upload manual e Sync sem exclusões.

## Migrations automáticas

FTP apenas transfere arquivos; ele não executa comandos SQL nem inicia tarefas no servidor. Depois de publicar `src/database.php` e `src/schemamigrationrunner.php`, a aplicação verifica e aplica as migrations de UID/carrinho e versão de autenticação na primeira conexão ao banco. A versão de autenticação permite invalidar sessões existentes após redefinir uma senha. O controle `vm_schema_migrations` impede reaplicações, e um lock do MySQL evita execuções simultâneas. As migrations SQL continuam excluídas dos uploads normais; a execução automática usa a implementação PHP versionada.

Antes de enviar esses arquivos, confirme que a configuração de produção existente aponta para o banco correto, que o usuário MySQL tem permissões de `CREATE` e `ALTER`, e que `carros` já usa InnoDB. Faça backup do banco de produção. Se `carros` ainda não usa InnoDB, aplique primeiro `database/migrations/20261101_convert_cars_to_innodb.sql` pelo painel do provedor; o runner interrompe a execução com uma mensagem explícita se esse pré-requisito faltar. Após o upload FTP, a migration é aplicada quando a hospedagem receber a próxima requisição à aplicação.