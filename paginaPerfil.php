<?php
session_start();
require_once('carregarPDO.php');
require_once('carregarTwig.php');

// Se não passar um ID, tenta pegar o do usuário logado
$userId = $_GET['id'] ?? ($_SESSION['user_id'] ?? null);

if (!$userId) {
    header('Location: paginaInicial.php');
    exit;
}

try {
    // 1. Buscar dados do usuário
    $stmtUser = $pdo->prepare("SELECT id, username, bio, profile_image_url FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("Usuário não encontrado.");
    }

    // 2. Buscar estatísticas simplificadas
    $stmtStats = $pdo->prepare("
        SELECT 
            COUNT(*) as total_tracks,
            SUM(CASE WHEN is_favorite = 1 THEN 1 ELSE 0 END) as total_favorites,
            AVG(rating) as avg_rating
        FROM user_tracks 
        WHERE user_id = ?
    ");
    $stmtStats->execute([$userId]);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    // 3. Buscar a lista de músicas do usuário
    $stmtTracks = $pdo->prepare("
        SELECT ut.*, t.title, t.image_url, a.name as artist_name
        FROM user_tracks ut
        JOIN tracks t ON ut.track_id = t.id
        JOIN artists a ON t.artist_id = a.id
        WHERE ut.user_id = ?
        ORDER BY ut.listened_at DESC
    ");
    $stmtTracks->execute([$userId]);
    $userTracks = $stmtTracks->fetchAll(PDO::FETCH_ASSOC);

    echo $twig->render('paginaPerfil.html', [
        'profile_user' => $user,
        'stats' => $stats,
        'user_tracks' => $userTracks,
        'is_own_profile' => (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId)
    ]);

} catch (PDOException $e) {
    echo "Erro ao carregar perfil: " . $e->getMessage();
}