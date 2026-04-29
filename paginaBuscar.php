<?php
// paginaBuscar.php
session_start();
require_once('carregarPDO.php');
require_once('carregarTwig.php');

// --- LÓGICA DE SALVAMENTO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    try {
        $pdo->beginTransaction();

        // 1. Garantir que o artista existe
        $stmtArt = $pdo->prepare("INSERT IGNORE INTO artists (name, external_id, image_url, provider) VALUES (?, ?, ?, 'deezer')");
        $stmtArt->execute([$_POST['artist_name'], $_POST['artist_id'], $_POST['artist_image']]);
        
        $stmtGetArt = $pdo->prepare("SELECT id FROM artists WHERE external_id = ? AND provider = 'deezer'");
        $stmtGetArt->execute([$_POST['artist_id']]);
        $artistId = $stmtGetArt->fetchColumn();

        // 2. Garantir que a música existe
        $stmtTrack = $pdo->prepare("INSERT IGNORE INTO tracks (title, artist_id, album_name, image_url, external_id, duration_seconds, provider) VALUES (?, ?, ?, ?, ?, ?, 'deezer')");
        $stmtTrack->execute([
            $_POST['title'], 
            $artistId, 
            $_POST['album_name'], 
            $_POST['image_url'], 
            $_POST['spotify_id'], 
            $_POST['duration']
        ]);

        $stmtGetTrack = $pdo->prepare("SELECT id FROM tracks WHERE external_id = ? AND provider = 'deezer'");
        $stmtGetTrack->execute([$_POST['spotify_id']]);
        $trackId = $stmtGetTrack->fetchColumn();

        if (!$trackId) {
            throw new Exception("Não foi possível processar a música no banco de dados.");
        }

        // 3. Salvar na lista do usuário logado
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("Você precisa estar logado para salvar músicas.");
        }
        
        $userId = $_SESSION['user_id']; 

        // Verifica se o usuário da sessão ainda existe no banco de dados.
        // Útil para evitar erros de chave estrangeira se o banco for resetado.
        $stmtCheckUser = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmtCheckUser->execute([$userId]);
        if (!$stmtCheckUser->fetch()) {
            session_destroy(); // Encerra a sessão inválida
            throw new Exception("Usuário não encontrado ou sessão expirada. Por favor, faça login novamente.");
        }

        $rating = $_POST['rating'] ?: null;
        $isFavorite = isset($_POST['favorite']) ? 1 : 0;

        $stmtUserTrack = $pdo->prepare("REPLACE INTO user_tracks (user_id, track_id, rating, is_favorite) VALUES (?, ?, ?, ?)");
        $stmtUserTrack->execute([$userId, $trackId, $rating, $isFavorite]);

        // 4. Atualizar estatísticas da música (opcional, mas recomendado)
        $pdo->prepare("UPDATE tracks SET 
            average_rating = (SELECT AVG(rating) FROM user_tracks WHERE track_id = ?),
            favorite_count = (SELECT COUNT(*) FROM user_tracks WHERE track_id = ? AND is_favorite = 1)
            WHERE id = ?")->execute([$trackId, $trackId, $trackId]);

        $pdo->commit();
        $message = "Música salva com sucesso!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erro ao salvar: " . $e->getMessage();
    }
}

// --- LÓGICA DE BUSCA (GET) ---
$query = $_GET['q'] ?? '';
$tracks = [];

if ($query) {
    try {
        $ch = curl_init();
        $params = [
            'q' => $query,
            'limit' => 21
        ];
        
        // A API da Deezer não exige Token para buscas públicas
        curl_setopt($ch, CURLOPT_URL, 'https://api.deezer.com/search?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        
        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }

        $data = json_decode($result, true);
        
        if (isset($data['error'])) {
            throw new Exception($data['error']['message']);
        }

        if (isset($data['data'])) {
            foreach ($data['data'] as $item) {
                $tracks[] = [
                    'spotify_id' => $item['id'], // Mantendo o nome da chave para compatibilidade com o POST
                    'title' => $item['title'],
                    'artist_name' => $item['artist']['name'],
                    'artist_id' => $item['artist']['id'],
                    'artist_image' => $item['artist']['picture_medium'],
                    'album_name' => $item['album']['title'],
                    'image_url' => $item['album']['cover_medium'] ?? '',
                    'duration' => $item['duration'], // Deezer já retorna em segundos
                    'preview_url' => $item['preview']
                ];
            }
        }
        curl_close($ch);
    } catch (Exception $e) {
        $error = "Erro na API da Deezer: " . $e->getMessage();
    }
}

echo $twig->render('paginaBuscar.html', [
    'query' => $query,
    'tracks' => $tracks,
    'message' => $message ?? null,
    'error' => $error ?? null
]);