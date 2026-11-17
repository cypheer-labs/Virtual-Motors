# Mapa do banco de dados

Este arquivo explica para que serve cada SQL e como ele se relaciona com a
configuração e o código da aplicação. Os dumps são snapshots para importação;
as migrations alteram um banco que já existe.

## Arquivos SQL

| Arquivo | O que faz | Quando usar |
| --- | --- | --- |
| [`dump/virtualmotors/loja_carros_carros.sql`](../dump/virtualmotors/loja_carros_carros.sql) | Cria a tabela `carros` e insere os seis veículos de demonstração. Já inclui `imagem_url`. | Instalação local da tabela de veículos. |
| [`dump/virtualmotors/loja_carros_usuario.sql`](../dump/virtualmotors/loja_carros_usuario.sql) | Cria a tabela `usuario`, com campos de cadastro e e-mail único. | Instalação local da tabela de usuários. |
| [`dump/virtualmotors/loja_carros_pedido.sql`](../dump/virtualmotors/loja_carros_pedido.sql) | Cria a tabela `pedido`, inicialmente ligada a `usuario`. | Instalação local da tabela de pedidos antes da migration de histórico. |
| [`dump/virtualmotors/loja_carros_item_pedido.sql`](../dump/virtualmotors/loja_carros_item_pedido.sql) | Cria `item_pedido`, que relaciona pedidos e carros. | Instalação local da tabela de itens antes da migration de histórico. |
| [`dump/dump_server/if0_43124603_loja_carros.sql`](../dump/dump_server/if0_43124603_loja_carros.sql) | Snapshot completo antigo do banco de produção, com as quatro tabelas e dados existentes naquele momento. | Apenas para recuperação/importação intencional desse snapshot; não importar junto com os quatro arquivos acima. |
| [`database/migrations/20261015_add_car_image_url.sql`](../database/migrations/20261015_add_car_image_url.sql) | Adiciona `carros.imagem_url`. | Em um banco antigo cuja tabela `carros` ainda não tenha essa coluna. |
| [`database/migrations/20261022_add_purchase_history.sql`](../database/migrations/20261022_add_purchase_history.sql) | Habilita transações em `pedido`/`item_pedido` e adiciona data, endereço, quantidade e cópia dos dados do veículo no pedido. | Em um banco com as tabelas de pedido já criadas, antes de usar o histórico/checkout atual. |
| [`database/migrations/20261029_create_avaliacao.sql`](../database/migrations/20261029_create_avaliacao.sql) | Cria a tabela `avaliacao` ligada a `pedido` e `usuario`. | Use este ou o script de 05/11, mas não ambos. |
| [`database/migrations/20261101_convert_cars_to_innodb.sql`](../database/migrations/20261101_convert_cars_to_innodb.sql) | Converte `carros` para InnoDB, necessário para que os itens do pedido respeitem a chave estrangeira. | Após a migration de histórico, em bancos nos quais `carros` ainda usa MyISAM. |
| [`database/migrations/20261102_add_account_uid_and_persistent_cart.sql`](../database/migrations/20261102_add_account_uid_and_persistent_cart.sql) | Adiciona um UID numérico exclusivo de oito ou mais dígitos às contas e cria o carrinho persistente por usuário e veículo. | Após as migrations anteriores, antes de usar cadastro, login, carrinho ou checkout atualizados. |
| [`database/migrations/20261102_add_auth_session_version.sql`](../database/migrations/20261102_add_auth_session_version.sql) | Adiciona a versão de autenticação usada para invalidar sessões quando a senha é redefinida. | Aplicada automaticamente junto às migrations de conta na primeira conexão ao banco. |
| [`database/migrations/20261103_add_profile_bio_and_box_color.sql`](../database/migrations/20261103_add_profile_bio_and_box_color.sql) | Adiciona a bio e uma coluna de cor de fundo posteriormente removida. | Aplicar antes da migration de remoção da cor. |
| [`database/migrations/20261103_add_profile_customization.sql`](../database/migrations/20261103_add_profile_customization.sql) | Adiciona foto, banner e cor do nome do perfil. | Aplicar uma vez para habilitar a personalização do perfil. |
| [`database/migrations/20261105_add_product_stock.sql`](../database/migrations/20261105_add_product_stock.sql) | Adiciona `carros.estoque`, inteiro não negativo com valor inicial zero. | Aplicada automaticamente na primeira conexão; o administrador informa manualmente as quantidades disponíveis. |
| [`database/migrations/20261105_create_avaliacao.sql`](../database/migrations/20261105_create_avaliacao.sql) | Cria a mesma estrutura de `avaliacao` que o script de 29/10. | Alternativa ao script de 29/10; não execute ambos. |
| [`database/migrations/20261105_remove_profile_box_color.sql`](../database/migrations/20261105_remove_profile_box_color.sql) | Remove a coluna de cor de fundo do perfil. | Aplicar depois de `20261103_add_profile_bio_and_box_color.sql`. |

### Ordem de instalação local

1. Crie o banco `loja_carros`.
2. Importe os quatro arquivos de `dump/virtualmotors/` acima nesta ordem:
   `carros`, `usuario`, `pedido`, `item_pedido`. As chaves estrangeiras exigem
   que `carros` e `usuario` existam antes de `item_pedido` e `pedido`.
3. **Não execute** `20261015_add_car_image_url.sql` depois do dump local de
   `carros`: essa coluna já está nele, e a migration tentaria criá-la de novo.
4. Execute [`20261022_add_purchase_history.sql`](../database/migrations/20261022_add_purchase_history.sql)
   uma vez para preparar o histórico de compras.
5. Execute apenas um dos scripts de avaliação:
   [`20261029_create_avaliacao.sql`](../database/migrations/20261029_create_avaliacao.sql)
   ou
   [`20261105_create_avaliacao.sql`](../database/migrations/20261105_create_avaliacao.sql).
   Ambos criam a mesma tabela; executar os dois causa erro de tabela existente.
6. Execute [`20261101_convert_cars_to_innodb.sql`](../database/migrations/20261101_convert_cars_to_innodb.sql)
   para habilitar as chaves estrangeiras do carrinho em bancos nos quais
   `carros` ainda usa MyISAM.
7. Para personalização de perfil, execute
   [`20261103_add_profile_bio_and_box_color.sql`](../database/migrations/20261103_add_profile_bio_and_box_color.sql),
   [`20261103_add_profile_customization.sql`](../database/migrations/20261103_add_profile_customization.sql)
   e, depois, [`20261105_remove_profile_box_color.sql`](../database/migrations/20261105_remove_profile_box_color.sql).
8. Configure a conexão em
   [`config/database.config.php.example`](../config/database.config.php.example),
   copiando-o para `config/database.config.php` e preenchendo os valores locais.
9. Na primeira conexão, `src/schemamigrationrunner.php` aplica automaticamente
   UID/carrinho persistente, `auth_version` e `estoque`. O carrinho combina os
   itens da sessão e da conta no login; os itens comprados são removidos na
   mesma transação do pedido. A alteração de senha invalida sessões anteriores.
10. A coluna `estoque` começa em zero para os veículos existentes; ajuste as
   quantidades em `admin/produtos.php`. Os pedidos não alteram o estoque
   automaticamente.

Na hospedagem por FTP, depois do upload de `src/database.php` e
`src/schemamigrationrunner.php`, essa migration é aplicada automaticamente na
primeira conexão ao banco. O servidor deve ter `carros` em InnoDB e a conta do
MySQL precisa ter permissões para criar tabela e alterar `usuario` e `carros`.
As migrations gerenciadas pelo executor automático são registradas em
`vm_schema_migrations` e não são repetidas. Scripts SQL executados manualmente
não são registrados por esse executor.

O snapshot `dump/dump_server/if0_43124603_loja_carros.sql` não é a base recomendada
para uma instalação nova: ele representa o servidor em uma data anterior, usa
MyISAM e não contém `imagem_url` nem os campos do histórico de compras. Se esse
snapshot específico for restaurado para uso com o código atual, execute uma vez
[`20261015_add_car_image_url.sql`](../database/migrations/20261015_add_car_image_url.sql)
e depois
[`20261022_add_purchase_history.sql`](../database/migrations/20261022_add_purchase_history.sql).
Depois execute
[`20261101_convert_cars_to_innodb.sql`](../database/migrations/20261101_convert_cars_to_innodb.sql).
Não misture esse snapshot com os dumps locais nem reaplique migrations em um
banco que já tenha essas colunas.

## Relações entre tabelas

```text
usuario (1) ── (N) pedido (1) ── (N) item_pedido (N) ── (1) carros
usuario (1) ── (N) carrinho_usuario (N) ── (1) carros
```

- Um usuário pode ter vários pedidos.
- Um pedido pode conter vários itens.
- Cada item referencia um carro.
- Cada conta tem um UID numérico único, gerado automaticamente para novas contas
  e preenchido para as contas existentes pela migration.
- `carrinho_usuario` guarda as quantidades por conta e veículo; suas chaves
  estrangeiras removem itens quando a conta ou o veículo é excluído.
- A migration de histórico guarda também nome, origem e preço unitário no item,
  para preservar os dados exibidos no histórico mesmo se o cadastro do carro
  mudar posteriormente.

## Quem usa o banco no código

- [`src/database.php`](../src/database.php) seleciona a configuração local ou de
  produção e cria a conexão PDO. Ele lê `config/database.config.php` quando
  esse arquivo existe.
- [`src/userrepository.php`](../src/userrepository.php) consulta e cadastra
  `usuario`, incluindo a geração de UID exclusivo.
- [`src/carrinhorepository.php`](../src/carrinhorepository.php) carrega,
  combina e salva itens em `carrinho_usuario`.
- [`src/carrorepository.php`](../src/carrorepository.php) consulta `carros` e
  lê `imagem_url` e `estoque`.
- [`src/pedidorepository.php`](../src/pedidorepository.php) grava e consulta
  `pedido` e `item_pedido`; usa as colunas adicionadas por
  [`20261022_add_purchase_history.sql`](../database/migrations/20261022_add_purchase_history.sql).
- As páginas administrativas também consultam essas mesmas tabelas; não criam
  nem importam o banco.

## Atenção aos dados do snapshot

O dump de produção inclui registros de usuários e hashes de senha. Não publique
o conteúdo desse arquivo nem o use como dump genérico de desenvolvimento.
Considere remover dados pessoais antes de compartilhar uma cópia e trocar
credenciais caso algum segredo de conexão tenha sido exposto.
