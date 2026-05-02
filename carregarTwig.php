<?php 
// carregar_twig.php
require_once('vendor/autoload.php');

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader);

// Inicia a sessão se ainda não tiver sido iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base_url = '/MyMusicList/';

$twig->addGlobal('base_url', $base_url);

$twig->addGlobal('session', $_SESSION);