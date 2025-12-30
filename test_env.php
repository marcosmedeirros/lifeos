<?php
// Script de teste para verificar se .env está sendo carregado
require_once 'includes/env.php';

$apiKey = getenv('GOOGLE_API_KEY');

echo "<h1>Teste de Carregamento do .env</h1>";
echo "<pre>";
echo "Arquivo .env existe: " . (file_exists('.env') ? "✅ SIM" : "❌ NÃO") . "\n";
echo "GOOGLE_API_KEY carregado: " . ($apiKey ? "✅ SIM" : "❌ NÃO") . "\n";
echo "Valor da chave: " . ($apiKey ? substr($apiKey, 0, 10) . "..." : "NÃO ENCONTRADO") . "\n";
echo "Comprimento: " . strlen($apiKey) . " caracteres\n";
echo "</pre>";
?>
