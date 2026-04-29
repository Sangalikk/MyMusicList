<?php
// paginaLogin.php
session_start();
require_once('carregarPDO.php');
require_once('carregarTwig.php');

// Se já estiver logado, redireciona para o início
if (isset($_SESSION['user_id'])) {
    header('Location: paginaInicial.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        header('Location: paginaInicial.php');
        exit;
    } else {
        $error = "E-mail ou senha incorretos.";
    }
}

echo $twig->render('paginaLogin.html', ['error' => $error]);