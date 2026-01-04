<?php
// nosso2026/index.php - Página pública (sem login), usando o mesmo banco
require_once __DIR__ . '/_bootstrap.php';

// Stats gerais
$totalGoals = $pdo->query("SELECT COUNT(*) FROM nosso2026_goals")->fetchColumn();
$doneGoals = $pdo->query("SELECT COUNT(*) FROM nosso2026_goals WHERE progress=100")->fetchColumn();
$nextEvents = $pdo->query("SELECT title, start_date FROM events WHERE group_id='nosso2026' AND start_date>=NOW() ORDER BY start_date ASC LIMIT 5")->fetchAll();

// Finanças do mês atual
$m=intval(date('m')); $y=intval(date('Y'));
$categories = ['Aluguel', 'Condominio/Agua', 'Internet', 'Luz', 'Gás', 'Outro'];
$expenses = $pdo->query("SELECT category, SUM(amount) as total FROM nosso2026_finances WHERE month=$m AND year=$y GROUP BY category")->fetchAll(PDO::FETCH_KEY_PAIR);
$totalExpenses = array_sum($expenses);
$eachExpenses = $totalExpenses / 2;

$marketItems = $pdo->query("SELECT * FROM nosso2026_market_list WHERE done=0 ORDER BY id DESC LIMIT 10")->fetchAll();
$movieItems = $pdo->query("SELECT * FROM nosso2026_movies WHERE status='planejado' OR status IS NULL ORDER BY id DESC LIMIT 10")->fetchAll();

// POST para mercado (adicionar/marcar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['add_market'])) {
    $stmt = $pdo->prepare("INSERT INTO nosso2026_market_list (item, qty, notes) VALUES (?,?,?)");
    $stmt->execute([trim($_POST['item']), trim($_POST['qty']), trim($_POST['notes'])]);
  } elseif (isset($_POST['done_market'])) {
    $pdo->prepare("UPDATE nosso2026_market_list SET done=1 WHERE id=?")->execute([intval($_POST['id'])]);
  } elseif (isset($_POST['add_movie'])) {
    $stmt = $pdo->prepare("INSERT INTO nosso2026_movies (title, status) VALUES (?, 'planejado')");
    $stmt->execute([trim($_POST['title'])]);
  } elseif (isset($_POST['del_movie'])) {
    $pdo->prepare("DELETE FROM nosso2026_movies WHERE id=?")->execute([intval($_POST['id'])]);
  }
  header('Location: ' . n26_link('index.php'));
  exit;
}

// Progresso do ano
$progress = 0;
$yearStart = strtotime('2026-01-01');
$yearEnd = strtotime('2026-12-31 23:59:59');
$now = time();
if ($now < $yearStart) { $progress = 0; }
elseif ($now > $yearEnd) { $progress = 100; }
else { $progress = round((($now - $yearStart)/($yearEnd - $yearStart))*100); }
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#000000">
  <link rel="icon" href="<?= n26_link('icons/icon-192.png') ?>">
  <link rel="manifest" href="<?= n26_link('manifest.json') ?>">
  <link rel="apple-touch-icon" href="<?= n26_link('icons/apple-touch-icon.png') ?>">
  <link rel="stylesheet" href="<?= n26_link('responsive.css') ?>">
  <title>Nosso 2026</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={darkMode:'class',theme:{extend:{fontFamily:{sans:['Outfit','sans-serif']},colors:{darkbg:'#0f172a',cardbg:'#111827',primary:'#a855f7'}}}};</script>
  <style>
    body{background:#000;color:#fff;font-family:'Outfit', sans-serif}
    .glass{background:#0a0a0a;border:1px solid #222}
    .btn{background:#fff;color:#000;padding:.75rem 1.25rem;border-radius:.75rem;font-weight:700}
    .section{scroll-margin-top:6rem}
    input, select, textarea {
      background:#1a1a1a !important;
      border:1px solid #333 !important;
      color:#fff !important;
    }
  </style>
</head>
<body class="min-h-screen">
  <!-- Topbar -->
  <?php include __DIR__ . '/_nav.php'; ?>

  <main class="max-w-7xl mx-auto px-4 py-10">
    <!-- Barra de progresso do ano -->
    <section class="glass p-6 rounded-2xl mb-8">
      <div class="flex justify-between items-center mb-2">
        <h2 class="text-2xl font-bold">2026</h2>
        <span class="text-sm text-[#999]"><?= $progress ?>% completo</span>
      </div>
      <div class="w-full h-2 bg-[#1a1a1a] rounded-full overflow-hidden">
        <div style="width: <?= $progress ?>%" class="h-full bg-white"></div>
      </div>
    </section>

    <!-- Grid de Widgets (3 por linha) -->
    <div class="grid md:grid-cols-3 gap-6 mb-6">
      <!-- Metas -->
      <div class="glass p-6 rounded-2xl">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-xl font-bold">Metas</h3>
          <a href="<?= n26_link('goals.php') ?>" class="text-xs hover:text-gray-300">Ver todas →</a>
        </div>
        <div class="text-center py-6">
          <div class="text-5xl font-bold mb-2"><?= $doneGoals ?><span class="text-2xl text-[#999]">/<?= $totalGoals ?></span></div>
          <p class="text-sm text-[#999]">concluídas</p>
        </div>
        <div class="w-full h-2 bg-[#1a1a1a] rounded-full overflow-hidden">
          <div style="width: <?= $totalGoals>0 ? round(($doneGoals/$totalGoals)*100) : 0 ?>%" class="h-full bg-white"></div>
        </div>
      </div>

      <!-- Finanças do Mês -->
      <div class="glass p-6 rounded-2xl">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-xl font-bold">Finanças - <?= date('M/Y') ?></h3>
          <a href="<?= n26_link('finances.php') ?>" class="text-xs hover:text-gray-300">Ver todas →</a>
        </div>
        <div class="space-y-2 text-sm">
          <?php foreach($categories as $cat): ?>
            <div class="flex justify-between items-center">
              <span class="text-[#999]"><?= $cat ?></span>
              <span class="font-semibold text-white">R$ <?= number_format($expenses[$cat] ?? 0, 2, ',', '.') ?></span>
            </div>
          <?php endforeach; ?>
          <div class="border-t border-[#222] pt-3 mt-3">
            <div class="flex justify-between items-center mb-1">
              <span class="font-bold">Total</span>
              <span class="font-bold text-white">R$ <?= number_format($totalExpenses, 2, ',', '.') ?></span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-[#999] text-xs">Cada</span>
              <span class="font-semibold text-[#999] text-xs">R$ <?= number_format($eachExpenses, 2, ',', '.') ?></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Próximos Eventos -->
      <div class="glass p-6 rounded-2xl">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-xl font-bold">Próximos</h3>
          <a href="<?= n26_link('calendar.php') ?>" class="text-xs hover:text-gray-300">Ver calendário →</a>
        </div>
        <div class="space-y-3">
          <?php if(empty($nextEvents)): ?>
            <p class="text-sm text-[#999] text-center py-4">Nenhum evento agendado</p>
          <?php else: foreach($nextEvents as $e): ?>
            <div class="flex gap-3">
              <div class="text-center">
                <div class="text-2xl font-bold"><?= date('d', strtotime($e['start_date'])) ?></div>
                <div class="text-xs text-[#999]"><?= date('M', strtotime($e['start_date'])) ?></div>
              </div>
              <div class="flex-1 border-l border-[#222] pl-3">
                <p class="text-sm font-semibold"><?= htmlspecialchars($e['title']) ?></p>
                <p class="text-xs text-[#999]"><?= date('H:i', strtotime($e['start_date'])) ?></p>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <!-- Segunda linha: Mercado e Filmes -->
    <div class="grid md:grid-cols-2 gap-6 mb-8">
      <!-- Lista de Mercado -->
      <div class="glass p-6 rounded-2xl">
        <h3 class="text-xl font-bold mb-3">Mercado</h3>
        <form method="post" class="flex gap-2 mb-3">
          <input type="hidden" name="add_market" value="1">
          <input name="item" class="flex-1 bg-[#1a1a1a] border border-[#333] rounded-lg p-2 text-white text-sm" placeholder="Item" required>
          <button class="btn text-sm">OK</button>
        </form>
        <div class="space-y-2">
          <?php foreach($marketItems as $it): ?>
          <form method="post" class="flex items-center justify-between bg-[#1a1a1a] border border-[#333] rounded-lg p-2">
            <span class="text-sm font-semibold flex-1"><?= htmlspecialchars($it['item']) ?></span>
            <input type="hidden" name="done_market" value="1">
            <input type="hidden" name="id" value="<?= $it['id'] ?>">
            <button class="text-xs text-green-400 hover:text-green-300">✓</button>
          </form>
          <?php endforeach; ?>
          <?php if(empty($marketItems)): ?>
            <p class="text-xs text-[#999] text-center py-2">Vazio</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Filmes (ao lado do Mercado) -->
      <div class="glass p-6 rounded-2xl">
        <h3 class="text-xl font-bold mb-3">Filmes/Series</h3>
        <form method="post" class="flex gap-2 mb-3">
          <input type="hidden" name="add_movie" value="1">
          <input name="title" class="flex-1 bg-[#1a1a1a] border border-[#333] rounded-lg p-2 text-white text-sm" placeholder="Título" required>
          <button class="btn text-sm">OK</button>
        </form>
        <div class="space-y-2">
          <?php foreach($movieItems as $mv): ?>
          <form method="post" class="flex items-center justify-between bg-[#1a1a1a] border border-[#333] rounded-lg p-2">
            <span class="text-sm font-semibold flex-1 truncate"><?= htmlspecialchars($mv['title']) ?></span>
            <input type="hidden" name="del_movie" value="1">
            <input type="hidden" name="id" value="<?= $mv['id'] ?>">
            <button class="text-xs text-red-400 hover:text-red-300" title="Remover">X</button>
          </form>
          <?php endforeach; ?>
          <?php if(empty($movieItems)): ?>
            <p class="text-xs text-[#999] text-center py-2">Vazio</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>

  <footer class="max-w-6xl mx-auto px-4 py-10 text-center text-[#999]">
    <p>Nosso 2026 • Parte do LifeOS • <?= date('Y') ?></p>
  </footer>
</body>
</html>
