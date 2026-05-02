<?php
// paginaBuscar.php
session_start();
require_once('carregarPDO.php');
require_once('carregarTwig.php');

// --- LÓGICA DE SALVAMENTO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
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

        if ($_POST['action'] === 'save') {
            // 3. Salvar na lista do usuário logado
            if (!isset($_SESSION['user_id'])) {
                throw new Exception("Você precisa estar logado para salvar músicas.");
            }
            
            $userId = $_SESSION['user_id']; 

            // Verifica se o usuário da sessão ainda existe
            $stmtCheckUser = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $stmtCheckUser->execute([$userId]);
            if (!$stmtCheckUser->fetch()) {
                session_destroy();
                throw new Exception("Usuário não encontrado. Por favor, faça login novamente.");
            }

            // Validação obrigatória da nota para salvar na lista
            if (empty($_POST['rating'])) {
                throw new Exception("Por favor, deixe uma nota");
            }

            $rating = $_POST['rating'];
            $isFavorite = isset($_POST['favorite']) ? 1 : 0;

            $stmtUserTrack = $pdo->prepare("REPLACE INTO user_tracks (user_id, track_id, rating, is_favorite) VALUES (?, ?, ?, ?)");
            $stmtUserTrack->execute([$userId, $trackId, $rating, $isFavorite]);

            // Atualizar estatísticas globais de avaliação
            $pdo->prepare("UPDATE tracks SET 
                average_rating = (SELECT AVG(rating) FROM user_tracks WHERE track_id = ?),
                favorite_count = (SELECT COUNT(*) FROM user_tracks WHERE track_id = ? AND is_favorite = 1)
                WHERE id = ?")->execute([$trackId, $trackId, $trackId]);

            $response = ['status' => 'success', 'message' => 'Adicionado à sua lista!'];

        } elseif ($_POST['action'] === 'listen') {
            // 4. Incrementar o contador de ouvintes (Ação de ouvir prévia)
            $stmtListen = $pdo->prepare("UPDATE tracks SET listen_count = listen_count + 1 WHERE id = ?");
            $stmtListen->execute([$trackId]);
            
            $response = ['status' => 'success', 'message' => 'Ouvinte registrado!'];
        }

        $pdo->commit();

        // Se for uma requisição AJAX, retorna JSON e encerra
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode($response);
            exit;
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erro ao salvar: " . $e->getMessage();
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
    }
}

// --- LÓGICA DE BUSCA (GET) ---
$query = $_GET['q'] ?? '';
$type = $_GET['type'] ?? 'music';
$tracks = [];
$profiles = [];

if ($query) {
    try {
        if ($type === 'perfil') {
            $stmt = $pdo->prepare("SELECT id, username, profile_image_url FROM users WHERE username LIKE ? LIMIT 21");
            $stmt->execute(["%$query%"]);
            $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
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
        }
    } catch (Exception $e) {
        $error = "Erro na API da Deezer: " . $e->getMessage();
    }
}

echo $twig->render('paginaBuscar.html', [
    'query' => $query,
    'type' => $type,
    'tracks' => $tracks,
    'profiles' => $profiles,
    'message' => $message ?? null,
    'error' => $error ?? null
]);