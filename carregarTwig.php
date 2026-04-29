<?php 
// carregar_twig.php
require_once('vendor/autoload.php');

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader);

// Inicia a sessão se ainda não tiver sido iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine a URL base para o aplicativo.
// Isso assume que a pasta do seu projeto 'MyMusicList' está diretamente sob o 'document root'
// do seu servidor web (ex: htdocs no XAMPP).
// Se o seu projeto estiver diretamente no document root (ex: http://localhost/paginaInicial.php),
// então $base_url deve ser '/'.
// Se o seu projeto estiver em uma subpasta como http://localhost/MyMusicList/, então $base_url deve ser '/MyMusicList/'.
// Ajuste este valor se o caminho base do seu projeto for diferente.
$base_url = '/MyMusicList/';

// Se precisar de uma forma mais dinâmica (ex: para diferentes ambientes), você pode tentar:
// $base_url = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
// if (strpos($base_url, '/back/') !== false) { $base_url = str_replace('/back/', '/', $base_url); }
$twig->addGlobal('base_url', $base_url);

// Torna a sessão global para o Twig acessar {{ session.username }}
$twig->addGlobal('session', $_SESSION);