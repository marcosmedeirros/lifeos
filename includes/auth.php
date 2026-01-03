<?php
// ARQUIVO: includes/auth.php
// Configura sessão para durar ~90 dias e cookies seguros
$sessionLifetime = 60*60*24*90;
ini_set('session.gc_maxlifetime', $sessionLifetime);
session_set_cookie_params([
    'lifetime' => $sessionLifetime,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// Carregar variáveis de ambiente
require_once __DIR__ . '/env.php';

require_once __DIR__ . '/../config.php';

// Credenciais hardcoded
define('AUTHORIZED_USER', 'marcosmedeirros');
define('AUTHORIZED_PASS', '2026meuano');

// Redirecionamento seguro
function sanitize_redirect_path($path) {
    if (!$path) return '';
    if (strpos($path, '://') !== false || str_starts_with($path, '//')) return '';
    if ($path[0] !== '/') return '';
    return $path;
}
function default_redirect_path() { return '/index.php'; }

// Função de login
function login_user($username, $password) {
    // Verifica se as credenciais são válidas
    if ($username === AUTHORIZED_USER && $password === AUTHORIZED_PASS) {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_name'] = 'Marcos Medeiros';
        $_SESSION['logged_in'] = true;
        return true;
    }
    
    return false;
}

// Processa Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . BASE_PATH . "/login.php");
    exit;
}

// Processa Login via POST
$login_error = '';
$redirect = sanitize_redirect_path($_POST['redirect'] ?? $_GET['redirect'] ?? '');
if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (login_user($username, $password)) {
        $target = $redirect ?: default_redirect_path();
        header("Location: " . $target);
        exit;
    } else {
        $login_error = "Usuário ou senha inválidos.";
    }
}

// Verifica se o usuário está logado
function require_login() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $current = $_SERVER['REQUEST_URI'] ?? '/';
    $redirectParam = urlencode($current);
        http_response_code(403);
        echo '<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sem Acesso - LifeOS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={darkMode:\'class\'};</script>
    <style>
        body { background: radial-gradient(circle at top right, #1e1b4b, #0f172a, #020617); color: #e2e8f0; min-height: 100vh; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="text-center">
        <div class="text-6xl mb-4">🔒</div>
        <h1 class="text-4xl font-bold text-white mb-2">Sem acesso</h1>
        <p class="text-slate-400 mb-6">Você precisa estar autenticado para acessar esta página.</p>
        <a href="' . BASE_PATH . '/login.php?redirect=' . $redirectParam . '" class="inline-block bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-xl font-bold transition">Fazer Login</a>
    </div>
</body>
</html>';
        exit;
    }
}

// Verifica se está acessando a página de login
function is_login_page() {
    $uri = $_SERVER['REQUEST_URI'];
    return strpos($uri, '/login') !== false;
}

// Define o ID do usuário logado
$user_id = $_SESSION['user_id'] ?? 1;
