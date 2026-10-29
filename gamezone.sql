-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Paź 26, 2026 at 07:11 AM
-- Wersja serwera: 10.4.32-MariaDB
-- Wersja PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `gamezone`
--

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `icon` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `icon`) VALUES
(1, 'Akcja', '⚔️'),
(2, 'RPG', '🧙'),
(3, 'Sportowe', '⚽'),
(4, 'Strategiczne', '♟️'),
(5, 'Wyścigowe', '🏎️'),
(6, 'SandBox', '⌛'),
(7, 'sał*tka', '☣️');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `games`
--

CREATE TABLE `games` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `image` varchar(500) DEFAULT NULL,
  `rating` decimal(3,1) DEFAULT 0.0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

--
-- Dumping data for table `games`
--

INSERT INTO `games` (`id`, `category_id`, `name`, `description`, `image`, `rating`, `created_at`) VALUES
(1, 2, 'Elden Ring', 'Wielki świat fantasy pełen niebezpiecznych przeciwników i tajemnic.', 'https://assets-prd.ignimgs.com/2021/06/12/elden-ring-button-03-1623460560664.jpg', 9.5, '2026-10-22 05:28:50'),
(2, 1, 'Cyberpunk 2077', 'Futurystyczna przygoda w ogromnym mieście Night City.', 'https://tse1.mm.bing.net/th/id/OIP.fxy0blraOnQORK8wvs36_wHaEK?r=0&rs=1&pid=ImgDetMain&o=7&rm=3', 8.0, '2026-10-22 05:28:50'),
(3, 3, 'EA Sports FC', 'Popularna seria piłkarska oferująca setki drużyn i zawodników.', 'https://tse2.mm.bing.net/th/id/OIP.0WDpPPtVDCOLSM1KtilkygHaEL?r=0&rs=1&pid=ImgDetMain&o=7&rm=3', 8.0, '2026-10-22 05:28:50'),
(4, 4, 'Civilization VI', 'Buduj własną cywilizację i prowadź ją przez kolejne epoki.', 'https://th.bing.com/th/id/R.2fcd1c150ac6d8cdc672ae042d2dd179?rik=18rS9ylHSeRBcw&pid=ImgRaw&r=0', 8.8, '2026-10-22 05:28:50'),
(5, 5, 'Forza Horizon 6', 'Dynamiczne wyścigi, otwarty świat i setki samochodów.', 'https://vgnw.net/wp-content/uploads/2026/01/Forza-horizon-6-revealed-VGNW.jpg', 9.2, '2026-10-22 05:28:50'),
(6, 1, 'God of War Ragnarok', 'Epicka przygoda Kratosa i Atreusa osadzona w świecie mitologii.', 'https://tse3.mm.bing.net/th/id/OIP.lESZ-06URQy9L3FM48c1pAAAAA?r=0&rs=1&pid=ImgDetMain&o=7&rm=3', 9.6, '2026-10-22 05:28:50'),
(7, 2, 'Wiedzmin 3 complete edition ', 'Niezwykła przygoda Geralta z rivii', 'https://royalgames.pl/wp-content/uploads/2025/05/wiedzmin-3-cover.jpg', 9.5, '2026-10-22 06:07:19'),
(8, 6, 'Minecraft', 'Sandbox w kwadratowym świecie', 'https://tse3.mm.bing.net/th/id/OIP.USctpQSfLJUXGuGYDculewHaHa?r=0&rs=1&pid=ImgDetMain&o=7&rm=3', 9.6, '2026-10-22 08:34:41'),
(9, 7, 'Walchajm', 'orginalny Walczajm nie jakiś valheim', 'hajm.jpg', 10.0, '2026-10-22 08:46:39'),
(10, 5, 'umamusume ', 'Gra o anime babokoniach trenujesz swoje konie do wyścigów', 'https://tse3.mm.bing.net/th/id/OIP.tJSEqoFhNdDE0r-oTXQsowHaEK?r=0&rs=1&pid=ImgDetMain&o=7&rm=3', 8.9, '2026-10-22 09:06:16'),
(11, 1, 'Fortnite', 'Fortnity ila Babadzi', 'https://external-content.duckduckgo.com/iu/?u=https%3A%2F%2Fi.pinimg.com%2Foriginals%2F2c%2F04%2F5f%2F2c045fa7b427c0ca7f6ae22ddf05f208.jpg&f=1&nofb=1&ipt=29dd039bbc4b1aef02ea5b476aec75d9dc82c8637cc208c44509f6874f60d819&ipo=images', 7.6, '2026-10-22 09:14:14');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `game_id` int(11) NOT NULL,
  `author` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `rating` decimal(3,1) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `game_id`, `author`, `content`, `rating`, `created_at`) VALUES
(1, 1, 'Krzys', 'Elden Ring to świetna gra, która potrafi wciągnąć na długie godziny., świat jest ogromny, a walka z bossami daje dużo satysfakcji po wielu trudach. Grafika i klimat są imponujące. Gra nie należy do najłatwiejszych, ale po kilku próbach albo rozwalony jest kontroler, albo boss pada. Zdecydowanie polecam każdemu, kto lubi wyzwania i gry z otwartym światem.\r\nP.S.\r\nPlusem jest, że gracz nie jest ciągnięty za rączkę przez cała grę lecz sam odkrywa świat Elden Ring.', 9.5, '2026-10-22 05:28:50'),
(2, 2, 'H3rbatka_', 'Bardzo fajna gra mozna robic duzo fajnych rzeczy jak sie chce fajne buildy fajne samochody tylko brakuje mi ciekawszych opcji customizowania samochodow troche. historia wysmienita i postacie tez tylko wkurza mnie ta panam bierze mnie na jakas samobojcza misje i potem ja musze odwalac cala brudna robote a ona tylko cos pierdzi ze uwaga snajper a jak jej odmowie to robi sceny.', 9.0, '2026-10-22 05:28:50'),
(3, 6, '☣ RUZG4R', 'niesamowitą fabułę, przepiękną grafikę i ♥♥♥♥♥♥♥♥♥♥ dopracowany system walki. Każda walka z bossem dostarcza ogromnych emocji, a historia Kratosa i Atreusa potrafi naprawdę poruszyć. Świat gry jest ogromny, pełen sekretów, ciekawych postaci i epickich przygód. Do tego świetna muzyka i klimatyczne lokacje sprawiają, że można się całkowicie zatracić w rozgrywce.\r\n\r\nJak dla mnie jedna z najlepszych gier, w jakie miałem okazję zagrać. Zdecydowanie polecam każdemu fanowi gier akcji i mocnych historii. Prawdziwe arcydzieło, które zasługuje na solidne 9.6/10! 🔥🐺', 9.6, '2026-10-22 05:28:50'),
(4, 7, 'kzdunek', 'Fantastyczny świat razem z dobrze napisanymi postaciami bardzo klimatyczna grafika bardziej realistyczna niż w oryginale dodatki jedne z najbardziej wciągających fabularnie rozgrywka bardzo przyjemna ale trochę powtarzalna.', 9.5, '2026-10-22 08:03:14'),
(5, 3, 'kabanos', 'dobrze sie gra fajne zmiany na plus ten dynamiczny overal, the grounds też dobry tryb nowy i pierwszy raz w historii w ea fc.', 8.0, '2026-10-22 08:13:54'),
(6, 5, 'Piter06041', 'Bardzo bardzo fajna gra mega polecam jak ktoś interesuje się samochodami i motoryzacją mi gra mega siedzi i warto kupić najlepiej na przecenie. Powodzenia w graniu\r\n', 9.2, '2026-10-22 08:20:05'),
(7, 4, 'Widiusz', 'Wreszcie Civka, która wkręciła mnie równie mocno jak część pierwsza. :)\r\n1.Dodanie mechaniki dzielnic oraz możliwość budowy cudów tylko w określonych lokalizacjach bardzo urozmaiciło rozgrywkę wybijając gracza z monotonnego klikania budowy wszystkiego w każdym mieście.\r\n\r\n2.Bardzo duży plus jeśli chodzi o mechanikę religii w grze. Mając swoją religię odpowiednio skonfigurowaną można wypracować dużą przewagę nad graczami, którzy swojej nie rozwinęli. Świetnie pomyślany pomysł misjonarzy/apostołów oraz walki duchowej. Daje to nam drugą płaszczyznę zmagań obok klasycznej walki.\r\n\r\nKreskówkowa stylistyka, krytykowana przez niektórych, mnie akurat bardzo przypadła do gustu.\r\nPodsumowując warto, nawet z samą podstawką.', 8.8, '2026-10-22 08:22:49'),
(8, 8, 'Senko', 'Był to zwykły dzień...\r\n w minecraft.\r\n>Bądź mną\r\n>obudź się \r\n>coś ci syczy\r\n>Okurła.jpg\r\n>kriper\r\n>mam świat na hardcorze od 5 lat\r\n>Domyśl sie', 9.6, '2026-10-22 08:36:48'),
(9, 9, 'to_nie_Sał*tka', 'sper gra dosfalem za drmo robale za kompiejk polecum', 10.0, '2026-10-22 08:47:27'),
(10, 10, 'Senko', 'Mayano to moja waifu i nikt nie będzie hejtował mojego ukochanego lotniczego karła + mommy creek pls give milk.', 8.9, '2026-10-22 09:08:07'),
(11, 11, 'spocynymaciuś123', 'Ta gra jest okropna!!! Grają w nią same spocuchy cuchnące nie wychodzące z pokoju 24/7 ALE pieniądze które zostawiłem w tej grze już nigdy nie wrócą :( liczba pieniędzy spłaciła by kredyt mojej matki i ojca. Jedyna rzecz ktgóra trzyma mnie w tej grze tyo moje wydane pieniądze na skin. ', 7.6, '2026-10-22 09:19:37');

--
-- Indeksy dla zrzutów tabel
--

--
-- Indeksy dla tabeli `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indeksy dla tabeli `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indeksy dla tabeli `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `game_id` (`game_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `games`
--
ALTER TABLE `games`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `games`
--
ALTER TABLE `games`
  ADD CONSTRAINT `games_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
