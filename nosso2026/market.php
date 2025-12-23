<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $stmt = $pdo->prepare("INSERT INTO nosso2026_market_list (item, qty, notes) VALUES (?,?,?)");
        $stmt->execute([trim($_POST['item']), trim($_POST['qty']), trim($_POST['notes'])]);
    } elseif (isset($_POST['done'])) {
        $stmt = $pdo->prepare("UPDATE nosso2026_market_list SET done=1 WHERE id=?");
        $stmt->execute([intval($_POST['id'])]);
    } elseif (isset($_POST['delete'])) {
        $stmt = $pdo->prepare("DELETE FROM nosso2026_market_list WHERE id=?");
        $stmt->execute([intval($_POST['id'])]);
    }
    header('Location: ' . n26_link('market.php'));
    exit;
}

$items = $pdo->query("SELECT * FROM nosso2026_market_list WHERE done=0 ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mercado • Nosso 2026</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={darkMode:'class'};</script>
  <style>
    body{background:#000;color:#fff;font-family:'Outfit',sans-serif}
    .glass{background:#0a0a0a; border:1px solid #222}
    .btn{background:#fff;color:#000;padding:.5rem 1rem;border-radius:.5rem;font-weight:700}
  </style>
</head>
<body class="min-h-screen">
  <?php include __DIR__.'/_nav.php'; ?>
  <main class="max-w-6xl mx-auto px-4 py-8 space-y-8">
    <section class="glass p-6 rounded-2xl">
      <h2 class="text-2xl font-bold mb-4">Adicionar item</h2>
      <form method="post" class="grid md:grid-cols-5 gap-3">
        <input type="hidden" name="add" value="1">
        <div class="md:col-span-2">
          <label class="text-sm">Item</label>
          <input name="item" class="w-full bg-black border border-[#222] rounded-xl p-2 text-white" required>
        </div>
        <div>
          <label class="text-sm">Qtd/Unidade</label>
          <input name="qty" class="w-full bg-black border border-[#222] rounded-xl p-2 text-white" placeholder="Ex.: 2kg">
        </div>
        <div class="md:col-span-2">
          <label class="text-sm">Notas</label>
          <input name="notes" class="w-full bg-black border border-[#222] rounded-xl p-2 text-white" placeholder="Ex.: integral">
        </div>
        <div class="md:col-span-5">
          <button class="btn">Adicionar</button>
        </div>
      </form>
    </section>

    <section class="glass p-6 rounded-2xl">
      <h2 class="text-xl font-bold mb-4">Lista de compras</h2>
      <div class="space-y-3">
        <?php foreach($items as $it): ?>
        <div class="border border-[#222] rounded-xl p-4 flex justify-between">
          <div>
            <p class="font-semibold mb-1"><?= htmlspecialchars($it['item']) ?></p>
            <p class="text-xs text-[#999]">Qtd: <?= htmlspecialchars($it['qty']) ?> • <?= htmlspecialchars($it['notes']) ?></p>
          </div>
          <div class="flex gap-2">
            <form method="post">
              <input type="hidden" name="done" value="1">
              <input type="hidden" name="id" value="<?= $it['id'] ?>">
              <button class="btn">OK</button>
            </form>
            <form method="post" onsubmit="return confirm('Remover item?')">
              <input type="hidden" name="delete" value="1">
              <input type="hidden" name="id" value="<?= $it['id'] ?>">
              <button class="btn">Remover</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
  </main>
</body>
</html>
