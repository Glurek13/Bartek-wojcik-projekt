// Pobieramy wszystkie karty gier
const games = document.querySelectorAll(".game-card");
const noResults = document.getElementById("noResults");

// Filtrowanie według kategorii
function filterGames(category) {

    let found = false;

    games.forEach(game => {

        const gameCategory = game.dataset.category;

        if (gameCategory === category) {
            game.style.display = "block";
            found = true;
        } else {
            game.style.display = "none";
        }
    });

    noResults.style.display = found ? "none" : "block";
}

// Pokazuje wszystkie gry
function showAllGames() {

    games.forEach(game => {
        game.style.display = "block";
    });

    noResults.style.display = "none";
}

// Wyszukiwanie gry
function searchGames() {

    const input = document
        .getElementById("searchInput")
        .value
        .toLowerCase()
        .trim();

    let found = false;

    games.forEach(game => {

        const name = game.dataset.name.toLowerCase();

        if (name.includes(input)) {
            game.style.display = "block";
            found = true;
        } else {
            game.style.display = "none";
        }
    });

    noResults.style.display = found ? "none" : "block";
}

// Wyszukiwanie po naciśnięciu Enter
document.getElementById("searchInput").addEventListener("keydown", function(event) {

    if (event.key === "Enter") {
        searchGames();
    }

});
