<?php
// ARQUIVO: includes/paths.php
// Detecta automaticamente o base path da aplicação

if (!defined('BASE_PATH')) {
    // Detecta se está em /lifeos/ ou na raiz
    $request_uri = $_SERVER['REQUEST_URI'];
    
    // Se a URI contém /lifeos/, o base path é /lifeos
    // Caso contrário, é vazio (raiz do domínio)
    if (strpos($request_uri, '/lifeos/') !== false || strpos($request_uri, '/lifeos') === 0) {
        define('BASE_PATH', '/lifeos');
    } else {
        define('BASE_PATH', '');
    }
}
?>
