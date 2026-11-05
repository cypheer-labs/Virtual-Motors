
<div align="center">

<img src="../assets/virtual-motors.png" width="280" alt="Virtual Motors">

# Virtual Motors

**E-commerce de veículos desenvolvido com metodologia Scrum**

</div>

---

## Sobre o projeto

O **Virtual Motors** é um e-commerce acadêmico de veículos, desenvolvido para oferecer uma experiência de busca, visualização e seleção de carros.

O projeto utiliza PHP no back-end, MySQL no banco de dados e HTML, CSS e JavaScript no front-end. A equipe organiza o desenvolvimento com a metodologia Scrum, dividindo as atividades entre front-end, back-end, banco de dados e documentação.

O sistema tem finalidade educacional e não processa pagamentos reais.

---

## Estado atual do projeto

### Funcionalidades implementadas

- Estrutura do repositório e documentação.
- Protótipo inicial e identidade visual.
- Banco de dados MySQL e dumps SQL.
- Conexão entre PHP e MySQL.
- Busca e listagem de veículos.
- Avaliações de compras confirmadas e médias nos cards dos veículos.
- Cálculo de parcelas com base no valor do veículo.
- Estrutura para histórico de compras.
- UID numérico por conta e carrinho persistente vinculado ao usuário.
- Migrations para evolução do banco de dados.
- Gerenciamento de dependências com Composer.
- Autoload de classes PSR-4.
- Integração do PHPMailer para envio de e-mails via SMTP.

### Funcionalidades em validação

- Cadastro, login e controle de acesso entre clientes e administradores.
- Verificação de e-mail durante o cadastro.
- Cadastro, edição e remoção de veículos pelo painel administrativo.
- Upload e exibição das imagens dos veículos.
- Integração entre front-end, back-end e banco de dados.
- Análise estática e padronização de código com PHPStan e PHP_CodeSniffer.
- Validação do fluxo de compra e do histórico de compras.

Os estados devem ser atualizados conforme cada funcionalidade for testada e integrada à branch principal.

---

## Entregas do projeto

### Back-end

- Conexão com banco de dados por PHP.
- Busca e listagem de veículos.
- Cálculo de parcelas.
- Estrutura de autenticação e controle de acesso.
- Registro e consulta do histórico de compras.
- Registro e consulta de avaliações dos pedidos.
- Integração com PHPMailer para envio de e-mails via SMTP.

### Banco de dados

- Banco `loja_carros`.
- Tabela `carros` utilizada pela listagem e busca.
- Dumps SQL em `dump/virtualmotors/`.
- Mapa do banco de dados: [dumps, migrations e relações](mapa-banco-de-dados.md).
- Migrations organizadas em `database/migrations/`.

### Front-end

- Landing page e vitrine de veículos.
- Interface inicial e identidade visual.
- Recursos visuais armazenados em `assets/`.
- Estilos e scripts organizados em `public/`.

### Ferramentas de desenvolvimento

- Composer para gerenciamento de dependências.
- Autoload PSR-4 para organização de classes.
- PHPStan para análise estática.
- PHP_CodeSniffer para verificação de padrões de código.

---

## Próximos passos

- Validar o cadastro, o login e o controle de acesso.
- Concluir a verificação de e-mail no cadastro.
- Finalizar e validar as operações de gerenciamento do catálogo.
- Validar o upload, armazenamento e exibição de imagens.
- Corrigir os apontamentos das ferramentas de qualidade de código.
- Testar a integração entre interface, back-end e banco de dados.
- Validar o histórico de compras e o fluxo completo de compra.
- Confirmar o funcionamento do sistema no ambiente de hospedagem.

---

## Fluxo de desenvolvimento e branches

O projeto utiliza branches para organizar o trabalho paralelo da equipe e pull requests para revisar alterações antes da integração.

- `main` — branch principal compartilhada.
- `backup-main` — branch de backup, se mantida pela equipe.
- Branches de desenvolvimento — utilizadas para implementar e validar funcionalidades.

A branch principal deve conter a versão integrada e revisada do projeto. Os nomes das branches de desenvolvimento podem mudar conforme a organização da equipe.

---

## Tecnologias

- **Front-end:** HTML, CSS e JavaScript.
- **Back-end:** PHP.
- **Banco de dados:** MySQL ou MariaDB.
- **Dependências:** Composer.
- **Envio de e-mails:** PHPMailer e SMTP.
- **Qualidade de código:** PHPStan e PHP_CodeSniffer.

### Estrutura do projeto

- `public/` — estilos e scripts públicos.
- `assets/` — imagens e recursos visuais.
- `src/` — código PHP.
- `docs/` — documentação técnica.
- `database/migrations/` — scripts de evolução do banco.
- `dump/virtualmotors/` — dumps SQL.
- `config/` — arquivos de configuração.
- `index.php` — ponto de entrada do servidor PHP na raiz.

---

## Equipe

- RayRay — Developer
- MustyD — Developer
- Antônio Rodrigues — Developer
- Yuri_nuvem — Analista
- mamonga-dev — Scrum Master

---

## Como executar localmente

### Pré-requisitos

- PHP com as extensões PDO e PDO MySQL.
- MySQL ou MariaDB.
- Composer.

### Instalação

1. Clone o repositório e entre na pasta do projeto.
2. Instale as dependências:

   ```bash
   composer install
   ```

3. Crie o banco de dados `loja_carros`.
4. Importe o dump SQL `dump/virtualmotors/loja_carros_carros.sql`.
5. Após importar o dump, verifique se as migrations abaixo já foram aplicadas ao banco. Elas são necessárias para os recursos atuais e devem ser executadas somente uma vez:

   - `database/migrations/20261015_add_car_image_url.sql`
   - `database/migrations/20261022_add_purchase_history.sql`
   - `database/migrations/20261029_create_avaliacao.sql`
   - `database/migrations/20261101_convert_cars_to_innodb.sql`
   - `database/migrations/20261102_add_account_uid_and_persistent_cart.sql`
   - `database/migrations/20261102_add_auth_session_version.sql`
   - `database/migrations/20261103_add_profile_customization.sql`
   - `database/migrations/20261103_add_profile_bio_and_box_color.sql`
   - `database/migrations/20261105_remove_profile_box_color.sql`
   - `database/migrations/20261105_create_avaliacao.sql`
   - `database/migrations/20261105_add_product_stock.sql`

6. Copie `config/database.config.php.example` para `config/database.config.php`.
7. Preencha as credenciais do banco de dados local. O arquivo real contém configurações privadas e não deve ser versionado.
8. Na raiz do projeto, inicie o servidor PHP:

   ```bash
   php -S localhost:8000
   ```

9. Acesse `http://localhost:8000`.

A busca e a listagem da página inicial consultam a tabela `carros`. O banco precisa estar ativo e conter registros para exibir os veículos.

### Teste de e-mail

Para configurar o teste isolado de envio de e-mails, consulte [`EMAIL_TEST_SETUP.md`](EMAIL_TEST_SETUP.md), caso o arquivo esteja localizado no mesmo diretório deste README.

Utilize credenciais de teste e mantenha senhas e chaves SMTP fora do repositório.

### Configuração do servidor

Na hospedagem, copie `config/database.config.php.example` para `config/database.config.php` e preencha a configuração `production` com as credenciais fornecidas pelo provedor.

O código seleciona `local` para `localhost` e `127.0.0.1`, e `production` para os demais hosts.

Envie o arquivo privado de configuração pelo SFTP e configure-o como exclusão da sincronização para evitar sobrescritas. O `.gitignore` impede o envio pelo GitHub, mas não controla as exclusões da extensão SFTP.

O ambiente de hospedagem também precisa ter a versão do PHP, as extensões e as dependências necessárias para executar o projeto.

---

## Licença

Este projeto está sob a licença **MIT**.
