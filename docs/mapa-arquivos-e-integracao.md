# Mapa de arquivos e integração

Este documento apresenta os arquivos da aplicação, os principais fluxos entre
eles e a localização do banco de dados. Arquivos de terceiros em `vendor/` e
imagens geradas em `public/uploads/perfis/` são descritos por diretório, em vez
de serem listados individualmente.

## Visão geral da aplicação

```text
Navegador
  ├─ index.php + public/style.css + public/script.js
  │    └─ src/pesquisa.php ── CarroRepository ── tabela carros
  ├─ páginas PHP em src/ e admin/
  │    ├─ páginas ── repositories/services ── tabelas MySQL
  │    └─ header.php ── links, sessão e menu compartilhados
  └─ uploads de perfil ── public/uploads/perfis/

src/database.php ── PDO ── MySQL/MariaDB
       └─ src/schemamigrationrunner.php ── migrations automáticas selecionadas
```

O PHP renderiza as páginas e processa formulários. O JavaScript melhora a
interação no navegador e consulta endpoints PHP quando necessário. Os
repositories concentram as consultas SQL usadas pelos fluxos de usuários,
veículos, carrinho, pedidos e avaliações.

## Localização do banco de dados

Há três coisas diferentes que podem ser chamadas de “banco”:

1. **Conexão e seleção do banco:** [`src/database.php`](../src/database.php)
   cria uma conexão PDO. Ele lê o arquivo privado
   `config/database.config.php`, se existir; sem esse arquivo, usa os valores
   padrão definidos no próprio código. O host seleciona a configuração local
   para `localhost` ou `127.0.0.1`.
2. **Banco lógico em uso nesta máquina:** a consulta ao MySQL local retornou
   `DATABASE() = loja_carros`, host `127.0.0.1` e porta `3306`.
3. **Diretório físico do serviço MySQL do XAMPP:** `@@datadir` retornou
   `C:\xampp\mysql\data\`. O schema `loja_carros` é administrado pelo MySQL
   dentro desse diretório; não é um arquivo `.sql` único e não se deve editar
   os arquivos físicos manualmente.

Os arquivos `.sql` do repositório são **dumps para importação** ou **migrations
para alterar o schema**. Eles não são o banco ativo. Os dumps estão em
[`dump/`](../dump/) e as migrations em
[`database/migrations/`](../database/migrations/). A cópia real de
`config/database.config.php`, caso criada, é privada e ignorada pelo Git; não
publique suas credenciais.

O arquivo de exemplo da conexão está em
[`config/database.config.php.example`](../config/database.config.php.example).
Na inspeção desta cópia, só o exemplo estava presente; por isso a conexão local
estava usando os padrões de `src/database.php`.

### Tabelas e relações principais

```text
usuario ──< pedido ──< item_pedido >── carros
   │                                  │
   └────────< carrinho_usuario >──────┘

usuario ──< avaliacao >── pedido
```

- `usuario`: contas, contatos, UID, tipo de acesso, senha protegida por hash e
  dados de perfil.
- `carros`: catálogo, preço, imagens e estoque.
- `carrinho_usuario`: quantidade de cada veículo no carrinho persistente de uma
  conta.
- `pedido` e `item_pedido`: compras e itens; o item guarda cópias do nome,
  origem e preço para preservar o histórico.
- `avaliacao`: avaliação associada a um pedido e usuário.
- `vm_schema_migrations`: versões das migrations que o executor automático
  registra.

O executor em [`src/schemamigrationrunner.php`](../src/schemamigrationrunner.php)
aplica automaticamente as migrations de UID/carrinho persistente, versão de
sessão e estoque. As outras migrations SQL devem ser aplicadas conforme o estado
do banco; confira
[`docs/mapa-banco-de-dados.md`](./mapa-banco-de-dados.md) antes de importar ou
reaplicar scripts.

Na inspeção local de 17/11/2026, a tabela `avaliacao` estava ausente, o que fazia
o endpoint do catálogo responder HTTP 500. A migration
`20261105_create_avaliacao.sql` foi aplicada ao MySQL local e o endpoint passou
a responder HTTP 200. Para outros bancos, aplique apenas uma das migrations
`20261029_create_avaliacao.sql` ou `20261105_create_avaliacao.sql`, pois ambas
criam a mesma tabela. As colunas de perfil também foram adicionadas manualmente
ao MySQL local; o executor automático não aplica essas migrations.

## Fluxos entre arquivos

### Catálogo e pesquisa

1. [`index.php`](../index.php) monta a página inicial e inclui o cabeçalho.
2. [`public/script.js`](../public/script.js) envia os filtros para
   [`src/pesquisa.php`](../src/pesquisa.php).
3. `pesquisa.php` valida os parâmetros e chama
   [`src/carrorepository.php`](../src/carrorepository.php).
4. `CarroRepository` consulta `carros` e agrega avaliações. Se a consulta ao
   catálogo falhar, o JavaScript apresenta os veículos demonstrativos.
5. Os cards permitem enviar ações para `src/carrinho.php` ou
   `src/pagamento.php`.

### Cadastro, login e conta

1. `src/cadastro.php` valida os dados e usa `AuthService` e `UserRepository`
   para criar a conta em `usuario`.
2. `src/login.php` autentica por `AuthService`, preenche a sessão e sincroniza
   o carrinho persistente com `CarrinhoRepository`.
3. `src/authsession.php` valida a conta e a versão de autenticação. O cabeçalho
   `src/header.php` é compartilhado pelas páginas e apresenta os links conforme
   a sessão e o tipo de usuário.
4. `src/perfil.php` mostra o perfil; `src/personalizar-perfil.php` altera nome,
   bio e imagens; `src/minha-conta.php` mostra e atualiza dados pessoais,
   contato e senha.
5. `src/logout.php` encerra a sessão.

Os repositories desses fluxos são `src/userrepository.php` e
`src/carrinhorepository.php`. A troca de senha incrementa `auth_version`, o que
permite invalidar as outras sessões da conta.

### Carrinho, checkout e histórico

1. `src/carrinho.php` valida as ações do usuário, consulta os veículos por
   `CarroRepository` e persiste o carrinho com `CarrinhoRepository`.
2. `src/pagamento.php` prepara o checkout demonstrativo, verifica estoque e
   mantém os dados temporários do pedido na sessão.
3. `src/consulta_cep.php` fornece o endpoint JSON que consulta a API ViaCEP;
   `public/script.js` preenche os campos de endereço no checkout.
4. `src/qrcode.php` permite revisar/confirmar o pedido demonstrativo e chama
   `PedidoRepository` para gravar `pedido` e `item_pedido`.
5. `src/minhas-compras.php` consulta o histórico; `src/perfil.php` mostra
   compras recentes. `src/avaliacao.php` e
   `src/avaliacaorepository.php` controlam as avaliações associadas a pedidos.

O sistema apresenta opções de pagamento para demonstração; não processa
pagamentos reais.

### Administração

1. `src/admin_guard.php` exige sessão autenticada e confere no banco se a conta
   tem `tipo_usuario = admin`.
2. `admin/dashboard.php` consulta resumos de veículos, usuários e pedidos.
3. `admin/produtos.php` administra linhas de `carros`.
4. `admin/usuarios.php` administra `usuario`, protegendo operações como
   exclusão de usuários com pedidos.
5. `admin/pedidos.php` lista e atualiza pedidos usando
   `src/pedidorepository.php`.

As páginas administrativas usam `admin/admin.css`; as demais compartilham
`public/style.css`.

## Mapa dos arquivos do projeto

### Raiz

| Arquivo | Finalidade |
| --- | --- |
| `index.php` | Entrada e página inicial: inclui cabeçalho e carrega o catálogo. |
| `composer.json` | Dependências PHP e comandos de PHPStan/PHPCS. |
| `composer.lock` | Versões exatas das dependências Composer. |
| `CODEOWNERS` | Responsáveis por revisar arquivos no GitHub. |
| `.gitattributes` | Regras de normalização de arquivos, incluindo finais de linha PHP. |
| `.gitignore` | Ignora dependências instaladas e configurações/segredos locais. |
| `phpcs.xml` | Configura o padrão PSR-12 para o PHPCS. |
| `phpstan.neon` | Configura a análise estática PHPStan. |
| `LICENSE` | Licença do projeto. |
| `test.php` | Protótipo isolado para testar envio de e-mail; não faz parte do cadastro nem usa o banco. |

### `admin/`

| Arquivo | Finalidade |
| --- | --- |
| `admin.css` | Estilos do painel administrativo. |
| `dashboard.php` | Indicadores e resumos administrativos. |
| `pedidos.php` | Consulta e atualização administrativa de pedidos. |
| `produtos.php` | Cadastro, edição, busca e remoção de veículos. |
| `usuarios.php` | Busca, edição, cadastro e remoção de usuários. |

### `src/` — páginas e serviços PHP

| Arquivo | Finalidade |
| --- | --- |
| `admin_guard.php` | Autenticação e autorização exclusiva de administradores. |
| `authservice.php` | Registro de contas e validação de credenciais. |
| `authsession.php` | Confere sessão, conta e `auth_version`; invalida sessões expiradas. |
| `avaliacao.php` | Lista e registra avaliações de pedidos. |
| `avaliacaorepository.php` | Consultas e gravações da tabela `avaliacao`. |
| `cadastro.php` | Formulário e processamento do cadastro. |
| `calcula.php` | Utilitário legado e isolado para cálculo simples de parcelas; não participa do checkout atual. |
| `carrinho.php` | Tela e ações do carrinho. |
| `carrinhorepository.php` | Lê, mescla e persiste o carrinho em `carrinho_usuario`. |
| `carrorepository.php` | Pesquisa e carrega veículos e dados agregados de avaliações. |
| `conexao.php` | Wrapper legado da conexão PDO; as páginas atuais usam `Database` diretamente. |
| `consulta_cep.php` | Endpoint JSON que consulta ViaCEP. |
| `database.php` | Seleção da configuração e conexão PDO do aplicativo. |
| `gtm.php` | Inclusão do trecho de Google Tag Manager nas páginas. |
| `header.php` | Cabeçalho, navegação, menu de usuário e atalhos compartilhados. |
| `login.php` | Formulário de login e inicialização da sessão. |
| `logout.php` | Encerramento da sessão e retorno à página inicial. |
| `minha-conta.php` | Consulta e alteração de dados pessoais, contato e senha. |
| `minhas-compras.php` | Histórico e detalhes das compras do usuário. |
| `pagamento.php` | Validação do carrinho e preparação do checkout demonstrativo. |
| `pedidorepository.php` | Criação transacional, consulta, atualização e cancelamento de pedidos. |
| `perfil.php` | Exibição do perfil público ou do próprio usuário e compras recentes. |
| `personalizar-perfil.php` | Atualização de nome, bio e imagens de perfil. |
| `pesquisa.php` | Endpoint JSON do catálogo e dos filtros de veículos. |
| `qrcode.php` | Revisão/confirmação do pedido demonstrativo e encaminhamento para avaliação. |
| `schemamigrationrunner.php` | Verifica e aplica um conjunto de migrations automáticas. |
| `sobre-nos.php` | Página institucional. |
| `termos-de-uso.php` | Página de termos de uso. |
| `userrepository.php` | Consultas, cadastro e atualização de contas em `usuario`. |

### `public/` e `assets/`

| Caminho/arquivo | Finalidade |
| --- | --- |
| `public/style.css` | Estilos da vitrine, conta, checkout e páginas públicas. |
| `public/script.js` | Menu, tema, catálogo, busca, CEP e interações do checkout. |
| `public/uploads/perfis/` | Imagens de perfil enviadas pelos usuários; arquivos gerados em execução. |
| `assets/cart-icon.png` | Ícone do carrinho. |
| `assets/image.png` | Imagem genérica/fallback de veículo. |
| `assets/qrcode-pix.webp` | Recurso visual de QR Code demonstrativo. |
| `assets/search-icon.png` | Ícone da busca. |
| `assets/virtual-motors.png` | Logotipo principal. |
| `assets/vm.png` | Ícone/favicon da aplicação. |

### `config/`

| Arquivo | Finalidade |
| --- | --- |
| `.htaccess` | Bloqueia acesso HTTP direto à pasta de configuração no Apache. |
| `database.config.php.example` | Modelo de configuração local e de produção para o MySQL. |
| `config.producao.php.example` | Modelo legado/de produção. |
| `email-test.config.example.php` | Modelo de configuração SMTP do protótipo isolado de e-mail. |

As versões preenchidas dessas configurações são privadas e ignoradas pelo Git.

### `database/migrations/`

| Arquivo | Finalidade |
| --- | --- |
| `20261015_add_car_image_url.sql` | Adiciona URL de imagem a `carros`. |
| `20261022_add_purchase_history.sql` | Adiciona datas, endereço e cópia dos dados dos itens ao histórico de pedidos. |
| `20261029_create_avaliacao.sql` | Cria a tabela de avaliações. |
| `20261101_convert_cars_to_innodb.sql` | Converte `carros` para InnoDB. |
| `20261102_add_account_uid_and_persistent_cart.sql` | Adiciona UID e cria o carrinho persistente. |
| `20261102_add_auth_session_version.sql` | Adiciona a versão usada para invalidar sessões. |
| `20261103_add_profile_bio_and_box_color.sql` | Adiciona bio e uma coluna de cor de fundo posteriormente removida. |
| `20261103_add_profile_customization.sql` | Adiciona foto, banner e cor do nome do perfil. |
| `20261105_add_product_stock.sql` | Adiciona estoque aos veículos. |
| `20261105_create_avaliacao.sql` | Outra versão do script de criação de avaliações; confira o estado do banco antes de usar, pois cria a mesma tabela. |
| `20261105_remove_profile_box_color.sql` | Remove a coluna de cor de fundo adicionada pela migration anterior. |

### `dump/`

| Caminho/arquivo | Finalidade |
| --- | --- |
| `dump/virtualmotors/loja_carros_carros.sql` | Schema e veículos iniciais. |
| `dump/virtualmotors/loja_carros_usuario.sql` | Schema inicial da tabela de usuários. |
| `dump/virtualmotors/loja_carros_pedido.sql` | Schema inicial de pedidos. |
| `dump/virtualmotors/loja_carros_item_pedido.sql` | Schema inicial dos itens dos pedidos. |
| `dump/dump_server/if0_43124603_loja_carros.sql` | Snapshot antigo do servidor; não combinar com os dumps locais. |

### `docs/`, CI e ferramentas

| Caminho | Finalidade |
| --- | --- |
| `docs/README.md` | Apresentação, estado e instruções para executar o projeto. |
| `docs/mapa-arquivos-e-integracao.md` | Este mapa de arquivos, fluxos e localização do banco local. |
| `docs/mapa-banco-de-dados.md` | Dicionário de tabelas, dumps, relações e instalação SQL. |
| `docs/requisitos-funcionais.md` | Requisitos funcionais do sistema. |
| `docs/backlog.md` e `docs/Sprint-Backlog.pdf` | Backlog e planejamento do projeto. |
| `docs/CONTRIBUTORS.md` | Informações de contribuição. |
| `docs/auditoria-pr-9-2026-10-12.md` | Registro de auditoria de um PR anterior. |
| `docs/EMAIL_TEST_SETUP.md` | Configuração do protótipo de teste SMTP. |
| `docs/deployment/ftp-sftp/README.md` | Instruções para publicação por FTP/SFTP. |
| `docs/deployment/ftp-sftp/gitignore-snippet.txt` | Trecho de `.gitignore` para configuração de publicação. |
| `docs/deployment/ftp-sftp/sftp.json.example` | Modelo de configuração de SFTP, sem substituir credenciais privadas. |
| `docs/Figma-Prototype/` | Referências visuais: imagens de carrinho, checkout, catálogo e login, além de `e-commerce.svg`. |
| `docs/UML Diagram.png` e `docs/UML SQL.png` | Diagramas UML e de banco de dados. |
| `.github/workflows/php-ci.yml` | CI: Composer, auditoria de dependências, sintaxe, PHPStan e PHPCS. |
| `.github/workflows/codeql.yml` | Análise CodeQL do JavaScript/TypeScript. |

### Dependências e configuração local do editor

- `vendor/` contém dependências Composer instaladas, incluindo PHPMailer,
  PHPStan e PHP_CodeSniffer; não é código próprio da aplicação e não deve ser
  editado manualmente.
- `.vscode/sftp.json` é uma configuração local ignorada pelo Git. Não a
  compartilhe: configurações de publicação podem conter credenciais.

