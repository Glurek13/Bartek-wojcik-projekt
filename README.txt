GAMEZONE — naprawiona paczka (osobne strony, CSS i baza danych)

INSTALACJA OD ZERA
1. Rozpakuj folder gamezone_dynamic do C:\xampp\htdocs\
2. Uruchom Apache i MySQL w XAMPP.
3. Wejdź na http://localhost/phpmyadmin/ i utwórz bazę `gamezone` z kodowaniem utf8mb4_polish_ci (jeśli jej nie ma).
4. Kliknij bazę `gamezone` -> Importuj -> wybierz plik gamezone.sql z tej paczki -> Wykonaj. Ten plik zawiera gry, kategorie, recenzje oraz tabelę użytkowników.
5. W api.php sprawdź ustawienia połączenia: host 127.0.0.1, baza gamezone, użytkownik root, hasło puste (typowe ustawienia XAMPP). Jeśli masz inne hasło, zmień $dbPassword.
6. Otwórz http://localhost/gamezone_dynamic/ — nie otwieraj plików przez dwuklik ani file://.

WAŻNE
- Dla świeżej instalacji importuj tylko gamezone.sql. Nie importuj migration_login_reviews.sql, bo aktualny gamezone.sql już zawiera wymagane tabele i kolumny.
- Jeśli masz już działającą bazę z danymi, nie importuj ponownie gamezone.sql, bo może się nie zaimportować z powodu istniejących tabel. Zrób kopię bazy i dopiero wtedy wykonaj migrację odpowiednią do jej stanu.
- W razie problemu z bazą sprawdź w phpMyAdmin, czy istnieją tabele users, games, categories i reviews.
- index.php jest dostępny tylko po zalogowaniu; niezalogowany użytkownik zostaje przekierowany do login.php. Po rejestracji/logowaniu trafia na index.php.
- style.css ma dopisany parametr wersji w linkach (style.css?v=3), aby wymusić pobranie aktualnego CSS.
- Wymagane PHP z PDO MySQL, sesjami i mbstring.
