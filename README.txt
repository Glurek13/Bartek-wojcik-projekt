GAMEZONE — wersja pobierająca dane z MySQL/MariaDB
=================================================

Pliki:
- index.html  — struktura strony
- style.css   — style strony
- script.js   — pobiera dane z API i tworzy karty gier, kategorie oraz recenzje
- api.php     — API PHP łączące się z bazą
- gamezone.sql — Twój zrzut bazy danych (jeśli dołączysz go osobno do folderu)

INSTALACJA (XAMPP / Windows)
1. Zainstaluj/uruchom XAMPP.
2. Uruchom Apache i MySQL.
3. Skopiuj folder gamezone_dynamic do C:\xampp\htdocs\
4. Otwórz http://localhost/phpmyadmin
5. Wybierz Importuj i zaimportuj swój plik gamezone.sql. Plik tworzy bazę `gamezone`.
6. Otwórz api.php i sprawdź ustawienia:
   $dbHost = '127.0.0.1';
   $dbName = 'gamezone';
   $dbUser = 'root';
   $dbPassword = '';
   Jeśli konto MySQL ma hasło, wpisz je w $dbPassword.
7. Wejdź na http://localhost/gamezone_dynamic/

WAŻNE
- Nie otwieraj index.html przez dwuklik (file://). PHP musi działać przez Apache/serwer PHP.
- Test API: http://localhost/gamezone_dynamic/api.php?action=all
  Powinien wyświetlić JSON z grami, kategoriami i recenzjami.
- Kategorie, wyszukiwanie i recenzje są generowane na podstawie danych z bazy.
- Ten projekt tylko odczytuje dane; nie ma formularza dodawania/edycji gier.
- W przypadku błędu sprawdź konfigurację bazy, czy zaimportowano SQL i czy PDO MySQL jest włączone.
