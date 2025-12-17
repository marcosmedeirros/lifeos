<?php
// ARQUIVO: includes/auth.php
session_start();

require_once __DIR__ . '/../config.php';

// Função de login
function login_user($pdo, $email, $password) {
    // Busca o usuário por email OU nome
    $stmt = $pdo->prepare("SELECT id, name, email, password_hash FROM users WHERE email = ? OR name = ?");
    $stmt->execute([$email, $email]);
    $user = $stmt->fetch();

    // Se encontrou o usuário e a senha é 'Gremio@13'
    if ($user && $password === 'Gremio@13') {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        return true;
    }
    
    return false;
}

// Processa Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: /");
    exit;
}

// Processa Login via POST
$login_error = '';
if (isset($_POST['login'])) {
    // DEBUG
    error_log("POST login recebido: email=" . $_POST['email'] . ", password=" . $_POST['password']);
    
    if (login_user($pdo, $_POST['email'], $_POST['password'])) {
        error_log("Login bem-sucedido, redirecionando...");
        header("Location: /");
        exit;
    } else {
        error_log("Login falhou para email: " . $_POST['email']);
        $login_error = "Usuário ou senha inválidos.";
    }
}

// Verifica se o usuário está logado
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        include __DIR__ . '/login.php';
        exit;
    }
}

// Define o ID do usuário logado
$user_id = $_SESSION['user_id'] ?? null;
