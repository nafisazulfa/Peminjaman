<?php
require_once __DIR__ . '/config/fungsi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !user()) {
    pindah('/login.php');
}

$staf = user()['role'] !== 'peminjam';
catat('Logout');
$_SESSION = [];
session_destroy();
header('Location: ' . BASE . ($staf ? '/login_staf.php' : '/login.php'));
exit;
