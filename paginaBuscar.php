<?php
// paginaBuscar.php
require_once('carregarPDO.php');
require_once('carregarTwig.php');

// --- CONFIGURAÇÕES SPOTIFY ---
$clientId = 'bda7aef332c14b439c1836e5b1f2932b';
$clientSecret = 'd89f9e23499b451b973b883365706e1b';

function getSpotifyAccessToken($id, $secret) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://accounts.spotify.com/api/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Essencial para rodar no XAMPP local
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode($id . ':' . $secret),
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    $result = curl_exec($ch);
    $data = json_decode($result, true);
    return $data['access_token'] ?? null;
}

// --- LÓGICA DE SALVAMENTO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    try {
        $pdo->beginTransaction();

        // 1. Garantir que o artista existe
        $stmtArt = $pdo->prepare("INSERT IGNORE INTO artists (name, external_id, image_url) VALUES (?, ?, ?)");
        $stmtArt->execute([$_POST['artist_name'], $_POST['artist_id'], $_POST['artist_image']]);
        
        $stmtGetArt = $pdo->prepare("SELECT id FROM artists WHERE external_id = ?");
        $stmtGetArt->execute([$_POST['artist_id']]);
        $artistId = $stmtGetArt->fetchColumn();

        // 2. Garantir que a música existe
        $stmtTrack = $pdo->prepare("INSERT IGNORE INTO tracks (title, artist_id, album_name, image_url, external_id, duration_seconds) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtTrack->execute([
            $_POST['title'], 
            $artistId, 
            $_POST['album_name'], 
            $_POST['image_url'], 
            $_POST['spotify_id'], 
            $_POST['duration']
        ]);

        $stmtGetTrack = $pdo->prepare("SELECT id FROM tracks WHERE external_id = ?");
        $stmtGetTrack->execute([$_POST['spotify_id']]);
        $trackId = $stmtGetTrack->fetchColumn();

        // 3. Salvar na lista do usuário (User ID 1 como exemplo, implementar sessão depois)
        $userId = 1; 
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
    $token = getSpotifyAccessToken($clientId, $clientSecret);
    
    // Verificação estrita: se não houver token, gera erro antes de tentar a busca
    if (!$token) {
        $error = "Falha na autenticação: Não foi possível obter o Token do Spotify. Verifique se suas chaves e o cURL estão corretos.";
    } else {
        try {
            $ch = curl_init();
            $params = [
                'q' => $query,
                'type' => 'track',
                'limit' => 15
            ];
            
            curl_setopt($ch, CURLOPT_URL, 'https://api.spotify.com/v1/search?' . http_build_query($params));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Essencial para rodar no XAMPP local
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
            $result = curl_exec($ch);
            
            if (curl_errno($ch)) {
                throw new Exception(curl_error($ch));
            }

            $data = json_decode($result, true);
            
            if (isset($data['error'])) {
                throw new Exception($data['error']['message']);
            }

            if (isset($data['tracks']['items'])) {
                foreach ($data['tracks']['items'] as $item) {
                    $tracks[] = [
                        'spotify_id' => $item['id'],
                        'title' => $item['name'],
                        'artist_name' => $item['artists'][0]['name'],
                        'artist_id' => $item['artists'][0]['id'],
                        'album_name' => $item['album']['name'],
                        'image_url' => $item['album']['images'][0]['url'] ?? '',
                        'duration' => floor($item['duration_ms'] / 1000),
                        'preview_url' => $item['preview_url']
                    ];
                }
            }
            curl_close($ch);
        } catch (Exception $e) {
            $error = "Erro na API do Spotify: " . $e->getMessage();
        }
    }
}

echo $twig->render('paginaBuscar.html', [
    'query' => $query,
    'tracks' => $tracks,
    'message' => $message ?? null,
    'error' => $error ?? null
]);