const gamesContainer = document.getElementById("gamesContainer");
const categoryList = document.getElementById("categoryList");
const reviewContainer = document.getElementById("reviewContainer");
const noResults = document.getElementById("noResults");
const statusMessage = document.getElementById("statusMessage");
const searchInput = document.getElementById("searchInput");
const searchForm = document.getElementById("searchForm");

let games = [];
let categories = [];
let reviews = [];
let activeCategory = null;

function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, char => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
    })[char]);
}

function renderCategories() {
    categoryList.innerHTML = `
        <button type="button" data-category-id="all">🎮 Wszystkie</button>
        ${categories.map(category => `
            <button type="button" data-category-id="${Number(category.id)}">
                ${escapeHtml(category.icon || "🎮")} ${escapeHtml(category.name)}
            </button>
        `).join("")}
    `;
}

function renderGames() {
    const query = searchInput.value.trim().toLocaleLowerCase("pl");
    const filteredGames = games.filter(game => {
        const matchesCategory = activeCategory === null ||
            Number(game.category_id) === Number(activeCategory);
        const matchesSearch = String(game.name).toLocaleLowerCase("pl").includes(query);
        return matchesCategory && matchesSearch;
    });

    gamesContainer.innerHTML = filteredGames.map(game => {
        const image = game.image
            ? `<img src="${escapeHtml(game.image)}" alt="${escapeHtml(game.name)}" loading="lazy">`
            : "";
        return `
            <article class="game-card">
                ${image}
                <div class="game-info">
                    <span class="category">${escapeHtml(game.category)}</span>
                    <h3>${escapeHtml(game.name)}</h3>
                    <p>${escapeHtml(game.description)}</p>
                    <div class="rating">⭐ ${Number(game.rating || 0).toFixed(1)}/10</div>
                </div>
            </article>
        `;
    }).join("");

    noResults.hidden = filteredGames.length > 0;
    statusMessage.hidden = true;
}

function renderReviews() {
    if (reviews.length === 0) {
        reviewContainer.innerHTML = "<p>Brak recenzji w bazie danych.</p>";
        return;
    }

    reviewContainer.innerHTML = reviews.map(review => `
        <article class="review">
            <div class="review-top">
                <span>🎮 ${escapeHtml(review.game_name)}</span>
                <strong>${Number(review.rating).toFixed(1)}/10</strong>
            </div>
            <p>„${escapeHtml(review.content)}”</p>
            <small>Autor: ${escapeHtml(review.author)}</small>
        </article>
    `).join("");
}

async function loadData() {
    try {
        const response = await fetch("api.php?action=all", {
            headers: { "Accept": "application/json" }
        });
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || "Błąd pobierania danych.");
        }

        games = data.games || [];
        categories = data.categories || [];
        reviews = data.reviews || [];

        renderCategories();
        renderGames();
        renderReviews();
    } catch (error) {
        console.error(error);
        statusMessage.hidden = false;
        statusMessage.textContent = error.message ||
            "Nie udało się pobrać danych. Sprawdź, czy uruchomiono stronę przez serwer PHP.";
        categoryList.innerHTML = "";
        gamesContainer.innerHTML = "";
        reviewContainer.innerHTML = "";
        noResults.hidden = true;
    }
}

categoryList.addEventListener("click", event => {
    const button = event.target.closest("button[data-category-id]");
    if (!button) return;

    const value = button.dataset.categoryId;
    activeCategory = value === "all" ? null : Number(value);
    renderGames();
});

searchForm.addEventListener("submit", event => {
    event.preventDefault();
    renderGames();
});

searchInput.addEventListener("input", renderGames);

loadData();
