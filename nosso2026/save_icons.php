<?php
// Criar diretório icons se não existir
$iconsDir = __DIR__ . '/icons';
if (!is_dir($iconsDir)) {
    mkdir($iconsDir, 0755, true);
}

function saveBase64Image($base64String, $outputFile) {
    // Remover cabeçalho "data:image/png;base64,"
    $data = explode(',', $base64String);
    if (count($data) > 1) {
        $data = $data[1];
    } else {
        $data = $data[0];
    }
    
    $imageData = base64_decode($data);
    file_put_contents($outputFile, $imageData);
    return file_exists($outputFile);
}

$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['icon192'])) {
        $saved = saveBase64Image($_POST['icon192'], $iconsDir . '/icon-192.png');
        $results[] = $saved ? 'icon-192.png salvo!' : 'Erro ao salvar icon-192.png';
    }
    
    if (isset($_POST['icon512'])) {
        $saved = saveBase64Image($_POST['icon512'], $iconsDir . '/icon-512.png');
        $results[] = $saved ? 'icon-512.png salvo!' : 'Erro ao salvar icon-512.png';
    }
    
    if (isset($_POST['iconApple'])) {
        $saved = saveBase64Image($_POST['iconApple'], $iconsDir . '/apple-touch-icon.png');
        $results[] = $saved ? 'apple-touch-icon.png salvo!' : 'Erro ao salvar apple-touch-icon.png';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Ícones Salvos</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #000; color: #fff; }
        .success { color: #4ade80; }
        .error { color: #f87171; }
        img { border: 1px solid #333; margin: 10px; }
    </style>
</head>
<body>
    <h2>Resultado do Salvamento de Ícones</h2>
    <?php foreach($results as $result): ?>
        <p class="success">✓ <?= htmlspecialchars($result) ?></p>
    <?php endforeach; ?>
    
    <?php if (!empty($results)): ?>
        <h3>Ícones criados:</h3>
        <img src="icons/icon-192.png" alt="192x192" width="96">
        <img src="icons/icon-512.png" alt="512x512" width="128">
        <img src="icons/apple-touch-icon.png" alt="Apple Touch Icon" width="90">
        <br>
        <a href="index.php" style="color: #a855f7;">← Voltar para Nosso 2026</a>
    <?php endif; ?>
</body>
</html>
