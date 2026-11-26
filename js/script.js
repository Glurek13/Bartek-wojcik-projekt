"use strict";

document.querySelectorAll(".avatar-image").forEach(image => {
    const fallback = image.parentElement.querySelector(".avatar-fallback");
    if (!fallback) return;
    const showPhoto = () => { image.hidden = false; fallback.hidden = true; };
    const showFallback = () => { image.hidden = true; fallback.hidden = false; };
    image.addEventListener("load", showPhoto);
    image.addEventListener("error", showFallback);
    if (image.complete) {
        if (image.naturalWidth > 0) showPhoto();
        else if (image.naturalWidth === 0) showFallback();
    }
});

document.querySelectorAll("[data-toggle-password]").forEach(button => {
    const input = document.getElementById(button.dataset.togglePassword);
    if (!input) return;
    button.addEventListener("click", () => {
        const show = input.type === "password";
        input.type = show ? "text" : "password";
        button.textContent = show ? "Ukryj hasło" : "Pokaż hasło";
    });
});

const catalog = document.getElementById("gamesContainer");
if (catalog) {
    const form = document.getElementById("searchForm");
    const search = document.getElementById("searchInput");
    const rating = document.getElementById("minRating");
    const sort = document.getElementById("sortGames");
    const onlyFavorites = document.getElementById("onlyFavorites");
    const empty = document.getElementById("noResults");
    const categories = [...document.querySelectorAll("[data-category-filter]")];
    const cards = [...catalog.querySelectorAll("[data-game]")];
    const games = document.getElementById("games");
    const collator = new Intl.Collator("pl");
    let activeCategory = "";

    function render() {
        const query = search.value.trim().toLocaleLowerCase("pl");
        const minimum = Number(rating.value);
        const order = sort.value;
        const sorted = [...cards].sort((first, second) => {
            const a = first.dataset;
            const b = second.dataset;
            const newest = () => b.created.localeCompare(a.created) || Number(b.id) - Number(a.id);
            if (order === "rating-desc") return Number(b.rating) - Number(a.rating) || newest();
            if (order === "rating-asc") return Number(a.rating) - Number(b.rating) || newest();
            if (order === "name-asc") return collator.compare(a.title, b.title) || newest();
            if (order === "name-desc") return collator.compare(b.title, a.title) || newest();
            return newest();
        });
        let visible = 0;
        for (const [index, card] of sorted.entries()) {
            const data = card.dataset;
            const matches = (!activeCategory || data.category === activeCategory)
                && data.title.toLocaleLowerCase("pl").includes(query)
                && Number(data.rating) >= minimum
                && (!onlyFavorites.checked || data.favorite === "1");
            card.hidden = !matches;
            if (matches) visible++;
            const current = catalog.children[index];
            if (current !== card) catalog.insertBefore(card, current || null);
        }
        empty.hidden = visible > 0;
    }

    function chooseCategory(value) {
        activeCategory = value;
        categories.forEach(button => button.setAttribute("aria-pressed", String(button.dataset.categoryFilter === value)));
        render();
    }

    categories.forEach(button => button.addEventListener("click", () => chooseCategory(button.dataset.categoryFilter)));
    form.addEventListener("submit", event => { event.preventDefault(); render(); games.scrollIntoView(); });
    search.addEventListener("input", render);
    for (const input of [rating, sort, onlyFavorites]) input.addEventListener("change", render);
    catalog.addEventListener("favorites-changed", render);
    document.getElementById("clearFilters").addEventListener("click", () => {
        search.value = "";
        rating.value = "0";
        sort.value = "newest";
        onlyFavorites.checked = false;
        chooseCategory("");
    });
    render();
}

const favoritesList = document.getElementById("favoritesContainer");
const profileList = document.getElementById("profileFavorites");
const favoritesEmpty = document.getElementById("favoritesEmpty");
const statusMessage = document.getElementById("interactionStatus");
const pendingActions = new Set();
let statusTimer;

function interactionNotice(message, error = false) {
    if (!statusMessage) return;
    clearTimeout(statusTimer);
    statusMessage.classList.toggle("error", error);
    statusMessage.textContent = message;
    statusTimer = setTimeout(() => { statusMessage.textContent = ""; }, error ? 7500 : 2600);
}

function updateGameButton(card, favorite) {
    card.dataset.favorite = favorite ? "1" : "0";
    const form = card.querySelector("[data-live-action='favorite']");
    const button = form.querySelector("button[type='submit']");
    form.elements.favorite.value = favorite ? "0" : "1";
    button.textContent = favorite ? "♥" : "♡";
    button.classList.toggle("active", favorite);
    button.setAttribute("aria-pressed", String(favorite));
    button.setAttribute("aria-label", (favorite ? "Usuń z ulubionych" : "Dodaj do ulubionych") + " — " + card.dataset.title);
}

function updateFavorite(gameId, favorite, focusedButton) {
    if (catalog && favoritesList) {
        const mainCard = [...catalog.querySelectorAll("[data-game]")].find(card => card.dataset.id === gameId);
        const saved = [...favoritesList.querySelectorAll("[data-game]")].find(card => card.dataset.id === gameId);
        if (mainCard) updateGameButton(mainCard, favorite);
        if (favorite && saved) updateGameButton(saved, true);
        if (favorite && !saved && mainCard) {
            const copy = mainCard.cloneNode(true);
            const copyForm = copy.querySelector("form");
            copyForm.elements.section.value = "favorites";
            copyForm.removeAttribute("aria-busy");
            copyForm.querySelector("button").removeAttribute("aria-disabled");
            const next = [...favoritesList.children].find(card =>
                copy.dataset.created > card.dataset.created ||
                (copy.dataset.created === card.dataset.created && Number(copy.dataset.id) > Number(card.dataset.id)));
            favoritesList.insertBefore(copy, next || null);
        }
        if (!favorite && saved) saved.remove();
        catalog.dispatchEvent(new Event("favorites-changed"));
    }
    if (profileList && !favorite) {
        const item = [...profileList.querySelectorAll("[data-profile-game]")].find(card => card.dataset.profileGame === gameId);
        if (item) item.remove();
        const count = document.getElementById("favoriteCount");
        if (count) count.textContent = String(profileList.querySelectorAll("[data-profile-game]").length);
    }
    const list = favoritesList || profileList;
    if (favoritesEmpty && list) favoritesEmpty.hidden = !!list.querySelector("[data-game], [data-profile-game]");
    if (focusedButton && (!focusedButton.isConnected || focusedButton.closest("[hidden]"))) {
        const nextButton = list?.querySelector("button[type='submit']");
        (nextButton || document.getElementById("favoriteHeading"))?.focus({ preventScroll: true });
    }
}

function updateLike(form, liked, count) {
    const button = form.querySelector("button[type='submit']");
    form.elements.liked.value = liked ? "0" : "1";
    button.classList.toggle("is-liked", liked);
    button.setAttribute("aria-pressed", String(liked));
    button.querySelector("[data-like-icon]").textContent = liked ? "♥" : "♡";
    button.querySelector(".review-count").textContent = String(count);
}

document.addEventListener("submit", async event => {
    const form = event.target;
    const kind = form.dataset.liveAction;
    if (kind !== "favorite" && kind !== "like") return;
    event.preventDefault();
    const body = new FormData(form);
    const id = body.get(kind === "favorite" ? "game_id" : "review_id");
    const key = kind + ":" + id;
    if (pendingActions.has(key)) return;
    const button = form.querySelector("button[type='submit']");
    pendingActions.add(key);
    form.setAttribute("aria-busy", "true");
    button.setAttribute("aria-disabled", "true");
    try {
        // The hidden input named "action" masks the form.action property.
        const endpoint = form.getAttribute("action");
        if (!endpoint) throw new Error("Formularz nie ma adresu zapisu.");
        const response = await fetch(endpoint, {
            method: "POST",
            credentials: "same-origin",
            headers: { Accept: "application/json" },
            body,
        });
        if (response.status === 404) {
            throw new Error("Nie znaleziono pliku " + endpoint + ". Sprawdź, czy pliki z paczki są w folderze projektu.");
        }
        if (!(response.headers.get("content-type") || "").includes("application/json")) {
            if (response.redirected && new URL(response.url).pathname.endsWith("/login.php")) {
                throw new Error("Sesja wygasła. Zaloguj się ponownie.");
            }
            throw new Error("Serwer nie zwrócił odpowiedzi JSON (" + endpoint + ", HTTP " + response.status + "). Sprawdź, czy pliki PHP są z tej samej wersji.");
        }
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || "Nie udało się zapisać zmiany.");
        if (kind === "favorite" && typeof result.favorite === "boolean") {
            updateFavorite(id, result.favorite, document.activeElement === button ? button : null);
        } else if (kind === "like" && typeof result.liked === "boolean" && Number.isInteger(result.count)) {
            updateLike(form, result.liked, result.count);
        } else {
            throw new Error("Serwer zwrócił nieoczekiwaną odpowiedź.");
        }
        interactionNotice(result.message || "Zapisano zmianę.");
    } catch (error) {
        interactionNotice(error instanceof TypeError ? "Nie udało się połączyć z serwerem. Spróbuj ponownie." : error.message, true);
    } finally {
        pendingActions.delete(key);
        form.removeAttribute("aria-busy");
        button.removeAttribute("aria-disabled");
    }
});


// Confirmation before irreversible admin deletions; backend enforces permissions and CSRF.
document.addEventListener("submit", event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    const message = form.getAttribute("data-confirm");
    if (message && !window.confirm(message)) event.preventDefault();
}, true);
