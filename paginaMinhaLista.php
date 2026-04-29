<?php 
//paginaMinhaLista.php
require_once('carregarPDO.php');
require_once('carregarTwig.php');

// 1. Proteção: Só acessa se estiver logado
if (!isset($_SESSION['user_id'])) {
    header('Location: paginaLogin.php');
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // 2. Buscar as músicas na lista do usuário logado
    // Fazemos JOIN para pegar os dados da música e do artista
    $stmt = $pdo->prepare("
        SELECT 
            t.title, 
            t.image_url, 
            t.external_id, 
            t.album_name,
            a.name AS artist_name, 
            ut.rating, 
            ut.is_favorite
        FROM user_tracks ut
        JOIN tracks t ON ut.track_id = t.id
        JOIN artists a ON t.artist_id = a.id
        WHERE ut.user_id = ?
        ORDER BY ut.listened_at DESC
    ");
    $stmt->execute([$userId]);
    $userTracks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo $twig->render('paginaMinhaLista.html', [
        'user_tracks' => $userTracks
    ]);
} catch (PDOException $e) {
    echo "Erro ao carregar sua lista: " . $e->getMessage();
}