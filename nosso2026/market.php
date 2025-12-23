<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $owner = $_POST['owner'] ?? 'nosso';
        $stmt = $pdo->prepare("INSERT INTO nosso2026_market_list (item, owner, done) VALUES (?,?,0)");
        $stmt->execute([trim($_POST['item']), $owner]);
    } elseif (isset($_POST['delete'])) {
        $pdo->prepare("DELETE FROM nosso2026_market_list WHERE id=?")->execute([intval($_POST['id'])]);
    }
    header('Location: ' . n26_link('market.php'));
    exit;
}

// Itens por dono
$nossas = $pdo->query("SELECT * FROM nosso2026_market_list WHERE owner='nosso' AND done=0 ORDER BY id DESC")->fetchAll();
$marcos = $pdo->query("SELECT * FROM nosso2026_market_list WHERE owner='marcos' AND done=0 ORDER BY id DESC")->fetchAll();
$luiza = $pdo->query("SELECT * FROM nosso2026_market_list WHERE owner='luiza' AND done=0 ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mercado • Nosso 2026</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { background:#000; color:#fff; font-family:system-ui,-apple-system,sans-serif; }
    .glass { background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.06); }
    .btn { background:#fff; color:#000; padding:0.5rem 1rem; border-radius:0.75rem; font-weight:600; cursor:pointer; border:0; }
    .btn:hover { background:#e5e5e5; }
  </style>
</head>
<body>
  <?php include __DIR__.'/_nav.php'; ?>
  
  <main class="max-w-5xl mx-auto px-4 py-10">
    <h1 class="text-3xl font-bold mb-6">Lista de Mercado</h1>

    <!-- Input simples -->
    <form method="post" class="flex gap-3 mb-8">
      <input type="hidden" name="add" value="1">
      <input name="item" class="flex-1 bg-black border border-[#222] rounded-xl px-4 py-2 text-white" placeholder="Adicionar item..." required>
      <select name="owner" class="bg-black border border-[#222] rounded-xl px-4 py-2 text-white">
        <option value="nosso">Nosso</option>
        <option value="marcos">Marcos</option>
        <option value="luiza">Luiza</option>
      </select>
      <button class="btn">Adicionar</button>
    </form>

    <!-- 3 Colunas -->
    <div class="grid md:grid-cols-3 gap-6">
      <!-- Nossas -->
      <div class="glass rounded-2xl p-6">
        <h2 class="text-xl font-bold mb-4">Nossas</h2>
        <div class="space-y-2">
          <?php foreach($nossas as $it): ?>
          <div class="flex items-center justify-between bg-black border border-[#222] rounded-lg p-3">
            <span><?= htmlspecialchars($it['item']) ?></span>
            <form method="post" class="inline">
              <input type="hidden" name="delete" value="1">
              <input type="hidden" name="id" value="<?= $it['id'] ?>">
              <button class="text-red-500 hover:text-red-400 text-sm">✕</button>
            </form>
          </div>
          <?php endforeach; ?>
          <?php if(empty($nossas)): ?><p class="text-[#999] text-sm text-center py-4">Vazio</p><?php endif; ?>
        </div>
      </div>

      <!-- Marcos -->
      <div class="glass rounded-2xl p-6">
        <h2 class="text-xl font-bold mb-4">Marcos</h2>
        <div class="space-y-2">
          <?php foreach($marcos as $it): ?>
          <div class="flex items-center justify-between bg-black border border-[#222] rounded-lg p-3">
            <span><?= htmlspecialchars($it['item']) ?></span>
            <form method="post" class="inline">
              <input type="hidden" name="delete" value="1">
              <input type="hidden" name="id" value="<?= $it['id'] ?>">
              <button class="text-red-500 hover:text-red-400 text-sm">✕</button>
            </form>
          </div>
          <?php endforeach; ?>
          <?php if(empty($marcos)): ?><p class="text-[#999] text-sm text-center py-4">Vazio</p><?php endif; ?>
        </div>
      </div>

      <!-- Luiza -->
      <div class="glass rounded-2xl p-6">
        <h2 class="text-xl font-bold mb-4">Luiza</h2>
        <div class="space-y-2">
          <?php foreach($luiza as $it): ?>
          <div class="flex items-center justify-between bg-black border border-[#222] rounded-lg p-3">
            <span><?= htmlspecialchars($it['item']) ?></span>
            <form method="post" class="inline">
              <input type="hidden" name="delete" value="1">
              <input type="hidden" name="id" value="<?= $it['id'] ?>">
              <button class="text-red-500 hover:text-red-400 text-sm">✕</button>
            </form>
          </div>
          <?php endforeach; ?>
          <?php if(empty($luiza)): ?><p class="text-[#999] text-sm text-center py-4">Vazio</p><?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</body>
</html>
