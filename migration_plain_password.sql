-- GameZone: przejście na JAWNE hasła w lokalnej bazie demonstracyjnej.
-- UWAGA: tylko do testów, nigdy na publiczny serwer.
-- PRZED URUCHOMIENIEM: wyeksportuj kopię obecnej bazy w phpMyAdmin.
-- Ten plik wykonaj TYLKO RAZ dla już istniejącej bazy z kolumną password_hash.
-- Istniejących hashy nie można odwrócić. Dotychczasowe konta wymagają
-- ręcznego nadania NOWYCH haseł, patrz INSTRUKCJA_HASLA.txt.
-- Zachowuje użytkowników, recenzje, ulubione, gry i inne dane.

ALTER TABLE `users`
  ADD COLUMN `password` VARCHAR(255) NOT NULL DEFAULT '' AFTER `email`;

ALTER TABLE `users`
  DROP COLUMN `password_hash`;

-- Sprawdź konta, którym trzeba nadać nowe hasło:
SELECT `id`, `username`, `email`, `password` FROM `users`;
-- Przykład dla swojego konta (edytuj i uruchom oddzielnie, nie używaj
-- prawdziwego hasła do żadnego innego serwisu):
-- UPDATE `users` SET `password`='MojeTestoweHaslo123!' WHERE `email`='twoj@email.pl';
