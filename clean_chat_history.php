<?php
// Script para limpar histórico de chat_life com caminhos de imagem incorretos
require_once 'config.php';

if ($_GET['action'] ?? '' === 'clean_chat_history') {
    try {
        // Exclui mensagens com caminhos errados de imagem
        $stmt = $pdo->prepare("DELETE FROM chat_life_messages WHERE content LIKE '%modules/uploads%'");
        $stmt->execute();
        $deleted = $stmt->rowCount();
        
        echo "<h1>✅ Histórico Limpo</h1>";
        echo "<p>Removidas $deleted mensagens com caminhos incorretos.</p>";
        echo "<p><a href='modules/chat_life.php'>Voltar ao Chat Life</a></p>";
        exit;
    } catch (Exception $e) {
        echo "<h1>❌ Erro</h1>";
        echo "<p>" . $e->getMessage() . "</p>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Limpeza de Chat</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .warning { color: red; margin: 20px 0; }
    </style>
</head>
<body>
    <h1>Limpeza do Histórico de Chat Life</h1>
    <p>Este script remove mensagens antigas com caminhos de imagem incorretos.</p>
    <div class="warning">
        <strong>⚠️ ATENÇÃO:</strong> Esta ação é irreversível. Você perderá o histórico antigo de imagens.
    </div>
    <p>
        <a href="?action=clean_chat_history" onclick="return confirm('Tem certeza? Isto é irreversível.')">
            <button style="padding: 10px 20px; background: #ff6b6b; color: white; border: none; cursor: pointer; font-size: 16px;">
                🗑️ Limpar Histórico Antigo
            </button>
        </a>
    </p>
</body>
</html>
