<?php
// ARQUIVO: login.php - Página de Login
require_once 'includes/auth.php';

$redirect = sanitize_redirect_path($_GET['redirect'] ?? '') ?: default_redirect_path();

// Se já estiver logado, redireciona para o destino solicitado
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: " . $redirect);
    exit;
}

// Inclui o template de login
include 'includes/login.php';
