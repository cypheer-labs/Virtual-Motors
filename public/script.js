
// ==============================
// MENU MOBILE
// ==============================

const menuButton = document.getElementById("menuButton");
const mainNav = document.getElementById("mainNav");
const themeToggle = document.getElementById("themeToggle");
const pageHeader = document.querySelector(".header");

document.querySelectorAll("[data-password-toggle]").forEach((toggle) => {
    toggle.addEventListener("click", () => {
        const passwordInput = document.getElementById(toggle.getAttribute("aria-controls"));
        if (!(passwordInput instanceof HTMLInputElement)) return;

        const showingPassword = passwordInput.type === "password";
        passwordInput.type = showingPassword ? "text" : "password";
        toggle.textContent = showingPassword ? "Ocultar" : "Mostrar";
        toggle.setAttribute("aria-pressed", String(showingPassword));
    });
});

let previousScrollY = window.scrollY;
let accumulatedScrollDelta = 0;

window.addEventListener("scroll", () => {
    if (!pageHeader) return;

    const currentScrollY = window.scrollY;
    accumulatedScrollDelta += currentScrollY - previousScrollY;
    previousScrollY = currentScrollY;

    if (currentScrollY <= pageHeader.offsetHeight) {
        pageHeader.classList.remove("header-hidden");
        accumulatedScrollDelta = 0;
        return;
    }

    if (mainNav?.classList.contains("active")) {
        pageHeader.classList.remove("header-hidden");
        accumulatedScrollDelta = 0;
        return;
    }

    if (accumulatedScrollDelta >= 8) {
        pageHeader.classList.add("header-hidden");
        accumulatedScrollDelta = 0;
    } else if (accumulatedScrollDelta <= -8) {
        pageHeader.classList.remove("header-hidden");
        accumulatedScrollDelta = 0;
    }
}, { passive: true });

function applyTheme(theme) {
    document.documentElement.dataset.theme = theme;
    if (!themeToggle) return;

    const nextTheme = theme === "dark" ? "claro" : "escuro";
    themeToggle.textContent = theme === "dark" ? "☀" : "☾";
    themeToggle.setAttribute("aria-label", `Ativar modo ${nextTheme}`);
    themeToggle.title = `Ativar modo ${nextTheme}`;
}

let savedTheme = null;
try {
    const storedTheme = window.localStorage.getItem("vm-theme");
    if (storedTheme === "light" || storedTheme === "dark") {
        savedTheme = storedTheme;
    }
} catch (error) {
    console.error("Não foi possível ler a preferência de tema salva.", error);
}

const colorSchemePreference = window.matchMedia("(prefers-color-scheme: dark)");
const pageTheme = document.documentElement.dataset.theme;
applyTheme(pageTheme || savedTheme || (colorSchemePreference.matches ? "dark" : "light"));

colorSchemePreference.addEventListener("change", (event) => {
    if (savedTheme === null) {
        applyTheme(event.matches ? "dark" : "light");
    }
});

themeToggle?.addEventListener("click", () => {
    savedTheme = document.documentElement.dataset.theme === "dark" ? "light" : "dark";
    applyTheme(savedTheme);
    try {
        window.localStorage.setItem("vm-theme", savedTheme);
    } catch (error) {
        console.error("Não foi possível salvar a preferência de tema.", error);
    }
});

menuButton?.addEventListener("click", () => {
    mainNav.classList.toggle("active");
});

// Fecha o menu depois de clicar em um link

const navLinks = document.querySelectorAll(".nav a");

navLinks.forEach((link) => {

    link.addEventListener("click", () => {
        mainNav.classList.remove("active");
    });

});


// ==============================
// FORMULÁRIO DE BUSCA
// ==============================

const searchForm = document.getElementById("searchForm");
const vehicleSearch = document.getElementById("vehicleSearch");
const priceMax = document.getElementById("priceMax");
const vehicleGrid = document.getElementById("vehicleGrid");
const vehicleStatus = document.getElementById("vehicleStatus");

const currencyFormatter = new Intl.NumberFormat("pt-BR", {
    style: "currency",
    currency: "BRL"
});

const vehicleImages = [
    { model: "ferrari", src: "https://images.unsplash.com/photo-1552519507-da3b142c6e3d?auto=format&fit=crop&w=1200&q=85" },
    { model: "lamborghini", src: "https://images.unsplash.com/photo-1614200187524-dc4b892acf16?auto=format&fit=crop&w=1200&q=85" },
    { model: "porsche", src: "https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=1200&q=85" },
    { model: "nissan", src: "https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?auto=format&fit=crop&w=1200&q=85" },
    { model: "bmw", src: "https://images.unsplash.com/photo-1555215695-3004980ad54e?auto=format&fit=crop&w=1200&q=85" },
    { model: "mclaren", src: "https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?auto=format&fit=crop&w=1200&q=85" }
];

const sampleVehicles = [
    { id_carro: 1, nome: "Ferrari 488 GTB", cor: "Vermelho", preco: 2500000, jogo_origem: "Forza Horizon", estoque: 0 },
    { id_carro: 2, nome: "Lamborghini Huracán", cor: "Amarelo", preco: 3000000, jogo_origem: "Forza Horizon", estoque: 0 },
    { id_carro: 3, nome: "Porsche 911", cor: "Preto", preco: 1800000, jogo_origem: "Need for Speed", estoque: 0 },
    { id_carro: 4, nome: "Nissan Skyline GT-R", cor: "Azul", preco: 900000, jogo_origem: "Need for Speed", estoque: 0 },
    { id_carro: 5, nome: "BMW M4", cor: "Branco", preco: 1200000, jogo_origem: "Forza Horizon", estoque: 0 },
    { id_carro: 6, nome: "McLaren 720S", cor: "Laranja", preco: 2800000, jogo_origem: "Forza Horizon", estoque: 0 }
];

function getVehicleImage(vehicle) {
    if (vehicle.imagem) {
        return vehicle.imagem;
    }

    const vehicleName = String(vehicle.nome || "").toLocaleLowerCase("pt-BR");
    const matchingImage = vehicleImages.find(({ model }) => vehicleName.includes(model));

    return matchingImage?.src || vehicleImages[0].src;
}

function filterSampleVehicles() {
    const term = vehicleSearch.value.trim().toLocaleLowerCase("pt-BR");
    const maxPrice = Number(priceMax.value) || Infinity;

    return sampleVehicles.filter((vehicle) => {
        const searchableText = `${vehicle.nome} ${vehicle.cor} ${vehicle.jogo_origem}`
            .toLocaleLowerCase("pt-BR");

        return searchableText.includes(term) && vehicle.preco <= maxPrice;
    });
}

function createVehicleCard(vehicle) {
    const card = document.createElement("article");
    card.className = "vehicle-card";

    const image = document.createElement("div");
    image.className = "vehicle-image";

    const vehicleImage = document.createElement("img");
    vehicleImage.src = getVehicleImage(vehicle);
    vehicleImage.alt = `Foto ilustrativa do veículo ${vehicle.nome}`;
    vehicleImage.loading = "lazy";
    vehicleImage.addEventListener("error", () => {
        vehicleImage.src = "./assets/image.png";
        vehicleImage.alt = `Ilustração de veículo para ${vehicle.nome}`;
    }, { once: true });

    const badge = document.createElement("span");
    badge.className = "vehicle-badge";
    const estoque = Math.max(0, Number(vehicle.estoque) || 0);
    badge.textContent = estoque > 0 ? "Em estoque" : "Sem estoque";

    const totalReviews = Math.max(0, Number(vehicle.total_avaliacoes) || 0);
    const reviewBadge = document.createElement("span");
    reviewBadge.className = "vehicle-review-badge";
    reviewBadge.textContent = `${totalReviews} ${totalReviews === 1 ? "avaliação" : "avaliações"}`;
    reviewBadge.setAttribute("aria-label", reviewBadge.textContent);

    image.append(vehicleImage, badge, reviewBadge);

    const info = document.createElement("div");
    info.className = "vehicle-info";

    const origin = document.createElement("span");
    origin.className = "vehicle-year";
    origin.textContent = vehicle.jogo_origem;

    const name = document.createElement("h3");
    name.textContent = vehicle.nome;

    const rating = document.createElement("p");
    rating.className = "vehicle-rating";
    const average = Number(vehicle.media_avaliacao);
    if (totalReviews > 0 && Number.isFinite(average)) {
        const ratingStar = document.createElement("span");
        ratingStar.className = "vehicle-rating-star";
        ratingStar.textContent = "★";
        ratingStar.setAttribute("aria-hidden", "true");

        const ratingValue = document.createElement("span");
        ratingValue.textContent = `${average.toLocaleString("pt-BR", {
            minimumFractionDigits: 1,
            maximumFractionDigits: 1
        })}/5`;
        ratingValue.setAttribute("aria-label", `Média de ${ratingValue.textContent}`);
        rating.append(ratingStar, ratingValue);
    } else {
        rating.textContent = "Sem avaliações";
    }

    const description = document.createElement("p");
    description.className = "vehicle-description";
    description.textContent = vehicle.cor ? `Cor: ${vehicle.cor}` : "Cor não informada";

    const stock = document.createElement("p");
    stock.className = "vehicle-stock";
    stock.textContent = `Disponível: ${estoque} unidade(s)`;

    const footer = document.createElement("div");
    footer.className = "vehicle-footer";

    const price = document.createElement("strong");
    price.textContent = currencyFormatter.format(Number(vehicle.preco));

    const addToCart = document.createElement("form");
    addToCart.className = "add-to-cart";
    addToCart.method = "post";
    addToCart.action = "./src/carrinho.php";

    const action = document.createElement("input");
    action.type = "hidden";
    action.name = "acao";
    action.value = "adicionar";

    const vehicleId = document.createElement("input");
    vehicleId.type = "hidden";
    vehicleId.name = "id_carro";
    vehicleId.value = vehicle.id_carro;

    const addButton = document.createElement("button");
    addButton.className = "vehicle-arrow";
    addButton.type = "submit";
    addButton.title = "Adicionar ao carrinho";
    addButton.setAttribute("aria-label", `Adicionar ${vehicle.nome} ao carrinho`);
    addButton.disabled = estoque < 1;
    const cartIcon = document.createElement("img");
    cartIcon.className = "cart-icon";
    cartIcon.src = "./assets/cart-icon.png";
    cartIcon.alt = "";
    cartIcon.setAttribute("aria-hidden", "true");
    addButton.append(cartIcon);

    const buyNow = document.createElement("form");
    buyNow.className = "buy-now-form";
    buyNow.method = "post";
    buyNow.action = "./src/pagamento.php";

    const directAction = document.createElement("input");
    directAction.type = "hidden";
    directAction.name = "acao";
    directAction.value = "comprar_direto";

    const directVehicleId = document.createElement("input");
    directVehicleId.type = "hidden";
    directVehicleId.name = "id_carro";
    directVehicleId.value = vehicle.id_carro;

    const buyButton = document.createElement("button");
    buyButton.className = "button button-primary vehicle-buy-now";
    buyButton.type = "submit";
    buyButton.textContent = "Comprar agora";
    buyButton.setAttribute("aria-label", `Comprar ${vehicle.nome} sem adicionar ao carrinho`);
    buyButton.disabled = estoque < 1;
    buyNow.append(directAction, directVehicleId, buyButton);
    buyNow.append(directAction, directVehicleId, buyButton);

    addToCart.append(action, vehicleId, addButton);
    const actions = document.createElement("div");
    actions.className = "vehicle-actions";
    actions.append(buyNow, addToCart);
    footer.append(price, actions);
    info.append(origin, name, rating, description, stock, footer);
    card.append(image, info);

    return card;
}

async function loadVehicles() {
    const sampleCatalog = filterSampleVehicles();
    vehicleGrid.replaceChildren(...sampleCatalog.map(createVehicleCard));
    vehicleStatus.textContent = "Carregando estoque atualizado. Exibindo catálogo de demonstração.";

    const parametros = new URLSearchParams();
    if (vehicleSearch.value.trim()) {
        parametros.set("busca", vehicleSearch.value.trim());
    }
    if (priceMax.value) {
        parametros.set("preco_max", priceMax.value);
    }

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 10000);

    try {
        const resposta = await fetch(`./src/pesquisa.php?${parametros.toString()}`, {
            signal: controller.signal
        });
        const veiculos = await resposta.json();

        if (!resposta.ok) {
            throw new Error(veiculos.erro || "Falha ao carregar os veículos.");
        }

        vehicleGrid.replaceChildren(...veiculos.map(createVehicleCard));
        vehicleStatus.textContent = veiculos.length
            ? `${veiculos.length} veículo(s) encontrado(s).`
            : "Nenhum veículo encontrado para esses filtros.";
    } catch (error) {
        const vehicles = filterSampleVehicles();
        vehicleGrid.replaceChildren(...vehicles.map(createVehicleCard));
        vehicleStatus.textContent = `Não foi possível consultar o banco de dados. Exibindo catálogo de demonstração: ${vehicles.length} veículo(s).`;
    } finally {
        clearTimeout(timeoutId);
    }
}

if (searchForm) {
    searchForm.addEventListener("submit", (event) => {
        event.preventDefault();
        loadVehicles();
    });
    loadVehicles();
}

const cepInput = document.getElementById("cep");
const cepButton = document.getElementById("consultCep");
const cepStatus = document.getElementById("cepStatus");

cepInput?.addEventListener("input", () => {
    const digits = cepInput.value.replace(/\D/g, "").slice(0, 8);
    cepInput.value = digits.length > 5
        ? `${digits.slice(0, 5)}-${digits.slice(5)}`
        : digits;
});

cepButton?.addEventListener("click", async () => {
    const cep = cepInput.value.replace(/\D/g, "");
    if (cep.length !== 8) {
        cepStatus.textContent = "Digite um CEP válido com 8 números.";
        cepInput.focus();
        return;
    }

    cepButton.disabled = true;
    cepStatus.textContent = "Consultando CEP...";

    try {
        const endpoint = new URL(cepButton.dataset.endpoint, window.location.href);
        endpoint.searchParams.set("cep", cep);
        const resposta = await fetch(endpoint);
        const endereco = await resposta.json();

        if (!resposta.ok) {
            throw new Error(endereco.erro || "Não foi possível consultar este CEP.");
        }

        document.getElementById("address").value = endereco.logradouro || "";
        document.getElementById("neighborhood").value = endereco.bairro || "";
        document.getElementById("city").value = endereco.localidade || "";
        document.getElementById("state").value = endereco.uf || "";
        cepStatus.textContent = "Endereço preenchido. Confira os dados e informe o número.";
    } catch (error) {
        cepStatus.textContent = error instanceof TypeError
            ? "Não foi possível conectar ao serviço de CEP. Tente novamente ou preencha o endereço manualmente."
            : error.message;
    } finally {
        cepButton.disabled = false;
    }
});

const paymentMethodMessage = document.getElementById("paymentMethodMessage");

const checkoutReviewForm = document.querySelector(".checkout-review-form");
checkoutReviewForm?.addEventListener("submit", () => {
    checkoutReviewForm.elements.tema.value = document.documentElement.dataset.theme;
});

const qrPaymentImage = document.querySelector(".qr-payment-image");

if (qrPaymentImage) {
    const renderQrOnWhite = () => {
        const canvas = document.createElement("canvas");
        canvas.width = qrPaymentImage.naturalWidth;
        canvas.height = qrPaymentImage.naturalHeight;

        const context = canvas.getContext("2d", { willReadFrequently: true });
        if (!context) {
            console.error("Não foi possível preparar a imagem do QR Code.");
            return;
        }

        context.drawImage(qrPaymentImage, 0, 0);
        const imageData = context.getImageData(0, 0, canvas.width, canvas.height);

        for (let index = 0; index < imageData.data.length; index += 4) {
            const color = imageData.data[index + 3] > 80 ? 0 : 255;
            imageData.data[index] = color;
            imageData.data[index + 1] = color;
            imageData.data[index + 2] = color;
            imageData.data[index + 3] = 255;
        }

        context.putImageData(imageData, 0, 0);
        qrPaymentImage.src = canvas.toDataURL("image/png");
    };

    if (qrPaymentImage.complete) {
        renderQrOnWhite();
    } else {
        qrPaymentImage.addEventListener("load", renderQrOnWhite, { once: true });
    }
}

document.querySelectorAll('input[name="paymentMethod"]').forEach((option) => {
    option.addEventListener("change", () => {
        const methodNames = {
            pix: "Pix",
            credit: "Cartão de crédito",
            boleto: "Boleto"
        };
        paymentMethodMessage.textContent = `A opção ${methodNames[option.value]} será disponibilizada quando a integração de pagamento estiver pronta.`;
    });
});
