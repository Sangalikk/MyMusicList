<?php
// paginaRegistrar.php
session_start();
require_once('carregarPDO.php');
require_once('carregarTwig.php');

if (isset($_SESSION['user_id'])) {
    header('Location: paginaInicial.php');
    exit;
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if ($password !== $password_confirm) {
        $error = "As senhas não coincidem.";
    } else {
        try {
            // Verifica se e-mail ou usuário já existem
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $stmtCheck->execute([$email, $username]);
            
            if ($stmtCheck->rowCount() > 0) {
                $error = "E-mail ou Nome de Usuário já cadastrados.";
            } else {
                // Cria o hash da senha (segurança)
                $hash = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
                $stmt->execute([$username, $email, $hash]);
                
                // Loga o usuário automaticamente e define a mensagem para o popup
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['username'] = $username;
                $_SESSION['register_success'] = "Conta criada com sucesso! Bem-vindo ao My Music List.";

                header('Location: paginaInicial.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = "Erro ao criar conta. Tente novamente.";
        }
    }
}

echo $twig->render('paginaRegistrar.html', [
    'error' => $error,
    'success' => $success,
    'old_username' => $_POST['username'] ?? '',
    'old_email' => $_POST['email'] ?? ''
]);