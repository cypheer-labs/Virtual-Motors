<?php
session_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>Sobre nós | Virtual Motors</title>
    <meta
        name="description"
        content="Conheça a Virtual Motors, nosso propósito e os valores que orientam cada experiência."
    >
    <link rel="stylesheet" href="../public/style.css">
    <link rel="icon" href="../assets/vm.png" type="image/png" sizes="2048x2048">
    <link rel="apple-touch-icon" href="../assets/vm.png" type="image/png" sizes="2048x2048">
    <link rel="shortcut icon" href="../assets/vm.png" type="image/png" sizes="2048x2048">
</head>

<body class="about-page">
    <?php
    $headerBasePath = '../';
    $headerPaginaSobreNos = true;
    require __DIR__ . '/header.php';
    ?>

    <main>
        <section class="about-hero" id="inicio">
            <div class="container about-hero-layout">
                <div class="about-hero-content">
                    <span class="about-eyebrow"><span aria-hidden="true"></span> CARROS · CULTURA · EXPERIÊNCIA</span>
                    <h1>Para quem vive o universo automotivo <span>em outra marcha.</span></h1>
                    <p>
                        A Virtual Motors conecta carros marcantes, cultura gamer e uma experiência
                        digital feita para quem sabe o que procura.
                    </p>
                    <div class="about-hero-actions">
                        <a class="button button-primary" href="../index.php#veiculos">Explorar veículos</a>
                        <a class="about-text-link" href="#quem-somos">
                            Conheça a Virtual Motors <span aria-hidden="true">↓</span>
                        </a>
                    </div>
                    <aside class="about-project-note" aria-label="Aviso sobre o projeto">
                        <span class="about-project-mark" aria-hidden="true">i</span>
                        <p>
                            <strong>Projeto acadêmico.</strong>
                            A Virtual Motors é uma marca fictícia; pedidos e pagamentos são apenas demonstrativos.
                        </p>
                    </aside>
                </div>

                <figure class="about-hero-visual">
                    <img
                        src="https://gamebanana.com/mods/embeddables/642666?variant=sd_image"
                        alt="La Hai-Rider, veículo de Wuthering Waves disponível no catálogo Virtual Motors"
                        fetchpriority="high"
                    >
                    <figcaption>
                        <span>DO UNIVERSO DE WUTHERING WAVES</span>
                        <strong>La Hai-Rider</strong>
                    </figcaption>
                </figure>
            </div>
        </section>

        <section class="about-story" id="quem-somos">
            <div class="container">
                <div class="about-story-heading">
                    <span class="section-tag">QUEM SOMOS</span>
                    <span class="about-story-index">01 — NOSSA IDEIA</span>
                </div>
                <div class="about-story-grid">
                    <h2>Não é só sobre chegar. É sobre <span>o que move você.</span></h2>
                    <div class="about-story-copy">
                        <p class="about-story-lead">
                            A Virtual Motors nasceu da mistura entre paixão por carros e o universo
                            dos jogos onde cada máquina tem uma história.
                        </p>
                        <p>
                            Criamos uma vitrine digital para reunir modelos icônicos de diferentes
                            mundos e tornar a descoberta de cada veículo simples, visual e envolvente.
                            Um projeto acadêmico com personalidade própria — feito para quem repara
                            nos detalhes antes de acelerar.
                        </p>
                        <a class="about-inline-link" href="../index.php#veiculos">
                            Descubra o catálogo <span aria-hidden="true">↗</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="about-values" id="valores">
            <div class="container">
                <div class="about-values-heading">
                    <div>
                        <span class="about-story-index">02 — NOSSO JEITO</span>
                        <h2>Uma experiência com <span>identidade.</span></h2>
                    </div>
                    <p>
                        Cada parte da Virtual Motors foi pensada para aproximar a paixão por
                        automobilismo de uma jornada digital clara e agradável.
                    </p>
                </div>

                <div class="about-values-grid">
                    <article class="about-value">
                        <span class="about-value-number">01 <span>— ESSÊNCIA</span></span>
                        <div class="about-value-rule" aria-hidden="true"></div>
                        <h3>Paixão em cada detalhe</h3>
                        <p>Uma curadoria que celebra design, desempenho e carros que deixam marca.</p>
                    </article>

                    <article class="about-value">
                        <span class="about-value-number">02 <span>— CONEXÃO</span></span>
                        <div class="about-value-rule" aria-hidden="true"></div>
                        <h3>Do jogo para a garagem</h3>
                        <p>Modelos de universos virtuais apresentados com contexto e personalidade.</p>
                    </article>

                    <article class="about-value">
                        <span class="about-value-number">03 <span>— JORNADA</span></span>
                        <div class="about-value-rule" aria-hidden="true"></div>
                        <h3>Feito para descobrir</h3>
                        <p>Busca intuitiva, informação organizada e uma experiência sem complicação.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="about-cta">
            <div class="container about-cta-content">
                <div>
                    <span class="about-story-index">SUA PRÓXIMA DESCOBERTA</span>
                    <h2>Encontre o carro que <span>fala com você.</span></h2>
                </div>
                <a class="button button-primary" href="../index.php#veiculos">
                    Ver catálogo <span aria-hidden="true">↗</span>
                </a>
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
                <a href="./sobre-nos.php">Sobre nós</a>
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
