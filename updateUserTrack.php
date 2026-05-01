<?php
session_start();
require_once('carregarPDO.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Você precisa estar logado.']);
    exit;
}

$userId = $_SESSION['user_id'];
$trackId = $_POST['track_id'] ?? null;
$action = $_POST['action'] ?? null;

if (!$trackId || !$action) {
    echo json_encode(['status' => 'error', 'message' => 'Dados inválidos.']);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($action === 'update') {
        $rating = $_POST['rating'] ?? null;
        $isFavorite = isset($_POST['favorite']) ? 1 : 0;

        // Valida a nota se fornecida
        if ($rating !== null && ($rating < 1 || $rating > 10)) {
            throw new Exception("A nota deve ser entre 1 e 10.");
        }

        $stmt = $pdo->prepare("
            UPDATE user_tracks
            SET rating = ?, is_favorite = ?
            WHERE user_id = ? AND track_id = ?
        ");
        $stmt->execute([$rating, $isFavorite, $userId, $trackId]);

        // Atualiza as estatísticas globais da música
        $pdo->prepare("UPDATE tracks SET 
            average_rating = (SELECT AVG(rating) FROM user_tracks WHERE track_id = ?),
            favorite_count = (SELECT COUNT(*) FROM user_tracks WHERE track_id = ? AND is_favorite = 1)
            WHERE id = ?")->execute([$trackId, $trackId, $trackId]);

        $response = ['status' => 'success', 'message' => 'Música atualizada com sucesso!'];

    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM user_tracks WHERE user_id = ? AND track_id = ?");
        $stmt->execute([$userId, $trackId]);

        // Atualiza as estatísticas globais da música após a exclusão
        $pdo->prepare("UPDATE tracks SET 
            average_rating = (SELECT AVG(rating) FROM user_tracks WHERE track_id = ?),
            favorite_count = (SELECT COUNT(*) FROM user_tracks WHERE track_id = ? AND is_favorite = 1)
            WHERE id = ?")->execute([$trackId, $trackId, $trackId]);

        $response = ['status' => 'success', 'message' => 'Música removida da sua lista.'];

    } else {
        throw new Exception("Ação inválida.");
    }

    $pdo->commit();
    echo json_encode($response);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>