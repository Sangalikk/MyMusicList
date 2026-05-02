<?php
session_start();
require_once('carregarPDO.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Não autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];
$bio = $_POST['bio'] ?? '';
$profileImageUrl = $_SESSION['profile_image'] ?? null;

try {
    // Lógica de Upload de Imagem
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
        $uploadDir = 'img/profiles/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $fileName = 'profile_' . $userId . '_' . time() . '.' . $ext;
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetPath)) {
            $profileImageUrl = $targetPath;
        }
    }

    $stmt = $pdo->prepare("UPDATE users SET bio = ?, profile_image_url = ? WHERE id = ?");
    $stmt->execute([$bio, $profileImageUrl, $userId]);

    // Atualiza a sessão para refletir as mudanças imediatamente
    $_SESSION['bio'] = $bio;
    $_SESSION['profile_image'] = $profileImageUrl;

    echo json_encode([
        'status' => 'success', 
        'message' => 'Perfil atualizado com sucesso!',
        'image_url' => $profileImageUrl
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>