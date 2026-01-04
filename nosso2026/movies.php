<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $stmt = $pdo->prepare("INSERT INTO nosso2026_movies (title, planned_date, notes) VALUES (?,?,?)");
        $stmt->execute([trim($_POST['title']), $_POST['planned_date'] ?: NULL, trim($_POST['notes'])]);
    } elseif (isset($_POST['watch'])) {
        $stmt = $pdo->prepare("UPDATE nosso2026_movies SET status='assistido', rating=? WHERE id=?");
        $stmt->execute([($_POST['rating']!=='')?intval($_POST['rating']):NULL, intval($_POST['id'])]);
    } elseif (isset($_POST['delete'])) {
        $stmt = $pdo->prepare("DELETE FROM nosso2026_movies WHERE id=?");
        $stmt->execute([intval($_POST['id'])]);
    }
    header('Location: ' . n26_link('movies.php'));
    exit;
}

$planned = $pdo->query("SELECT * FROM nosso2026_movies WHERE status='planejado' ORDER BY planned_date ASC, id DESC")->fetchAll();
$watched = $pdo->query("SELECT * FROM nosso2026_movies WHERE status='assistido' ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />  <meta name="theme-color" content="#000000">
  <link rel="icon" href="<?= n26_link('icons/icon-192.png') ?>">
  <link rel="manifest" href="<?= n26_link('manifest.json') ?>">
  <link rel="apple-touch-icon" href="<?= n26_link('icons/apple-touch-icon.png') ?>">  <link rel="stylesheet" href="<?= n26_link('responsive.css') ?>">
  <title>Filmes • Nosso 2026</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { background:#000; color:#fff; font-family:system-ui,-apple-system,sans-serif; }
    .glass { background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.06); }
    .btn { background:#ffffff; color:#000; padding:0.5rem 1rem; border-radius:0.75rem; font-weight:600; transition:all 0.2s; display:inline-block; text-align:center; border:0; cursor:pointer; }
    .btn:hover { background:#e0e0e0; transform: translateY(-1px); }
  </style>
</head>
<body>
  <?php include __DIR__.'/_nav.php'; ?>
  
  <main class="max-w-7xl mx-auto px-4 py-10">
    <!-- Adicionar Filme -->
    <section class="glass rounded-2xl p-6 mb-8">
      <h2 class="text-2xl font-bold mb-4">Adicionar Filme</h2>
      <form method="post" class="grid md:grid-cols-4 gap-3">
        <input type="hidden" name="add" value="1">
        <input name="title" class="md:col-span-2 bg-[#1a1a1a] border border-[#333] rounded-xl p-3 text-white" placeholder="Título" required>
        <input type="date" name="planned_date" class="bg-[#1a1a1a] border border-[#333] rounded-xl p-3 text-white">
        <input name="notes" class="bg-[#1a1a1a] border border-[#333] rounded-xl p-3 text-white" placeholder="Notas">
        <button class="md:col-span-4 btn">🎬 Adicionar</button>
      </form>
    </section>

    <!-- Duas Colunas: Planejados | Assistidos -->
    <div class="grid md:grid-cols-2 gap-8">
      <!-- Planejados -->
      <section class="glass rounded-2xl p-6">
        <h2 class="text-2xl font-bold mb-4">Quero Ver <span class="text-sm text-[#999] font-normal">(<?= count($planned) ?>)</span></h2>
        <div class="space-y-3">
          <?php foreach($planned as $m): ?>
          <div class="bg-[#1a1a1a] border border-[#333] rounded-xl p-4">
            <p class="font-bold mb-1"><?= htmlspecialchars($m['title']) ?></p>
            <?php if($m['planned_date'] || $m['notes']): ?>
              <p class="text-xs text-[#999] mb-2">
                <?= $m['planned_date'] ? date('d/m/Y', strtotime($m['planned_date'])) : '' ?>
                <?= $m['notes'] ? ' • '.htmlspecialchars($m['notes']) : '' ?>
              </p>
            <?php endif; ?>
            <form method="post" class="flex gap-2">
              <input type="hidden" name="watch" value="1">
              <input type="hidden" name="id" value="<?= $m['id'] ?>">
              <select name="rating" class="flex-1 bg-[#222] border border-[#333] rounded-lg p-2 text-white text-sm">
                <option value="">Nota</option>
                <?php for($i=1;$i<=5;$i++): ?><option value="<?= $i ?>"><?= str_repeat('⭐', $i) ?></option><?php endfor; ?>
              </select>
              <button class="btn text-sm">🌟 Marcar Assistido</button>
            </form>
          </div>
          <?php endforeach; ?>
          <?php if(empty($planned)): ?>
            <p class="text-center text-[#999] py-8">Nenhum filme planejado</p>
          <?php endif; ?>
        </div>
      </section>

      <!-- Assistidos -->
      <section class="glass rounded-2xl p-6">
        <h2 class="text-2xl font-bold mb-4">Assistidos <span class="text-sm text-[#999] font-normal">(<?= count($watched) ?>)</span></h2>
        <div class="space-y-3">
          <?php foreach($watched as $m): ?>
          <div class="bg-[#1a1a1a] border border-[#333] rounded-xl p-4">
            <div class="flex justify-between items-start mb-2">
              <p class="font-bold flex-1"><?= htmlspecialchars($m['title']) ?></p>
              <form method="post" class="ml-2">
                <input type="hidden" name="delete" value="1">
                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                <button class="text-red-500 hover:text-red-400 text-sm">✕</button>
              </form>
            </div>
            <?php if($m['rating']): ?>
              <p class="text-sm"><?= str_repeat('⭐', $m['rating']) ?></p>
            <?php endif; ?>
            <?php if($m['notes']): ?>
              <p class="text-xs text-[#999] mt-1"><?= htmlspecialchars($m['notes']) ?></p>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
          <?php if(empty($watched)): ?>
            <p class="text-center text-[#999] py-8">Nenhum filme assistido</p>
          <?php endif; ?>
        </div>
      </section>
    </div>
  </main>
</body>
</html>
