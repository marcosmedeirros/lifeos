<?php
// ARQUIVO: includes/paths.php
// Detecta automaticamente o base path da aplicação

if (!defined('BASE_PATH')) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Produção: qualquer host que termine com marcosmedeiros.io (com ou sem www)
    $isProd = (bool)preg_match('/(^|\.)marcosmedeiros\.io$/', $host);

    if ($isProd) {
        define('BASE_PATH', '');
    } else {
        // Ambiente local ou qualquer outro host usa /lifeos
        define('BASE_PATH', '/lifeos');
    }
}
?>
