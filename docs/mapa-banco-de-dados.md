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
| [`database/migrations/20261101_convert_cars_to_innodb.sql`](../database/migrations/20261101_convert_cars_to_innodb.sql) | Converte `carros` para InnoDB, necessário para que os itens do pedido respeitem a chave estrangeira. | Após a migration de histórico, em bancos nos quais `carros` ainda usa MyISAM. |

### Ordem de instalação local

1. Crie o banco `loja_carros`.
2. Importe os quatro arquivos de `dump/virtualmotors/` acima nesta ordem:
   `carros`, `usuario`, `pedido`, `item_pedido`. As chaves estrangeiras exigem
   que `carros` e `usuario` existam antes de `item_pedido` e `pedido`.
3. **Não execute** `20261015_add_car_image_url.sql` depois do dump local de
   `carros`: essa coluna já está nele, e a migration tentaria criá-la de novo.
4. Execute [`20261022_add_purchase_history.sql`](../database/migrations/20261022_add_purchase_history.sql)
   uma vez se for usar o fluxo de
   compras/histórico que existe no código.
5. Execute [`20261101_convert_cars_to_innodb.sql`](../database/migrations/20261101_convert_cars_to_innodb.sql)
   para habilitar a gravação dos itens de pedido em bancos que ainda usam MyISAM
   na tabela `carros`.
6. Configure a conexão em
   [`config/database.config.php.example`](../config/database.config.php.example),
   copiando-o para `config/database.config.php` e preenchendo os valores locais.

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
```

- Um usuário pode ter vários pedidos.
- Um pedido pode conter vários itens.
- Cada item referencia um carro.
- A migration de histórico guarda também nome, origem e preço unitário no item,
  para preservar os dados exibidos no histórico mesmo se o cadastro do carro
  mudar posteriormente.

## Quem usa o banco no código

- [`src/database.php`](../src/database.php) seleciona a configuração local ou de
  produção e cria a conexão PDO. Ele lê `config/database.config.php` quando
  esse arquivo existe.
- [`src/userrepository.php`](../src/userrepository.php) consulta e cadastra
  `usuario`.
- [`src/carrorepository.php`](../src/carrorepository.php) consulta `carros` e
  lê `imagem_url`.
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
