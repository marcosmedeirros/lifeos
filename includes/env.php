<?php
// Carregar variáveis de ambiente do arquivo .env
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Pular comentários
        if (strpos(trim($line), '#') === 0) continue;
        
        // Fazer parse da linha
        if (strpos($line, '=') === false) continue;
        
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, '"\'');
        
        // Definir como variável de ambiente
        if (!getenv($key)) {
            putenv("$key=$value");
        }
    }
}
?>
