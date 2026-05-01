<?php
// paginaFavoritos.php
require_once('carregarPDO.php');
require_once('carregarTwig.php');

// 1. Proteção: Só acessa se estiver logado
if (!isset($_SESSION['user_id'])) {
    header('Location: paginaLogin.php');
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // 2. Buscar as músicas favoritadas pelo usuário (is_favorite = 1)
    $stmt = $pdo->prepare("
        SELECT 
            t.title, 
            t.image_url, 
            t.id AS internal_track_id,
            t.external_id AS spotify_id, 
            t.album_name,
            t.listen_count,
            t.duration_seconds AS duration,
            a.name AS artist_name, 
            a.external_id AS artist_id,
            a.image_url AS artist_image,
            ut.rating
        FROM user_tracks ut
        JOIN tracks t ON ut.track_id = t.id
        JOIN artists a ON t.artist_id = a.id
        WHERE ut.user_id = ? AND ut.is_favorite = 1
        ORDER BY ut.listened_at DESC
    ");
    $stmt->execute([$userId]);
    $favoriteTracks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo $twig->render('paginaFavoritos.html', [
        'favorite_tracks' => $favoriteTracks
    ]);
} catch (PDOException $e) {
    echo "Erro ao carregar favoritos: " . $e->getMessage();
}