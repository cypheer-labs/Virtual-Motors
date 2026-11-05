<?php
session_start();
require_once __DIR__ . '/authsession.php';
validar_sessao_usuario();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>Termos de uso | Virtual Motors</title>
    <meta
        name="description"
        content="Consulte as condições de uso e os avisos sobre o projeto acadêmico Virtual Motors."
    >
    <link rel="stylesheet" href="../public/style.css?v=profile-header-layout">
    <link rel="icon" href="../assets/vm.png" type="image/png" sizes="2048x2048">
    <link rel="apple-touch-icon" href="../assets/vm.png" type="image/png" sizes="2048x2048">
    <link rel="shortcut icon" href="../assets/vm.png" type="image/png" sizes="2048x2048">
</head>

<body class="terms-page">
    <?php
    $headerBasePath = '../';
    require __DIR__ . '/header.php';
    ?>

    <main>
        <section class="terms-hero">
            <div class="container">
                <span class="about-eyebrow"><span aria-hidden="true"></span> INFORMAÇÕES IMPORTANTES</span>
                <h1>Termos de <span>uso.</span></h1>
                <p>
                    Leia as condições para navegar e interagir com a Virtual Motors.
                    Última atualização: novembro de 2026.
                </p>
                <aside class="about-project-note" aria-label="Aviso sobre o projeto">
                    <span class="about-project-mark" aria-hidden="true">i</span>
                    <p>
                        <strong>Projeto acadêmico.</strong>
                        A Virtual Motors é fictícia; pedidos, preços e pagamentos exibidos são demonstrativos
                        e não representam uma oferta comercial.
                    </p>
                </aside>
            </div>
        </section>

        <section class="terms-content">
            <div class="container terms-layout">
                <aside class="terms-summary" aria-label="Resumo">
                    <span class="section-tag">EM POUCAS PALAVRAS</span>
                    <p>
                        Use somente dados fictícios. Não informe dados pessoais sensíveis, senhas reais
                        ou informações de pagamento.
                    </p>
                </aside>

                <div class="terms-sections">
                    <section class="terms-section" id="projeto">
                        <span class="about-story-index">01 — SOBRE O PROJETO</span>
                        <h2>Uma experiência acadêmica, não uma loja real.</h2>
                        <p>
                            A Virtual Motors é um projeto acadêmico e demonstrativo, criado para fins
                            educacionais. A marca, o catálogo, os preços, os pedidos e as etapas de compra
                            são fictícios. O site não vende nem entrega veículos e não processa pagamentos
                            reais. Não realize transferências, pagamentos ou compras com base nas informações
                            exibidas.
                        </p>
                    </section>

                    <section class="terms-section" id="uso">
                        <span class="about-story-index">02 — USO DO SITE</span>
                        <h2>Utilize os recursos de forma responsável.</h2>
                        <p>
                            Ao acessar o site, você concorda em utilizá-lo apenas para navegação e avaliação
                            da demonstração. Não tente acessar áreas ou contas sem autorização, interferir no
                            funcionamento do sistema, inserir conteúdo ilícito ou usar o site para enganar
                            outras pessoas.
                        </p>
                        <p>
                            Se criar uma conta para testar a demonstração, forneça apenas informações
                            fictícias. Você é responsável pelas ações realizadas com os dados de acesso
                            informados no ambiente.
                        </p>
                    </section>

                    <section class="terms-section terms-section-notice" id="dados">
                        <span class="about-story-index">03 — DADOS E SEGURANÇA</span>
                        <h2>Não informe dados reais ou confidenciais.</h2>
                        <p>
                            Os dados de catálogo e os exemplos apresentados são fictícios. Informações
                            inseridas em formulários, como nome, e-mail, telefone, endereço ou credenciais,
                            podem ser armazenadas no banco de dados do ambiente acadêmico e acessadas pela
                            equipe do projeto para desenvolvimento, manutenção e avaliação.
                        </p>
                        <p>
                            Este ambiente não é um serviço comercial nem um sistema de segurança certificado.
                            A equipe não garante disponibilidade, confidencialidade ou proteção contra perda,
                            acesso indevido ou falhas. Não use senhas reutilizadas e não informe números de
                            cartão, dados bancários, documentos, senhas reais ou quaisquer informações
                            sensíveis. Esses avisos não afastam direitos ou deveres que não possam ser
                            excluídos pela legislação aplicável.
                        </p>
                    </section>

                    <section class="terms-section" id="conteudo">
                        <span class="about-story-index">04 — CONTEÚDO E MARCAS</span>
                        <h2>Materiais usados para fins demonstrativos.</h2>
                        <p>
                            Nomes, imagens, marcas e referências a veículos ou jogos de terceiros pertencem
                            aos seus respectivos titulares. Quando presentes, são utilizados apenas para
                            identificação e demonstração no contexto acadêmico; não indicam patrocínio,
                            parceria ou afiliação.
                        </p>
                    </section>

                    <section class="terms-section" id="disponibilidade">
                        <span class="about-story-index">05 — DISPONIBILIDADE E RESPONSABILIDADE</span>
                        <h2>O funcionamento pode mudar.</h2>
                        <p>
                            O site é disponibilizado “como está” para demonstração e pode ser alterado,
                            interrompido ou removido sem aviso prévio. Na medida permitida pela legislação,
                            a equipe do projeto não se responsabiliza por decisões tomadas com base em
                            informações fictícias, indisponibilidade, erros ou consequências do uso do
                            ambiente. Nada nesta cláusula limita responsabilidades que a lei não permita
                            limitar.
                        </p>
                    </section>

                    <section class="terms-section" id="alteracoes">
                        <span class="about-story-index">06 — ALTERAÇÕES E CONTATO</span>
                        <h2>Termos atualizados quando necessário.</h2>
                        <p>
                            Estes termos podem ser atualizados para refletir mudanças no projeto. A versão
                            vigente será publicada nesta página. Em caso de dúvidas sobre a demonstração,
                            consulte a seção de contato na página inicial.
                        </p>
                    </section>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container footer-content">
            <div class="footer-brand">
                <img src="../assets/virtual-motors.png" alt="Virtual Motors">
                <p>Seu próximo veículo começa aqui.</p>
            </div>

            <div class="footer-column">
                <h4>Empresa</h4>
                <a href="sobre-nos.php">Sobre nós</a>
                <a href="termos-de-uso.php" aria-current="page">Termos de uso</a>
                <a href="../index.php#veiculos">Veículos</a>
                <a href="../index.php#contato">Contato</a>
            </div>

            <div class="footer-column">
                <h4>Atendimento</h4>
                <a href="../index.php#contato">WhatsApp</a>
                <a href="../index.php#contato">Instagram</a>
                <a href="../index.php#contato">Localização</a>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="container">
                <p>© 2026 Virtual Motors. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <script src="../public/script.js" defer></script>
</body>

</html>
