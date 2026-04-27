<?php
// paginaInicial.php
require_once('../carregarPDO.php');
require_once('../carregarTwig.php');

try {
    // 1. Buscar as músicas melhor avaliadas (Top Rated)
    // Fazemos um JOIN com a tabela de artistas para exibir o nome do autor
    $stmtTopRated = $pdo->query("
        SELECT t.*, a.name AS artist_name 
        FROM tracks t
        LEFT JOIN artists a ON t.artist_id = a.id
        ORDER BY t.average_rating DESC
        LIMIT 12
    ");
    $topRatedTracks = $stmtTopRated->fetchAll(PDO::FETCH_ASSOC);

    // 2. Buscar as músicas mais populares (Mais ouvidas)
    $stmtPopular = $pdo->query("
        SELECT t.*, a.name AS artist_name 
        FROM tracks t
        LEFT JOIN artists a ON t.artist_id = a.id
        ORDER BY t.listen_count DESC
        LIMIT 12
    ");
    $popularTracks = $stmtPopular->fetchAll(PDO::FETCH_ASSOC);

    // 3. Buscar alguns artistas em destaque
    $stmtArtists = $pdo->query("SELECT * FROM artists LIMIT 6");
    $featuredArtists = $stmtArtists->fetchAll(PDO::FETCH_ASSOC);

    // Renderizar o template passando as variáveis
    echo $twig->render('paginaInicial.html', [
        'top_rated' => $topRatedTracks,
        'popular' => $popularTracks,
        'artists' => $featuredArtists
    ]);

} catch (PDOException $e) {
    echo "Erro ao carregar dados: " . $e->getMessage();
}
