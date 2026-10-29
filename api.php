<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
function out(array $data, int $status=200): void { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function body(): array { $raw=file_get_contents('php://input'); $d=json_decode($raw ?: '', true); return is_array($d) ? $d : $_POST; }
function postOnly(): void { if ($_SERVER['REQUEST_METHOD']!=='POST') out(['error'=>'Ta operacja wymaga metody POST.'],405); }
function loggedId(): int { if (empty($_SESSION['user_id'])) out(['error'=>'Zaloguj się, aby wykonać tę czynność.'],401); return (int)$_SESSION['user_id']; }
// Dopasuj ustawienia do swojej instalacji MySQL.
$dbHost='127.0.0.1'; $dbName='gamezone'; $dbUser='root'; $dbPassword='';
try {
 $pdo=new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",$dbUser,$dbPassword,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 $action=$_GET['action']??'all';
 if($action==='register'){
  postOnly(); $d=body(); $name=trim((string)($d['username']??'')); $email=strtolower(trim((string)($d['email']??''))); $pass=(string)($d['password']??'');
  if(mb_strlen($name)<3||mb_strlen($name)>50) out(['error'=>'Nazwa musi mieć od 3 do 50 znaków.'],422);
  if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190) out(['error'=>'Podaj prawidłowy e-mail.'],422);
  if(strlen($pass)<8||strlen($pass)>4096) out(['error'=>'Hasło musi mieć co najmniej 8 znaków.'],422);
  $s=$pdo->prepare('SELECT id FROM users WHERE email=? OR username=? LIMIT 1');$s->execute([$email,$name]); if($s->fetch()) out(['error'=>'Taki e-mail lub nazwa użytkownika już istnieje.'],409);
  $s=$pdo->prepare('INSERT INTO users(username,email,password_hash) VALUES(?,?,?)');$s->execute([$name,$email,password_hash($pass,PASSWORD_DEFAULT)]);
  session_regenerate_id(true); $_SESSION['user_id']=(int)$pdo->lastInsertId(); $_SESSION['username']=$name; out(['message'=>'Konto utworzone. Jesteś zalogowany.','user'=>['id'=>$_SESSION['user_id'],'username'=>$name]]);
 }
 if($action==='login'){
  postOnly();$d=body();$email=strtolower(trim((string)($d['email']??'')));$pass=(string)($d['password']??'');$s=$pdo->prepare('SELECT id,username,password_hash FROM users WHERE email=? LIMIT 1');$s->execute([$email]);$u=$s->fetch();
  if(!$u||!password_verify($pass,$u['password_hash']))out(['error'=>'Nieprawidłowy e-mail lub hasło.'],401);
  session_regenerate_id(true);$_SESSION['user_id']=(int)$u['id'];$_SESSION['username']=$u['username'];out(['message'=>'Zalogowano pomyślnie.','user'=>['id'=>(int)$u['id'],'username'=>$u['username']]]);
 }
 if($action==='logout') { postOnly(); $_SESSION=[]; if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);} session_destroy();out(['message'=>'Wylogowano.']); }
 if($action==='me') { out(['user'=>empty($_SESSION['user_id'])?null:['id'=>(int)$_SESSION['user_id'],'username'=>(string)$_SESSION['username']]]); }
 if($action==='add_review'){
  postOnly();$uid=loggedId();$d=body();$gid=filter_var($d['game_id']??null,FILTER_VALIDATE_INT);$rating=filter_var($d['rating']??null,FILTER_VALIDATE_INT);$content=trim((string)($d['content']??''));
  if(!$gid||!$rating||$rating<1||$rating>10)out(['error'=>'Wybierz grę i ocenę od 1 do 10.'],422);
  if(mb_strlen($content)<5||mb_strlen($content)>3000)out(['error'=>'Opinia musi mieć od 5 do 3000 znaków.'],422);
  $s=$pdo->prepare('SELECT id FROM games WHERE id=?');$s->execute([$gid]);if(!$s->fetch())out(['error'=>'Wybrana gra nie istnieje.'],404);
  $s=$pdo->prepare('INSERT INTO reviews(game_id,user_id,author,content,rating) VALUES(?,?,?,?,?)');$s->execute([$gid,$uid,(string)$_SESSION['username'],$content,$rating]);out(['message'=>'Twoja opinia została opublikowana.']);
 }
 if($action==='games'){$s=$pdo->query('SELECT g.id,g.name,g.description,g.image,g.rating,c.id category_id,c.name category,c.icon category_icon FROM games g JOIN categories c ON c.id=g.category_id ORDER BY g.created_at DESC,g.id DESC');out(['games'=>$s->fetchAll()]);}
 if($action==='categories')out(['categories'=>$pdo->query('SELECT id,name,icon FROM categories ORDER BY id')->fetchAll()]);
 if($action==='reviews'){$s=$pdo->query('SELECT r.id,r.author,r.content,r.rating,r.created_at,g.name game_name FROM reviews r JOIN games g ON g.id=r.game_id ORDER BY r.created_at DESC,r.id DESC');out(['reviews'=>$s->fetchAll()]);}
 if($action==='all'){
  $games=$pdo->query('SELECT g.id,g.name,g.description,g.image,g.rating,c.id category_id,c.name category,c.icon category_icon FROM games g JOIN categories c ON c.id=g.category_id ORDER BY g.created_at DESC,g.id DESC')->fetchAll();
  $cats=$pdo->query('SELECT id,name,icon FROM categories ORDER BY id')->fetchAll();$reviews=$pdo->query('SELECT r.id,r.author,r.content,r.rating,r.created_at,g.name game_name FROM reviews r JOIN games g ON g.id=r.game_id ORDER BY r.created_at DESC,r.id DESC')->fetchAll();out(['games'=>$games,'categories'=>$cats,'reviews'=>$reviews]);
 }
 out(['error'=>'Nieznana akcja API.'],400);
} catch(Throwable $e) { error_log((string)$e); out(['error'=>'Błąd serwera lub bazy danych. Sprawdź konfigurację oraz migrację SQL.'],500); }
