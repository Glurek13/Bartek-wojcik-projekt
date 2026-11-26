<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
if (!empty($_SESSION['user_id'])) redirect('index.php');
if (isset($_GET['type']) && $_GET['type'] === 'admin') redirect('login-admin.php');
redirect('login-user.php');
