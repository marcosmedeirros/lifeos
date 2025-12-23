<?php
// ARQUIVO: includes/paths.php
// Detecta automaticamente o base path da aplicação

if (!defined('BASE_PATH')) {
    // Detecta o host atual
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Se for marcosmedeirros.io (produção), o base path é vazio (raiz)
    // Se for localhost ou qualquer outro, usa /lifeos
    if (strpos($host, 'marcosmedeirros.io') !== false) {
        define('BASE_PATH', '');
    } else {
        // Para localhost e desenvolvimento
        define('BASE_PATH', '/lifeos');
    }
}
?>
