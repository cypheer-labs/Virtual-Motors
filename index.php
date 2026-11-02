<?php
session_start();
require_once __DIR__ . '/src/authsession.php';
validar_sessao_usuario();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">

    <title>Virtual Motors | Veículos Premium</title>

    <meta
        name="description"
        content="Encontre seu próximo veículo na Virtual Motors."
    >

    <link rel="stylesheet" href="./public/style.css?v=main-merge">
    <link rel="icon" href="./assets/vm.png" type="image/png" sizes="2048x2048">
    <link rel="apple-touch-icon" href="./assets/vm.png" type="image/png" sizes="2048x2048">
    <link rel="shortcut icon" href="./assets/vm.png" type="image/png" sizes="2048x2048">
</head>

<body>

    <?php
    $headerBasePath = './';
    $headerPaginaInicial = true;
    require __DIR__ . '/src/header.php';
    ?>


    <!-- CONTEÚDO -->
    <main>

        <!-- HERO -->
        <section class="hero" id="inicio">

            <div class="container hero-content">

                <span class="hero-tag">
                    VIRTUAL MOTORS
                </span>

                <h1>
                    Seu próximo carro
                    <strong>está aqui.</strong>
                </h1>

                <p>
                    Veículos selecionados para quem busca qualidade,
                    confiança e uma experiência diferenciada.
                </p>

                <div class="hero-buttons">

                    <a href="#veiculos" class="button button-primary">
                        Ver veículos
                    </a>

                    <a href="#contato" class="button button-outline">
                        Fale conosco
                    </a>

                </div>

            </div>

        </section>

        <!-- BUSCA -->
        <section class="search-section">

            <div class="container">

                <div class="search-box">

                    <div class="search-title">

                        <span><img src="./assets/search-icon.png" alt="" aria-hidden="true"></span>

                        <div>
                            <h2>Encontre seu veículo</h2>
                            <p>
                                Pesquise entre nossos veículos disponíveis
                            </p>
                        </div>

                    </div>

                    <form class="search-form" id="searchForm">

                        <div class="input-group">

                            <label for="vehicleSearch">
                                Veículo, cor ou jogo de origem
                            </label>

                            <input
                                type="search"
                                id="vehicleSearch"
                                name="busca"
                                placeholder="Ex.: Ferrari, vermelho"
                            >

                        </div>


                        <div class="input-group">

                            <label for="priceMax">
                                Preço máximo
                            </label>

                            <select id="priceMax" name="preco_max">

                                <option value="">
                                    Qualquer preço
                                </option>

                                <option value="900000">
                                    Até R$ 900 mil
                                </option>

                                <option value="1200000">
                                    Até R$ 1,2 milhão
                                </option>

                                <option value="1800000">
                                    Até R$ 1,8 milhão
                                </option>

                                <option value="3000000">
                                    Até R$ 3 milhões
                                </option>

                            </select>

                        </div>


                        <button
                            type="submit"
                            class="button button-primary search-button"
                        >
                            Buscar
                        </button>

                    </form>

                </div>

            </div>

        </section>


        <!-- VEÍCULOS -->
        <section class="vehicles-section" id="veiculos">

            <div class="container">

                <div class="section-header">

                    <div>

                        <span class="section-tag">
                            ESTOQUE
                        </span>

                        <h2>
                            Veículos em destaque
                        </h2>

                    </div>

                    <a href="#veiculos" class="view-all">
                        Ver todos →
                    </a>

                </div>


                <p id="vehicleStatus" class="vehicle-status" role="status" aria-live="polite"></p>
                <div class="vehicle-grid" id="vehicleGrid"></div>

            </div>

        </section>


        <!-- DIFERENCIAIS -->
        <section class="features-section" id="sobre">

            <div class="container">

                <div class="section-header centered">

                    <span class="section-tag">
                        POR QUE A VIRTUAL MOTORS?
                    </span>

                    <h2>
                        Comprar seu carro pode ser
                        uma experiência diferente.
                    </h2>

                </div>


                <div class="features-grid">

                    <div class="feature">

                        <div class="feature-icon">
                            ✓
                        </div>

                        <h3>
                            Veículos selecionados
                        </h3>

                        <p>
                            Trabalhamos com veículos escolhidos
                            para oferecer qualidade e segurança.
                        </p>

                    </div>


                    <div class="feature">

                        <div class="feature-icon">
                            ◆
                        </div>

                        <h3>
                            Atendimento personalizado
                        </h3>

                        <p>
                            Nossa equipe está pronta para ajudar
                            você a encontrar o veículo ideal.
                        </p>

                    </div>


                    <div class="feature">

                        <div class="feature-icon">
                            $
                        </div>

                        <h3>
                            Negociação transparente
                        </h3>

                        <p>
                            Condições claras e uma negociação
                            feita de forma simples e transparente.
                        </p>

                    </div>


                    <div class="feature">

                        <div class="feature-icon">
                            ★
                        </div>

                        <h3>
                            Experiência premium
                        </h3>

                        <p>
                            Uma experiência pensada para você
                            desde o primeiro contato.
                        </p>

                    </div>

                </div>

            </div>

        </section>


        <!-- CTA -->
        <section class="cta-section" id="contato">

            <div class="container">

                <div class="cta">

                    <div>

                        <span class="section-tag">
                            FALE CONOSCO
                        </span>

                        <h2>
                            Encontrou o carro ideal?
                        </h2>

                        <p>
                            Entre em contato com nossa equipe
                            e saiba mais sobre nossos veículos.
                        </p>

                    </div>

                    <a
                        href="https://wa.me/5500000000000"
                        class="button button-white"
                    >
                        Falar pelo WhatsApp
                    </a>

                </div>

            </div>

        </section>

    </main>


    <!-- FOOTER -->
    <footer class="footer">

        <div class="container footer-content">

            <div class="footer-brand">

                <img
                    src="./assets/virtual-motors.png"
                    alt="Virtual Motors"
                >

                <p>
                    Seu próximo veículo começa aqui.
                </p>

            </div>


            <div class="footer-column">

                <h4>
                    Empresa
                </h4>

                <a href="./src/sobre-nos.php">
                    Sobre nós
                </a>

                <a href="#veiculos">
                    Veículos
                </a>

                <a href="#contato">
                    Contato
                </a>

            </div>


            <div class="footer-column">

                <h4>
                    Atendimento
                </h4>

                <a href="#contato">
                    WhatsApp
                </a>

                <a href="#">
                    Instagram
                </a>

                <a href="#">
                    Localização
                </a>

            </div>

        </div>


        <div class="footer-bottom">

            <div class="container">

                <p>
                    © 2026 Virtual Motors.
                    Todos os direitos reservados.
                </p>

            </div>

        </div>

    </footer>


    <script src="public/script.js?v=main-merge" defer></script>

</body>

</html>
