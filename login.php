<?php
// ARQUIVO: login.php - Página de Login
require_once 'includes/auth.php';

// Se já estiver logado, redireciona para o dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: " . BASE_PATH . "/index.php");
    exit;
}

// Inclui o template de login
include 'includes/login.php';
